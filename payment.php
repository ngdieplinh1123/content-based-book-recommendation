<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php?redirect=orders.php");
    exit;
}

$orderId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT id, total_amount, status, payment_method, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), created_at + INTERVAL " . BANK_TRANSFER_TTL_MINUTES . " MINUTE)) AS seconds_left FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, (int)$_SESSION['user']['id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order || $order['payment_method'] !== 'banking' || in_array($order['status'], ['cancelled', 'completed'], true)) {
    header("Location: orders.php");
    exit;
}

$memo = 'CATBOOK' . $order['id'];
$amount = (float)$order['total_amount'];
$qrUrl = vietqr_url($pdo, $amount, $memo);
$bank = bank_settings($pdo);

require_once 'header.php';
?>

<div class="catbook-store-page min-h-screen pb-20 pt-8" style="background:#f7f3ed">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="bg-white border border-slate-100 shadow-sm p-6 sm:p-8">
            <div class="text-center mb-6">
                <div class="inline-flex w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 items-center justify-center text-2xl mb-3"><i class="fas fa-check"></i></div>
                <h1 class="text-2xl font-black text-slate-800">Đặt hàng thành công - Đơn #<?= (int)$order['id'] ?></h1>
                <p class="text-slate-500 mt-1">Quét mã QR bằng ứng dụng ngân hàng để thanh toán.</p>
            </div>

            <div class="grid md:grid-cols-2 gap-8 items-start">
                <div class="text-center">
                    <img src="<?= htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Mã QR thanh toán đơn <?= (int)$order['id'] ?>" class="w-full max-w-xs mx-auto border border-slate-200" loading="eager">
                    <p class="text-xs text-slate-400 mt-2">Nếu mã không hiện, vui lòng chuyển khoản thủ công theo thông tin bên cạnh.</p>
                    <?php if ($order['status'] === 'pending'): ?>
                        <div class="mt-4 text-left bg-amber-50 border border-amber-200 text-amber-800 text-sm p-3">
                            <i class="fas fa-clock mr-1"></i>
                            <strong>Lưu ý:</strong> Đơn hàng sẽ tự động bị hủy nếu bạn chưa thanh toán trong vòng <strong>1 giờ</strong> kể từ khi đặt hàng.
                            <div class="mt-1">Thời gian còn lại: <strong id="payCountdown" data-seconds="<?= (int)$order['seconds_left'] ?>" class="text-red-600">--:--</strong></div>
                        </div>
                    <?php endif; ?>
                </div>

                <dl class="text-sm space-y-3">
                    <?php
                    $rows = [
                        'Ngân hàng' => $bank['bank_display_name'],
                        'Số tài khoản' => $bank['bank_account_no'],
                        'Chủ tài khoản' => $bank['bank_account_name'],
                        'Số tiền' => number_format($amount, 0, ',', '.') . 'đ',
                        'Nội dung chuyển khoản' => $memo,
                    ];
                    foreach ($rows as $label => $value): ?>
                        <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500"><?= $label ?></dt>
                            <dd class="font-bold text-slate-800 text-right flex items-center gap-2">
                                <span><?= htmlspecialchars($value) ?></span>
                                <?php if (in_array($label, ['Số tài khoản', 'Nội dung chuyển khoản'], true)): ?>
                                    <button type="button" class="text-[#e87500] text-xs copy-btn" data-copy="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><i class="far fa-copy"></i></button>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                    <p class="text-xs text-slate-500 pt-1">Vui lòng nhập đúng nội dung chuyển khoản để cửa hàng xác nhận đơn nhanh nhất.</p>
                </dl>
            </div>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="orders.php" class="bg-[#e87500] hover:bg-[#c65f00] text-white px-6 py-3 font-bold text-sm">Xem đơn hàng của tôi</a>
                <a href="products.php" class="border border-slate-300 text-slate-700 px-6 py-3 font-bold text-sm hover:bg-slate-50">Tiếp tục mua sắm</a>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var el = document.getElementById('payCountdown');
        if (!el) return;
        var left = parseInt(el.dataset.seconds, 10) || 0;
        function tick() {
            if (left <= 0) {
                el.textContent = 'Đã hết hạn';
                setTimeout(function() { window.location.href = 'orders.php'; }, 1500);
                return;
            }
            var m = Math.floor(left / 60), s = left % 60;
            el.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            left--;
            setTimeout(tick, 1000);
        }
        tick();
    })();
    document.querySelectorAll('.copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            navigator.clipboard.writeText(btn.dataset.copy).then(function() {
                btn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(function() { btn.innerHTML = '<i class="far fa-copy"></i>'; }, 1500);
            });
        });
    });
</script>

<?php require_once 'footer.php'; ?>
