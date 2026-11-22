<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
$needle = vn_normalize(mb_substr($q, 0, 100, 'UTF-8'));
$tokens = array_values(array_filter(explode(' ', $needle), fn($t) => $t !== ''));
if (!$tokens) {
    echo json_encode(['products' => [], 'publishers' => []]);
    exit;
}

$scored = [];
$publishers = [];
try {
    $rows = $pdo->query("SELECT p.id, p.name, p.author, p.publisher, p.price, p.discount_price, p.image
        FROM products p LEFT JOIN categories c ON c.id = p.category_id
        WHERE c.status = 1 OR p.category_id IS NULL")->fetchAll();
} catch (\PDOException $e) {
    error_log('Search suggest failed: ' . $e->getMessage());
    echo json_encode(['products' => [], 'publishers' => []]);
    exit;
}

foreach ($rows as $row) {
    $name = vn_normalize((string)$row['name']);
    $pub = vn_normalize((string)$row['publisher']);
    $hay = ' ' . $name . ' ' . vn_normalize((string)$row['author']);

    $pubMatch = true;
    foreach ($tokens as $t) {
        if (strpos(' ' . $pub, ' ' . $t) === false) {
            $pubMatch = false;
            break;
        }
    }
    $pubRaw = trim((string)$row['publisher']);
    if ($pubMatch && $pubRaw !== '') {
        $publishers[$pubRaw] = true;
    }

    $ok = true;
    foreach ($tokens as $t) {
        if (strpos($hay, ' ' . $t) === false) {
            $ok = false;
            break;
        }
    }
    if (!$ok) {
        continue;
    }

    $score = 0;
    if (strpos(' ' . $name, ' ' . $needle) !== false) {
        $score += 100;
    }
    if (strpos($name, $tokens[0]) === 0) {
        $score += 50;
    }
    $scored[(int)$row['id']] = ['score' => $score, 'row' => $row];
}

uasort($scored, fn($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['row']['name'], $b['row']['name']));

$products = [];
foreach (array_slice($scored, 0, 8) as $item) {
    $r = $item['row'];
    $price = (float)$r['price'];
    $disc = (float)$r['discount_price'];
    $products[] = [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'author' => $r['author'],
        'price' => number_format(($disc > 0 && $disc < $price) ? $disc : $price, 0, ',', '.') . 'đ',
        'image' => product_image_url($r['image'] ?? null),
    ];
}

echo json_encode([
    'products' => $products,
    'publishers' => array_slice(array_keys($publishers), 0, 3),
    'total' => count($scored),
], JSON_UNESCAPED_UNICODE);
