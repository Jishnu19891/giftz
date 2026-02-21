<?php
require_once dirname(__DIR__) . '/config/config.php';
header('Location: ' . BASE_URL . '/customers/add.php?id=' . intval($_GET['id'] ?? 0));
exit;
