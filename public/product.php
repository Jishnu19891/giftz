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

$qty = (int)$product['stock_qty'];
$min = (int)$product['min_stock_level'];
if ($qty <= 0) {
    $stockLabel = 'Out of Stock';
    $stockBadge = 'stock-out';
} elseif ($qty <= $min) {
    $stockLabel = "{$qty} unit" . ($qty != 1 ? 's' : '') . ' remaining — Low Stock';
    $stockBadge = 'stock-low';
} else {
    $stockLabel = "{$qty} unit" . ($qty != 1 ? 's' : '') . ' in stock';
    $stockBadge = 'stock-ok';
}

// Ref to come back with same category filter
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <style>
    body { background: var(--body-bg); }

    /* ─── Header ─────────────────────────────────────────── */
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

    /* ─── Wrapper ─────────────────────────────────────────── */
    .pub-wrap {
      max-width: 960px;
      margin: 0 auto;
      padding: 2rem 2rem 3rem;
    }

    /* ─── Breadcrumb ──────────────────────────────────────── */
    .pub-breadcrumb {
      display: flex;
      align-items: center;
      gap: .4rem;
      font-size: .85rem;
      color: var(--text-muted);
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    .pub-breadcrumb a { color: var(--text-muted); text-decoration: none; }
    .pub-breadcrumb a:hover { color: var(--accent); }
    .pub-breadcrumb .sep { color: var(--border); }
    .pub-breadcrumb .current { color: var(--text-primary); font-weight: 500; }

    /* ─── Product detail layout ───────────────────────────── */
    .product-detail {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2.5rem;
      align-items: start;
    }

    .product-img-wrap { position: relative; }

    .product-detail-img {
      width: 100%;
      aspect-ratio: 1;
      object-fit: cover;
      border-radius: 14px;
      box-shadow: var(--shadow-lg);
      display: block;
    }

    .product-detail-placeholder {
      width: 100%;
      aspect-ratio: 1;
      background: linear-gradient(135deg, #F3F4F6, #E9EBF0);
      display: grid;
      place-items: center;
      font-size: 5rem;
      border-radius: 14px;
      box-shadow: var(--shadow);
      color: var(--text-muted);
    }

    /* ─── Info panel ──────────────────────────────────────── */
    .product-detail-info {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .product-detail-cat {
      font-size: .78rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: var(--accent);
    }

    .product-detail-name {
      font-size: 1.85rem;
      font-weight: 700;
      line-height: 1.2;
      color: var(--text-primary);
    }

    .product-detail-price {
      font-size: 2.1rem;
      font-weight: 700;
      color: var(--accent);
      letter-spacing: -.02em;
    }

    /* ─── Meta table ──────────────────────────────────────── */
    .detail-meta {
      background: #F9FAFB;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      overflow: hidden;
    }

    .detail-meta-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .7rem 1rem;
      font-size: .875rem;
      border-bottom: 1px solid var(--border);
    }
    .detail-meta-row:last-child { border-bottom: none; }
    .detail-meta-label { color: var(--text-muted); }
    .detail-meta-value { font-weight: 500; color: var(--text-primary); }

    /* ─── Footer ─────────────────────────────────────────── */
    .pub-footer {
      text-align: center;
      padding: 1.5rem 2rem;
      font-size: .82rem;
      color: var(--text-muted);
      border-top: 1px solid var(--border);
    }

    /* ─── Responsive ─────────────────────────────────────── */
    @media (max-width: 700px) {
      .pub-header         { padding: 0 1rem; }
      .pub-wrap           { padding: 1.25rem 1rem 2rem; }
      .product-detail     { grid-template-columns: 1fr; gap: 1.5rem; }
      .product-detail-name  { font-size: 1.5rem; }
      .product-detail-price { font-size: 1.65rem; }
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
  <a href="<?= BASE_URL ?>/public/catalog.php<?= e($backCat) ?>" class="btn btn-secondary btn-sm" style="flex-shrink:0">
    ← Back to Catalog
  </a>
</header>

<!-- ─── Main ───────────────────────────────────────────── -->
<main class="pub-wrap">

  <!-- Breadcrumb -->
  <nav class="pub-breadcrumb">
    <a href="<?= BASE_URL ?>/public/catalog.php">All Products</a>
    <?php if ($product['category_name']): ?>
    <span class="sep">›</span>
    <a href="<?= BASE_URL ?>/public/catalog.php?cat=<?= $product['category_id'] ?>"><?= e($product['category_name']) ?></a>
    <?php endif; ?>
    <span class="sep">›</span>
    <span class="current"><?= e($product['name']) ?></span>
  </nav>

  <!-- Product card -->
  <div class="card">
    <div class="card-body">
      <div class="product-detail">

        <!-- Image -->
        <div class="product-img-wrap">
          <?php if ($product['image'] && file_exists(UPLOAD_PATH . '/' . $product['image'])): ?>
            <img
              src="<?= UPLOAD_URL ?>/<?= e($product['image']) ?>"
              class="product-detail-img"
              alt="<?= e($product['name']) ?>"
            >
          <?php else: ?>
            <div class="product-detail-placeholder">🎁</div>
          <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="product-detail-info">
          <?php if ($product['category_name']): ?>
          <div class="product-detail-cat"><?= e($product['category_name']) ?></div>
          <?php endif; ?>

          <h1 class="product-detail-name"><?= e($product['name']) ?></h1>

          <div class="product-detail-price"><?= formatCurrency((float)$product['selling_price']) ?></div>

          <div>
            <span class="badge <?= $stockBadge ?>">
              <?= $stockBadge === 'stock-ok' ? '● ' : ($stockBadge === 'stock-low' ? '⚠ ' : '✕ ') ?><?= e($stockLabel) ?>
            </span>
          </div>

          <!-- Meta details -->
          <div class="detail-meta">
            <div class="detail-meta-row">
              <span class="detail-meta-label">SKU</span>
              <span class="detail-meta-value"><?= e($product['sku']) ?></span>
            </div>
            <div class="detail-meta-row">
              <span class="detail-meta-label">Type</span>
              <span class="detail-meta-value"><?= ucfirst(e($product['type'])) ?></span>
            </div>
            <?php if (!empty($product['size'])): ?>
            <div class="detail-meta-row">
              <span class="detail-meta-label">Size</span>
              <span class="detail-meta-value"><?= e($product['size']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($product['color'])): ?>
            <div class="detail-meta-row">
              <span class="detail-meta-label">Color</span>
              <span class="detail-meta-value"><?= e($product['color']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($product['occasion'])): ?>
            <div class="detail-meta-row">
              <span class="detail-meta-label">Occasion</span>
              <span class="detail-meta-value"><?= e($product['occasion']) ?></span>
            </div>
            <?php endif; ?>
          </div>

          <div>
            <a href="<?= BASE_URL ?>/public/catalog.php<?= e($backCat) ?>" class="btn btn-outline">
              ← Browse More Products
            </a>
          </div>
        </div>

      </div>
    </div>
  </div>

</main>

<footer class="pub-footer">
  &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &mdash; All prices in <?= CURRENCY_CODE ?>
</footer>

</body>
</html>
