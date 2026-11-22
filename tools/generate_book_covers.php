<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config.php';

$outputDir = dirname(__DIR__) . '/uploads/products';
if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Could not create the product cover directory.');
}

$themes = [
    1 => ['#173f35', '#4e8a68', '#f0d7a2', 'LITERATURE'],
    2 => ['#242746', '#5e5b91', '#f4d68b', 'FICTION'],
    3 => ['#143d4c', '#258b88', '#f4c66a', 'BUSINESS'],
    4 => ['#284d58', '#5a9b91', '#f5d18a', 'WELLBEING'],
    5 => ['#2f7290', '#78c6d0', '#ffe28b', 'CHILDREN'],
    6 => ['#592d74', '#b24e88', '#ffd45e', 'COMIC'],
    7 => ['#285582', '#549bc0', '#ffcf73', 'LANGUAGE'],
    8 => ['#59452e', '#a88452', '#ecd49a', 'HISTORY'],
    9 => ['#163b69', '#398fb1', '#85d8d0', 'SCIENCE'],
    10 => ['#753f52', '#d0776f', '#ffcf87', 'ART & DESIGN'],
];

function xmlText(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function characterLength(string $value): int
{
    preg_match_all('/./us', $value, $characters);
    return count($characters[0]);
}

function wrapCoverTitle(string $title, int $limit): array
{
    $words = preg_split('/\s+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY);
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && characterLength($candidate) > $limit) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines;
}

function coverArtwork(int $categoryId, string $accent, int $variant): string
{
    $offset = ($variant % 3) * 24;

    switch ($categoryId) {
        case 1:
            return '<path d="M128 440 C210 330 330 340 440 180 M210 344 C152 302 120 286 82 294 M286 294 C276 242 282 207 310 174 M362 242 C420 258 455 285 478 326" fill="none" stroke="' . $accent . '" stroke-width="13" stroke-linecap="round"/><ellipse cx="143" cy="304" rx="43" ry="18" transform="rotate(28 143 304)" fill="' . $accent . '" opacity=".85"/><ellipse cx="310" cy="184" rx="44" ry="18" transform="rotate(-54 310 184)" fill="' . $accent . '" opacity=".9"/><ellipse cx="451" cy="319" rx="44" ry="18" transform="rotate(44 451 319)" fill="' . $accent . '" opacity=".75"/>';
        case 2:
            return '<circle cx="' . (390 - $offset) . '" cy="225" r="78" fill="' . $accent . '"/><circle cx="' . (420 - $offset) . '" cy="202" r="72" fill="#242746" opacity=".65"/><path d="M68 390 Q175 300 280 385 T530 365" fill="none" stroke="' . $accent . '" stroke-width="5" opacity=".75"/><path d="M86 426 L164 294 237 398 323 270 494 430Z" fill="' . $accent . '" opacity=".28"/><circle cx="125" cy="206" r="6" fill="' . $accent . '"/><circle cx="282" cy="153" r="5" fill="' . $accent . '"/><circle cx="490" cy="260" r="7" fill="' . $accent . '"/>';
        case 3:
            return '<path d="M96 426V334h66v92 M198 426V278h66v148 M300 426V218h66v208 M402 426V160h66v266" fill="' . $accent . '" opacity=".72"/><path d="M90 250 L210 205 300 235 442 116" fill="none" stroke="' . $accent . '" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M405 118h38v38" fill="none" stroke="' . $accent . '" stroke-width="10"/>';
        case 4:
            return '<circle cx="300" cy="280" r="116" fill="none" stroke="' . $accent . '" stroke-width="4" opacity=".7"/><circle cx="300" cy="280" r="82" fill="none" stroke="' . $accent . '" stroke-width="3" opacity=".5"/><path d="M300 128v58 M300 374v58 M148 280h58 M394 280h58" stroke="' . $accent . '" stroke-width="7" stroke-linecap="round"/><path d="M300 280 L354 225 M300 280 L248 323" stroke="' . $accent . '" stroke-width="8" stroke-linecap="round"/><circle cx="300" cy="280" r="13" fill="' . $accent . '"/>';
        case 5:
            return '<path d="M88 360 Q178 300 268 360 T448 360V450H88Z" fill="' . $accent . '" opacity=".42"/><path d="M120 210 Q150 167 180 210 Q210 167 240 210 Q270 167 300 210" fill="none" stroke="' . $accent . '" stroke-width="8" stroke-linecap="round"/><path d="M162 310L300 194 438 310 300 407Z" fill="' . $accent . '" opacity=".82"/><path d="M300 194V407" stroke="#fff" stroke-width="5" opacity=".65"/><circle cx="430" cy="182" r="29" fill="' . $accent . '"/>';
        case 6:
            return '<path d="M70 195L260 230 90 273 275 288 82 352 302 333 108 414 365 355" fill="none" stroke="' . $accent . '" stroke-width="12" stroke-linecap="round" opacity=".8"/><path d="M334 160l19 44 47 5-35 31 10 46-41-24-41 24 10-46-35-31 47-5Z" fill="' . $accent . '"/><circle cx="164" cy="167" r="13" fill="' . $accent . '"/><circle cx="449" cy="363" r="10" fill="' . $accent . '"/>';
        case 7:
            return '<rect x="92" y="182" width="220" height="128" rx="32" fill="' . $accent . '" opacity=".88"/><path d="M148 310l-23 39 72-39" fill="' . $accent . '"/><rect x="251" y="278" width="238" height="126" rx="32" fill="#fff" opacity=".18" stroke="' . $accent . '" stroke-width="5"/><path d="M426 404l28 38-73-38" fill="#fff" opacity=".18"/><text x="202" y="265" text-anchor="middle" font-size="61" font-weight="700" fill="#fff">Aa</text><text x="370" y="361" text-anchor="middle" font-size="43" font-weight="700" fill="' . $accent . '">文</text>';
        case 8:
            return '<path d="M118 420V246h364v174 M91 246h418L300 150Z" fill="' . $accent . '" opacity=".72"/><path d="M165 267v139 M233 267v139 M300 267v139 M367 267v139 M435 267v139" stroke="#fff" stroke-width="9" opacity=".72"/><path d="M95 432h410" stroke="' . $accent . '" stroke-width="18" stroke-linecap="round"/>';
        case 9:
            return '<circle cx="300" cy="292" r="26" fill="' . $accent . '"/><ellipse cx="300" cy="292" rx="174" ry="74" fill="none" stroke="' . $accent . '" stroke-width="6" transform="rotate(-25 300 292)"/><ellipse cx="300" cy="292" rx="174" ry="74" fill="none" stroke="' . $accent . '" stroke-width="6" transform="rotate(35 300 292)"/><circle cx="440" cy="186" r="13" fill="' . $accent . '"/><circle cx="151" cy="358" r="10" fill="' . $accent . '"/><circle cx="332" cy="452" r="11" fill="' . $accent . '"/>';
        case 10:
            return '<path d="M108 367 C168 215 226 415 286 244 S401 187 488 315" fill="none" stroke="' . $accent . '" stroke-width="30" stroke-linecap="round" opacity=".78"/><circle cx="160" cy="198" r="47" fill="' . $accent . '" opacity=".75"/><circle cx="408" cy="390" r="59" fill="' . $accent . '" opacity=".58"/><path d="M145 416 Q300 365 451 423" fill="none" stroke="' . $accent . '" stroke-width="8" stroke-linecap="round"/>';
    }

    return '<circle cx="300" cy="290" r="120" fill="' . $accent . '" opacity=".55"/>';
}

function makeCover(array $product, array $theme): string
{
    [$dark, $light, $accent, $label] = $theme;
    $title = (string)$product['name'];
    $fontSize = characterLength($title) > 48 ? 35 : 42;
    $lines = wrapCoverTitle($title, $fontSize === 35 ? 25 : 21);
    if (count($lines) > 4) {
        $fontSize = 31;
        $lines = wrapCoverTitle($title, 28);
    }

    $titleY = 650 + max(0, 4 - count($lines)) * 17;
    $titleMarkup = '';
    foreach ($lines as $index => $line) {
        $titleMarkup .= '<tspan x="58" y="' . ($titleY + $index * ($fontSize + 15)) . '">' . xmlText($line) . '</tspan>';
    }

    $category = xmlText((string)$product['category_name']);
    $safeLabel = xmlText($label);
    $art = coverArtwork((int)$product['category_id'], $accent, (int)$product['id']);

    return '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="900" viewBox="0 0 600 900">'
        . '<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop stop-color="' . $dark . '"/><stop offset="1" stop-color="' . $light . '"/></linearGradient>'
        . '<pattern id="grain" width="24" height="24" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="#fff" opacity=".12"/></pattern></defs>'
        . '<rect width="600" height="900" fill="url(#bg)"/><rect width="600" height="900" fill="url(#grain)"/>'
        . '<path d="M0 0h600v74H0z" fill="#101b20" opacity=".22"/>'
        . '<text x="58" y="47" fill="#fff" font-family="Arial,sans-serif" font-size="19" letter-spacing="3" font-weight="700">' . $safeLabel . '</text>'
        . '<text x="542" y="47" text-anchor="end" fill="#fff" opacity=".76" font-family="Arial,sans-serif" font-size="16">HIEN SACH</text>'
        . '<circle cx="300" cy="294" r="218" fill="#fff" opacity=".055"/>'
        . $art
        . '<path d="M0 568 Q150 520 300 562 T600 548V900H0Z" fill="#fff" opacity=".94"/>'
        . '<path d="M0 568 Q150 520 300 562 T600 548" fill="none" stroke="' . $accent . '" stroke-width="5" opacity=".9"/>'
        . '<text fill="' . $dark . '" font-family="DejaVu Sans,Arial,sans-serif" font-size="' . $fontSize . '" font-weight="700" letter-spacing=".2">' . $titleMarkup . '</text>'
        . '<path d="M58 824h86" stroke="' . $accent . '" stroke-width="6" stroke-linecap="round"/>'
        . '<text x="58" y="860" fill="' . $dark . '" opacity=".7" font-family="Arial,sans-serif" font-size="16" letter-spacing="2">TUYEN CHON SACH HAY</text>'
        . '<text x="542" y="860" text-anchor="end" fill="' . $dark . '" opacity=".6" font-family="Arial,sans-serif" font-size="15">HIEN SACH</text>'
        . '</svg>';
}

$products = $pdo->query(
    'SELECT p.id, p.category_id, p.name, c.name AS category_name
     FROM products AS p
     INNER JOIN categories AS c ON c.id = p.category_id
     WHERE p.description LIKE "Sách thuộc danh mục %"
     ORDER BY p.id'
)->fetchAll(PDO::FETCH_ASSOC);

$update = $pdo->prepare('UPDATE products SET image = ? WHERE id = ?');
$generated = 0;
$pdo->beginTransaction();

foreach ($products as $product) {
    if (strpos((string)($product['image'] ?? ''), 'openlibrary:') === 0) {
        continue;
    }

    $theme = $themes[(int)$product['category_id']] ?? ['#344454', '#708090', '#e3c98a', 'BOOKS'];
    $filename = 'cover-product-' . (int)$product['id'] . '.svg';
    $written = file_put_contents($outputDir . '/' . $filename, makeCover($product, $theme), LOCK_EX);
    if ($written === false) {
        $pdo->rollBack();
        throw new RuntimeException('Could not write cover file: ' . $filename);
    }

    $update->execute([$filename, $product['id']]);
    $generated++;
}

$pdo->commit();
echo 'Generated ' . $generated . ' illustrative covers.' . PHP_EOL;
