<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 1 && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, u.username as customer_name 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $stmtDet = $pdo->prepare("
                SELECT od.*, p.name, p.image 
                FROM order_details od 
                JOIN products p ON od.product_id = p.id 
                WHERE od.order_id = ?
            ");
            $stmtDet->execute([$id]);
            $details = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
            foreach ($details as &$detail) {
                $detail['image'] = product_image_url($detail['image'] ?? null, '../uploads/products/');
            }
            unset($detail);
            echo json_encode(['success' => true, 'order' => $order, 'details' => $details]);
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

if (isset($_GET['print']) && $_GET['print'] == 1 && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT o.*, u.username as customer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die("Đơn hàng không tồn tại.");
    }

    $stmtDet = $pdo->prepare("SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON od.product_id = p.id WHERE od.order_id = ?");
    $stmtDet->execute([$id]);
    $details = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
    foreach ($details as &$detail) {
        $detail['image'] = product_image_url($detail['image'] ?? null, '../uploads/products/');
    }
    unset($detail);
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <title>In Hóa Đơn <?= $order['id'] ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @media print {
                @page { margin: 15mm; }
                body { -webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: white !important; }
                .no-print { display: none !important; }
            }
        </style>
    </head>
    <body class="bg-slate-100 text-slate-800 font-sans antialiased">
        <div class="max-w-3xl mx-auto bg-white p-10 min-h-screen sm:min-h-0 sm:my-10 sm:shadow-lg sm:border border-slate-200 print:shadow-none print:border-none print:m-0 print:p-0">
            
            <div class="flex justify-between items-start mb-10 pb-8 border-b-2 border-slate-200">
                <div>
                    <h1 class="text-3xl font-black text-brown-800 tracking-wider">CatBook</h1>
                    <p class="text-slate-500 text-sm mt-2">123 Đường Sách, Quận 1, TP.HCM</p>
                    <p class="text-slate-500 text-sm">Điện thoại: 0123.456.789</p>
                </div>
                <div class="text-right">
                    <h2 class="text-4xl font-black text-slate-800 uppercase tracking-widest mb-2">Hóa Đơn</h2>
                    <p class="text-slate-500 font-bold">Mã ĐH: <span class="text-slate-800"><?= $order['id'] ?></span></p>
                    <p class="text-slate-500">Ngày in: <?= date('d/m/Y H:i') ?></p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-between gap-8 mb-10">
                <div>
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3">Thông Tin Khách Hàng</h3>
                    <p class="font-bold text-slate-800 text-lg mb-1"><?= htmlspecialchars($order['customer_name'] ?? 'Khách lẻ') ?></p>
                    <p class="text-slate-600 mb-1">SĐT: <?= htmlspecialchars($order['shipping_phone']) ?></p>
                    <p class="text-slate-600">Đ/c: <?= htmlspecialchars($order['shipping_address']) ?></p>
                </div>
                <div class="sm:text-right">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3">Thông Tin Đơn Hàng</h3>
                    <p class="text-slate-600 mb-1">Ngày đặt: <span class="font-bold text-slate-800"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span></p>
                    <p class="text-slate-600">Thanh toán: <span class="font-bold text-slate-800">Thanh toán khi nhận hàng (COD)</span></p>
                </div>
            </div>

            <table class="w-full mb-10">
                <thead>
                    <tr class="border-b-2 border-slate-800">
                        <th class="py-3 text-left text-sm font-black text-slate-800 w-16">Ảnh</th>
                        <th class="py-3 text-left text-sm font-black text-slate-800">Sản phẩm</th>
                        <th class="py-3 text-center text-sm font-black text-slate-800 w-20">SL</th>
                        <th class="py-3 text-right text-sm font-black text-slate-800 w-32">Đơn giá</th>
                        <th class="py-3 text-right text-sm font-black text-slate-800 w-32">Thành tiền</th>
                    </tr>
                </thead>
                <tbody class="divide-y border-b-2 border-slate-200">
                    <?php foreach ($details as $item): ?>
                        <tr>
                            <td class="py-4">
                                <img src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>" class="w-12 h-12 object-cover rounded shadow-sm border border-slate-100">
                            </td>
                            <td class="py-4 font-bold text-slate-800 text-sm">
                                <?= htmlspecialchars($item['name']) ?>
                            </td>
                            <td class="py-4 text-center font-bold text-slate-600">
                                <?= $item['quantity'] ?>
                            </td>
                            <td class="py-4 text-right text-slate-600">
                                <?= number_format($item['price'], 0, ',', '.') ?>đ
                            </td>
                            <td class="py-4 text-right font-black text-slate-800">
                                <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="flex justify-end mb-16">
                <div class="w-full sm:w-1/2 lg:w-1/3 space-y-3">
                    <div class="flex justify-between text-slate-600">
                        <span>Tạm tính:</span>
                        <span class="font-bold"><?= number_format($order['total_amount'], 0, ',', '.') ?>đ</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Phí vận chuyển:</span>
                        <span class="font-bold">0đ</span>
                    </div>
                    <div class="flex justify-between items-center border-t-2 border-slate-800 pt-3 mt-3">
                        <span class="font-black text-slate-800 uppercase">Tổng Cộng:</span>
                        <span class="text-2xl font-black text-red-600"><?= number_format($order['total_amount'], 0, ',', '.') ?>đ</span>
                    </div>
                </div>
            </div>

            <div class="text-center text-slate-500 text-sm pt-10 border-t border-slate-200">
                <p class="font-bold text-slate-700 mb-1">Cảm ơn quý khách đã mua sắm tại CatBook!</p>
                <p>Nếu có thắc mắc về đơn hàng, vui lòng liên hệ hotline 0123.456.789</p>
            </div>

            <div class="no-print fixed bottom-6 right-6 flex gap-3">
                <button onclick="window.close()" class="bg-slate-800 text-white px-6 py-3 rounded-xl font-bold shadow-lg">Đóng</button>
                <button onclick="window.print()" class="bg-brown-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-brown-500/30 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    In Hóa Đơn
                </button>
            </div>
        </div>
        <script>
            window.onload = function() {
                setTimeout(function() { window.print(); }, 500);
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        if ($status === 'completed') {
            $stmtOwner = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmtOwner->execute([$order_id]);
            $ownerId = (int)$stmtOwner->fetchColumn();
            if ($ownerId > 0) {
                reco_refresh_user($ownerId, true);
            }
        }
        $_SESSION['success_toast'] = "Cập nhật trạng thái thành công!";
    } catch (\Exception $e) {
        $_SESSION['error_toast'] = "Lỗi cập nhật!";
    }
    header("Location: orders.php" . ($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : ''));
    exit;
}

require_once 'header.php';

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = per_page(10);
$offset = ($page - 1) * $limit;

try {
    $params = [];
    $whereClause = "WHERE 1=1";

    if ($search !== '') {
        $whereClause .= " AND (o.id LIKE ? OR u.username LIKE ? OR o.shipping_phone LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if ($status_filter !== '') {
        $whereClause .= " AND o.status = ?";
        $params[] = $status_filter;
    }

    $countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id $whereClause";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalOrders = $stmtCount->fetchColumn();
    $totalPages = ceil($totalOrders / $limit);

    $sql = "
        SELECT o.*, u.username as customer_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        $whereClause 
        ORDER BY o.id DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orderItems = [];
    if ($orders) {
        $orderIds = array_map('intval', array_column($orders, 'id'));
        $in = implode(',', $orderIds);
        $itemRows = $pdo->query("SELECT od.order_id, od.quantity, od.price, p.name, p.image
            FROM order_details od LEFT JOIN products p ON p.id = od.product_id
            WHERE od.order_id IN ($in) ORDER BY od.id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($itemRows as $row) {
            $orderItems[(int)$row['order_id']][] = $row;
        }
    }
} catch (\PDOException $e) {
    $orders = [];
    $totalPages = 1;
}

$statusMap = [
    'pending' => ['label' => 'Chờ xác nhận', 'color' => 'orange', 'bg' => 'bg-orange-100', 'text' => 'text-orange-700'],
    'processing' => ['label' => 'Đang xử lý', 'color' => 'blue', 'bg' => 'bg-blue-100', 'text' => 'text-blue-700'],
    'shipping' => ['label' => 'Đang giao hàng', 'color' => 'purple', 'bg' => 'bg-purple-100', 'text' => 'text-purple-700'],
    'completed' => ['label' => 'Đã giao', 'color' => 'emerald', 'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700'],
    'cancelled' => ['label' => 'Đã hủy', 'color' => 'red', 'bg' => 'bg-red-100', 'text' => 'text-red-700']
];

function buildUrl($newPage) {
    $qs = $_GET;
    $qs['page'] = $newPage;
    return '?' . http_build_query($qs);
}

$swal_script = '';
if (isset($_SESSION['success_toast'])) {
    $swal_script = "showToast('success', '" . $_SESSION['success_toast'] . "');";
    unset($_SESSION['success_toast']);
}
if (isset($_SESSION['error_toast'])) {
    $swal_script = "showToast('error', '" . $_SESSION['error_toast'] . "');";
    unset($_SESSION['error_toast']);
}
?>

<style>
    .custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
    .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .custom-scroll:hover::-webkit-scrollbar-thumb { background: #94a3b8; }
</style>

<div class="p-4 sm:p-6 lg:p-8 w-full max-w-7xl mx-auto min-h-screen">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight">Quản Lý Đơn Hàng</h1>
            <p class="text-slate-500 font-medium mt-1">Hệ thống quản lý trạng thái và in hóa đơn</p>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="p-5 sm:p-6 border-b border-slate-100 bg-slate-50/50">
            <form method="GET" action="orders.php" class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm mã đơn, tên KH, SĐT..." class="w-full bg-white border border-slate-300 rounded-xl py-2.5 pl-11 pr-4 text-sm font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
                    </div>
                </div>
                <div class="w-full lg:w-64">
                    <select name="status" class="w-full bg-white border border-slate-300 rounded-xl py-2.5 px-4 text-sm font-bold text-slate-700 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm appearance-none cursor-pointer">
                        <option value="">Tất cả trạng thái</option>
                        <?php foreach($statusMap as $key => $val): ?>
                            <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>><?= $val['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="w-full lg:w-auto bg-slate-800 hover:bg-slate-900 text-white px-8 py-2.5 rounded-xl font-bold text-sm shadow-md transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-filter"></i> Lọc
                </button>
                <?php if($search !== '' || $status_filter !== ''): ?>
                    <a href="orders.php" class="w-full lg:w-auto bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 px-6 py-2.5 rounded-xl font-bold text-sm shadow-sm transition-colors flex items-center justify-center">
                        Xóa lọc
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="w-full overflow-x-auto custom-scroll">
            <?= per_page_selector($limit, $page, (int)$totalOrders) ?>
            <table class="w-full text-left border-collapse min-w-[1100px]">
                <thead>
                    <tr class="bg-white border-b border-slate-200">
                        <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Mã ĐH</th>
                        <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Khách Hàng</th>
                        <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Sách Đã Mua</th>
                        <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Ngày Đặt</th>
                        <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Tổng Tiền</th>
                        <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Trạng Thái</th>
                        <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-widest text-right whitespace-nowrap">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 bg-white">
                    <?php if(empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 font-medium">Không tìm thấy đơn hàng nào!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($orders as $order): ?>
                            <?php $sInfo = $statusMap[$order['status']] ?? $statusMap['pending']; ?>
                            <tr class="hover:bg-slate-50/80 transition-colors group">
                                <td class="py-4 px-6">
                                    <span class="font-black text-slate-800 text-base"><?= $order['id'] ?></span>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-800 truncate max-w-[200px]"><?= htmlspecialchars($order['customer_name'] ?? 'Khách lẻ') ?></span>
                                        <span class="text-xs font-medium text-slate-500"><?= htmlspecialchars($order['shipping_phone']) ?></span>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <?php $items = $orderItems[(int)$order['id']] ?? []; $totalQty = array_sum(array_column($items, 'quantity')); ?>
                                    <div class="flex flex-col gap-2 min-w-[240px] max-w-[320px]">
                                        <?php foreach (array_slice($items, 0, 3) as $it): ?>
                                            <div class="flex items-center gap-3">
                                                <img src="<?= htmlspecialchars(product_image_url($it['image'] ?? null, '../uploads/products/'), ENT_QUOTES, 'UTF-8') ?>" alt="" class="w-10 h-14 object-cover rounded border border-slate-200 shrink-0 bg-slate-50">
                                                <div class="min-w-0">
                                                    <div class="text-sm font-bold text-slate-800 line-clamp-2 leading-snug"><?= htmlspecialchars($it['name'] ?? 'Sách đã xóa') ?></div>
                                                    <div class="text-xs font-bold text-brown-600">Số lượng: <?= (int)$it['quantity'] ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($items) > 3): ?>
                                            <button type="button" onclick="openOrderModal(<?= (int)$order['id'] ?>)" class="text-xs font-bold text-blue-600 hover:underline text-left">+ <?= count($items) - 3 ?> sách khác (xem tất cả)</button>
                                        <?php endif; ?>
                                        <?php if ($items): ?>
                                            <div class="text-xs text-slate-500"><?= count($items) ?> loại · tổng <?= (int)$totalQty ?> cuốn</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="text-sm font-bold text-slate-600"><?= date('d/m/Y', strtotime($order['created_at'])) ?></span>
                                    <span class="text-xs text-slate-400 block"><?= date('H:i', strtotime($order['created_at'])) ?></span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="font-black text-red-600 text-base"><?= number_format($order['total_amount'], 0, ',', '.') ?>đ</span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?= $sInfo['bg'] ?> <?= $sInfo['text'] ?>">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current mr-1.5"></span>
                                        <?= $sInfo['label'] ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <div class="flex items-center justify-end gap-2 opacity-100 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity">
                                        <a href="orders.php?print=1&id=<?= $order['id'] ?>" target="_blank" class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 hover:bg-brown-100 hover:text-brown-600 flex items-center justify-center transition-colors shadow-sm" title="In Hóa Đơn">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <button type="button" onclick="openOrderModal(<?= $order['id'] ?>)" class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 hover:bg-blue-100 hover:text-blue-600 flex items-center justify-center transition-colors shadow-sm" title="Xem & Cập nhật">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="p-5 sm:p-6 border-t border-slate-100 bg-slate-50/50 flex justify-center sm:justify-end">
            <div class="flex items-center gap-1 sm:gap-2">
                <?php if ($page > 1): ?>
                    <a href="<?= buildUrl($page - 1) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-brown-600 transition-colors font-bold text-sm shadow-sm"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                        <a href="<?= buildUrl($i) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl font-bold text-sm transition-all shadow-sm <?= $page === $i ? 'bg-brown-600 text-white border border-brown-600 shadow-brown-500/30' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-brown-600' ?>">
                            <?= $i ?>
                        </a>
                    <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                        <span class="w-9 h-9 flex items-center justify-center text-slate-400 font-black tracking-widest">...</span>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= buildUrl($page + 1) ?>" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-brown-600 transition-colors font-bold text-sm shadow-sm"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="orderModal" class="fixed inset-0 z-[100] flex items-center justify-center px-4 py-6 opacity-0 pointer-events-none transition-opacity duration-300 hidden">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm modal-bg"></div>
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-full flex flex-col relative z-10 scale-95 transition-transform duration-300 modal-content overflow-hidden">
        
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/80 shrink-0">
            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-file-invoice text-brown-600"></i> Chi Tiết Đơn Hàng <span id="modalOrderIdLabel" class="text-brown-600"></span>
            </h2>
            <button id="closeModalBtn" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 text-slate-500 hover:bg-red-100 hover:text-red-500 transition-colors focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-6 overflow-y-auto custom-scroll flex-1 bg-white">
            <div id="modalLoading" class="flex flex-col justify-center items-center py-12 hidden">
                <i class="fas fa-circle-notch fa-spin text-4xl text-brown-500 mb-4"></i>
                <span class="text-slate-500 font-medium">Đang tải dữ liệu...</span>
            </div>
            
            <div id="modalContent" class="hidden flex flex-col lg:flex-row gap-6">
                
                <div class="flex-1 space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
                            <h4 class="font-bold text-slate-400 mb-2 text-xs uppercase tracking-widest"><i class="fas fa-user-circle mr-1"></i> Khách Hàng</h4>
                            <p id="mName" class="font-bold text-slate-800 text-lg mb-1"></p>
                            <p class="text-sm font-medium text-slate-600"><i class="fas fa-phone-alt w-4"></i> <span id="mPhone"></span></p>
                        </div>
                        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
                            <h4 class="font-bold text-slate-400 mb-2 text-xs uppercase tracking-widest"><i class="fas fa-truck mr-1"></i> Giao Hàng</h4>
                            <p class="text-sm font-medium text-slate-600 mb-1"><i class="fas fa-map-marker-alt w-4"></i> <span id="mAddress"></span></p>
                            <p class="text-sm font-medium text-slate-600"><i class="far fa-calendar-alt w-4"></i> <span id="mDate"></span></p>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-black text-slate-800 mb-4 pb-2 border-b border-slate-100">Danh Sách Sản Phẩm</h4>
                        <div id="modalProductsList" class="space-y-3 max-h-[300px] overflow-y-auto custom-scroll pr-2"></div>
                    </div>
                </div>

                <div class="w-full lg:w-72 shrink-0 flex flex-col gap-6">
                    <div class="bg-slate-800 rounded-2xl p-6 text-white shadow-lg">
                        <h4 class="text-slate-400 text-xs font-black uppercase tracking-widest mb-2">Tổng Thanh Toán</h4>
                        <p id="mTotal" class="text-3xl font-black text-white mb-4"></p>
                        <a id="btnPrint" href="#" target="_blank" class="w-full bg-white/10 hover:bg-white/20 border border-white/20 text-white py-3 rounded-xl font-bold flex items-center justify-center gap-2 transition-colors">
                            <i class="fas fa-print"></i> In Hóa Đơn
                        </a>
                    </div>

                    <form method="POST" action="orders.php<?= $_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : '' ?>" class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" id="formOrderId">
                        
                        <h4 class="font-black text-slate-800 mb-4 pb-2 border-b border-slate-100">Cập Nhật Trạng Thái</h4>
                        <div class="space-y-4">
                            <select name="status" id="formStatus" class="w-full bg-slate-50 border border-slate-300 rounded-xl py-3 px-4 text-sm font-bold text-slate-700 focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all cursor-pointer">
                                <?php foreach($statusMap as $key => $val): ?>
                                    <option value="<?= $key ?>"><?= $val['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full bg-brown-600 hover:bg-brown-700 text-white py-3 rounded-xl font-bold transition-colors shadow-md shadow-brown-500/30">
                                Lưu Thay Đổi
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
        Toast.fire({ icon: icon, title: title });
    }

    <?php if ($swal_script !== ''): ?>
    document.addEventListener('DOMContentLoaded', function() {
        <?= $swal_script ?>
    });
    <?php endif; ?>

    const modal = document.getElementById('orderModal');
    const modalBg = document.querySelector('.modal-bg');
    const modalContentContainer = document.querySelector('.modal-content');
    const closeBtn = document.getElementById('closeModalBtn');

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function openOrderModal(orderId) {
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modalContentContainer.classList.remove('scale-95');
            document.body.classList.add('overflow-hidden');
        }, 10);

        document.getElementById('modalLoading').classList.remove('hidden');
        document.getElementById('modalContent').classList.add('hidden');

        fetch(`orders.php?ajax=1&id=${orderId}`)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const order = data.order;
                    const details = data.details;

                    document.getElementById('modalOrderIdLabel').textContent = order.id;
                    document.getElementById('mName').textContent = order.customer_name || 'Khách lẻ';
                    document.getElementById('mPhone').textContent = order.shipping_phone;
                    document.getElementById('mAddress').textContent = order.shipping_address;
                    
                    const dateObj = new Date(order.created_at);
                    document.getElementById('mDate').textContent = dateObj.toLocaleDateString('vi-VN') + ' ' + dateObj.toLocaleTimeString('vi-VN', {hour: '2-digit', minute:'2-digit'});
                    
                    document.getElementById('mTotal').textContent = new Intl.NumberFormat('vi-VN').format(order.total_amount) + 'đ';
                    document.getElementById('btnPrint').href = `orders.php?print=1&id=${order.id}`;
                    
                    document.getElementById('formOrderId').value = order.id;
                    document.getElementById('formStatus').value = order.status;

                    const listContainer = document.getElementById('modalProductsList');
                    listContainer.innerHTML = '';
                    details.forEach(item => {
                        listContainer.innerHTML += `
                            <div class="flex items-center gap-4 py-3 bg-white rounded-2xl p-3 shadow-sm border border-slate-100 hover:border-brown-200 transition-colors">
                                <div class="w-16 h-16 shrink-0 rounded-xl bg-slate-50 border border-slate-100 overflow-hidden relative">
                                    <img src="${item.image}" class="w-full h-full object-cover">
                                    <span class="absolute top-0 right-0 bg-slate-800 text-white text-[10px] font-bold px-1.5 h-5 flex items-center justify-center rounded-bl-xl shadow-sm">x${item.quantity}</span>
                                </div>
                                <div class="flex flex-col flex-1 min-w-0">
                                    <div class="font-bold text-slate-800 text-sm line-clamp-2 mb-1">${escapeHtml(item.name)}</div>
                                    <div class="text-xs font-bold text-brown-600 mb-1">Số lượng: ${item.quantity}</div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs font-bold text-slate-400">${new Intl.NumberFormat('vi-VN').format(item.price)}đ</span>
                                        <span class="font-black text-brown-600 text-sm">${new Intl.NumberFormat('vi-VN').format(item.price * item.quantity)}đ</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    document.getElementById('modalLoading').classList.add('hidden');
                    document.getElementById('modalContent').classList.remove('hidden');
                } else {
                    closeModal();
                    showToast('error', 'Lỗi tải dữ liệu!');
                }
            })
            .catch(() => {
                closeModal();
                showToast('error', 'Lỗi kết nối!');
            });
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
</script>

<?php require_once 'footer.php'; ?>