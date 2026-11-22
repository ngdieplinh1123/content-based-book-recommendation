<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config.php';

$books = [
    [
        'category_id' => 1,
        'name' => 'Mắt Biếc',
        'price' => 105000,
        'discount_price' => 89000,
        'quantity' => 30,
        'image' => 'openlibrary:13258074:OL23402825W',
        'description' => '<p><strong>Tác giả:</strong> Nguyễn Nhật Ánh</p><p>Tiểu thuyết Việt Nam về tuổi thơ, tình bạn và tình yêu đầu đời.</p><p>Nguồn bìa: <a href="https://openlibrary.org/works/OL23402825W" target="_blank" rel="noopener noreferrer">Open Library</a>.</p>',
    ],
    [
        'category_id' => 1,
        'name' => 'Tôi thấy hoa vàng trên cỏ xanh',
        'price' => 110000,
        'discount_price' => 99000,
        'quantity' => 30,
        'image' => 'openlibrary:13860354:OL34960390W',
        'description' => '<p><strong>Tác giả:</strong> Nguyễn Nhật Ánh</p><p>Tác phẩm văn học Việt Nam về tuổi thơ và tình cảm gia đình.</p><p>Nguồn bìa: <a href="https://openlibrary.org/works/OL34960390W" target="_blank" rel="noopener noreferrer">Open Library</a>.</p>',
    ],
    [
        'category_id' => 5,
        'name' => 'Dế Mèn phiêu lưu ký',
        'price' => 79000,
        'discount_price' => 69000,
        'quantity' => 36,
        'image' => 'openlibrary:15204917:OL19948370W',
        'description' => '<p><strong>Tác giả:</strong> Tô Hoài</p><p>Tác phẩm văn học thiếu nhi Việt Nam kể về cuộc phiêu lưu của Dế Mèn.</p><p>Nguồn bìa: <a href="https://openlibrary.org/works/OL19948370W" target="_blank" rel="noopener noreferrer">Open Library</a>.</p>',
    ],
];

$categoryExists = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ? AND status = 1');
$findProduct = $pdo->prepare('SELECT id FROM products WHERE image = ?');
$updateProduct = $pdo->prepare(
    'UPDATE products
     SET category_id = ?, name = ?, price = ?, discount_price = ?, description = ?
     WHERE id = ?'
);
$insertProduct = $pdo->prepare(
    'INSERT INTO products (category_id, name, price, discount_price, quantity, image, description)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);

$updated = 0;
$inserted = 0;
$pdo->beginTransaction();
try {
    foreach ($books as $book) {
        $categoryExists->execute([$book['category_id']]);
        if ((int)$categoryExists->fetchColumn() !== 1) {
            throw new RuntimeException('Category ' . $book['category_id'] . ' is not available.');
        }

        $findProduct->execute([$book['image']]);
        $productId = $findProduct->fetchColumn();
        if ($productId !== false) {
            $updateProduct->execute([
                $book['category_id'],
                $book['name'],
                $book['price'],
                $book['discount_price'],
                $book['description'],
                $productId,
            ]);
            $updated++;
            continue;
        }

        $insertProduct->execute([
            $book['category_id'],
            $book['name'],
            $book['price'],
            $book['discount_price'],
            $book['quantity'],
            $book['image'],
            $book['description'],
        ]);
        $inserted++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo 'Synchronized ' . $updated . ' existing books and added ' . $inserted . ' new books.' . PHP_EOL;
