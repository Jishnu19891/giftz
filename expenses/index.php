<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

// ─── Filters ──────────────────────────────────────────────
$dateFrom  = $_GET['from']     ?? date('Y-m-01');
$dateTo    = $_GET['to']       ?? date('Y-m-d');
$catId     = intval($_GET['category'] ?? 0);
$payMethod = $_GET['payment']  ?? '';
$search    = trim($_GET['search'] ?? '');
$page      = max(1, intval($_GET['page'] ?? 1));

$where  = ['e.expense_date >= ?', 'e.expense_date <= ?'];
$params = [$dateFrom, $dateTo];

if ($catId)     { $where[] = 'e.category_id = ?';                                             $params[] = $catId; }
if ($payMethod) { $where[] = 'e.payment_method = ?';                                          $params[] = $payMethod; }
if ($search)    { $where[] = '(e.description LIKE ? OR e.paid_to LIKE ? OR e.reference_no LIKE ?)';
                  $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$whereStr = implode(' AND ', $where);

// ─── Pagination ───────────────────────────────────────────
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM expenses e WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pg    = paginate($total, $page);

// ─── Expense Rows ─────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT e.*, ec.name AS cat_name, ec.color AS cat_color, u.name AS user_name
    FROM expenses e
    LEFT JOIN expense_categories ec ON ec.id = e.category_id
    LEFT JOIN users u ON u.id = e.created_by
    WHERE $whereStr
    ORDER BY e.expense_date DESC, e.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// ─── KPIs ─────────────────────────────────────────────────
$kpiStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(e.amount), 0)                                                     AS total_amount,
        COUNT(*)                                                                        AS total_count,
        COALESCE(SUM(CASE WHEN e.expense_date = CURDATE() THEN e.amount ELSE 0 END),0) AS today_amount
    FROM expenses e WHERE $whereStr
");
$kpiStmt->execute($params);
$kpi = $kpiStmt->fetch();

$topCatStmt = $pdo->prepare("
    SELECT ec.name, COALESCE(SUM(e.amount),0) AS total
    FROM expenses e
    LEFT JOIN expense_categories ec ON ec.id = e.category_id
    WHERE $whereStr
    GROUP BY e.category_id ORDER BY total DESC LIMIT 1
");
$topCatStmt->execute($params);
$topCat = $topCatStmt->fetch();

// ─── Dropdown Data ────────────────────────────────────────
$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY sort_order, name")->fetchAll();

$pageTitle   = 'Expenses';
$breadcrumbs = ['Expenses' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">💸</span> Expenses</h1>
    <a href="<?= BASE_URL ?>/expenses/add.php" class="btn btn-primary">+ Add Expense</a>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="margin-bottom:1.25rem;grid-template-columns:repeat(4,1fr)">
    <div class="kpi-card kpi-red">
        <div class="kpi-icon">💸</div>
        <div class="kpi-info">
            <div class="kpi-label">Period Expenses</div>
            <div class="kpi-value"><?= formatCurrency($kpi['total_amount']) ?></div>
        </div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon">🧾</div>
        <div class="kpi-info">
            <div class="kpi-label">Expense Count</div>
            <div class="kpi-value"><?= $kpi['total_count'] ?></div>
        </div>
    </div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-icon">🏷</div>
        <div class="kpi-info">
            <div class="kpi-label">Top Category</div>
            <div class="kpi-value" style="font-size:1rem"><?= $topCat ? e($topCat['name']) : '—' ?></div>
        </div>
    </div>
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon">📅</div>
        <div class="kpi-info">
            <div class="kpi-label">Today's Expenses</div>
            <div class="kpi-value"><?= formatCurrency($kpi['today_amount']) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <!-- Filters -->
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
            <label class="form-label">Category</label>
            <select name="category" class="form-control">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $catId === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Payment</label>
            <select name="payment" class="form-control">
                <option value="">All</option>
                <?php foreach (['cash','card','gcash','maya','bank','transfer'] as $pm): ?>
                <option value="<?= $pm ?>" <?= $payMethod === $pm ? 'selected' : '' ?>><?= strtoupper($pm) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1">
            <label class="form-label">Search</label>
            <div class="input-icon-wrap">
                <span class="icon">🔍</span>
                <input type="text" name="search" class="form-control" placeholder="Description, paid to, ref…" value="<?= e($search) ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">&nbsp;</label>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= BASE_URL ?>/expenses/index.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <!-- Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Paid To</th>
                    <th>Payment</th>
                    <th class="text-right">Amount</th>
                    <th>By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($expenses)): ?>
                <tr><td colspan="9">
                    <div class="empty-state">
                        <div class="icon">💸</div>
                        <h3>No expenses found</h3>
                        <p>Adjust the filters or <a href="<?= BASE_URL ?>/expenses/add.php">add an expense</a>.</p>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($expenses as $exp): ?>
                <tr>
                    <td><span class="fw-bold" style="color:var(--danger)"><?= e($exp['reference_no']) ?></span></td>
                    <td class="text-muted fs-sm"><?= formatDate($exp['expense_date']) ?></td>
                    <td>
                        <?php if ($exp['cat_name']): ?>
                        <span class="badge <?= e($exp['cat_color']) ?>"><?= e($exp['cat_name']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($exp['description']) ?></td>
                    <td class="text-muted"><?= $exp['paid_to'] ? e($exp['paid_to']) : '—' ?></td>
                    <td><span class="badge badge-info"><?= strtoupper($exp['payment_method']) ?></span></td>
                    <td class="text-right fw-bold" style="color:var(--danger)"><?= formatCurrency($exp['amount']) ?></td>
                    <td class="text-muted fs-sm"><?= e($exp['user_name'] ?? '—') ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>/expenses/edit.php?id=<?= $exp['id'] ?>"
                               class="btn btn-sm btn-secondary">✏ Edit</a>
                            <?php if (isAdmin()): ?>
                            <form method="POST" action="<?= BASE_URL ?>/expenses/delete.php" style="display:inline"
                                  onsubmit="return confirm('Delete expense <?= e($exp['reference_no']) ?>? This cannot be undone.')">
                                <input type="hidden" name="id" value="<?= $exp['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">✕</button>
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
        <div class="text-muted fs-sm">
            Showing <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'],$total) ?> of <?= $total ?>
        </div>
        <?= paginationLinks($pg,
            BASE_URL . '/expenses/index.php?from=' . urlencode($dateFrom)
            . '&to='       . urlencode($dateTo)
            . '&category=' . $catId
            . '&payment='  . urlencode($payMethod)
            . '&search='   . urlencode($search)) ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
