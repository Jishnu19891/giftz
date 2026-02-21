<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$pdo    = db();
$search = trim($_GET['q']    ?? '');
$catId  = intval($_GET['cat'] ?? 0);
$page   = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;

$where  = ["p.status = 'active'"];
$params = [];
if ($search) {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($catId) {
    $where[] = 'p.category_id = ?';
    $params[] = $catId;
}
$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pg    = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE $whereStr
    ORDER BY p.name ASC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$products   = $stmt->fetchAll();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$baseLink = BASE_URL . '/public/catalog.php?q=' . urlencode($search) . '&cat=' . $catId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Product Catalog — <?= APP_NAME ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <style>
    /* ─── Public layout reset ────────────────────────────── */
    body { background: var(--body-bg); }

    .pub-header {
      background: var(--sidebar-bg);
      color: #fff;
      padding: 0 2rem;
      height: 66px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.5rem;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 16px rgba(0,0,0,.35);
    }

    .pub-brand {
      display: flex;
      align-items: center;
      gap: .75rem;
      color: #fff;
      font-size: 1.15rem;
      font-weight: 700;
      text-decoration: none;
      flex-shrink: 0;
    }

    .pub-brand-icon {
      width: 38px; height: 38px;
      background: linear-gradient(135deg, var(--accent), var(--secondary));
      border-radius: 10px;
      display: grid;
      place-items: center;
      font-size: 1.1rem;
    }

    .pub-search-form {
      display: flex;
      gap: .5rem;
      flex: 1;
      max-width: 480px;
    }

    .pub-search-input {
      flex: 1;
      padding: .55rem 1rem;
      border-radius: 8px;
      border: none;
      background: rgba(255,255,255,.12);
      color: #fff;
      font-size: .9rem;
      font-family: inherit;
      outline: none;
      transition: background .2s;
    }
    .pub-search-input::placeholder { color: rgba(255,255,255,.45); }
    .pub-search-input:focus { background: rgba(255,255,255,.2); box-shadow: 0 0 0 2px var(--accent); }

    .pub-search-btn {
      padding: .55rem .95rem;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: .9rem;
      cursor: pointer;
      transition: background .2s;
    }
    .pub-search-btn:hover { background: var(--accent-dark); }

    /* ─── Page wrapper ───────────────────────────────────── */
    .pub-wrap {
      max-width: 1280px;
      margin: 0 auto;
      padding: 2rem 2rem 3rem;
    }

    /* ─── Category tabs ──────────────────────────────────── */
    .cat-tabs {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
      margin-bottom: 1.75rem;
    }

    .cat-tab {
      display: inline-flex;
      align-items: center;
      padding: .42rem 1rem;
      border-radius: 100px;
      font-size: .82rem;
      font-weight: 500;
      border: 1.5px solid var(--border);
      background: var(--card-bg);
      color: var(--text-muted);
      text-decoration: none;
      transition: all .2s;
      white-space: nowrap;
    }
    .cat-tab:hover   { border-color: var(--accent); color: var(--accent); }
    .cat-tab.active  { background: var(--accent); color: #fff; border-color: var(--accent); }

    /* ─── Section info row ───────────────────────────────── */
    .section-info {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.25rem;
      flex-wrap: wrap;
      gap: .75rem;
    }
    .section-info .count { font-size: .875rem; color: var(--text-muted); }
    .section-info .count a { color: var(--accent); }

    /* ─── Product grid ───────────────────────────────────── */
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
      gap: 1.25rem;
      margin-bottom: 2rem;
    }

    .product-card {
      background: var(--card-bg);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      text-decoration: none;
      color: inherit;
      transition: transform .2s, box-shadow .2s;
    }
    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
      color: inherit;
    }

    .product-card-img {
      width: 100%;
      aspect-ratio: 1;
      object-fit: cover;
      display: block;
    }

    .product-card-placeholder {
      width: 100%;
      aspect-ratio: 1;
      background: linear-gradient(135deg, #F3F4F6, #E9EBF0);
      display: grid;
      place-items: center;
      font-size: 2.8rem;
      color: var(--text-muted);
    }

    .product-card-body {
      padding: 1rem;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: .3rem;
    }

    .product-card-cat {
      font-size: .7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--accent);
    }

    .product-card-name {
      font-size: .95rem;
      font-weight: 600;
      line-height: 1.3;
      color: var(--text-primary);
    }

    .product-card-price {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--accent);
      margin-top: auto;
      padding-top: .6rem;
    }

    .product-card-stock {
      font-size: .75rem;
      font-weight: 500;
    }
    .stock-txt-ok  { color: var(--success); }
    .stock-txt-low { color: var(--warning); }
    .stock-txt-out { color: var(--danger);  }

    /* ─── Pagination row ─────────────────────────────────── */
    .pagination-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
      padding-top: .5rem;
    }

    /* ─── Footer ─────────────────────────────────────────── */
    .pub-footer {
      text-align: center;
      padding: 1.5rem 2rem;
      font-size: .82rem;
      color: var(--text-muted);
      border-top: 1px solid var(--border);
    }

    /* ─── Responsive ─────────────────────────────────────── */
    @media (max-width: 640px) {
      .pub-header  { padding: 0 1rem; }
      .pub-wrap    { padding: 1.25rem 1rem 2rem; }
      .product-grid { grid-template-columns: repeat(2, 1fr); gap: .75rem; }
    }
    @media (max-width: 380px) {
      .product-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- ─── Header ─────────────────────────────────────────── -->
<header class="pub-header">
  <a href="<?= BASE_URL ?>/public/catalog.php" class="pub-brand">
    <div class="pub-brand-icon">🎁</div>
    <span><?= APP_NAME ?></span>
  </a>

  <form class="pub-search-form" method="GET" action="">
    <?php if ($catId): ?>
    <input type="hidden" name="cat" value="<?= $catId ?>">
    <?php endif; ?>
    <input
      type="search"
      name="q"
      class="pub-search-input"
      placeholder="Search products..."
      value="<?= e($search) ?>"
      autocomplete="off"
    >
    <button type="submit" class="pub-search-btn">🔍</button>
  </form>
</header>

<!-- ─── Main ───────────────────────────────────────────── -->
<main class="pub-wrap">

  <!-- Category tabs -->
  <nav class="cat-tabs">
    <a href="<?= BASE_URL ?>/public/catalog.php<?= $search ? '?q=' . urlencode($search) : '' ?>"
       class="cat-tab <?= $catId === 0 ? 'active' : '' ?>">
      All Products
    </a>
    <?php foreach ($categories as $c): ?>
    <a href="<?= BASE_URL ?>/public/catalog.php?cat=<?= $c['id'] ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
       class="cat-tab <?= $catId === (int)$c['id'] ? 'active' : '' ?>">
      <?= e($c['name']) ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <!-- Result info -->
  <div class="section-info">
    <div class="count">
      <?php if ($search || $catId): ?>
        <?= number_format($total) ?> result<?= $total != 1 ? 's' : '' ?>
        <?php if ($search): ?> for "<strong><?= e($search) ?></strong>"<?php endif; ?>
        <?php if ($catId): ?>
          <?php foreach ($categories as $c): if ((int)$c['id'] === $catId): ?>
          in <strong><?= e($c['name']) ?></strong>
          <?php endif; endforeach; ?>
        <?php endif; ?>
        &mdash; <a href="<?= BASE_URL ?>/public/catalog.php">Clear filters</a>
      <?php else: ?>
        <?= number_format($total) ?> product<?= $total != 1 ? 's' : '' ?> available
      <?php endif; ?>
    </div>
    <?php if ($pg['total_pages'] > 1): ?>
    <div class="count">Page <?= $pg['current'] ?> of <?= $pg['total_pages'] ?></div>
    <?php endif; ?>
  </div>

  <!-- Product grid -->
  <?php if (empty($products)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="icon">🎁</div>
      <h3>No products found</h3>
      <p>Try a different search or browse all categories.</p>
      <a href="<?= BASE_URL ?>/public/catalog.php" class="btn btn-primary" style="margin-top:1.25rem">Browse All Products</a>
    </div>
  </div>

  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $p):
      $qty = (int)$p['stock_qty'];
      $min = (int)$p['min_stock_level'];
      if ($qty <= 0) {
          $stockLabel = 'Out of Stock';
          $stockClass = 'stock-txt-out';
      } elseif ($qty <= $min) {
          $stockLabel = "Low Stock ({$qty} left)";
          $stockClass = 'stock-txt-low';
      } else {
          $stockLabel = "In Stock ({$qty})";
          $stockClass = 'stock-txt-ok';
      }
    ?>
    <a href="<?= BASE_URL ?>/public/product.php?id=<?= $p['id'] ?>" class="product-card">
      <?php if ($p['image'] && file_exists(UPLOAD_PATH . '/' . $p['image'])): ?>
        <img src="<?= UPLOAD_URL ?>/<?= e($p['image']) ?>" class="product-card-img" alt="<?= e($p['name']) ?>">
      <?php else: ?>
        <div class="product-card-placeholder">🎁</div>
      <?php endif; ?>

      <div class="product-card-body">
        <?php if ($p['category_name']): ?>
        <div class="product-card-cat"><?= e($p['category_name']) ?></div>
        <?php endif; ?>
        <div class="product-card-name"><?= e($p['name']) ?></div>
        <div class="product-card-price"><?= formatCurrency((float)$p['selling_price']) ?></div>
        <div class="product-card-stock <?= $stockClass ?>">● <?= $stockLabel ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($pg['total_pages'] > 1): ?>
  <div class="pagination-row">
    <div class="text-muted fs-sm">
      Showing <?= $pg['offset'] + 1 ?>–<?= min($pg['offset'] + $pg['per_page'], $total) ?> of <?= number_format($total) ?>
    </div>
    <?= paginationLinks($pg, $baseLink) ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</main>

<footer class="pub-footer">
  &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &mdash; All prices in <?= CURRENCY_CODE ?>
</footer>

</body>
</html>
