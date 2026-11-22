<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php?redirect=checkout.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: products.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$currentUser = [];

try {
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$products = [];
$total_amount = 0;

try {
    $ids = implode(',', array_map('intval', array_keys($cart)));
    $stmt = $pdo->query("SELECT * FROM products WHERE id IN ($ids)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $qty = $cart[$row['id']];
        $originalPrice = (float)$row['price'];
        $discountPrice = (float)$row['discount_price'];
        $price = ($discountPrice > 0 && $discountPrice < $originalPrice) ? $discountPrice : $originalPrice;
        
        $row['cart_qty'] = $qty;
        $row['current_price'] = $price;
        $row['subtotal'] = $price * $qty;
        $total_amount += $row['subtotal'];
        $products[] = $row;
    }
} catch (\PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_checkout') {
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $paymentMethod = ($_POST['payment_method'] ?? 'cod') === 'banking' ? 'banking' : 'cod';

    if ($phone !== '' && $address !== '') {
        try {
            $pdo->beginTransaction();

            $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_address, shipping_phone, payment_method, created_at) VALUES (?, ?, 'pending', ?, ?, ?, NOW())");
            $stmtOrder->execute([$user_id, $total_amount, $address, $phone, $paymentMethod]);
            $order_id = $pdo->lastInsertId();

            $stmtDetail = $pdo->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmtUpdateStock = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");

            foreach ($products as $p) {
                $stmtDetail->execute([$order_id, $p['id'], $p['cart_qty'], $p['current_price']]);
                $stmtUpdateStock->execute([$p['cart_qty'], $p['id'], $p['cart_qty']]);
            }

            $pdo->commit();
            if (!empty($_SESSION['user']['id'])) {
                reco_refresh_user((int)$_SESSION['user']['id'], true);
            }
            unset($_SESSION['cart']);
            $_SESSION['checkout_success'] = true;
            header("Location: " . ($paymentMethod === 'banking' ? "payment.php?id=" . (int)$order_id : "orders.php"));
            exit;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}

require_once 'header.php';
?>

<style>
    .catbook-store-page { background: #f7f3ed; }
    .catbook-store-page .rounded-3xl { border-radius: 4px !important; }
    .catbook-store-page .bg-brown-600 { background-color: #e87500 !important; }
    .catbook-store-page .hover\:bg-brown-700:hover { background-color: #c65f00 !important; }
    .catbook-store-page .text-brown-600, .catbook-store-page .text-brown-500 { color: #e87500 !important; }
    .custom-scroll::-webkit-scrollbar { width: 6px; }
    .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .custom-scroll:hover::-webkit-scrollbar-thumb { background: #94a3b8; }
    .pay-check { opacity: 0; }
    .pay-option:has(input:checked) { border-color: #e87500; background: #fff7ed; }
    .pay-option:has(input:checked) .pay-check { opacity: 1; }
</style>

<div class="catbook-store-page min-h-screen pb-20 pt-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 flex items-center gap-3">
                <i class="fas fa-money-check-alt text-brown-600"></i> Thanh Toán Đơn Hàng
            </h1>
        </div>

        <form id="checkoutForm" method="POST" action="checkout.php" class="flex flex-col lg:flex-row gap-8 items-start">
            <input type="hidden" name="action" value="process_checkout">
            
            <div class="w-full lg:flex-1 bg-white rounded-3xl shadow-sm border border-slate-100 p-6 sm:p-8">
                <h2 class="text-xl font-black text-slate-800 mb-6 pb-4 border-b border-slate-100 flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-brown-500"></i> Thông Tin Nhận Hàng
                </h2>
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Họ và tên người nhận</label>
                        <input type="text" value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-800 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all" readonly>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Số điện thoại liên hệ <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" required class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-800 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Địa chỉ giao hàng chi tiết <span class="text-red-500">*</span></label>
                        <textarea name="address" rows="3" required class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-800 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm resize-none"><?= htmlspecialchars($currentUser['address'] ?? '') ?></textarea>
                    </div>

                    <div class="pt-4">
                        <label class="block text-sm font-bold text-slate-700 mb-3">Phương thức thanh toán</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="pay-option relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <input type="radio" name="payment_method" value="cod" class="peer sr-only" checked>
                                <div class="flex w-full items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-truck text-brown-600 text-xl"></i>
                                        <div class="text-sm font-bold text-slate-800">Thanh toán khi nhận hàng (COD)</div>
                                    </div>
                                    <i class="fas fa-check-circle text-brown-600 text-lg pay-check"></i>
                                </div>
                            </label>
                            <label class="pay-option relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <input type="radio" name="payment_method" value="banking" class="peer sr-only">
                                <div class="flex w-full items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-qrcode text-brown-600 text-xl"></i>
                                        <div class="text-sm font-bold text-slate-800">Chuyển khoản QR ngân hàng</div>
                                    </div>
                                    <i class="fas fa-check-circle text-brown-600 text-lg pay-check"></i>
                                </div>
                            </label>
                        </div>
                        <p id="bankingHint" class="hidden mt-3 text-sm text-slate-500">Sau khi đặt hàng, mã QR kèm nội dung chuyển khoản sẽ hiển thị để bạn quét thanh toán.</p>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-[420px] shrink-0 sticky top-28">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                    <div class="p-6 pb-4 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="text-lg font-black text-slate-800 flex items-center justify-between">
                            <span>Sản Phẩm Của Bạn</span>
                            <span class="bg-brown-100 text-brown-700 text-xs py-1 px-2.5 rounded-full"><?= count($products) ?> món</span>
                        </h2>
                    </div>
                    
                    <div class="max-h-[280px] overflow-y-auto custom-scroll p-6 py-2">
                        <div class="space-y-4">
                            <?php foreach ($products as $item): ?>
                                <div class="flex items-center gap-4 py-3 border-b border-slate-50 last:border-none">
                                    <div class="w-16 h-16 shrink-0 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden relative">
                                        <img src="<?= htmlspecialchars(product_image_url($item['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                                        <span class="absolute -top-1.5 -right-1.5 bg-slate-800 text-white text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white shadow-sm">
                                            <?= $item['cart_qty'] ?>
                                        </span>
                                    </div>
                                    <div class="flex flex-col flex-1 py-1 min-w-0">
                                        <div class="font-bold text-slate-800 text-sm truncate mb-1">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </div>
                                        <div class="font-black text-brown-600 text-sm">
                                            <?= number_format($item['subtotal'], 0, ',', '.') ?>đ
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="p-6 bg-slate-50/50 border-t border-slate-100">
                        <div class="space-y-3 mb-5">
                            <div class="flex justify-between items-center text-slate-600 text-sm font-medium">
                                <span>Tạm tính</span>
                                <span class="font-bold text-slate-800"><?= number_format($total_amount, 0, ',', '.') ?>đ</span>
                            </div>
                            <div class="flex justify-between items-center text-slate-600 text-sm font-medium">
                                <span>Phí vận chuyển</span>
                                <span class="font-bold text-emerald-600">Miễn phí</span>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 border-dashed mb-5"></div>

                        <div class="flex justify-between items-end mb-6">
                            <span class="font-bold text-slate-800">Tổng cộng</span>
                            <span class="text-2xl font-black text-red-600 leading-none"><?= number_format($total_amount, 0, ',', '.') ?>đ</span>
                        </div>

                        <button type="submit" id="submitBtn" class="w-full bg-brown-600 hover:bg-brown-700 text-white py-4 rounded-xl font-black shadow-lg shadow-brown-500/30 transition-all hover:-translate-y-1 relative overflow-hidden group">
                            <span class="relative z-10 flex items-center justify-center gap-2">
                                XÁC NHẬN ĐẶT HÀNG <i class="fas fa-check-circle"></i>
                            </span>
                            <div class="absolute inset-0 h-full w-full bg-white/20 scale-x-0 group-hover:scale-x-100 origin-left transition-transform duration-500 ease-out z-0"></div>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .swal2-popup.custom-swal { border-radius: 1.5rem !important; padding: 2rem !important; }
    .swal2-title { font-size: 1.5rem !important; color: #1e293b !important; }
    .swal2-html-container { font-weight: 500 !important; color: #64748b !important; }
</style>

<script>
    document.querySelectorAll('input[name="payment_method"]').forEach(function(r) {
        r.addEventListener('change', function() {
            document.getElementById('bankingHint').classList.toggle('hidden', this.value !== 'banking' || !this.checked);
        });
    });

    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const phone = document.querySelector('input[name="phone"]').value.trim();
        const address = document.querySelector('textarea[name="address"]').value.trim();

        if(!phone || !address) {
            Swal.fire({
                icon: 'warning',
                title: 'Thiếu thông tin!',
                text: 'Vui lòng điền đầy đủ số điện thoại và địa chỉ giao hàng.',
                confirmButtonColor: '#ca7344',
                customClass: { popup: 'custom-swal' }
            });
            return;
        }

        Swal.fire({
            title: 'Đang xử lý đơn hàng...',
            html: 'Vui lòng không đóng trình duyệt',
            allowOutsideClick: false,
            showConfirmButton: false,
            customClass: { popup: 'custom-swal' },
            didOpen: () => {
                Swal.showLoading();
            }
        });

        setTimeout(() => {
            Swal.fire({
                icon: 'success',
                title: 'Đặt hàng thành công!',
                html: 'Mã đơn hàng đã được ghi nhận.<br>Hệ thống đang chuyển hướng...',
                showConfirmButton: false,
                timer: 1800,
                timerProgressBar: true,
                customClass: { popup: 'custom-swal' }
            }).then(() => {
                this.submit();
            });
        }, 1200);
    });
</script>

<?php require_once 'footer.php'; ?>