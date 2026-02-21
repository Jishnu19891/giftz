<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
} else {
    header('Location: ' . BASE_URL . '/dashboard.php');
}
exit;
