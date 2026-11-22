<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../config.php';

$currentPage = basename($_SERVER['PHP_SELF']);

$menuItems = [
    'index.php' => ['icon' => 'fa-chart-pie', 'title' => 'Bảng Điều Khiển'],
    'orders.php' => ['icon' => 'fa-box-open', 'title' => 'Đơn Hàng'],
    'products.php' => ['icon' => 'fa-book', 'title' => 'Sản Phẩm'],
    'categories.php' => ['icon' => 'fa-layer-group', 'title' => 'Danh Mục'],
    'news.php' => ['icon' => 'fa-newspaper', 'title' => 'Tin Tức'],
    'banners.php' => ['icon' => 'fa-images', 'title' => 'Banner'],
    'users.php' => ['icon' => 'fa-users', 'title' => 'Tài Khoản'],
    'payment_settings.php' => ['icon' => 'fa-credit-card', 'title' => 'Thanh Toán'],
    'site_settings.php' => ['icon' => 'fa-sliders-h', 'title' => 'Thông Tin Website'],
];
$siteInfo = site_info($pdo);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Quản Trị Hệ Thống</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brown: {
                            50: '#fbf8ef', 100: '#f2ead7', 200: '#e3d4b6', 300: '#cbb88d',
                            400: '#ad9462', 500: '#d8b56b', 600: '#315b50', 700: '#26483f',
                            800: '#1d342e', 900: '#132520',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #f5f3ea; color: #23302b; }
        .sidebar-transition { transition: transform 0.3s ease-in-out; }
        .content-transition { transition: margin-left 0.3s ease-in-out; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #315b50; border-radius: 10px; }
        
        @media (max-width: 767px) {
            .mobile-card-table thead { display: none; }
            .mobile-card-table tbody, .mobile-card-table tr, .mobile-card-table td { display: block; width: 100%; }
            .mobile-card-table tr { margin-bottom: 1rem; border: 1px solid #e2e8f0; border-radius: 0.75rem; background: #fff; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            .mobile-card-table td { text-align: right; padding: 0.75rem 1rem 0.75rem 45%; position: relative; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; }
            .mobile-card-table td::before { content: attr(data-label); position: absolute; left: 1rem; top: 0.75rem; width: 40%; text-align: left; font-weight: 700; color: #64748b; }
            .mobile-card-table td:last-child { border-bottom: 0; text-align: right; }
            .mobile-card-table td:last-child::before { content: none; }
            .mobile-card-table td:last-child > div { display: flex; justify-content: flex-end; width: 100%; }
        }

        #sidebar { background: #132520 !important; border-right: 0 !important; }
        #sidebar .bg-brown-50 { background: #172d27 !important; border-color: rgba(255,255,255,.08) !important; }
        #sidebar nav a { color: #d7ded8; }
        #sidebar nav a:not(.bg-brown-500):hover { background: #1d342e !important; color: #ffffff !important; }
        #sidebar nav a.bg-brown-500 { background: #d8b56b !important; color: #132520 !important; box-shadow: none !important; }
        #sidebar nav a.bg-brown-500 i { color: #132520 !important; }
        #sidebar .text-slate-400 { color: #9caea4 !important; }
        #mainContent > header { background: rgba(255,252,244,.9) !important; border-bottom: 1px solid #e4dcc9 !important; }
        .mobile-card-table tr, table, .rounded-2xl { border-color: #ebe2cf !important; }
        .shadow-sm { box-shadow: 0 6px 18px rgba(19,37,32,.05) !important; }
    </style>
</head>
<body class="flex bg-slate-50">

    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

    <aside id="sidebar" class="bg-brown-900 text-brown-50 w-64 h-screen fixed top-0 left-0 z-50 border-r border-slate-800 sidebar-transition -translate-x-full md:translate-x-0 flex flex-col shadow-xl md:shadow-none">
        <div class="h-20 flex items-center justify-between px-6 border-b border-slate-100 bg-brown-50">
            <a href="index.php" class="flex items-center gap-2 text-xl font-black text-white tracking-wide">
                <img src="../<?= htmlspecialchars($siteInfo['site_logo'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-9 w-9 object-contain">
                <?= htmlspecialchars($siteInfo['site_name']) ?><span class="text-brown-400 font-light">ADMIN</span>
            </a>
            <button id="closeSidebar" class="md:hidden text-slate-400 hover:text-red-500 text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-1">
            <?php foreach ($menuItems as $url => $item): ?>
                <?php $isActive = ($currentPage === $url); ?>
                <a href="<?= $url ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-semibold <?= $isActive ? 'bg-brown-500 text-white shadow-md shadow-brown-500/30' : 'text-slate-500 hover:bg-slate-50 hover:text-brown-600' ?>">
                    <i class="fas <?= $item['icon'] ?> w-5 text-center <?= $isActive ? 'text-white' : 'text-slate-400' ?>"></i>
                    <?= $item['title'] ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-slate-100 space-y-2">
            <a href="../index.php" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-brown-500 text-white font-bold hover:bg-brown-600 transition-colors">
                <i class="fas fa-home"></i> Về Trang Chủ
            </a>
            <a href="../logout.php" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-red-50 text-red-600 font-bold hover:bg-red-500 hover:text-white transition-colors">
                <i class="fas fa-sign-out-alt"></i> Đăng Xuất
            </a>
        </div>
    </aside>

    <div id="mainContent" class="flex-1 flex flex-col min-h-screen content-transition md:ml-64 w-full">
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-30 px-4 sm:px-8 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-4">
                <button id="openSidebar" class="md:hidden text-slate-500 hover:text-brown-600 text-2xl p-2 rounded-lg bg-slate-50 border border-slate-200">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="text-xl font-bold text-slate-800 hidden sm:block">Quản Trị Viên</h1>
            </div>

            <div class="flex items-center gap-4 sm:gap-6">
                <a href="../index.php" class="flex items-center gap-2 px-4 h-10 rounded-full bg-slate-100 text-slate-600 font-semibold hover:bg-brown-100 hover:text-brown-600 transition-colors" title="Về trang chủ">
                    <i class="fas fa-home"></i> <span class="hidden sm:inline">Trang chủ</span>
                </a>
                <div class="flex items-center gap-3 pl-4 sm:pl-6 border-l border-slate-200">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-brown-400 to-brown-600 text-white flex items-center justify-center font-bold shadow-md">
                        <?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?>
                    </div>
                    <div class="hidden sm:block">
                        <p class="text-sm font-bold text-slate-800"><?= htmlspecialchars($_SESSION['user']['username']) ?></p>
                        <p class="text-xs text-green-500 font-medium"><i class="fas fa-circle text-[8px] mr-1"></i>Đang trực tuyến</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-8 overflow-x-hidden">