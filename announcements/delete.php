<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/announcements/index.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id) {
    db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
    flash('success', 'Announcement deleted.');
} else {
    flash('error', 'Invalid announcement.');
}

header('Location: ' . BASE_URL . '/announcements/index.php');
exit;
