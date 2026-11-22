<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo "<script>window.location.href='news.php';</script>";
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([$id]);
    $news = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$news) {
        echo "<script>window.location.href='news.php';</script>";
        exit;
    }

    $stmtRelated = $pdo->prepare("SELECT * FROM news WHERE id != ? ORDER BY id DESC LIMIT 3");
    $stmtRelated->execute([$id]);
    $relatedNews = $stmtRelated->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    echo "<script>window.location.href='news.php';</script>";
    exit;
}
?>

<style>
    .news-content {
        line-height: 1.8;
        color: #334155;
        font-size: 1.0625rem;
    }
    @media (min-width: 640px) {
        .news-content { font-size: 1.125rem; }
    }
    .news-content p { margin-bottom: 1.25rem; }
    .news-content img { 
        max-width: 100%; 
        height: auto; 
        border-radius: 0.75rem; 
        margin: 2rem auto; 
        display: block; 
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    }
    .news-content figure.image {
        margin: 2rem auto;
        text-align: center;
    }
    .news-content figure.image img {
        margin: 0 auto 0.5rem auto;
    }
    .news-content figure.image figcaption {
        font-size: 0.875rem;
        color: #64748b;
        font-style: italic;
    }
    .news-content h2 { 
        font-size: 1.5rem; 
        font-weight: 900; 
        color: #1e293b; 
        margin-top: 2.5rem; 
        margin-bottom: 1rem; 
        line-height: 1.3;
    }
    .news-content h3 { 
        font-size: 1.25rem; 
        font-weight: 800; 
        color: #1e293b; 
        margin-top: 2rem; 
        margin-bottom: 0.75rem; 
    }
    .news-content ul { 
        list-style-type: disc; 
        padding-left: 1.5rem; 
        margin-bottom: 1.25rem; 
    }
    .news-content ol { 
        list-style-type: decimal; 
        padding-left: 1.5rem; 
        margin-bottom: 1.25rem; 
    }
    .news-content li { margin-bottom: 0.5rem; }
    .news-content a { color: #ca7344; font-weight: 600; text-decoration: underline; }
    .news-content a:hover { color: #9f462d; }
    .news-content blockquote { 
        border-left: 4px solid #ca7344; 
        background: #fdf8f5; 
        padding: 1.25rem 1.5rem; 
        border-radius: 0 0.5rem 0.5rem 0; 
        margin: 2rem 0;
        font-style: italic; 
        color: #475569; 
    }
    .news-content blockquote p:last-child { margin-bottom: 0; }
    .news-content table {
        width: 100%;
        border-collapse: collapse;
        margin: 1.5rem 0;
    }
    .news-content table th, .news-content table td {
        border: 1px solid #e2e8f0;
        padding: 0.75rem;
    }
    .news-content table th {
        background-color: #f8fafc;
        font-weight: 700;
        text-align: left;
    }
</style>

<div class="bg-slate-50 min-h-screen pb-20 pt-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <nav class="flex text-sm text-slate-500 mb-6 font-medium whitespace-nowrap overflow-x-auto pb-2">
            <a href="index.php" class="hover:text-brown-600 transition-colors">Trang Chủ</a>
            <span class="mx-2"><i class="fas fa-angle-right text-xs"></i></span>
            <a href="news.php" class="hover:text-brown-600 transition-colors">Tin Tức</a>
            <span class="mx-2"><i class="fas fa-angle-right text-xs"></i></span>
            <span class="text-slate-800 truncate max-w-[150px] sm:max-w-xs"><?= htmlspecialchars($news['title'] ?? '') ?></span>
        </nav>

        <article class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-12">
            
            <div class="p-6 sm:p-10 lg:p-12 border-b border-slate-100 relative overflow-hidden bg-slate-900">
                <div class="absolute inset-0 opacity-40">
                    <img src="uploads/news/<?= htmlspecialchars($news['image'] ?? 'default.jpg') ?>" class="w-full h-full object-cover blur-sm scale-110">
                </div>
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/80 to-transparent"></div>
                
                <div class="relative z-10 flex flex-col justify-end min-h-[200px] sm:min-h-[250px]">
                    <div class="flex flex-wrap items-center gap-4 text-brown-300 text-sm font-bold mb-4">
                        <?php if (isset($news['created_at'])): ?>
                            <span class="flex items-center gap-2 bg-slate-800/80 backdrop-blur px-3 py-1.5 rounded-lg border border-slate-700/50">
                                <i class="far fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($news['created_at'])) ?>
                            </span>
                        <?php endif; ?>
                        <span class="flex items-center gap-2 bg-slate-800/80 backdrop-blur px-3 py-1.5 rounded-lg border border-slate-700/50 text-emerald-400">
                            <i class="fas fa-newspaper"></i> Tin tức
                        </span>
                    </div>
                    
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-white leading-tight mb-4">
                        <?= htmlspecialchars($news['title'] ?? '') ?>
                    </h1>
                    
                    <?php if (!empty($news['description'])): ?>
                        <p class="text-slate-300 text-sm sm:text-base font-medium leading-relaxed max-w-3xl">
                            <?= htmlspecialchars(strip_tags($news['description'])) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="p-6 sm:p-10 lg:p-12">
                <div class="news-content">
                    <?= $news['content'] ?? '<p class="italic text-slate-400">Nội dung đang được cập nhật...</p>' ?>
                </div>

                <div class="mt-12 pt-8 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-slate-800">Chia sẻ bài viết:</span>
                        <div class="flex gap-2">
                            <button class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-colors flex items-center justify-center">
                                <i class="fab fa-facebook-f"></i>
                            </button>
                            <button class="w-10 h-10 rounded-full bg-sky-50 text-sky-500 hover:bg-sky-500 hover:text-white transition-colors flex items-center justify-center">
                                <i class="fab fa-twitter"></i>
                            </button>
                            <button class="w-10 h-10 rounded-full bg-slate-100 text-slate-700 hover:bg-slate-700 hover:text-white transition-colors flex items-center justify-center">
                                <i class="fas fa-link"></i>
                            </button>
                        </div>
                    </div>
                    <a href="news.php" class="font-bold text-brown-600 hover:text-brown-800 flex items-center gap-2 transition-colors">
                        <i class="fas fa-arrow-left"></i> Quay lại danh sách
                    </a>
                </div>
            </div>
        </article>

        <?php if (!empty($relatedNews)): ?>
        <div class="mt-16">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-800 mb-1">Tin Tức Liên Quan</h2>
                    <div class="h-1 w-20 bg-brown-600 rounded-full"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($relatedNews as $item): ?>
                    <a href="news_detail.php?id=<?= $item['id'] ?>" class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-100 group flex flex-col h-full">
                        <div class="aspect-[4/3] relative overflow-hidden bg-slate-100 shrink-0">
                            <img src="uploads/news/<?= htmlspecialchars($item['image'] ?? 'default.jpg') ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                            <?php if(isset($item['created_at'])): ?>
                                <div class="absolute bottom-3 left-3 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-lg shadow-sm flex items-center gap-2">
                                    <i class="far fa-calendar-alt text-brown-600 text-[10px] sm:text-xs"></i>
                                    <span class="text-[10px] sm:text-xs font-bold text-slate-700"><?= date('d/m/Y', strtotime($item['created_at'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-5 flex flex-col flex-1">
                            <h3 class="text-base font-bold text-slate-800 line-clamp-2 mb-3 group-hover:text-brown-600 transition-colors leading-snug">
                                <?= htmlspecialchars($item['title'] ?? '') ?>
                            </h3>
                            <div class="mt-auto text-xs font-bold text-brown-600 flex items-center gap-1 group-hover:gap-2 transition-all">
                                Đọc tiếp <i class="fas fa-angle-right"></i>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once 'footer.php'; ?>