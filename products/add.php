<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo    = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']            ?? '');
    $catId    = intval($_POST['category_id']   ?? 0) ?: null;
    $type     = in_array($_POST['type'] ?? '', ['gift','cloth','both']) ? $_POST['type'] : 'gift';
    $costP    = floatval($_POST['cost_price']   ?? 0);
    $sellP    = floatval($_POST['selling_price']?? 0);
    $stockQty = intval($_POST['stock_qty']      ?? 0);
    $minStock = intval($_POST['min_stock_level']?? 5);
    $size     = trim($_POST['size']    ?? '');
    $color    = trim($_POST['color']   ?? '');
    $occasion = trim($_POST['occasion']?? '');
    $status   = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    if (empty($name))  $errors[] = 'Product name is required.';
    if ($sellP <= 0)   $errors[] = 'Selling price must be greater than 0.';

    // Generate unique SKU
    $catCode = 'GEN';
    if ($catId) {
        $catRow = $pdo->prepare('SELECT name FROM categories WHERE id=?');
        $catRow->execute([$catId]);
        $cname   = $catRow->fetchColumn();
        $catCode = $cname ? strtoupper(substr($cname, 0, 3)) : 'GEN';
    }
    do {
        $sku = generateSKU($catCode, $type);
        $chk = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku=?');
        $chk->execute([$sku]);
    } while ($chk->fetchColumn() > 0);

    // Image
    $imageName = null;
    if (!empty($_FILES['image']['name'])) {
        $imageName = uploadImage($_FILES['image']);
        if (!$imageName) $errors[] = 'Invalid image. Allowed: JPG, PNG, GIF, WebP.';
    }

    if (empty($errors)) {
        $pdo->prepare('
            INSERT INTO products (sku,name,category_id,type,cost_price,selling_price,stock_qty,min_stock_level,size,color,occasion,image,status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ')->execute([$sku, $name, $catId, $type, $costP, $sellP, $stockQty, $minStock, $size, $color, $occasion, $imageName, $status]);

        $newId = (int)$pdo->lastInsertId();
        if ($stockQty > 0) {
            updateStock($newId, $stockQty, 'in', 'Initial stock', currentUser()['id']);
        }

        flash('success', "Product \"$name\" added with SKU $sku.");
        header('Location: ' . BASE_URL . '/products/index.php');
        exit;
    }
}

$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$pageTitle   = 'Add Product';
$breadcrumbs = ['Products' => BASE_URL . '/products/index.php', 'Add Product' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">+</span> Add Product</h1>
    <a href="<?= BASE_URL ?>/products/index.php" class="btn btn-secondary">← Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul style="margin:0 0 0 1rem">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start">

    <div class="card">
        <div class="card-header"><div class="card-title">Product Information</div></div>
        <div class="card-body">
            <div class="form-grid form-grid-2">
                <div class="form-group full-width">
                    <label class="form-label required">Product Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">— Select Category —</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($_POST['category_id']??'')==$c['id']?'selected':''?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control">
                        <option value="gift"  <?= ($_POST['type']??'gift')==='gift' ?'selected':''?>>Gift</option>
                        <option value="cloth" <?= ($_POST['type']??'')==='cloth'?'selected':''?>>Cloth</option>
                        <option value="both"  <?= ($_POST['type']??'')==='both' ?'selected':''?>>Both</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Selling Price</label>
                    <input type="number" name="selling_price" class="form-control" step="0.01" min="0"
                           value="<?= e($_POST['selling_price'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Cost Price</label>
                    <input type="number" name="cost_price" class="form-control" step="0.01" min="0"
                           value="<?= e($_POST['cost_price'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Initial Stock Qty</label>
                    <input type="number" name="stock_qty" class="form-control" min="0"
                           value="<?= intval($_POST['stock_qty'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Min Stock Level</label>
                    <input type="number" name="min_stock_level" class="form-control" min="0"
                           value="<?= intval($_POST['min_stock_level'] ?? 5) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Size</label>
                    <input type="text" name="size" class="form-control" placeholder="S, M, L, XL or Free Size"
                           value="<?= e($_POST['size'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="text" name="color" class="form-control" placeholder="Red, Blue, Mixed..."
                           value="<?= e($_POST['color'] ?? '') ?>">
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Occasion</label>
                    <input type="text" name="occasion" class="form-control" placeholder="Birthday, Christmas, Valentine..."
                           value="<?= e($_POST['occasion'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:1.25rem">
        <div class="card">
            <div class="card-header"><div class="card-title">Product Image</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Upload Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*" data-preview="imgPreview">
                    <div class="form-text">JPG, PNG, GIF, WebP. Max 5MB.</div>
                    <img id="imgPreview" src="" alt="" class="img-preview" style="display:none;width:100%;height:140px;object-fit:cover;margin-top:.5rem">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">Status</div></div>
            <div class="card-body">
                <div class="form-group">
                    <select name="status" class="form-control">
                        <option value="active"   <?= ($_POST['status']??'active')==='active'  ?'selected':''?>>Active</option>
                        <option value="inactive" <?= ($_POST['status']??'')==='inactive'?'selected':''?>>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-actions" style="flex-direction:column">
            <button type="submit" class="btn btn-primary w-100">Save Product</button>
            <a href="<?= BASE_URL ?>/products/index.php" class="btn btn-secondary w-100">Cancel</a>
        </div>
    </div>

</div>
</form>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
