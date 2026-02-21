<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$pdo     = db();
$search  = trim($_GET['q']    ?? '');
$catId   = intval($_GET['cat'] ?? 0);
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 16;

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
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();

// Category emoji map for the filter bar
$catEmojis = [
    'gift items'    => '🎁',
    'clothing'      => '👕',
    'souvenirs'     => '🏺',
    'accessories'   => '💍',
    'seasonal gifts'=> '🎄',
    "children's"    => '🧸',
    "men's wear"    => '👔',
    "women's wear"  => '👗',
];

$baseLink = BASE_URL . '/public/catalog.php?q=' . urlencode($search) . '&cat=' . $catId;

// Storefront announcements from DB
$announcements = $pdo->query(
    "SELECT emoji, message FROM announcements WHERE is_active = 1 ORDER BY sort_order ASC, id ASC"
)->fetchAll();

// Active category name
$activeCatName = 'All Products';
foreach ($categories as $c) {
    if ((int)$c['id'] === $catId) { $activeCatName = $c['name']; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $catId ? e($activeCatName) . ' — ' : '' ?><?= APP_NAME ?> Store</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <style>
    /* ═══════════════════════════════════════════════════════
       Giftz Public Storefront
    ═══════════════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
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
      letter-spacing: -.01em;
    }
    .nav-brand-icon {
      width: 34px; height: 34px;
      background: linear-gradient(135deg, var(--accent), var(--secondary));
      border-radius: 9px;
      display: grid;
      place-items: center;
      font-size: 1rem;
      flex-shrink: 0;
    }

    .nav-search {
      flex: 1;
      max-width: 500px;
      margin: 0 auto;
      display: flex;
      background: rgba(255,255,255,.1);
      border-radius: 10px;
      overflow: hidden;
      border: 1.5px solid rgba(255,255,255,.1);
      transition: border-color .2s, background .2s;
    }
    .nav-search:focus-within {
      background: rgba(255,255,255,.16);
      border-color: var(--accent);
    }
    .nav-search input {
      flex: 1;
      background: transparent;
      border: none;
      padding: .6rem 1rem;
      color: #fff;
      font-size: .875rem;
      font-family: inherit;
      outline: none;
    }
    .nav-search input::placeholder { color: rgba(255,255,255,.4); }
    .nav-search button {
      background: transparent;
      border: none;
      padding: 0 .9rem;
      color: rgba(255,255,255,.6);
      font-size: 1rem;
      cursor: pointer;
      transition: color .2s;
    }
    .nav-search button:hover { color: #fff; }

    .nav-tagline {
      font-size: .78rem;
      color: rgba(255,255,255,.4);
      flex-shrink: 0;
      white-space: nowrap;
    }

    /* ─── Hero ───────────────────────────────────────────── */
    .hero {
      background: linear-gradient(135deg, #1A1D2E 0%, #2D2563 50%, #3D1A5C 100%);
      padding: 4rem 2rem 3.5rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 20% 50%, rgba(108,99,255,.25) 0%, transparent 50%),
        radial-gradient(circle at 80% 30%, rgba(255,101,132,.2) 0%, transparent 45%);
      pointer-events: none;
    }
    .hero-inner { position: relative; z-index: 1; max-width: 680px; margin: 0 auto; }
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      background: rgba(255,255,255,.1);
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 100px;
      padding: .3rem .9rem;
      font-size: .75rem;
      font-weight: 600;
      color: rgba(255,255,255,.8);
      letter-spacing: .04em;
      text-transform: uppercase;
      margin-bottom: 1.25rem;
    }
    .hero h1 {
      font-size: clamp(2rem, 5vw, 3rem);
      font-weight: 800;
      color: #fff;
      line-height: 1.15;
      margin-bottom: .75rem;
      letter-spacing: -.02em;
    }
    .hero h1 span {
      background: linear-gradient(90deg, var(--accent-light), var(--secondary));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .hero-sub {
      font-size: 1.05rem;
      color: rgba(255,255,255,.6);
      margin-bottom: 2rem;
      line-height: 1.6;
    }

    /* Hero search */
    .hero-search {
      display: flex;
      background: #fff;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 8px 40px rgba(0,0,0,.35);
      max-width: 560px;
      margin: 0 auto;
    }
    .hero-search input {
      flex: 1;
      border: none;
      padding: .95rem 1.25rem;
      font-size: .95rem;
      font-family: inherit;
      color: var(--text-primary);
      outline: none;
      background: transparent;
    }
    .hero-search input::placeholder { color: var(--text-muted); }
    .hero-search button {
      background: linear-gradient(135deg, var(--accent), var(--accent-dark));
      color: #fff;
      border: none;
      padding: 0 1.5rem;
      font-size: .9rem;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      transition: opacity .2s;
      display: flex;
      align-items: center;
      gap: .4rem;
      white-space: nowrap;
    }
    .hero-search button:hover { opacity: .9; }

    .hero-stats {
      display: flex;
      justify-content: center;
      gap: 2rem;
      margin-top: 2rem;
    }
    .hero-stat { color: rgba(255,255,255,.55); font-size: .82rem; }
    .hero-stat strong { color: #fff; display: block; font-size: 1.1rem; font-weight: 700; }

    /* ─── Category bar ───────────────────────────────────── */
    .cat-bar-wrap {
      background: #fff;
      border-bottom: 1px solid var(--border);
      position: sticky;
      top: 62px;
      z-index: 150;
    }
    .cat-bar {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 2rem;
      display: flex;
      gap: 0;
      overflow-x: auto;
      scrollbar-width: none;
    }
    .cat-bar::-webkit-scrollbar { display: none; }

    .cat-btn {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .9rem 1.1rem;
      font-size: .82rem;
      font-weight: 500;
      color: var(--text-muted);
      text-decoration: none;
      border-bottom: 2.5px solid transparent;
      transition: color .2s, border-color .2s;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .cat-btn:hover  { color: var(--accent); }
    .cat-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }

    /* ─── Main content ───────────────────────────────────── */
    .store-wrap {
      max-width: 1280px;
      margin: 0 auto;
      padding: 2rem 2rem 4rem;
    }

    /* ─── Section header ─────────────────────────────────── */
    .section-hd {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      gap: .75rem;
    }
    .section-hd h2 {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--text-primary);
    }
    .section-hd h2 .count-pill {
      display: inline-flex;
      align-items: center;
      background: var(--accent);
      color: #fff;
      font-size: .72rem;
      font-weight: 600;
      padding: .18em .6em;
      border-radius: 100px;
      margin-left: .5rem;
      vertical-align: middle;
    }
    .section-hd .filter-info { font-size: .85rem; color: var(--text-muted); }
    .section-hd .filter-info a { color: var(--accent); }

    /* ─── Product grid ───────────────────────────────────── */
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2.5rem;
    }

    .product-card {
      background: #fff;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 1px 6px rgba(0,0,0,.07);
      display: flex;
      flex-direction: column;
      text-decoration: none;
      color: inherit;
      transition: transform .22s ease, box-shadow .22s ease;
      border: 1px solid var(--border);
    }
    .product-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 36px rgba(0,0,0,.13);
      color: inherit;
    }

    /* Image area */
    .card-img-wrap {
      position: relative;
      overflow: hidden;
      aspect-ratio: 1;
      background: #F3F4F6;
    }
    .card-img-wrap img {
      width: 100%; height: 100%;
      object-fit: cover;
      display: block;
      transition: transform .35s ease;
    }
    .product-card:hover .card-img-wrap img { transform: scale(1.06); }

    .card-placeholder {
      width: 100%; height: 100%;
      display: grid;
      place-items: center;
      font-size: 3.5rem;
      background: linear-gradient(135deg, #F0F0F8, #E4E4F0);
      transition: transform .35s ease;
    }
    .product-card:hover .card-placeholder { transform: scale(1.08); }

    /* Hover overlay */
    .card-overlay {
      position: absolute;
      inset: 0;
      background: rgba(26,29,46,.55);
      display: grid;
      place-items: center;
      opacity: 0;
      transition: opacity .22s;
    }
    .product-card:hover .card-overlay { opacity: 1; }
    .card-overlay-btn {
      background: #fff;
      color: var(--accent);
      font-weight: 700;
      font-size: .82rem;
      padding: .6rem 1.3rem;
      border-radius: 100px;
      letter-spacing: .02em;
      transform: translateY(6px);
      transition: transform .22s;
    }
    .product-card:hover .card-overlay-btn { transform: translateY(0); }

    /* Out-of-stock ribbon */
    .card-ribbon {
      position: absolute;
      top: .75rem;
      right: .75rem;
      background: var(--danger);
      color: #fff;
      font-size: .68rem;
      font-weight: 700;
      padding: .25em .7em;
      border-radius: 100px;
      letter-spacing: .03em;
      text-transform: uppercase;
    }
    .card-ribbon.low { background: var(--warning); }

    /* Card body */
    .card-body {
      padding: 1rem 1.1rem 1.1rem;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: .3rem;
    }
    .card-cat {
      font-size: .68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: var(--accent);
    }
    .card-name {
      font-size: .95rem;
      font-weight: 600;
      line-height: 1.35;
      color: var(--text-primary);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .card-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: auto;
      padding-top: .75rem;
    }
    .card-price {
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--text-primary);
      letter-spacing: -.02em;
    }
    .card-stock {
      font-size: .72rem;
      font-weight: 600;
      padding: .2em .65em;
      border-radius: 100px;
    }
    .cs-ok  { background: rgba(16,185,129,.1);  color: var(--success); }
    .cs-low { background: rgba(245,158,11,.1);  color: var(--warning); }
    .cs-out { background: rgba(239,68,68,.1);   color: var(--danger);  }

    /* ─── Empty state ────────────────────────────────────── */
    .empty-wrap {
      background: #fff;
      border-radius: 16px;
      padding: 4rem 2rem;
      text-align: center;
      border: 1px solid var(--border);
    }
    .empty-wrap .icon { font-size: 3.5rem; opacity: .35; margin-bottom: 1rem; }
    .empty-wrap h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: .5rem; }
    .empty-wrap p  { font-size: .9rem; color: var(--text-muted); margin-bottom: 1.5rem; }

    /* ─── Pagination ─────────────────────────────────────── */
    .pg-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .pg-info { font-size: .85rem; color: var(--text-muted); }

    /* ─── Footer ─────────────────────────────────────────── */
    .store-footer {
      background: var(--sidebar-bg);
      color: rgba(255,255,255,.6);
      padding: 3rem 2rem 2rem;
    }
    .footer-inner {
      max-width: 1280px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1.6fr 1fr 1fr 1.3fr;
      gap: 2.5rem;
      padding-bottom: 2rem;
      border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .footer-brand { display: flex; align-items: center; gap: .65rem; color: #fff; font-weight: 800; font-size: 1.05rem; margin-bottom: .75rem; }
    .footer-brand-icon {
      width: 32px; height: 32px;
      background: linear-gradient(135deg, var(--accent), var(--secondary));
      border-radius: 8px;
      display: grid;
      place-items: center;
      font-size: .9rem;
    }
    .footer-desc { font-size: .83rem; line-height: 1.7; max-width: 280px; }
    .footer-col h4 { color: #fff; font-size: .82rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .85rem; }
    .footer-col a {
      display: block;
      font-size: .83rem;
      color: rgba(255,255,255,.5);
      text-decoration: none;
      margin-bottom: .5rem;
      transition: color .2s;
    }
    .footer-col a:hover { color: #fff; }
    .footer-contact-item {
      display: flex;
      align-items: flex-start;
      gap: .6rem;
      font-size: .83rem;
      color: rgba(255,255,255,.5);
      margin-bottom: .75rem;
      line-height: 1.55;
    }
    .footer-contact-item a { color: rgba(255,255,255,.5); text-decoration: none; transition: color .2s; }
    .footer-contact-item a:hover { color: #fff; }
    .footer-bottom {
      max-width: 1280px;
      margin: 1.5rem auto 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: .78rem;
      color: rgba(255,255,255,.3);
      flex-wrap: wrap;
      gap: .5rem;
    }

    /* ─── Announcement bar ───────────────────────────────── */
    .announce-bar {
      background: linear-gradient(90deg, #4F3EBF, var(--accent), var(--secondary), #C2185B);
      background-size: 300% 100%;
      animation: gradientShift 8s ease infinite;
      color: #fff;
      padding: .6rem 3rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .82rem;
      font-weight: 500;
      position: relative;
      overflow: hidden;
      transition: max-height .4s ease, padding .35s ease, opacity .3s;
      max-height: 48px;
    }
    @keyframes gradientShift {
      0%   { background-position: 0% 50%; }
      50%  { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }
    .announce-bar.hidden {
      max-height: 0;
      padding-top: 0;
      padding-bottom: 0;
      opacity: 0;
      pointer-events: none;
    }
    .announce-msg {
      display: none;
      align-items: center;
      gap: .5rem;
      animation: fadeMsg .4s ease;
    }
    .announce-msg.active { display: flex; }
    @keyframes fadeMsg {
      from { opacity: 0; transform: translateY(4px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .announce-dots {
      display: flex;
      gap: .3rem;
      margin-left: .75rem;
    }
    .announce-dot {
      width: 5px; height: 5px;
      border-radius: 50%;
      background: rgba(255,255,255,.4);
      cursor: pointer;
      border: none;
      padding: 0;
      transition: background .2s, transform .2s;
    }
    .announce-dot.active { background: #fff; transform: scale(1.3); }
    .announce-dismiss {
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255,255,255,.18);
      border: none;
      color: #fff;
      width: 22px; height: 22px;
      border-radius: 50%;
      font-size: .9rem;
      cursor: pointer;
      display: grid;
      place-items: center;
      transition: background .2s;
      line-height: 1;
    }
    .announce-dismiss:hover { background: rgba(255,255,255,.35); }

    /* ─── Responsive ─────────────────────────────────────── */
    @media (max-width: 1024px) {
      .footer-inner { grid-template-columns: 1fr 1fr; }
      .footer-inner > :first-child { grid-column: 1 / -1; }
    }
    @media (max-width: 640px) {
      .navbar { padding: 0 1rem; }
      .nav-tagline { display: none; }
      .hero { padding: 2.5rem 1rem 2rem; }
      .hero-stats { gap: 1.25rem; }
      .cat-bar { padding: 0 1rem; }
      .store-wrap { padding: 1.5rem 1rem 3rem; }
      .product-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
      .footer-inner { grid-template-columns: 1fr; gap: 1.5rem; }
      .store-footer { padding: 2rem 1rem 1.5rem; }
    }
    @media (max-width: 360px) {
      .product-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- ─── Announcement bar ─────────────────────────────── -->
<?php if (!empty($announcements)): ?>
<div class="announce-bar" id="announceBar">
  <?php foreach ($announcements as $i => $ann): ?>
  <div class="announce-msg <?= $i === 0 ? 'active' : '' ?>">
    <?= e($ann['emoji']) ?> <span><?= e($ann['message']) ?></span>
  </div>
  <?php endforeach; ?>
  <?php if (count($announcements) > 1): ?>
  <div class="announce-dots" id="announceDots">
    <?php foreach ($announcements as $i => $ann): ?>
    <button class="announce-dot <?= $i === 0 ? 'active' : '' ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <button class="announce-dismiss" id="announceDismiss" title="Dismiss">×</button>
</div>
<?php endif; ?>

<!-- ─── Navbar ────────────────────────────────────────── -->
<header class="navbar">
  <a href="<?= BASE_URL ?>/public/catalog.php" class="nav-brand">
    <div class="nav-brand-icon">🎁</div>
    <?= APP_NAME ?>
  </a>

  <form class="nav-search" method="GET" action="">
    <?php if ($catId): ?><input type="hidden" name="cat" value="<?= $catId ?>"><?php endif; ?>
    <input type="search" name="q" placeholder="Search products..." value="<?= e($search) ?>" autocomplete="off">
    <button type="submit">🔍</button>
  </form>

  <span class="nav-tagline">All prices in <?= CURRENCY_CODE ?></span>
</header>

<!-- ─── Hero (shown only on the unfiltered landing) ─── -->
<?php if (!$search && !$catId && $page === 1): ?>
<section class="hero">
  <div class="hero-inner">
    <div class="hero-badge">🎁 New arrivals every week</div>
    <h1>Your perfect <span>gift</span> awaits</h1>
    <p class="hero-sub">Gifts, clothing &amp; accessories — curated for every occasion</p>

    <form class="hero-search" method="GET" action="">
      <input type="search" name="q" placeholder="Search for gifts, clothing, souvenirs..." autocomplete="off">
      <button type="submit">🔍 Search</button>
    </form>

    <div class="hero-stats">
      <div class="hero-stat"><strong><?= number_format($total) ?>+</strong>Products</div>
      <div class="hero-stat"><strong><?= count($categories) ?></strong>Categories</div>
      <div class="hero-stat"><strong>100%</strong>Authentic</div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── Category bar ──────────────────────────────────── -->
<div class="cat-bar-wrap">
  <nav class="cat-bar">
    <a href="<?= BASE_URL ?>/public/catalog.php<?= $search ? '?q=' . urlencode($search) : '' ?>"
       class="cat-btn <?= $catId === 0 ? 'active' : '' ?>">
      All Products
    </a>
    <?php foreach ($categories as $c):
      $emoji = $catEmojis[strtolower($c['name'])] ?? '🏷';
    ?>
    <a href="<?= BASE_URL ?>/public/catalog.php?cat=<?= $c['id'] ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
       class="cat-btn <?= $catId === (int)$c['id'] ? 'active' : '' ?>">
      <?= $emoji ?> <?= e($c['name']) ?>
    </a>
    <?php endforeach; ?>
  </nav>
</div>

<!-- ─── Products ─────────────────────────────────────── -->
<main class="store-wrap">

  <div class="section-hd">
    <h2>
      <?= $search ? 'Search Results' : e($activeCatName) ?>
      <span class="count-pill"><?= number_format($total) ?></span>
    </h2>
    <?php if ($search || $catId): ?>
    <div class="filter-info">
      <?php if ($search): ?>Showing results for "<strong><?= e($search) ?></strong>"<?php endif; ?>
      <?php if ($catId && $search): ?> in <strong><?= e($activeCatName) ?></strong><?php elseif ($catId): ?>Browsing <strong><?= e($activeCatName) ?></strong><?php endif; ?>
      &mdash; <a href="<?= BASE_URL ?>/public/catalog.php">Clear</a>
    </div>
    <?php endif; ?>
  </div>

  <?php if (empty($products)): ?>
  <div class="empty-wrap">
    <div class="icon">🔍</div>
    <h3>No products found</h3>
    <p>Try a different search term or browse a different category.</p>
    <a href="<?= BASE_URL ?>/public/catalog.php" class="btn btn-primary">Browse All Products</a>
  </div>

  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $p):
      $qty = (int)$p['stock_qty'];
      $min = (int)$p['min_stock_level'];
      if ($qty <= 0) {
          $stockLabel = 'Out of Stock'; $stockClass = 'cs-out'; $ribbon = '<span class="card-ribbon">Out of Stock</span>';
      } elseif ($qty <= $min) {
          $stockLabel = "Only {$qty} left"; $stockClass = 'cs-low'; $ribbon = '<span class="card-ribbon low">Low Stock</span>';
      } else {
          $stockLabel = 'In Stock'; $stockClass = 'cs-ok'; $ribbon = '';
      }
    ?>
    <a href="<?= BASE_URL ?>/public/product.php?id=<?= $p['id'] ?>" class="product-card">
      <div class="card-img-wrap">
        <?php if ($p['image'] && file_exists(UPLOAD_PATH . '/' . $p['image'])): ?>
          <img src="<?= UPLOAD_URL ?>/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>">
        <?php else: ?>
          <div class="card-placeholder"><?= productEmoji($p['category_name'] ?? '', $p['type']) ?></div>
        <?php endif; ?>
        <div class="card-overlay"><div class="card-overlay-btn">View Details</div></div>
        <?= $ribbon ?>
      </div>

      <div class="card-body">
        <?php if ($p['category_name']): ?>
        <div class="card-cat"><?= e($p['category_name']) ?></div>
        <?php endif; ?>
        <div class="card-name"><?= e($p['name']) ?></div>
        <div class="card-footer">
          <div class="card-price"><?= formatCurrency((float)$p['selling_price']) ?></div>
          <span class="card-stock <?= $stockClass ?>"><?= $stockLabel ?></span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($pg['total_pages'] > 1): ?>
  <div class="pg-row">
    <div class="pg-info">
      Showing <?= $pg['offset'] + 1 ?>–<?= min($pg['offset'] + $pg['per_page'], $total) ?> of <?= number_format($total) ?> products
    </div>
    <?= paginationLinks($pg, $baseLink) ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</main>

<!-- ─── Footer ────────────────────────────────────────── -->
<footer class="store-footer">
  <div class="footer-inner">
    <div>
      <div class="footer-brand">
        <div class="footer-brand-icon">🎁</div>
        <?= APP_NAME ?>
      </div>
      <p class="footer-desc">Your one-stop destination for thoughtful gifts, trendy clothing, and unique souvenirs. Every purchase is special.</p>
    </div>

    <div class="footer-col">
      <h4>Browse</h4>
      <a href="<?= BASE_URL ?>/public/catalog.php">All Products</a>
      <?php foreach ($categories as $c): ?>
      <a href="<?= BASE_URL ?>/public/catalog.php?cat=<?= $c['id'] ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="footer-col">
      <h4>Info</h4>
      <a href="<?= BASE_URL ?>/public/catalog.php">New Arrivals</a>
      <a href="<?= BASE_URL ?>/public/catalog.php?cat=5">Seasonal Gifts</a>
      <a href="<?= BASE_URL ?>/login.php">Staff Login</a>
    </div>

    <div class="footer-col">
      <h4>Contact &amp; Location</h4>
      <div class="footer-contact-item">📍 <span>123 Gift Street, Connaught Place<br>New Delhi, India 110001</span></div>
      <div class="footer-contact-item">📞 <span><a href="tel:+911234567890">+91 12345 67890</a></span></div>
      <div class="footer-contact-item">✉️ <span><a href="mailto:hello@giftz.in">hello@giftz.in</a></span></div>
      <div class="footer-contact-item">🕐 <span>Mon – Sat: 10 AM – 8 PM<br>Sun: 11 AM – 6 PM</span></div>
    </div>
  </div>

  <div class="footer-bottom">
    <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</span>
    <span>All prices in <?= CURRENCY_CODE ?></span>
  </div>
</footer>

<script>
(function () {
  const bar   = document.getElementById('announceBar');
  const btn   = document.getElementById('announceDismiss');
  const msgs  = bar.querySelectorAll('.announce-msg');
  const dots  = bar.querySelectorAll('.announce-dot');

  if (localStorage.getItem('giftz_announce_v1')) {
    bar.classList.add('hidden');
    return;
  }

  let current = 0;

  function goTo(n) {
    msgs[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = n;
    msgs[current].classList.add('active');
    dots[current].classList.add('active');
  }

  dots.forEach((d, i) => d.addEventListener('click', () => { goTo(i); resetTimer(); }));

  btn.addEventListener('click', () => {
    bar.classList.add('hidden');
    localStorage.setItem('giftz_announce_v1', '1');
  });

  let timer = setInterval(() => goTo((current + 1) % msgs.length), 4500);
  function resetTimer() { clearInterval(timer); timer = setInterval(() => goTo((current + 1) % msgs.length), 4500); }
})();
</script>
</body>
</html>
