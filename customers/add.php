<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo    = db();
$errors = [];

// Check for edit mode
$editId   = intval($_GET['id'] ?? 0);
$customer = null;
$isEdit   = false;

if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id=?');
    $stmt->execute([$editId]);
    $customer = $stmt->fetch();
    if ($customer) $isEdit = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formId = intval($_POST['id']    ?? 0);
    $name   = trim($_POST['name']    ?? '');
    $phone  = trim($_POST['phone']   ?? '');
    $email  = trim($_POST['email']   ?? '');
    $addr   = trim($_POST['address'] ?? '');

    if (empty($name)) $errors[] = 'Customer name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    if (empty($errors)) {
        if ($formId) {
            $pdo->prepare('UPDATE customers SET name=?,phone=?,email=?,address=?,updated_at=NOW() WHERE id=?')
                ->execute([$name, $phone ?: null, $email ?: null, $addr ?: null, $formId]);
            flash('success', 'Customer updated successfully.');
        } else {
            $pdo->prepare('INSERT INTO customers (name,phone,email,address) VALUES (?,?,?,?)')
                ->execute([$name, $phone ?: null, $email ?: null, $addr ?: null]);
            flash('success', "Customer \"$name\" added.");
        }
        header('Location: ' . BASE_URL . '/customers/index.php');
        exit;
    }

    // Re-populate on error
    if ($isEdit) {
        $customer = array_merge($customer, compact('name','phone','email','addr'));
        $customer['address'] = $addr;
    }
}

$pageTitle   = $isEdit ? 'Edit Customer' : 'Add Customer';
$breadcrumbs = ['Customers' => BASE_URL . '/customers/index.php', $pageTitle => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon"><?= $isEdit ? '✏' : '+' ?></span> <?= $pageTitle ?></h1>
    <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-secondary">← Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul style="margin:0 0 0 1rem"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card" style="max-width:600px">
    <div class="card-header"><div class="card-title">Customer Details</div></div>
    <div class="card-body">
        <form method="POST">
            <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $customer['id'] ?>">
            <?php endif; ?>
            <div class="form-grid" style="grid-template-columns:1fr">
                <div class="form-group">
                    <label class="form-label required">Full Name</label>
                    <input type="text" name="name" class="form-control"
                           value="<?= e($customer['name'] ?? $_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= e($customer['phone'] ?? $_POST['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= e($customer['email'] ?? $_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control"><?= e($customer['address'] ?? $_POST['address'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions" style="margin-top:1.25rem">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Save Changes' : 'Add Customer' ?>
                </button>
                <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
