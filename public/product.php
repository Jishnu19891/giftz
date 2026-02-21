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
    $stockDot   = 'dot-out';
} elseif ($qty <= $min) {
    $stockLabel = "Only {$qty} unit" . ($qty != 1 ? 's' : '') . ' left';
    $stockBadge = 'stock-low';
    $stockDot   = 'dot-low';
} else {
    $stockLabel = 'In Stock';
    $stockBadge = 'stock-ok';
    $stockDot   = 'dot-ok';
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
    /* ═══════════════════════════════════════════════════════
       Giftz Product Page — Professional Design
    ═══════════════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: var(--font); background: #F7F8FC; color: #111827; }

    /* ─── Navbar ─────────────────────────────────────────── */
    .navbar {
      position: sticky; top: 0; z-index: 200;
      background: #0F172A; height: 64px;
      display: flex; align-items: center;
      padding: 0 2rem; gap: 2rem;
      box-shadow: 0 1px 0 rgba(255,255,255,.05), 0 4px 20px rgba(0,0,0,.35);
    }
    .nav-brand {
      display: flex; align-items: center; gap: .6rem;
      color: #fff; font-weight: 700; font-size: 1.05rem;
      text-decoration: none; flex-shrink: 0; letter-spacing: -.02em;
    }
    .nav-brand-icon {
      width: 32px; height: 32px;
      background: linear-gradient(135deg, #818CF8, #6366F1);
      border-radius: 8px; display: grid; place-items: center;
      font-size: .95rem; flex-shrink: 0;
      box-shadow: 0 2px 8px rgba(99,102,241,.45);
    }
    .nav-back {
      margin-left: auto;
      display: inline-flex; align-items: center; gap: .4rem;
      color: rgba(255,255,255,.55); font-size: .82rem; font-weight: 500;
      text-decoration: none; padding: .42rem .9rem;
      border-radius: 8px; border: 1px solid rgba(255,255,255,.12);
      transition: background .2s, color .2s; flex-shrink: 0;
      white-space: nowrap;
    }
    .nav-back:hover { background: rgba(255,255,255,.08); color: #fff; }

    /* ─── Trust bar ──────────────────────────────────────── */
    .trust-bar {
      background: #fff; border-bottom: 1px solid #E5E7EB; padding: .5rem 2rem;
    }
    .trust-inner {
      max-width: 1100px; margin: 0 auto;
      display: flex; align-items: center; justify-content: center;
      gap: 2rem; flex-wrap: wrap;
    }
    .trust-item {
      display: flex; align-items: center; gap: .4rem;
      font-size: .74rem; font-weight: 500; color: #374151; white-space: nowrap;
    }
    .trust-divider { width: 1px; height: 13px; background: #E5E7EB; }

    /* ─── Page wrapper ───────────────────────────────────── */
    .page-wrap {
      max-width: 1100px; margin: 0 auto;
      padding: 2rem 2rem 5rem;
    }

    /* ─── Breadcrumb ─────────────────────────────────────── */
    .breadcrumb {
      display: flex; align-items: center; gap: .35rem;
      font-size: .78rem; color: #9CA3AF;
      margin-bottom: 1.5rem; flex-wrap: wrap;
    }
    .breadcrumb a { color: #9CA3AF; text-decoration: none; transition: color .2s; }
    .breadcrumb a:hover { color: #4F46E5; }
    .breadcrumb .sep { color: #D1D5DB; font-size: .7rem; }
    .breadcrumb .current { color: #374151; font-weight: 500; }

    /* ─── Product layout ─────────────────────────────────── */
    .product-layout {
      display: grid; grid-template-columns: 1fr 1fr;
      background: #fff; border-radius: 16px;
      border: 1px solid #EAECF0;
      box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 8px 32px rgba(0,0,0,.04);
      overflow: hidden;
    }

    /* ─── Image panel ────────────────────────────────────── */
    .img-panel {
      position: relative; width: 100%; padding-top: 100%;
      background: linear-gradient(145deg, #EEF2FF, #E0E7FF);
      overflow: hidden;
    }
    .img-panel img {
      position: absolute; top: 0; left: 0;
      width: 100%; height: 100%;
      object-fit: cover; display: block;
    }
    .img-placeholder {
      position: absolute; top: 0; left: 0;
      width: 100%; height: 100%;
      display: grid; place-items: center;
      font-size: 7rem; opacity: .55; user-select: none;
    }
    /* Subtle "zoom" hint badge */
    .img-hint {
      position: absolute; bottom: .75rem; right: .75rem;
      background: rgba(255,255,255,.85); backdrop-filter: blur(6px);
      border: 1px solid #E5E7EB; border-radius: 8px;
      padding: .3rem .65rem; font-size: .7rem; font-weight: 600;
      color: #374151; display: flex; align-items: center; gap: .3rem;
    }

    /* ─── Info panel ─────────────────────────────────────── */
    .info-panel {
      padding: 2.5rem 2.5rem 2rem;
      display: flex; flex-direction: column;
      border-left: 1px solid #F1F2F4;
    }

    .info-cat {
      font-size: .67rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .1em; color: #4F46E5; margin-bottom: .5rem;
    }
    .info-name {
      font-size: 1.75rem; font-weight: 800;
      line-height: 1.15; letter-spacing: -.025em;
      color: #111827; margin-bottom: 1rem;
    }

    /* Price block */
    .price-block {
      padding: 1.1rem 0; border-top: 1px solid #F1F2F4; border-bottom: 1px solid #F1F2F4;
      margin-bottom: 1.1rem;
      display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    }
    .info-price {
      font-size: 2.1rem; font-weight: 800;
      color: #111827; letter-spacing: -.03em; line-height: 1;
    }
    .info-price-label { font-size: .72rem; color: #9CA3AF; font-weight: 500; margin-top: .2rem; }

    /* Stock badge */
    .stock-badge {
      display: inline-flex; align-items: center; gap: .45rem;
      font-size: .8rem; font-weight: 600;
      padding: .4em .85em; border-radius: 8px;
    }
    .stock-badge .sdot {
      width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
    }
    .stock-ok  { background: #ECFDF5; color: #059669; }
    .stock-low { background: #FFFBEB; color: #D97706; }
    .stock-out { background: #FEF2F2; color: #DC2626; }
    .dot-ok  { background: #10B981; animation: pulse-dot 2s ease-in-out infinite; }
    .dot-low { background: #F59E0B; }
    .dot-out { background: #EF4444; }
    @keyframes pulse-dot {
      0%,100% { box-shadow: 0 0 0 0 rgba(16,185,129,.4); }
      50%      { box-shadow: 0 0 0 5px rgba(16,185,129,0); }
    }

    /* Delivery estimate */
    .delivery-line {
      display: flex; align-items: center; gap: .5rem;
      font-size: .8rem; color: #6B7280; margin-bottom: 1.1rem;
    }
    .delivery-line strong { color: #374151; }

    /* Meta specs */
    .info-meta {
      background: #F9FAFB; border-radius: 10px;
      border: 1px solid #EAECF0; overflow: hidden;
      margin-bottom: 1.25rem;
    }
    .meta-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: .65rem 1rem; font-size: .84rem;
      border-bottom: 1px solid #EAECF0;
    }
    .meta-row:last-child { border-bottom: none; }
    .meta-label { color: #9CA3AF; font-weight: 500; }
    .meta-value { font-weight: 600; color: #111827; }

    /* Action buttons */
    .info-actions { display: flex; gap: .75rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .btn-primary-store {
      flex: 1; min-width: 160px;
      display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
      padding: .8rem 1.5rem;
      background: #4F46E5; color: #fff;
      border: none; border-radius: 10px;
      font-weight: 600; font-size: .9rem; font-family: inherit;
      text-decoration: none; cursor: pointer;
      transition: background .2s, transform .15s; letter-spacing: .01em;
    }
    .btn-primary-store:hover { background: #4338CA; color: #fff; transform: translateY(-1px); }
    .btn-outline-store {
      display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
      padding: .8rem 1.25rem;
      background: transparent; color: #374151;
      border: 1px solid #D1D5DB; border-radius: 10px;
      font-weight: 500; font-size: .88rem;
      text-decoration: none;
      transition: border-color .2s, background .2s, color .2s;
    }
    .btn-outline-store:hover { border-color: #4F46E5; color: #4F46E5; background: #EEF2FF; }

    /* Micro trust row */
    .info-trust {
      display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
      padding-top: .75rem; border-top: 1px solid #F1F2F4;
    }
    .info-trust-item {
      display: flex; align-items: center; gap: .3rem;
      font-size: .72rem; color: #9CA3AF; font-weight: 500;
    }

    /* ─── Related section ────────────────────────────────── */
    .related-section { margin-top: 3.5rem; }
    .related-hd {
      display: flex; align-items: center; gap: .75rem;
      margin-bottom: 1.5rem;
    }
    .related-hd h2 { font-size: 1.1rem; font-weight: 700; color: #111827; }
    .related-hd-line { flex: 1; height: 1px; background: #EAECF0; }

    /* Related grid — matches catalog card style */
    .related-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 1.25rem;
    }
    .mini-card {
      background: #fff; border-radius: 12px; overflow: hidden;
      border: 1px solid #EAECF0;
      text-decoration: none; color: inherit;
      display: flex; flex-direction: column;
      box-shadow: 0 1px 3px rgba(0,0,0,.05);
      transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
    }
    .mini-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 28px rgba(0,0,0,.09);
      border-color: #C7D2FE; color: inherit;
    }
    .mini-img {
      position: relative; width: 100%; padding-top: 100%;
      background: linear-gradient(145deg, #EEF2FF, #E0E7FF);
      overflow: hidden;
    }
    .mini-img img,
    .mini-img > span {
      position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    }
    .mini-img img { object-fit: cover; display: block; transition: transform .4s ease; }
    .mini-card:hover .mini-img img { transform: scale(1.05); }
    .mini-img > span { display: grid; place-items: center; font-size: 2.5rem; }
    /* Corner arrow on related card */
    .mini-arrow {
      position: absolute; bottom: 0; right: 0;
      width: 32px; height: 32px;
      background: #4F46E5; color: #fff;
      border-radius: 10px 0 0 0;
      display: grid; place-items: center;
      font-size: .88rem;
      opacity: 0; transform: translate(3px, 3px);
      transition: opacity .22s, transform .22s;
    }
    .mini-card:hover .mini-arrow { opacity: 1; transform: translate(0,0); }
    .mini-body { padding: .85rem 1rem 1rem; flex: 1; display: flex; flex-direction: column; gap: .2rem; }
    .mini-cat  { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #4F46E5; }
    .mini-name { font-size: .88rem; font-weight: 600; line-height: 1.35; color: #111827; }
    .mini-price{ font-size: 1rem; font-weight: 800; color: #111827; margin-top: .4rem; letter-spacing: -.01em; }

    /* ─── Footer ─────────────────────────────────────────── */
    .store-footer {
      background: #0F172A; color: rgba(255,255,255,.38);
      padding: 2rem 2rem;
      margin-top: 2rem;
    }
    .footer-inner {
      max-width: 1100px; margin: 0 auto;
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 1rem;
    }
    .footer-brand {
      display: flex; align-items: center; gap: .55rem;
      color: rgba(255,255,255,.7); font-weight: 700; font-size: .95rem;
      text-decoration: none; letter-spacing: -.01em;
    }
    .footer-brand-icon {
      width: 26px; height: 26px;
      background: linear-gradient(135deg, #818CF8, #6366F1);
      border-radius: 6px; display: grid; place-items: center; font-size: .75rem;
    }
    .footer-links { display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; }
    .footer-links a {
      font-size: .78rem; color: rgba(255,255,255,.35);
      text-decoration: none; transition: color .2s;
    }
    .footer-links a:hover { color: rgba(255,255,255,.75); }
    .footer-copy { font-size: .75rem; }

    /* ─── Loader ─────────────────────────────────────────── */
    #pageLoader {
      position: fixed; inset: 0;
      background: rgba(15,17,32,.72); backdrop-filter: blur(6px);
      z-index: 9999; display: grid; place-items: center;
      opacity: 0; pointer-events: none; transition: opacity .25s ease;
    }
    #pageLoader.active { opacity: 1; pointer-events: all; }
    .loader-box { position: relative; width: 64px; height: 64px; display: grid; place-items: center; }
    .loader-ring {
      position: absolute; inset: 0; border-radius: 50%;
      border: 2.5px solid rgba(255,255,255,.07);
      border-top-color: #6366F1; border-right-color: #EC4899;
      animation: spin .75s linear infinite;
    }
    .loader-brand { font-size: 1.5rem; animation: pulse 1.2s ease-in-out infinite; }
    @keyframes spin  { to { transform: rotate(360deg); } }
    @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.12); } }

    /* ─── Back to top ────────────────────────────────────── */
    #backToTop {
      position: fixed; bottom: 2rem; right: 2rem;
      width: 40px; height: 40px; border-radius: 10px;
      background: #1E293B; color: rgba(255,255,255,.8);
      border: 1px solid rgba(255,255,255,.1);
      font-size: .95rem; cursor: pointer;
      box-shadow: 0 4px 14px rgba(0,0,0,.3);
      display: grid; place-items: center;
      opacity: 0; transform: translateY(10px);
      transition: opacity .3s, transform .3s, background .2s;
      z-index: 500; pointer-events: none;
    }
    #backToTop.visible { opacity: 1; transform: translateY(0); pointer-events: auto; }
    #backToTop:hover { background: #334155; color: #fff; }

    /* ─── Responsive ─────────────────────────────────────── */
    @media (max-width: 800px) {
      .product-layout { grid-template-columns: 1fr; }
      .img-panel { padding-top: 75%; }
      .info-panel { border-left: none; border-top: 1px solid #F1F2F4; padding: 1.75rem; }
      .info-name  { font-size: 1.45rem; }
      .info-price { font-size: 1.75rem; }
    }
    @media (max-width: 768px) {
      .trust-inner { gap: 1.25rem; }
      .trust-divider { display: none; }
    }
    @media (max-width: 640px) {
      .navbar { padding: 0 .85rem; height: 56px; gap: 1rem; }
      .nav-back { font-size: .76rem; padding: .35rem .7rem; }
      .trust-bar { padding: .45rem 1rem; }
      .trust-item:nth-child(n+4) { display: none; }
      .page-wrap { padding: 1.25rem .9rem 3rem; }
      .img-panel { padding-top: 70%; }
      .info-panel { padding: 1.25rem; }
      .info-name  { font-size: 1.25rem; }
      .info-price { font-size: 1.55rem; }
      .related-grid { grid-template-columns: repeat(2, 1fr); gap: .75rem; }
      .related-section { margin-top: 2.5rem; }
      #backToTop { bottom: 1.25rem; right: 1.25rem; width: 36px; height: 36px; }
    }
    @media (max-width: 480px) {
      .navbar { height: 54px; }
      .info-panel { padding: 1rem .9rem; }
      .info-name  { font-size: 1.1rem; }
      .info-price { font-size: 1.35rem; }
      .info-actions { flex-direction: column; }
      .btn-primary-store, .btn-outline-store { min-width: unset; width: 100%; }
      .meta-row { padding: .55rem .85rem; font-size: .82rem; }
      .trust-item:nth-child(n+3) { display: none; }
    }
    @media (max-width: 360px) {
      .navbar { padding: 0 .65rem; gap: .5rem; }
      .nav-back { font-size: .7rem; padding: .3rem .55rem; }
      .related-grid { grid-template-columns: 1fr; }
      .img-panel { padding-top: 65%; }
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

<!-- ─── Trust bar ────────────────────────────────────── -->
<div class="trust-bar">
  <div class="trust-inner">
    <div class="trust-item"><span>🚚</span> Free delivery above ₹499</div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span>↩</span> Easy 7-day returns</div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span>✓</span> 100% authentic</div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span>🔒</span> Secure checkout</div>
  </div>
</div>

<!-- ─── Main content ──────────────────────────────────── -->
<main class="page-wrap">

  <!-- Breadcrumb -->
  <nav class="breadcrumb">
    <a href="<?= BASE_URL ?>/public/catalog.php">Store</a>
    <?php if ($product['category_name']): ?>
    <span class="sep">›</span>
    <a href="<?= BASE_URL ?>/public/catalog.php?cat=<?= $product['category_id'] ?>"><?= e($product['category_name']) ?></a>
    <?php endif; ?>
    <span class="sep">›</span>
    <span class="current"><?= e($product['name']) ?></span>
  </nav>

  <!-- Product detail -->
  <div class="product-layout">

    <!-- Image panel -->
    <div class="img-panel">
      <?php if ($product['image'] && file_exists(UPLOAD_PATH . '/' . $product['image'])): ?>
        <img src="<?= UPLOAD_URL ?>/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>">
        <div class="img-hint">🔍 Product image</div>
      <?php else: ?>
        <div class="img-placeholder"><?= productEmoji($product['category_name'] ?? '', $product['type']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Info panel -->
    <div class="info-panel">

      <?php if ($product['category_name']): ?>
      <div class="info-cat"><?= e($product['category_name']) ?></div>
      <?php endif; ?>

      <h1 class="info-name"><?= e($product['name']) ?></h1>

      <!-- Price + stock -->
      <div class="price-block">
        <div>
          <div class="info-price"><?= formatCurrency((float)$product['selling_price']) ?></div>
          <div class="info-price-label">Inclusive of all taxes</div>
        </div>
        <span class="stock-badge <?= $stockBadge ?>">
          <span class="sdot <?= $stockDot ?>"></span>
          <?= e($stockLabel) ?>
        </span>
      </div>

      <!-- Delivery estimate -->
      <div class="delivery-line">
        🚚 <span>Estimated delivery: <strong>2 – 5 business days</strong></span>
      </div>

      <!-- Spec table -->
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

      <!-- Actions -->
      <div class="info-actions">
        <a href="<?= BASE_URL ?>/public/catalog.php<?= e($backCat) ?>" class="btn-primary-store">
          Browse <?= $product['category_name'] ? e($product['category_name']) : 'More Products' ?>
        </a>
        <a href="<?= BASE_URL ?>/public/catalog.php" class="btn-outline-store">All Products</a>
      </div>

      <!-- Micro trust -->
      <div class="info-trust">
        <div class="info-trust-item">✓ Authentic</div>
        <div class="info-trust-item">↩ Easy returns</div>
        <div class="info-trust-item">🔒 Secure</div>
        <div class="info-trust-item">🎁 Gift ready</div>
      </div>

    </div>
  </div>

  <!-- Related products -->
  <?php if (!empty($related)): ?>
  <div class="related-section">
    <div class="related-hd">
      <h2>More from <?= e($product['category_name'] ?? 'our store') ?></h2>
      <div class="related-hd-line"></div>
    </div>
    <div class="related-grid">
      <?php foreach ($related as $r): ?>
      <a href="<?= BASE_URL ?>/public/product.php?id=<?= $r['id'] ?>" class="mini-card">
        <div class="mini-img">
          <?php if ($r['image'] && file_exists(UPLOAD_PATH . '/' . $r['image'])): ?>
            <img src="<?= UPLOAD_URL ?>/<?= e($r['image']) ?>" alt="<?= e($r['name']) ?>">
          <?php else: ?>
            <span><?= productEmoji($r['category_name'] ?? '', $r['type']) ?></span>
          <?php endif; ?>
          <div class="mini-arrow">→</div>
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
  <div class="footer-inner">
    <a href="<?= BASE_URL ?>/public/catalog.php" class="footer-brand">
      <div class="footer-brand-icon">🎁</div>
      <?= APP_NAME ?>
    </a>
    <div class="footer-links">
      <a href="<?= BASE_URL ?>/public/catalog.php">All Products</a>
      <?php if ($product['category_name']): ?>
      <a href="<?= BASE_URL ?>/public/catalog.php?cat=<?= $product['category_id'] ?>"><?= e($product['category_name']) ?></a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/login.php">Staff Login</a>
    </div>
    <div class="footer-copy">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &mdash; All prices in <?= CURRENCY_CODE ?></div>
  </div>
</footer>

<!-- ─── Page loader ───────────────────────────────────── -->
<div id="pageLoader">
  <div class="loader-box">
    <div class="loader-ring"></div>
    <div class="loader-brand">🎁</div>
  </div>
</div>

<script>
(function () {
  const loader = document.getElementById('pageLoader');
  document.querySelectorAll('.mini-card, .btn-primary-store, .btn-outline-store, .breadcrumb a, .nav-back').forEach(el => {
    el.addEventListener('click', () => loader.classList.add('active'));
  });
  window.addEventListener('pageshow', e => {
    if (e.persisted) loader.classList.remove('active');
  });
})();
</script>

<!-- ─── Back to top ──────────────────────────────────── -->
<button id="backToTop" title="Back to top" aria-label="Back to top">↑</button>

<script>
(function () {
  const btn = document.getElementById('backToTop');
  window.addEventListener('scroll', () => {
    btn.classList.toggle('visible', window.scrollY > 400);
  }, { passive: true });
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
})();
</script>
</body>
</html>
