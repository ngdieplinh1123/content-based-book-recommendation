<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
}

$currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$siteInfo = site_info($pdo);
$siteName = htmlspecialchars($siteInfo['site_name']);
$siteLogo = htmlspecialchars($siteInfo['site_logo'], ENT_QUOTES, 'UTF-8');
$sitePhone = htmlspecialchars($siteInfo['support_phone']);
$sitePhoneTel = preg_replace('/[^0-9+]/', '', $siteInfo['support_phone']);
$siteEmail = htmlspecialchars($siteInfo['support_email']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $siteName ?> | Hiệu sách trực tuyến</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brown: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f08300',
                            600: '#e87500',
                            700: '#c65f00',
                            800: '#8f4300',
                            900: '#522800',
                        }
                    },
                    fontFamily: {
                        sans: ['"Segoe UI"', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #f7f4f0;
            color: #333333;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        .glass-header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-bottom: 1px solid #e8e3dd;
        }
        #mobileMenu {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #mobileOverlay {
            transition: opacity 0.3s ease;
        }
        .btn-primary {
            background: #e87500;
            color: white;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.16);
        }
        .btn-primary:hover {
            background: #c65f00;
            box-shadow: 0 8px 18px rgba(232, 117, 0, 0.20);
        }
        .cart-badge {
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .cart-icon-container:hover .cart-badge {
            transform: scale(1.15);
        }

        .glass-header { box-shadow: 0 2px 8px rgba(40, 30, 20, 0.05); }
        .bookshop-panel {
            background: #fff;
            border: 1px solid #ece7e1;
        }
        .product-card, .news-card {
            border-radius: 4px !important;
            box-shadow: 0 1px 5px rgba(40, 30, 20, .06) !important;
        }
        .product-card:hover, .news-card:hover { transform: translateY(-3px); }
        .product-card .aspect-square, .news-card .aspect-video { background: #f7f4f0; }
        main a { text-underline-offset: 4px; }
        .store-topline { background: #39780b; color: #fff; }
        .store-topline a { color: #fff; }
        .store-topline a:hover { color: #f3c66b; }
        .store-search input { border: 1px solid #dedede; }
        .store-search input:focus { outline: 1px solid #e87500; border-color: #e87500; }
        .store-nav { border-top: 1px solid #f0ece8; }
        .store-nav a:hover { color: #e87500; }
        .store-logo { color: #183b2b; letter-spacing: -1px; }
        @media (max-width: 640px) {
            .store-search { order: 3; width: 100%; }
            .store-search input { min-width: 0; }
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <div class="store-topline hidden sm:block text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-8 flex items-center justify-between">
            <span><?= $siteName ?> - <?= htmlspecialchars($siteInfo['site_slogan']) ?></span>
            <div class="flex items-center gap-5">
                <a href="tel:<?= $sitePhoneTel ?>" class="hover:text-brown-600"><i class="fas fa-phone-alt mr-1"></i> Tư vấn bán hàng: <?= $sitePhone ?></a>
                <a href="mailto:<?= $siteEmail ?>" class="hover:text-brown-600"><i class="fas fa-envelope mr-1"></i> <?= $siteEmail ?></a>
            </div>
        </div>
    </div>

    <header class="glass-header sticky top-0 z-50 w-full">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 min-h-[76px] py-3 flex flex-wrap lg:flex-nowrap items-center justify-between gap-3">
            <div class="flex items-center gap-3 shrink-0">
                <button id="menuToggleBtn" class="lg:hidden w-10 h-10 flex items-center justify-center text-brown-700 hover:text-brown-600 focus:outline-none" aria-label="Mở danh mục">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <a href="index.php" class="store-logo flex items-center gap-2 text-[27px] sm:text-[32px] font-black whitespace-nowrap">
                    <img src="<?= $siteLogo ?>" alt="" class="h-10 w-10 object-contain">
                    <span><?= $siteName ?></span>
                </a>
            </div>

            <form method="GET" action="products.php" class="store-search relative flex flex-1 max-w-[560px] h-11 lg:mx-8" autocomplete="off">
                <input type="search" name="search" id="searchInput" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Tìm kiếm sách, tác giả, nhà xuất bản..." class="flex-1 min-w-0 bg-white px-4 text-sm" aria-autocomplete="list" aria-controls="searchSuggest">
                <button type="submit" class="bg-[#171717] hover:bg-brown-600 text-white px-5 text-sm font-semibold transition-colors"><i class="fas fa-search mr-1"></i> Tìm kiếm</button>
                <div id="searchSuggest" class="hidden absolute left-0 right-0 top-full z-50 bg-white border border-slate-200 shadow-xl max-h-[70vh] overflow-y-auto" role="listbox"></div>
            </form>
            <script>
            (function () {
                var input = document.getElementById('searchInput');
                var box = document.getElementById('searchSuggest');
                if (!input || !box) return;
                var timer = null, seq = 0, active = -1;

                function esc(s) {
                    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
                    });
                }
                function hide() { box.classList.add('hidden'); active = -1; }
                function items() { return box.querySelectorAll('a[data-item]'); }
                function highlight(i) {
                    var list = items();
                    list.forEach(function (el, idx) { el.classList.toggle('bg-orange-50', idx === i); });
                    active = i;
                    if (list[i]) list[i].scrollIntoView({block: 'nearest'});
                }
                function render(data, q) {
                    var html = '';
                    (data.publishers || []).forEach(function (p) {
                        html += '<a data-item href="products.php?publisher=' + encodeURIComponent(p) + '" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-orange-50 border-b border-slate-100"><i class="fas fa-building text-[#e87500] w-5 text-center"></i><span>Nhà xuất bản: <b>' + esc(p) + '</b></span></a>';
                    });
                    (data.products || []).forEach(function (p) {
                        html += '<a data-item href="product_detail.php?id=' + p.id + '" class="flex items-center gap-3 px-4 py-2 hover:bg-orange-50 border-b border-slate-50">' +
                            '<img src="' + esc(p.image) + '" alt="" class="w-10 h-14 object-cover border border-slate-200 bg-slate-50 flex-shrink-0">' +
                            '<div class="min-w-0 flex-1"><div class="text-sm font-medium text-slate-800 line-clamp-2">' + esc(p.name) + '</div>' +
                            (p.author ? '<div class="text-xs text-slate-500 truncate">' + esc(p.author) + '</div>' : '') + '</div>' +
                            '<div class="text-sm font-semibold text-[#e87500] whitespace-nowrap">' + esc(p.price) + '</div></a>';
                    });
                    if (!html) {
                        html = '<div class="px-4 py-4 text-sm text-slate-500">Không có gợi ý cho "' + esc(q) + '". Nhấn Enter để tìm sách liên quan.</div>';
                    } else {
                        html += '<a data-item href="products.php?search=' + encodeURIComponent(q) + '" class="block px-4 py-3 text-sm font-semibold text-[#e87500] text-center hover:bg-orange-50">Xem tất cả kết quả cho "' + esc(q) + '"' + (data.total ? ' (' + data.total + ')' : '') + '</a>';
                    }
                    box.innerHTML = html;
                    box.classList.remove('hidden');
                    active = -1;
                }
                function fetchSuggest() {
                    var q = input.value.trim();
                    if (!q) { hide(); return; }
                    var mine = ++seq;
                    fetch('search_suggest.php?q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}})
                        .then(function (r) { return r.json(); })
                        .then(function (d) { if (mine === seq) render(d, q); })
                        .catch(function () { if (mine === seq) hide(); });
                }

                input.addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(fetchSuggest, 200);
                });
                input.addEventListener('focus', function () { if (input.value.trim()) fetchSuggest(); });
                input.addEventListener('keydown', function (e) {
                    var list = items();
                    if (e.key === 'ArrowDown' && list.length) { e.preventDefault(); highlight((active + 1) % list.length); }
                    else if (e.key === 'ArrowUp' && list.length) { e.preventDefault(); highlight((active - 1 + list.length) % list.length); }
                    else if (e.key === 'Enter' && active >= 0 && list[active]) { e.preventDefault(); window.location.href = list[active].href; }
                    else if (e.key === 'Escape') { hide(); }
                });
                document.addEventListener('click', function (e) {
                    if (!input.form.contains(e.target)) hide();
                });
            })();
            </script>

            <div class="flex items-center gap-3 sm:gap-5">
                <a href="tel:<?= $sitePhoneTel ?>" class="hidden xl:flex items-center gap-3 text-sm text-gray-700">
                    <i class="fas fa-phone-volume text-2xl text-black"></i>
                    <span><span class="block text-xs text-gray-500">Tư vấn bán hàng</span><strong><?= $sitePhone ?></strong></span>
                </a>
                
                <a href="cart.php" class="cart-icon-container relative flex items-center justify-center w-12 h-12 rounded-lg bg-brown-600 hover:bg-brown-700 text-white transition-all duration-300">
                    <i class="fas fa-shopping-cart text-xl transition-transform duration-300"></i>
                    <span class="cart-badge absolute -top-1 -right-1 flex h-[20px] min-w-[20px] px-1 items-center justify-center rounded-full <?= $cart_count > 0 ? 'bg-red-500' : 'bg-gray-600' ?> text-white text-[10px] font-black border-2 border-white">
                        <?= $cart_count > 99 ? '99+' : $cart_count ?>
                    </span>
                </a>

                <div class="h-6 w-[1px] bg-brown-200 mx-1 hidden sm:block"></div>

                <div class="relative group">
                    <?php if ($currentUser): ?>
                        <button class="flex items-center gap-2.5 bg-white border border-brown-200 py-1.5 px-2 sm:pr-4 sm:pl-1.5 rounded-full shadow-sm hover:shadow-md hover:border-brown-300 focus:outline-none transition-all duration-300">
                            <div class="w-8 h-8 bg-brown-600 rounded-full flex items-center justify-center text-white font-bold shadow-inner">
                                <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
                            </div>
                            <span class="hidden sm:block font-bold text-brown-800 max-w-[120px] truncate">
                                <?= htmlspecialchars($currentUser['username']) ?>
                            </span>
                            <i class="fas fa-chevron-down text-[10px] text-brown-400 hidden sm:block transition-transform duration-300 group-hover:rotate-180"></i>
                        </button>
                        
                        <div class="absolute right-0 mt-3 w-64 bg-white rounded-2xl shadow-xl border border-brown-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-2 group-hover:translate-y-0 overflow-hidden z-50">
                            <div class="p-4 border-b border-brown-50 bg-brown-50/50">
                                <div class="font-black text-brown-800 truncate"><?= htmlspecialchars($currentUser['username']) ?></div>
                                <div class="text-xs font-medium text-brown-500 truncate"><?= htmlspecialchars($currentUser['email'] ?? 'Thành viên') ?></div>
                            </div>
                            <div class="py-2">
                                <a href="profile.php" class="flex items-center gap-3 px-5 py-3 text-brown-700 hover:bg-brown-50 transition-colors font-medium">
                                    <div class="w-6 flex justify-center"><i class="fas fa-id-badge text-brown-400 text-lg"></i></div> Trang cá nhân
                                </a>
                                <a href="orders.php" class="flex items-center gap-3 px-5 py-3 text-brown-700 hover:bg-brown-50 transition-colors font-medium">
                                    <div class="w-6 flex justify-center"><i class="fas fa-box-open text-brown-400 text-lg"></i></div> Đơn hàng của tôi
                                </a>
                                <?php if (isset($currentUser['role']) && $currentUser['role'] === 'admin'): ?>
                                    <a href="admin/index.php" class="flex items-center gap-3 px-5 py-3 text-brown-800 font-bold bg-brown-100/50 hover:bg-brown-200/50 transition-colors">
                                        <div class="w-6 flex justify-center"><i class="fas fa-cog text-brown-600 text-lg"></i></div> Quản trị hệ thống
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="border-t border-brown-50 bg-slate-50/50">
                                <a href="logout.php" class="flex items-center gap-3 px-5 py-4 text-red-500 hover:bg-red-50 transition-colors font-bold">
                                    <div class="w-6 flex justify-center"><i class="fas fa-sign-out-alt text-lg"></i></div> Đăng xuất
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn-primary px-5 py-2.5 rounded-xl font-bold shadow-md shadow-brown-500/20 flex items-center gap-2 hover:-translate-y-0.5">
                            <i class="fas fa-sign-in-alt"></i> <span class="hidden sm:inline">Đăng nhập</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <nav class="store-nav hidden lg:block bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-11 flex items-center gap-9 text-sm font-semibold text-gray-700">
                <a href="index.php" class="text-brown-700"><i class="fas fa-home mr-1"></i> Trang chủ</a>
                <a href="products.php?new=1"><i class="fas fa-book-open mr-1"></i> Sách mới nổi bật</a>
                <a href="products.php?discount=1"><i class="fas fa-tags mr-1"></i> Sách khuyến mãi</a>
                <a href="news.php"><i class="far fa-newspaper mr-1"></i> Tin tức</a>
            </div>
        </nav>
    </header>

    <div id="mobileOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] opacity-0 pointer-events-none"></div>
    
    <aside id="mobileMenu" class="fixed top-0 left-0 h-full w-[280px] max-w-[85vw] bg-white z-[70] shadow-2xl flex flex-col -translate-x-full">
        <div class="h-20 flex items-center justify-between px-6 border-b border-brown-100 bg-white">
            <a href="index.php" class="store-logo flex items-center gap-2 text-xl font-black">
                <img src="<?= $siteLogo ?>" alt="" class="h-9 w-9 object-contain">
                <span><?= $siteName ?></span>
            </a>
            <button id="closeMenuBtn" class="w-8 h-8 flex items-center justify-center rounded-full bg-white text-brown-400 hover:text-red-500 hover:bg-red-50 shadow-sm transition-colors focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="flex-1 overflow-y-auto py-6 px-4">
            <div class="space-y-2">
                <a href="index.php" class="flex items-center gap-4 px-4 py-3.5 rounded-xl bg-brown-50 text-brown-800 font-bold border border-brown-100/50">
                    <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-brown-500 shadow-sm"><i class="fas fa-home"></i></div> Trang chủ
                </a>
                <a href="products.php" class="flex items-center gap-4 px-4 py-3.5 rounded-xl text-brown-600 hover:bg-brown-50 hover:text-brown-800 font-bold transition-colors">
                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-brown-400"><i class="fas fa-th-large"></i></div> Sản phẩm
                </a>
                <a href="news.php" class="flex items-center gap-4 px-4 py-3.5 rounded-xl text-brown-600 hover:bg-brown-50 hover:text-brown-800 font-bold transition-colors">
                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-brown-400"><i class="fas fa-newspaper"></i></div> Tin tức
                </a>
            </div>
        </div>

        <?php if (!$currentUser): ?>
        <div class="p-6 border-t border-brown-50 bg-slate-50">
            <a href="login.php" class="flex items-center justify-center gap-2 w-full bg-brown-600 text-white py-3 rounded-xl font-bold shadow-md shadow-brown-500/30">
                <i class="fas fa-sign-in-alt"></i> Đăng nhập ngay
            </a>
        </div>
        <?php endif; ?>
    </aside>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const closeMenuBtn = document.getElementById('closeMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            const mobileOverlay = document.getElementById('mobileOverlay');

            function toggleMobileMenu() {
                const isOpen = !mobileMenu.classList.contains('-translate-x-full');
                
                if (isOpen) {
                    mobileMenu.classList.add('-translate-x-full');
                    mobileOverlay.classList.add('opacity-0', 'pointer-events-none');
                    document.body.classList.remove('overflow-hidden');
                } else {
                    mobileMenu.classList.remove('-translate-x-full');
                    mobileOverlay.classList.remove('opacity-0', 'pointer-events-none');
                    document.body.classList.add('overflow-hidden');
                }
            }

            if(menuToggleBtn) menuToggleBtn.addEventListener('click', toggleMobileMenu);
            if(closeMenuBtn) closeMenuBtn.addEventListener('click', toggleMobileMenu);
            if(mobileOverlay) mobileOverlay.addEventListener('click', toggleMobileMenu);
        });
    </script>
    
    <main class="flex-grow">