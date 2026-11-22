<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = '127.0.0.1';
$db   = 'catbook_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed");
}

const BANK_TRANSFER_TTL_MINUTES = 60;

// Hủy đơn chuyển khoản chưa được xác nhận thanh toán sau thời hạn và hoàn lại tồn kho.
function expire_unpaid_orders(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE payment_method = 'banking' AND status = 'pending' AND created_at <= (NOW() - INTERVAL " . BANK_TRANSFER_TTL_MINUTES . " MINUTE)");
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
            $upd->execute([$id]);
            if ($upd->rowCount() === 1) {
                $pdo->prepare("UPDATE products p INNER JOIN order_details d ON d.product_id = p.id SET p.quantity = p.quantity + d.quantity WHERE d.order_id = ?")->execute([$id]);
            }
            $pdo->commit();
        }
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Unable to expire unpaid orders: ' . $e->getMessage());
    }
}
expire_unpaid_orders($pdo);

// Giá trị mặc định; quản trị viên chỉnh trong admin/payment_settings.php (lưu ở bảng site_settings).
const BANK_DEFAULTS = [
    'bank_id' => 'MB',
    'bank_account_no' => '0123456789',
    'bank_account_name' => 'CATBOOK',
    'bank_display_name' => 'MB Bank (Ngân hàng Quân đội)',
];

function bank_settings(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = BANK_DEFAULTS;
    try {
        foreach ($pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll() as $row) {
            if (isset($cache[$row['setting_key']]) && $row['setting_value'] !== '') {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (\PDOException $e) {
    }
    return $cache;
}

// Thông tin website; quản trị viên chỉnh trong admin/site_settings.php (lưu ở bảng site_settings).
const SITE_DEFAULTS = [
    'site_name' => 'CatBook',
    'site_slogan' => 'Gửi tri thức, trao yêu thương',
    'support_phone' => '028.73008182',
    'support_email' => 'hotro@hiensach.vn',
    'site_logo' => 'uploads/brand/catbook-logo.png',
];

function site_info(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = SITE_DEFAULTS;
    try {
        foreach ($pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll() as $row) {
            if (isset($cache[$row['setting_key']]) && $row['setting_value'] !== '') {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (\PDOException $e) {
    }
    return $cache;
}

function vietqr_url(PDO $pdo, float $amount, string $memo): string
{
    $b = bank_settings($pdo);
    return 'https://img.vietqr.io/image/' . rawurlencode($b['bank_id']) . '-' . rawurlencode($b['bank_account_no']) . '-compact2.png?' . http_build_query([
        'amount' => (int)$amount,
        'addInfo' => $memo,
        'accountName' => $b['bank_account_name'],
    ]);
}

function vn_normalize(string $text): string
{
    static $map = null;
    if ($map === null) {
        $groups = [
            'a' => 'àáạảãâầấậẩẫăằắặẳẵ',
            'e' => 'èéẹẻẽêềếệểễ',
            'i' => 'ìíịỉĩ',
            'o' => 'òóọỏõôồốộổỗơờớợởỡ',
            'u' => 'ùúụủũưừứựửữ',
            'y' => 'ỳýỵỷỹ',
            'd' => 'đ',
        ];
        $map = [];
        foreach ($groups as $base => $chars) {
            foreach (preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                $map[$ch] = $base;
            }
        }
    }
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[\x{0300}-\x{036f}]/u', '', $text);
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    $text = preg_replace('/\bnha xuat ban\b/', 'nxb', $text);
    return trim($text);
}

/**
 * Tìm sách không phân biệt dấu/hoa thường theo tên, tác giả, nhà xuất bản.
 * Trả về ['exact' => [id theo độ liên quan], 'related' => [id gợi ý]].
 */
function search_products(PDO $pdo, string $query, int $relatedLimit = 8): array
{
    $needle = vn_normalize($query);
    $tokens = array_values(array_unique(array_filter(explode(' ', $needle), fn($t) => $t !== '')));
    if (!$tokens) {
        return ['exact' => [], 'related' => []];
    }

    $exact = [];
    $partial = [];
    $rows = $pdo->query("SELECT id, name, author, publisher, category_id FROM products")->fetchAll();
    $meta = [];
    foreach ($rows as $row) {
        $name = vn_normalize((string)$row['name']);
        $hay = $name . ' ' . vn_normalize((string)$row['author']) . ' ' . vn_normalize((string)$row['publisher']);
        $meta[(int)$row['id']] = $row;

        $hits = 0;
        foreach ($tokens as $t) {
            if (strpos($hay, $t) !== false) {
                $hits++;
            }
        }
        $bonus = (strpos($name, $needle) !== false ? 100 : 0) + (strpos($hay, $needle) !== false ? 50 : 0);
        if ($hits === count($tokens)) {
            $exact[(int)$row['id']] = $bonus + $hits * 10;
        } elseif ($hits > 0) {
            $partial[(int)$row['id']] = $hits * 10;
        } else {
            $sim = 0;
            similar_text($needle, $name, $sim);
            if ($sim >= 45) {
                $partial[(int)$row['id']] = $sim / 10;
            }
        }
    }
    arsort($exact);

    // Sách cùng danh mục/nhà xuất bản với kết quả đầu tiên cũng là gợi ý liên quan.
    foreach (array_slice(array_keys($exact), 0, 3) as $topId) {
        foreach ($meta as $id => $row) {
            if (isset($exact[$id]) || $id === $topId) {
                continue;
            }
            $same = 0;
            if ($row['publisher'] !== '' && $row['publisher'] === $meta[$topId]['publisher']) {
                $same += 3;
            }
            if ($row['author'] !== '' && $row['author'] === $meta[$topId]['author']) {
                $same += 5;
            }
            if ($row['category_id'] === $meta[$topId]['category_id']) {
                $same += 1;
            }
            if ($same > 0) {
                $partial[$id] = ($partial[$id] ?? 0) + $same;
            }
        }
    }
    foreach ($exact as $id => $_) {
        unset($partial[$id]);
    }
    arsort($partial);

    return [
        'exact' => array_keys($exact),
        'related' => array_slice(array_keys($partial), 0, $relatedLimit),
    ];
}

const PER_PAGE_ADMIN = [10, 25, 50, 100, 500, 0];
const PER_PAGE_SHOP = [9, 18, 36, 72, 0];

/** Số mục mỗi trang người dùng chọn qua ?per_page= (0 = tất cả). */
function per_page(int $default, array $options = PER_PAGE_ADMIN): int
{
    if (!isset($_GET['per_page'])) {
        return $default;
    }
    $raw = $_GET['per_page'] === 'all' ? 0 : (int)$_GET['per_page'];
    if (!in_array($raw, $options, true)) {
        return $default;
    }
    return $raw === 0 ? 100000 : $raw;
}

function page_url(int $page): string
{
    $qs = $_GET;
    $qs['page'] = $page;
    return '?' . http_build_query($qs);
}

/** Ô chọn số mục mỗi trang kèm dòng "Hiển thị a–b / tổng". */
function per_page_selector(int $limit, int $page, int $total, array $options = PER_PAGE_ADMIN): string
{
    $isAll = ($_GET['per_page'] ?? '') === 'all';
    $hidden = '';
    foreach ($_GET as $k => $v) {
        if ($k === 'page' || $k === 'per_page' || is_array($v)) {
            continue;
        }
        $hidden .= '<input type="hidden" name="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '">';
    }
    $opts = '';
    foreach ($options as $o) {
        $value = $o === 0 ? 'all' : (string)$o;
        $selected = $o === 0 ? $isAll : (!$isAll && $o === $limit);
        $opts .= '<option value="' . $value . '"' . ($selected ? ' selected' : '') . '>' . ($o === 0 ? 'Tất cả' : $o) . '</option>';
    }
    $from = $total > 0 ? ($page - 1) * $limit + 1 : 0;
    $to = min($total, $page * $limit);
    return '<div class="flex flex-wrap items-center justify-between gap-3 mb-4 text-sm text-slate-600">'
        . '<form method="GET" class="flex items-center gap-2">' . $hidden
        . '<label for="perPageSelect">Hiển thị</label>'
        . '<select id="perPageSelect" name="per_page" onchange="this.form.submit()" class="border border-slate-300 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:border-brown-500">' . $opts . '</select>'
        . '<span>mục mỗi trang</span></form>'
        . '<span>Hiển thị ' . $from . '–' . $to . ' / ' . $total . ' mục</span></div>';
}

/** Lấy danh sách sách (đầy đủ cột) theo thứ tự ID trong kết quả gợi ý. */
function reco_fetch_products(PDO $pdo, array $items): array
{
    $ids = array_values(array_unique(array_filter(
        array_map('intval', array_column($items, 'product_id')),
        static fn(int $id): bool => $id > 0
    )));
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT p.* FROM products p JOIN categories c ON c.id = p.category_id AND c.status = 1 WHERE p.quantity > 0 AND p.id IN ($in)");
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byId[(int)$row['id']] = $row;
    }
    $out = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $out[] = $byId[$id];
        }
    }
    return $out;
}

function reco_ensure_view_table(PDO $pdo): void
{
    static $ready = false;
    if (!$ready) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            session_key VARCHAR(64) NOT NULL,
            product_id INT NOT NULL,
            viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user (user_id, viewed_at),
            KEY idx_session (session_key, viewed_at),
            KEY idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ready = true;
    }
}

/**
 * Chạy pipeline Python nền để tính lại gợi ý cho một người dùng.
 * Có thể đổi đường dẫn Python bằng biến môi trường CATBOOK_PYTHON.
 */
function reco_refresh_user(int $userId, bool $force = false): void
{
    if ($userId <= 0) {
        return;
    }
    $now = time();
    if (!$force && isset($_SESSION['reco_refreshed_at']) && $now - (int)$_SESSION['reco_refreshed_at'] < 30) {
        return;
    }
    $_SESSION['reco_refreshed_at'] = $now;

    $script = __DIR__ . DIRECTORY_SEPARATOR . 'recommendation_research' . DIRECTORY_SEPARATOR . 'run_offline_recommendation.py';
    $pythonFile = __DIR__ . DIRECTORY_SEPARATOR . 'recommendation_research' . DIRECTORY_SEPARATOR . 'python_path.txt';
    $python = getenv('CATBOOK_PYTHON')
        ?: (is_file($pythonFile) ? trim((string) file_get_contents($pythonFile)) : '')
        ?: 'python';
    if (!is_file($script) || !function_exists('popen')) {
        return;
    }
    $log = dirname($script) . DIRECTORY_SEPARATOR . 'web_refresh.log';
    $cmd = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' --user-id ' . $userId;
    try {
        if (stripos(PHP_OS, 'WIN') === 0) {
            $handle = popen('start /B "" ' . $cmd . ' >> ' . escapeshellarg($log) . ' 2>&1', 'r');
        } else {
            $handle = popen($cmd . ' >> ' . escapeshellarg($log) . ' 2>&1 &', 'r');
        }
        if (is_resource($handle)) {
            pclose($handle);
        }
    } catch (Throwable $e) {
        error_log('Unable to refresh recommendations: ' . $e->getMessage());
    }
}

/** Ghi lượt xem sách theo tài khoản hoặc phiên để làm tín hiệu gợi ý. */
function reco_track_view(PDO $pdo, int $productId): void
{
    try {
        reco_ensure_view_table($pdo);
        $pdo->prepare("INSERT INTO product_views (user_id, session_key, product_id) VALUES (?, ?, ?)")
            ->execute([isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null, session_id(), $productId]);
        if (isset($_SESSION['user']['id'])) {
            reco_refresh_user((int)$_SESSION['user']['id']);
        }
    } catch (PDOException $e) {
        error_log('Unable to record recommendation view: ' . $e->getMessage());
    }
}

/** Các sách xem gần đây (cũ -> mới, không trùng) của người dùng hiện tại. */
function reco_recent_views(PDO $pdo, int $limit = 15): array
{
    try {
        reco_ensure_view_table($pdo);
        $limit = max(1, min(50, $limit));
        if (isset($_SESSION['user']['id'])) {
            $stmt = $pdo->prepare("SELECT product_id FROM product_views WHERE user_id = ? OR session_key = ? GROUP BY product_id ORDER BY MAX(viewed_at) DESC, MAX(id) DESC LIMIT $limit");
            $stmt->execute([(int)$_SESSION['user']['id'], session_id()]);
        } else {
            $stmt = $pdo->prepare("SELECT product_id FROM product_views WHERE session_key = ? GROUP BY product_id ORDER BY MAX(viewed_at) DESC, MAX(id) DESC LIMIT $limit");
            $stmt->execute([session_id()]);
        }
        return array_reverse(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    } catch (PDOException $e) {
        error_log('Unable to read recommendation views: ' . $e->getMessage());
        return [];
    }
}

/** Ghi sự kiện thêm vào giỏ hàng của người dùng đã đăng nhập làm tín hiệu gợi ý. */
function reco_track_cart(PDO $pdo, int $productId): void
{
    if (empty($_SESSION['user']['id'])) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_cart_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user (user_id, created_at),
            KEY idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->prepare("INSERT INTO product_cart_events (user_id, product_id) VALUES (?, ?)")
            ->execute([(int)$_SESSION['user']['id'], $productId]);
        reco_refresh_user((int)$_SESSION['user']['id'], true);
    } catch (PDOException $e) {
        error_log('Unable to record cart event: ' . $e->getMessage());
    }
}

/** Sách được xem nhiều nhất trong 30 ngày gần đây; thiếu dữ liệu thì bù bằng sách mới nhất. */
function reco_most_viewed(PDO $pdo, int $limit = 8): array
{
    $limit = max(1, min(50, $limit));
    $items = [];
    try {
        reco_ensure_view_table($pdo);
        $items = $pdo->query(
            "SELECT p.*, COUNT(pv.id) AS view_count
             FROM product_views pv
             INNER JOIN products p ON p.id = pv.product_id
             INNER JOIN categories c ON c.id = p.category_id AND c.status = 1
             WHERE pv.viewed_at >= (NOW() - INTERVAL 30 DAY) AND p.quantity > 0
             GROUP BY p.id
             ORDER BY view_count DESC, p.id DESC
             LIMIT $limit"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Unable to read most viewed products: ' . $e->getMessage());
    }
    if (count($items) < $limit) {
        $have = array_map('intval', array_column($items, 'id')) ?: [0];
        $fill = $pdo->query(
            "SELECT p.* FROM products p
             INNER JOIN categories c ON c.id = p.category_id AND c.status = 1
             WHERE p.id NOT IN (" . implode(',', $have) . ") AND p.quantity > 0
             ORDER BY p.id DESC LIMIT " . ($limit - count($items))
        )->fetchAll(PDO::FETCH_ASSOC);
        $items = array_merge($items, $fill);
    }
    return $items;
}

/** Sách được yêu thích: nhiều người mua (đơn chưa hủy) và nhiều đánh giá cao. */
function reco_favorites(PDO $pdo, int $limit = 8, array $exclude = []): array
{
    $limit = max(1, min(50, $limit));
    $exclude = array_values(array_unique(array_filter(array_map('intval', $exclude), static fn(int $id): bool => $id > 0)));
    $excludeSql = $exclude ? ' AND p.id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')' : '';
    $stmt = $pdo->prepare(
        "SELECT p.*,
                COALESCE(s.total_sold, 0) AS total_sold,
                COALESCE(r.average_rating, 0) AS average_rating,
                COALESCE(r.review_count, 0) AS review_count
         FROM products p
         INNER JOIN categories c ON c.id = p.category_id AND c.status = 1
         LEFT JOIN (
             SELECT od.product_id, SUM(od.quantity) AS total_sold
             FROM order_details od
             INNER JOIN orders o ON o.id = od.order_id AND o.status <> 'cancelled'
             GROUP BY od.product_id
         ) s ON s.product_id = p.id
         LEFT JOIN (
             SELECT product_id, AVG(rating) AS average_rating, COUNT(*) AS review_count
             FROM product_reviews
             GROUP BY product_id
         ) r ON r.product_id = p.id
         WHERE p.quantity > 0" . $excludeSql . "
         ORDER BY (COALESCE(s.total_sold, 0) + COALESCE(r.review_count, 0) * COALESCE(r.average_rating, 0) / 5) DESC,
                  average_rating DESC, p.id DESC
         LIMIT " . $limit
    );
    $stmt->execute($exclude);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Gắn điểm đánh giá trung bình và số lượt đánh giá vào danh sách sách. */
function product_attach_ratings(PDO $pdo, array $products): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn($p) => (int)($p['id'] ?? 0), $products))));
    $stats = [];
    if ($ids) {
        try {
            $stmt = $pdo->query('SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM product_reviews WHERE product_id IN (' . implode(',', $ids) . ') GROUP BY product_id');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $stats[(int)$row['product_id']] = $row;
            }
        } catch (PDOException $e) {
            error_log('Unable to read product ratings: ' . $e->getMessage());
        }
    }
    foreach ($products as &$p) {
        $s = $stats[(int)($p['id'] ?? 0)] ?? null;
        $p['avg_rating'] = $s ? (float)$s['avg_rating'] : 0.0;
        $p['review_count'] = $s ? (int)$s['review_count'] : 0;
    }
    unset($p);
    return $products;
}

/** Hiển thị hàng sao đánh giá nhỏ gọn cho thẻ sách. */
function product_stars_html(array $product): string
{
    $avg = (float)($product['avg_rating'] ?? 0);
    $count = (int)($product['review_count'] ?? 0);
    if ($count <= 0) {
        return '<div class="flex items-center gap-1 text-[11px] text-slate-400 mb-1 h-4">Chưa có đánh giá</div>';
    }
    $html = '<div class="flex items-center gap-1 text-[11px] mb-1 h-4"><span class="flex text-amber-400">';
    for ($i = 1; $i <= 5; $i++) {
        $icon = $avg >= $i - 0.25 ? 'fas fa-star' : ($avg >= $i - 0.75 ? 'fas fa-star-half-alt' : 'far fa-star');
        $html .= '<i class="' . $icon . '"></i>';
    }
    return $html . '</span><span class="text-slate-500">' . number_format($avg, 1) . ' (' . $count . ')</span></div>';
}

function reco_for_user(PDO $pdo, ?int $userId, int $limit = 8, array $exclude = []): array
{
    $views = reco_recent_views($pdo);
    $limit = max(1, min(50, $limit));
    $exclude = array_values(array_unique(array_filter(array_map('intval', $exclude), static fn(int $id): bool => $id > 0)));

    if ($userId !== null && $userId > 0) {
        try {
            $excludeSql = $exclude ? ' AND ur.product_id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')' : '';
            $stmt = $pdo->prepare(
                'SELECT ur.product_id, ur.score
                 FROM user_recommendations ur
                 INNER JOIN products p ON p.id = ur.product_id AND p.quantity > 0
                 INNER JOIN categories c ON c.id = p.category_id AND c.status = 1
                 WHERE ur.user_id = ?' . $excludeSql . '
                 ORDER BY ur.recommendation_rank ASC
                 LIMIT ' . $limit
            );
            $stmt->execute(array_merge([$userId], $exclude));
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $products = reco_fetch_products($pdo, $items);
            if ($products) {
                return ['mode' => 'personalized', 'views' => count($views), 'products' => $products];
            }
        } catch (PDOException $e) {
            error_log('Unable to read offline recommendations: ' . $e->getMessage());
        }
    }

    return [
        'mode' => 'popular',
        'views' => count($views),
        'products' => reco_favorites($pdo, $limit, $exclude),
    ];
}

function product_image_url(?string $image, string $localPrefix = 'uploads/products/'): string
{
    $image = (string)$image;
    if (preg_match('/^openlibrary:(\d+):(OL\d+W)$/D', $image, $matches)) {
        return 'https://covers.openlibrary.org/b/id/' . $matches[1] . '-M.jpg';
    }

    return $localPrefix . rawurlencode(basename($image !== '' ? $image : 'default.png'));
}

function product_image_source_url(?string $image): ?string
{
    if (preg_match('/^openlibrary:\d+:(OL\d+W)$/D', (string)$image, $matches)) {
        return 'https://openlibrary.org/works/' . $matches[1];
    }

    return null;
}
?>