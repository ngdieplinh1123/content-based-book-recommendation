<?php
require_once 'header.php';

$allowedRedirects = ['index.php', 'products.php', 'cart.php', 'checkout.php', 'orders.php', 'profile.php'];
$requestedRedirect = (string)($_GET['redirect'] ?? $_POST['redirect'] ?? '');
$safeRedirect = 'index.php';
if (in_array($requestedRedirect, $allowedRedirects, true)) {
    $safeRedirect = $requestedRedirect;
} elseif (preg_match('/^product_detail\.php\?id=([1-9][0-9]*)(#reviews)?$/', $requestedRedirect, $redirectMatch)) {
    $safeRedirect = 'product_detail.php?id=' . $redirectMatch[1] . ($redirectMatch[2] ?? '');
}

$status = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($phone) || empty($address) || empty($password) || empty($confirm_password)) {
        $status = 'error';
        $message = 'Vui lòng điền đầy đủ thông tin.';
    } elseif ($password !== $confirm_password) {
        $status = 'error';
        $message = 'Mật khẩu xác nhận không khớp.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $status = 'error';
            $message = 'Tên đăng nhập đã tồn tại.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, phone, address, role) VALUES (?, ?, ?, ?, 'user')");
            if ($stmtInsert->execute([$username, $hashed_password, $phone, $address])) {
                $status = 'success';
                $message = 'Đăng ký thành công!';
            } else {
                $status = 'error';
                $message = 'Hệ thống đang bận, thử lại sau.';
            }
        }
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="auth-page container mx-auto px-4 py-12 md:py-16 flex justify-center relative overflow-hidden">
    <i class="fas fa-book-reader absolute top-0 left-10 text-brown-200 text-7xl opacity-20 -rotate-12 pointer-events-none"></i>
    <i class="fas fa-leaf absolute bottom-0 right-10 text-brown-200 text-6xl opacity-40 rotate-45 pointer-events-none"></i>

    <div class="w-full max-w-md bg-white shadow-sm p-7 sm:p-9 border border-[#e9e2d8] z-10 relative">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-semibold text-slate-800 tracking-wide mb-2">Tạo tài khoản CatBook</h2>
            <p class="text-slate-500 font-medium">Tạo tài khoản mua sắm mới</p>
        </div>

        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($safeRedirect, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label class="block text-sm font-bold text-brown-700 mb-1 ml-1">Tên đăng nhập</label>
                <div class="relative">
                    <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-brown-400"></i>
                    <input type="text" name="username" autocomplete="username" class="w-full bg-white border border-slate-300 py-3.5 pl-11 pr-4 text-slate-800 font-medium focus:outline-none focus:border-[#e87500] focus:ring-1 focus:ring-[#e87500] transition-all" placeholder="Nhập tên đăng nhập" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-brown-700 mb-1 ml-1">Số điện thoại</label>
                <div class="relative">
                    <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-brown-400"></i>
                    <input type="tel" name="phone" autocomplete="tel" class="w-full bg-white border border-slate-300 py-3.5 pl-11 pr-4 text-slate-800 font-medium focus:outline-none focus:border-[#e87500] focus:ring-1 focus:ring-[#e87500] transition-all" placeholder="Nhập số điện thoại" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-brown-700 mb-1 ml-1">Địa chỉ nhận hàng</label>
                <div class="relative">
                    <i class="fas fa-map-marker-alt absolute left-4 top-1/2 -translate-y-1/2 text-brown-400"></i>
                    <input type="text" name="address" autocomplete="street-address" class="w-full bg-white border border-slate-300 py-3.5 pl-11 pr-4 text-slate-800 font-medium focus:outline-none focus:border-[#e87500] focus:ring-1 focus:ring-[#e87500] transition-all" placeholder="Nhập địa chỉ cụ thể" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-brown-700 mb-1 ml-1">Mật khẩu</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-brown-400"></i>
                    <input type="password" name="password" autocomplete="new-password" class="w-full bg-white border border-slate-300 py-3.5 pl-11 pr-4 text-slate-800 font-medium focus:outline-none focus:border-[#e87500] focus:ring-1 focus:ring-[#e87500] transition-all" placeholder="Nhập mật khẩu" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-brown-700 mb-1 ml-1">Xác nhận mật khẩu</label>
                <div class="relative">
                    <i class="fas fa-shield-alt absolute left-4 top-1/2 -translate-y-1/2 text-brown-400"></i>
                    <input type="password" name="confirm_password" autocomplete="new-password" class="w-full bg-white border border-slate-300 py-3.5 pl-11 pr-4 text-slate-800 font-medium focus:outline-none focus:border-[#e87500] focus:ring-1 focus:ring-[#e87500] transition-all" placeholder="Nhập lại mật khẩu" required>
                </div>
            </div>

            <button type="submit" class="w-full bg-[#e87500] hover:bg-[#c65f00] text-white py-3.5 font-semibold text-base mt-4 transition-colors">
                Đăng ký
            </button>
        </form>

        <div class="mt-8 text-center border-t border-slate-100 pt-6">
            <p class="text-slate-600 font-medium">Đã có tài khoản? <a href="login.php?redirect=<?= rawurlencode($safeRedirect) ?>" class="text-[#e87500] font-semibold hover:underline">Đăng nhập ngay</a></p>
        </div>
    </div>
</div>

<?php if ($status !== ''): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const Toast = Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true,
            customClass: { popup: 'rounded-2xl shadow-xl border border-brown-100' },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: '<?= $status ?>',
            title: '<?= $message ?>',
            iconColor: '<?= $status === "success" ? "#ca7344" : "#e3342f" ?>'
        }).then(() => {
            <?php if ($status === 'success'): ?>
                window.location.href = 'login.php?redirect=' + encodeURIComponent(<?= json_encode($safeRedirect, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
            <?php endif; ?>
        });
    });
</script>
<?php endif; ?>

<?php
require_once 'footer.php';
?>