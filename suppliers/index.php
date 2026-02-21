<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id   = intval($_POST['id'] ?? 0);
    $used = $pdo->prepare('SELECT COUNT(*) FROM purchases WHERE supplier_id=?');
    $used->execute([$id]);
    if ($used->fetchColumn() > 0) {
        flash('error', 'Cannot delete: supplier has purchase orders.');
    } else {
        $pdo->prepare('DELETE FROM suppliers WHERE id=?')->execute([$id]);
        flash('success', 'Supplier deleted.');
    }
    header('Location: ' . BASE_URL . '/suppliers/index.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(s.name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereStr  = implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers s WHERE $whereStr");
$countStmt->execute($params);
$total     = (int)$countStmt->fetchColumn();
$pg        = paginate($total, $page);

$stmt = $pdo->prepare("
    SELECT s.*, (SELECT COUNT(*) FROM purchases WHERE supplier_id=s.id) as po_count
    FROM suppliers s WHERE $whereStr
    ORDER BY s.name ASC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

$pageTitle   = 'Suppliers';
$breadcrumbs = ['Suppliers' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">🏭</span> Suppliers</h1>
    <a href="<?= BASE_URL ?>/suppliers/add.php" class="btn btn-primary">+ Add Supplier</a>
</div>

<div class="card">
    <form method="GET" class="filter-bar">
        <div class="form-group" style="flex:1">
            <label class="form-label">Search</label>
            <div class="input-icon-wrap">
                <span class="icon">🔍</span>
                <input type="text" name="search" class="form-control" placeholder="Name, contact, email..." value="<?= e($search) ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">&nbsp;</label>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="<?= BASE_URL ?>/suppliers/index.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Supplier Name</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th class="text-center">POs</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($suppliers)): ?>
                <tr><td colspan="7"><div class="empty-state"><div class="icon">🏭</div><h3>No suppliers yet</h3><p><a href="<?= BASE_URL ?>/suppliers/add.php">Add your first supplier</a></p></div></td></tr>
            <?php else: ?>
                <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td class="text-muted fs-sm"><?= $s['id'] ?></td>
                    <td><strong><?= e($s['name']) ?></strong></td>
                    <td><?= e($s['contact_person'] ?? '—') ?></td>
                    <td><?= e($s['phone'] ?? '—') ?></td>
                    <td><?= $s['email'] ? '<a href="mailto:'.e($s['email']).'">'.e($s['email']).'</a>' : '—' ?></td>
                    <td class="text-center"><span class="badge badge-secondary"><?= $s['po_count'] ?></span></td>
                    <td class="text-right">
                        <div class="btn-group" style="justify-content:flex-end">
                            <a href="<?= BASE_URL ?>/suppliers/edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-secondary">✏ Edit</a>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Delete supplier \'<?= e(addslashes($s['name'])) ?>\'?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                            </form>
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
