<?php
require_once 'header.php';

$statusLabels = [
    'pending' => 'Chờ xử lý',
    'processing' => 'Đang xử lý',
    'shipping' => 'Đang giao hàng',
    'completed' => 'Giao thành công',
    'cancelled' => 'Đã hủy',
];
$statusStyles = [
    'pending' => ['bg-amber-100 text-amber-700', 'fa-clock'],
    'processing' => ['bg-blue-100 text-blue-700', 'fa-box-open'],
    'shipping' => ['bg-purple-100 text-purple-700', 'fa-truck'],
    'completed' => ['bg-emerald-100 text-emerald-700', 'fa-circle-check'],
    'cancelled' => ['bg-red-100 text-red-700', 'fa-ban'],
];
$statusCounts = array_fill_keys(array_keys($statusLabels), 0);
$periodStats = [];
$periodCards = [];

try {
    $stmtTotalOrders = $pdo->query("SELECT COUNT(*) as total, SUM(total_amount) as revenue FROM orders");
    $ordersStat = $stmtTotalOrders->fetch();
    $totalRevenue = $ordersStat['revenue'] ?? 0;
    $totalOrders = $ordersStat['total'] ?? 0;

    $stmtTotalProducts = $pdo->query("SELECT COUNT(*) as total FROM products");
    $totalProducts = $stmtTotalProducts->fetch()['total'] ?? 0;

    $stmtTotalUsers = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $totalUsers = $stmtTotalUsers->fetch()['total'] ?? 0;

    $stmtPie = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $statusData = $stmtPie->fetchAll();
    
    $labelsPie = [];
    $dataPie = [];
    $statusCounts = array_fill_keys(array_keys($statusLabels), 0);
    foreach ($statusData as $row) {
        $statusCounts[$row['status']] = (int)$row['count'];
        $labelsPie[] = $statusLabels[$row['status']] ?? $row['status'];
        $dataPie[] = $row['count'];
    }

    $stmtPeriods = $pdo->query("SELECT
        SUM(YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)) AS week_count,
        COALESCE(SUM(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) THEN total_amount END), 0) AS week_amount,
        SUM(YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())) AS month_count,
        COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN total_amount END), 0) AS month_amount,
        SUM(YEAR(created_at) = YEAR(CURDATE())) AS year_count,
        COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) THEN total_amount END), 0) AS year_amount
        FROM orders WHERE status = 'completed'");
    $periodStats = $stmtPeriods->fetch();
    $periodCards = [
        ['Tuần này', 'fa-calendar-week', 'week'],
        ['Tháng này', 'fa-calendar-alt', 'month'],
        ['Năm nay', 'fa-calendar', 'year'],
    ];

    $series = [];
    $fillSeries = function (string $sql, array $keys) use ($pdo) {
        $map = [];
        foreach ($pdo->query($sql)->fetchAll() as $r) {
            $map[$r['k']] = $r;
        }
        $out = ['labels' => [], 'orders' => [], 'amount' => []];
        foreach ($keys as $k => $label) {
            $out['labels'][] = $label;
            $out['orders'][] = (int)($map[$k]['cnt'] ?? 0);
            $out['amount'][] = (float)($map[$k]['amt'] ?? 0);
        }
        return $out;
    };
    $days = [];
    for ($i = 29; $i >= 0; $i--) {
        $t = strtotime("-$i day");
        $days[date('Y-m-d', $t)] = date('d/m', $t);
    }
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $t = strtotime(date('Y-m-01') . " -$i month");
        $months[date('Y-m', $t)] = date('m/Y', $t);
    }
    $years = [];
    for ($i = 4; $i >= 0; $i--) {
        $y = (int)date('Y') - $i;
        $years[(string)$y] = (string)$y;
    }
    $base = "SELECT %s AS k, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amt FROM orders WHERE status = 'completed' AND created_at >= %s GROUP BY k";
    $series['day'] = $fillSeries(sprintf($base, "DATE_FORMAT(created_at,'%Y-%m-%d')", "DATE_SUB(CURDATE(), INTERVAL 29 DAY)"), $days);
    $series['month'] = $fillSeries(sprintf($base, "DATE_FORMAT(created_at,'%Y-%m')", "DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')"), $months);
    $series['year'] = $fillSeries(sprintf($base, "DATE_FORMAT(created_at,'%Y')", "MAKEDATE(YEAR(CURDATE()) - 4, 1)"), $years);
    $labelsBar = $series['day']['labels'];
    $dataBar = $series['day']['amount'];

    $stmtRecentOrders = $pdo->query("SELECT o.id, u.username, o.total_amount, o.status, o.created_at FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 5");
    $recentOrders = $stmtRecentOrders->fetchAll();

    $stmtRecentUsers = $pdo->query("SELECT id, username, phone, created_at FROM users WHERE role = 'user' ORDER BY id DESC LIMIT 5");
    $recentUsers = $stmtRecentUsers->fetchAll();

} catch (Exception $e) {
    $totalRevenue = 0; $totalOrders = 0; $totalProducts = 0; $totalUsers = 0;
    $labelsPie = []; $dataPie = [];
    $labelsBar = []; $dataBar = []; $series = [];
    $recentOrders = []; $recentUsers = [];
}
?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center gap-5 relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-50 rounded-full z-0"></div>
        <div class="w-14 h-14 rounded-2xl bg-blue-500 text-white flex items-center justify-center text-2xl z-10 shadow-lg shadow-blue-500/30">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="z-10">
            <p class="text-sm font-bold text-slate-400 mb-1">Tổng Doanh Thu</p>
            <h3 class="text-2xl font-black text-slate-800"><?= number_format($totalRevenue, 0, ',', ',') ?>đ</h3>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center gap-5 relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-brown-50 rounded-full z-0"></div>
        <div class="w-14 h-14 rounded-2xl bg-brown-500 text-white flex items-center justify-center text-2xl z-10 shadow-lg shadow-brown-500/30">
            <i class="fas fa-shopping-bag"></i>
        </div>
        <div class="z-10">
            <p class="text-sm font-bold text-slate-400 mb-1">Đơn Hàng</p>
            <h3 class="text-2xl font-black text-slate-800"><?= $totalOrders ?></h3>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center gap-5 relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-50 rounded-full z-0"></div>
        <div class="w-14 h-14 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-2xl z-10 shadow-lg shadow-emerald-500/30">
            <i class="fas fa-book"></i>
        </div>
        <div class="z-10">
            <p class="text-sm font-bold text-slate-400 mb-1">Sản Phẩm</p>
            <h3 class="text-2xl font-black text-slate-800"><?= $totalProducts ?></h3>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center gap-5 relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-50 rounded-full z-0"></div>
        <div class="w-14 h-14 rounded-2xl bg-purple-500 text-white flex items-center justify-center text-2xl z-10 shadow-lg shadow-purple-500/30">
            <i class="fas fa-users"></i>
        </div>
        <div class="z-10">
            <p class="text-sm font-bold text-slate-400 mb-1">Khách Hàng</p>
            <h3 class="text-2xl font-black text-slate-800"><?= $totalUsers ?></h3>
        </div>
    </div>
</div>

<h3 class="text-lg font-bold text-slate-800 mb-3">Đơn giao thành công và tiền đã nhận</h3>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <?php foreach ($periodCards as [$title, $icon, $key]): ?>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center"><i class="fas <?= $icon ?>"></i></div>
                <p class="font-bold text-slate-600"><?= $title ?></p>
            </div>
            <div class="flex justify-between items-end">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase">Đơn giao thành công</p>
                    <p class="text-2xl font-black text-slate-800"><?= (int)($periodStats[$key . '_count'] ?? 0) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-bold text-slate-400 uppercase">Số tiền đã nhận</p>
                    <p class="text-2xl font-black text-emerald-600"><?= number_format((float)($periodStats[$key . '_amount'] ?? 0), 0, ',', '.') ?>đ</p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h3 class="text-lg font-bold text-slate-800 mb-3">Tổng quan đơn hàng theo trạng thái</h3>
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <?php foreach ($statusLabels as $key => $label): ?>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="w-10 h-10 rounded-xl <?= $statusStyles[$key][0] ?> flex items-center justify-center mb-3"><i class="fas <?= $statusStyles[$key][1] ?>"></i></div>
            <p class="text-sm font-bold text-slate-500"><?= $label ?></p>
            <p class="text-2xl font-black text-slate-800"><?= $statusCounts[$key] ?> <span class="text-sm font-semibold text-slate-400">đơn</span></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <h3 class="text-lg font-bold text-slate-800">Đơn đã giao và tiền đã nhận</h3>
            <div class="inline-flex rounded-xl bg-slate-100 p-1" id="periodTabs">
                <button type="button" data-period="day" class="px-4 py-1.5 rounded-lg text-sm font-bold">Ngày</button>
                <button type="button" data-period="month" class="px-4 py-1.5 rounded-lg text-sm font-bold">Tháng</button>
                <button type="button" data-period="year" class="px-4 py-1.5 rounded-lg text-sm font-bold">Năm</button>
            </div>
        </div>
        <div class="flex flex-wrap gap-6 mb-4 text-sm">
            <div><span class="text-slate-400 font-bold">Đơn đã giao:</span> <span id="sumOrders" class="font-black text-slate-800">0</span></div>
            <div><span class="text-slate-400 font-bold">Tiền đã nhận:</span> <span id="sumAmount" class="font-black text-emerald-600">0đ</span></div>
        </div>
        <div class="relative w-full h-[300px]">
            <canvas id="barChart"></canvas>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
        <h3 class="text-lg font-bold text-slate-800 mb-6">Trạng thái đơn hàng</h3>
        <div class="relative w-full h-[300px] flex items-center justify-center">
            <canvas id="pieChart"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-lg font-bold text-slate-800"><i class="fas fa-clipboard-list text-brown-500 mr-2"></i>Đơn Hàng Mới Nhất</h3>
            <a href="orders.php" class="text-sm font-bold text-brown-600 hover:text-brown-800">Xem tất cả</a>
        </div>
        <div class="p-4 sm:p-6">
            <table class="w-full text-left border-collapse mobile-card-table">
                <thead>
                    <tr>
                        <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-l-lg uppercase tracking-wider">Mã</th>
                        <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Khách Hàng</th>
                        <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Giá Trị</th>
                        <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Trạng Thái</th>
                        <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-r-lg text-right uppercase tracking-wider">Xử Lý</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="5" class="py-8 text-center text-slate-400 font-medium">Chưa có đơn hàng nào</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td data-label="Mã Đơn" class="py-4 px-4 font-bold text-slate-800 border-b border-slate-50"><?= $order['id'] ?></td>
                                <td data-label="Khách Hàng" class="py-4 px-4 font-semibold text-slate-600 border-b border-slate-50"><?= htmlspecialchars($order['username']) ?></td>
                                <td data-label="Giá Trị" class="py-4 px-4 font-black text-brown-600 border-b border-slate-50"><?= number_format($order['total_amount'], 0, ',', ',') ?>đ</td>
                                <td data-label="Trạng Thái" class="py-4 px-4 border-b border-slate-50">
                                    <span class="px-3 py-1 <?= $statusStyles[$order['status']][0] ?? 'bg-slate-100 text-slate-700' ?> rounded-full text-xs font-bold tracking-wide"><?= htmlspecialchars($statusLabels[$order['status']] ?? $order['status']) ?></span>
                                </td>
                                <td data-label="Hành Động" class="py-4 px-4 border-b border-slate-50 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-blue-500 hover:text-white transition-colors flex items-center justify-center"><i class="fas fa-eye"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-lg font-bold text-slate-800"><i class="fas fa-user-plus text-brown-500 mr-2"></i>Khách Hàng Mới</h3>
            <a href="users.php" class="text-sm font-bold text-brown-600 hover:text-brown-800">Xem tất cả</a>
        </div>
        <div class="p-4 sm:p-6">
            <table class="w-full text-left border-collapse mobile-card-table">
                <thead>
                    <tr>
                        <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-l-lg uppercase tracking-wider">Mã</th>
                        <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Tên Đăng Nhập</th>
                        <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Điện Thoại</th>
                        <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-r-lg text-right uppercase tracking-wider">Thời Gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentUsers)): ?>
                        <tr><td colspan="4" class="py-8 text-center text-slate-400 font-medium">Chưa có người dùng mới</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentUsers as $u): ?>
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td data-label="Mã User" class="py-4 px-4 font-bold text-slate-800 border-b border-slate-50"><?= $u['id'] ?></td>
                                <td data-label="Tài Khoản" class="py-4 px-4 font-semibold text-slate-600 border-b border-slate-50">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs uppercase"><?= substr($u['username'], 0, 1) ?></div>
                                        <?= htmlspecialchars($u['username']) ?>
                                    </div>
                                </td>
                                <td data-label="Điện Thoại" class="py-4 px-4 font-medium text-slate-600 border-b border-slate-50"><?= htmlspecialchars($u['phone']) ?></td>
                                <td data-label="Đăng Ký Vào" class="py-4 px-4 text-slate-500 text-sm border-b border-slate-50 text-right"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const series = <?= json_encode($series) ?>;
        const fmt = n => new Intl.NumberFormat('vi-VN').format(n);

        const labelsPie = <?= json_encode($labelsPie) ?>;
        const dataPie = <?= json_encode($dataPie) ?>;

        const ctxBar = document.getElementById('barChart').getContext('2d');
        const barChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Tiền đã nhận (VNĐ)',
                    data: [],
                    backgroundColor: '#ca7344',
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => 'Tiền đã nhận: ' + fmt(ctx.parsed.y) + 'đ',
                            afterLabel: ctx => 'Đơn đã giao: ' + barChart.$orders[ctx.dataIndex]
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#f1f5f9' }, ticks: { callback: v => fmt(v) } },
                    x: { grid: { display: false } }
                }
            }
        });

        function showPeriod(period) {
            const s = series[period] || { labels: [], orders: [], amount: [] };
            barChart.$orders = s.orders;
            barChart.data.labels = s.labels;
            barChart.data.datasets[0].data = s.amount;
            barChart.update();
            document.getElementById('sumOrders').textContent = s.orders.reduce((a, b) => a + b, 0);
            document.getElementById('sumAmount').textContent = fmt(s.amount.reduce((a, b) => a + b, 0)) + 'đ';
            document.querySelectorAll('#periodTabs button').forEach(b => {
                const active = b.dataset.period === period;
                b.className = 'px-4 py-1.5 rounded-lg text-sm font-bold ' + (active ? 'bg-white text-brown-700 shadow-sm' : 'text-slate-500');
            });
        }
        document.querySelectorAll('#periodTabs button').forEach(b => b.addEventListener('click', () => showPeriod(b.dataset.period)));
        showPeriod('day');

        const ctxPie = document.getElementById('pieChart').getContext('2d');
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: labelsPie.length > 0 ? labelsPie : ['Chưa có dữ liệu'],
                datasets: [{
                    data: dataPie.length > 0 ? dataPie : [1],
                    backgroundColor: ['#ca7344', '#10b981', '#3b82f6', '#8b5cf6'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20, font: { family: 'sans-serif', weight: 'bold' } } }
                }
            }
        });
    });
</script>

<?php require_once 'footer.php'; ?>