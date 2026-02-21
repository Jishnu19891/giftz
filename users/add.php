<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
requireRole('admin');

$pdo    = db();
$errors = [];

$editId  = intval($_GET['id'] ?? 0);
$user    = null;
$isEdit  = false;

if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$editId]);
    $user = $stmt->fetch();
    if ($user) $isEdit = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formId   = intval($_POST['id']     ?? 0);
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $role     = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';
    $status   = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
    $password = $_POST['password']         ?? '';
    $passConf = $_POST['password_confirm'] ?? '';

    if (empty($name))  $errors[] = 'Full name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    // Email uniqueness
    if ($email) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=? AND id!=?');
        $chk->execute([$email, $formId]);
        if ($chk->fetchColumn() > 0) $errors[] = 'Email address already in use.';
    }

    if (!$formId && empty($password)) $errors[] = 'Password is required for new users.';
    if ($password && $password !== $passConf) $errors[] = 'Passwords do not match.';
    if ($password && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors)) {
        if ($formId) {
            if ($password) {
                $pdo->prepare('UPDATE users SET name=?,email=?,role=?,status=?,password=?,updated_at=NOW() WHERE id=?')
                    ->execute([$name, $email, $role, $status, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $formId]);
            } else {
                $pdo->prepare('UPDATE users SET name=?,email=?,role=?,status=?,updated_at=NOW() WHERE id=?')
                    ->execute([$name, $email, $role, $status, $formId]);
            }
            flash('success', 'User updated successfully.');
        } else {
            $pdo->prepare('INSERT INTO users (name,email,role,status,password,created_at) VALUES (?,?,?,?,?,NOW())')
                ->execute([$name, $email, $role, $status, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);
            flash('success', "User \"$name\" created.");
        }
        header('Location: ' . BASE_URL . '/users/index.php');
        exit;
    }
}

$pageTitle   = $isEdit ? 'Edit User' : 'Add User';
$breadcrumbs = ['Admin' => '', 'Users' => BASE_URL . '/users/index.php', $pageTitle => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon"><?= $isEdit ? '✏' : '+' ?></span> <?= $pageTitle ?></h1>
    <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-secondary">← Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul style="margin:0 0 0 1rem"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card" style="max-width:600px">
    <div class="card-header"><div class="card-title"><?= $pageTitle ?></div></div>
    <div class="card-body">
        <form method="POST">
            <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $user['id'] ?>">
            <?php endif; ?>
            <div class="form-grid form-grid-2">
                <div class="form-group full-width">
                    <label class="form-label required">Full Name</label>
                    <input type="text" name="name" class="form-control"
                           value="<?= e($user['name'] ?? $_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group full-width">
                    <label class="form-label required">Email Address</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= e($user['email'] ?? $_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">Password <?= $isEdit ? '<span class="text-muted" style="font-weight:400">(blank = keep current)</span>' : '' ?></label>
                    <input type="password" name="password" class="form-control" <?= !$isEdit ? 'required' : '' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirm" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="staff" <?= ($user['role'] ?? 'staff') === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active"   <?= ($user['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-actions" style="margin-top:1.25rem">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Save Changes' : 'Create User' ?>
                </button>
                <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
