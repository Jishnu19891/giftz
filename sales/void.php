<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/sales/index.php');
    exit;
}

$pdo = db();
$id  = intval($_POST['id'] ?? 0);

$saleStmt = $pdo->prepare('SELECT * FROM sales WHERE id=?');
$saleStmt->execute([$id]);
$sale = $saleStmt->fetch();

if (!$sale || $sale['status'] !== 'completed') {
    flash('error', 'Invalid sale or already voided.');
    header('Location: ' . BASE_URL . '/sales/index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $itemsStmt = $pdo->prepare('SELECT * FROM sale_items WHERE sale_id=?');
    $itemsStmt->execute([$id]);
    $items  = $itemsStmt->fetchAll();
    $userId = currentUser()['id'];

    foreach ($items as $item) {
        if ($item['product_id']) {
            updateStock($item['product_id'], $item['quantity'], 'in', 'VOID-' . $sale['invoice_no'], $userId);
        }
    }

    $pdo->prepare('UPDATE sales SET status="voided" WHERE id=?')->execute([$id]);
    $pdo->commit();

    flash('success', 'Sale ' . $sale['invoice_no'] . ' voided. Stock has been restored.');

} catch (Exception $e) {
    $pdo->rollBack();
    flash('error', 'Void failed: ' . $e->getMessage());
}

header('Location: ' . BASE_URL . '/sales/index.php');
exit;
