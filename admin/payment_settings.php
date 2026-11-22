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

$banks = [
    'MB' => 'MB Bank (Ngân hàng Quân đội)',
    'VCB' => 'Vietcombank',
    'ACB' => 'ACB (Ngân hàng Á Châu)',
    'BIDV' => 'BIDV',
    'TCB' => 'Techcombank',
    'ICB' => 'VietinBank',
    'VBA' => 'Agribank',
    'VPB' => 'VPBank',
    'TPB' => 'TPBank',
    'STB' => 'Sacombank',
    'VIB' => 'VIB',
    'HDB' => 'HDBank',
    'SHB' => 'SHB',
    'OCB' => 'OCB (Ngân hàng Phương Đông)',
    'MSB' => 'MSB (Ngân hàng Hàng Hải)',
    'EIB' => 'Eximbank',
    'SEAB' => 'SeABank',
    'LPBank' => 'LPBank (LienVietPostBank)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bankId = (string)($_POST['bank_id'] ?? '');
    $input = [
        'bank_id' => $bankId,
        'bank_account_no' => preg_replace('/\s+/', '', (string)($_POST['bank_account_no'] ?? '')),
        'bank_account_name' => trim($_POST['bank_account_name'] ?? ''),
        'bank_display_name' => $banks[$bankId] ?? '',
    ];

    if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Phiên làm việc đã hết hạn. Vui lòng thử lại.';
    } elseif (!isset($banks[$bankId])) {
        $error = 'Vui lòng chọn ngân hàng trong danh sách.';
    } elseif (!preg_match('/^[0-9]{6,20}$/', $input['bank_account_no'])) {
        $error = 'Số tài khoản phải gồm 6-20 chữ số.';
    } elseif ($input['bank_account_name'] === '' || mb_strlen($input['bank_account_name'], 'UTF-8') > 100) {
        $error = 'Vui lòng nhập tên chủ tài khoản (tối đa 100 ký tự).';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $pdo->beginTransaction();
            foreach ($input as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            $pdo->commit();
            $_SESSION['settings_notice'] = 'Đã lưu thông tin thanh toán.';
            header('Location: payment_settings.php');
            exit;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Unable to save payment settings: ' . $e->getMessage());
            $error = 'Chưa thể lưu lúc này. Vui lòng thử lại sau.';
        }
    }
}

if (!empty($_SESSION['settings_notice'])) {
    $message = $_SESSION['settings_notice'];
    unset($_SESSION['settings_notice']);
}

$bank = bank_settings($pdo);
if ($error !== '') {
    $bank = array_merge($bank, array_intersect_key($input, $bank));
}
$previewUrl = 'https://img.vietqr.io/image/' . rawurlencode($bank['bank_id']) . '-' . rawurlencode($bank['bank_account_no']) . '-compact2.png?' . http_build_query([
    'amount' => 100000,
    'addInfo' => 'CATBOOK-DEMO',
    'accountName' => $bank['bank_account_name'],
]);

require_once 'header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form method="POST" class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-5">
        <h3 class="text-lg font-bold text-slate-800"><i class="fas fa-credit-card text-brown-500 mr-2"></i>Thông tin nhận chuyển khoản</h3>

        <?php if ($message): ?>
            <div class="p-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-bold"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="p-3 rounded-xl bg-red-50 text-red-700 text-sm font-bold"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Ngân hàng</label>
                <select name="bank_id" required class="w-full border border-slate-200 rounded-xl py-3 px-4 text-sm bg-white focus:outline-none focus:border-brown-500">
                    <?php if (!isset($banks[$bank['bank_id']])): ?>
                        <option value="" selected disabled>-- Chọn ngân hàng --</option>
                    <?php endif; ?>
                    <?php foreach ($banks as $code => $name): ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= $bank['bank_id'] === $code ? 'selected' : '' ?>><?= htmlspecialchars($code === 'LPBank' ? $name : $code . ' - ' . $name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Số tài khoản</label>
                <input type="text" name="bank_account_no" value="<?= htmlspecialchars($bank['bank_account_no']) ?>" required inputmode="numeric" maxlength="20" class="w-full border border-slate-200 rounded-xl py-3 px-4 text-sm focus:outline-none focus:border-brown-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Tên chủ tài khoản</label>
                <input type="text" name="bank_account_name" value="<?= htmlspecialchars($bank['bank_account_name']) ?>" required maxlength="100" class="w-full border border-slate-200 rounded-xl py-3 px-4 text-sm uppercase focus:outline-none focus:border-brown-500">
            </div>
        </div>

        <button type="submit" class="bg-brown-600 hover:bg-brown-700 text-white px-8 py-3 rounded-xl font-bold text-sm">Lưu thay đổi</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 text-center">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Xem trước mã QR</h3>
        <img src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Mã QR xem trước" class="w-full max-w-[260px] mx-auto border border-slate-200">
        <p class="text-xs text-slate-400 mt-3">Mẫu với số tiền 100.000đ. Lưu thông tin rồi tải lại trang để cập nhật.</p>
    </div>
</div>

<?php require_once 'footer.php'; ?>
