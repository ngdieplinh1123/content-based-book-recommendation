<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user']['id'];

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

try {
    $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmtOrder->execute([$order_id, $user_id]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
        exit;
    }

    $stmtDetails = $pdo->prepare("
        SELECT od.*, p.name, p.image 
        FROM order_details od 
        JOIN products p ON od.product_id = p.id 
        WHERE od.order_id = ?
    ");
    $stmtDetails->execute([$order_id]);
    $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
    foreach ($details as &$detail) {
        $detail['image'] = product_image_url($detail['image'] ?? null);
    }
    unset($detail);

    echo json_encode([
        'success' => true,
        'order' => $order,
        'details' => $details,
        'user' => $_SESSION['user']
    ]);

} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL']);
}