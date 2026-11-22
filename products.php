<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'header.php';

$search = trim($_GET['search'] ?? '');
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$publisher = trim($_GET['publisher'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$sortOptions = [
    'newest' => 'Mới nhất',
    'oldest' => 'Cũ nhất',
    'price_asc' => 'Giá: Tăng dần',
    'price_desc' => 'Giá: Giảm dần',
    'name_asc' => 'Tên: A-Z',
    'name_desc' => 'Tên: Z-A',
];
if (!isset($sortOptions[$sort])) {
    $sort = 'newest';
}
$discountOnly = isset($_GET['discount']) && $_GET['discount'] === '1';
$newOnly = isset($_GET['new']) && $_GET['new'] === '1';
$favoritesOnly = isset($_GET['favorites']) && $_GET['favorites'] === '1';
$recommendedOnly = isset($_GET['recommended']) && $_GET['recommended'] === '1';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$catName = 'Danh mục';
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = per_page(9, PER_PAGE_SHOP);
$offset = ($page - 1) * $limit;
$totalProducts = 0;
$relatedProducts = [];
$searchResult = ['exact' => [], 'related' => []];

try {
    $priceStmt = $pdo->query("SELECT MIN(price) as min_p, MAX(price) as max_p FROM products");
    $priceRange = $priceStmt->fetch(PDO::FETCH_ASSOC);
    $dbMinPrice = floor((float)($priceRange['min_p'] ?? 0));
    $dbMaxPrice = ceil((float)($priceRange['max_p'] ?? 10000000));

    $catStmt = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($category_id > 0 && !in_array($category_id, array_map('intval', array_column($categories, 'id')), true)) {
        $category_id = 0;
    }
    $catName = 'Danh mục';
    foreach ($categories as $category) {
        if ((int)$category['id'] === $category_id) {
            $catName = $category['name'];
            break;
        }
    }

    $publisherSql = "SELECT DISTINCT publisher FROM products WHERE publisher IS NOT NULL AND publisher <> ''";
    $publisherParams = [];
    if ($category_id > 0) {
        $publisherSql .= " AND category_id = ?";
        $publisherParams[] = $category_id;
    }
    $publisherSql .= " ORDER BY publisher ASC";
    $publisherStmt = $pdo->prepare($publisherSql);
    $publisherStmt->execute($publisherParams);
    $publishers = $publisherStmt->fetchAll(PDO::FETCH_COLUMN);

    $params = [];
    $where = "WHERE 1=1";

    $searchResult = ['exact' => [], 'related' => []];
    if ($search !== '') {
        $searchResult = search_products($pdo, $search);
        $ids = array_map('intval', $searchResult['exact']);
        $where .= $ids ? " AND id IN (" . implode(',', $ids) . ")" : " AND 1=0";
    }
    if ($category_id > 0) {
        $where .= " AND category_id = ?";
        $params[] = $category_id;
    }
    if ($publisher !== '') {
        $where .= " AND publisher = ?";
        $params[] = $publisher;
    }
    if ($discountOnly) {
        $where .= " AND discount_price > 0 AND discount_price < price";
    }
    if ($newOnly) {
        $hotIds = array_map('intval', array_column(reco_most_viewed($pdo, 24), 'id'));
        $where .= $hotIds ? " AND id IN (" . implode(',', $hotIds) . ")" : " AND 1=0";
    }
    if ($recommendedOnly) {
        $recoPage = reco_for_user($pdo, isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null, 24);
        $favIds = array_map('intval', array_column($recoPage['products'], 'id'));
        $where .= $favIds ? " AND id IN (" . implode(',', $favIds) . ")" : " AND 1=0";
    }

    
    if ($favoritesOnly) { // Chỉ lấy sách có điểm đánh giá trung bình từ 4 sao trở lên
    $favStmt = $pdo->query("
        SELECT product_id
        FROM product_reviews
        GROUP BY product_id
        HAVING AVG(rating) >= 4
        ORDER BY AVG(rating) DESC, COUNT(*) DESC
    ");
    $favIds = array_map('intval', $favStmt->fetchAll(PDO::FETCH_COLUMN));
    $where .= $favIds ? " AND id IN (" . implode(',', $favIds) . ")" : " AND 1=0";
}


    if ($min_price > 0) {
        $where .= " AND price >= ?";
        $params[] = $min_price;
    }
    if ($max_price > 0) {
        $where .= " AND price <= ?";
        $params[] = $max_price;
    }

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM products $where");
    $totalStmt->execute($params);
    $totalProducts = $totalStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $limit);

    $rankIds = !empty($favIds) ? $favIds : (!empty($hotIds) ? $hotIds : []);
    $orderBy = [
        'newest' => ($search !== '' && $searchResult['exact']) ? 'FIELD(id, ' . implode(',', array_map('intval', $searchResult['exact'])) . ')' : ($rankIds ? 'FIELD(id, ' . implode(',', $rankIds) . ')' : 'id DESC'),
        'oldest' => 'id ASC',
        'price_asc' => 'COALESCE(NULLIF(discount_price, 0), price) ASC, id DESC',
        'price_desc' => 'COALESCE(NULLIF(discount_price, 0), price) DESC, id DESC',
        'name_asc' => 'name ASC',
        'name_desc' => 'name DESC',
    ][$sort];
    $prodStmt = $pdo->prepare("SELECT * FROM products $where ORDER BY $orderBy LIMIT $limit OFFSET $offset");
    $prodStmt->execute($params);
    $products = product_attach_ratings($pdo, $prodStmt->fetchAll(PDO::FETCH_ASSOC));

    $relatedProducts = [];
    if ($search !== '' && $searchResult['related']) {
        $relIds = array_map('intval', $searchResult['related']);
        $relStmt = $pdo->query("SELECT * FROM products WHERE id IN (" . implode(',', $relIds) . ") ORDER BY FIELD(id, " . implode(',', $relIds) . ")");
        $relatedProducts = $relStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $featuredSql = "SELECT * FROM products WHERE quantity > 0";
    $featuredParams = [];
    if ($category_id > 0) {
        $featuredSql .= " AND category_id = ?";
        $featuredParams[] = $category_id;
    }
    $featuredSql .= " ORDER BY (discount_price > 0 AND discount_price < price) DESC, id DESC LIMIT 4";
    $featuredStmt = $pdo->prepare($featuredSql);
    $featuredStmt->execute($featuredParams);
    $featuredProducts = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    $products = [];
    $categories = [];
    $publishers = [];
    $featuredProducts = [];
    $totalPages = 1;
    $dbMinPrice = 0;
    $dbMaxPrice = 10000000;
    $totalProducts = 0;
}

function buildUrl($newPage) {
    $qs = $_GET;
    $qs['page'] = $newPage;
    return '?' . http_build_query($qs);
}
?>

<style>
    .custom-scroll::-webkit-scrollbar { width: 5px; }
    .custom-scroll::-webkit-scrollbar-track { background: transparent; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scroll:hover::-webkit-scrollbar-thumb { background: #cbd5e1; }
    .collection-page { background: #f7f3ed; }
    .collection-side-panel { margin-bottom: 14px; }
    .collection-sidebar h3 { letter-spacing: .04em; }
    .collection-card:hover .product-img { transform: scale(1.035); }
    .collection-cover { background: #fff; }
    .collection-sort { min-width: 180px; }
    input[type=number]::-webkit-inner-spin-button, 
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }
    @media (max-width: 1023px) {
        .collection-sidebar { max-width: calc(100vw - 40px); }
    }
</style>

<div class="collection-page min-h-screen pb-16 pt-0 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <nav aria-label="Breadcrumb" class="collection-breadcrumb flex flex-wrap items-center gap-2 text-sm text-slate-500 py-3 mb-6 font-medium">
            <a href="index.php" class="hover:text-brown-600 transition-colors">Trang chủ</a>
            <span>/</span>
            <a href="products.php" class="hover:text-brown-600 transition-colors">Danh mục</a>
            <?php if ($category_id > 0): ?>
                <span>/</span>
                <span class="text-slate-700"><?= htmlspecialchars($catName ?? 'Sản phẩm') ?></span>
            <?php elseif ($search !== ''): ?>
                <span>/</span>
                <span class="text-slate-700">Kết quả tìm kiếm</span>
            <?php elseif ($newOnly): ?>
                <span>/</span>
                <span class="text-slate-700">Sách mới nổi bật</span>
            <?php elseif ($recommendedOnly): ?>
                <span>/</span>
                <span class="text-slate-700">Sách được đề xuất</span>
            <?php elseif ($favoritesOnly): ?>
                <span>/</span>
                <span class="text-slate-700">Sách được yêu thích</span>
            <?php elseif ($discountOnly): ?>
                <span>/</span>
                <span class="text-slate-700">Sách giảm giá</span>
            <?php else: ?>
                <span>/</span>
                <span class="text-slate-700">Tất cả sản phẩm</span>
            <?php endif; ?>
        </nav>

        <div class="flex flex-col lg:flex-row gap-7 items-start">
            
            <div id="filterOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] opacity-0 pointer-events-none transition-opacity lg:hidden"></div>
            
            <aside id="filterSidebar" class="collection-sidebar fixed inset-y-0 left-0 w-[290px] bg-white z-[70] shadow-2xl transform -translate-x-full transition-transform duration-300 lg:static lg:translate-x-0 lg:w-[255px] lg:shrink-0 lg:shadow-none lg:z-auto flex flex-col h-full lg:h-auto">
                <div class="flex items-center justify-between p-5 border-b border-slate-100 bg-white lg:hidden">
                    <span class="text-lg font-black text-slate-800 uppercase tracking-widest">BỘ LỌC</span>
                    <button id="closeFilterBtn" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:text-red-500 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto custom-scroll p-5 lg:p-0 space-y-4 bg-[#f8f5f0] lg:bg-transparent">
                    
                    <div class="collection-side-panel bg-white p-4 hidden lg:block">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700 mb-3">
                            Tìm kiếm
                        </h3>
                        <form method="GET" action="products.php" class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm sách, tác giả, nhà xuất bản..." class="w-full bg-white border border-slate-200 py-2.5 pl-3 pr-10 text-sm focus:outline-none focus:border-brown-500 transition-all">
                            <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-brown-600 transition-colors">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if($category_id > 0): ?><input type="hidden" name="category" value="<?= $category_id ?>"><?php endif; ?>
                            <?php if($discountOnly): ?><input type="hidden" name="discount" value="1"><?php endif; ?>
                            <?php if($min_price > 0): ?><input type="hidden" name="min_price" value="<?= $min_price ?>"><?php endif; ?>
                            <?php if($max_price > 0): ?><input type="hidden" name="max_price" value="<?= $max_price ?>"><?php endif; ?>
                            <?php if($publisher !== ''): ?><input type="hidden" name="publisher" value="<?= htmlspecialchars($publisher) ?>"><?php endif; ?>
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                        </form>
                    </div>

                    <div class="collection-side-panel bg-white p-4">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700 border-b border-[#e87500] pb-3 mb-3">
                            Danh mục
                        </h3>
                        <div class="max-h-[300px] overflow-y-auto custom-scroll pr-2 -mr-2">
                            <ul class="flex flex-col gap-1">
                                <li>
                                    <a href="products.php?<?= http_build_query(array_merge($_GET, ['category' => 0, 'publisher' => '', 'page' => 1])) ?>" class="collection-filter-link flex items-center py-1.5 text-sm transition-colors <?= $category_id === 0 ? 'text-brown-700 font-bold' : 'text-slate-600 hover:text-brown-600' ?>">
                                        <i class="fas fa-angle-right text-[10px] mr-2 opacity-50"></i> Tất cả sản phẩm
                                    </a>
                                </li>
                                <?php foreach ($categories as $cat): ?>
                                    <li>
                                        <a href="products.php?<?= http_build_query(array_merge($_GET, ['category' => $cat['id'], 'publisher' => '', 'page' => 1])) ?>" class="collection-filter-link flex items-center py-1.5 text-sm transition-colors <?= $category_id === (int)$cat['id'] ? 'text-brown-700 font-bold' : 'text-slate-600 hover:text-brown-600' ?>">
                                            <i class="fas fa-angle-right text-[10px] mr-2 opacity-50"></i> <?= htmlspecialchars($cat['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <?php if (!empty($publishers)): ?>
                    <div class="collection-side-panel bg-white p-4">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700 border-b border-[#e87500] pb-3 mb-3">
                            Nhà xuất bản
                        </h3>
                        <ul class="max-h-48 overflow-y-auto custom-scroll">
                            <li>
                                <a href="products.php?<?= http_build_query(array_merge($_GET, ['publisher' => '', 'page' => 1])) ?>" class="collection-filter-link block py-1.5 text-sm <?= $publisher === '' ? 'text-brown-700 font-bold' : 'text-slate-600 hover:text-brown-600' ?>">
                                    Tất cả nhà xuất bản
                                </a>
                            </li>
                            <?php foreach ($publishers as $publisherName): ?>
                                <li>
                                    <a href="products.php?<?= http_build_query(array_merge($_GET, ['publisher' => $publisherName, 'page' => 1])) ?>" class="collection-filter-link block py-1.5 text-sm <?= $publisher === $publisherName ? 'text-brown-700 font-bold' : 'text-slate-600 hover:text-brown-600' ?>">
                                        <?= htmlspecialchars($publisherName) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="collection-side-panel bg-white p-4">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700 border-b border-[#e87500] pb-3 mb-3">
                            Giá sản phẩm
                        </h3>
                        <form method="GET" action="products.php" class="space-y-4">
                            <?php if($search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                            <?php if($category_id > 0): ?><input type="hidden" name="category" value="<?= $category_id ?>"><?php endif; ?>
                            <?php if($discountOnly): ?><input type="hidden" name="discount" value="1"><?php endif; ?>
                            <?php if($publisher !== ''): ?><input type="hidden" name="publisher" value="<?= htmlspecialchars($publisher) ?>"><?php endif; ?>
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                            
                            <div class="flex items-center gap-3">
                                <div class="flex-1">
                                    <label class="block text-xs text-slate-500 mb-1">Từ (đ)</label>
                                    <input type="number" name="min_price" value="<?= $min_price > 0 ? $min_price : $dbMinPrice ?>" min="<?= $dbMinPrice ?>" max="<?= $dbMaxPrice ?>" class="w-full bg-white border border-slate-200 py-2 px-2 text-sm text-slate-700 focus:outline-none focus:border-brown-500 transition-all">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs text-slate-500 mb-1">Đến (đ)</label>
                                    <input type="number" name="max_price" value="<?= $max_price > 0 ? $max_price : $dbMaxPrice ?>" min="<?= $dbMinPrice ?>" max="<?= $dbMaxPrice ?>" class="w-full bg-white border border-slate-200 py-2 px-2 text-sm text-slate-700 focus:outline-none focus:border-brown-500 transition-all">
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-[#e87500] hover:bg-[#c65f00] text-white font-bold py-2.5 text-sm transition-colors">
                                Lọc sản phẩm
                            </button>
                        </form>
                    </div>

                    <?php if (!empty($featuredProducts)): ?>
                    <div class="collection-side-panel bg-white p-4">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700 border-b border-[#e87500] pb-3 mb-3">
                            Sản phẩm nổi bật
                        </h3>
                        <div class="space-y-4">
                            <?php foreach ($featuredProducts as $featured): ?>
                                <?php $featuredPrice = !empty($featured['discount_price']) && $featured['discount_price'] < $featured['price'] ? $featured['discount_price'] : $featured['price']; ?>
                                <a href="product_detail.php?id=<?= (int)$featured['id'] ?>" class="flex gap-3 items-start group">
                                    <img src="<?= htmlspecialchars(product_image_url($featured['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($featured['name']) ?>" class="w-14 h-[76px] object-contain bg-white shrink-0">
                                    <span class="min-w-0">
                                        <span class="block text-sm text-slate-700 line-clamp-2 group-hover:text-brown-600"><?= htmlspecialchars($featured['name']) ?></span>
                                        <span class="block mt-1 text-sm font-semibold text-[#bd7783]"><?= number_format((float)$featuredPrice, 0, ',', '.') ?>đ</span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </aside>

            <div class="flex-1 w-full">
                <div class="collection-toolbar flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4 mb-4">
                    <div class="min-w-0">
                    <h1 class="text-2xl sm:text-[30px] font-semibold text-slate-800 leading-tight">
                        <?php 
                            $categoryDescription = '';
                            if ($category_id > 0) {
                                foreach($categories as $c) {
                                    if($c['id'] == $category_id) {
                                        $categoryDescription = $c['description'] ?? '';
                                        break;
                                    }
                                }
                                echo htmlspecialchars($catName);
                            } elseif ($recommendedOnly) {
                                echo 'Sách được đề xuất';
                            } elseif ($favoritesOnly) {
                                echo 'Sách được yêu thích';
                            } elseif ($newOnly) {
                                echo 'Sách mới nổi bật';
                            } elseif ($discountOnly) {
                                echo 'Sách khuyến mãi';
                            } elseif ($search !== '') {
                                echo 'Kết quả cho: "' . htmlspecialchars($search) . '"';
                            } else {
                                echo 'Tất Cả Sản Phẩm';
                            }
                        ?>
                    </h1>
                    <?php if ($categoryDescription !== ''): ?>
                        <p class="mt-2 text-sm text-slate-500"><?= htmlspecialchars($categoryDescription) ?></p>
                    <?php endif; ?>
                    </div>
                    <div class="flex w-full sm:w-auto items-center justify-between sm:justify-end gap-3 shrink-0">
                        <button type="button" id="openFilterMobile" class="lg:hidden inline-flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 text-sm text-slate-700">
                            <i class="fas fa-sliders"></i> Bộ lọc
                        </button>
                        <span class="text-sm text-slate-500 whitespace-nowrap">
                            <?= (int)$totalProducts ?> sản phẩm
                        </span>
                        <form method="GET" action="products.php">
                            <?php if($search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                            <?php if($category_id > 0): ?><input type="hidden" name="category" value="<?= $category_id ?>"><?php endif; ?>
                            <?php if($publisher !== ''): ?><input type="hidden" name="publisher" value="<?= htmlspecialchars($publisher) ?>"><?php endif; ?>
                            <?php if($discountOnly): ?><input type="hidden" name="discount" value="1"><?php endif; ?>
                            <?php if($min_price > 0): ?><input type="hidden" name="min_price" value="<?= $min_price ?>"><?php endif; ?>
                            <?php if($max_price > 0): ?><input type="hidden" name="max_price" value="<?= $max_price ?>"><?php endif; ?>
                            <label class="sr-only" for="collectionSort">Sắp xếp sản phẩm</label>
                            <select id="collectionSort" name="sort" class="collection-sort bg-white border-b border-slate-400 px-2 py-2 text-sm text-slate-700 focus:outline-none" onchange="this.form.submit()">
                                <?php foreach ($sortOptions as $sortValue => $sortLabel): ?>
                                    <option value="<?= htmlspecialchars($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= htmlspecialchars($sortLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if (empty($products)): ?>
                    <div class="bg-white p-12 text-center flex flex-col items-center">
                        <i class="fas fa-box-open text-6xl text-slate-200 mb-4"></i>
                        <h3 class="text-xl font-bold text-slate-700 mb-2">Không tìm thấy sản phẩm nào</h3>
                        <p class="text-slate-500">Vui lòng thử thay đổi điều kiện lọc hoặc từ khóa tìm kiếm.</p>
                        <a href="products.php" class="mt-6 bg-brown-50 text-brown-700 font-bold px-6 py-2.5 rounded-xl hover:bg-brown-100 transition-colors">Xóa bộ lọc</a>
                    </div>
                <?php else: ?>
                    <?= per_page_selector($limit, $page, (int)$totalProducts, PER_PAGE_SHOP) ?>
                    <div class="collection-product-grid grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4 mb-10 bg-white p-3 sm:p-4">
                        <?php foreach ($products as $prod): ?>
                            <a href="product_detail.php?id=<?= $prod['id'] ?>" class="collection-card product-card bg-white p-3 sm:p-4 transition-all duration-300 border border-transparent hover:border-slate-100 group block relative flex flex-col h-full">
                                <div class="collection-cover h-[210px] sm:h-[255px] overflow-hidden relative mb-3 shrink-0">
                                    <?php 
                                        $price = isset($prod['price']) ? (float)$prod['price'] : 0;
                                        $discountPrice = isset($prod['discount_price']) ? (float)$prod['discount_price'] : 0;
                                        
                                        $sellingPrice = $price;
                                        $oldPrice = 0;
                                        $hasDiscount = false;
                                        
                                        if ($discountPrice > 0 && $discountPrice < $price) {
                                            $sellingPrice = $discountPrice;
                                            $oldPrice = $price;
                                            $hasDiscount = true;
                                            $discountPercent = round((($oldPrice - $sellingPrice) / $oldPrice) * 100);
                                        }
                                    ?>
                                    <?php if ($hasDiscount): ?>
                                        <span class="absolute top-2 left-2 z-10 bg-[#e87500] text-white text-[10px] sm:text-xs font-semibold px-2 py-1">-<?= $discountPercent ?>%</span>
                                    <?php endif; ?>

                                    <img src="<?= htmlspecialchars(product_image_url($prod['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($prod['name'] ?? '') ?>" class="product-img w-full h-full object-contain transition-transform duration-500">
                                </div>
                                
                                <h3 class="text-sm font-medium text-slate-700 line-clamp-2 mb-2 group-hover:text-brown-600 transition-colors leading-snug flex-1">
                                    <?= htmlspecialchars($prod['name'] ?? '') ?>
                                </h3>
                                <?php if (!empty($prod['author'])): ?>
                                    <p class="text-xs text-slate-500 line-clamp-1 mb-2">Tác giả: <?= htmlspecialchars($prod['author']) ?></p>
                                <?php endif; ?>
                                <?= product_stars_html($prod) ?>
                                
                                <div class="flex flex-wrap items-end gap-x-2 gap-y-1 shrink-0">
                                    <span class="text-base sm:text-lg font-semibold text-[#bd7783] leading-none"><?= number_format($sellingPrice, 0, ',', '.') ?>đ</span>
                                    <?php if ($hasDiscount): ?>
                                        <span class="text-xs text-slate-400 line-through font-normal leading-none"><?= number_format($oldPrice, 0, ',', '.') ?>đ</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="flex justify-center">
                            <div class="flex items-center gap-1.5 sm:gap-2 bg-white p-1.5 sm:p-2 border border-slate-100">
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildUrl($page - 1) ?>" class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-500 hover:bg-brown-50 hover:text-brown-600 transition-colors font-bold text-sm">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                        <a href="<?= buildUrl($i) ?>" class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl font-bold text-sm transition-all <?= $page === $i ? 'bg-brown-600 text-white shadow-md shadow-brown-500/30' : 'bg-slate-50 text-slate-600 hover:bg-brown-50 hover:text-brown-600' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                        <span class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center text-slate-400 font-black tracking-widest">...</span>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= buildUrl($page + 1) ?>" class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-500 hover:bg-brown-50 hover:text-brown-600 transition-colors font-bold text-sm">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <?php if (!empty($relatedProducts)): ?>
                    <div class="bg-white p-4 sm:p-5 mt-6">
                        <h3 class="text-lg font-semibold text-slate-800 mb-4 border-l-4 border-[#e87500] pl-3">
                            <?= empty($products) ? 'Có thể bạn đang tìm' : 'Sách liên quan' ?>
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
                            <?php foreach ($relatedProducts as $rel):
                                $relPrice = (float)$rel['price'];
                                $relDiscount = (float)$rel['discount_price'];
                                $relSelling = ($relDiscount > 0 && $relDiscount < $relPrice) ? $relDiscount : $relPrice;
                            ?>
                                <a href="product_detail.php?id=<?= (int)$rel['id'] ?>" class="group block">
                                    <div class="h-[190px] mb-2 overflow-hidden">
                                        <img src="<?= htmlspecialchars(product_image_url($rel['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($rel['name']) ?>" class="w-full h-full object-contain">
                                    </div>
                                    <h4 class="text-sm font-medium text-slate-700 line-clamp-2 group-hover:text-brown-600"><?= htmlspecialchars($rel['name']) ?></h4>
                                    <?php if (!empty($rel['publisher'])): ?><p class="text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars($rel['publisher']) ?></p><?php endif; ?>
                                    <span class="text-sm font-semibold text-[#bd7783]"><?= number_format($relSelling, 0, ',', '.') ?>đ</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const openFilterBtn = document.getElementById('openFilterMobile');
        const closeFilterBtn = document.getElementById('closeFilterBtn');
        const filterSidebar = document.getElementById('filterSidebar');
        const filterOverlay = document.getElementById('filterOverlay');

        function openFilter() {
            filterSidebar.classList.remove('-translate-x-full');
            filterOverlay.classList.remove('opacity-0', 'pointer-events-none');
            document.body.classList.add('overflow-hidden');
        }

        function closeFilter() {
            filterSidebar.classList.add('-translate-x-full');
            filterOverlay.classList.add('opacity-0', 'pointer-events-none');
            document.body.classList.remove('overflow-hidden');
        }

        if (openFilterBtn) openFilterBtn.addEventListener('click', openFilter);
        if (closeFilterBtn) closeFilterBtn.addEventListener('click', closeFilter);
        if (filterOverlay) filterOverlay.addEventListener('click', closeFilter);
    });
</script>

<?php require_once 'footer.php'; ?>