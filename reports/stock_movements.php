<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

$dateFrom   = $_GET['from']    ?? date('Y-m-01');
$dateTo     = $_GET['to']      ?? date('Y-m-d');
$typeFilter = in_array($_GET['type'] ?? '', ['', 'in', 'out', 'adjustment'])
              ? ($_GET['type'] ?? '') : '';
$productId  = intval($_GET['product'] ?? 0);
$search     = trim($_GET['search'] ?? '');
$page       = max(1, intval($_GET['page'] ?? 1));

// Build WHERE
$where  = ['sm.created_at >= ?', 'sm.created_at <= DATE_ADD(?, INTERVAL 1 DAY)'];
$params = [$dateFrom, $dateTo];

if ($typeFilter) {
    $where[]  = 'sm.type = ?';
    $params[] = $typeFilter;
}
if ($productId) {
    $where[]  = 'sm.product_id = ?';
    $params[] = $productId;
}
if ($search) {
    $where[]  = '(p.name LIKE ? OR p.sku LIKE ? OR sm.reference LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereStr = implode(' AND ', $where);

// CSV Export
if (isset($_GET['export'])) {
    $expStmt = $pdo->prepare("
        SELECT sm.created_at, p.name AS product_name, p.sku,
               sm.type, sm.quantity, sm.reference, u.name AS user_name
        FROM stock_movements sm
        LEFT JOIN products p ON p.id = sm.product_id
        LEFT JOIN users u ON u.id = sm.created_by
        WHERE $whereStr
        ORDER BY sm.created_at DESC
    ");
    $expStmt->execute($params);
    $rows = $expStmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock_log_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Product', 'SKU', 'Type', 'Quantity', 'Reference', 'User']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['created_at'],
            $r['product_name'] ?? '—',
            $r['sku']          ?? '—',
            $r['type'],
            $r['quantity'],
            $r['reference']    ?? '—',
            $r['user_name']    ?? '—',
        ]);
    }
    fclose($out);
    exit;
}

// Summary KPIs
$summaryStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN sm.type='in'         THEN sm.quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN sm.type='out'        THEN sm.quantity ELSE 0 END), 0) AS total_out,
        COALESCE(SUM(CASE WHEN sm.type='adjustment' THEN sm.quantity ELSE 0 END), 0) AS total_adj,
        COUNT(*) AS total_movements
    FROM stock_movements sm
    LEFT JOIN products p ON p.id = sm.product_id
    WHERE $whereStr
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

// Paginated movements
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM stock_movements sm
    LEFT JOIN products p ON p.id = sm.product_id
    WHERE $whereStr
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pg    = paginate($total, $page);

$movStmt = $pdo->prepare("
    SELECT sm.id, sm.type, sm.quantity, sm.reference, sm.created_at,
           p.id AS product_id, p.name AS product_name, p.sku,
           u.name AS user_name
    FROM stock_movements sm
    LEFT JOIN products p ON p.id = sm.product_id
    LEFT JOIN users u ON u.id = sm.created_by
    WHERE $whereStr
    ORDER BY sm.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$movStmt->execute($params);
$movements = $movStmt->fetchAll();

// Product list for filter dropdown
$productList = $pdo->query("SELECT id, name, sku FROM products ORDER BY name")->fetchAll();

// Build base URL for pagination/export links
$baseQuery = http_build_query([
    'from'    => $dateFrom,
    'to'      => $dateTo,
    'type'    => $typeFilter,
    'product' => $productId,
    'search'  => $search,
]);

$pageTitle   = 'Stock Movement Log';
$breadcrumbs = ['Reports' => '', 'Stock Log' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">📋</span> Stock Movement Log</h1>
    <a href="?<?= $baseQuery ?>&export=1" class="btn btn-secondary">⬇ Export CSV</a>
</div>

<!-- KPI Summary -->
<div class="kpi-grid" style="margin-bottom:1.25rem;grid-template-columns:repeat(4,1fr)">
    <div class="kpi-card kpi-green">
        <div class="kpi-icon">📥</div>
        <div class="kpi-info">
            <div class="kpi-label">Stock In</div>
            <div class="kpi-value"><?= number_format($summary['total_in']) ?></div>
            <div class="kpi-sub">units received</div>
        </div>
    </div>
    <div class="kpi-card kpi-pink">
        <div class="kpi-icon">📤</div>
        <div class="kpi-info">
            <div class="kpi-label">Stock Out</div>
            <div class="kpi-value"><?= number_format($summary['total_out']) ?></div>
            <div class="kpi-sub">units sold / removed</div>
        </div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon">🔧</div>
        <div class="kpi-info">
            <div class="kpi-label">Adjustments</div>
            <div class="kpi-value"><?= number_format($summary['total_adj']) ?></div>
            <div class="kpi-sub">units adjusted</div>
        </div>
    </div>
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon">📋</div>
        <div class="kpi-info">
            <div class="kpi-label">Total Entries</div>
            <div class="kpi-value"><?= number_format($summary['total_movements']) ?></div>
            <div class="kpi-sub">in period</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:1.25rem">
    <form method="GET" class="filter-bar" style="padding:1.25rem">
        <div class="form-group">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="<?= e($dateFrom) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="<?= e($dateTo) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type" class="form-control">
                <option value="">All Types</option>
                <option value="in"         <?= $typeFilter === 'in'         ? 'selected' : '' ?>>Stock In</option>
                <option value="out"        <?= $typeFilter === 'out'        ? 'selected' : '' ?>>Stock Out</option>
                <option value="adjustment" <?= $typeFilter === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Product</label>
            <select name="product" class="form-control">
                <option value="">All Products</option>
                <?php foreach ($productList as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1">
            <label class="form-label">Search</label>
            <div class="input-icon-wrap">
                <span class="icon">🔍</span>
                <input type="text" name="search" class="form-control"
                       placeholder="Product name, SKU or reference..."
                       value="<?= e($search) ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">&nbsp;</label>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= BASE_URL ?>/reports/stock_movements.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>
</div>

<!-- Table -->
<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="text-center">Type</th>
                    <th class="text-center">Quantity</th>
                    <th>Reference</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($movements)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="icon">📋</div>
                            <h3>No stock movements found</h3>
                            <p>Adjust the filters or date range.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($movements as $m): ?>
                <?php
                    // Type display config
                    $typeMap = [
                        'in'         => ['label' => 'Stock In',    'class' => 'badge-success', 'sign' => '+'],
                        'out'        => ['label' => 'Stock Out',   'class' => 'badge-danger',  'sign' => '−'],
                        'adjustment' => ['label' => 'Adjustment',  'class' => 'badge-warning', 'sign' => '±'],
                    ];
                    $t    = $typeMap[$m['type']] ?? ['label' => ucfirst($m['type']), 'class' => 'badge-secondary', 'sign' => ''];
                    $qtyColor = $m['type'] === 'in' ? 'var(--success)' : ($m['type'] === 'out' ? 'var(--danger)' : 'var(--warning)');
                ?>
                <tr>
                    <td class="fs-sm text-muted"><?= formatDateTime($m['created_at']) ?></td>
                    <td>
                        <?php if ($m['product_id']): ?>
                        <a href="<?= BASE_URL ?>/products/edit.php?id=<?= $m['product_id'] ?>"
                           class="fw-bold" style="color:var(--text-primary)">
                            <?= e($m['product_name'] ?? '—') ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">Deleted product</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="product-sku"><?= e($m['sku'] ?? '—') ?></span></td>
                    <td class="text-center">
                        <span class="badge <?= $t['class'] ?>"><?= $t['label'] ?></span>
                    </td>
                    <td class="text-center fw-bold" style="color:<?= $qtyColor ?>;font-size:1rem">
                        <?= $t['sign'] ?><?= number_format($m['quantity']) ?>
                    </td>
                    <td>
                        <?php if ($m['reference']): ?>
                        <?php
                            // Link to invoice or PO if recognisable
                            $ref = $m['reference'];
                            $refHtml = e($ref);
                            if (str_starts_with($ref, 'INV-') || str_starts_with($ref, 'VOID-')) {
                                // Try to find the sale ID
                                $cleanRef = ltrim($ref, 'VOID-'); // strip VOID- prefix if present
                                $saleStmt = $pdo->prepare("SELECT id FROM sales WHERE invoice_no = ?");
                                $saleStmt->execute([str_starts_with($ref, 'VOID-') ? substr($ref, 5) : $ref]);
                                $saleRow = $saleStmt->fetch();
                                if ($saleRow) {
                                    $refHtml = '<a href="' . BASE_URL . '/sales/invoice.php?id=' . $saleRow['id'] . '" style="color:var(--accent)">' . e($ref) . '</a>';
                                }
                            } elseif (str_starts_with($ref, 'PO-')) {
                                $poStmt = $pdo->prepare("SELECT id FROM purchases WHERE reference_no = ?");
                                $poStmt->execute([$ref]);
                                $poRow = $poStmt->fetch();
                                if ($poRow) {
                                    $refHtml = '<a href="' . BASE_URL . '/purchases/view.php?id=' . $poRow['id'] . '" style="color:var(--accent)">' . e($ref) . '</a>';
                                }
                            }
                        ?>
                        <?= $refHtml ?>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= e($m['user_name'] ?? 'System') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pg['total_pages'] > 1): ?>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <div class="text-muted fs-sm">
            Showing <?= $pg['offset'] + 1 ?>–<?= min($pg['offset'] + $pg['per_page'], $total) ?> of <?= number_format($total) ?> entries
        </div>
        <?= paginationLinks($pg, BASE_URL . '/reports/stock_movements.php?' . $baseQuery) ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
