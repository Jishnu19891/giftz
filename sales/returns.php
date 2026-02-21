<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

$dateFrom = $_GET['from']   ?? date('Y-m-01');
$dateTo   = $_GET['to']     ?? date('Y-m-d');
$search   = trim($_GET['search'] ?? '');
$page     = max(1, intval($_GET['page'] ?? 1));

$where  = ['sr.created_at >= ?', 'sr.created_at <= DATE_ADD(?, INTERVAL 1 DAY)'];
$params = [$dateFrom, $dateTo];

if ($search) {
    $where[]  = '(sr.return_no LIKE ? OR s.invoice_no LIKE ? OR c.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereStr = implode(' AND ', $where);

// ── Summary KPIs ────────────────────────────────────────────
$summaryStmt = $pdo->prepare("
    SELECT COUNT(*)                        AS return_count,
           COALESCE(SUM(sr.total_refund), 0) AS total_refunded
    FROM sale_returns sr
    JOIN  sales s ON s.id = sr.sale_id
    LEFT JOIN customers c ON c.id = s.customer_id
    WHERE $whereStr
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

// ── Paginated list ───────────────────────────────────────────
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM sale_returns sr
    JOIN  sales s ON s.id = sr.sale_id
    LEFT JOIN customers c ON c.id = s.customer_id
    WHERE $whereStr
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pg    = paginate($total, $page);

$listStmt = $pdo->prepare("
    SELECT sr.id, sr.return_no, sr.total_refund, sr.reason, sr.created_at,
           s.id AS sale_id, s.invoice_no,
           COALESCE(c.name, 'Walk-in') AS customer_name,
           u.name AS processed_by,
           (SELECT COUNT(*) FROM sale_return_items WHERE return_id = sr.id) AS item_count,
           (SELECT SUM(quantity) FROM sale_return_items WHERE return_id = sr.id) AS unit_count
    FROM sale_returns sr
    JOIN  sales s       ON s.id  = sr.sale_id
    LEFT JOIN customers c ON c.id = s.customer_id
    LEFT JOIN users u     ON u.id = sr.created_by
    WHERE $whereStr
    ORDER BY sr.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$listStmt->execute($params);
$returns = $listStmt->fetchAll();

$baseQuery = http_build_query(['from' => $dateFrom, 'to' => $dateTo, 'search' => $search]);

$pageTitle   = 'Returns';
$breadcrumbs = ['Sales' => '', 'Returns' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">↩</span> Returns</h1>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="margin-bottom:1.25rem;grid-template-columns:repeat(3,1fr)">
    <div class="kpi-card kpi-pink">
        <div class="kpi-icon">↩</div>
        <div class="kpi-info">
            <div class="kpi-label">Returns</div>
            <div class="kpi-value"><?= number_format($summary['return_count']) ?></div>
            <div class="kpi-sub">in period</div>
        </div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon">💸</div>
        <div class="kpi-info">
            <div class="kpi-label">Total Refunded</div>
            <div class="kpi-value"><?= formatCurrency($summary['total_refunded']) ?></div>
            <div class="kpi-sub">in period</div>
        </div>
    </div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-icon">📊</div>
        <div class="kpi-info">
            <div class="kpi-label">Avg. Refund</div>
            <div class="kpi-value">
                <?= $summary['return_count'] > 0
                    ? formatCurrency($summary['total_refunded'] / $summary['return_count'])
                    : formatCurrency(0) ?>
            </div>
            <div class="kpi-sub">per return</div>
        </div>
    </div>
</div>

<!-- Filters + table -->
<div class="card">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="<?= e($dateFrom) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="<?= e($dateTo) ?>">
        </div>
        <div class="form-group" style="flex:1">
            <label class="form-label">Search</label>
            <div class="input-icon-wrap">
                <span class="icon">🔍</span>
                <input type="text" name="search" class="form-control"
                       placeholder="Return no, invoice, or customer..."
                       value="<?= e($search) ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">&nbsp;</label>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= BASE_URL ?>/sales/returns.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Return No</th>
                    <th>Original Invoice</th>
                    <th>Customer</th>
                    <th class="text-center">Items</th>
                    <th class="text-center">Units</th>
                    <th>Reason</th>
                    <th>Processed By</th>
                    <th class="text-right">Refund</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($returns)): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <div class="icon">↩</div>
                            <h3>No returns found</h3>
                            <p>Adjust the date range or filters.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($returns as $r): ?>
                <tr>
                    <td class="fw-bold" style="color:var(--danger)"><?= e($r['return_no']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $r['sale_id'] ?>"
                           style="color:var(--accent);font-weight:600">
                            <?= e($r['invoice_no']) ?>
                        </a>
                    </td>
                    <td><?= e($r['customer_name']) ?></td>
                    <td class="text-center">
                        <span class="badge badge-secondary"><?= $r['item_count'] ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-warning"><?= $r['unit_count'] ?></span>
                    </td>
                    <td class="text-muted fs-sm">
                        <?php if ($r['reason']): ?>
                            <?= e(mb_strimwidth($r['reason'], 0, 45, '…')) ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= e($r['processed_by'] ?? '—') ?></td>
                    <td class="text-right fw-bold" style="color:var(--danger)">
                        <?= formatCurrency($r['total_refund']) ?>
                    </td>
                    <td class="text-muted fs-sm"><?= formatDateTime($r['created_at']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $r['sale_id'] ?>"
                           class="btn btn-sm btn-secondary" target="_blank">🖨 Invoice</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pg['total_pages'] > 1): ?>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);
                display:flex;align-items:center;justify-content:space-between">
        <div class="text-muted fs-sm">
            Showing <?= $pg['offset'] + 1 ?>–<?= min($pg['offset'] + $pg['per_page'], $total) ?>
            of <?= number_format($total) ?>
        </div>
        <?= paginationLinks($pg, BASE_URL . '/sales/returns.php?' . $baseQuery) ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
