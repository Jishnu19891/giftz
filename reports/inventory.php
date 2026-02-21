<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

$filter   = in_array($_GET['filter'] ?? 'all', ['all','low','out']) ? ($_GET['filter'] ?? 'all') : 'all';
$category = intval($_GET['cat'] ?? 0);
$type     = in_array($_GET['type'] ?? '', ['','gift','cloth','both']) ? ($_GET['type'] ?? '') : '';

// CSV Export
if (isset($_GET['export'])) {
    $rows = $pdo->query("
        SELECT p.sku, p.name, c.name as category, p.type, p.stock_qty, p.min_stock_level,
               p.cost_price, p.selling_price, p.status
        FROM products p LEFT JOIN categories c ON c.id=p.category_id
        WHERE p.status='active' ORDER BY p.name
    ")->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['SKU','Name','Category','Type','Stock Qty','Min Level','Cost Price','Selling Price','Status']);
    foreach ($rows as $r) fputcsv($out, array_values($r));
    fclose($out);
    exit;
}

$where  = ["p.status='active'"];
$params = [];
if ($filter === 'low') { $where[] = 'p.stock_qty <= p.min_stock_level AND p.stock_qty > 0'; }
if ($filter === 'out') { $where[] = 'p.stock_qty = 0'; }
if ($category)         { $where[] = 'p.category_id=?'; $params[] = $category; }
if ($type)             { $where[] = 'p.type=?'; $params[] = $type; }

$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name
    FROM products p LEFT JOIN categories c ON c.id=p.category_id
    WHERE $whereStr
    ORDER BY p.stock_qty ASC, p.name ASC
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Summary
$counts = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN stock_qty=0 THEN 1 ELSE 0 END) as out_count,
        SUM(CASE WHEN stock_qty > 0 AND stock_qty <= min_stock_level THEN 1 ELSE 0 END) as low_count,
        COALESCE(SUM(stock_qty * cost_price), 0) as stock_value
    FROM products WHERE status='active'
")->fetch();

// Bottom 10 for chart
$chartProds = $pdo->query("SELECT name, stock_qty, min_stock_level FROM products WHERE status='active' ORDER BY stock_qty ASC LIMIT 10")->fetchAll();
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();

$pageTitle   = 'Inventory Report';
$breadcrumbs = ['Reports' => '', 'Inventory' => ''];
$inlineJs    = "
document.addEventListener('DOMContentLoaded', () => {
    GiftzCharts.inventorySnapshot('inventoryChart',
        " . json_encode(array_column($chartProds, 'name')) . ",
        " . json_encode(array_map('intval', array_column($chartProds, 'stock_qty'))) . ",
        " . json_encode(array_map('intval', array_column($chartProds, 'min_stock_level'))) . "
    );
});
";
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">📊</span> Inventory Report</h1>
    <a href="?filter=<?= e($filter) ?>&cat=<?= $category ?>&type=<?= e($type) ?>&export=1" class="btn btn-secondary">⬇ Export CSV</a>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="margin-bottom:1.25rem;grid-template-columns:repeat(4,1fr)">
    <div class="kpi-card kpi-blue">
        <div class="kpi-icon">📦</div>
        <div class="kpi-info"><div class="kpi-label">Total Products</div><div class="kpi-value"><?= $counts['total'] ?></div></div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon">⚠</div>
        <div class="kpi-info"><div class="kpi-label">Low Stock</div><div class="kpi-value"><?= $counts['low_count'] ?></div></div>
    </div>
    <div class="kpi-card kpi-pink">
        <div class="kpi-icon">🔴</div>
        <div class="kpi-info"><div class="kpi-label">Out of Stock</div><div class="kpi-value"><?= $counts['out_count'] ?></div></div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-icon">💵</div>
        <div class="kpi-info"><div class="kpi-label">Stock Value (Cost)</div><div class="kpi-value"><?= formatCurrency($counts['stock_value']) ?></div></div>
    </div>
</div>

<!-- Stock Chart -->
<?php if (!empty($chartProds)): ?>
<div class="card" style="margin-bottom:1.25rem">
    <div class="card-header"><div class="card-title">📊 Lowest Stock Levels</div></div>
    <div class="card-body">
        <div class="chart-container" style="height:<?= count($chartProds) * 38 + 50 ?>px;min-height:200px">
            <canvas id="inventoryChart"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label class="form-label">Stock Filter</label>
            <select name="filter" class="form-control">
                <option value="all" <?= $filter==='all'?'selected':''?>>All Products</option>
                <option value="low" <?= $filter==='low'?'selected':''?>>Low Stock</option>
                <option value="out" <?= $filter==='out'?'selected':''?>>Out of Stock</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Category</label>
            <select name="cat" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $category==$c['id']?'selected':''?>><?= e($c['name']) ?></option>
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
            <label class="form-label">&nbsp;</label>
            <button type="submit" class="btn btn-primary">Filter</button>
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
                    <th class="text-center">Stock</th>
                    <th class="text-center">Min Level</th>
                    <th class="text-right">Cost</th>
                    <th class="text-right">Stock Value</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="8"><div class="empty-state"><div class="icon">📦</div><h3>No products match</h3></div></td></tr>
            <?php else: ?>
                <?php foreach ($products as $p):
                    $stockCls = $p['stock_qty'] <= 0 ? 'stock-out' : ($p['stock_qty'] <= $p['min_stock_level'] ? 'stock-low' : 'stock-ok');
                    $rowBg    = $p['stock_qty'] <= 0 ? 'background:rgba(239,68,68,.03)' : ($p['stock_qty'] <= $p['min_stock_level'] ? 'background:rgba(245,158,11,.03)' : '');
                ?>
                <tr style="<?= $rowBg ?>">
                    <td class="fw-bold"><?= e($p['name']) ?></td>
                    <td class="fs-sm text-muted"><?= e($p['sku']) ?></td>
                    <td class="text-muted"><?= e($p['cat_name'] ?? '—') ?></td>
                    <td><?= statusBadge($p['type']) ?></td>
                    <td class="text-center"><span class="badge <?= $stockCls ?>"><?= $p['stock_qty'] ?></span></td>
                    <td class="text-center text-muted"><?= $p['min_stock_level'] ?></td>
                    <td class="text-right"><?= formatCurrency($p['cost_price']) ?></td>
                    <td class="text-right"><?= formatCurrency($p['stock_qty'] * $p['cost_price']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
