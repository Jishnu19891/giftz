<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/purchases/index.php');
    exit;
}

$pdo = db();
$id  = intval($_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM purchases WHERE id = ?');
$stmt->execute([$id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    flash('error', 'Purchase order not found.');
    header('Location: ' . BASE_URL . '/purchases/index.php');
    exit;
}

if ($purchase['status'] !== 'pending') {
    flash('error', 'Only pending purchase orders can be cancelled (this PO is "' . $purchase['status'] . '").');
    header('Location: ' . BASE_URL . '/purchases/view.php?id=' . $id);
    exit;
}

$pdo->prepare("UPDATE purchases SET status = 'cancelled', updated_at = NOW() WHERE id = ?")
    ->execute([$id]);

flash('success', 'Purchase order ' . $purchase['reference_no'] . ' has been cancelled.');
header('Location: ' . BASE_URL . '/purchases/index.php');
exit;
