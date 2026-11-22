<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'header.php';

$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = per_page(5, [5, 10, 20, 50, 0]);
$topNewsLimit = 4;

$topNews = [];
$newsList = [];
$totalPages = 1;

try {
    $stmtTop = $pdo->query("SELECT * FROM news ORDER BY id DESC LIMIT $topNewsLimit");
    $topNews = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    $heroNews = $topNews[0] ?? null;
    $sideNews = array_slice($topNews, 1, 3);

    $totalStmt = $pdo->query("SELECT COUNT(*) FROM news");
    $totalCount = (int)$totalStmt->fetchColumn();

    $totalListCount = max(0, $totalCount - $topNewsLimit);
    $totalPages = ceil($totalListCount / $limit);

    $offset = (($page - 1) * $limit) + $topNewsLimit;

    $listStmt = $pdo->query("SELECT * FROM news ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $newsList = $listStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    $heroNews = null;
    $sideNews = [];
    $newsList = [];
}

function buildUrl($newPage) {
    $qs = $_GET;
    $qs['page'] = $newPage;
    return '?' . http_build_query($qs);
}
?>

<style>
    .news-hover-img { transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1); }
    .group:hover .news-hover-img { transform: scale(1.05); }
    .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>

<div class="bg-slate-50 min-h-screen pb-20 pt-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <nav class="flex text-sm text-slate-500 mb-8 font-medium">
            <a href="index.php" class="hover:text-brown-600 transition-colors">Trang Chủ</a>
            <span class="mx-2"><i class="fas fa-angle-right text-xs"></i></span>
            <span class="text-slate-800">Tin Tức</span>
        </nav>

        <?php if ($page === 1 && $heroNews): ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-12">
            
            <a href="news_detail.php?id=<?= $heroNews['id'] ?>" class="lg:col-span-8 rounded-2xl overflow-hidden relative group block aspect-[4/3] lg:aspect-[16/9] shadow-sm">
                <img src="uploads/news/<?= htmlspecialchars($heroNews['image'] ?? 'default.jpg') ?>" class="news-hover-img w-full h-full object-cover absolute inset-0">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-transparent"></div>
                
                <div class="absolute bottom-0 left-0 w-full p-6 sm:p-8 flex flex-col justify-end">
                    <?php if (isset($heroNews['created_at'])): ?>
                        <div class="flex items-center gap-2 text-brown-300 text-xs sm:text-sm font-bold mb-3">
                            <i class="far fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($heroNews['created_at'])) ?>
                        </div>
                    <?php endif; ?>
                    <h2 class="text-xl sm:text-3xl lg:text-4xl font-black text-white leading-tight mb-3 group-hover:text-brown-300 transition-colors">
                        <?= htmlspecialchars($heroNews['title'] ?? '') ?>
                    </h2>
                    <p class="text-slate-300 text-sm sm:text-base line-clamp-2 hidden sm:block">
                        <?= htmlspecialchars(strip_tags($heroNews['description'] ?? '')) ?>
                    </p>
                </div>
            </a>

            <div class="lg:col-span-4 flex flex-col gap-4">
                <?php foreach ($sideNews as $item): ?>
                    <a href="news_detail.php?id=<?= $item['id'] ?>" class="bg-white rounded-2xl p-3 shadow-sm hover:shadow-md transition-all border border-slate-100 flex gap-4 items-center group h-full">
                        <div class="w-24 h-24 sm:w-28 sm:h-28 shrink-0 rounded-xl overflow-hidden bg-slate-100 relative">
                            <img src="uploads/news/<?= htmlspecialchars($item['image'] ?? 'default.jpg') ?>" class="news-hover-img w-full h-full object-cover">
                        </div>
                        <div class="flex flex-col flex-1 py-1">
                            <?php if (isset($item['created_at'])): ?>
                                <span class="text-xs font-bold text-brown-600 mb-1.5"><i class="far fa-clock"></i> <?= date('d/m/Y', strtotime($item['created_at'])) ?></span>
                            <?php endif; ?>
                            <h3 class="text-sm sm:text-base font-bold text-slate-800 line-clamp-2 group-hover:text-brown-600 transition-colors leading-snug">
                                <?= htmlspecialchars($item['title'] ?? '') ?>
                            </h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div>
            <div class="flex items-end justify-between mb-6">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-800 mb-1">
                        <?= $page === 1 ? 'Tin Tức Đọc Nhiều' : 'Danh Sách Tin Tức' ?>
                    </h2>
                    <div class="h-1 w-20 bg-brown-600 rounded-full"></div>
                </div>
            </div>

            <?php if (empty($newsList) && !$heroNews): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-12 text-center flex flex-col items-center">
                    <i class="fas fa-newspaper text-6xl text-slate-200 mb-4"></i>
                    <h3 class="text-xl font-bold text-slate-700">Chưa có bài viết nào</h3>
                </div>
            <?php else: ?>
                <?= per_page_selector($limit, $page, (int)$totalListCount, [5, 10, 20, 50, 0]) ?>
                <div class="flex flex-col gap-5 sm:gap-6 mb-10">
                    <?php foreach ($newsList as $news): ?>
                        <a href="news_detail.php?id=<?= $news['id'] ?>" class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-5 shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-100 flex flex-col sm:flex-row gap-5 sm:gap-8 group block">
                            <div class="w-full sm:w-[320px] lg:w-[400px] shrink-0 aspect-[16/10] rounded-xl sm:rounded-2xl overflow-hidden bg-slate-100 relative">
                                <img src="uploads/news/<?= htmlspecialchars($news['image'] ?? 'default.jpg') ?>" class="news-hover-img w-full h-full object-cover">
                                <?php if (isset($news['created_at'])): ?>
                                    <div class="absolute top-3 left-3 bg-white/95 backdrop-blur px-3 py-1.5 rounded-lg shadow-sm flex items-center gap-2">
                                        <i class="far fa-calendar-alt text-brown-600 text-xs"></i>
                                        <span class="text-xs font-bold text-slate-800"><?= date('d/m/Y', strtotime($news['created_at'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex flex-col justify-center flex-1 py-2">
                                <h3 class="text-lg sm:text-xl lg:text-2xl font-black text-slate-800 mb-3 group-hover:text-brown-600 transition-colors leading-snug">
                                    <?= htmlspecialchars($news['title'] ?? '') ?>
                                </h3>
                                <p class="text-slate-500 text-sm sm:text-base line-clamp-3 mb-4 leading-relaxed">
                                    <?= htmlspecialchars(strip_tags($news['description'] ?? '')) ?>
                                </p>
                                <div class="mt-auto inline-flex items-center gap-2 text-sm font-bold text-brown-600">
                                    Đọc chi tiết <i class="fas fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-center mt-12">
                        <div class="flex items-center gap-2 bg-white p-2 rounded-2xl shadow-sm border border-slate-100">
                            <?php if ($page > 1): ?>
                                <a href="<?= buildUrl($page - 1) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-500 hover:bg-brown-50 hover:text-brown-600 transition-colors font-bold">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                    <a href="<?= buildUrl($i) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-bold transition-all <?= $page === $i ? 'bg-brown-600 text-white shadow-md shadow-brown-500/30' : 'bg-slate-50 text-slate-600 hover:bg-brown-50 hover:text-brown-600' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                    <span class="w-10 h-10 flex items-center justify-center text-slate-400 font-black tracking-widest">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= buildUrl($page + 1) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-500 hover:bg-brown-50 hover:text-brown-600 transition-colors font-bold">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once 'footer.php'; ?>