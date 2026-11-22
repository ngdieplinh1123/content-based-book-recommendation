<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../config.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(50) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$brandDir = dirname(__DIR__) . '/uploads/brand';
$allowedTypes = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [
        'site_name' => trim($_POST['site_name'] ?? ''),
        'site_slogan' => trim($_POST['site_slogan'] ?? ''),
        'support_phone' => trim($_POST['support_phone'] ?? ''),
        'support_email' => trim($_POST['support_email'] ?? ''),
    ];
    $newLogo = null;
    $oldLogo = site_info($pdo)['site_logo'];

    if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Phiên làm việc đã hết hạn. Vui lòng thử lại.';
    } elseif ($input['site_name'] === '' || mb_strlen($input['site_name'], 'UTF-8') > 40) {
        $error = 'Tên website bắt buộc và tối đa 40 ký tự.';
    } elseif (mb_strlen($input['site_slogan'], 'UTF-8') > 120) {
        $error = 'Khẩu hiệu tối đa 120 ký tự.';
    } elseif (!preg_match('/^[0-9 .()+\-]{8,20}$/', $input['support_phone'])) {
        $error = 'Số điện thoại hỗ trợ không hợp lệ (8-20 ký tự gồm số, dấu chấm, cách, gạch ngang).';
    } elseif (!filter_var($input['support_email'], FILTER_VALIDATE_EMAIL) || strlen($input['support_email']) > 100) {
        $error = 'Email hỗ trợ không hợp lệ.';
    } elseif (!empty($_FILES['site_logo']['name'])) {
        $f = $_FILES['site_logo'];
        $info = ($f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) ? @getimagesize($f['tmp_name']) : false;
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $error = 'Tải logo thất bại. Vui lòng thử lại.';
        } elseif ($f['size'] > 2 * 1024 * 1024) {
            $error = 'Logo tối đa 2MB.';
        } elseif (!$info || !isset($allowedTypes[$info[2]])) {
            $error = 'Logo phải là ảnh PNG, JPG, WEBP hoặc GIF.';
        } else {
            $name = 'logo-' . bin2hex(random_bytes(6)) . '.' . $allowedTypes[$info[2]];
            if (move_uploaded_file($f['tmp_name'], $brandDir . '/' . $name)) {
                $newLogo = 'uploads/brand/' . $name;
            } else {
                $error = 'Không lưu được file logo.';
            }
        }
    }

    if ($error === '') {
        if ($newLogo !== null) {
            $input['site_logo'] = $newLogo;
        } elseif (isset($_POST['reset_logo'])) {
            $input['site_logo'] = SITE_DEFAULTS['site_logo'];
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $pdo->beginTransaction();
            foreach ($input as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            $pdo->commit();
            if (isset($input['site_logo']) && $input['site_logo'] !== $oldLogo && preg_match('#^uploads/brand/logo-[a-f0-9]{12}\.\w+$#', $oldLogo)) {
                @unlink(dirname(__DIR__) . '/' . $oldLogo);
            }
            $_SESSION['settings_notice'] = 'Đã lưu thông tin website.';
            header('Location: site_settings.php');
            exit;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Unable to save site settings: ' . $e->getMessage());
            $error = 'Chưa thể lưu lúc này. Vui lòng thử lại sau.';
        }
    }
}

if (!empty($_SESSION['settings_notice'])) {
    $message = $_SESSION['settings_notice'];
    unset($_SESSION['settings_notice']);
}

$site = site_info($pdo);
if ($error !== '') {
    $site = array_merge($site, array_intersect_key($input, $site));
}

require_once 'header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form method="POST" enctype="multipart/form-data" class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-5">
        <h3 class="text-lg font-bold text-slate-800"><i class="fas fa-cog text-brown-500 mr-2"></i>Thông tin website</h3>

        <?php if ($message): ?>
            <div class="p-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-bold"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="p-3 rounded-xl bg-red-50 text-red-700 text-sm font-bold"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Tên website</label>
                <input type="text" name="site_name" value="<?= htmlspecialchars($site['site_name']) ?>" required maxlength="40" class="w-full border border-slate-200 rounded-xl py-3 px-4 text-sm focus:outline-none focus:border-brown-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Khẩu hiệu</label>
                <input type="text" name="site_slogan" value="<?= htmlspecialchars($site['site_slogan']) ?>" maxlength="120" class="w-full border border-slate-200 rounded-xl py-3 px-4 text-sm focus:outline-none focus:border-brown-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Số điện thoại hỗ trợ</label>
                <input type="text" name="support_phone" value="<?= htmlspecialchars($site['support_phone']) ?>" required maxlength="20" class="w-full border border-slate-200 rounded-xl py-3 px-4 text-sm focus:outline-none focus:border-brown-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Email hỗ trợ</label>
                <input type="email" name="support_email" value="<?= htmlspecialchars($site['support_email']) ?>" required maxlength="100" class="w-full border border-slate-200 rounded-xl py-3 px-4 text-sm focus:outline-none focus:border-brown-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Logo mới (PNG, JPG, WEBP, GIF - tối đa 2MB)</label>
            <input type="file" name="site_logo" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm bg-white">
            <label class="inline-flex items-center gap-2 mt-3 text-sm text-slate-600">
                <input type="checkbox" name="reset_logo" value="1"> Dùng lại logo mặc định
            </label>
        </div>

        <button type="submit" class="bg-brown-600 hover:bg-brown-700 text-white px-8 py-3 rounded-xl font-bold text-sm">Lưu thay đổi</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Xem trước</h3>
        <div class="flex items-center gap-3 border border-slate-200 rounded-xl p-4">
            <img src="../<?= htmlspecialchars($site['site_logo'], ENT_QUOTES, 'UTF-8') ?>" alt="Logo" class="h-12 w-12 object-contain">
            <div>
                <div class="text-2xl font-black text-brown-800"><?= htmlspecialchars($site['site_name']) ?></div>
                <div class="text-xs text-slate-500"><?= htmlspecialchars($site['site_slogan']) ?></div>
            </div>
        </div>
        <p class="text-sm text-slate-600 mt-4"><i class="fas fa-phone-alt text-brown-500 mr-2"></i><?= htmlspecialchars($site['support_phone']) ?></p>
        <p class="text-sm text-slate-600 mt-2"><i class="fas fa-envelope text-brown-500 mr-2"></i><?= htmlspecialchars($site['support_email']) ?></p>
        <p class="text-xs text-slate-400 mt-4">Thông tin này hiển thị trên đầu trang, chân trang, trang chi tiết sách và trợ lý Mèo.</p>
    </div>
</div>

<?php require_once 'footer.php'; ?>
