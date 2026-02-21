<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();
$id  = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Product not found.');
    header('Location: ' . BASE_URL . '/products/index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']             ?? '');
    $catId    = intval($_POST['category_id']    ?? 0) ?: null;
    $type     = in_array($_POST['type'] ?? '', ['gift','cloth','both']) ? $_POST['type'] : $product['type'];
    $costP    = floatval($_POST['cost_price']   ?? 0);
    $sellP    = floatval($_POST['selling_price']?? 0);
    $newQty   = intval($_POST['stock_qty']      ?? 0);
    $minStock = intval($_POST['min_stock_level']?? 5);
    $size     = trim($_POST['size']    ?? '');
    $color    = trim($_POST['color']   ?? '');
    $occasion = trim($_POST['occasion']?? '');
    $status   = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    if (empty($name)) $errors[] = 'Product name is required.';
    if ($sellP <= 0)  $errors[] = 'Selling price must be greater than 0.';

    // Image handling
    $imageName = $product['image'];
    if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        if ($imageName && file_exists(UPLOAD_PATH . '/' . $imageName)) unlink(UPLOAD_PATH . '/' . $imageName);
        $imageName = null;
    }
    if (!empty($_FILES['image']['name'])) {
        $uploaded = uploadImage($_FILES['image']);
        if (!$uploaded) {
            $errors[] = 'Invalid image file.';
        } else {
            if ($imageName && file_exists(UPLOAD_PATH . '/' . $imageName)) unlink(UPLOAD_PATH . '/' . $imageName);
            $imageName = $uploaded;
        }
    }

    if (empty($errors)) {
        $pdo->prepare('
            UPDATE products SET name=?,category_id=?,type=?,cost_price=?,selling_price=?,
            stock_qty=?,min_stock_level=?,size=?,color=?,occasion=?,image=?,status=?,updated_at=NOW()
            WHERE id=?
        ')->execute([$name, $catId, $type, $costP, $sellP, $newQty, $minStock, $size, $color, $occasion, $imageName, $status, $id]);

        // Log stock adjustment if qty changed
        $diff = $newQty - $product['stock_qty'];
        if ($diff !== 0) {
            $dir = $diff > 0 ? 'in' : 'out';
            $pdo->prepare('INSERT INTO stock_movements (product_id,type,quantity,reference,created_by,created_at) VALUES (?,?,?,?,?,NOW())')
                ->execute([$id, 'adjustment', abs($diff), 'Manual adjustment', currentUser()['id']]);
        }

        flash('success', 'Product updated successfully.');
        header('Location: ' . BASE_URL . '/products/index.php');
        exit;
    }

    // Re-merge for display
    $product = array_merge($product, compact('name','catId','type','costP','sellP','newQty','minStock','size','color','occasion','status'));
    $product['category_id']    = $catId;
    $product['cost_price']     = $costP;
    $product['selling_price']  = $sellP;
    $product['stock_qty']      = $newQty;
    $product['min_stock_level']= $minStock;
    $product['image']          = $imageName;
}

$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$pageTitle   = 'Edit Product';
$breadcrumbs = ['Products' => BASE_URL . '/products/index.php', 'Edit' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">✏</span> Edit Product</h1>
    <a href="<?= BASE_URL ?>/products/index.php" class="btn btn-secondary">← Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul style="margin:0 0 0 1rem"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div style="margin-bottom:1rem;padding:.75rem 1rem;background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
    <span class="text-muted fs-sm">SKU:</span><strong><?= e($product['sku']) ?></strong>
    <span class="text-muted">|</span>
    <span class="text-muted fs-sm">Added:</span><span class="fs-sm"><?= formatDate($product['created_at']) ?></span>
</div>

<form method="POST" enctype="multipart/form-data">
<div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start">

    <div class="card">
        <div class="card-header"><div class="card-title">Product Information</div></div>
        <div class="card-body">
            <div class="form-grid form-grid-2">
                <div class="form-group full-width">
                    <label class="form-label required">Product Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($product['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $product['category_id']==$c['id']?'selected':''?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control">
                        <option value="gift"  <?= $product['type']==='gift' ?'selected':''?>>Gift</option>
                        <option value="cloth" <?= $product['type']==='cloth'?'selected':''?>>Cloth</option>
                        <option value="both"  <?= $product['type']==='both' ?'selected':''?>>Both</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Selling Price</label>
                    <input type="number" name="selling_price" class="form-control" step="0.01" min="0"
                           value="<?= $product['selling_price'] ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Cost Price</label>
                    <input type="number" name="cost_price" class="form-control" step="0.01" min="0"
                           value="<?= $product['cost_price'] ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Stock Quantity</label>
                    <input type="number" name="stock_qty" class="form-control" min="0"
                           value="<?= $product['stock_qty'] ?>">
                    <div class="form-text">Changing this logs a stock adjustment.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Min Stock Level</label>
                    <input type="number" name="min_stock_level" class="form-control" min="0"
                           value="<?= $product['min_stock_level'] ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Size</label>
                    <input type="text" name="size" class="form-control" value="<?= e($product['size'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="text" name="color" class="form-control" value="<?= e($product['color'] ?? '') ?>">
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Occasion</label>
                    <input type="text" name="occasion" class="form-control" value="<?= e($product['occasion'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:1.25rem">
        <div class="card">
            <div class="card-header"><div class="card-title">Product Image</div></div>
            <div class="card-body">
                <?php if ($product['image'] && file_exists(UPLOAD_PATH . '/' . $product['image'])): ?>
                <img src="<?= UPLOAD_URL ?>/<?= e($product['image']) ?>" style="width:100%;height:150px;object-fit:cover;border-radius:8px;margin-bottom:.75rem">
                <label style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;cursor:pointer;margin-bottom:.75rem">
                    <input type="checkbox" name="remove_image" value="1"> Remove current image
                </label>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">New Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*" data-preview="imgPreview">
                    <img id="imgPreview" src="" alt="" style="display:none;width:100%;height:120px;object-fit:cover;border-radius:8px;margin-top:.5rem">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">Status</div></div>
            <div class="card-body">
                <select name="status" class="form-control">
                    <option value="active"   <?= $product['status']==='active'  ?'selected':''?>>Active</option>
                    <option value="inactive" <?= $product['status']==='inactive'?'selected':''?>>Inactive</option>
                </select>
            </div>
        </div>

        <div class="form-actions" style="flex-direction:column">
            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
        </div>
    </div>

</div>
</form>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
