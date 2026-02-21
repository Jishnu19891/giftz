<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (attemptLogin($email, $password)) {
        flash('success', 'Welcome back, ' . $_SESSION['user_name'] . '!');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials or account is inactive.';
    }
}

$flashMsgs = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    body {
      min-height: 100vh;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, #1A1D2E 0%, #2D2F45 40%, #1A1D2E 100%);
      padding: 1rem;
    }

    .login-wrapper {
      display: grid;
      grid-template-columns: 1fr 1fr;
      max-width: 900px;
      width: 100%;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 24px 64px rgba(0,0,0,.4);
    }

    /* Left brand panel */
    .login-brand {
      background: linear-gradient(160deg, #6C63FF 0%, #FF6584 100%);
      padding: 3rem 2.5rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 1.5rem;
      color: #fff;
      position: relative;
      overflow: hidden;
    }

    .login-brand::before {
      content: '🎁';
      position: absolute;
      bottom: -20px; right: -20px;
      font-size: 10rem;
      opacity: .08;
    }

    .brand-logo-big {
      width: 64px; height: 64px;
      background: rgba(255,255,255,.15);
      border-radius: 16px;
      display: grid;
      place-items: center;
      font-size: 2rem;
      backdrop-filter: blur(10px);
    }

    .brand-headline { font-size: 1.9rem; font-weight: 800; line-height: 1.25; }
    .brand-desc     { font-size: .95rem; opacity: .85; line-height: 1.6; }

    .brand-features { display: flex; flex-direction: column; gap: .6rem; margin-top: .5rem; }
    .brand-feature  { display: flex; align-items: center; gap: .6rem; font-size: .875rem; opacity: .9; }
    .brand-feature .fi { font-size: 1rem; }

    /* Right form panel */
    .login-form-panel {
      background: #fff;
      padding: 3rem 2.5rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 1.75rem;
    }

    .login-title    { font-size: 1.6rem; font-weight: 700; color: #1A1D2E; }
    .login-subtitle { font-size: .875rem; color: #6B7280; margin-top: .3rem; }

    .login-error {
      background: rgba(239,68,68,.08);
      border-left: 4px solid #EF4444;
      padding: .85rem 1rem;
      border-radius: 8px;
      font-size: .875rem;
      color: #EF4444;
      display: flex;
      align-items: center;
      gap: .6rem;
    }

    .login-form { display: flex; flex-direction: column; gap: 1.1rem; }

    .login-input-group { position: relative; }
    .login-input-icon {
      position: absolute;
      left: .9rem; top: 50%;
      transform: translateY(-50%);
      font-size: .9rem;
      pointer-events: none;
    }

    .login-input {
      width: 100%;
      padding: .75rem 1rem .75rem 2.5rem;
      border: 1.5px solid #E5E7EB;
      border-radius: 10px;
      font-size: .95rem;
      font-family: inherit;
      color: #1A1D2E;
      background: #fff;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }

    .login-input:focus {
      border-color: #6C63FF;
      box-shadow: 0 0 0 3px rgba(108,99,255,.12);
    }

    .login-label {
      display: block;
      font-size: .82rem;
      font-weight: 500;
      color: #374151;
      margin-bottom: .35rem;
    }

    .btn-login {
      width: 100%;
      padding: .85rem;
      background: linear-gradient(135deg, #6C63FF, #5A52E0);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s;
      box-shadow: 0 4px 14px rgba(108,99,255,.35);
    }

    .btn-login:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(108,99,255,.45);
    }

    .login-hint {
      text-align: center;
      font-size: .78rem;
      color: #9CA3AF;
      margin-top: .5rem;
    }

    @media (max-width: 680px) {
      .login-wrapper { grid-template-columns: 1fr; }
      .login-brand { padding: 2rem; display: none; }
      .login-form-panel { padding: 2rem; }
    }
  </style>
</head>
<body>

<!-- Flash messages from redirect -->
<?php if (!empty($flashMsgs)): ?>
<div class="flash-container">
  <?php foreach ($flashMsgs as $msg): ?>
  <div class="flash flash-<?= e($msg['type']) ?>">
    <span class="flash-icon"><?= $msg['type'] === 'success' ? '✓' : 'ℹ' ?></span>
    <span class="flash-msg"><?= e($msg['message']) ?></span>
    <button class="flash-close" onclick="this.parentElement.remove()">×</button>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="login-wrapper">

  <!-- Brand Panel -->
  <div class="login-brand">
    <div class="brand-logo-big">🎁</div>
    <div>
      <div class="brand-headline">Giftz<br>Inventory</div>
      <div class="brand-desc">All-in-one inventory &amp; sales management for your gift &amp; clothing shop.</div>
    </div>
    <div class="brand-features">
      <div class="brand-feature"><span class="fi">📦</span> Product &amp; stock tracking</div>
      <div class="brand-feature"><span class="fi">🛒</span> Point of Sale (POS)</div>
      <div class="brand-feature"><span class="fi">📋</span> Purchase orders</div>
      <div class="brand-feature"><span class="fi">📈</span> Sales &amp; profit reports</div>
    </div>
  </div>

  <!-- Form Panel -->
  <div class="login-form-panel">
    <div>
      <div class="login-title">Welcome back</div>
      <div class="login-subtitle">Sign in to your account to continue</div>
    </div>

    <?php if ($error): ?>
    <div class="login-error">⚠ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="login-form">
      <div>
        <label class="login-label" for="email">Email address</label>
        <div class="login-input-group">
          <span class="login-input-icon">✉</span>
          <input
            id="email" name="email" type="email"
            class="login-input"
            placeholder="admin@giftz.local"
            value="<?= e($_POST['email'] ?? '') ?>"
            autocomplete="email"
            required
          >
        </div>
      </div>

      <div>
        <label class="login-label" for="password">Password</label>
        <div class="login-input-group">
          <span class="login-input-icon">🔒</span>
          <input
            id="password" name="password" type="password"
            class="login-input"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
          >
        </div>
      </div>

      <button type="submit" class="btn-login">Sign In →</button>
    </form>

    <div class="login-hint">
      Default: admin@giftz.local / Admin@123
    </div>
  </div>

</div>

<script>
// Auto-dismiss flash
setTimeout(() => {
  document.querySelectorAll('.flash').forEach(f => {
    f.style.opacity = '0';
    f.style.transition = 'opacity .4s';
    setTimeout(() => f.remove(), 400);
  });
}, 5000);
</script>
</body>
</html>
