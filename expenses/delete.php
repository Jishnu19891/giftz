<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/expenses/index.php');
    exit;
}

$pdo = db();
$id  = intval($_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT reference_no FROM expenses WHERE id = ?");
$stmt->execute([$id]);
$expense = $stmt->fetch();

if (!$expense) {
    flash('error', 'Expense not found.');
    header('Location: ' . BASE_URL . '/expenses/index.php');
    exit;
}

$pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);

flash('success', 'Expense ' . $expense['reference_no'] . ' deleted.');
header('Location: ' . BASE_URL . '/expenses/index.php');
exit;
