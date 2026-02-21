<?php
// header.php — must be required AFTER requireLogin() and any page-level logic
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/functions.php';

$user        = currentUser();
$lowStock    = getLowStockCount();
$flashMsgs   = getFlash();
$currentPath = $_SERVER['REQUEST_URI'];

// Auto-detect active nav section
function isActive(string $path): string {
    global $currentPath;
    return strpos($currentPath, $path) !== false ? 'active' : '';
}

// Page meta defaults
$pageTitle     = $pageTitle     ?? APP_NAME;
$pageSubtitle  = $pageSubtitle  ?? '';
$breadcrumbs   = $breadcrumbs   ?? [];
$bodyClass     = $bodyClass     ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="robots" content="noindex, nofollow">

  <!-- Google Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
  <?php if ($bodyClass === 'pos-page'): ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pos.css">
  <?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<div class="layout">

<!-- ─── Sidebar ────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">

  <!-- Brand -->
  <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-brand">
    <div class="brand-icon">🎁</div>
    <div class="brand-text">
      <span class="brand-name"><?= APP_NAME ?></span>
      <span class="brand-sub">Inventory System</span>
    </div>
  </a>

  <!-- Nav -->
  <nav class="sidebar-nav">

    <div class="nav-section-label">Main</div>

    <a href="<?= BASE_URL ?>/dashboard.php" class="nav-item <?= isActive('/dashboard') ?>">
      <span class="nav-icon">⊞</span>
      <span class="nav-label">Dashboard</span>
    </a>

    <a href="<?= BASE_URL ?>/sales/pos.php" class="nav-item <?= isActive('/sales/pos') ?>">
      <span class="nav-icon">🛒</span>
      <span class="nav-label">Point of Sale</span>
    </a>

    <div class="nav-section-label">Inventory</div>

    <a href="<?= BASE_URL ?>/products/index.php" class="nav-item <?= isActive('/products') ?>">
      <span class="nav-icon">📦</span>
      <span class="nav-label">Products</span>
      <?php if ($lowStock > 0): ?>
      <span class="nav-badge"><?= $lowStock ?></span>
      <?php endif; ?>
    </a>

    <a href="<?= BASE_URL ?>/categories/index.php" class="nav-item <?= isActive('/categories') ?>">
      <span class="nav-icon">🏷</span>
      <span class="nav-label">Categories</span>
    </a>

    <div class="nav-section-label">Commerce</div>

    <a href="<?= BASE_URL ?>/sales/index.php" class="nav-item <?= isActive('/sales/index') ?>">
      <span class="nav-icon">🧾</span>
      <span class="nav-label">Sales History</span>
    </a>

    <a href="<?= BASE_URL ?>/sales/returns.php" class="nav-item <?= isActive('/sales/returns') ?>">
      <span class="nav-icon">↩</span>
      <span class="nav-label">Returns</span>
    </a>

    <a href="<?= BASE_URL ?>/expenses/index.php" class="nav-item <?= isActive('/expenses') ?>">
      <span class="nav-icon">💸</span>
      <span class="nav-label">Expenses</span>
    </a>

    <a href="<?= BASE_URL ?>/purchases/index.php" class="nav-item <?= isActive('/purchases') ?>">
      <span class="nav-icon">📋</span>
      <span class="nav-label">Purchases</span>
    </a>

    <a href="<?= BASE_URL ?>/suppliers/index.php" class="nav-item <?= isActive('/suppliers') ?>">
      <span class="nav-icon">🏭</span>
      <span class="nav-label">Suppliers</span>
    </a>

    <a href="<?= BASE_URL ?>/customers/index.php" class="nav-item <?= isActive('/customers') ?>">
      <span class="nav-icon">👥</span>
      <span class="nav-label">Customers</span>
    </a>

    <div class="nav-section-label">Analytics</div>

    <a href="<?= BASE_URL ?>/reports/sales.php" class="nav-item <?= isActive('/reports/sales') ?>">
      <span class="nav-icon">📈</span>
      <span class="nav-label">Sales Report</span>
    </a>

    <a href="<?= BASE_URL ?>/reports/inventory.php" class="nav-item <?= isActive('/reports/inventory') ?>">
      <span class="nav-icon">📊</span>
      <span class="nav-label">Inventory Report</span>
    </a>

    <a href="<?= BASE_URL ?>/reports/profit.php" class="nav-item <?= isActive('/reports/profit') ?>">
      <span class="nav-icon">💰</span>
      <span class="nav-label">Profit Report</span>
    </a>

    <a href="<?= BASE_URL ?>/reports/stock_movements.php" class="nav-item <?= isActive('/reports/stock_movements') ?>">
      <span class="nav-icon">📋</span>
      <span class="nav-label">Stock Log</span>
    </a>

    <?php if (isAdmin()): ?>
    <div class="nav-section-label">Admin</div>
    <a href="<?= BASE_URL ?>/users/index.php" class="nav-item <?= isActive('/users') ?>">
      <span class="nav-icon">👤</span>
      <span class="nav-label">Users</span>
    </a>
    <?php endif; ?>

    <div class="nav-section-label">Storefront</div>
    <a href="<?= BASE_URL ?>/public/catalog.php" class="nav-item" target="_blank">
      <span class="nav-icon">🛍</span>
      <span class="nav-label">Public Catalog</span>
    </a>

  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/users/profile.php" class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= e($user['name']) ?></div>
        <div class="user-role"><?= e(ucfirst($user['role'])) ?></div>
      </div>
    </a>
  </div>

</aside>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ─── Main Content ─────────────────────────────────────── -->
<div class="main-content" id="mainContent">

  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-left">
      <button id="sidebarToggle" title="Toggle sidebar">☰</button>

      <!-- Breadcrumb -->
      <nav class="breadcrumb">
        <a href="<?= BASE_URL ?>/dashboard.php">Home</a>
        <?php foreach ($breadcrumbs as $label => $url): ?>
          <span class="sep">›</span>
          <?php if ($url): ?>
            <a href="<?= e($url) ?>"><?= e($label) ?></a>
          <?php else: ?>
            <span class="current"><?= e($label) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </div>

    <div class="topbar-right">
      <!-- Low stock alert -->
      <?php if ($lowStock > 0): ?>
      <a href="<?= BASE_URL ?>/reports/inventory.php" class="topbar-btn" title="<?= $lowStock ?> low stock items">
        ⚠
        <span class="topbar-badge"><?= $lowStock ?></span>
      </a>
      <?php endif; ?>

      <!-- User dropdown -->
      <div class="user-dropdown">
        <button class="user-dropdown-toggle">
          <div class="dropdown-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
          <span class="dropdown-name"><?= e($user['name']) ?></span>
          <span class="dropdown-caret">▾</span>
        </button>
        <div class="dropdown-menu">
          <div style="padding:.75rem 1rem; border-bottom:1px solid var(--border)">
            <div style="font-weight:600;font-size:.875rem"><?= e($user['name']) ?></div>
            <div style="font-size:.78rem;color:var(--text-muted)"><?= e(ucfirst($user['role'])) ?></div>
          </div>
          <a href="<?= BASE_URL ?>/users/profile.php" class="dropdown-item">👤 My Profile</a>
          <?php if (isAdmin()): ?>
          <a href="<?= BASE_URL ?>/users/index.php" class="dropdown-item">⚙ User Management</a>
          <?php endif; ?>
          <div class="dropdown-divider"></div>
          <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item text-danger">⎋ Logout</a>
        </div>
      </div>
    </div>
  </header>

  <!-- Flash Messages -->
  <?php if (!empty($flashMsgs)): ?>
  <div class="flash-container">
    <?php foreach ($flashMsgs as $msg): ?>
    <?php
      $icons = ['success' => '✓', 'error' => '✕', 'warning' => '⚠', 'info' => 'ℹ'];
      $icon  = $icons[$msg['type']] ?? 'ℹ';
    ?>
    <div class="flash flash-<?= e($msg['type']) ?>">
      <span class="flash-icon"><?= $icon ?></span>
      <span class="flash-msg"><?= e($msg['message']) ?></span>
      <button class="flash-close" type="button">×</button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Page Body -->
  <div class="page-body">
