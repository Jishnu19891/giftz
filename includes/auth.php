<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== $role) {
        flash('error', 'Access denied. You do not have permission to view that page.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? 0,
        'name' => $_SESSION['user_name'] ?? 'Unknown',
        'role' => $_SESSION['user_role'] ?? 'staff',
    ];
}

function attemptLogin(string $email, string $password): bool {
    $stmt = db()->prepare(
        'SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        return false;
    }
    if (!password_verify($password, $user['password'])) {
        return false;
    }

    // Update last login
    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];

    return true;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
