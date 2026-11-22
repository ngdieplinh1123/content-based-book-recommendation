<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

function bot_reply(string $text, array $products = [], array $chips = []): void
{
    global $pdo;
    $site = site_info($pdo);
    $text = str_replace(['hotro@hiensach.vn', '028.73008182', 'CatBook'], [$site['support_email'], $site['support_phone'], $site['site_name']], $text);
    echo json_encode(['reply' => $text, 'products' => $products, 'chips' => $chips], JSON_UNESCAPED_UNICODE);
    exit;
}

function bot_money(string $num, string $unit): int
{
    $n = (float)str_replace(',', '.', $num);
    if (in_array($unit, ['k', 'nghin', 'ngan'], true)) {
        return (int)round($n * 1000);
    }
    if (in_array($unit, ['tr', 'trieu'], true)) {
        return (int)round($n * 1000000);
    }
    return (int)round($n < 1000 ? $n * 1000 : $n);
}

function bot_vnd(float $n): string
{
    return number_format($n, 0, ',', '.') . 'đ';
}

$raw = mb_substr(trim((string)($_POST['message'] ?? $_GET['q'] ?? '')), 0, 200, 'UTF-8');
$defaultChips = ['Sách đang khuyến mãi', 'Sách dưới 100k', 'Sách thiếu nhi', 'Sách kỹ năng sống'];

if ($raw === '') {
    bot_reply('Meo~ Mình là Mèo CatBook 🐱 Bạn muốn tìm sách gì nào? Cứ nhập tên sách, tác giả, nhà xuất bản, thể loại hoặc khoảng giá nhé!', [], $defaultChips);
}

$norm = vn_normalize($raw);

if (preg_match('/\b(xin chao|chao|hello|hi|alo)\b/', $norm) && str_word_count($norm) <= 3) {
    bot_reply('Chào bạn, Mèo CatBook đây 🐾 Bạn đang muốn tìm cuốn sách nào?', [], $defaultChips);
}
if (preg_match('/\b(cam on|thanks|thank)\b/', $norm)) {
    bot_reply('Không có chi ạ, meo~ Cần tìm thêm sách gì cứ nhắn mình nhé! 😺');
}
if (preg_match('/\b(van chuyen|ship|giao hang|phi giao)\b/', $norm)) {
    bot_reply('CatBook miễn phí vận chuyển cho mọi đơn hàng. Đơn được xử lý và giao đến tận nơi, bạn theo dõi trạng thái trong mục "Đơn hàng" nhé.', [], $defaultChips);
}
if (preg_match('/\b(thanh toan|chuyen khoan|cod|qr)\b/', $norm)) {
    bot_reply('CatBook hỗ trợ thanh toán khi nhận hàng (COD) và chuyển khoản qua mã QR ngân hàng. Bạn chọn khi thanh toán đơn hàng nhé!', [], $defaultChips);
}

// Câu hỏi hỗ trợ (không phải tìm sách). Bỏ qua nếu người dùng nói rõ là đang tìm sách.
$explicitSearch = (bool)preg_match('/\b(tim|kiem|co) (cuon |quyen |quen )?sach\b|\bsach (cua|ve|nxb)\b/', $norm);
$supportTopics = [
    ['/\b(quen mat khau|mat khau|doi mat khau|reset)\b/', 'Nếu quên hoặc cần đổi mật khẩu, bạn vào "Thông tin tài khoản" (menu tên tài khoản góc trên bên phải) để đổi mật khẩu. Nếu vẫn không đăng nhập được, hãy liên hệ hotro@hiensach.vn hoặc gọi 028.73008182 để được hỗ trợ nhé.'],
    ['/\b(dang ky|tao tai khoan|lap tai khoan)\b/', 'Để tạo tài khoản, bạn bấm "Đăng ký" ở góc trên bên phải, điền họ tên, email, mật khẩu rồi xác nhận. Xong là có thể đăng nhập và mua hàng ngay.'],
    ['/\b(dang nhap|khong vao duoc|khong login|login)\b/', 'Khi không đăng nhập được, bạn kiểm tra lại tên đăng nhập/email và mật khẩu (chú ý chữ hoa, chữ thường), thử tải lại trang hoặc dùng trình duyệt khác. Vẫn lỗi thì liên hệ hotro@hiensach.vn hoặc 028.73008182 để Mèo nhờ nhân viên hỗ trợ nhé.'],
    ['/\b(tai khoan|thong tin ca nhan|cap nhat thong tin|doi thong tin|bi khoa)\b/', 'Về tài khoản: bạn vào "Thông tin tài khoản" để xem và cập nhật tên, số điện thoại, địa chỉ. Nếu tài khoản bị lỗi hoặc bị khóa, hãy liên hệ hotro@hiensach.vn hoặc gọi 028.73008182, kèm email đăng ký để được xử lý nhanh nhất.'],
    ['/\b(cach mua|mua nhu the nao|mua sao|mua the nao|huong dan mua|dat hang|cach dat|dat mua|dat don|mua hang)\b/', 'Cách mua sách tại CatBook:|1) Tìm sách rồi bấm "Thêm vào giỏ".|2) Vào Giỏ hàng, kiểm tra số lượng và bấm Thanh toán.|3) Điền địa chỉ nhận hàng, chọn COD hoặc chuyển khoản QR.|4) Bấm đặt hàng và theo dõi trong mục "Đơn hàng". (Cần đăng nhập để đặt hàng.)'],
    ['/\b(kiem tra don|theo doi don|don hang|tra cuu don|lich su mua|trang thai don)\b/', 'Bạn xem đơn hàng ở mục "Đơn hàng" (đăng nhập trước nhé). Trạng thái gồm: Chờ xử lý, Đang xử lý, Đang giao, Hoàn thành và Đã hủy. Đơn chuyển khoản chưa thanh toán sẽ có nút "Thanh toán QR".'],
    ['/\b(huy don|huy hang|doi tra|tra hang|hoan tien|hoan hang|doi sach)\b/', 'Về hủy đơn, đổi trả hoặc hoàn tiền, bạn vui lòng liên hệ hotro@hiensach.vn hoặc 028.73008182 kèm mã đơn hàng để được hỗ trợ sớm nhất nhé.'],
    ['/\b(danh gia|binh luan|nhan xet|review)\b/', 'Bạn chỉ đánh giá được khi đơn hàng đã Hoàn thành. Vào mục "Đơn hàng", chọn số sao và viết nhận xét dưới đơn, đánh giá sẽ áp dụng cho tất cả sách trong đơn đó.'],
    ['/\b(lien he|hotline|sdt|so dien thoai|nhan vien|tu van|hotro|ho tro|goi dien|email)\b/', 'Bạn có thể liên hệ CatBook qua hotro@hiensach.vn hoặc hotline 028.73008182 để được nhân viên hỗ trợ nhé.'],
    ['/\b(loi|bi loi|khong duoc|khong the|hong|treo|khong hien)\b/', 'Tiếc quá, bạn đang gặp sự cố rồi. Bạn thử tải lại trang (Ctrl+F5) hoặc đăng nhập lại; nếu vẫn lỗi, hãy gửi mô tả lỗi tới hotro@hiensach.vn hoặc gọi 028.73008182 để được hỗ trợ nhé.'],
];
if (!$explicitSearch) {
    foreach ($supportTopics as [$re, $answer]) {
        if (preg_match($re, $norm)) {
            bot_reply(str_replace('|', "\n", $answer), [], ['Cách mua sách', 'Đơn hàng của tôi', 'Liên hệ hỗ trợ', 'Tìm sách']);
        }
    }
}
if (preg_match('/^tim sach$/', $norm)) {
    bot_reply('Bạn muốn tìm sách gì nào? Nhập tên sách, tác giả, nhà xuất bản, thể loại hoặc khoảng giá nhé!', [], $defaultChips);
}

try {
    $rows = $pdo->query("SELECT p.id, p.name, p.author, p.publisher, p.price, p.discount_price, p.image, p.quantity, p.category_id, c.name AS category_name
        FROM products p INNER JOIN categories c ON c.id = p.category_id AND c.status = 1")->fetchAll();
    $cats = $pdo->query("SELECT id, name FROM categories WHERE status = 1")->fetchAll();
} catch (\PDOException $e) {
    error_log('Chatbot query failed: ' . $e->getMessage());
    bot_reply('Meo… hệ thống đang bận một chút, bạn thử lại sau nhé.');
}

$work = ' ' . $norm . ' ';
$filters = [];

// Khuyến mãi
$discountOnly = false;
if (preg_match('/\b(khuyen mai|giam gia|sale|uu dai)\b/', $work)) {
    $discountOnly = true;
    $work = preg_replace('/\b(khuyen mai|giam gia|sale|uu dai)\b/', ' ', $work);
}

// Khoảng giá
$min = null;
$max = null;
$moneyRe = '(\d+(?:[.,]\d+)?)\s*(trieu|tr|nghin|ngan|dong|k|d)?\b';
if (preg_match('/\btu ' . $moneyRe . ' (?:den|toi|-) ' . $moneyRe . '\b/', $work, $m)) {
    $min = bot_money($m[1], $m[2] ?: $m[4]);
    $max = bot_money($m[3], $m[4]);
    $work = str_replace($m[0], ' ', $work);
} elseif (preg_match('/\b(?:duoi|nho hon|toi da|khong qua|re hon)\s*' . $moneyRe . '/', $work, $m)) {
    $max = bot_money($m[1], $m[2]);
    $work = str_replace($m[0], ' ', $work);
} elseif (preg_match('/\b(?:tren|lon hon|toi thieu|it nhat)\s*' . $moneyRe . '/', $work, $m)) {
    $min = bot_money($m[1], $m[2]);
    $work = str_replace($m[0], ' ', $work);
}
$cheapest = (bool)preg_match('/\b(re nhat|gia re|re)\b/', $work);
$work = preg_replace('/\b(re nhat|gia re|re)\b/', ' ', $work);

// Danh mục
$catIds = [];
$catNames = [];
foreach ($cats as $c) {
    $full = vn_normalize($c['name']);
    $phrases = array_merge([$full], array_map('vn_normalize', preg_split('/\s+-\s+|\s*[\/,]\s*/u', $c['name'])));
    foreach ($phrases as $ph) {
        if ($ph !== '' && preg_match('/\b' . preg_quote($ph, '/') . '\b/', $work)) {
            $catIds[(int)$c['id']] = true;
            $catNames[$c['name']] = true;
            $work = preg_replace('/\b' . preg_quote($ph, '/') . '\b/', ' ', $work);
        }
    }
}

// Nhà xuất bản (cú pháp "nxb <tên>")
$pubSet = [];
foreach ($rows as $r) {
    $pn = vn_normalize((string)$r['publisher']);
    if ($pn !== '') {
        $pubSet[$pn] = $r['publisher'];
    }
}
$pubMatch = [];
foreach ($pubSet as $pn => $label) {
    $short = trim(preg_replace('/^nxb\s+/', '', $pn));
    if ($short !== '' && preg_match('/\bnxb ' . preg_quote($short, '/') . '\b/', $work)) {
        $pubMatch[$pn] = $label;
        $work = preg_replace('/\bnxb ' . preg_quote($short, '/') . '\b/', ' ', $work);
    }
}

// Từ khóa còn lại
$work = preg_replace('/\b(tim kiem|tim giup|goi y|de xuat|tac gia|muon mua|cho toi|cho minh|co sach|nhung cuon|nhung quyen)\b/', ' ', $work);
$stop = ['tim', 'kiem', 'sach', 'cuon', 'quyen', 'cho', 'toi', 'minh', 'muon', 'mua', 'can', 'giup', 'hay', 'nao', 'nhung', 'cac', 'khong', 'nxb', 'danh', 'muc', 'the', 'loai'];
$tokens = array_values(array_filter(explode(' ', trim($work)), fn($t) => $t !== '' && !in_array($t, $stop, true) && !ctype_digit($t)));

$hasFilter = $discountOnly || $cheapest || $min !== null || $max !== null || $catIds || $pubMatch;

$final = static function (array $r) {
    $price = (float)$r['price'];
    $disc = (float)$r['discount_price'];
    $sell = ($disc > 0 && $disc < $price) ? $disc : $price;
    return $sell;
};

$candidates = [];
foreach ($rows as $r) {
    $sell = $final($r);
    $price = (float)$r['price'];
    $disc = (float)$r['discount_price'];
    if ($discountOnly && !($disc > 0 && $disc < $price)) {
        continue;
    }
    if ($min !== null && $sell < $min) {
        continue;
    }
    if ($max !== null && $sell > $max) {
        continue;
    }
    if ($catIds && !isset($catIds[(int)$r['category_id']])) {
        continue;
    }
    if ($pubMatch && !isset($pubMatch[vn_normalize((string)$r['publisher'])])) {
        continue;
    }
    $score = 0;
    if ($tokens) {
        $name = vn_normalize((string)$r['name']);
        $hay = ' ' . $name . ' ' . vn_normalize((string)$r['author']) . ' ' . vn_normalize((string)$r['publisher']);
        $hits = 0;
        foreach ($tokens as $t) {
            if (strpos($hay, ' ' . $t) !== false) {
                $hits++;
            }
        }
        if ($hits < count($tokens)) {
            continue;
        }
        $score = $hits * 10 + (strpos(' ' . $name, ' ' . implode(' ', $tokens)) !== false ? 50 : 0);
    }
    $r['_score'] = $score;
    $r['_sell'] = $sell;
    $candidates[] = $r;
}

$sortCandidates = static function (array &$list) use ($cheapest, $min, $max, $discountOnly) {
    usort($list, function ($a, $b) use ($cheapest, $max, $discountOnly) {
        if ($a['_score'] !== $b['_score']) {
            return $b['_score'] <=> $a['_score'];
        }
        if ($discountOnly) {
            $da = (float)$a['price'] > 0 ? 1 - $a['_sell'] / (float)$a['price'] : 0;
            $db = (float)$b['price'] > 0 ? 1 - $b['_sell'] / (float)$b['price'] : 0;
            return $db <=> $da;
        }
        if ($cheapest) {
            return $a['_sell'] <=> $b['_sell'];
        }
        if ($max !== null) {
            return $b['_sell'] <=> $a['_sell'];
        }
        return (int)$b['id'] <=> (int)$a['id'];
    });
};
$sortCandidates($candidates);

$toCard = static function (array $r): array {
    $price = (float)$r['price'];
    return [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'author' => $r['author'],
        'price' => bot_vnd($r['_sell'] ?? $price),
        'old_price' => ($r['_sell'] ?? $price) < $price ? bot_vnd($price) : '',
        'image' => product_image_url($r['image'] ?? null),
    ];
};

// Mô tả điều kiện cho câu trả lời
$parts = [];
if ($catNames) {
    $parts[] = 'thể loại ' . implode(', ', array_keys($catNames));
}
if ($pubMatch) {
    $parts[] = 'của ' . implode(', ', $pubMatch);
}
if ($discountOnly) {
    $parts[] = 'đang khuyến mãi';
}
if ($min !== null && $max !== null) {
    $parts[] = 'giá từ ' . bot_vnd($min) . ' đến ' . bot_vnd($max);
} elseif ($max !== null) {
    $parts[] = 'giá dưới ' . bot_vnd($max);
} elseif ($min !== null) {
    $parts[] = 'giá trên ' . bot_vnd($min);
}
if ($cheapest) {
    $parts[] = 'giá rẻ nhất';
}
if ($tokens) {
    $parts[] = 'khớp "' . implode(' ', $tokens) . '"';
}
$desc = $parts ? implode(', ', $parts) : '';

if (!$tokens && !$hasFilter) {
    // Câu hỏi chung chung: gợi ý sách mới
    usort($rows, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
    $rows = array_map(fn($r) => $r + ['_sell' => $final($r)], array_slice($rows, 0, 5));
    bot_reply('Mình chưa rõ bạn cần sách gì, bạn tham khảo vài cuốn mới nhé. Hoặc nói rõ tên sách, tác giả, thể loại, khoảng giá (ví dụ "sách thiếu nhi dưới 80k") nha!', array_map($toCard, $rows), $defaultChips);
}

if ($candidates) {
    $total = count($candidates);
    $shown = array_slice($candidates, 0, 6);
    $link = 'products.php?search=' . rawurlencode($raw);
    $chips = [];
    $msg = 'Mèo tìm được ' . $total . ' cuốn sách' . ($desc ? ' ' . $desc : '') . ' 🐱' . ($total > 6 ? ' Đây là ' . count($shown) . ' cuốn phù hợp nhất:' : ':');
    bot_reply($msg, array_map($toCard, $shown), $chips);
}

// Không có kết quả chính xác: gợi ý sách liên quan
if ($tokens) {
    $res = search_products($pdo, implode(' ', $tokens), 6);
    $ids = array_merge($res['exact'], $res['related']);
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int)$r['id']] = $r + ['_sell' => $final($r)];
    }
    $related = [];
    foreach (array_slice($ids, 0, 6) as $id) {
        if (isset($byId[$id])) {
            $related[] = $byId[$id];
        }
    }
    if ($related) {
        bot_reply('Meo, mình chưa thấy đúng "' . implode(' ', $tokens) . '" trong kho, nhưng có vài cuốn có thể bạn sẽ thích:', array_map($toCard, $related), $defaultChips);
    }
}

bot_reply('Meo… mình chưa tìm thấy sách nào' . ($desc ? ' ' . $desc : '') . '. Bạn thử đổi từ khóa, nới khoảng giá hoặc chọn gợi ý bên dưới nhé!', [], $defaultChips);
