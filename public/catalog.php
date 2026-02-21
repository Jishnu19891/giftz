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

// 4 featured products for hero showcase (one per category, with images)
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
      background: linear-gradient(135deg, #0F1120 0%, #1E1760 45%, #2E1250 100%);
      position: relative;
      overflow: hidden;
      padding-bottom: 0;
    }

    /* Animated background blobs */
    .hero-blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(70px);
      pointer-events: none;
      animation: blobPulse 7s ease-in-out infinite;
    }
    .hero-blob-1 { width: 500px; height: 500px; background: rgba(108,99,255,.22); top: -100px; left: -100px; animation-delay: 0s; }
    .hero-blob-2 { width: 400px; height: 400px; background: rgba(255,101,132,.18); top: 50px; right: -80px; animation-delay: 2.5s; }
    .hero-blob-3 { width: 300px; height: 300px; background: rgba(139,92,246,.2); bottom: 0; left: 40%; animation-delay: 5s; }
    @keyframes blobPulse {
      0%, 100% { transform: scale(1) translate(0, 0); opacity: .8; }
      50%       { transform: scale(1.12) translate(15px, -10px); opacity: 1; }
    }

    /* Floating emoji decorations */
    .hero-floats { position: absolute; inset: 0; pointer-events: none; z-index: 1; }
    .hero-floats span {
      position: absolute;
      font-size: 1.8rem;
      opacity: .12;
      animation: floatUp 6s ease-in-out infinite;
    }
    .hero-floats span:nth-child(1) { left: 6%;  top: 18%; animation-delay: 0s;   }
    .hero-floats span:nth-child(2) { left: 18%; top: 72%; animation-delay: 1.4s; }
    .hero-floats span:nth-child(3) { left: 88%; top: 12%; animation-delay: 0.7s; }
    .hero-floats span:nth-child(4) { left: 80%; top: 68%; animation-delay: 2.1s; }
    .hero-floats span:nth-child(5) { left: 50%; top: 8%;  animation-delay: 3.5s; }
    @keyframes floatUp {
      0%, 100% { transform: translateY(0) rotate(0deg);   opacity: .12; }
      50%       { transform: translateY(-18px) rotate(8deg); opacity: .22; }
    }

    /* Two-column layout */
    .hero-layout {
      position: relative;
      z-index: 2;
      max-width: 1280px;
      margin: 0 auto;
      padding: 5rem 2rem 4rem;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 4rem;
      align-items: center;
    }

    /* Left: content */
    .hero-content { display: flex; flex-direction: column; gap: 1.5rem; }
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      background: rgba(108,99,255,.25);
      border: 1px solid rgba(108,99,255,.4);
      border-radius: 100px;
      padding: .35rem 1rem;
      font-size: .75rem;
      font-weight: 700;
      color: #C4BEFF;
      letter-spacing: .06em;
      text-transform: uppercase;
      width: fit-content;
    }
    .hero h1 {
      font-size: clamp(2.2rem, 4vw, 3.4rem);
      font-weight: 800;
      color: #fff;
      line-height: 1.12;
      letter-spacing: -.03em;
      margin: 0;
    }
    .hero h1 .grad {
      background: linear-gradient(90deg, #A78BFA, #F472B6, #FB923C);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .hero-sub {
      font-size: 1rem;
      color: rgba(255,255,255,.55);
      line-height: 1.7;
      max-width: 420px;
    }

    /* Search bar */
    .hero-search {
      display: flex;
      background: rgba(255,255,255,.07);
      border: 1.5px solid rgba(255,255,255,.15);
      border-radius: 14px;
      overflow: hidden;
      backdrop-filter: blur(10px);
      max-width: 480px;
      transition: border-color .2s;
    }
    .hero-search:focus-within { border-color: rgba(108,99,255,.7); }
    .hero-search input {
      flex: 1;
      background: transparent;
      border: none;
      padding: .9rem 1.1rem;
      font-size: .9rem;
      font-family: inherit;
      color: #fff;
      outline: none;
    }
    .hero-search input::placeholder { color: rgba(255,255,255,.35); }
    .hero-search button {
      background: linear-gradient(135deg, var(--accent), #7C3AED);
      color: #fff;
      border: none;
      padding: 0 1.3rem;
      font-size: .88rem;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      display: flex;
      align-items: center;
      gap: .4rem;
      white-space: nowrap;
      transition: opacity .2s;
    }
    .hero-search button:hover { opacity: .88; }

    /* Stats row */
    .hero-stats {
      display: flex;
      gap: 1.5rem;
      padding-top: .5rem;
    }
    .hero-stat {
      display: flex;
      flex-direction: column;
      gap: .1rem;
    }
    .hero-stat strong { font-size: 1.4rem; font-weight: 800; color: #fff; letter-spacing: -.02em; }
    .hero-stat span   { font-size: .72rem; color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: .05em; }

    /* Divider between stats */
    .hero-stat + .hero-stat { padding-left: 1.5rem; border-left: 1px solid rgba(255,255,255,.1); }

    /* Right: product showcase */
    .hero-showcase {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    .showcase-card {
      border-radius: 16px;
      overflow: hidden;
      position: relative;
      aspect-ratio: 1;
      box-shadow: 0 12px 40px rgba(0,0,0,.5);
      transition: transform .3s ease;
    }
    .showcase-card:nth-child(2) { margin-top: 2rem; }
    .showcase-card:nth-child(4) { margin-top: -2rem; }
    .showcase-card:hover { transform: scale(1.04); }
    .showcase-card img {
      width: 100%; height: 100%;
      object-fit: cover;
      display: block;
    }
    .showcase-card-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(0,0,0,.7) 0%, transparent 55%);
      display: flex;
      align-items: flex-end;
      padding: .85rem;
    }
    .showcase-card-label {
      font-size: .72rem;
      font-weight: 700;
      color: #fff;
      line-height: 1.3;
    }
    .showcase-card-price {
      font-size: .82rem;
      font-weight: 800;
      color: #C4BEFF;
      margin-top: .1rem;
    }

    /* Wave divider */
    .hero-wave {
      position: relative;
      z-index: 2;
      line-height: 0;
    }
    .hero-wave svg { display: block; width: 100%; }

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

    /* Image area — padding-top trick ensures square on all browsers */
    .card-img-wrap {
      position: relative;
      width: 100%;
      padding-top: 100%;
      overflow: hidden;
      background: #F3F4F6;
    }
    .card-img-wrap img,
    .card-img-wrap .card-placeholder,
    .card-img-wrap .card-overlay {
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
    }
    .card-img-wrap img {
      object-fit: cover;
      display: block;
      transition: transform .35s ease;
    }
    .product-card:hover .card-img-wrap img { transform: scale(1.06); }

    .card-placeholder {
      display: grid;
      place-items: center;
      font-size: 3.5rem;
      background: linear-gradient(135deg, #F0F0F8, #E4E4F0);
      transition: transform .35s ease;
      top: 0; left: 0; width: 100%; height: 100%;
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
      .hero-layout { grid-template-columns: 1fr; gap: 2rem; padding: 3rem 1rem 0; }
      .hero-showcase { display: none; }
      .hero h1 { font-size: 2rem; }
      .hero-stats { gap: 1rem; }
      .hero-stat + .hero-stat { padding-left: 1rem; }
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

  <!-- Background blobs -->
  <div class="hero-blob hero-blob-1"></div>
  <div class="hero-blob hero-blob-2"></div>
  <div class="hero-blob hero-blob-3"></div>

  <!-- Floating emoji decorations -->
  <div class="hero-floats" aria-hidden="true">
    <span>🎁</span><span>🎀</span><span>🎄</span><span>💝</span><span>✨</span>
  </div>

  <!-- Two-column layout -->
  <div class="hero-layout">

    <!-- Left: content -->
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

    <!-- Right: product showcase -->
    <?php if (!empty($heroProducts)): ?>
    <div class="hero-showcase">
      <?php foreach ($heroProducts as $hp): ?>
      <a href="<?= BASE_URL ?>/public/product.php?id=<?= $hp['id'] ?>" class="showcase-card">
        <?php if ($hp['image'] && file_exists(UPLOAD_PATH . '/' . $hp['image'])): ?>
          <img src="<?= UPLOAD_URL ?>/<?= e($hp['image']) ?>" alt="<?= e($hp['name']) ?>">
        <?php else: ?>
          <div style="width:100%;height:100%;background:rgba(255,255,255,.05);display:grid;place-items:center;font-size:3rem">
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

  <!-- Wave transition -->
  <div class="hero-wave">
    <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
      <path d="M0,30 C360,60 1080,0 1440,30 L1440,60 L0,60 Z" fill="#ffffff"/>
    </svg>
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

<!-- ─── Back to top ──────────────────────────────────── -->
<button id="backToTop" title="Back to top" aria-label="Back to top">↑</button>

<style>
  #backToTop {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), #7C3AED);
    color: #fff;
    border: none;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 18px rgba(108,99,255,.45);
    display: grid;
    place-items: center;
    opacity: 0;
    transform: translateY(12px);
    transition: opacity .3s, transform .3s;
    z-index: 500;
    pointer-events: none;
  }
  #backToTop.visible {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
  }
  #backToTop:hover {
    box-shadow: 0 6px 24px rgba(108,99,255,.65);
    transform: translateY(-2px);
  }
</style>

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
