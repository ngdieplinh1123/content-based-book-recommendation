<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php?redirect=orders.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    if ($order_id > 0) {
        try {
            $stmtCheck = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
            $stmtCheck->execute([$order_id, $user_id]);
            $currentStatus = $stmtCheck->fetchColumn();

            if ($currentStatus === 'pending') {
                $pdo->beginTransaction();
                
                $stmtUpdate = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
                $stmtUpdate->execute([$order_id]);

                $stmtDetails = $pdo->prepare("SELECT product_id, quantity FROM order_details WHERE order_id = ?");
                $stmtDetails->execute([$order_id]);
                $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

                $stmtRestoreStock = $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
                foreach ($details as $d) {
                    $stmtRestoreStock->execute([$d['quantity'], $d['product_id']]);
                }

                $pdo->commit();
                $_SESSION['action_success'] = 'Hủy đơn hàng thành công!';
            }
        } catch (\Exception $e) {
            $pdo->rollBack();
            $_SESSION['action_error'] = 'Có lỗi xảy ra khi hủy đơn hàng.';
        }
        header("Location: orders.php");
        exit;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review_order') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
    $comment = trim((string)($_POST['comment'] ?? ''));

    if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
        $_SESSION['action_error'] = 'Phiên làm việc đã hết hạn. Vui lòng thử lại.';
    } elseif ($order_id <= 0 || $rating === false || $rating < 1 || $rating > 5 || $comment === '' || mb_strlen($comment, 'UTF-8') > 2000) {
        $_SESSION['action_error'] = 'Vui lòng chọn số sao và nhập phản hồi tối đa 2.000 ký tự.';
    } else {
        try {
            $stmtOrder = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'completed'");
            $stmtOrder->execute([$order_id, $user_id]);
            if (!$stmtOrder->fetchColumn()) {
                $_SESSION['action_error'] = 'Chỉ đơn hàng đã giao thành công mới có thể đánh giá.';
            } else {
                $stmtItems = $pdo->prepare("SELECT DISTINCT product_id FROM order_details WHERE order_id = ?");
                $stmtItems->execute([$order_id]);
                $productIds = $stmtItems->fetchAll(PDO::FETCH_COLUMN);

                $pdo->beginTransaction();
                $stmtSave = $pdo->prepare(
                    'INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE order_id = VALUES(order_id), rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP'
                );
                foreach ($productIds as $productId) {
                    $stmtSave->execute([(int)$productId, $user_id, $order_id, $rating, $comment]);
                }
                $pdo->commit();
                $_SESSION['action_success'] = 'Đã gửi đánh giá cho ' . count($productIds) . ' sản phẩm trong đơn hàng!';
            }
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Unable to save order review: ' . $e->getMessage());
            $_SESSION['action_error'] = 'Chưa thể lưu đánh giá lúc này. Vui lòng thử lại sau.';
        }
    }
    header("Location: orders.php#order-" . $order_id);
    exit;
}

require_once 'header.php';

$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = per_page(5, [5, 10, 20, 50, 0]);
$offset = ($page - 1) * $limit;

try {
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $totalStmt->execute([$user_id]);
    $totalOrders = $totalStmt->fetchColumn();
    $totalPages = ceil($totalOrders / $limit);

    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $orderStmt->execute([$user_id]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

    $orderItems = [];
    $orderIds = array_map('intval', array_column($orders, 'id'));
    if ($orderIds) {
        $allIn = implode(',', $orderIds);
        $itemStmt = $pdo->query("SELECT od.order_id, od.quantity, od.price, p.id, p.name, p.image FROM order_details od INNER JOIN products p ON p.id = od.product_id WHERE od.order_id IN ($allIn) ORDER BY od.id");
        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $orderItems[(int)$item['order_id']][] = $item;
        }
    }

    $completedItems = [];
    $orderReviews = [];
    $completedIds = array_map('intval', array_column(array_filter($orders, fn($o) => $o['status'] === 'completed'), 'id'));
    if ($completedIds) {
        $in = implode(',', $completedIds);
        $itemStmt = $pdo->query("SELECT od.order_id, p.id, p.name FROM order_details od INNER JOIN products p ON p.id = od.product_id WHERE od.order_id IN ($in) GROUP BY od.order_id, p.id, p.name");
        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $completedItems[(int)$item['order_id']][] = $item;
        }
        $reviewStmt = $pdo->prepare("SELECT order_id, rating, comment FROM product_reviews WHERE user_id = ? AND order_id IN ($in) ORDER BY updated_at DESC");
        $reviewStmt->execute([$user_id]);
        foreach ($reviewStmt->fetchAll(PDO::FETCH_ASSOC) as $rv) {
            $orderReviews[(int)$rv['order_id']] ??= $rv;
        }
    }

} catch (\PDOException $e) {
    $orders = [];
    $totalPages = 1;
}

$statusMap = [
    'pending' => ['label' => 'Chờ xác nhận', 'color' => 'orange', 'icon' => 'fa-clock', 'step' => 1],
    'processing' => ['label' => 'Đang xử lý', 'color' => 'blue', 'icon' => 'fa-box', 'step' => 2],
    'shipping' => ['label' => 'Đang giao hàng', 'color' => 'purple', 'icon' => 'fa-truck', 'step' => 3],
    'completed' => ['label' => 'Đã giao', 'color' => 'emerald', 'icon' => 'fa-check-circle', 'step' => 4],
    'cancelled' => ['label' => 'Đã hủy', 'color' => 'red', 'icon' => 'fa-times-circle', 'step' => 0]
];

function buildUrl($newPage) {
    $qs = $_GET;
    $qs['page'] = $newPage;
    return '?' . http_build_query($qs);
}

$swal_script = '';
if (isset($_SESSION['action_success'])) {
    $swal_script = "showToast('success', '" . $_SESSION['action_success'] . "');";
    unset($_SESSION['action_success']);
}
if (isset($_SESSION['action_error'])) {
    $swal_script = "showToast('error', '" . $_SESSION['action_error'] . "');";
    unset($_SESSION['action_error']);
}
if (isset($_SESSION['checkout_success'])) {
    $swal_script = "Swal.fire({icon: 'success', title: 'Đặt hàng thành công!', text: 'Cảm ơn bạn đã mua sắm.', confirmButtonColor: '#ca7344', customClass: { popup: 'rounded-3xl' }});";
    unset($_SESSION['checkout_success']);
}
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
    
    .modal-overlay { transition: opacity 0.3s ease; }
    .modal-content { transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    
    .step-line { transition: width 0.5s ease-in-out; }
    .step-icon { transition: all 0.3s ease; }
    .order-rating input:checked ~ label,
    .order-rating label:hover,
    .order-rating label:hover ~ label { color: #e87500; }
</style>

<div class="catbook-store-page min-h-screen pb-20 pt-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 flex items-center gap-3">
                <i class="fas fa-box-open text-brown-600"></i> Đơn Hàng Của Tôi
            </h1>
            <p class="text-slate-500 font-medium mt-2">Quản lý và theo dõi trạng thái các đơn hàng của bạn</p>
        </div>

        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-12 flex flex-col items-center justify-center text-center">
                <div class="w-32 h-32 bg-slate-50 rounded-full flex items-center justify-center mb-6 text-slate-300">
                    <i class="fas fa-receipt text-5xl"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800 mb-2">Chưa có đơn hàng nào</h2>
                <p class="text-slate-500 mb-8 max-w-md">Bạn chưa thực hiện giao dịch nào. Hãy khám phá các sản phẩm tuyệt vời của chúng tôi!</p>
                <a href="products.php" class="bg-brown-600 hover:bg-brown-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-brown-500/30 transition-transform hover:-translate-y-1">
                    Mua sắm ngay
                </a>
            </div>
        <?php else: ?>
            <?= per_page_selector($limit, $page, (int)$totalOrders, [5, 10, 20, 50, 0]) ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <?php 
                        $statusInfo = $statusMap[$order['status']] ?? $statusMap['pending'];
                        $currentStep = $statusInfo['step'];
                        $isCancelled = $order['status'] === 'cancelled';
                    ?>
                    <div id="order-<?= (int)$order['id'] ?>" class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden flex flex-col scroll-mt-28">
                        
                        <div class="p-5 sm:p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-<?= $statusInfo['color'] ?>-100 text-<?= $statusInfo['color'] ?>-600 flex items-center justify-center text-xl shadow-inner shrink-0">
                                    <i class="fas <?= $statusInfo['icon'] ?>"></i>
                                </div>
                                <div>
                                    <h3 class="font-black text-slate-800 text-lg">Đơn Hàng <?= $order['id'] ?></h3>
                                    <p class="text-xs sm:text-sm font-medium text-slate-500">
                                        <i class="far fa-calendar-alt mr-1"></i> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between sm:justify-end gap-3 sm:gap-4 w-full sm:w-auto mt-2 sm:mt-0">
                                <span class="px-4 py-1.5 rounded-full text-xs sm:text-sm font-bold bg-<?= $statusInfo['color'] ?>-100 text-<?= $statusInfo['color'] ?>-700 border border-<?= $statusInfo['color'] ?>-200 shadow-sm whitespace-nowrap">
                                    <?= $statusInfo['label'] ?>
                                </span>
                                
                                <div class="flex gap-2">
                                    <button type="button" onclick="viewOrderDetails(<?= $order['id'] ?>)" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-blue-50 shadow-sm flex items-center justify-center transition-all focus:outline-none" title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <button type="button" onclick="confirmCancel(<?= $order['id'] ?>)" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-red-600 hover:border-red-300 hover:bg-red-50 shadow-sm flex items-center justify-center transition-all focus:outline-none" title="Hủy đơn hàng">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!$isCancelled): ?>
                        <div class="px-6 py-8 sm:px-10 hidden sm:block border-b border-slate-50">
                            <div class="relative max-w-2xl mx-auto">
                                <div class="absolute top-1/2 left-0 w-full h-1.5 bg-slate-100 rounded-full -translate-y-1/2"></div>
                                
                                <?php 
                                    $progressWidth = 0;
                                    if($currentStep == 1) $progressWidth = 0;
                                    elseif($currentStep == 2) $progressWidth = 33.33;
                                    elseif($currentStep == 3) $progressWidth = 66.66;
                                    elseif($currentStep == 4) $progressWidth = 100;
                                ?>
                                <div class="absolute top-1/2 left-0 h-1.5 bg-brown-500 rounded-full -translate-y-1/2 step-line" style="width: <?= $progressWidth ?>%;"></div>
                                
                                <div class="relative flex justify-between items-center z-10">
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm border-4 <?= $currentStep >= 1 ? 'bg-brown-600 border-brown-200 text-white shadow-md' : 'bg-white border-slate-200 text-slate-400' ?> step-icon">1</div>
                                        <span class="text-xs font-bold <?= $currentStep >= 1 ? 'text-brown-700' : 'text-slate-400' ?> absolute -bottom-6 whitespace-nowrap">Chờ xác nhận</span>
                                    </div>
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm border-4 <?= $currentStep >= 2 ? 'bg-brown-600 border-brown-200 text-white shadow-md' : 'bg-white border-slate-200 text-slate-400' ?> step-icon">2</div>
                                        <span class="text-xs font-bold <?= $currentStep >= 2 ? 'text-brown-700' : 'text-slate-400' ?> absolute -bottom-6 whitespace-nowrap">Đang xử lý</span>
                                    </div>
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm border-4 <?= $currentStep >= 3 ? 'bg-brown-600 border-brown-200 text-white shadow-md' : 'bg-white border-slate-200 text-slate-400' ?> step-icon">3</div>
                                        <span class="text-xs font-bold <?= $currentStep >= 3 ? 'text-brown-700' : 'text-slate-400' ?> absolute -bottom-6 whitespace-nowrap">Giao hàng</span>
                                    </div>
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm border-4 <?= $currentStep >= 4 ? 'bg-brown-600 border-brown-200 text-white shadow-md' : 'bg-white border-slate-200 text-slate-400' ?> step-icon">4</div>
                                        <span class="text-xs font-bold <?= $currentStep >= 4 ? 'text-brown-700' : 'text-slate-400' ?> absolute -bottom-6 whitespace-nowrap">Hoàn thành</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($orderItems[(int)$order['id']])): ?>
                            <div class="px-5 sm:px-6 py-4 border-t border-slate-100 bg-white">
                                <h4 class="text-sm font-semibold text-slate-800 mb-3">Sản phẩm đã đặt (<?= count($orderItems[(int)$order['id']]) ?>)</h4>
                                <div class="divide-y divide-slate-100">
                                    <?php foreach ($orderItems[(int)$order['id']] as $oi): ?>
                                        <a href="product_detail.php?id=<?= (int)$oi['id'] ?>" class="flex items-center gap-4 py-3 group">
                                            <img src="<?= htmlspecialchars(product_image_url($oi['image'] ?? '')) ?>" alt="<?= htmlspecialchars($oi['name']) ?>" class="w-14 h-20 object-cover border border-slate-200 bg-slate-50 flex-shrink-0">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-sm font-medium text-slate-800 group-hover:text-[#e87500] line-clamp-2"><?= htmlspecialchars($oi['name']) ?></div>
                                                <div class="text-xs text-slate-500 mt-1">Số lượng: <?= (int)$oi['quantity'] ?> × <?= number_format((float)$oi['price'], 0, ',', '.') ?>đ</div>
                                            </div>
                                            <div class="text-sm font-semibold text-[#e87500] whitespace-nowrap"><?= number_format((float)$oi['price'] * (int)$oi['quantity'], 0, ',', '.') ?>đ</div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="p-5 sm:p-6 bg-white flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div class="text-sm font-medium text-slate-600 truncate max-w-xs">
                                <i class="fas fa-map-marker-alt text-slate-400 w-4"></i> <?= htmlspecialchars($order['shipping_address'] ?? 'Chưa cập nhật') ?>
                            </div>
                            <div class="text-right w-full sm:w-auto">
                                <?php if (($order['payment_method'] ?? '') === 'banking' && $order['status'] === 'pending'): ?>
                                    <a href="payment.php?id=<?= (int)$order['id'] ?>" class="inline-flex items-center gap-1 text-xs font-bold text-[#e87500] border border-[#e87500] px-3 py-1.5 mr-3 hover:bg-orange-50"><i class="fas fa-qrcode"></i> Thanh toán QR</a>
                                    <div class="text-xs text-amber-700 mb-2"><i class="fas fa-clock"></i> Tự động hủy nếu chưa thanh toán trong 1 giờ kể từ khi đặt.</div>
                                <?php endif; ?>
                                <span class="text-sm font-bold text-slate-500 mr-2">Tổng tiền:</span>
                                <span class="text-xl sm:text-2xl font-black text-red-600"><?= number_format($order['total_amount'], 0, ',', '.') ?>đ</span>
                            </div>
                        </div>

                        <?php if ($order['status'] === 'completed' && !empty($completedItems[(int)$order['id']])): ?>
                            <?php $ownReview = $orderReviews[(int)$order['id']] ?? null; $oid = (int)$order['id']; ?>
                            <div class="p-5 sm:p-6 border-t border-slate-100 bg-[#faf9f7]">
                                <h4 class="font-semibold text-slate-800 mb-2">Đánh giá và phản hồi đơn hàng</h4>
                                <p class="text-sm text-slate-500 mb-3">Đánh giá của bạn sẽ được áp dụng cho <?= count($completedItems[$oid]) ?> sách trong đơn:</p>
                                <ul class="text-sm text-slate-600 mb-4 list-disc pl-5 space-y-1">
                                    <?php foreach ($completedItems[$oid] as $it): ?>
                                        <li><a class="hover:text-brown-600" href="product_detail.php?id=<?= (int)$it['id'] ?>#reviews"><?= htmlspecialchars($it['name']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                                <form method="POST" action="orders.php" class="space-y-3">
                                    <input type="hidden" name="action" value="review_order">
                                    <input type="hidden" name="order_id" value="<?= $oid ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <fieldset>
                                        <legend class="text-sm font-medium text-slate-700 mb-1">Số sao</legend>
                                        <div class="flex flex-row-reverse justify-end gap-1 order-rating">
                                            <?php for ($s = 5; $s >= 1; $s--): ?>
                                                <input class="sr-only peer" type="radio" id="or-<?= $oid ?>-<?= $s ?>" name="rating" value="<?= $s ?>" <?= (int)($ownReview['rating'] ?? 0) === $s ? 'checked' : '' ?> required>
                                                <label for="or-<?= $oid ?>-<?= $s ?>" class="cursor-pointer text-2xl text-slate-300 hover:text-[#e87500] peer-checked:text-[#e87500]" aria-label="<?= $s ?> sao"><i class="fas fa-star"></i></label>
                                            <?php endfor; ?>
                                        </div>
                                    </fieldset>
                                    <textarea name="comment" rows="3" maxlength="2000" required placeholder="Chia sẻ cảm nhận về các sách trong đơn hàng..." class="w-full border border-slate-300 bg-white p-3 text-sm focus:outline-none focus:border-[#e87500]"><?= htmlspecialchars($ownReview['comment'] ?? '') ?></textarea>
                                    <button type="submit" class="bg-[#e87500] hover:bg-[#c65f00] text-white px-6 py-2.5 text-sm font-semibold">
                                        <?= $ownReview ? 'Cập nhật đánh giá' : 'Gửi đánh giá cho cả đơn' ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-center mt-10">
                        <div class="flex items-center gap-1.5 sm:gap-2 bg-white p-1.5 sm:p-2 rounded-2xl shadow-sm border border-slate-100">
                            <?php if ($page > 1): ?>
                                <a href="<?= buildUrl($page - 1) ?>" class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-500 hover:bg-brown-50 hover:text-brown-600 transition-colors font-bold">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                    <a href="<?= buildUrl($i) ?>" class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl font-bold transition-all <?= $page === $i ? 'bg-brown-600 text-white shadow-md shadow-brown-500/30' : 'bg-slate-50 text-slate-600 hover:bg-brown-50 hover:text-brown-600' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                    <span class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center text-slate-400 font-black tracking-widest">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= buildUrl($page + 1) ?>" class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-500 hover:bg-brown-50 hover:text-brown-600 transition-colors font-bold">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>
</div>

<form id="cancelOrderForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="cancel_order">
    <input type="hidden" name="order_id" id="cancelOrderId">
</form>

<div id="orderModal" class="fixed inset-0 z-[100] flex items-center justify-center px-4 py-6 opacity-0 pointer-events-none modal-overlay hidden">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm modal-bg"></div>
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl max-h-full flex flex-col relative z-10 scale-95 modal-content overflow-hidden">
        
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/80 shrink-0">
            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-receipt text-brown-600"></i> Chi Tiết Đơn Hàng <span id="modalOrderId" class="text-brown-600"></span>
            </h2>
            <button id="closeModalBtn" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 text-slate-500 hover:bg-red-100 hover:text-red-500 transition-colors focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-6 overflow-y-auto custom-scroll flex-1 bg-white">
            <div id="modalLoading" class="flex flex-col justify-center items-center py-12 hidden">
                <i class="fas fa-circle-notch fa-spin text-4xl text-brown-500 mb-4"></i>
                <span class="text-slate-500 font-medium">Đang tải thông tin...</span>
            </div>
            
            <div id="modalContent" class="hidden space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
                        <h4 class="font-bold text-slate-800 mb-3 flex items-center gap-2 text-sm uppercase tracking-wide">
                            <i class="fas fa-user-circle text-brown-500"></i> Thông Tin Khách Hàng
                        </h4>
                        <div class="space-y-2 text-sm text-slate-600">
                            <p><span class="font-bold text-slate-700">Tên:</span> <span id="modalCustName"></span></p>
                            <p><span class="font-bold text-slate-700">SĐT:</span> <span id="modalCustPhone"></span></p>
                        </div>
                    </div>
                    <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
                        <h4 class="font-bold text-slate-800 mb-3 flex items-center gap-2 text-sm uppercase tracking-wide">
                            <i class="fas fa-truck text-brown-500"></i> Giao Hàng
                        </h4>
                        <div class="space-y-2 text-sm text-slate-600">
                            <p><span class="font-bold text-slate-700">Trạng thái:</span> <span id="modalStatus" class="font-bold"></span></p>
                            <p><span class="font-bold text-slate-700">Địa chỉ:</span> <span id="modalAddress"></span></p>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="font-black text-slate-800 mb-4 pb-2 border-b border-slate-100 text-lg">Sản Phẩm Đã Đặt</h4>
                    <div id="modalProductsList" class="space-y-3">
                        
                    </div>
                </div>

                <div class="bg-brown-50 rounded-2xl p-5 border border-brown-100 flex justify-between items-center">
                    <span class="font-bold text-brown-800">Tổng thanh toán:</span>
                    <span id="modalTotal" class="text-2xl font-black text-red-600"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>.swal2-popup.custom-swal { border-radius: 1.5rem !important; }</style>

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
        Toast.fire({ icon: icon, title: title });
    }

    <?php if ($swal_script !== ''): ?>
    document.addEventListener('DOMContentLoaded', function() {
        <?= $swal_script ?>
    });
    <?php endif; ?>

    function confirmCancel(id) {
        Swal.fire({
            title: 'Hủy đơn hàng?',
            text: "Bạn chắc chắn muốn hủy đơn hàng này?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e3342f',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Đồng ý hủy',
            cancelButtonText: 'Không',
            customClass: { popup: 'custom-swal' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('cancelOrderId').value = id;
                document.getElementById('cancelOrderForm').submit();
            }
        });
    }

    const modal = document.getElementById('orderModal');
    const modalBg = document.querySelector('.modal-bg');
    const modalContentContainer = document.querySelector('.modal-content');
    const closeBtn = document.getElementById('closeModalBtn');

    function openModal() {
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modalContentContainer.classList.remove('scale-95');
            document.body.classList.add('overflow-hidden');
        }, 10);
    }

    function closeModal() {
        modal.classList.add('opacity-0', 'pointer-events-none');
        modalContentContainer.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }, 300);
    }

    closeBtn.addEventListener('click', closeModal);
    modalBg.addEventListener('click', closeModal);

    const statusMap = {
        'pending': { label: 'Chờ xác nhận', color: 'text-orange-600' },
        'processing': { label: 'Đang xử lý', color: 'text-blue-600' },
        'shipping': { label: 'Đang giao hàng', color: 'text-purple-600' },
        'completed': { label: 'Đã giao', color: 'text-emerald-600' },
        'cancelled': { label: 'Đã hủy', color: 'text-red-600' }
    };

    function formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
    }

    function viewOrderDetails(orderId) {
        openModal();
        document.getElementById('modalLoading').classList.remove('hidden');
        document.getElementById('modalContent').classList.add('hidden');

        fetch(`ajax_get_order.php?id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    const order = data.order;
                    const details = data.details;
                    const sMap = statusMap[order.status] || statusMap['pending'];

                    document.getElementById('modalOrderId').textContent = order.id;
                    document.getElementById('modalCustName').textContent = data.user.username;
                    document.getElementById('modalCustPhone').textContent = order.shipping_phone;
                    document.getElementById('modalAddress').textContent = order.shipping_address;
                    
                    const statusEl = document.getElementById('modalStatus');
                    statusEl.textContent = sMap.label;
                    statusEl.className = `font-bold ${sMap.color}`;
                    
                    document.getElementById('modalTotal').textContent = formatCurrency(order.total_amount);

                    const productsContainer = document.getElementById('modalProductsList');
                    productsContainer.innerHTML = '';
                    
                    details.forEach(item => {
                        const html = `
                            <div class="flex items-center gap-4 py-3 border-b border-slate-50 last:border-0 bg-white rounded-xl p-3 shadow-sm border border-slate-100">
                                <div class="w-16 h-16 shrink-0 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden relative">
                                    <img src="${item.image}" class="w-full h-full object-cover">
                                    <span class="absolute top-0 right-0 bg-slate-800 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-bl-lg shadow-sm">${item.quantity}</span>
                                </div>
                                <div class="flex flex-col flex-1 min-w-0">
                                    <div class="font-bold text-slate-800 text-sm truncate mb-1">${item.name}</div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-slate-500">${formatCurrency(item.price)} x ${item.quantity}</span>
                                        <span class="font-black text-brown-600 text-sm">${formatCurrency(item.price * item.quantity)}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                        productsContainer.innerHTML += html;
                    });

                    document.getElementById('modalLoading').classList.add('hidden');
                    document.getElementById('modalContent').classList.remove('hidden');
                } else {
                    closeModal();
                    showToast('error', 'Không tìm thấy thông tin đơn hàng!');
                }
            })
            .catch(err => {
                closeModal();
                showToast('error', 'Lỗi kết nối!');
            });
    }
</script>

<?php require_once 'footer.php'; ?>