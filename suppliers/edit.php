<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();
$id  = intval($_GET['id'] ?? 0);

$supplierStmt = $pdo->prepare('SELECT * FROM suppliers WHERE id=?');
$supplierStmt->execute([$id]);
$supplier = $supplierStmt->fetch();

if (!$supplier) {
    flash('error', 'Supplier not found.');
    header('Location: ' . BASE_URL . '/suppliers/index.php');
    exit;
}

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
        $pdo->prepare('UPDATE suppliers SET name=?,contact_person=?,phone=?,email=?,address=?,updated_at=NOW() WHERE id=?')
            ->execute([$name, $contact ?: null, $phone ?: null, $email ?: null, $address ?: null, $id]);
        flash('success', 'Supplier updated.');
        header('Location: ' . BASE_URL . '/suppliers/index.php');
        exit;
    }

    $supplier = array_merge($supplier, compact('name','contact','phone','email','address'));
}

$pageTitle   = 'Edit Supplier';
$breadcrumbs = ['Suppliers' => BASE_URL . '/suppliers/index.php', 'Edit' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">✏</span> Edit Supplier</h1>
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
                    <input type="text" name="name" class="form-control" value="<?= e($supplier['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?= e($supplier['contact_person'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($supplier['phone'] ?? '') ?>">
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($supplier['email'] ?? '') ?>">
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control"><?= e($supplier['address'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions" style="margin-top:1.25rem">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="<?= BASE_URL ?>/suppliers/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
