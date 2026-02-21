<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

$dateFrom = $_GET['from']  ?? date('Y-m-01');
$dateTo   = $_GET['to']    ?? date('Y-m-d');
$groupBy  = in_array($_GET['group'] ?? 'month', ['day','week','month']) ? ($_GET['group'] ?? 'month') : 'month';

$groupFmt = ['day' => '%Y-%m-%d', 'week' => '%Y-%u', 'month' => '%Y-%m'];
$labelFmt = ['day' => '%b %d', 'week' => 'Wk %u', 'month' => '%b %Y'];
$gFmt     = $groupFmt[$groupBy];
$lFmt     = $labelFmt[$groupBy];

$dataStmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(s.created_at, '$lFmt') as period,
        COALESCE(SUM(si.total_price), 0) as revenue,
        COALESCE(SUM(si.quantity * p.cost_price), 0) as cogs
    FROM sales s
    JOIN sale_items si ON si.sale_id=s.id
    JOIN products p ON p.id=si.product_id
    WHERE s.created_at >= ? AND s.created_at <= DATE_ADD(?, INTERVAL 1 DAY) AND s.status='completed'
    GROUP BY DATE_FORMAT(s.created_at, '$gFmt')
    ORDER BY MIN(s.created_at) ASC
");
$dataStmt->execute([$dateFrom, $dateTo]);
$data = $dataStmt->fetchAll();

$labels  = array_column($data, 'period');
$revenue = array_map('floatval', array_column($data, 'revenue'));
$cogs    = array_map('floatval', array_column($data, 'cogs'));
$profit  = array_map(fn($r, $c) => $r - $c, $revenue, $cogs);

$totRev       = array_sum($revenue);
$totCogs      = array_sum($cogs);
$totGrossProfit = $totRev - $totCogs;

// ─── Expenses per period ───────────────────────────────────
$expStmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(expense_date, '$lFmt') as period,
        COALESCE(SUM(amount), 0)           as expenses
    FROM expenses
    WHERE expense_date >= ? AND expense_date <= ?
    GROUP BY DATE_FORMAT(expense_date, '$gFmt')
    ORDER BY MIN(expense_date) ASC
");
$expStmt->execute([$dateFrom, $dateTo]);
$expData = $expStmt->fetchAll();

// Build a period→expenses lookup and merge with existing periods
$expByPeriod = array_column($expData, 'expenses', 'period');
$expenses    = array_map(fn($lbl) => floatval($expByPeriod[$lbl] ?? 0), $labels);

// Also collect expenses for periods that only have expenses (no sales)
foreach ($expData as $eRow) {
    if (!in_array($eRow['period'], $labels)) {
        $labels[]   = $eRow['period'];
        $revenue[]  = 0.0;
        $cogs[]     = 0.0;
        $profit[]   = 0.0;
        $expenses[] = floatval($eRow['expenses']);
    }
}

$netProfit   = array_map(fn($gp, $exp) => $gp - $exp, $profit, $expenses);
$totExpenses = array_sum($expenses);
$totNetProfit = $totGrossProfit - $totExpenses;
$netMargin   = $totRev > 0 ? ($totNetProfit / $totRev * 100) : 0;

$pageTitle   = 'Profit Report';
$breadcrumbs = ['Reports' => '', 'Profit' => ''];
$inlineJs    = "
document.addEventListener('DOMContentLoaded', () => {
    GiftzCharts.profitBar('profitChart',
        " . json_encode($labels) . ",
        " . json_encode($revenue) . ",
        " . json_encode($cogs) . ",
        " . json_encode($profit) . ",
        " . json_encode($expenses) . ",
        " . json_encode($netProfit) . "
    );
});
";
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">💰</span> Profit Report</h1>
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
        <button type="submit" class="btn btn-primary">Generate</button>
    </div>
</form>

<!-- KPIs -->
<div class="kpi-grid" style="margin-bottom:1.25rem;grid-template-columns:repeat(3,1fr)">
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon">💰</div>
        <div class="kpi-info"><div class="kpi-label">Revenue</div><div class="kpi-value"><?= formatCurrency($totRev) ?></div></div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon">📦</div>
        <div class="kpi-info"><div class="kpi-label">COGS</div><div class="kpi-value"><?= formatCurrency($totCogs) ?></div></div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-icon">📈</div>
        <div class="kpi-info">
            <div class="kpi-label">Gross Profit</div>
            <div class="kpi-value" style="color:<?= $totGrossProfit >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= formatCurrency($totGrossProfit) ?></div>
        </div>
    </div>
</div>
<div class="kpi-grid" style="margin-bottom:1.25rem;grid-template-columns:repeat(3,1fr)">
    <div class="kpi-card kpi-red">
        <div class="kpi-icon">💸</div>
        <div class="kpi-info"><div class="kpi-label">Operating Expenses</div><div class="kpi-value"><?= formatCurrency($totExpenses) ?></div></div>
    </div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-icon">🏦</div>
        <div class="kpi-info">
            <div class="kpi-label">Net Profit</div>
            <div class="kpi-value" style="color:<?= $totNetProfit >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= formatCurrency($totNetProfit) ?></div>
        </div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-icon">%</div>
        <div class="kpi-info"><div class="kpi-label">Net Margin</div><div class="kpi-value"><?= number_format($netMargin, 1) ?>%</div></div>
    </div>
</div>

<!-- Chart -->
<div class="card" style="margin-bottom:1.25rem">
    <div class="card-header"><div class="card-title">💰 Revenue vs COGS vs Profit</div></div>
    <div class="card-body">
        <div class="chart-container" style="height:300px">
            <?php if (empty($data)): ?>
            <div class="empty-state"><div class="icon">📊</div><p>No sales data for selected period.</p></div>
            <?php else: ?>
            <canvas id="profitChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Breakdown Table -->
<?php if (!empty($labels)): ?>
<div class="card">
    <div class="card-header"><div class="card-title">Period Breakdown</div></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">COGS</th>
                    <th class="text-right">Gross Profit</th>
                    <th class="text-right">Expenses</th>
                    <th class="text-right">Net Profit</th>
                    <th class="text-right">Margin</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($labels as $i => $lbl):
                $gp  = $revenue[$i] - $cogs[$i];
                $gm  = $revenue[$i] > 0 ? $gp / $revenue[$i] * 100 : 0;
                $exp = $expenses[$i];
                $np  = $netProfit[$i];
            ?>
            <tr>
                <td class="fw-bold"><?= e($lbl) ?></td>
                <td class="text-right"><?= formatCurrency($revenue[$i]) ?></td>
                <td class="text-right text-muted"><?= formatCurrency($cogs[$i]) ?></td>
                <td class="text-right fw-bold" style="color:<?= $gp >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= formatCurrency($gp) ?></td>
                <td class="text-right text-muted"><?= formatCurrency($exp) ?></td>
                <td class="text-right fw-bold" style="color:<?= $np >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= formatCurrency($np) ?></td>
                <td class="text-right"><?= number_format($gm, 1) ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;background:#F9FAFB">
                    <td>TOTAL</td>
                    <td class="text-right"><?= formatCurrency($totRev) ?></td>
                    <td class="text-right text-muted"><?= formatCurrency($totCogs) ?></td>
                    <td class="text-right" style="color:<?= $totGrossProfit >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= formatCurrency($totGrossProfit) ?></td>
                    <td class="text-right text-muted"><?= formatCurrency($totExpenses) ?></td>
                    <td class="text-right" style="color:<?= $totNetProfit >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= formatCurrency($totNetProfit) ?></td>
                    <td class="text-right"><?= number_format($netMargin, 1) ?>%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
