<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

$search = trim($_GET['search'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereStr  = implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers c WHERE $whereStr");
$countStmt->execute($params);
$total     = (int)$countStmt->fetchColumn();
$pg        = paginate($total, $page);

$stmt = $pdo->prepare("
    SELECT c.*,
           COUNT(s.id) as purchase_count,
           COALESCE(SUM(s.total),0) as total_spent
    FROM customers c
    LEFT JOIN sales s ON s.customer_id=c.id AND s.status='completed'
    WHERE $whereStr
    GROUP BY c.id
    ORDER BY total_spent DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle   = 'Customers';
$breadcrumbs = ['Customers' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">👥</span> Customers</h1>
    <a href="<?= BASE_URL ?>/customers/add.php" class="btn btn-primary">+ Add Customer</a>
</div>

<div class="card">
    <form method="GET" class="filter-bar">
        <div class="form-group" style="flex:1">
            <label class="form-label">Search</label>
            <div class="input-icon-wrap">
                <span class="icon">🔍</span>
                <input type="text" name="search" class="form-control" placeholder="Name, phone, email..." value="<?= e($search) ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">&nbsp;</label>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th class="text-center">Purchases</th>
                    <th class="text-right">Total Spent</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr><td colspan="7"><div class="empty-state"><div class="icon">👥</div><h3>No customers yet</h3><p><a href="<?= BASE_URL ?>/customers/add.php">Add first customer</a></p></div></td></tr>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td class="text-muted fs-sm"><?= $c['id'] ?></td>
                    <td>
                        <div class="fw-bold"><?= e($c['name']) ?></div>
                        <?php if ($c['address']): ?><div class="fs-sm text-muted"><?= e(substr($c['address'], 0, 40)) ?><?= strlen($c['address']) > 40 ? '…' : '' ?></div><?php endif; ?>
                    </td>
                    <td><?= e($c['phone'] ?? '—') ?></td>
                    <td><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '—' ?></td>
                    <td class="text-center"><span class="badge badge-info"><?= $c['purchase_count'] ?></span></td>
                    <td class="text-right fw-bold"><?= formatCurrency($c['total_spent']) ?></td>
                    <td class="text-right">
                        <div class="btn-group" style="justify-content:flex-end">
                            <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">👤 View</a>
                            <a href="<?= BASE_URL ?>/customers/add.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">✏ Edit</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pg['total_pages'] > 1): ?>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <div class="text-muted fs-sm">Showing <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'],$total) ?> of <?= $total ?></div>
        <?= paginationLinks($pg, BASE_URL . '/customers/index.php?search=' . urlencode($search)) ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
