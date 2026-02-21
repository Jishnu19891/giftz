<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

$search = trim($_GET['search'] ?? '');
$catId  = intval($_GET['cat']  ?? 0);
$type   = $_GET['type']         ?? '';
$status = $_GET['status']       ?? 'active';
$page   = max(1, intval($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($catId)  { $where[] = 'p.category_id=?'; $params[] = $catId; }
if ($type)   { $where[] = 'p.type=?'; $params[] = $type; }
if ($status) { $where[] = 'p.status=?'; $params[] = $status; }
$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pg    = paginate($total, $page);

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p LEFT JOIN categories c ON c.id=p.category_id
    WHERE $whereStr ORDER BY p.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$products   = $stmt->fetchAll();
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();

$pageTitle   = 'Products';
$breadcrumbs = ['Products' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">📦</span> Products</h1>
    <a href="<?= BASE_URL ?>/products/add.php" class="btn btn-primary">+ Add Product</a>
</div>

<div class="card">
    <form method="GET" class="filter-bar">
        <div class="form-group" style="flex:1;min-width:180px">
            <label class="form-label">Search</label>
            <div class="input-icon-wrap">
                <span class="icon">🔍</span>
                <input type="text" name="search" class="form-control" placeholder="Name or SKU..." value="<?= e($search) ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Category</label>
            <select name="cat" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $catId==$c['id']?'selected':''?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type" class="form-control">
                <option value="">All</option>
                <option value="gift"  <?= $type==='gift' ?'selected':''?>>Gift</option>
                <option value="cloth" <?= $type==='cloth'?'selected':''?>>Cloth</option>
                <option value="both"  <?= $type==='both' ?'selected':''?>>Both</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="active"   <?= $status==='active'  ?'selected':''?>>Active</option>
                <option value="inactive" <?= $status==='inactive'?'selected':''?>>Inactive</option>
                <option value=""         <?= $status===''        ?'selected':''?>>All</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">&nbsp;</label>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= BASE_URL ?>/products/index.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th class="text-right">Cost</th>
                    <th class="text-right">Price</th>
                    <th class="text-center">Stock</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="9"><div class="empty-state">
                    <div class="icon">📦</div>
                    <h3>No products found</h3>
                    <p>Try adjusting filters or <a href="<?= BASE_URL ?>/products/add.php">add a product</a>.</p>
                </div></td></tr>
            <?php else: ?>
                <?php foreach ($products as $p):
                    $stockClass = $p['stock_qty'] <= 0 ? 'stock-out' : ($p['stock_qty'] <= $p['min_stock_level'] ? 'stock-low' : 'stock-ok');
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:.75rem">
                            <?php if ($p['image'] && file_exists(UPLOAD_PATH . '/' . $p['image'])): ?>
                            <img src="<?= UPLOAD_URL ?>/<?= e($p['image']) ?>" class="product-thumb" alt="">
                            <?php else: ?>
                            <div class="product-thumb-placeholder"><?= productEmoji($p['category_name'] ?? '', $p['type']) ?></div>
                            <?php endif; ?>
                            <div class="product-name"><?= e($p['name']) ?></div>
                        </div>
                    </td>
                    <td><span class="product-sku"><?= e($p['sku']) ?></span></td>
                    <td class="text-muted"><?= e($p['category_name'] ?? '—') ?></td>
                    <td><?= statusBadge($p['type']) ?></td>
                    <td class="text-right text-muted"><?= formatCurrency($p['cost_price']) ?></td>
                    <td class="text-right fw-bold"><?= formatCurrency($p['selling_price']) ?></td>
                    <td class="text-center"><span class="badge <?= $stockClass ?>"><?= $p['stock_qty'] ?></span></td>
                    <td><?= statusBadge($p['status']) ?></td>
                    <td class="text-right">
                        <div class="btn-group" style="justify-content:flex-end">
                            <a href="<?= BASE_URL ?>/products/edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">✏ Edit</a>
                            <form method="POST" action="<?= BASE_URL ?>/products/delete.php" style="display:inline"
                                  onsubmit="return confirm('Deactivate this product?')">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
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

    <?php if ($pg['total_pages'] > 1): ?>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <div class="text-muted fs-sm">
            Showing <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'], $total) ?> of <?= $total ?> products
        </div>
        <?= paginationLinks($pg, BASE_URL . '/products/index.php?search=' . urlencode($search) . '&cat=' . $catId . '&type=' . urlencode($type) . '&status=' . urlencode($status)) ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
