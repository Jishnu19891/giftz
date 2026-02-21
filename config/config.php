<?php
// ─── Database ─────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'giftz_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ─── Application ─────────────────────────────────────────
define('APP_NAME', 'Giftz Inventory');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/giftz');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/products');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/products');

// ─── Currency ─────────────────────────────────────────────
define('CURRENCY_SYMBOL', '₱');
define('CURRENCY_CODE', 'PHP');

// ─── Pagination ───────────────────────────────────────────
define('ROWS_PER_PAGE', 20);

// ─── Stock Alert ─────────────────────────────────────────
define('LOW_STOCK_THRESHOLD', 5);

// ─── Timezone ────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ─── Error Reporting (set to 0 in production) ────────────
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ─── Session ─────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
