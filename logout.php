<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logout();
flash('info', 'You have been logged out.');
header('Location: ' . BASE_URL . '/login.php');
exit;
