<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$pdo     = db();
$search  = trim($_GET['q']    ?? '');
$catId   = intval($_GET['cat'] ?? 0);
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 16;

// Sort
$sort    = $_GET['sort'] ?? 'name';
$sortMap = [
    'name'       => 'p.name ASC',
    'price_asc'  => 'p.selling_price ASC',
    'price_desc' => 'p.selling_price DESC',
    'newest'     => 'p.id DESC',
];
$orderBy = $sortMap[$sort] ?? 'p.name ASC';

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
    ORDER BY $orderBy
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$products   = $stmt->fetchAll();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();

// Category emoji map
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

$baseLink = BASE_URL . '/public/catalog.php?q=' . urlencode($search) . '&cat=' . $catId . '&sort=' . urlencode($sort);

// Announcements from DB
$announcements = $pdo->query(
    "SELECT emoji, message FROM announcements WHERE is_active = 1 ORDER BY sort_order ASC, id ASC"
)->fetchAll();

// 4 hero showcase products (one per category, with images)
$heroProducts = $pdo->query("
    SELECT p.id, p.name, p.selling_price, p.image, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'active' AND p.image IS NOT NULL AND p.image != ''
    GROUP BY p.category_id
    ORDER BY RAND()
    LIMIT 4
")->fetchAll();

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
       Giftz Public Storefront — Professional Design
    ═══════════════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: var(--font); background: #F7F8FC; color: #111827; }

    /* ─── Announcement bar ───────────────────────────────── */
    .announce-bar {
      background: #1E1B4B;
      color: rgba(255,255,255,.85);
      padding: .55rem 2.8rem .55rem 1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .78rem;
      font-weight: 500;
      letter-spacing: .02em;
      position: relative;
      transition: max-height .4s ease, padding .35s ease, opacity .3s;
      max-height: 40px;
    }
    .announce-bar.hidden { max-height: 0; padding-top: 0; padding-bottom: 0; opacity: 0; pointer-events: none; }
    .announce-msg { display: none; align-items: center; gap: .5rem; animation: fadeMsg .4s ease; }
    .announce-msg.active { display: flex; }
    @keyframes fadeMsg {
      from { opacity: 0; transform: translateY(3px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .announce-dots { display: flex; gap: .3rem; margin-left: .65rem; }
    .announce-dot {
      width: 4px; height: 4px; border-radius: 50%;
      background: rgba(255,255,255,.3); cursor: pointer; border: none; padding: 0;
      transition: background .2s, transform .2s;
    }
    .announce-dot.active { background: #fff; transform: scale(1.5); }
    .announce-dismiss {
      position: absolute; right: .85rem; top: 50%; transform: translateY(-50%);
      background: rgba(255,255,255,.1); border: none; color: rgba(255,255,255,.6);
      width: 20px; height: 20px; border-radius: 50%; font-size: .8rem; cursor: pointer;
      display: grid; place-items: center; transition: background .2s;
    }
    .announce-dismiss:hover { background: rgba(255,255,255,.22); }

    /* ─── Navbar ─────────────────────────────────────────── */
    .navbar {
      position: sticky;
      top: 0;
      z-index: 200;
      background: #0F172A;
      height: 64px;
      display: flex;
      align-items: center;
      padding: 0 2rem;
      gap: 2rem;
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
    .nav-search {
      flex: 1; max-width: 460px; margin: 0 auto;
      display: flex; align-items: center;
      background: rgba(255,255,255,.07);
      border-radius: 8px; overflow: hidden;
      border: 1px solid rgba(255,255,255,.1);
      transition: border-color .2s, background .2s;
    }
    .nav-search:focus-within { background: rgba(255,255,255,.11); border-color: rgba(99,102,241,.55); }
    .nav-search input {
      flex: 1; background: transparent; border: none;
      padding: .6rem 1rem; color: #fff; font-size: .85rem;
      font-family: inherit; outline: none;
    }
    .nav-search input::placeholder { color: rgba(255,255,255,.28); }
    .nav-search button {
      background: transparent; border: none;
      padding: 0 .85rem; color: rgba(255,255,255,.4);
      font-size: .95rem; cursor: pointer;
      display: flex; align-items: center;
      transition: color .2s;
    }
    .nav-search button:hover { color: rgba(255,255,255,.85); }
    .nav-tagline {
      font-size: .74rem; color: rgba(255,255,255,.28);
      flex-shrink: 0; white-space: nowrap; letter-spacing: .02em;
    }

    /* ─── Trust bar ──────────────────────────────────────── */
    .trust-bar {
      background: #fff;
      border-bottom: 1px solid #E5E7EB;
      padding: .5rem 2rem;
    }
    .trust-inner {
      max-width: 1280px; margin: 0 auto;
      display: flex; align-items: center; justify-content: center;
      gap: 2rem; flex-wrap: wrap;
    }
    .trust-item {
      display: flex; align-items: center; gap: .4rem;
      font-size: .74rem; font-weight: 500; color: #374151;
      white-space: nowrap;
    }
    .trust-item .t-icon { font-size: .9rem; }
    .trust-divider { width: 1px; height: 13px; background: #E5E7EB; }

    /* ─── Hero ───────────────────────────────────────────── */
    .hero {
      background: linear-gradient(135deg, #0C0F1E 0%, #151840 50%, #1B1050 100%);
      position: relative;
      overflow: hidden;
      padding-bottom: 0;
    }
    .hero-blob {
      position: absolute; border-radius: 50%;
      filter: blur(80px); pointer-events: none;
      animation: blobPulse 9s ease-in-out infinite;
    }
    .hero-blob-1 { width: 420px; height: 420px; background: rgba(99,102,241,.14); top: -80px; left: -60px; }
    .hero-blob-2 { width: 340px; height: 340px; background: rgba(236,72,153,.09); top: 20px; right: -60px; animation-delay: 3s; }
    .hero-blob-3 { width: 260px; height: 260px; background: rgba(139,92,246,.11); bottom: 0; left: 40%; animation-delay: 6s; }
    @keyframes blobPulse {
      0%,100% { transform: scale(1) translate(0,0); opacity: .7; }
      50%      { transform: scale(1.1) translate(12px,-8px); opacity: 1; }
    }
    .hero-floats { position: absolute; inset: 0; pointer-events: none; z-index: 1; }
    .hero-floats span {
      position: absolute; font-size: 1.5rem; opacity: .06;
      animation: floatUp 7s ease-in-out infinite;
    }
    .hero-floats span:nth-child(1) { left: 6%;  top: 20%; }
    .hero-floats span:nth-child(2) { left: 17%; top: 70%; animation-delay: 1.6s; }
    .hero-floats span:nth-child(3) { left: 88%; top: 15%; animation-delay: 0.8s; }
    .hero-floats span:nth-child(4) { left: 80%; top: 65%; animation-delay: 2.4s; }
    .hero-floats span:nth-child(5) { left: 50%; top: 10%; animation-delay: 4s; }
    @keyframes floatUp {
      0%,100% { transform: translateY(0) rotate(0deg); opacity: .06; }
      50%      { transform: translateY(-16px) rotate(6deg); opacity: .12; }
    }
    .hero-layout {
      position: relative; z-index: 2;
      max-width: 1280px; margin: 0 auto;
      padding: 5rem 2rem 4rem;
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 5rem; align-items: center;
    }
    .hero-content { display: flex; flex-direction: column; gap: 1.4rem; }
    .hero-badge {
      display: inline-flex; align-items: center; gap: .4rem;
      background: rgba(129,140,248,.15); border: 1px solid rgba(129,140,248,.28);
      border-radius: 100px; padding: .3rem .9rem;
      font-size: .7rem; font-weight: 600; color: #A5B4FC;
      letter-spacing: .08em; text-transform: uppercase; width: fit-content;
    }
    .hero h1 {
      font-size: clamp(2rem, 3.8vw, 3.2rem);
      font-weight: 800; color: #fff;
      line-height: 1.1; letter-spacing: -.03em; margin: 0;
    }
    .hero h1 .grad {
      background: linear-gradient(90deg, #A78BFA 0%, #F472B6 60%, #FB923C 100%);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .hero-sub { font-size: .95rem; color: rgba(255,255,255,.48); line-height: 1.75; max-width: 400px; }
    .hero-search {
      display: flex; align-items: stretch;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.11);
      border-radius: 12px; overflow: hidden;
      backdrop-filter: blur(12px);
      max-width: 460px; transition: border-color .2s;
    }
    .hero-search:focus-within { border-color: rgba(99,102,241,.45); }
    .hero-search input {
      flex: 1; background: transparent; border: none;
      padding: .95rem 1.1rem; font-size: .88rem;
      font-family: inherit; color: #fff; outline: none;
    }
    .hero-search input::placeholder { color: rgba(255,255,255,.26); }
    .hero-search button {
      background: linear-gradient(135deg, #6366F1, #7C3AED);
      color: #fff; border: none; padding: 0 1.4rem;
      font-size: .85rem; font-weight: 600; cursor: pointer;
      font-family: inherit; display: flex; align-items: center; gap: .4rem;
      white-space: nowrap; letter-spacing: .01em; transition: opacity .2s;
    }
    .hero-search button:hover { opacity: .9; }
    .hero-stats { display: flex; gap: 2rem; padding-top: .25rem; }
    .hero-stat { display: flex; flex-direction: column; gap: .1rem; }
    .hero-stat strong { font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -.03em; }
    .hero-stat span   { font-size: .67rem; color: rgba(255,255,255,.36); text-transform: uppercase; letter-spacing: .08em; }
    .hero-stat + .hero-stat { padding-left: 2rem; border-left: 1px solid rgba(255,255,255,.08); }

    /* Showcase */
    .hero-showcase { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .showcase-card {
      border-radius: 14px; overflow: hidden; position: relative;
      aspect-ratio: 1; box-shadow: 0 8px 32px rgba(0,0,0,.5);
      transition: transform .35s cubic-bezier(.34,1.56,.64,1);
    }
    .showcase-card:nth-child(2) { margin-top: 1.75rem; }
    .showcase-card:nth-child(4) { margin-top: -1.75rem; }
    .showcase-card:hover { transform: translateY(-6px) scale(1.02); }
    .showcase-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .showcase-card-overlay {
      position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(0,0,0,.75) 0%, rgba(0,0,0,.1) 50%, transparent 100%);
      display: flex; align-items: flex-end; padding: .9rem;
    }
    .showcase-card-label { font-size: .7rem; font-weight: 600; color: #fff; line-height: 1.3; }
    .showcase-card-price { font-size: .8rem; font-weight: 800; color: #C4BEFF; margin-top: .1rem; }

    /* Wave divider */
    .hero-wave { position: relative; z-index: 2; line-height: 0; }
    .hero-wave svg { display: block; width: 100%; }

    /* ─── Category bar ───────────────────────────────────── */
    .cat-bar-wrap {
      background: #fff;
      border-bottom: 1px solid #E5E7EB;
      position: sticky;
      top: 64px;
      z-index: 150;
    }
    .cat-bar {
      max-width: 1280px; margin: 0 auto;
      padding: .65rem 2rem;
      display: flex; gap: .4rem;
      overflow-x: auto; scrollbar-width: none; align-items: center;
    }
    .cat-bar::-webkit-scrollbar { display: none; }
    .cat-btn {
      display: inline-flex; align-items: center; gap: .35rem;
      padding: .42rem 1rem;
      font-size: .8rem; font-weight: 500; color: #6B7280;
      background: #F3F4F6; border-radius: 100px;
      border: 1px solid transparent; text-decoration: none;
      white-space: nowrap; flex-shrink: 0;
      transition: background .15s ease, color .15s ease, box-shadow .15s ease;
    }
    .cat-btn:hover { background: #E9EAFF; color: #4F46E5; }
    .cat-btn.active {
      background: #4F46E5; color: #fff;
      border-color: #4F46E5;
      box-shadow: 0 2px 8px rgba(79,70,229,.3);
    }

    /* ─── Main content ───────────────────────────────────── */
    .store-wrap {
      max-width: 1280px;
      margin: 0 auto;
      padding: 2rem 2rem 5rem;
    }

    /* ─── Section header ─────────────────────────────────── */
    .section-hd {
      display: flex; align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem; flex-wrap: wrap; gap: .75rem;
    }
    .section-hd-left { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
    .section-hd h2 { font-size: 1.15rem; font-weight: 700; color: #111827; }
    .count-pill {
      display: inline-flex; align-items: center;
      background: #EEF2FF; color: #4F46E5;
      font-size: .7rem; font-weight: 700;
      padding: .2em .65em; border-radius: 6px;
    }
    .filter-info { font-size: .8rem; color: #9CA3AF; }
    .filter-info a { color: #4F46E5; text-decoration: none; font-weight: 500; }
    .filter-info a:hover { text-decoration: underline; }
    .sort-select {
      appearance: none; background: #fff;
      border: 1px solid #E5E7EB; border-radius: 8px;
      padding: .42rem 2rem .42rem .8rem; font-size: .8rem;
      font-family: inherit; color: #374151; cursor: pointer;
      font-weight: 500; outline: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 16 16'%3E%3Cpath stroke='%239CA3AF' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' d='M4 6l4 4 4-4'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right .5rem center; background-size: 14px;
      transition: border-color .2s;
    }
    .sort-select:focus { border-color: #4F46E5; box-shadow: 0 0 0 3px rgba(79,70,229,.1); }

    /* ─── Product grid ───────────────────────────────────── */
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2.5rem;
    }
    .product-card {
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,.06);
      display: flex; flex-direction: column;
      text-decoration: none; color: inherit;
      border: 1px solid #EAECF0;
      transition: box-shadow .22s ease, transform .22s ease, border-color .22s ease;
    }
    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(0,0,0,.09), 0 4px 10px rgba(0,0,0,.05);
      border-color: #C7D2FE;
      color: inherit;
    }

    /* Square image */
    .card-img-wrap {
      position: relative;
      width: 100%; padding-top: 100%;
      overflow: hidden; background: #F3F4F6;
    }
    .card-img-wrap img,
    .card-img-wrap .card-placeholder {
      position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    }
    .card-img-wrap img {
      object-fit: cover; display: block;
      transition: transform .4s cubic-bezier(.25,.46,.45,.94);
    }
    .product-card:hover .card-img-wrap img { transform: scale(1.05); }
    .card-placeholder {
      display: grid; place-items: center;
      font-size: 3.5rem;
      background: linear-gradient(145deg, #EEF2FF, #E0E7FF);
      transition: transform .4s cubic-bezier(.25,.46,.45,.94);
    }
    .product-card:hover .card-placeholder { transform: scale(1.06); }

    /* Corner arrow — replaces dark overlay */
    .card-arrow {
      position: absolute; bottom: 0; right: 0;
      width: 38px; height: 38px;
      background: #4F46E5; color: #fff;
      border-radius: 12px 0 0 0;
      display: grid; place-items: center;
      font-size: 1rem; line-height: 1;
      opacity: 0; transform: translate(4px,4px);
      transition: opacity .22s ease, transform .22s ease;
    }
    .product-card:hover .card-arrow { opacity: 1; transform: translate(0,0); }

    /* Stock ribbon */
    .card-ribbon {
      position: absolute; top: .65rem; left: .65rem;
      background: #DC2626; color: #fff;
      font-size: .63rem; font-weight: 700;
      padding: .22em .65em; border-radius: 6px;
      letter-spacing: .04em; text-transform: uppercase;
    }
    .card-ribbon.low { background: #D97706; }

    /* Card body */
    .card-body {
      padding: 1rem 1rem 1.05rem;
      flex: 1; display: flex; flex-direction: column; gap: .25rem;
    }
    .card-cat {
      font-size: .65rem; font-weight: 600;
      text-transform: uppercase; letter-spacing: .08em; color: #4F46E5;
    }
    .card-name {
      font-size: .92rem; font-weight: 600; line-height: 1.4;
      color: #111827;
      display: -webkit-box; -webkit-line-clamp: 2;
      -webkit-box-orient: vertical; overflow: hidden;
    }
    .card-footer {
      display: flex; align-items: center;
      justify-content: space-between;
      margin-top: auto; padding-top: .75rem;
    }
    .card-price {
      font-size: 1.1rem; font-weight: 800;
      color: #111827; letter-spacing: -.02em;
    }
    .card-stock {
      font-size: .67rem; font-weight: 600;
      padding: .2em .6em; border-radius: 6px;
    }
    .cs-ok  { background: #ECFDF5; color: #059669; }
    .cs-low { background: #FFFBEB; color: #D97706; }
    .cs-out { background: #FEF2F2; color: #DC2626; }

    /* ─── Empty state ────────────────────────────────────── */
    .empty-wrap {
      background: #fff; border-radius: 16px;
      padding: 5rem 2rem; text-align: center;
      border: 1px solid #EAECF0;
      box-shadow: 0 1px 3px rgba(0,0,0,.04);
    }
    .empty-wrap .icon { font-size: 3rem; opacity: .28; margin-bottom: 1.25rem; }
    .empty-wrap h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: .5rem; }
    .empty-wrap p  { font-size: .88rem; color: #9CA3AF; margin-bottom: 1.5rem; line-height: 1.65; }

    /* ─── Pagination ─────────────────────────────────────── */
    .pg-row {
      display: flex; align-items: center;
      justify-content: space-between;
      gap: 1rem; flex-wrap: wrap;
    }
    .pg-info { font-size: .82rem; color: #9CA3AF; }

    /* ─── Footer ─────────────────────────────────────────── */
    .store-footer {
      background: #0F172A;
      color: rgba(255,255,255,.45);
      padding: 3.5rem 2rem 2rem;
    }
    .footer-inner {
      max-width: 1280px; margin: 0 auto;
      display: grid;
      grid-template-columns: 1.7fr 1fr 1fr 1.2fr;
      gap: 3rem; padding-bottom: 2.5rem;
      border-bottom: 1px solid rgba(255,255,255,.07);
    }
    .footer-brand {
      display: flex; align-items: center; gap: .6rem;
      color: #fff; font-weight: 700; font-size: 1rem;
      margin-bottom: .85rem; letter-spacing: -.01em;
    }
    .footer-brand-icon {
      width: 30px; height: 30px;
      background: linear-gradient(135deg, #818CF8, #6366F1);
      border-radius: 7px; display: grid; place-items: center; font-size: .85rem;
    }
    .footer-desc { font-size: .82rem; line-height: 1.78; color: rgba(255,255,255,.38); max-width: 260px; }
    .footer-col h4 {
      color: rgba(255,255,255,.6); font-size: .7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .1em; margin-bottom: 1rem;
    }
    .footer-col a {
      display: block; font-size: .82rem;
      color: rgba(255,255,255,.38); text-decoration: none;
      margin-bottom: .55rem; transition: color .2s;
    }
    .footer-col a:hover { color: rgba(255,255,255,.85); }
    .footer-contact-item {
      display: flex; align-items: flex-start; gap: .55rem;
      font-size: .82rem; color: rgba(255,255,255,.38);
      margin-bottom: .7rem; line-height: 1.6;
    }
    .footer-contact-item a { color: rgba(255,255,255,.38); text-decoration: none; transition: color .2s; }
    .footer-contact-item a:hover { color: rgba(255,255,255,.85); }
    .footer-bottom {
      max-width: 1280px; margin: 1.75rem auto 0;
      display: flex; align-items: center; justify-content: space-between;
      font-size: .75rem; color: rgba(255,255,255,.22);
      flex-wrap: wrap; gap: .5rem;
    }

    /* ─── Page loader ────────────────────────────────────── */
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
    @media (max-width: 1024px) {
      .footer-inner { grid-template-columns: 1fr 1fr; }
      .footer-inner > :first-child { grid-column: 1 / -1; }
    }
    @media (max-width: 768px) {
      .hero-layout { padding: 3.5rem 1.5rem 0; }
      .showcase-card:nth-child(2) { margin-top: 1.25rem; }
      .showcase-card:nth-child(4) { margin-top: -1.25rem; }
      .trust-inner { gap: 1.25rem; }
      .trust-divider { display: none; }
    }
    @media (max-width: 640px) {
      .navbar { padding: 0 .85rem; gap: .75rem; height: 56px; }
      .nav-tagline { display: none; }
      .nav-brand-icon { width: 30px; height: 30px; }
      .trust-bar { padding: .45rem 1rem; }
      .trust-item:nth-child(n+4) { display: none; }
      .announce-bar { padding: .5rem 2.4rem .5rem 1rem; font-size: .75rem; }
      .announce-dismiss { right: .55rem; }
      .hero-layout { grid-template-columns: 1fr; gap: 1.5rem; padding: 2.5rem 1rem 0; }
      .hero-showcase { display: none; }
      .hero h1 { font-size: 1.9rem; }
      .hero-sub { font-size: .88rem; max-width: 100%; }
      .hero-badge { font-size: .68rem; padding: .28rem .8rem; }
      .hero-search { max-width: 100%; }
      .hero-search input { padding: .8rem 1rem; }
      .hero-search button { padding: 0 1rem; font-size: .82rem; }
      .hero-stats { gap: 1rem; }
      .hero-stat + .hero-stat { padding-left: 1rem; }
      .hero-stat strong { font-size: 1.2rem; }
      .cat-bar-wrap { top: 56px; }
      .cat-bar { padding: .55rem .75rem; }
      .cat-btn { padding: .38rem .85rem; font-size: .76rem; }
      .store-wrap { padding: 1.25rem .9rem 3rem; }
      .section-hd { margin-bottom: 1rem; gap: .5rem; }
      .section-hd h2 { font-size: 1rem; }
      .sort-select { display: none; }
      .product-grid { grid-template-columns: repeat(2, 1fr); gap: .75rem; margin-bottom: 1.75rem; }
      .card-body { padding: .7rem .8rem .85rem; }
      .card-name { font-size: .85rem; }
      .card-price { font-size: 1rem; }
      .card-stock { font-size: .65rem; }
      .card-arrow { display: none; }
      .pg-row { flex-direction: column; align-items: flex-start; gap: .75rem; }
      .footer-inner { grid-template-columns: 1fr; gap: 1.5rem; }
      .store-footer { padding: 2rem 1rem 1.5rem; }
      .footer-bottom { flex-direction: column; gap: .35rem; }
      #backToTop { bottom: 1.25rem; right: 1.25rem; width: 36px; height: 36px; }
    }
    @media (max-width: 480px) {
      .navbar { height: 54px; }
      .cat-bar-wrap { top: 54px; }
      .hero h1 { font-size: 1.65rem; }
      .hero-stats { flex-wrap: wrap; column-gap: .75rem; row-gap: .4rem; }
      .hero-stat + .hero-stat { border-left: none; padding-left: 0; }
      .trust-item:nth-child(n+3) { display: none; }
    }
    @media (max-width: 360px) {
      .navbar { padding: 0 .65rem; gap: .5rem; }
      .nav-brand-icon { width: 26px; height: 26px; font-size: .8rem; }
      .product-grid { grid-template-columns: 1fr; }
      .hero h1 { font-size: 1.5rem; }
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
    <?php if ($sort !== 'name'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <input type="search" name="q" placeholder="Search products..." value="<?= e($search) ?>" autocomplete="off">
    <button type="submit">🔍</button>
  </form>

  <span class="nav-tagline">Prices in <?= CURRENCY_CODE ?></span>
</header>

<!-- ─── Trust bar ────────────────────────────────────── -->
<div class="trust-bar">
  <div class="trust-inner">
    <div class="trust-item"><span class="t-icon">🚚</span> Free delivery above ₹499</div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="t-icon">↩</span> Easy 7-day returns</div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="t-icon">✓</span> 100% authentic products</div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="t-icon">🔒</span> Secure checkout</div>
  </div>
</div>

<!-- ─── Hero (landing page only) ─────────────────────── -->
<?php if (!$search && !$catId && $page === 1): ?>
<section class="hero">

  <div class="hero-blob hero-blob-1"></div>
  <div class="hero-blob hero-blob-2"></div>
  <div class="hero-blob hero-blob-3"></div>

  <div class="hero-floats" aria-hidden="true">
    <span>🎁</span><span>🎀</span><span>🎄</span><span>💝</span><span>✨</span>
  </div>

  <div class="hero-layout">

    <div class="hero-content">
      <div class="hero-badge">✨ New arrivals every week</div>

      <h1>Find the <span class="grad">Perfect Gift</span><br>for Everyone</h1>

      <p class="hero-sub">Thoughtful gifts, trendy clothing &amp; unique accessories — curated for every occasion and budget.</p>

      <form class="hero-search" method="GET" action="">
        <input type="search" name="q" placeholder="Search gifts, clothing, souvenirs..." autocomplete="off">
        <button type="submit">🔍 Search</button>
      </form>

      <div class="hero-stats">
        <div class="hero-stat">
          <strong><?= number_format($total) ?>+</strong>
          <span>Products</span>
        </div>
        <div class="hero-stat">
          <strong><?= count($categories) ?></strong>
          <span>Categories</span>
        </div>
        <div class="hero-stat">
          <strong>100%</strong>
          <span>Authentic</span>
        </div>
      </div>
    </div>

    <?php if (!empty($heroProducts)): ?>
    <div class="hero-showcase">
      <?php foreach ($heroProducts as $hp): ?>
      <a href="<?= BASE_URL ?>/public/product.php?id=<?= $hp['id'] ?>" class="showcase-card">
        <?php if ($hp['image'] && file_exists(UPLOAD_PATH . '/' . $hp['image'])): ?>
          <img src="<?= UPLOAD_URL ?>/<?= e($hp['image']) ?>" alt="<?= e($hp['name']) ?>">
        <?php else: ?>
          <div style="width:100%;height:100%;background:rgba(255,255,255,.04);display:grid;place-items:center;font-size:2.8rem">
            <?= productEmoji($hp['category_name'] ?? '', '') ?>
          </div>
        <?php endif; ?>
        <div class="showcase-card-overlay">
          <div>
            <div class="showcase-card-label"><?= e($hp['name']) ?></div>
            <div class="showcase-card-price"><?= formatCurrency((float)$hp['selling_price']) ?></div>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>

  <div class="hero-wave">
    <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
      <path d="M0,20 C480,60 960,0 1440,30 L1440,60 L0,60 Z" fill="#F7F8FC"/>
    </svg>
  </div>

</section>
<?php endif; ?>

<!-- ─── Category bar ──────────────────────────────────── -->
<div class="cat-bar-wrap">
  <nav class="cat-bar">
    <a href="<?= BASE_URL ?>/public/catalog.php<?= $search ? '?q=' . urlencode($search) : '' ?><?= ($sort && $sort !== 'name') ? ($search ? '&' : '?') . 'sort=' . urlencode($sort) : '' ?>"
       class="cat-btn <?= $catId === 0 ? 'active' : '' ?>">
      All Products
    </a>
    <?php foreach ($categories as $c):
      $emoji = $catEmojis[strtolower($c['name'])] ?? '🏷';
    ?>
    <a href="<?= BASE_URL ?>/public/catalog.php?cat=<?= $c['id'] ?><?= $search ? '&q=' . urlencode($search) : '' ?><?= ($sort && $sort !== 'name') ? '&sort=' . urlencode($sort) : '' ?>"
       class="cat-btn <?= $catId === (int)$c['id'] ? 'active' : '' ?>">
      <?= $emoji ?> <?= e($c['name']) ?>
    </a>
    <?php endforeach; ?>
  </nav>
</div>

<!-- ─── Products ─────────────────────────────────────── -->
<main class="store-wrap">

  <div class="section-hd">
    <div class="section-hd-left">
      <h2><?= $search ? 'Search Results' : e($activeCatName) ?></h2>
      <span class="count-pill"><?= number_format($total) ?></span>
      <?php if ($search || $catId): ?>
      <div class="filter-info">
        <?php if ($search): ?>for "<strong><?= e($search) ?></strong>"<?php endif; ?>
        <?php if ($catId && $search): ?> in <?= e($activeCatName) ?><?php elseif ($catId): ?><?= e($activeCatName) ?><?php endif; ?>
        — <a href="<?= BASE_URL ?>/public/catalog.php">Clear</a>
      </div>
      <?php endif; ?>
    </div>

    <form method="GET" action="">
      <?php if ($catId): ?><input type="hidden" name="cat" value="<?= $catId ?>"><?php endif; ?>
      <?php if ($search): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
      <select name="sort" class="sort-select" onchange="this.form.submit()">
        <option value="name"       <?= $sort === 'name'       ? 'selected' : '' ?>>Sort: A – Z</option>
        <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Sort: Newest</option>
        <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: Low to High</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
      </select>
    </form>
  </div>

  <?php if (empty($products)): ?>
  <div class="empty-wrap">
    <div class="icon">🔍</div>
    <h3>No products found</h3>
    <p>Try a different search term or browse another category.</p>
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
        <div class="card-arrow">→</div>
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
      <a href="<?= BASE_URL ?>/public/catalog.php?sort=newest">New Arrivals</a>
      <a href="<?= BASE_URL ?>/public/catalog.php?sort=price_asc">Best Value</a>
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

<!-- ─── Announcement JS ───────────────────────────────── -->
<?php if (!empty($announcements)): ?>
<script>
(function () {
  const bar  = document.getElementById('announceBar');
  const btn  = document.getElementById('announceDismiss');
  const msgs = bar.querySelectorAll('.announce-msg');
  const dots = bar.querySelectorAll('.announce-dot');

  if (localStorage.getItem('giftz_announce_v1')) {
    bar.classList.add('hidden');
    return;
  }

  let current = 0;

  function goTo(n) {
    msgs[current].classList.remove('active');
    if (dots[current]) dots[current].classList.remove('active');
    current = n;
    msgs[current].classList.add('active');
    if (dots[current]) dots[current].classList.add('active');
  }

  dots.forEach((d, i) => d.addEventListener('click', () => { goTo(i); resetTimer(); }));

  btn.addEventListener('click', () => {
    bar.classList.add('hidden');
    localStorage.setItem('giftz_announce_v1', '1');
  });

  let timer;
  function resetTimer() {
    clearInterval(timer);
    timer = setInterval(() => goTo((current + 1) % msgs.length), 4500);
  }
  if (msgs.length > 1) resetTimer();
})();
</script>
<?php endif; ?>

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
  document.querySelectorAll('.product-card, .page-btn').forEach(el => {
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
