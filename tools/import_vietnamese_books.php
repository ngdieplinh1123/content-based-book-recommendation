<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
foreach (array_slice($argv ?? [], 1) as $argument) {
    if ($argument !== '--dry-run') {
        throw new InvalidArgumentException('Usage: php tools/import_vietnamese_books.php [--dry-run]');
    }
}

$sources = [
    1 => [
        'query' => 'author_name:("Nguyen Nhat Anh" OR "Bảo Ninh" OR "Viet Thanh Nguyen" OR "Kim Thuy" OR "Duong Thu Huong" OR "Nguyen Phan Que Mai" OR "Nguyen Ngoc Tu" OR "Nguyen Huy Thiep" OR "Le Minh Khue" OR "Chu Lai" OR "Ma Van Khang")',
        'authors' => ['bao ninh', 'chu lai', 'duong thu huong', 'kim thuy', 'le minh khue', 'ma van khang', 'nguyen huy thiep', 'nguyen nhat anh', 'nguyen ngoc tu', 'nguyen phan que mai', 'phan que mai nguyen', 'viet thanh nguyen'],
    ],
    2 => [
        'query' => 'author_name:("Nguyen Nhat Anh" OR "Bảo Ninh" OR "Viet Thanh Nguyen" OR "Kim Thuy" OR "Duong Thu Huong" OR "Nguyen Phan Que Mai") AND subject:fiction',
        'authors' => ['bao ninh', 'duong thu huong', 'kim thuy', 'nguyen nhat anh', 'nguyen phan que mai', 'phan que mai nguyen', 'viet thanh nguyen'],
    ],
    3 => [
        'query' => 'author_name:("Nguyen Hien Le" OR "Phan Van Truong" OR "Tony Buoi Sang") AND subject:business',
        'authors' => ['nguyen hien le', 'phan van truong', 'tony buoi sang'],
    ],
    4 => [
        'query' => 'author_name:("Thich Nhat Hanh" OR "Nguyen Hien Le" OR "Minh Niem") AND subject:"self-help"',
        'authors' => ['minh niem', 'nguyen hien le', 'thich nhat hanh'],
    ],
    5 => [
        'query' => 'author_name:("Nguyen Nhat Anh" OR "To Hoai" OR "Doan Gioi" OR "Vo Quang" OR "Nguyen Hong") AND subject:"juvenile fiction"',
        'authors' => ['doan gioi', 'nguyen hong', 'nguyen nhat anh', 'to hoai', 'vo quang'],
    ],
    6 => [
        'query' => 'author_name:("Thi Bui" OR "Trung Le Nguyen" OR "Nguyen Nhat Anh") AND subject:comics',
        'authors' => ['nguyen nhat anh', 'thi bui', 'trung le nguyen'],
    ],
    7 => [
        'query' => 'author_name:("Mai Lan Huong" OR "Le Huy Lam" OR "Nguyen Thanh Yen" OR "Bui Phung") AND subject:"English language -- textbooks for foreign speakers"',
        'authors' => ['bui phung', 'le huy lam', 'mai lan huong', 'nguyen thanh yen'],
    ],
    8 => [
        'query' => 'author_name:("Tran Trong Kim" OR "Nguyen Khac Vien" OR "Le Thanh Khoi" OR "Duong Trung Quoc") AND subject:history',
        'authors' => ['duong trung quoc', 'le thanh khoi', 'nguyen khac vien', 'tran trong kim'],
    ],
    9 => [
        'query' => 'author_name:("Nguyen Van Hieu" OR "Tran Nam Dung" OR "Nguyen Van Tuan") AND subject:science',
        'authors' => ['nguyen van hieu', 'nguyen van tuan', 'tran nam dung'],
    ],
    10 => [
        'query' => 'author_name:("Bui Xuan Phai" OR "Nguyen Quan" OR "Tran Luong" OR "Thi Bui") AND subject:art',
        'authors' => ['bui xuan phai', 'nguyen quan', 'thi bui', 'tran luong'],
    ],
];

function openLibraryRequest(string $url): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Catbook local bookstore catalog/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $body = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($body === false || $status !== 200) {
        throw new RuntimeException('Open Library request failed (' . $status . '): ' . ($error ?: $url));
    }

    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !isset($data['docs']) || !is_array($data['docs'])) {
        throw new RuntimeException('Open Library returned an invalid catalog response.');
    }

    return $data['docs'];
}

function normalizedText(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii === false) {
        throw new RuntimeException('Could not normalize book metadata.');
    }

    return trim(preg_replace('/[^a-z0-9]+/', ' ', $ascii));
}

function cleanBookTitle(string $title, string $workId): string
{
    $titleCorrections = [
        'OL1026807W' => 'Việt Nam sử lược',
        'OL2448894W' => 'Tiểu thuyết vô đề',
    ];

    return $titleCorrections[$workId] ?? trim($title);
}

function selectVietnameseBooks(array $records, array $allowedAuthors, array &$usedWorks, array &$usedTitles, PDOStatement $titleComparator): array
{
    $selected = [];

    foreach ($records as $record) {
        if (!is_array($record) || empty($record['title']) || !is_string($record['title'])) {
            continue;
        }

        $coverId = $record['cover_i'] ?? null;
        $key = (string)($record['key'] ?? '');
        $authors = $record['author_name'] ?? [];
        if (
            !is_numeric($coverId)
            || (int)$coverId <= 0
            || !preg_match('~^/works/(OL\d+W)$~D', $key, $workMatch)
            || !is_array($authors)
            || preg_match('/[ăâđơưĂÂĐƠƯấầẩẫậắằẳẵặếềểễệốồổỗộớờởỡợứừửữự]/u', $record['title']) !== 1
        ) {
            continue;
        }

        $author = '';
        foreach ($authors as $candidate) {
            if (is_string($candidate) && in_array(normalizedText($candidate), $allowedAuthors, true)) {
                $author = trim($candidate);
                break;
            }
        }
        if ($author === '') {
            continue;
        }

        $workId = $workMatch[1];
        $title = cleanBookTitle($record['title'], $workId);
        $normalizedTitle = normalizedText($title);
        if ($normalizedTitle === '' || isset($usedWorks[$workId]) || isset($usedTitles[$normalizedTitle])) {
            continue;
        }

        foreach ($usedTitles as $previousTitle) {
            $titleComparator->execute([$title, $previousTitle]);
            if ((int)$titleComparator->fetchColumn() === 1) {
                continue 2;
            }
        }

        $usedWorks[$workId] = true;
        $usedTitles[$normalizedTitle] = $title;
        $selected[] = [
            'title' => $title,
            'author' => $author,
            'cover_id' => (int)$coverId,
            'work_id' => $workId,
        ];
        if (count($selected) === 20) {
            break;
        }
    }

    return $selected;
}

$targetRowsByCategory = [];
$targetIds = [];
foreach ($sources as $categoryId => $_source) {
    $targetRowsByCategory[$categoryId] = [];
}
$targetQuery = $pdo->query(
    "SELECT id, category_id, image
     FROM products
     WHERE id > 16
       AND image LIKE 'cover-product-%'
     ORDER BY id"
);
foreach ($targetQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $categoryId = (int)$row['category_id'];
    if (isset($targetRowsByCategory[$categoryId])) {
        $targetRowsByCategory[$categoryId][] = [
            'id' => (int)$row['id'],
            'image' => (string)$row['image'],
        ];
        $targetIds[] = (int)$row['id'];
    }
}

if ($targetIds !== []) {
    $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
    $orderedProducts = $pdo->prepare(
        "SELECT COUNT(*)
         FROM order_details
         WHERE product_id IN ($placeholders)"
    );
    $orderedProducts->execute($targetIds);
    if ((int)$orderedProducts->fetchColumn() > 0) {
        throw new RuntimeException('Sample products are present in order history. No products were changed.');
    }
}

$categoryNames = $pdo->query('SELECT id, name FROM categories WHERE status = 1')->fetchAll(PDO::FETCH_KEY_PAIR);
$titleComparator = $pdo->prepare(
    'SELECT CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci'
);
$usedWorks = [];
$usedTitles = [];
$selectedByCategory = [];

foreach ([1, 2, 4, 5, 6, 8, 3, 7, 9, 10] as $categoryId) {
    $source = $sources[$categoryId];
    $query = http_build_query([
        'q' => $source['query'],
        'limit' => 100,
        'fields' => 'key,title,author_name,cover_i',
    ]);
    $records = openLibraryRequest('https://openlibrary.org/search.json?' . $query);
    $selectedByCategory[$categoryId] = selectVietnameseBooks(
        $records,
        $source['authors'],
        $usedWorks,
        $usedTitles,
        $titleComparator
    );
    sleep(1);
}

$shortages = [];
foreach ($sources as $categoryId => $_source) {
    $targetCount = count($targetRowsByCategory[$categoryId]);
    $selectedCount = count($selectedByCategory[$categoryId]);
    echo ($categoryNames[$categoryId] ?? ('Category ' . $categoryId))
        . ': ' . $selectedCount . '/' . $targetCount . PHP_EOL;
    if ($selectedCount !== $targetCount) {
        $shortages[$categoryId] = [$selectedCount, $targetCount];
    }
}

if ($dryRun) {
    echo $shortages === []
        ? 'Dry run passed; no database changes were made.' . PHP_EOL
        : 'Dry run found shortages; no database changes were made.' . PHP_EOL;
    exit($shortages === [] ? 0 : 2);
}

if ($shortages !== []) {
    throw new RuntimeException('Not enough verified Vietnamese books with covers. No database changes were made; run with --dry-run for details.');
}

$update = $pdo->prepare(
    'UPDATE products
     SET name = ?, image = ?, description = ?
     WHERE id = ? AND category_id = ? AND image = ?'
);
$updatedCount = 0;

$pdo->beginTransaction();
try {
    foreach ($sources as $categoryId => $_source) {
        $productIds = $targetRowsByCategory[$categoryId];
        $books = $selectedByCategory[$categoryId];

        foreach ($books as $index => $book) {
            $target = $productIds[$index];
            $safeAuthor = htmlspecialchars($book['author'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeCategory = htmlspecialchars((string)$categoryNames[$categoryId], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $workUrl = 'https://openlibrary.org/works/' . $book['work_id'];
            $description = '<p><strong>Tác giả:</strong> ' . $safeAuthor . '</p>'
                . '<p>Danh mục: ' . $safeCategory . '</p>'
                . '<p>Nguồn thông tin và bìa sách: <a href="' . $workUrl
                . '" target="_blank" rel="noopener noreferrer">Open Library</a>.</p>';

            $image = 'openlibrary:' . $book['cover_id'] . ':' . $book['work_id'];
            if (isset($productIds[$index])) {
                $update->execute([
                    $book['title'],
                    $image,
                    $description,
                    $target['id'],
                    $categoryId,
                    $target['image'],
                ]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Could not update sample product ID ' . $target['id'] . '.');
                }
                $updatedCount++;
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo 'Updated ' . $updatedCount . ' sample books with verified Vietnamese titles and Open Library covers.' . PHP_EOL;
