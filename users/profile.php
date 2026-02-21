<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo    = db();
$userId = currentUser()['id'];
$errors = [];
$tab    = in_array($_GET['tab'] ?? 'info', ['info', 'password']) ? ($_GET['tab'] ?? 'info') : 'info';

// Fetch full user record
$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

if (!$user) {
    flash('error', 'User not found.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// ── Handle: update profile info ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'info') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($name))  $errors[] = 'Full name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    if (empty($errors)) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
        $chk->execute([$email, $userId]);
        if ($chk->fetchColumn() > 0) $errors[] = 'Email is already used by another account.';
    }

    if (empty($errors)) {
        $pdo->prepare('UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$name, $email, $userId]);

        // Sync session so the topbar/sidebar reflect the new name immediately
        $_SESSION['user_name']  = $name;
        $_SESSION['user_email'] = $email;

        flash('success', 'Profile updated successfully.');
        header('Location: ' . BASE_URL . '/users/profile.php?tab=info');
        exit;
    }

    $tab = 'info';
    // Keep typed values for re-display
    $user['name']  = $name;
    $user['email'] = $email;
}

// ── Handle: change password ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current))       $errors[] = 'Current password is required.';
    if (empty($new))           $errors[] = 'New password is required.';
    if (strlen($new) < 6)     $errors[] = 'New password must be at least 6 characters.';
    if ($new !== $confirm)     $errors[] = 'New passwords do not match.';

    if (empty($errors)) {
        // Re-fetch to get the stored hash (not the possibly merged $user)
        $freshStmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $freshStmt->execute([$userId]);
        $storedHash = $freshStmt->fetchColumn();

        if (!password_verify($current, $storedHash)) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $userId]);

            flash('success', 'Password changed successfully.');
            header('Location: ' . BASE_URL . '/users/profile.php?tab=password');
            exit;
        }
    }

    $tab = 'password';
}

// ── Activity stats ───────────────────────────────────────────
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*)                AS sales_count,
        COALESCE(SUM(total),0) AS total_revenue
    FROM sales
    WHERE created_by = ? AND status = 'completed'
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

$poCountStmt = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE created_by = ?");
$poCountStmt->execute([$userId]);
$poCount = (int)$poCountStmt->fetchColumn();

$todayStmt = $pdo->prepare("
    SELECT COUNT(*), COALESCE(SUM(total),0)
    FROM sales
    WHERE created_by = ? AND status = 'completed' AND DATE(created_at) = CURDATE()
");
$todayStmt->execute([$userId]);
[$todaySales, $todayRevenue] = $todayStmt->fetch(PDO::FETCH_NUM);

// ── Recent sales by this user ─────────────────────────────────
$recentStmt = $pdo->prepare("
    SELECT s.id, s.invoice_no, s.total, s.status, s.created_at,
           COALESCE(c.name, 'Walk-in') AS customer_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    WHERE s.created_by = ?
    ORDER BY s.created_at DESC
    LIMIT 8
");
$recentStmt->execute([$userId]);
$recentSales = $recentStmt->fetchAll();

$pageTitle   = 'My Profile';
$breadcrumbs = ['My Profile' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">👤</span> My Profile</h1>
</div>

<!-- Activity KPIs -->
<div class="kpi-grid" style="margin-bottom:1.25rem;grid-template-columns:repeat(4,1fr)">
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon">🧾</div>
        <div class="kpi-info">
            <div class="kpi-label">Total Sales</div>
            <div class="kpi-value"><?= number_format($stats['sales_count']) ?></div>
            <div class="kpi-sub">completed transactions</div>
        </div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-icon">💰</div>
        <div class="kpi-info">
            <div class="kpi-label">Revenue Generated</div>
            <div class="kpi-value"><?= formatCurrency($stats['total_revenue']) ?></div>
            <div class="kpi-sub">lifetime</div>
        </div>
    </div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-icon">📅</div>
        <div class="kpi-info">
            <div class="kpi-label">Today's Sales</div>
            <div class="kpi-value"><?= number_format($todaySales) ?></div>
            <div class="kpi-sub"><?= formatCurrency($todayRevenue) ?> revenue</div>
        </div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon">📋</div>
        <div class="kpi-info">
            <div class="kpi-label">Purchase Orders</div>
            <div class="kpi-value"><?= number_format($poCount) ?></div>
            <div class="kpi-sub">POs created</div>
        </div>
    </div>
</div>

<!-- Main grid -->
<div style="display:grid;grid-template-columns:360px 1fr;gap:1.25rem;align-items:start">

    <!-- Left: Settings Card -->
    <div class="card">

        <!-- Profile summary -->
        <div style="padding:2rem 1.5rem;text-align:center;border-bottom:1px solid var(--border)">
            <div style="width:72px;height:72px;border-radius:50%;
                        background:linear-gradient(135deg,var(--accent),var(--secondary));
                        display:grid;place-items:center;color:#fff;font-size:1.75rem;
                        font-weight:700;margin:0 auto 1rem">
                <?= strtoupper(mb_substr($user['name'], 0, 1)) ?>
            </div>
            <div style="font-size:1.1rem;font-weight:700"><?= e($user['name']) ?></div>
            <div style="margin-top:.35rem"><?= statusBadge($user['role']) ?></div>
            <?php if ($user['last_login']): ?>
            <div class="fs-sm text-muted" style="margin-top:.5rem">
                Last login: <?= formatDateTime($user['last_login']) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tab nav -->
        <div style="display:flex;border-bottom:1px solid var(--border)">
            <a href="?tab=info"
               style="flex:1;padding:.75rem;text-align:center;font-size:.875rem;font-weight:500;
                      border-bottom:2px solid <?= $tab === 'info' ? 'var(--accent)' : 'transparent' ?>;
                      color:<?= $tab === 'info' ? 'var(--accent)' : 'var(--text-muted)' ?>;
                      text-decoration:none;transition:all .2s">
                ✏ Edit Info
            </a>
            <a href="?tab=password"
               style="flex:1;padding:.75rem;text-align:center;font-size:.875rem;font-weight:500;
                      border-bottom:2px solid <?= $tab === 'password' ? 'var(--accent)' : 'transparent' ?>;
                      color:<?= $tab === 'password' ? 'var(--accent)' : 'var(--text-muted)' ?>;
                      text-decoration:none;transition:all .2s">
                🔒 Password
            </a>
        </div>

        <!-- Error messages -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin:1rem 1.25rem 0;border-radius:var(--radius)">
            <ul style="margin:0 0 0 1rem">
                <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Tab: Edit Info -->
        <?php if ($tab === 'info'): ?>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="form" value="info">
                <div class="form-group">
                    <label class="form-label required">Full Name</label>
                    <input type="text" name="name" class="form-control"
                           value="<?= e($user['name']) ?>" required>
                </div>
                <div class="form-group" style="margin-top:.85rem">
                    <label class="form-label required">Email Address</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= e($user['email']) ?>" required>
                </div>
                <div class="form-group" style="margin-top:.85rem">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control"
                           value="<?= e(ucfirst($user['role'])) ?>" disabled
                           style="background:var(--body-bg);cursor:not-allowed">
                    <div class="form-text">Role can only be changed by an admin.</div>
                </div>
                <div style="margin-top:1.25rem">
                    <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                </div>
            </form>
        </div>

        <!-- Tab: Change Password -->
        <?php else: ?>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="form" value="password">
                <div class="form-group">
                    <label class="form-label required">Current Password</label>
                    <input type="password" name="current_password" class="form-control"
                           autocomplete="current-password" required>
                </div>
                <div class="form-group" style="margin-top:.85rem">
                    <label class="form-label required">New Password</label>
                    <input type="password" name="new_password" id="newPass" class="form-control"
                           autocomplete="new-password" required minlength="6">
                    <div class="form-text">Minimum 6 characters.</div>
                </div>
                <div class="form-group" style="margin-top:.85rem">
                    <label class="form-label required">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirmPass" class="form-control"
                           autocomplete="new-password" required>
                </div>
                <div style="margin-top:1.25rem">
                    <button type="submit" class="btn btn-primary w-100">Change Password</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Account meta -->
        <div style="padding:.75rem 1.25rem;border-top:1px solid var(--border);
                    font-size:.78rem;color:var(--text-muted);display:flex;justify-content:space-between">
            <span>Member since <?= formatDate($user['created_at']) ?></span>
            <span><?= statusBadge($user['status']) ?></span>
        </div>
    </div>

    <!-- Right: Recent Activity -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">🧾 Recent Sales</div>
            <a href="<?= BASE_URL ?>/sales/index.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th class="text-right">Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentSales)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state" style="padding:3rem 1rem">
                                <div class="icon">🧾</div>
                                <p>No sales processed yet.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentSales as $s): ?>
                    <tr>
                        <td>
                            <a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $s['id'] ?>"
                               class="fw-bold" style="color:var(--accent)">
                                <?= e($s['invoice_no']) ?>
                            </a>
                        </td>
                        <td><?= e($s['customer_name']) ?></td>
                        <td class="text-right fw-bold"><?= formatCurrency($s['total']) ?></td>
                        <td><?= statusBadge($s['status']) ?></td>
                        <td class="text-muted fs-sm"><?= formatDateTime($s['created_at']) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $s['id'] ?>"
                               class="btn btn-sm btn-secondary" target="_blank">🖨</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// Live password match indicator
const newPass     = document.getElementById('newPass');
const confirmPass = document.getElementById('confirmPass');

function checkMatch() {
    if (!confirmPass || !confirmPass.value) return;
    const match = newPass.value === confirmPass.value;
    confirmPass.style.borderColor = match ? 'var(--success)' : 'var(--danger)';
}

newPass?.addEventListener('input', checkMatch);
confirmPass?.addEventListener('input', checkMatch);
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
