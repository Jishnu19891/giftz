<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo    = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']           ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $phone   = trim($_POST['phone']          ?? '');
    $email   = trim($_POST['email']          ?? '');
    $address = trim($_POST['address']        ?? '');

    if (empty($name)) $errors[] = 'Supplier name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    if (empty($errors)) {
        $pdo->prepare('INSERT INTO suppliers (name,contact_person,phone,email,address) VALUES (?,?,?,?,?)')
            ->execute([$name, $contact ?: null, $phone ?: null, $email ?: null, $address ?: null]);
        flash('success', "Supplier \"$name\" added.");
        header('Location: ' . BASE_URL . '/suppliers/index.php');
        exit;
    }
}

$pageTitle   = 'Add Supplier';
$breadcrumbs = ['Suppliers' => BASE_URL . '/suppliers/index.php', 'Add Supplier' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">+</span> Add Supplier</h1>
    <a href="<?= BASE_URL ?>/suppliers/index.php" class="btn btn-secondary">← Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul style="margin:0 0 0 1rem"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card" style="max-width:700px">
    <div class="card-header"><div class="card-title">Supplier Information</div></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid form-grid-2">
                <div class="form-group full-width">
                    <label class="form-label required">Supplier Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?= e($_POST['contact_person'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control"><?= e($_POST['address'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions" style="margin-top:1.25rem">
                <button type="submit" class="btn btn-primary">Save Supplier</button>
                <a href="<?= BASE_URL ?>/suppliers/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
