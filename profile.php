<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php?redirect=profile.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $name = trim($_POST['username'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $address, $user_id]);
            
            $_SESSION['user']['username'] = $name;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['address'] = $address;
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Cập nhật thông tin thành công!'];
        } catch (\Exception $e) {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Cập nhật thất bại! Vui lòng thử lại.'];
        }
        header("Location: profile.php");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current_hash = $stmt->fetchColumn();

            if (password_verify($old_pass, $current_hash)) {
                if ($new_pass === $confirm_pass) {
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmtUpdate->execute([$new_hash, $user_id]);
                    $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Đổi mật khẩu thành công!'];
                } else {
                    $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Mật khẩu mới không trùng khớp!'];
                }
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Mật khẩu cũ không chính xác!'];
            }
        } catch (\Exception $e) {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Lỗi hệ thống! Vui lòng thử lại.'];
        }
        header("Location: profile.php");
        exit;
    }
}

try {
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $stmtOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $stmtOrders->execute([$user_id]);
    $totalOrders = $stmtOrders->fetchColumn();
    
    $stmtSpent = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE user_id = ? AND status = 'completed'");
    $stmtSpent->execute([$user_id]);
    $totalSpent = $stmtSpent->fetchColumn() ?: 0;

} catch (\PDOException $e) {
    $currentUser = [];
    $totalOrders = 0;
    $totalSpent = 0;
}

require_once 'header.php';

$swal_script = '';
if (isset($_SESSION['toast'])) {
    $swal_script = "showToast('{$_SESSION['toast']['type']}', '{$_SESSION['toast']['msg']}');";
    unset($_SESSION['toast']);
}
?>

<style>
    .account-page { background: #f7f3ed; }
    .account-page .rounded-3xl { border-radius: 4px !important; }
    .account-page .bg-gradient-to-br { background-image: linear-gradient(135deg, #39780b, #24520a) !important; }
    .account-page .bg-brown-600 { background-color: #e87500 !important; }
    .account-page .hover\:bg-brown-700:hover { background-color: #c65f00 !important; }
    .account-page .text-brown-600, .account-page .text-brown-500 { color: #e87500 !important; }
</style>
<div class="account-page min-h-screen pb-20 pt-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8 hidden sm:block">
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 flex items-center gap-3">
                <i class="fas fa-user-circle text-brown-600"></i> Tài Khoản Của Tôi
            </h1>
        </div>

        <div class="flex flex-col lg:flex-row gap-6 lg:gap-8 items-start">
            
            <div class="w-full lg:w-[340px] shrink-0 space-y-6">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-br from-brown-600 to-brown-800 p-8 text-center relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-5 rounded-full -mr-10 -mt-10"></div>
                        <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-5 rounded-full -ml-8 -mb-8"></div>
                        
                        <div class="relative w-28 h-28 mx-auto mb-4">
                            <div class="w-full h-full rounded-full bg-white flex items-center justify-center text-4xl text-brown-600 font-black shadow-lg border-4 border-white/20">
                                <?= strtoupper(substr(htmlspecialchars($currentUser['username'] ?? 'U'), 0, 1)) ?>
                            </div>
                            <button class="absolute bottom-0 right-0 w-8 h-8 bg-slate-800 text-white rounded-full flex items-center justify-center border-2 border-white hover:bg-slate-700 transition-colors shadow-sm">
                                <i class="fas fa-camera text-xs"></i>
                            </button>
                        </div>
                        <h2 class="text-xl font-black text-white mb-1"><?= htmlspecialchars($currentUser['username'] ?? '') ?></h2>
                        <p class="text-white/80 text-sm font-medium"><?= htmlspecialchars($currentUser['phone'] ?? 'Khách hàng CatBook') ?></p>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                                <i class="fas fa-box-open text-brown-500 text-xl mb-2"></i>
                                <div class="font-black text-slate-800 text-lg"><?= $totalOrders ?></div>
                                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Đơn hàng</div>
                            </div>
                            <div class="bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                                <i class="fas fa-medal text-brown-500 text-xl mb-2"></i>
                                <div class="font-black text-slate-800 text-lg">Thành viên</div>
                                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Hạng</div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <a href="profile.php" class="flex items-center gap-3 w-full p-3 rounded-xl bg-brown-50 text-brown-700 font-bold transition-colors">
                                <i class="fas fa-user-edit w-5 text-center"></i> Thông tin cá nhân
                            </a>
                            <a href="orders.php" class="flex items-center gap-3 w-full p-3 rounded-xl text-slate-600 hover:bg-slate-50 hover:text-brown-600 font-bold transition-colors">
                                <i class="fas fa-receipt w-5 text-center"></i> Lịch sử đơn hàng
                            </a>
                            <a href="logout.php" class="flex items-center gap-3 w-full p-3 rounded-xl text-red-500 hover:bg-red-50 font-bold transition-colors mt-4">
                                <i class="fas fa-sign-out-alt w-5 text-center"></i> Đăng xuất
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 w-full space-y-6">
                
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 sm:p-8">
                    <h2 class="text-xl font-black text-slate-800 mb-6 pb-4 border-b border-slate-100 flex items-center gap-2">
                        <i class="fas fa-id-card text-brown-500"></i> Cập Nhật Thông Tin
                    </h2>
                    
                    <form method="POST" action="profile.php" class="space-y-5">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Họ và tên</label>
                                <div class="relative">
                                    <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input type="text" name="username" value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-sm font-bold text-slate-800 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Số điện thoại</label>
                                <div class="relative">
                                    <i class="fas fa-phone-alt absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-sm font-bold text-slate-800 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Mã khách hàng</label>
                            <div class="relative">
                                <i class="fas fa-id-badge absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" value="CB-<?= (int)($currentUser['id'] ?? 0) ?>" readonly class="w-full bg-slate-100 border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-sm font-bold text-slate-500 cursor-not-allowed">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Địa chỉ giao hàng mặc định</label>
                            <div class="relative">
                                <i class="fas fa-map-marker-alt absolute left-4 top-4 text-slate-400"></i>
                                <textarea name="address" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-sm font-bold text-slate-800 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all resize-none"><?= htmlspecialchars($currentUser['address'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full sm:w-auto bg-brown-600 hover:bg-brown-700 text-white px-8 py-3.5 rounded-xl font-black transition-transform hover:-translate-y-1 shadow-lg shadow-brown-500/30 flex items-center justify-center gap-2">
                                <i class="fas fa-save"></i> LƯU THAY ĐỔI
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 sm:p-8">
                    <h2 class="text-xl font-black text-slate-800 mb-6 pb-4 border-b border-slate-100 flex items-center gap-2">
                        <i class="fas fa-shield-alt text-brown-500"></i> Đổi Mật Khẩu
                    </h2>
                    
                    <form method="POST" action="profile.php" class="space-y-5">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Mật khẩu hiện tại</label>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="password" name="old_password" required class="w-full bg-white border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-sm font-bold text-slate-800 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Mật khẩu mới</label>
                                <div class="relative">
                                    <i class="fas fa-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input type="password" name="new_password" required minlength="6" class="w-full bg-white border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-sm font-bold text-slate-800 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Xác nhận mật khẩu mới</label>
                                <div class="relative">
                                    <i class="fas fa-check-circle absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input type="password" name="confirm_password" required minlength="6" class="w-full bg-white border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-sm font-bold text-slate-800 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full sm:w-auto bg-slate-800 hover:bg-slate-900 text-white px-8 py-3.5 rounded-xl font-black transition-transform hover:-translate-y-1 shadow-lg shadow-slate-800/30 flex items-center justify-center gap-2">
                                <i class="fas fa-sync-alt"></i> CẬP NHẬT MẬT KHẨU
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'bottom-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: { popup: 'rounded-2xl shadow-2xl border border-slate-100' }
    });

    function showToast(icon, title) {
        Toast.fire({
            icon: icon,
            title: title
        });
    }

    <?php if ($swal_script !== ''): ?>
    document.addEventListener('DOMContentLoaded', function() {
        <?= $swal_script ?>
    });
    <?php endif; ?>
</script>

<?php require_once 'footer.php'; ?>