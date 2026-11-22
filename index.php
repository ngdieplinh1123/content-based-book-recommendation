<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'header.php';

$categories = [];
$banners = [];
$products = [];
$news = [];

try {
    $stmtCat = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY id DESC");
    $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {}

try {
    $stmtBan = $pdo->query("SELECT * FROM banners ORDER BY id DESC");
    $banners = $stmtBan->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {}

try {
    $products = product_attach_ratings($pdo, reco_most_viewed($pdo, 8));
} catch (\PDOException $e) {}

try {
    $stmtNews = $pdo->query("SELECT * FROM news ORDER BY id DESC LIMIT 3");
    $news = $stmtNews->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {}
try {
    $stmtBest = $pdo->query("SELECT p.*, SUM(oi.quantity) AS sold FROM order_details oi
        INNER JOIN orders o ON o.id = oi.order_id AND o.status <> 'cancelled'
        INNER JOIN products p ON p.id = oi.product_id
        INNER JOIN categories c ON c.id = p.category_id AND c.status = 1
        GROUP BY p.id ORDER BY sold DESC, p.id DESC LIMIT 3");
    $bestsellers = $stmtBest->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $bestsellers = [];
}
if (count($bestsellers) < 3) {
    $have = array_map('intval', array_column($bestsellers, 'id')) ?: [0];
    try {
        $stmtFill = $pdo->query("SELECT p.* FROM products p INNER JOIN categories c ON c.id = p.category_id AND c.status = 1
            WHERE p.id NOT IN (" . implode(',', $have) . ") ORDER BY p.discount_price > 0 DESC, p.id DESC LIMIT " . (3 - count($bestsellers)));
        $bestsellers = array_merge($bestsellers, $stmtFill->fetchAll(PDO::FETCH_ASSOC));
    } catch (\PDOException $e) {}
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />

<style>
    .custom-scroll::-webkit-scrollbar { width: 5px; }
    .custom-scroll::-webkit-scrollbar-track { background: transparent; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scroll:hover::-webkit-scrollbar-thumb { background: #cbd5e1; }
    
    .swiper-pagination-bullet { background: rgba(255, 255, 255, 0.7) !important; opacity: 1 !important; width: 8px !important; height: 8px !important; transition: all 0.3s ease !important; }
    .swiper-pagination-bullet-active { background: #e87500 !important; width: 24px !important; border-radius: 4px !important; }
    
    .product-card:hover .product-img { transform: scale(1.05); }
    .news-card:hover .news-img { transform: scale(1.05); }
</style>

<div class="bg-[#f7f4f0] min-h-screen pb-16 pt-5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <?php if (!empty($categories)): ?>
            <nav aria-label="Danh mục sách" class="lg:hidden bg-white border border-[#ece7e1] mb-4 px-4 py-3 overflow-x-auto">
                <div class="flex gap-5 whitespace-nowrap text-sm">
                    <?php foreach ($categories as $cat): ?>
                        <a href="products.php?category=<?= (int)$cat['id'] ?>" class="text-gray-700 hover:text-brown-600"><?= htmlspecialchars($cat['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </nav>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row gap-5 mb-5 items-start">
            
            <div class="hidden lg:flex w-[255px] shrink-0 bookshop-panel overflow-hidden flex-col">
                <div class="px-4 py-4 bg-brown-600 text-white font-bold tracking-wide text-sm flex items-center gap-2 shrink-0">
                    <i class="fas fa-book-open"></i> Danh mục sách
                </div>
                <div class="px-3 pt-3">
                    <a href="products.php" class="flex items-center gap-2 px-3 py-2.5 bg-brown-600 text-white text-sm font-semibold rounded hover:bg-brown-700 transition-colors">
                        <i class="fas fa-th-large text-xs"></i> Tất cả sách
                    </a>
                </div>
                <div class="py-2 border-b border-slate-100">
                    <?php if (!empty($categories)): ?>
                        <ul class="flex flex-col">
                            <?php foreach ($categories as $cat): ?>
                                <li>
                                    <a href="products.php?category=<?= (int)$cat['id'] ?>" class="flex items-center justify-between px-4 py-2.5 hover:bg-brown-50 hover:text-brown-700 text-slate-600 text-sm transition-colors group">
                                        <?= htmlspecialchars($cat['name']) ?>
                                        <i class="fas fa-angle-right text-xs text-gray-400 group-hover:text-brown-600"></i>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="px-5 py-4 text-slate-400 text-sm italic">Chưa có danh mục...</div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($bestsellers)): ?>
                    <div class="px-4 py-3">
                        <h3 class="text-sm font-bold text-slate-800 mb-2">Sách bán chạy</h3>
                        <?php foreach ($bestsellers as $b): ?>
                            <?php $bp = ((float)$b['discount_price'] > 0 && (float)$b['discount_price'] < (float)$b['price']) ? (float)$b['discount_price'] : (float)$b['price']; ?>
                            <a href="product_detail.php?id=<?= (int)$b['id'] ?>" class="flex gap-3 py-2 border-t border-slate-100 first:border-0 group">
                                <img src="<?= htmlspecialchars(product_image_url($b['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="" class="w-11 h-14 object-cover border border-slate-100 shrink-0 bg-slate-50">
                                <span class="min-w-0">
                                    <span class="block text-xs text-slate-700 line-clamp-2 group-hover:text-brown-600"><?= htmlspecialchars($b['name']) ?></span>
                                    <span class="block text-xs font-bold text-red-600 mt-1"><?= number_format($bp, 0, ',', '.') ?>đ</span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex-1 min-w-0 w-full">
            <div class="overflow-hidden shadow-sm h-[200px] sm:h-[300px] md:h-[350px] lg:h-[420px] relative bg-slate-200">
                <div class="swiper heroSwiper w-full h-full">
                    <div class="swiper-wrapper">
                        <?php if (!empty($banners)): ?>
                            <?php foreach ($banners as $banner): ?>
                                <div class="swiper-slide w-full h-full relative">
                                    <a href="<?= isset($banner['link']) ? htmlspecialchars($banner['link']) : '#' ?>" class="block w-full h-full">
                                        <img src="uploads/banners/<?= htmlspecialchars($banner['image']) ?>" class="w-full h-full object-cover">
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="swiper-slide w-full h-full bg-slate-200 flex items-center justify-center">
                                <span class="text-slate-400 font-medium">Chưa có Banner</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="swiper-pagination mb-2"></div>
                    <button type="button" class="hero-prev absolute left-0 top-1/2 -translate-y-1/2 z-10 w-9 h-12 bg-black/35 text-white hover:bg-black/60" aria-label="Trước"><i class="fas fa-angle-left"></i></button>
                    <button type="button" class="hero-next absolute right-0 top-1/2 -translate-y-1/2 z-10 w-9 h-12 bg-black/35 text-white hover:bg-black/60" aria-label="Sau"><i class="fas fa-angle-right"></i></button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
                <a href="products.php?new=1" class="flex items-center gap-4 p-5 bg-gradient-to-r from-slate-100 to-white border border-slate-100 hover:shadow-md transition-shadow">
                    <span class="w-11 h-11 rounded-full bg-white flex items-center justify-center text-slate-700 shadow-sm"><i class="fas fa-book-open"></i></span>
                    <span><span class="block font-bold text-slate-800">Sách mới nổi bật</span><span class="block text-xs text-slate-500">Những tựa sách được xem nhiều nhất</span></span>
                </a>
                <a href="products.php?favorites=1" class="flex items-center gap-4 p-5 bg-gradient-to-r from-orange-50 to-white border border-slate-100 hover:shadow-md transition-shadow">
                    <span class="w-11 h-11 rounded-full bg-brown-100 flex items-center justify-center text-brown-600 shadow-sm"><i class="fas fa-star"></i></span>
                    <span><span class="block font-bold text-slate-800">Sách được yêu thích</span><span class="block text-xs text-slate-500">Được nhiều người mua và đánh giá cao</span></span>
                </a>
            </div>

                <?php
                $recoUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
                $reco = reco_for_user($pdo, $recoUserId, 8);
                $recoSections = [];
                $recoSections[] = [
                    'title' => 'Sách Được Đề Xuất',
                    'note' => $reco['mode'] === 'personalized'
                        ? 'Được tạo từ lịch sử xem, giỏ hàng, đơn hàng và đánh giá của bạn'
                        : 'Gợi ý theo xu hướng chung, sẽ cá nhân hóa khi bạn xem hoặc mua sách',
                    'icon' => 'fa-thumbs-up',
                    'products' => $reco['products'],
                ];
            ?>
            <?php foreach ($recoSections as $recoSection): if (empty($recoSection['products'])) continue; $recoSection['products'] = product_attach_ratings($pdo, $recoSection['products']); ?>
            <div class="mt-5 bg-white border border-slate-100 p-4">
                <div class="flex items-end justify-between mb-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800"><i class="fas <?= $recoSection['icon'] ?> text-brown-600 mr-1"></i><?= htmlspecialchars($recoSection['title']) ?></h2>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars($recoSection['note']) ?></p>
                    </div>
                </div>
                <div class="flex gap-3 overflow-x-auto snap-x pb-2 custom-scroll" style="scrollbar-width:thin">
                    <?php foreach ($recoSection['products'] as $rp):
                        $rPrice = (float)$rp['price'];
                        $rDisc = (float)($rp['discount_price'] ?? 0);
                        $rSell = ($rDisc > 0 && $rDisc < $rPrice) ? $rDisc : $rPrice;
                    ?>
                        <a href="product_detail.php?id=<?= (int)$rp['id'] ?>" class="product-card w-[130px] shrink-0 snap-start group block">
                            <div class="aspect-[3/4] overflow-hidden bg-slate-50 mb-2">
                                <img src="<?= htmlspecialchars(product_image_url($rp['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($rp['name']) ?>" class="product-img w-full h-full object-cover">
                            </div>
                            <h3 class="text-xs font-semibold text-gray-800 line-clamp-2 group-hover:text-brown-600 leading-snug h-8"><?= htmlspecialchars($rp['name']) ?></h3>
                            <?= product_stars_html($rp) ?>
                            <span class="text-sm font-semibold text-red-600"><?= number_format($rSell, 0, ',', '.') ?>đ</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-12">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-1">Sách Mới Nổi Bật</h2>
                    <div class="h-[2px] w-full bg-brown-600"></div>
                </div>
                <a href="products.php?new=1" class="text-brown-600 font-bold hover:text-brown-800 transition-colors text-sm sm:text-base flex items-center gap-1">
                    Khám phá thêm <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>

            <div class="flex gap-4 overflow-x-auto snap-x pb-3 custom-scroll" style="scrollbar-width:thin">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $prod): ?>
                        <a href="product_detail.php?id=<?= (int)$prod['id'] ?>" class="product-card w-[165px] sm:w-[190px] shrink-0 snap-start bg-white p-3 transition-all duration-300 border border-slate-100 group block relative">
                            <div class="aspect-square overflow-hidden relative bg-slate-50 mb-3">
                                <span class="absolute top-2 left-2 z-10 bg-brown-600 text-white text-[10px] sm:text-xs font-bold px-2 py-1 uppercase shadow-sm">Hot</span>
                                
                                <?php 
                                    $price = isset($prod['price']) ? (float)$prod['price'] : 0;
                                    $discountPrice = isset($prod['discount_price']) ? (float)$prod['discount_price'] : 0;
                                    $sellingPrice = ($discountPrice > 0 && $discountPrice < $price) ? $discountPrice : $price;
                                    $oldPrice = ($sellingPrice < $price) ? $price : 0;
                                    $hasDiscount = false;
                                    
                                    if ($oldPrice > $sellingPrice && $sellingPrice > 0) {
                                        $discountPercent = round((($oldPrice - $sellingPrice) / $oldPrice) * 100);
                                        $hasDiscount = true;
                                    }
                                ?>
                                <?php if ($hasDiscount): ?>
                                    <span class="absolute top-2 right-2 z-10 bg-red-600 text-white text-[10px] sm:text-xs font-bold px-2 py-1 shadow-sm">-<?= $discountPercent ?>%</span>
                                <?php endif; ?>

                                <img src="<?= htmlspecialchars(product_image_url($prod['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($prod['name'] ?? '') ?>" class="product-img w-full h-full object-cover transition-transform duration-500">
                            </div>
                            
                            <h3 class="text-sm sm:text-base font-semibold text-gray-800 line-clamp-2 mb-2 group-hover:text-brown-600 transition-colors leading-snug h-10 sm:h-12">
                                <?= htmlspecialchars($prod['name'] ?? '') ?>
                            </h3>
                            <?php if (!empty($prod['author'])): ?>
                                <p class="text-xs text-slate-500 line-clamp-1 mb-2">Tác giả: <?= htmlspecialchars($prod['author']) ?></p>
                            <?php endif; ?>
                            <?= product_stars_html($prod) ?>
                            
                            <div class="flex items-end gap-2 flex-wrap">
                                <span class="text-lg sm:text-xl font-semibold text-red-600"><?= number_format($sellingPrice, 0, ',', '.') ?>đ</span>
                                <?php if ($hasDiscount): ?>
                                    <span class="text-xs sm:text-sm text-slate-400 line-through font-medium pb-1"><?= number_format($oldPrice, 0, ',', '.') ?>đ</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-12 text-slate-400 font-medium bg-white rounded-2xl border border-slate-100">Đang cập nhật sản phẩm...</div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="flex items-end justify-between mb-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-1">Góc Đọc Sách</h2>
                    <div class="h-[2px] w-full bg-brown-600"></div>
                </div>
                <a href="news.php" class="text-brown-600 font-bold hover:text-brown-800 transition-colors text-sm sm:text-base flex items-center gap-1">
                    Xem toàn bộ <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                <?php if (!empty($news)): ?>
                    <?php foreach ($news as $item): ?>
                        <a href="news_detail.php?id=<?= $item['id'] ?>" class="news-card bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-100 group block flex flex-col h-full">
                            <div class="aspect-video relative overflow-hidden bg-slate-100 shrink-0">
                                <img src="uploads/news/<?= htmlspecialchars($item['image']) ?>" class="news-img w-full h-full object-cover transition-transform duration-500">
                                <?php if(isset($item['created_at'])): ?>
                                <div class="absolute bottom-3 left-3 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-lg shadow-sm flex items-center gap-2">
                                    <i class="far fa-calendar-alt text-brown-600 text-xs"></i>
                                    <span class="text-xs font-bold text-slate-700"><?= date('d/m/Y', strtotime($item['created_at'])) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-5 flex-1 flex flex-col">
                                <h3 class="text-base sm:text-lg font-bold text-slate-800 line-clamp-2 mb-3 group-hover:text-brown-600 transition-colors leading-snug flex-1">
                                    <?= htmlspecialchars($item['title'] ?? '') ?>
                                </h3>
                                <div class="text-sm font-bold text-brown-600 flex items-center gap-1 group-hover:gap-2 transition-all shrink-0">
                                    Đọc tiếp <i class="fas fa-angle-right"></i>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-12 text-slate-400 font-medium bg-white rounded-2xl border border-slate-100">Đang cập nhật tin tức...</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        new Swiper('.heroSwiper', {
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            effect: 'fade',
            navigation: { nextEl: '.hero-next', prevEl: '.hero-prev' },
            fadeEffect: {
                crossFade: true
            }
        });
    });
</script>

<?php require_once 'footer.php'; ?>