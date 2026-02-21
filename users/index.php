<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
requireRole('admin');

$pdo  = db();
$self = currentUser()['id'];

// Toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = intval($_POST['id'] ?? 0);
    if ($id === $self) {
        flash('error', 'You cannot deactivate your own account.');
    } else {
        $pdo->prepare('UPDATE users SET status=IF(status="active","inactive","active") WHERE id=?')->execute([$id]);
        flash('success', 'User status updated.');
    }
    header('Location: ' . BASE_URL . '/users/index.php');
    exit;
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id === $self) {
        flash('error', 'You cannot delete your own account.');
    } else {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        flash('success', 'User deleted.');
    }
    header('Location: ' . BASE_URL . '/users/index.php');
    exit;
}

$users = $pdo->query('SELECT * FROM users ORDER BY role ASC, name ASC')->fetchAll();

$pageTitle   = 'User Management';
$breadcrumbs = ['Admin' => '', 'Users' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">👤</span> Users</h1>
    <a href="<?= BASE_URL ?>/users/add.php" class="btn btn-primary">+ Add User</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td class="text-muted fs-sm"><?= $u['id'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:.65rem">
                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--secondary));display:grid;place-items:center;color:#fff;font-size:.82rem;font-weight:700;flex-shrink:0">
                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                        </div>
                        <strong><?= e($u['name']) ?></strong>
                    </div>
                </td>
                <td><?= e($u['email']) ?></td>
                <td><?= statusBadge($u['role']) ?></td>
                <td><?= statusBadge($u['status']) ?></td>
                <td class="text-muted fs-sm"><?= $u['last_login'] ? formatDateTime($u['last_login']) : 'Never' ?></td>
                <td class="text-right">
                    <div class="btn-group" style="justify-content:flex-end">
                        <a href="<?= BASE_URL ?>/users/add.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-secondary">✏ Edit</a>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Toggle status for <?= e(addslashes($u['name'])) ?>?')">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-warning" title="Toggle status">
                                <?= $u['status'] === 'active' ? '⊘' : '✓' ?>
                            </button>
                        </form>
                        <?php if ($u['id'] !== $self): ?>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Delete user \'<?= e(addslashes($u['name'])) ?>\'? This cannot be undone.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
