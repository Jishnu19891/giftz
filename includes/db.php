<?php
require_once dirname(__DIR__) . '/config/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:2rem;color:#EF4444;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;margin:2rem auto;max-width:600px">
                <h2>Database Connection Failed</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Please ensure MySQL is running and the database <strong>' . DB_NAME . '</strong> exists.</p>
            </div>');
        }
    }
    return $pdo;
}
