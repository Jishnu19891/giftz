<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

$dateFrom = $_GET['from']  ?? date('Y-m-01');
$dateTo   = $_GET['to']    ?? date('Y-m-d');
$groupBy  = in_array($_GET['group'] ?? 'day', ['day','week','month']) ? ($_GET['group'] ?? 'day') : 'day';

// CSV Export
if (isset($_GET['export'])) {
    $stmt = $pdo->prepare("
        SELECT s.invoice_no, COALESCE(c.name,'Walk-in') as customer,
               s.subtotal, s.discount, s.tax, s.total, s.payment_method, s.status, s.created_at
        FROM sales s LEFT JOIN customers c ON c.id=s.customer_id
        WHERE s.created_at >= ? AND s.created_at <= DATE_ADD(?, INTERVAL 1 DAY) AND s.status='completed'
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice','Customer','Subtotal','Discount','Tax','Total','Payment','Status','Date']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['invoice_no'],$r['customer'],$r['subtotal'],$r['discount'],$r['tax'],$r['total'],$r['payment_method'],$r['status'],$r['created_at']]);
    }
    fclose($out);
    exit;
}

$labelFmt = ['day' => '%b %d', 'week' => 'Week %u', 'month' => '%b %Y'];
$groupFmt = ['day' => '%Y-%m-%d', 'week' => '%Y-%u', 'month' => '%Y-%m'];
$lFmt     = $labelFmt[$groupBy];
$gFmt     = $groupFmt[$groupBy];

$trendStmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '$lFmt') as period_label,
           COALESCE(SUM(total),0) as revenue, COUNT(*) as count
    FROM sales
    WHERE created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY) AND status='completed'
    GROUP BY DATE_FORMAT(created_at, '$gFmt')
    ORDER BY MIN(created_at) ASC
");
$trendStmt->execute([$dateFrom, $dateTo]);
$trend        = $trendStmt->fetchAll();
$trendLabels  = array_column($trend, 'period_label');
$trendRevenue = array_map('floatval', array_column($trend, 'revenue'));

// Summary stats
$summary = $pdo->prepare("
    SELECT COALESCE(SUM(total),0) as total_revenue,
           COALESCE(SUM(discount),0) as total_discount,
           COUNT(*) as sale_count,
           COALESCE(AVG(total),0) as avg_sale
    FROM sales
    WHERE created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY) AND status='completed'
");
$summary->execute([$dateFrom, $dateTo]);
$summary = $summary->fetch();

// Top products
$topStmt = $pdo->prepare("
    SELECT p.name, p.sku, SUM(si.quantity) as qty_sold, SUM(si.total_price) as revenue
    FROM sale_items si
    JOIN sales s ON s.id=si.sale_id AND s.status='completed'
       AND s.created_at >= ? AND s.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
    JOIN products p ON p.id=si.product_id
    GROUP BY si.product_id, p.name, p.sku
    ORDER BY revenue DESC LIMIT 10
");
$topStmt->execute([$dateFrom, $dateTo]);
$topProds = $topStmt->fetchAll();

// Payment breakdown
$pmStmt = $pdo->prepare("
    SELECT payment_method, COUNT(*) as cnt, SUM(total) as total
    FROM sales
    WHERE created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL 1 DAY) AND status='completed'
    GROUP BY payment_method ORDER BY total DESC
");
$pmStmt->execute([$dateFrom, $dateTo]);
$pmBreak = $pmStmt->fetchAll();

$pageTitle   = 'Sales Report';
$breadcrumbs = ['Reports' => '', 'Sales' => ''];
$inlineJs    = "
document.addEventListener('DOMContentLoaded', () => {
    GiftzCharts.salesTrend('salesReportChart', " . json_encode($trendLabels) . ", " . json_encode($trendRevenue) . ");
});
";
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">📈</span> Sales Report</h1>
    <a href="?from=<?= e($dateFrom) ?>&to=<?= e($dateTo) ?>&group=<?= e($groupBy) ?>&export=1" class="btn btn-secondary">⬇ Export CSV</a>
</div>

<!-- Filter -->
<form method="GET" class="card" style="padding:1.25rem;margin-bottom:1.25rem">
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="<?= e($dateFrom) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="<?= e($dateTo) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Group By</label>
            <select name="group" class="form-control">
                <option value="day"   <?= $groupBy==='day'  ?'selected':''?>>Day</option>
                <option value="week"  <?= $groupBy==='week' ?'selected':''?>>Week</option>
                <option value="month" <?= $groupBy==='month'?'selected':''?>>Month</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Generate Report</button>
    </div>
</form>

<!-- KPIs -->
<div class="kpi-grid" style="margin-bottom:1.25rem;grid-template-columns:repeat(4,1fr)">
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon">💰</div>
        <div class="kpi-info"><div class="kpi-label">Total Revenue</div><div class="kpi-value"><?= formatCurrency($summary['total_revenue']) ?></div></div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-icon">🧾</div>
        <div class="kpi-info"><div class="kpi-label">Total Sales</div><div class="kpi-value"><?= number_format($summary['sale_count']) ?></div></div>
    </div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-icon">📊</div>
        <div class="kpi-info"><div class="kpi-label">Average Sale</div><div class="kpi-value"><?= formatCurrency($summary['avg_sale']) ?></div></div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon">🏷</div>
        <div class="kpi-info"><div class="kpi-label">Total Discounts</div><div class="kpi-value"><?= formatCurrency($summary['total_discount']) ?></div></div>
    </div>
</div>

<!-- Chart -->
<div class="card" style="margin-bottom:1.25rem">
    <div class="card-header"><div class="card-title">📈 Revenue Trend</div></div>
    <div class="card-body">
        <div class="chart-container" style="height:280px">
            <?php if (empty($trend)): ?>
            <div class="empty-state"><div class="icon">📊</div><p>No data for selected period.</p></div>
            <?php else: ?>
            <canvas id="salesReportChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:1.25rem">

    <!-- Top Products -->
    <div class="card">
        <div class="card-header"><div class="card-title">🏆 Top Products</div></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Product</th><th>SKU</th><th class="text-center">Qty Sold</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                <?php if (empty($topProds)): ?>
                    <tr><td colspan="4" class="text-center text-muted" style="padding:2rem">No data</td></tr>
                <?php else: ?>
                    <?php foreach ($topProds as $p): ?>
                    <tr>
                        <td class="fw-bold"><?= e($p['name']) ?></td>
                        <td class="fs-sm text-muted"><?= e($p['sku']) ?></td>
                        <td class="text-center"><?= number_format($p['qty_sold']) ?></td>
                        <td class="text-right"><?= formatCurrency($p['revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="card">
        <div class="card-header"><div class="card-title">💳 Payment Methods</div></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Method</th><th class="text-center">Count</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                <?php if (empty($pmBreak)): ?>
                    <tr><td colspan="3" class="text-center text-muted" style="padding:2rem">No data</td></tr>
                <?php else: ?>
                    <?php foreach ($pmBreak as $pm): ?>
                    <tr>
                        <td><span class="badge badge-info"><?= e(strtoupper($pm['payment_method'])) ?></span></td>
                        <td class="text-center"><?= $pm['cnt'] ?></td>
                        <td class="text-right fw-bold"><?= formatCurrency($pm['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
