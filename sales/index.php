<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

$dateFrom = $_GET['from']   ?? date('Y-m-01');
$dateTo   = $_GET['to']     ?? date('Y-m-d');
$status   = $_GET['status'] ?? '';
$search   = trim($_GET['search'] ?? '');
$page     = max(1, intval($_GET['page'] ?? 1));

$where  = ['s.created_at >= ?', 's.created_at <= DATE_ADD(?, INTERVAL 1 DAY)'];
$params = [$dateFrom, $dateTo];

if ($status) { $where[] = 's.status=?'; $params[] = $status; }
if ($search) { $where[] = '(s.invoice_no LIKE ? OR c.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pg    = paginate($total, $page);

$stmt = $pdo->prepare("
    SELECT s.id, s.invoice_no, s.subtotal, s.discount, s.total, s.payment_method, s.status, s.created_at,
           COALESCE(c.name,'Walk-in') as customer_name, u.name as cashier_name
    FROM sales s
    LEFT JOIN customers c ON c.id=s.customer_id
    LEFT JOIN users u ON u.id=s.created_by
    WHERE $whereStr ORDER BY s.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Summary
$totStmt = $pdo->prepare("
    SELECT COALESCE(SUM(total),0) as revenue, COUNT(*) as cnt
    FROM sales s LEFT JOIN customers c ON c.id=s.customer_id
    WHERE $whereStr AND s.status='completed'
");
$totStmt->execute($params);
$summary = $totStmt->fetch();

$pageTitle   = 'Sales History';
$breadcrumbs = ['Sales History' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">🧾</span> Sales History</h1>
    <a href="<?= BASE_URL ?>/sales/pos.php" class="btn btn-primary">🛒 New Sale</a>
</div>

<!-- Summary -->
<div class="kpi-grid" style="margin-bottom:1.25rem;grid-template-columns:repeat(3,1fr)">
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon">💰</div>
        <div class="kpi-info"><div class="kpi-label">Period Revenue</div><div class="kpi-value"><?= formatCurrency($summary['revenue']) ?></div></div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-icon">🧾</div>
        <div class="kpi-info"><div class="kpi-label">Completed Sales</div><div class="kpi-value"><?= $summary['cnt'] ?></div></div>
    </div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-icon">📊</div>
        <div class="kpi-info">
            <div class="kpi-label">Avg. per Sale</div>
            <div class="kpi-value"><?= $summary['cnt'] > 0 ? formatCurrency($summary['revenue'] / $summary['cnt']) : formatCurrency(0) ?></div>
        </div>
    </div>
</div>

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
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">All</option>
                <option value="completed" <?= $status==='completed'?'selected':''?>>Completed</option>
                <option value="voided"    <?= $status==='voided'   ?'selected':''?>>Voided</option>
            </select>
        </div>
        <div class="form-group" style="flex:1">
            <label class="form-label">Search</label>
            <div class="input-icon-wrap">
                <span class="icon">🔍</span>
                <input type="text" name="search" class="form-control" placeholder="Invoice or customer..." value="<?= e($search) ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">&nbsp;</label>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= BASE_URL ?>/sales/index.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Cashier</th>
                    <th>Payment</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">Discount</th>
                    <th class="text-right">Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($sales)): ?>
                <tr><td colspan="10"><div class="empty-state"><div class="icon">🧾</div><h3>No sales found</h3><p>Adjust the date range or filters.</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($sales as $s): ?>
                <tr>
                    <td><a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $s['id'] ?>" class="fw-bold" style="color:var(--accent)"><?= e($s['invoice_no']) ?></a></td>
                    <td><?= e($s['customer_name']) ?></td>
                    <td class="text-muted"><?= e($s['cashier_name'] ?? '—') ?></td>
                    <td><span class="badge badge-info"><?= e(strtoupper($s['payment_method'])) ?></span></td>
                    <td class="text-right"><?= formatCurrency($s['subtotal']) ?></td>
                    <td class="text-right text-muted"><?= $s['discount'] > 0 ? '−' . formatCurrency($s['discount']) : '—' ?></td>
                    <td class="text-right fw-bold"><?= formatCurrency($s['total']) ?></td>
                    <td><?= statusBadge($s['status']) ?></td>
                    <td class="text-muted fs-sm"><?= formatDateTime($s['created_at']) ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-secondary" target="_blank">🖨 View</a>
                            <?php if ($s['status'] === 'completed'): ?>
                            <a href="<?= BASE_URL ?>/sales/return.php?sale_id=<?= $s['id'] ?>"
                               class="btn btn-sm btn-warning">↩ Return</a>
                            <?php endif; ?>
                            <?php if ($s['status'] === 'completed' && isAdmin()): ?>
                            <form method="POST" action="<?= BASE_URL ?>/sales/void.php" style="display:inline"
                                  onsubmit="return confirm('Void sale <?= e($s['invoice_no']) ?>? Stock will be restored.')">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Void</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pg['total_pages'] > 1): ?>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <div class="text-muted fs-sm">Showing <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'],$total) ?> of <?= $total ?></div>
        <?= paginationLinks($pg, BASE_URL . '/sales/index.php?from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo) . '&status=' . urlencode($status) . '&search=' . urlencode($search)) ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
