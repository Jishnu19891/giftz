<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

// ── Date filter ──────────────────────────────────────────────────────────────
$range  = $_GET['range'] ?? '7';          // 7 | 30 | 90 | all
$pageF  = $_GET['pf']   ?? '';            // catalog | product | ''
$validR = ['7', '30', '90', 'all'];
if (!in_array($range, $validR, true)) $range = '7';

$dateWhere = $range === 'all' ? '1=1' : "created_at >= DATE_SUB(NOW(), INTERVAL {$range} DAY)";
$pageWhere = $pageF ? "AND page = " . $pdo->quote($pageF) : '';

// ── KPI counts ───────────────────────────────────────────────────────────────
$todayVisitors   = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitor_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$weekVisitors    = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitor_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$monthVisitors   = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitor_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$totalPageviews  = (int)$pdo->query("SELECT COUNT(*) FROM visitor_logs")->fetchColumn();

// ── Trend: unique IPs per day for selected range ──────────────────────────────
$trendDays = $range === 'all' ? 30 : (int)$range;
$trendRows = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%b %d') as label,
           COUNT(DISTINCT ip_hash)          as visitors,
           COUNT(*)                          as pageviews
    FROM visitor_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$trendDays} DAY)
    GROUP BY DATE(created_at), label
    ORDER BY DATE(created_at) ASC
")->fetchAll();
$trendLabels    = array_column($trendRows, 'label');
$trendVisitors  = array_map('intval', array_column($trendRows, 'visitors'));
$trendPageviews = array_map('intval', array_column($trendRows, 'pageviews'));

// ── Page breakdown ────────────────────────────────────────────────────────────
$pageBreak = $pdo->query("
    SELECT page, COUNT(*) as hits, COUNT(DISTINCT ip_hash) as uniq
    FROM visitor_logs
    WHERE {$dateWhere}
    GROUP BY page ORDER BY hits DESC
")->fetchAll();

// ── Device breakdown ──────────────────────────────────────────────────────────
$deviceBreak = $pdo->query("
    SELECT device, COUNT(*) as cnt
    FROM visitor_logs
    WHERE {$dateWhere}
    GROUP BY device ORDER BY cnt DESC
")->fetchAll();
$deviceLabels = array_column($deviceBreak, 'device');
$deviceCounts = array_map('intval', array_column($deviceBreak, 'cnt'));

// ── Top products viewed ───────────────────────────────────────────────────────
$topProducts = $pdo->query("
    SELECT v.page_id, p.name, p.sku, COUNT(*) as views
    FROM visitor_logs v
    LEFT JOIN products p ON p.id = v.page_id
    WHERE v.page = 'product' AND {$dateWhere} AND v.page_id IS NOT NULL
    GROUP BY v.page_id, p.name, p.sku
    ORDER BY views DESC
    LIMIT 10
")->fetchAll();

// ── Recent visits (paginated) ─────────────────────────────────────────────────
$pg      = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$total   = (int)$pdo->query("SELECT COUNT(*) FROM visitor_logs WHERE {$dateWhere} {$pageWhere}")->fetchColumn();
$pagi    = paginate($total, $pg, $perPage);

$recentVisits = $pdo->query("
    SELECT id, page, page_id, referrer, device, created_at
    FROM visitor_logs
    WHERE {$dateWhere} {$pageWhere}
    ORDER BY created_at DESC
    LIMIT {$pagi['per_page']} OFFSET {$pagi['offset']}
")->fetchAll();

// ── Build query string for pagination links ───────────────────────────────────
$qs = '?range=' . urlencode($range) . '&pf=' . urlencode($pageF) . '&page=';

$pageTitle   = 'Visitor Analytics';
$breadcrumbs = ['Visitor Analytics' => ''];

$inlineJs = "
document.addEventListener('DOMContentLoaded', () => {
    // Trend chart
    const ctx1 = document.getElementById('trendChart').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: " . json_encode($trendLabels) . ",
            datasets: [
                {
                    label: 'Unique Visitors',
                    data:  " . json_encode($trendVisitors) . ",
                    borderColor: '#6C63FF',
                    backgroundColor: 'rgba(108,99,255,.12)',
                    fill: true, tension: .35, pointRadius: 3
                },
                {
                    label: 'Page Views',
                    data:  " . json_encode($trendPageviews) . ",
                    borderColor: '#F472B6',
                    backgroundColor: 'rgba(244,114,182,.08)',
                    fill: true, tension: .35, pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Device doughnut
    const ctx2 = document.getElementById('deviceChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: " . json_encode($deviceLabels) . ",
            datasets: [{ data: " . json_encode($deviceCounts) . ",
                backgroundColor: ['#6C63FF','#F472B6','#34D399','#FBBF24'] }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
";

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <span class="title-icon">👁</span> Visitor Analytics
    </h1>
    <!-- Range filter -->
    <div class="page-actions">
        <?php foreach (['7' => 'Last 7 days', '30' => '30 days', '90' => '90 days', 'all' => 'All time'] as $v => $l): ?>
        <a href="?range=<?= $v ?>&pf=<?= urlencode($pageF) ?>"
           class="btn <?= $range === $v ? 'btn-primary' : 'btn-secondary' ?>">
            <?= $l ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- KPI row -->
<div class="kpi-grid">
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon">👁</div>
        <div class="kpi-info">
            <div class="kpi-label">Today's Visitors</div>
            <div class="kpi-value"><?= number_format($todayVisitors) ?></div>
            <div class="kpi-sub">Unique IPs</div>
        </div>
    </div>
    <div class="kpi-card kpi-pink">
        <div class="kpi-icon">📅</div>
        <div class="kpi-info">
            <div class="kpi-label">Last 7 Days</div>
            <div class="kpi-value"><?= number_format($weekVisitors) ?></div>
            <div class="kpi-sub">Unique IPs</div>
        </div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-icon">🗓</div>
        <div class="kpi-info">
            <div class="kpi-label">Last 30 Days</div>
            <div class="kpi-value"><?= number_format($monthVisitors) ?></div>
            <div class="kpi-sub">Unique IPs</div>
        </div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon">📊</div>
        <div class="kpi-info">
            <div class="kpi-label">Total Page Views</div>
            <div class="kpi-value"><?= number_format($totalPageviews) ?></div>
            <div class="kpi-sub">All time</div>
        </div>
    </div>
</div>

<!-- Trend + Device charts -->
<div class="charts-grid">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📈 Visitor Trend</div>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height:260px">
                <?php if (empty($trendRows)): ?>
                <div class="empty-state" style="padding:3rem 1rem">
                    <div class="icon">👁</div>
                    <p>No visitor data yet for this period.</p>
                </div>
                <?php else: ?>
                <canvas id="trendChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">📱 Device Breakdown</div>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height:260px">
                <?php if (empty($deviceBreak)): ?>
                <div class="empty-state" style="padding:3rem 1rem">
                    <div class="icon">📱</div>
                    <p>No device data yet.</p>
                </div>
                <?php else: ?>
                <canvas id="deviceChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Page breakdown + Top products -->
<div style="display:grid;grid-template-columns:1fr 1.2fr;gap:1.25rem;margin-bottom:1.25rem">

    <!-- Page breakdown -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">📄 Hits by Page</div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Page</th><th class="text-center">Page Views</th><th class="text-center">Unique Visitors</th></tr>
                </thead>
                <tbody>
                <?php if (empty($pageBreak)): ?>
                    <tr><td colspan="3" class="text-center text-muted" style="padding:2rem">No data</td></tr>
                <?php else: ?>
                    <?php foreach ($pageBreak as $row): ?>
                    <tr>
                        <td>
                            <a href="?range=<?= $range ?>&pf=<?= urlencode($row['page']) ?>" class="fw-bold">
                                <?= ucfirst(e($row['page'])) ?>
                            </a>
                        </td>
                        <td class="text-center"><?= number_format($row['hits']) ?></td>
                        <td class="text-center"><?= number_format($row['uniq']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top viewed products -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">🔥 Top Viewed Products</div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Product</th><th>SKU</th><th class="text-center">Views</th></tr>
                </thead>
                <tbody>
                <?php if (empty($topProducts)): ?>
                    <tr><td colspan="3" class="text-center text-muted" style="padding:2rem">No product views yet</td></tr>
                <?php else: ?>
                    <?php foreach ($topProducts as $tp): ?>
                    <tr>
                        <td>
                            <a href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$tp['page_id'] ?>" target="_blank">
                                <?= e($tp['name'] ?? 'Deleted product') ?>
                            </a>
                        </td>
                        <td class="text-muted fs-sm"><?= e($tp['sku'] ?? '—') ?></td>
                        <td class="text-center fw-bold"><?= number_format($tp['views']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Recent visits log -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            🕒 Recent Visits
            <?php if ($pageF): ?>
            — <span class="badge badge-info"><?= ucfirst(e($pageF)) ?></span>
            <a href="?range=<?= $range ?>" class="text-muted fs-sm" style="margin-left:.5rem">clear filter</a>
            <?php endif; ?>
        </div>
        <span class="text-muted fs-sm"><?= number_format($total) ?> record<?= $total != 1 ? 's' : '' ?></span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Page</th>
                    <th>Device</th>
                    <th>Referrer</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recentVisits)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:2rem">No visits recorded yet</td></tr>
            <?php else: ?>
                <?php foreach ($recentVisits as $v): ?>
                <tr>
                    <td class="text-muted fs-sm"><?= $v['id'] ?></td>
                    <td>
                        <?php
                            $pageLabel = ucfirst($v['page']);
                            if ($v['page'] === 'product' && $v['page_id']) {
                                $pageLabel .= ' #' . $v['page_id'];
                            }
                        ?>
                        <span class="badge <?= $v['page'] === 'product' ? 'badge-purple' : 'badge-info' ?>">
                            <?= e($pageLabel) ?>
                        </span>
                    </td>
                    <td>
                        <?php $icons = ['desktop'=>'🖥','mobile'=>'📱','tablet'=>'📲','bot'=>'🤖']; ?>
                        <?= $icons[$v['device']] ?? '?' ?> <?= e(ucfirst($v['device'])) ?>
                    </td>
                    <td class="text-muted fs-sm" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($v['referrer'] ?? '') ?>">
                        <?= $v['referrer'] ? e($v['referrer']) : '<span style="color:var(--text-muted)">—</span>' ?>
                    </td>
                    <td class="text-muted fs-sm"><?= formatDateTime($v['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagi['total_pages'] > 1): ?>
    <div class="card-footer" style="padding:.75rem 1rem">
        <?= paginationLinks($pagi, '?range=' . $range . '&pf=' . urlencode($pageF)) ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
