<?php
/**
 * Database Setup Helper
 * Visit: http://localhost/giftz/database/setup.php
 * DELETE this file after setup is complete!
 */

require_once dirname(__DIR__) . '/config/config.php';

$message = '';
$status  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'import') {
        $sqlFile = __DIR__ . '/giftz_db.sql';
        if (!file_exists($sqlFile)) {
            $status  = 'error';
            $message = 'SQL file not found: ' . $sqlFile;
        } else {
            try {
                // Connect without selecting DB first
                $pdo = new PDO(
                    'mysql:host=' . DB_HOST . ';charset=utf8mb4',
                    DB_USER, DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $sql = file_get_contents($sqlFile);
                // Execute each statement
                $pdo->exec($sql);

                // Now update admin password to Admin@123
                $hash = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->exec("USE `giftz_db`");
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@giftz.local'");
                $stmt->execute([$hash]);
                $stmt2 = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'staff@giftz.local'");
                $stmt2->execute([$hash]);

                $status  = 'success';
                $message = 'Database imported successfully! Default credentials: admin@giftz.local / Admin@123';
            } catch (PDOException $e) {
                $status  = 'error';
                $message = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Giftz DB Setup</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 600px; margin: 3rem auto; padding: 1rem; }
    h1 { color: #6C63FF; }
    .msg { padding: 1rem; border-radius: 8px; margin: 1rem 0; }
    .msg.success { background: #D1FAE5; color: #065F46; }
    .msg.error   { background: #FEE2E2; color: #991B1B; }
    .btn { background: #6C63FF; color: #fff; border: none; padding: .75rem 1.5rem; border-radius: 8px; font-size: 1rem; cursor: pointer; }
    .btn:hover { background: #5A52E0; }
    .warn { background: #FEF3C7; color: #92400E; padding: 1rem; border-radius: 8px; margin: 1rem 0; font-size: .9rem; }
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    td, th { padding: .5rem .75rem; border: 1px solid #ddd; font-size: .9rem; }
    th { background: #F3F4F6; }
  </style>
</head>
<body>
  <h1>🎁 Giftz — Database Setup</h1>

  <?php if ($message): ?>
  <div class="msg <?= $status ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($status !== 'success'): ?>
  <p>This will create the <strong>giftz_db</strong> database and import all tables with seed data.</p>

  <table>
    <tr><th>Setting</th><th>Value</th></tr>
    <tr><td>Host</td><td><?= DB_HOST ?></td></tr>
    <tr><td>Database</td><td><?= DB_NAME ?></td></tr>
    <tr><td>User</td><td><?= DB_USER ?></td></tr>
  </table>

  <form method="POST" style="margin-top:1.5rem">
    <input type="hidden" name="action" value="import">
    <button type="submit" class="btn">▶ Import Database Now</button>
  </form>
  <?php else: ?>
  <div class="warn">⚠ <strong>Security:</strong> Delete this file after setup! (<code>database/setup.php</code>)</div>
  <p><a href="<?= BASE_URL ?>/login.php" style="color:#6C63FF;font-weight:600">→ Go to Login</a></p>
  <?php endif; ?>
</body>
</html>
