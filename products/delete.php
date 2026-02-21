<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/products/index.php');
    exit;
}

$id  = intval($_POST['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT name FROM products WHERE id=?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Product not found.');
} else {
    // Soft delete
    $pdo->prepare('UPDATE products SET status="inactive" WHERE id=?')->execute([$id]);
    flash('success', '"' . $product['name'] . '" has been deactivated.');
}

header('Location: ' . BASE_URL . '/products/index.php');
exit;
