<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    $token = (string)($_POST['csrf_token'] ?? '');
    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
    $comment = trim((string)($_POST['comment'] ?? ''));

    if ($userId <= 0) {
        header('Location: login.php?redirect=' . rawurlencode('product_detail.php?id=' . $id . '#reviews'));
        exit;
    }
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['review_notice'] = ['type' => 'error', 'message' => 'Phiên gửi đánh giá đã hết hạn. Vui lòng thử lại.'];
    } elseif ($id <= 0 || $rating === false || $rating < 1 || $rating > 5 || $comment === '' || mb_strlen($comment, 'UTF-8') > 2000) {
        $_SESSION['review_notice'] = ['type' => 'error', 'message' => 'Vui lòng chọn số sao và nhập bình luận tối đa 2.000 ký tự.'];
    } else {
        try {
            $eligibleOrder = $pdo->prepare(
                "SELECT o.id
                 FROM orders o
                 INNER JOIN order_details od ON od.order_id = o.id
                 WHERE o.user_id = ? AND o.status = 'completed' AND od.product_id = ?
                 ORDER BY o.id DESC
                 LIMIT 1"
            );
            $eligibleOrder->execute([$userId, $id]);
            $completedOrderId = $eligibleOrder->fetchColumn();

            if (!$completedOrderId) {
                $_SESSION['review_notice'] = ['type' => 'error', 'message' => 'Chỉ khách đã nhận thành công sách này mới có thể đánh giá.'];
            } else {
                $saveReview = $pdo->prepare(
                    'INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE order_id = VALUES(order_id), rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP'
                );
                $saveReview->execute([$id, $userId, (int)$completedOrderId, $rating, $comment]);
                $_SESSION['review_notice'] = ['type' => 'success', 'message' => 'Cảm ơn bạn đã chia sẻ đánh giá về cuốn sách!'];
            }
        } catch (PDOException $e) {
            error_log('Unable to save product review: ' . $e->getMessage());
            $_SESSION['review_notice'] = ['type' => 'error', 'message' => 'Chưa thể lưu đánh giá lúc này. Vui lòng thử lại sau.'];
        }
    }

    header('Location: product_detail.php?id=' . $id . '#reviews');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $qty = (int)$_POST['quantity'];
    if ($qty > 0 && $id > 0) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id] += $qty;
        } else {
            $_SESSION['cart'][$id] = $qty;
        }
        $_SESSION['success_toast'] = 'Đã thêm sản phẩm vào giỏ hàng!';
        reco_track_cart($pdo, (int)$id);
        header("Location: product_detail.php?id=" . $id);
        exit;
    }
}

require_once 'header.php';

if ($id <= 0) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$swal_script = '';
$reviewNotice = $_SESSION['review_notice'] ?? null;
unset($_SESSION['review_notice']);
if (isset($_SESSION['success_toast'])) {
    $swal_script = "showToast('success', " . json_encode($_SESSION['success_toast'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ");";
    unset($_SESSION['success_toast']);
}

try {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id AND c.status = 1 WHERE p.id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo "<script>window.location.href='index.php';</script>";
        exit;
    }

    $stock = isset($product['quantity']) ? (int)$product['quantity'] : 0; 
    
    $originalPrice = isset($product['price']) ? (float)$product['price'] : 0;
    $discountPrice = isset($product['discount_price']) ? (float)$product['discount_price'] : 0;
    
    $sellingPrice = $originalPrice;
    $oldPrice = 0;
    $hasDiscount = false;
    $discountPercent = 0;

    if ($discountPrice > 0 && $discountPrice < $originalPrice) {
        $sellingPrice = $discountPrice;
        $oldPrice = $originalPrice;
        $hasDiscount = true;
        $discountPercent = round((($oldPrice - $sellingPrice) / $oldPrice) * 100);
    }

    $cat_id = isset($product['category_id']) ? $product['category_id'] : 0;
    $stmtSim = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? ORDER BY id DESC LIMIT 6");
    $stmtSim->execute([$cat_id, $id]);
    $similarProducts = $stmtSim->fetchAll(PDO::FETCH_ASSOC);
    $similarProducts = product_attach_ratings($pdo, $similarProducts);
    reco_track_view($pdo, (int)$id);
    $recoForYou = reco_for_user($pdo, isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null, 8, [(int)$id]);

    $reviewSummaryStmt = $pdo->prepare(
        'SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS average_rating
         FROM product_reviews WHERE product_id = ?'
    );
    $reviewSummaryStmt->execute([$id]);
    $reviewSummary = $reviewSummaryStmt->fetch(PDO::FETCH_ASSOC);

    $reviewsStmt = $pdo->prepare(
        'SELECT r.rating, r.comment, r.created_at, r.updated_at, u.username
         FROM product_reviews r
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ?
         ORDER BY r.created_at DESC, r.id DESC'
    );
    $reviewsStmt->execute([$id]);
    $reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);

    $ratingCountsStmt = $pdo->prepare(
        'SELECT rating, COUNT(*) AS total FROM product_reviews WHERE product_id = ? GROUP BY rating'
    );
    $ratingCountsStmt->execute([$id]);
    $ratingCounts = array_fill(1, 5, 0);
    foreach ($ratingCountsStmt->fetchAll(PDO::FETCH_ASSOC) as $ratingCount) {
        $ratingCounts[(int)$ratingCount['rating']] = (int)$ratingCount['total'];
    }

    $currentUserId = (int)($_SESSION['user']['id'] ?? 0);
    $eligibleOrderId = null;
    $currentUserReview = null;
    if ($currentUserId > 0) {
        $eligibilityStmt = $pdo->prepare(
            "SELECT o.id FROM orders o
             INNER JOIN order_details od ON od.order_id = o.id
             WHERE o.user_id = ? AND o.status = 'completed' AND od.product_id = ?
             ORDER BY o.id DESC LIMIT 1"
        );
        $eligibilityStmt->execute([$currentUserId, $id]);
        $eligibleOrderId = $eligibilityStmt->fetchColumn() ?: null;

        $ownReviewStmt = $pdo->prepare('SELECT rating, comment FROM product_reviews WHERE product_id = ? AND user_id = ?');
        $ownReviewStmt->execute([$id, $currentUserId]);
        $currentUserReview = $ownReviewStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $soldStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(od.quantity), 0)
         FROM order_details od
         INNER JOIN orders o ON o.id = od.order_id
         WHERE od.product_id = ? AND o.status = 'completed'"
    );
    $soldStmt->execute([$id]);
    $soldCount = (int)$soldStmt->fetchColumn();

} catch (\PDOException $e) {
    error_log('Unable to load product details: ' . $e->getMessage());
    http_response_code(500);
    exit('Không thể tải thông tin sản phẩm lúc này. Vui lòng thử lại sau.');
}
?>

<style>
    input[type=number]::-webkit-inner-spin-button, 
    input[type=number]::-webkit-outer-spin-button { 
        -webkit-appearance: none; 
        margin: 0; 
    }
    input[type=number] {
        -moz-appearance: textfield;
    }
    .product-detail-page { background: #f7f3ed; }
    .product-detail-card, .review-panel { border: 1px solid #e9e2d8; box-shadow: 0 1px 4px rgba(50, 40, 28, .05); }
    .product-content img { max-width: 100%; height: auto; }
    .product-content p { margin-bottom: 1rem; color: #475569; line-height: 1.6; }
    .review-star { color: #d5d0c7; }
    .review-star.is-active { color: #e87500; }
    .review-rating-input:checked + label { color: #e87500; }
    .review-rating-group label:hover,
    .review-rating-group label:hover ~ label { color: #e87500; }
    .review-progress::-webkit-progress-bar { background: #eee9e1; }
    .review-progress::-webkit-progress-value { background: #e87500; }
</style>

<div class="product-detail-page min-h-screen pb-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-slate-500 py-3 mb-6 font-medium">
            <a href="index.php" class="hover:text-brown-600 transition-colors">Trang chủ</a>
            <span>/</span>
            <a href="products.php" class="hover:text-brown-600 transition-colors">Danh mục</a>
            <?php if (!empty($product['category_name'])): ?><span>/</span><a href="products.php?category=<?= (int)$product['category_id'] ?>" class="hover:text-brown-600"><?= htmlspecialchars($product['category_name']) ?></a><?php endif; ?>
            <span>/</span>
            <span class="text-slate-700 truncate"><?= htmlspecialchars($product['name'] ?? '') ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-8">
            <div class="lg:col-span-5 product-detail-card bg-white p-5 sm:p-8">
                
                <div class="relative w-full aspect-[4/5] max-h-[680px] bg-white overflow-hidden">
                    <?php if ($hasDiscount): ?>
                        <span class="absolute top-3 left-3 z-10 bg-[#e87500] text-white text-sm font-semibold px-3 py-1">-<?= $discountPercent ?>%</span>
                    <?php endif; ?>
                    <img src="<?= htmlspecialchars(product_image_url($product['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($product['name'] ?? '') ?>" class="w-full h-full object-contain">
                </div>
                <?php $coverSource = product_image_source_url($product['image'] ?? null); ?>
                <?php if ($coverSource !== null): ?>
                    <a href="<?= htmlspecialchars($coverSource, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block text-xs text-slate-500 hover:text-brown-600">
                        Nguồn bìa: Open Library
                    </a>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-4 product-detail-card bg-white p-5 sm:p-7 flex flex-col">
                    <?php if (!empty($product['publisher'])): ?><p class="text-xs text-slate-500 mb-2"><?= htmlspecialchars($product['publisher']) ?></p><?php endif; ?>
                    <h1 class="text-2xl sm:text-3xl font-semibold text-slate-800 leading-tight mb-3">
                        <?= htmlspecialchars($product['name'] ?? '') ?>
                    </h1>
                    <?php if (!empty($product['author'])): ?>
                        <p class="text-sm text-slate-600 mb-3">Tác giả: <span class="font-semibold"><?= htmlspecialchars($product['author']) ?></span></p>
                    <?php endif; ?>
                    <a href="#reviews" class="flex items-center gap-2 text-sm text-slate-600 mb-5">
                        <span class="text-[#e87500]"><?= number_format((float)$reviewSummary['average_rating'], 1) ?></span>
                        <span class="flex text-[#e87500]" aria-label="Đánh giá trung bình <?= number_format((float)$reviewSummary['average_rating'], 1) ?> trên 5">
                            <?php for ($star = 1; $star <= 5; $star++): ?><i class="<?= $star <= (int)round((float)$reviewSummary['average_rating']) ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?>
                        </span>
                        <span>(<?= (int)$reviewSummary['review_count'] ?> đánh giá)</span>
                    </a>
                    <?php if (!empty($product['author']) || !empty($product['publisher']) || !empty($product['publication_year']) || !empty($product['category_name'])): ?>
                        <div class="text-sm text-slate-600 space-y-2 border-t border-slate-100 py-4 mb-2">
                            <?php if (!empty($product['category_name'])): ?><p><span class="text-slate-500">Danh mục:</span> <a class="text-[#e87500] hover:underline" href="products.php?category=<?= (int)$product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a></p><?php endif; ?>
                            <?php if (!empty($product['publication_year'])): ?><p><span class="text-slate-500">Năm xuất bản:</span> <?= (int)$product['publication_year'] ?></p><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex items-end gap-3 mb-5">
                        <span class="text-3xl font-semibold text-[#bd3b49]"><?= number_format($sellingPrice, 0, ',', '.') ?>đ</span>
                        <?php if ($hasDiscount): ?>
                            <span class="text-base text-slate-400 line-through font-medium pb-1"><?= number_format($oldPrice, 0, ',', '.') ?>đ</span>
                        <?php endif; ?>
                    </div>

                    <div class="text-sm text-slate-600 mb-5">
                        Tình trạng:
                        <?php if ($stock > 0): ?><span class="text-emerald-700 font-semibold">Còn hàng (<?= $stock ?>)</span>
                        <?php else: ?><span class="text-red-600 font-semibold">Hết hàng</span><?php endif; ?>
                        <span class="block mt-2 text-slate-500">Đã giao thành công: <?= $soldCount ?> cuốn</span>
                    </div>

                    <form method="POST" action="product_detail.php?id=<?= $id ?>" class="mt-auto pt-5 border-t border-slate-100">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="flex items-stretch gap-3 w-full">
                            <div class="flex items-center justify-between border border-slate-300 bg-white w-32 h-12 shrink-0 overflow-hidden">
                                <button type="button" onclick="decreaseQty()" class="w-10 h-full flex items-center justify-center text-slate-500 hover:bg-slate-50 hover:text-brown-600 transition-colors">
                                    <i class="fas fa-minus text-sm"></i>
                                </button>
                                <input type="number" name="quantity" id="qtyInput" value="1" min="1" max="<?= max(1, $stock) ?>" class="w-full h-full text-center font-semibold text-lg text-slate-800 bg-transparent border-none focus:outline-none" readonly>
                                <button type="button" onclick="increaseQty()" class="w-10 h-full flex items-center justify-center text-slate-500 hover:bg-slate-50 hover:text-brown-600 transition-colors">
                                    <i class="fas fa-plus text-sm"></i>
                                </button>
                            </div>
                            <button type="submit" <?= $stock <= 0 ? 'disabled' : '' ?> class="flex-1 bg-[#e87500] hover:bg-[#c65f00] disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold text-sm sm:text-base h-12 transition-colors flex items-center justify-center gap-2">
                                <i class="fas fa-cart-plus"></i> THÊM VÀO GIỎ
                            </button>
                        </div>
                    </form>

            </div>

            <aside class="lg:col-span-3 product-detail-card bg-white h-fit">
                <h2 class="bg-[#f2efeb] px-5 py-4 font-semibold text-slate-800">Mua sắm tại <?= $siteName ?></h2>
                <div class="p-5 space-y-5 text-sm text-slate-600">
                    <p class="flex gap-3"><i class="fas fa-book-open text-[#e87500] text-lg"></i><span>Thông tin sách được hiển thị rõ ràng</span></p>
                    <p class="flex gap-3"><i class="fas fa-headset text-[#e87500] text-lg"></i><span>Hỗ trợ khách hàng trong giờ hành chính</span></p>
                    <p class="flex gap-3"><i class="fas fa-truck text-[#e87500] text-lg"></i><span>Giao hàng toàn quốc</span></p>
                    <p class="flex gap-3"><i class="fas fa-phone text-[#e87500] text-lg"></i><span>Hotline: <?= $sitePhone ?></span></p>
                </div>
            </aside>
        </div>

        <section id="introduction" class="product-detail-card bg-white mb-5">
            <nav class="flex border-b border-slate-200" aria-label="Thông tin sản phẩm">
                <a href="#introduction" class="px-5 py-4 text-sm font-semibold border-b-2 border-[#e87500] text-slate-800">GIỚI THIỆU SÁCH</a>
                <a href="#reviews" class="px-5 py-4 text-sm font-semibold text-slate-500 hover:text-[#e87500]">ĐÁNH GIÁ (<?= (int)$reviewSummary['review_count'] ?>)</a>
            </nav>
            <div class="p-5 sm:p-8">
            <h2 class="text-xl font-semibold text-slate-800 mb-5">Giới thiệu sách</h2>
            <div class="product-content max-w-none text-slate-700">
                <?php if (!empty($product['description'])): ?>
                    <?= $product['description'] ?>
                <?php else: ?>
                    <p class="italic text-slate-400">Chưa có bài viết mô tả cho sản phẩm này.</p>
                <?php endif; ?>
            </div>
            </div>
        </section>

        <section id="reviews" class="review-panel bg-white p-5 sm:p-8 mb-10 scroll-mt-28">
            <h2 class="text-xl sm:text-2xl font-semibold text-slate-800 mb-6">Đánh giá và bình luận</h2>
            <?php if (is_array($reviewNotice)): ?>
                <div role="status" class="mb-5 border px-4 py-3 text-sm <?= $reviewNotice['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-700' ?>">
                    <?= htmlspecialchars($reviewNotice['message']) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-8 border-b border-slate-100 pb-7 mb-7">
                <div class="text-center md:border-r md:border-slate-100 md:pr-6">
                    <div class="text-5xl font-semibold text-slate-800"><?= number_format((float)$reviewSummary['average_rating'], 1) ?><span class="text-2xl text-slate-400">/5</span></div>
                    <div class="flex justify-center gap-1 text-[#e87500] my-2" aria-hidden="true">
                        <?php for ($star = 1; $star <= 5; $star++): ?><i class="<?= $star <= (int)round((float)$reviewSummary['average_rating']) ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?>
                    </div>
                    <p class="text-sm text-slate-500"><?= (int)$reviewSummary['review_count'] ?> đánh giá từ khách đã mua</p>
                </div>
                <div class="space-y-2">
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <?php $count = $ratingCounts[$star]; $percentage = (int)$reviewSummary['review_count'] > 0 ? (int)round($count * 100 / (int)$reviewSummary['review_count']) : 0; ?>
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-10 text-slate-600"><?= $star ?> sao</span>
                            <progress class="review-progress h-2 flex-1 overflow-hidden border-0" max="100" value="<?= $percentage ?>" aria-label="<?= $star ?> sao: <?= $count ?> đánh giá"></progress>
                            <span class="w-8 text-right text-slate-500"><?= $count ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(280px,380px)] gap-8">
                <div class="space-y-5">
                    <?php if (empty($reviews)): ?>
                        <p class="text-slate-500">Chưa có đánh giá nào. Khách hàng đã nhận sách có thể chia sẻ cảm nhận đầu tiên.</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <article class="border-b border-slate-100 pb-5 last:border-0">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-3">
                                        <span class="w-9 h-9 bg-[#f7f3ed] flex items-center justify-center font-semibold text-slate-700"><?= htmlspecialchars(mb_strtoupper(mb_substr($review['username'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($review['username']) ?></span>
                                    </div>
                                    <time class="text-xs text-slate-500" datetime="<?= htmlspecialchars($review['created_at']) ?>"><?= date('d/m/Y', strtotime($review['created_at'])) ?></time>
                                </div>
                                <div class="text-[#e87500] text-sm mb-2" aria-label="<?= (int)$review['rating'] ?> trên 5 sao">
                                    <?php for ($star = 1; $star <= 5; $star++): ?><i class="<?= $star <= (int)$review['rating'] ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?>
                                    <span class="ml-2 text-slate-500"><?= (int)$review['rating'] ?>/5</span>
                                </div>
                                <p class="text-sm text-slate-700 whitespace-pre-line"><?= htmlspecialchars($review['comment']) ?></p>
                                <p class="mt-2 text-xs text-emerald-700"><i class="fas fa-check-circle mr-1"></i>Đã mua và nhận sách</p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="bg-[#faf9f7] p-5 h-fit">
                    <h3 class="font-semibold text-slate-800 mb-2"><?= $currentUserReview ? 'Cập nhật đánh giá của bạn' : 'Viết đánh giá' ?></h3>
                    <?php if ($currentUserId <= 0): ?>
                        <p class="text-sm text-slate-600 mb-4">Đăng nhập và mua thành công cuốn sách này để gửi đánh giá.</p>
                        <a href="login.php?redirect=<?= rawurlencode('product_detail.php?id=' . $id . '#reviews') ?>" class="inline-flex bg-[#e87500] hover:bg-[#c65f00] text-white px-5 py-2.5 text-sm font-semibold">Đăng nhập</a>
                    <?php elseif (!$eligibleOrderId): ?>
                        <p class="text-sm text-slate-600">Chỉ tài khoản đã có đơn hàng ở trạng thái <strong>Đã giao</strong> cho cuốn sách này mới được đánh giá và bình luận.</p>
                    <?php else: ?>
                        <p class="text-xs text-emerald-700 mb-4"><i class="fas fa-check-circle mr-1"></i>Đã xác nhận bạn mua thành công sách này.</p>
                        <form method="POST" action="product_detail.php?id=<?= $id ?>#reviews" class="space-y-4">
                            <input type="hidden" name="action" value="submit_review">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <fieldset>
                                <legend class="block text-sm font-medium text-slate-700 mb-2">Bạn chấm cuốn sách này mấy sao?</legend>
                                <div class="review-rating-group flex flex-row-reverse justify-end gap-1">
                                    <?php for ($star = 5; $star >= 1; $star--): ?>
                                        <input class="review-rating-input sr-only" type="radio" id="rating-<?= $star ?>" name="rating" value="<?= $star ?>" <?= (int)($currentUserReview['rating'] ?? 0) === $star ? 'checked' : '' ?> required>
                                        <label for="rating-<?= $star ?>" class="review-star cursor-pointer text-2xl hover:text-[#e87500]" aria-label="<?= $star ?> sao"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </fieldset>
                            <div>
                                <label for="reviewComment" class="block text-sm font-medium text-slate-700 mb-2">Bình luận</label>
                                <textarea id="reviewComment" name="comment" rows="5" maxlength="2000" required class="w-full border border-slate-300 bg-white p-3 text-sm text-slate-800 focus:outline-none focus:border-[#e87500]" placeholder="Chia sẻ cảm nhận của bạn về cuốn sách..."><?= htmlspecialchars($currentUserReview['comment'] ?? '') ?></textarea>
                                <p class="mt-1 text-xs text-slate-500">Tối đa 2.000 ký tự</p>
                            </div>
                            <button type="submit" class="w-full bg-[#e87500] hover:bg-[#c65f00] text-white px-5 py-3 text-sm font-semibold">
                                <?= $currentUserReview ? 'Cập nhật đánh giá' : 'Gửi đánh giá' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>

<?php if (!empty($similarProducts)): ?>
<div>
    <div class="mb-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-1">Sản Phẩm Tương Tự</h2>
        <div class="h-[2px] w-full bg-brown-600"></div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-3 bg-white p-3 sm:p-4">
        <?php foreach ($similarProducts as $prod):
            $sOriginalPrice = (float)($prod['price'] ?? 0);
            $sDiscountPrice = (float)($prod['discount_price'] ?? 0);
            $sHasDiscount = ($sDiscountPrice > 0 && $sDiscountPrice < $sOriginalPrice);
            $sSellingPrice = $sHasDiscount ? $sDiscountPrice : $sOriginalPrice;
            $sOldPrice = $sHasDiscount ? $sOriginalPrice : 0;
            $sDiscountPercent = $sHasDiscount ? round((($sOldPrice - $sSellingPrice) / $sOldPrice) * 100) : 0;
        ?>
            <a href="product_detail.php?id=<?= (int)$prod['id'] ?>" class="bg-white p-2.5 transition-all duration-300 border border-transparent hover:border-slate-100 group block relative flex flex-col h-full">
                <div class="h-[200px] sm:h-[235px] overflow-hidden relative mb-3 shrink-0 bg-white">
                    <?php if ($sHasDiscount): ?>
                        <span class="absolute top-1.5 left-1.5 z-10 bg-[#e87500] text-white text-[11px] font-semibold px-2 py-0.5">-<?= $sDiscountPercent ?>%</span>
                    <?php endif; ?>
                    <img src="<?= htmlspecialchars(product_image_url($prod['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($prod['name'] ?? '') ?>" class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-[1.035]">
                </div>

                <h3 class="text-sm font-medium text-slate-700 line-clamp-2 mb-1.5 group-hover:text-brown-600 transition-colors leading-snug flex-1">
                    <?= htmlspecialchars($prod['name'] ?? '') ?>
                </h3>
                <?php if (!empty($prod['author'])): ?>
                    <p class="text-xs text-slate-500 line-clamp-1 mb-1.5"><?= htmlspecialchars($prod['author']) ?></p>
                <?php endif; ?>
                <?= product_stars_html($prod) ?>

                <div class="flex flex-wrap items-end gap-x-2 gap-y-0.5 shrink-0">
                    <span class="text-base font-semibold text-[#bd7783] leading-none"><?= number_format($sSellingPrice, 0, ',', '.') ?>đ</span>
                    <?php if ($sHasDiscount): ?>
                        <span class="text-xs text-slate-400 line-through font-normal leading-none"><?= number_format($sOldPrice, 0, ',', '.') ?>đ</span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
           
        <?php if (!empty($recoForYou['products'])):
    $recoProducts = product_attach_ratings($pdo, array_slice($recoForYou['products'], 0, 6));
?>
<div class="mt-10">
    <div class="mb-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-1"><?= $recoForYou['mode'] === 'personalized' ? 'Gợi Ý Dành Cho Bạn' : 'Sách Được Yêu Thích' ?></h2>
        <p class="text-xs text-slate-500 mb-2"><?= $recoForYou['mode'] === 'personalized' ? 'Được tạo từ lịch sử xem, đơn hàng hoàn tất và đánh giá của bạn' : 'Sách bán chạy và được đánh giá tốt trong cửa hàng' ?></p>
        <div class="h-[2px] w-full bg-brown-600"></div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-3 bg-white p-3 sm:p-4">
        <?php foreach ($recoProducts as $rp):
            $rPrice = (float)($rp['price'] ?? 0);
            $rDisc = (float)($rp['discount_price'] ?? 0);
            $rHasDiscount = ($rDisc > 0 && $rDisc < $rPrice);
            $rSell = $rHasDiscount ? $rDisc : $rPrice;
            $rPercent = $rHasDiscount ? round((($rPrice - $rSell) / $rPrice) * 100) : 0;
        ?>
            <a href="product_detail.php?id=<?= (int)$rp['id'] ?>" class="bg-white p-2.5 transition-all duration-300 border border-transparent hover:border-slate-100 group block relative flex flex-col h-full">
                <div class="h-[200px] sm:h-[235px] overflow-hidden relative mb-3 shrink-0 bg-white">
                    <?php if ($rHasDiscount): ?>
                        <span class="absolute top-1.5 left-1.5 z-10 bg-[#e87500] text-white text-[11px] font-semibold px-2 py-0.5">-<?= $rPercent ?>%</span>
                    <?php endif; ?>
                    <img src="<?= htmlspecialchars(product_image_url($rp['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($rp['name'] ?? '') ?>" class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-[1.035]">
                </div>

                <h3 class="text-sm font-medium text-slate-700 line-clamp-2 mb-1.5 group-hover:text-brown-600 transition-colors leading-snug flex-1">
                    <?= htmlspecialchars($rp['name'] ?? '') ?>
                </h3>
                <?php if (!empty($rp['author'])): ?>
                    <p class="text-xs text-slate-500 line-clamp-1 mb-1.5"><?= htmlspecialchars($rp['author']) ?></p>
                <?php endif; ?>
                <?= product_stars_html($rp) ?>

                <div class="flex flex-wrap items-end gap-x-2 gap-y-0.5 shrink-0">
                    <span class="text-base font-semibold text-[#bd7783] leading-none"><?= number_format($rSell, 0, ',', '.') ?>đ</span>
                    <?php if ($rHasDiscount): ?>
                        <span class="text-xs text-slate-400 line-through font-normal leading-none"><?= number_format($rPrice, 0, ',', '.') ?>đ</span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const maxStock = <?= $stock ?>;
    const qtyInput = document.getElementById('qtyInput');

    function increaseQty() {
        let currentVal = parseInt(qtyInput.value);
        if (currentVal < maxStock) {
            qtyInput.value = currentVal + 1;
        } else {
            showToast('warning', 'Đã đạt số lượng tồn kho tối đa!');
        }
    }

    function decreaseQty() {
        let currentVal = parseInt(qtyInput.value);
        if (currentVal > 1) {
            qtyInput.value = currentVal - 1;
        }
    }

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