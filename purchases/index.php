<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

// Mark received
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'receive') {
    $pid      = intval($_POST['id'] ?? 0);
    $purStmt  = $pdo->prepare('SELECT * FROM purchases WHERE id=?');
    $purStmt->execute([$pid]);
    $purchase = $purStmt->fetch();

    if ($purchase && $purchase['status'] === 'pending') {
        try {
            $pdo->beginTransaction();

            $itemsStmt = $pdo->prepare('SELECT * FROM purchase_items WHERE purchase_id=?');
            $itemsStmt->execute([$pid]);
            $userId = currentUser()['id'];

            foreach ($itemsStmt->fetchAll() as $item) {
                if ($item['product_id']) {
                    updateStock($item['product_id'], $item['quantity'], 'in', $purchase['reference_no'], $userId);
                }
            }

            $pdo->prepare("UPDATE purchases SET status='received', updated_at=NOW() WHERE id=?")->execute([$pid]);
            $pdo->commit();
            flash('success', 'PO ' . $purchase['reference_no'] . ' marked received. Stock updated.');

        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', 'Failed: ' . $e->getMessage());
        }
    } else {
        flash('error', 'Purchase not found or already processed.');
    }
    header('Location: ' . BASE_URL . '/purchases/index.php');
    exit;
}

$status = $_GET['status'] ?? '';
$page   = max(1, intval($_GET['page'] ?? 1));
$where  = ['1=1'];
$params = [];
if ($status) { $where[] = 'p.status=?'; $params[] = $status; }
$whereStr  = implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM purchases p WHERE $whereStr");
$countStmt->execute($params);
$total     = (int)$countStmt->fetchColumn();
$pg        = paginate($total, $page);

$stmt = $pdo->prepare("
    SELECT p.*, s.name as supplier_name
    FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id
    WHERE $whereStr ORDER BY p.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$purchases = $stmt->fetchAll();

$pageTitle   = 'Purchase Orders';
$breadcrumbs = ['Purchases' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">📋</span> Purchase Orders</h1>
    <a href="<?= BASE_URL ?>/purchases/add.php" class="btn btn-primary">+ New PO</a>
</div>

<!-- Status tabs -->
<div style="display:flex;gap:.4rem;margin-bottom:1rem;flex-wrap:wrap">
    <?php foreach (['' => 'All', 'pending' => 'Pending', 'received' => 'Received', 'partial' => 'Partial', 'cancelled' => 'Cancelled'] as $v => $l): ?>
    <a href="<?= BASE_URL ?>/purchases/index.php?status=<?= $v ?>"
       class="btn btn-sm <?= $status === $v ? 'btn-primary' : 'btn-secondary' ?>"><?= $l ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Reference No</th>
                    <th>Supplier</th>
                    <th>Date</th>
                    <th class="text-right">Total</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($purchases)): ?>
                <tr><td colspan="6"><div class="empty-state"><div class="icon">📋</div><h3>No purchase orders</h3><p><a href="<?= BASE_URL ?>/purchases/add.php">Create the first PO</a></p></div></td></tr>
            <?php else: ?>
                <?php foreach ($purchases as $p): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/purchases/view.php?id=<?= $p['id'] ?>" class="fw-bold" style="color:var(--accent)">
                            <?= e($p['reference_no']) ?>
                        </a>
                    </td>
                    <td><?= e($p['supplier_name'] ?? '—') ?></td>
                    <td><?= formatDate($p['purchase_date']) ?></td>
                    <td class="text-right fw-bold"><?= formatCurrency($p['total_amount']) ?></td>
                    <td><?= statusBadge($p['status']) ?></td>
                    <td class="text-right">
                        <div class="btn-group" style="justify-content:flex-end">
                            <a href="<?= BASE_URL ?>/purchases/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <?php if ($p['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Mark PO <?= e($p['reference_no']) ?> as received? This will update stock.')">
                                <input type="hidden" name="action" value="receive">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">✓ Receive</button>
                            </form>
                            <form method="POST" action="<?= BASE_URL ?>/purchases/cancel.php" style="display:inline"
                                  onsubmit="return confirm('Cancel PO <?= e($p['reference_no']) ?>? This cannot be undone.')">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">✕ Cancel</button>
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
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
