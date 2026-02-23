<?php
/**
 * Visitor Logs — one-time migration
 * Visit: http://localhost/giftz/visitors/migrate.php
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

$pdo = db();
$sql = file_get_contents(dirname(__DIR__) . '/database/add_visitor_logs.sql');

// Strip comment lines so PDO doesn't choke
$lines = array_filter(explode("\n", $sql), fn($l) => !str_starts_with(trim($l), '--'));
$sql   = implode("\n", $lines);

try {
    $pdo->exec($sql);
    $ok = true;
} catch (PDOException $e) {
    $ok  = false;
    $err = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Visitor Logs Migration</title>
  <style>
    body{font-family:system-ui,sans-serif;max-width:600px;margin:3rem auto;padding:1rem}
    h1{color:#6C63FF}
    .ok{background:#D1FAE5;color:#065F46;padding:1rem;border-radius:8px}
    .err{background:#FEE2E2;color:#991B1B;padding:1rem;border-radius:8px}
    a{color:#6C63FF;font-weight:600}
  </style>
</head>
<body>
  <h1>Visitor Logs Migration</h1>
  <?php if ($ok): ?>
    <div class="ok">Table <strong>visitor_logs</strong> created (or already exists). Migration complete!</div>
    <p style="margin-top:1rem"><a href="<?= BASE_URL ?>/visitors/index.php">Go to Visitor Analytics &rarr;</a></p>
  <?php else: ?>
    <div class="err"><strong>Error:</strong> <?= htmlspecialchars($err) ?></div>
  <?php endif; ?>
</body>
</html>
