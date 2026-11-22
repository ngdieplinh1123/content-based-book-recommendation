<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($action === 'update' && $id > 0) {
        $qty = (int)$_POST['quantity'];
        if ($qty > 0) {
            $_SESSION['cart'][$id] = $qty;
        } else {
            unset($_SESSION['cart'][$id]);
        }
    } elseif ($action === 'delete' && $id > 0) {
        unset($_SESSION['cart'][$id]);
    }
    
    header("Location: cart.php");
    exit;
}

require_once 'header.php';

$cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$products = [];
$total_price = 0;
$total_quantity = 0;

if (!empty($cart_items)) {
    $ids = implode(',', array_map('intval', array_keys($cart_items)));
    try {
        $stmt = $pdo->query("SELECT id, name, image, price, discount_price, quantity as stock FROM products WHERE id IN ($ids)");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['cart_qty'] = $cart_items[$row['id']];
            
            if ($row['cart_qty'] > $row['stock']) {
                $row['cart_qty'] = $row['stock'];
                $_SESSION['cart'][$row['id']] = $row['stock'];
            }

            $originalPrice = (float)$row['price'];
            $discountPrice = (float)$row['discount_price'];
            $current_price = ($discountPrice > 0 && $discountPrice < $originalPrice) ? $discountPrice : $originalPrice;
            
            $row['current_price'] = $current_price;
            $row['old_price'] = ($current_price < $originalPrice) ? $originalPrice : 0;
            $row['subtotal'] = $current_price * $row['cart_qty'];
            
            $total_price += $row['subtotal'];
            $total_quantity += $row['cart_qty'];
            $products[] = $row;
        }
    } catch (\PDOException $e) {}
}

$is_logged_in = isset($_SESSION['user']);
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
    input[type=number]::-webkit-inner-spin-button, 
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }
</style>

<div class="catbook-store-page min-h-screen pb-20 pt-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 flex items-center gap-3">
                <i class="fas fa-shopping-basket text-brown-600"></i> Giỏ Hàng Của Bạn
            </h1>
            <?php if (!empty($products)): ?>
                <p class="text-slate-500 font-medium mt-2">Bạn đang có <?= count($products) ?> sản phẩm trong giỏ hàng</p>
            <?php endif; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-10 flex flex-col items-center justify-center text-center">
                <div class="w-40 h-40 bg-brown-50 rounded-full flex items-center justify-center mb-6 text-brown-300">
                    <i class="fas fa-cart-arrow-down text-6xl"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800 mb-2">Giỏ hàng trống</h2>
                <p class="text-slate-500 mb-8 max-w-md">Chưa có sản phẩm nào trong giỏ hàng của bạn. Hãy khám phá hàng ngàn tựa sách hấp dẫn tại cửa hàng!</p>
                <a href="products.php" class="bg-brown-600 hover:bg-brown-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-brown-500/30 transition-transform hover:-translate-y-1">
                    Tiếp tục mua sắm
                </a>
            </div>
        <?php else: ?>
            <div class="flex flex-col lg:flex-row gap-8 items-start">
                
                <div class="w-full lg:flex-1 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                    <div class="p-5 border-b border-slate-100 bg-slate-50/50 hidden sm:grid grid-cols-12 gap-4 text-sm font-bold text-slate-500 uppercase tracking-wide">
                        <div class="col-span-6">Sản phẩm</div>
                        <div class="col-span-3 text-center">Số lượng</div>
                        <div class="col-span-3 text-right pr-2">Tạm tính</div>
                    </div>
                    
                    <div class="max-h-[520px] overflow-y-auto custom-scroll p-2 sm:p-0">
                        <?php foreach ($products as $item): ?>
                            <div class="flex flex-col sm:grid sm:grid-cols-12 gap-4 sm:gap-4 items-start sm:items-center p-4 border-b border-slate-50 last:border-none hover:bg-slate-50/50 transition-colors group rounded-2xl sm:rounded-none">
                                
                                <div class="col-span-6 flex gap-4 w-full">
                                    <div class="w-20 h-20 sm:w-24 sm:h-24 shrink-0 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden relative">
                                        <img src="<?= htmlspecialchars(product_image_url($item['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                                    </div>
                                    <div class="flex flex-col justify-between py-1 w-full">
                                        <a href="product_detail.php?id=<?= $item['id'] ?>" class="font-bold text-slate-800 hover:text-brown-600 line-clamp-2 leading-snug mb-2 pr-6 relative">
                                            <?= htmlspecialchars($item['name']) ?>
                                            <button type="button" onclick="confirmDelete(<?= $item['id'] ?>)" class="absolute top-0 right-0 sm:hidden text-slate-400 hover:text-red-500 p-1">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </a>
                                        <div class="flex flex-wrap items-end gap-2">
                                            <span class="font-black text-brown-600 text-base sm:text-lg"><?= number_format($item['current_price'], 0, ',', '.') ?>đ</span>
                                            <?php if ($item['old_price'] > 0): ?>
                                                <span class="text-xs text-slate-400 line-through font-medium pb-0.5"><?= number_format($item['old_price'], 0, ',', '.') ?>đ</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-span-3 flex justify-between sm:justify-center items-center w-full sm:w-auto mt-2 sm:mt-0">
                                    <span class="sm:hidden font-bold text-slate-500 text-sm">Số lượng:</span>
                                    <div class="flex items-center border-2 border-slate-200 rounded-xl h-10 w-28 bg-white overflow-hidden shadow-sm">
                                        <button type="button" onclick="updateQty(<?= $item['id'] ?>, -1, <?= $item['stock'] ?>)" class="w-8 h-full flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-brown-600 transition-colors">
                                            <i class="fas fa-minus text-[10px]"></i>
                                        </button>
                                        <input type="number" id="qty_<?= $item['id'] ?>" value="<?= $item['cart_qty'] ?>" class="w-full h-full text-center font-bold text-slate-800 bg-transparent border-none p-0 focus:outline-none" readonly>
                                        <button type="button" onclick="updateQty(<?= $item['id'] ?>, 1, <?= $item['stock'] ?>)" class="w-8 h-full flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-brown-600 transition-colors">
                                            <i class="fas fa-plus text-[10px]"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-span-3 flex justify-between sm:justify-end items-center w-full sm:w-auto mt-2 sm:mt-0 sm:pr-4">
                                    <span class="sm:hidden font-bold text-slate-500 text-sm">Tạm tính:</span>
                                    <div class="flex items-center gap-4">
                                        <span class="font-black text-red-600 text-lg"><?= number_format($item['subtotal'], 0, ',', '.') ?>đ</span>
                                        <button type="button" onclick="confirmDelete(<?= $item['id'] ?>)" class="hidden sm:flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-red-500 transition-colors focus:outline-none">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="w-full lg:w-[380px] shrink-0 sticky top-28">
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 sm:p-8">
                        <h2 class="text-xl font-black text-slate-800 mb-6 pb-4 border-b border-slate-100">Tổng Đơn Hàng</h2>
                        
                        <div class="space-y-4 mb-6">
                            <div class="flex justify-between items-center text-slate-600 font-medium">
                                <span>Tổng số lượng:</span>
                                <span class="font-bold text-slate-800"><?= $total_quantity ?> sản phẩm</span>
                            </div>
                            <div class="flex justify-between items-center text-slate-600 font-medium">
                                <span>Tạm tính:</span>
                                <span class="font-bold text-slate-800"><?= number_format($total_price, 0, ',', '.') ?>đ</span>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 border-dashed my-6"></div>

                        <div class="flex justify-between items-end mb-8">
                            <span class="font-bold text-slate-800 text-lg">Thành tiền:</span>
                            <div class="text-right">
                                <span class="block text-3xl font-black text-red-600 leading-none"><?= number_format($total_price, 0, ',', '.') ?>đ</span>
                                <span class="text-xs text-slate-400 font-medium mt-1 inline-block">(Đã bao gồm VAT)</span>
                            </div>
                        </div>

                        <?php if ($is_logged_in): ?>
                            <a href="checkout.php" class="flex items-center justify-center gap-2 w-full bg-brown-600 hover:bg-brown-700 text-white py-4 rounded-xl font-black shadow-lg shadow-brown-500/30 transition-transform hover:-translate-y-1">
                                TIẾN HÀNH THANH TOÁN <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <a href="login.php?redirect=cart.php" class="flex items-center justify-center gap-2 w-full bg-slate-800 hover:bg-slate-900 text-white py-4 rounded-xl font-black shadow-lg shadow-slate-800/30 transition-transform hover:-translate-y-1">
                                <i class="fas fa-user-circle text-lg"></i> ĐĂNG NHẬP ĐỂ THANH TOÁN
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-6 flex items-center justify-center gap-4 text-slate-400 text-2xl">
                        <i class="fab fa-cc-visa hover:text-blue-600 transition-colors"></i>
                        <i class="fab fa-cc-mastercard hover:text-orange-500 transition-colors"></i>
                        <i class="fab fa-cc-paypal hover:text-blue-400 transition-colors"></i>
                        <i class="fab fa-cc-apple-pay hover:text-slate-800 transition-colors"></i>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div>
</div>

<form id="updateForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" id="updateId">
    <input type="hidden" name="quantity" id="updateQty">
</form>

<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>.swal2-popup.custom-swal { border-radius: 1.5rem !important; }</style>

<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'bottom-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true,
        customClass: { popup: 'rounded-2xl shadow-2xl border border-slate-100' }
    });

    function updateQty(id, change, maxStock) {
        const input = document.getElementById('qty_' + id);
        let val = parseInt(input.value) + change;
        
        if (val < 1) {
            confirmDelete(id);
            return;
        }
        
        if (val > maxStock) {
            Toast.fire({ icon: 'warning', title: 'Đã đạt số lượng tồn kho tối đa!' });
            val = maxStock;
        }
        
        document.getElementById('updateId').value = id;
        document.getElementById('updateQty').value = val;
        document.getElementById('updateForm').submit();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Xóa khỏi giỏ hàng?',
            text: "Bạn muốn bỏ sản phẩm này khỏi giỏ hàng?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e3342f',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Đồng ý xóa',
            cancelButtonText: 'Không',
            customClass: { popup: 'custom-swal' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        });
    }
</script>

<?php require_once 'footer.php'; ?>