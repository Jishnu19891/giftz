<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$pdo = db();
$id  = intval($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . BASE_URL . '/public/catalog.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.id AS category_id
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = ? AND p.status = 'active'
    LIMIT 1
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . BASE_URL . '/public/catalog.php');
    exit;
}

// Related products (same category, exclude self)
$related = [];
if ($product['category_id']) {
    $rs = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
        ORDER BY RAND() LIMIT 4
    ");
    $rs->execute([$product['category_id'], $id]);
    $related = $rs->fetchAll();
}

$qty = (int)$product['stock_qty'];
$min = (int)$product['min_stock_level'];
if ($qty <= 0) {
    $stockLabel = 'Out of Stock';
    $stockBadge = 'stock-out';
    $stockIcon  = '✕';
} elseif ($qty <= $min) {
    $stockLabel = "Only {$qty} unit" . ($qty != 1 ? 's' : '') . ' left — Low Stock';
    $stockBadge = 'stock-low';
    $stockIcon  = '⚠';
} else {
    $stockLabel = "{$qty} unit" . ($qty != 1 ? 's' : '') . ' in stock';
    $stockBadge = 'stock-ok';
    $stockIcon  = '●';
}

$backCat = $product['category_id'] ? '?cat=' . $product['category_id'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($product['name']) ?> — <?= APP_NAME ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: var(--font); background: #F4F6FB; color: var(--text-primary); }

    /* ─── Navbar ─────────────────────────────────────────── */
    .navbar {
      position: sticky;
      top: 0;
      z-index: 200;
      background: var(--sidebar-bg);
      height: 62px;
      display: flex;
      align-items: center;
      padding: 0 2rem;
      gap: 1.5rem;
      box-shadow: 0 2px 20px rgba(0,0,0,.4);
    }
    .nav-brand {
      display: flex;
      align-items: center;
      gap: .65rem;
      color: #fff;
      font-weight: 800;
      font-size: 1.1rem;
      text-decoration: none;
      flex-shrink: 0;
    }
    .nav-brand-icon {
      width: 34px; height: 34px;
      background: linear-gradient(135deg, var(--accent), var(--secondary));
      border-radius: 9px;
      display: grid;
      place-items: center;
      font-size: 1rem;
    }
    .nav-back {
      margin-left: auto;
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      color: rgba(255,255,255,.65);
      font-size: .83rem;
      font-weight: 500;
      text-decoration: none;
      padding: .4rem .85rem;
      border-radius: 8px;
      border: 1px solid rgba(255,255,255,.15);
      transition: background .2s, color .2s;
      flex-shrink: 0;
    }
    .nav-back:hover { background: rgba(255,255,255,.1); color: #fff; }

    /* ─── Page wrapper ───────────────────────────────────── */
    .page-wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 2rem 2rem 4rem;
    }

    /* ─── Breadcrumb ──────────────────────────────────────── */
    .breadcrumb {
      display: flex;
      align-items: center;
      gap: .4rem;
      font-size: .82rem;
      color: var(--text-muted);
      margin-bottom: 1.75rem;
      flex-wrap: wrap;
    }
    .breadcrumb a { color: var(--text-muted); text-decoration: none; transition: color .2s; }
    .breadcrumb a:hover { color: var(--accent); }
    .breadcrumb .sep { color: #D1D5DB; }
    .breadcrumb .current { color: var(--text-primary); font-weight: 500; }

    /* ─── Product detail card ────────────────────────────── */
    .product-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 2px 20px rgba(0,0,0,.07);
      border: 1px solid var(--border);
      overflow: hidden;
      display: grid;
      grid-template-columns: 1fr 1fr;
    }

    /* Image panel */
    .img-panel {
      background: linear-gradient(135deg, #F0F0F8, #E4E4F0);
      display: grid;
      place-items: center;
      padding: 2.5rem;
      min-height: 420px;
    }
    .img-panel img {
      width: 100%;
      max-height: 400px;
      object-fit: contain;
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,.12);
    }
    .img-placeholder {
      font-size: 7rem;
      opacity: .6;
      user-select: none;
    }

    /* Info panel */
    .info-panel {
      padding: 2.5rem;
      display: flex;
      flex-direction: column;
      gap: 1.1rem;
    }
    .info-cat {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--accent);
    }
    .info-name {
      font-size: 1.85rem;
      font-weight: 800;
      line-height: 1.2;
      letter-spacing: -.02em;
    }
    .info-price {
      font-size: 2.25rem;
      font-weight: 800;
      color: var(--accent);
      letter-spacing: -.03em;
    }
    .info-stock {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      font-size: .82rem;
      font-weight: 600;
      padding: .4em .9em;
      border-radius: 100px;
    }
    .stock-ok  { background: rgba(16,185,129,.1);  color: var(--success); }
    .stock-low { background: rgba(245,158,11,.1);  color: var(--warning); }
    .stock-out { background: rgba(239,68,68,.1);   color: var(--danger);  }

    /* Meta table */
    .info-meta {
      background: #F9FAFB;
      border-radius: 12px;
      border: 1px solid var(--border);
      overflow: hidden;
    }
    .meta-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .7rem 1.1rem;
      font-size: .86rem;
      border-bottom: 1px solid var(--border);
    }
    .meta-row:last-child { border-bottom: none; }
    .meta-label { color: var(--text-muted); }
    .meta-value { font-weight: 600; }

    .info-actions { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: .25rem; }
    .btn-store-back {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .7rem 1.4rem;
      background: transparent;
      color: var(--accent);
      border: 1.5px solid var(--accent);
      border-radius: 10px;
      font-weight: 600;
      font-size: .88rem;
      text-decoration: none;
      transition: background .2s, color .2s;
    }
    .btn-store-back:hover { background: var(--accent); color: #fff; }

    /* ─── Related products ───────────────────────────────── */
    .related-section { margin-top: 3rem; }
    .related-title {
      font-size: 1.15rem;
      font-weight: 700;
      margin-bottom: 1.25rem;
    }
    .related-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
      gap: 1.25rem;
    }

    /* Mini product card (same as catalog) */
    .mini-card {
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid var(--border);
      text-decoration: none;
      color: inherit;
      display: flex;
      flex-direction: column;
      transition: transform .2s, box-shadow .2s;
    }
    .mini-card:hover { transform: translateY(-4px); box-shadow: 0 10px 28px rgba(0,0,0,.11); color: inherit; }
    .mini-img {
      aspect-ratio: 1;
      background: linear-gradient(135deg, #F0F0F8, #E4E4F0);
      display: grid;
      place-items: center;
      font-size: 2.5rem;
      overflow: hidden;
    }
    .mini-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .mini-body { padding: .85rem 1rem; flex: 1; display: flex; flex-direction: column; gap: .2rem; }
    .mini-cat  { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--accent); }
    .mini-name { font-size: .88rem; font-weight: 600; line-height: 1.3; }
    .mini-price{ font-size: 1rem; font-weight: 800; color: var(--text-primary); margin-top: .4rem; }

    /* ─── Footer ─────────────────────────────────────────── */
    .store-footer {
      background: var(--sidebar-bg);
      color: rgba(255,255,255,.4);
      text-align: center;
      padding: 1.5rem 2rem;
      font-size: .8rem;
      margin-top: 2rem;
    }
    .store-footer a { color: rgba(255,255,255,.55); text-decoration: none; }
    .store-footer a:hover { color: #fff; }

    /* ─── Responsive ─────────────────────────────────────── */
    @media (max-width: 800px) {
      .product-card { grid-template-columns: 1fr; }
      .img-panel { min-height: 280px; padding: 2rem; }
      .info-panel { padding: 1.75rem; }
      .info-name  { font-size: 1.5rem; }
      .info-price { font-size: 1.75rem; }
    }
    @media (max-width: 640px) {
      .navbar    { padding: 0 1rem; }
      .page-wrap { padding: 1.25rem 1rem 3rem; }
      .related-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

<!-- ─── Navbar ────────────────────────────────────────── -->
<header class="navbar">
  <a href="<?= BASE_URL ?>/public/catalog.php" class="nav-brand">
    <div class="nav-brand-icon">🎁</div>
    <?= APP_NAME ?>
  </a>
  <a href="<?= BASE_URL ?>/public/catalog.php<?= e($backCat) ?>" class="nav-back">← Back to Catalog</a>
</header>

<!-- ─── Main ─────────────────────────────────────────── -->
<main class="page-wrap">

  <!-- Breadcrumb -->
  <nav class="breadcrumb">
    <a href="<?= BASE_URL ?>/public/catalog.php">All Products</a>
    <?php if ($product['category_name']): ?>
    <span class="sep">›</span>
    <a href="<?= BASE_URL ?>/public/catalog.php?cat=<?= $product['category_id'] ?>"><?= e($product['category_name']) ?></a>
    <?php endif; ?>
    <span class="sep">›</span>
    <span class="current"><?= e($product['name']) ?></span>
  </nav>

  <!-- Product detail -->
  <div class="product-card">

    <!-- Image -->
    <div class="img-panel">
      <?php if ($product['image'] && file_exists(UPLOAD_PATH . '/' . $product['image'])): ?>
        <img src="<?= UPLOAD_URL ?>/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>">
      <?php else: ?>
        <div class="img-placeholder"><?= productEmoji($product['category_name'] ?? '', $product['type']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="info-panel">
      <?php if ($product['category_name']): ?>
      <div class="info-cat"><?= e($product['category_name']) ?></div>
      <?php endif; ?>

      <h1 class="info-name"><?= e($product['name']) ?></h1>

      <div class="info-price"><?= formatCurrency((float)$product['selling_price']) ?></div>

      <div>
        <span class="info-stock <?= $stockBadge ?>">
          <?= $stockIcon ?> <?= e($stockLabel) ?>
        </span>
      </div>

      <div class="info-meta">
        <div class="meta-row">
          <span class="meta-label">SKU</span>
          <span class="meta-value"><?= e($product['sku']) ?></span>
        </div>
        <div class="meta-row">
          <span class="meta-label">Type</span>
          <span class="meta-value"><?= ucfirst(e($product['type'])) ?></span>
        </div>
        <?php if (!empty($product['size'])): ?>
        <div class="meta-row">
          <span class="meta-label">Size</span>
          <span class="meta-value"><?= e($product['size']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($product['color'])): ?>
        <div class="meta-row">
          <span class="meta-label">Color</span>
          <span class="meta-value"><?= e($product['color']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($product['occasion'])): ?>
        <div class="meta-row">
          <span class="meta-label">Occasion</span>
          <span class="meta-value"><?= e($product['occasion']) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <div class="info-actions">
        <a href="<?= BASE_URL ?>/public/catalog.php<?= e($backCat) ?>" class="btn-store-back">← Browse More</a>
      </div>
    </div>
  </div>

  <!-- Related products -->
  <?php if (!empty($related)): ?>
  <div class="related-section">
    <div class="related-title">More from <?= e($product['category_name'] ?? 'our store') ?></div>
    <div class="related-grid">
      <?php foreach ($related as $r): ?>
      <a href="<?= BASE_URL ?>/public/product.php?id=<?= $r['id'] ?>" class="mini-card">
        <div class="mini-img">
          <?php if ($r['image'] && file_exists(UPLOAD_PATH . '/' . $r['image'])): ?>
            <img src="<?= UPLOAD_URL ?>/<?= e($r['image']) ?>" alt="<?= e($r['name']) ?>">
          <?php else: ?>
            <?= productEmoji($r['category_name'] ?? '', $r['type']) ?>
          <?php endif; ?>
        </div>
        <div class="mini-body">
          <?php if ($r['category_name']): ?><div class="mini-cat"><?= e($r['category_name']) ?></div><?php endif; ?>
          <div class="mini-name"><?= e($r['name']) ?></div>
          <div class="mini-price"><?= formatCurrency((float)$r['selling_price']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</main>

<!-- ─── Footer ────────────────────────────────────────── -->
<footer class="store-footer">
  &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &mdash; All prices in <?= CURRENCY_CODE ?>
  &nbsp;·&nbsp; <a href="<?= BASE_URL ?>/public/catalog.php">Back to Store</a>
</footer>

</body>
</html>
