<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$pdo = db();

// KPIs
$todayRevenue  = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=CURDATE() AND status='completed'")->fetchColumn();
$monthRevenue  = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND status='completed'")->fetchColumn();
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$lowStockItems = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= min_stock_level AND status='active'")->fetchColumn();
$totalCustomers= (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$todaySales    = (int)$pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at)=CURDATE() AND status='completed'")->fetchColumn();
// Visitors KPI (silently 0 if table doesn't exist yet)
try {
    $todayVisitors = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitor_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
} catch (Throwable $e) {
    $todayVisitors = 0;
}

// Sales trend last 7 days
$trendRows = $pdo->query("
    SELECT DATE_FORMAT(d.dt,'%b %d') as label, COALESCE(SUM(s.total),0) as revenue
    FROM (
        SELECT CURDATE()-INTERVAL 6 DAY as dt UNION SELECT CURDATE()-INTERVAL 5 DAY UNION SELECT CURDATE()-INTERVAL 4 DAY
        UNION SELECT CURDATE()-INTERVAL 3 DAY UNION SELECT CURDATE()-INTERVAL 2 DAY
        UNION SELECT CURDATE()-INTERVAL 1 DAY UNION SELECT CURDATE()
    ) d
    LEFT JOIN sales s ON DATE(s.created_at)=d.dt AND s.status='completed'
    GROUP BY d.dt, label ORDER BY d.dt ASC
")->fetchAll();

$trendLabels = array_column($trendRows, 'label');
$trendData   = array_map('floatval', array_column($trendRows, 'revenue'));

// Category sales doughnut (this month)
$catSales = $pdo->query("
    SELECT c.name, COALESCE(SUM(si.total_price),0) as total
    FROM categories c
    LEFT JOIN products p ON p.category_id=c.id
    LEFT JOIN sale_items si ON si.product_id=p.id
    LEFT JOIN sales s ON s.id=si.sale_id AND s.status='completed'
        AND MONTH(s.created_at)=MONTH(NOW()) AND YEAR(s.created_at)=YEAR(NOW())
    GROUP BY c.id, c.name
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 8
")->fetchAll();

$catLabels = array_column($catSales, 'name');
$catData   = array_map('floatval', array_column($catSales, 'total'));

// Recent sales
$recentSales = $pdo->query("
    SELECT s.id, s.invoice_no, s.total, s.payment_method, s.created_at,
           COALESCE(c.name,'Walk-in') as customer_name
    FROM sales s
    LEFT JOIN customers c ON c.id=s.customer_id
    WHERE s.status='completed'
    ORDER BY s.created_at DESC LIMIT 8
")->fetchAll();

// Low stock products
$lowStockProducts = $pdo->query("
    SELECT p.name, p.sku, p.stock_qty, p.min_stock_level, c.name as cat
    FROM products p LEFT JOIN categories c ON c.id=p.category_id
    WHERE p.stock_qty <= p.min_stock_level AND p.status='active'
    ORDER BY p.stock_qty ASC LIMIT 6
")->fetchAll();

$pageTitle  = 'Dashboard';
$breadcrumbs = ['Dashboard' => ''];

$inlineJs = "
document.addEventListener('DOMContentLoaded', () => {
    GiftzCharts.salesTrend('salesTrendChart', " . json_encode($trendLabels) . ", " . json_encode($trendData) . ");
    " . (!empty($catData) ? "GiftzCharts.categoryDoughnut('categoryChart', " . json_encode($catLabels) . ", " . json_encode($catData) . ");" : '') . "
});
";

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <span class="title-icon">⊞</span> Dashboard
    </h1>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/public/catalog.php" class="btn btn-secondary" target="_blank">🛍 Public Catalog</a>
        <a href="<?= BASE_URL ?>/sales/pos.php"     class="btn btn-primary">🛒 New Sale</a>
        <a href="<?= BASE_URL ?>/purchases/add.php" class="btn btn-secondary">📋 New Purchase</a>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon">💰</div>
        <div class="kpi-info">
            <div class="kpi-label">Today's Revenue</div>
            <div class="kpi-value"><?= formatCurrency($todayRevenue) ?></div>
            <div class="kpi-sub"><?= $todaySales ?> sale<?= $todaySales != 1 ? 's' : '' ?> today</div>
        </div>
    </div>
    <div class="kpi-card kpi-pink">
        <div class="kpi-icon">📈</div>
        <div class="kpi-info">
            <div class="kpi-label">Month Revenue</div>
            <div class="kpi-value"><?= formatCurrency($monthRevenue) ?></div>
            <div class="kpi-sub"><?= date('F Y') ?></div>
        </div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-icon">📦</div>
        <div class="kpi-info">
            <div class="kpi-label">Total Products</div>
            <div class="kpi-value"><?= number_format($totalProducts) ?></div>
            <div class="kpi-sub <?= $lowStockItems > 0 ? 'kpi-trend down' : '' ?>">
                <?= $lowStockItems > 0 ? "⚠ $lowStockItems low stock" : 'All stocked' ?>
            </div>
        </div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon">👥</div>
        <div class="kpi-info">
            <div class="kpi-label">Customers</div>
            <div class="kpi-value"><?= number_format($totalCustomers) ?></div>
            <div class="kpi-sub">Registered</div>
        </div>
    </div>
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon">👁</div>
        <div class="kpi-info">
            <div class="kpi-label">Today's Visitors</div>
            <div class="kpi-value"><?= number_format($todayVisitors) ?></div>
            <div class="kpi-sub"><a href="<?= BASE_URL ?>/visitors/index.php" style="color:inherit;text-decoration:underline;opacity:.75">View analytics</a></div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="charts-grid">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📈 Sales Trend (Last 7 Days)</div>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height:260px">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">🏷 Sales by Category</div>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height:260px">
                <?php if (empty($catData)): ?>
                <div class="empty-state" style="padding:3rem 1rem">
                    <div class="icon">📊</div>
                    <p>No sales data yet this month.</p>
                </div>
                <?php else: ?>
                <canvas id="categoryChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:1.25rem">

    <!-- Recent Sales -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">🧾 Recent Sales</div>
            <a href="<?= BASE_URL ?>/sales/index.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th class="text-right">Total</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentSales)): ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:2rem">No sales yet</td></tr>
                <?php else: ?>
                    <?php foreach ($recentSales as $sale): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $sale['id'] ?>" style="font-weight:600;color:var(--accent)"><?= e($sale['invoice_no']) ?></a></td>
                        <td><?= e($sale['customer_name']) ?></td>
                        <td><span class="badge badge-info"><?= e(strtoupper($sale['payment_method'])) ?></span></td>
                        <td class="text-right fw-bold"><?= formatCurrency($sale['total']) ?></td>
                        <td class="text-muted fs-sm"><?= formatDateTime($sale['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">⚠ Low Stock Alert</div>
            <a href="<?= BASE_URL ?>/reports/inventory.php?filter=low" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <?php if (empty($lowStockProducts)): ?>
        <div class="empty-state" style="padding:2rem">
            <div class="icon">✅</div>
            <h3>All stocked up!</h3>
            <p>No items below minimum level.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Min</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStockProducts as $p): ?>
                    <tr>
                        <td>
                            <div class="product-name"><?= e($p['name']) ?></div>
                            <div class="product-sku"><?= e($p['sku']) ?></div>
                        </td>
                        <td class="text-muted fs-sm"><?= e($p['cat'] ?? '—') ?></td>
                        <td class="text-center">
                            <span class="badge <?= $p['stock_qty'] <= 0 ? 'stock-out' : 'stock-low' ?>">
                                <?= $p['stock_qty'] ?>
                            </span>
                        </td>
                        <td class="text-center text-muted"><?= $p['min_stock_level'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
