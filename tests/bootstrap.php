<?php
declare(strict_types=1);

/**
 * PHPUnit Bootstrap
 *
 * Loads the Composer autoloader and application code needed for tests.
 * The db() function is lazy — it only connects when called, so pure-function
 * tests run without a database connection.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// ── Session ───────────────────────────────────────────────────────────────────
// Start the session before config.php loads so its session_start() guard fires.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Application code under test ───────────────────────────────────────────────
// config.php defines all constants and sets the timezone.
// db.php defines the lazy db() singleton — safe to include without a real DB
// because the PDO connection is only made when db() is actually called.
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/visitor_tracker.php';
require_once dirname(__DIR__) . '/includes/auth.php';
