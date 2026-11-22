<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config.php';

$sourcePath = $argv[1] ?? 'C:/Users/Admin/Downloads/CatBook/catbook/BookStore.db';
$sourceRoot = realpath(dirname($sourcePath));
if ($sourceRoot === false || !is_file($sourcePath)) {
    throw new RuntimeException("SQLite source database not found: {$sourcePath}");
}

$source = new PDO('sqlite:' . $sourcePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$source->exec('PRAGMA query_only=ON');

$sourceCategories = $source->query('SELECT MaTheLoai, TenTheLoai, MoTa FROM TheLoai ORDER BY MaTheLoai')->fetchAll();
$sourceBooks = $source->query(
    'SELECT MaSach, TenSach, TacGia, NhaXuatBan, NamXuatBan, GiaBan, GiaGoc, SoLuongTon, MoTa, HinhAnh, MaTheLoai
     FROM Sach ORDER BY MaSach'
)->fetchAll();

if (!$sourceCategories || !$sourceBooks) {
    throw new RuntimeException('Source database contains no categories or books.');
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->execute([$table, $column]);
    if ((int)$statement->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

ensureColumn($pdo, 'categories', 'description', 'TEXT NULL');
ensureColumn($pdo, 'products', 'author', 'VARCHAR(255) NULL');
ensureColumn($pdo, 'products', 'publisher', 'VARCHAR(255) NULL');
ensureColumn($pdo, 'products', 'publication_year', 'SMALLINT NULL');

$uploadDirectory = __DIR__ . '/../uploads/products';
if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
    throw new RuntimeException("Cannot create cover directory: {$uploadDirectory}");
}

$addedCoverFiles = [];
$imported = 0;
$enriched = 0;
$pdo->beginTransaction();

try {
    $categoryIds = [];
    $findCategory = $pdo->prepare('SELECT id, description FROM categories WHERE name = ? LIMIT 1');
    $insertCategory = $pdo->prepare('INSERT INTO categories (name, description, status) VALUES (?, ?, 1)');
    $updateCategory = $pdo->prepare(
        "UPDATE categories SET description = ? WHERE id = ? AND (description IS NULL OR description = '')"
    );

    foreach ($sourceCategories as $category) {
        $name = trim((string)$category['TenTheLoai']);
        if ($name === '') {
            throw new RuntimeException('Source contains a category with an empty name.');
        }

        $findCategory->execute([$name]);
        $existingCategory = $findCategory->fetch();
        if ($existingCategory) {
            $categoryId = (int)$existingCategory['id'];
            $updateCategory->execute([(string)($category['MoTa'] ?? ''), $categoryId]);
        } else {
            $insertCategory->execute([$name, (string)($category['MoTa'] ?? '')]);
            $categoryId = (int)$pdo->lastInsertId();
        }
        $categoryIds[(int)$category['MaTheLoai']] = $categoryId;
    }

    $findProduct = $pdo->prepare('SELECT id, author, publisher, publication_year, description FROM products WHERE LOWER(name) = LOWER(?)');
    $enrichProduct = $pdo->prepare(
        "UPDATE products SET
            author = COALESCE(NULLIF(author, ''), ?),
            publisher = COALESCE(NULLIF(publisher, ''), ?),
            publication_year = COALESCE(publication_year, ?),
            description = COALESCE(NULLIF(description, ''), ?)
         WHERE id = ?"
    );
    $insertProduct = $pdo->prepare(
        'INSERT INTO products
            (category_id, name, price, discount_price, quantity, image, description, author, publisher, publication_year)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($sourceBooks as $book) {
        $title = trim((string)$book['TenSach']);
        if ($title === '' || !isset($categoryIds[(int)$book['MaTheLoai']])) {
            throw new RuntimeException('Source book has an empty title or an unknown category.');
        }

        $findProduct->execute([$title]);
        $matches = $findProduct->fetchAll();
        if (count($matches) > 1) {
            throw new RuntimeException("Multiple active products match source title: {$title}");
        }
        if ($matches) {
            $enrichProduct->execute([
                trim((string)$book['TacGia']),
                trim((string)($book['NhaXuatBan'] ?? '')),
                $book['NamXuatBan'] === null ? null : (int)$book['NamXuatBan'],
                trim((string)($book['MoTa'] ?? '')),
                (int)$matches[0]['id'],
            ]);
            $enriched++;
            continue;
        }

        $imageValue = trim((string)($book['HinhAnh'] ?? ''));
        $relativeImage = ltrim(str_replace('\\', '/', $imageValue), '/');
        if ($relativeImage === '' || str_contains($relativeImage, '../')) {
            throw new RuntimeException("Invalid image path for source book: {$title}");
        }
        $sourceImage = realpath($sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeImage));
        $sourceRootPrefix = rtrim($sourceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($sourceImage === false || strpos($sourceImage, $sourceRootPrefix) !== 0 || !is_file($sourceImage)) {
            throw new RuntimeException("Source cover is missing or outside the source folder for: {$title}");
        }

        $extension = strtolower(pathinfo($sourceImage, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException("Unsupported cover type for: {$title}");
        }
        $imageName = 'bookstore-' . (int)$book['MaSach'] . '.' . $extension;
        $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $imageName;
        if (!is_file($destination)) {
            if (!copy($sourceImage, $destination)) {
                throw new RuntimeException("Failed to copy cover for: {$title}");
            }
            $addedCoverFiles[] = $destination;
        }

        $listPrice = (int)round((float)($book['GiaGoc'] ?? 0));
        $sellingPrice = (int)round((float)$book['GiaBan']);
        if ($listPrice <= 0) {
            $listPrice = $sellingPrice;
        }
        $discountPrice = $sellingPrice > 0 && $sellingPrice < $listPrice ? $sellingPrice : 0;
        $insertProduct->execute([
            $categoryIds[(int)$book['MaTheLoai']],
            $title,
            $listPrice,
            $discountPrice,
            max(0, (int)$book['SoLuongTon']),
            $imageName,
            trim((string)($book['MoTa'] ?? '')),
            trim((string)$book['TacGia']),
            trim((string)($book['NhaXuatBan'] ?? '')),
            $book['NamXuatBan'] === null ? null : (int)$book['NamXuatBan'],
        ]);
        $imported++;
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($addedCoverFiles as $coverFile) {
        if (is_file($coverFile) && !unlink($coverFile)) {
            error_log("Could not remove failed-import cover: {$coverFile}");
        }
    }
    throw $exception;
}

echo json_encode([
    'source_categories' => count($sourceCategories),
    'source_books' => count($sourceBooks),
    'new_books' => $imported,
    'enriched_existing_books' => $enriched,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
