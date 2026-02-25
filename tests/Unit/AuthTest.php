<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for includes/auth.php
 *
 * Tests the pure session-based helpers: isAdmin(), currentUser(), logout().
 * requireLogin() and requireRole() are excluded because they call header()
 * and exit, which are not testable without output buffering tricks.
 */
class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        // Restart the session if a previous logout() call destroyed it
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ── isAdmin ───────────────────────────────────────────────────────────────

    #[Test]
    public function isAdminReturnsTrueWhenSessionRoleIsAdmin(): void
    {
        $_SESSION['user_role'] = 'admin';
        $this->assertTrue(isAdmin());
    }

    #[Test]
    public function isAdminReturnsFalseWhenSessionRoleIsStaff(): void
    {
        $_SESSION['user_role'] = 'staff';
        $this->assertFalse(isAdmin());
    }

    #[Test]
    public function isAdminReturnsFalseWhenSessionIsEmpty(): void
    {
        $this->assertFalse(isAdmin());
    }

    #[Test]
    public function isAdminIsCaseSensitive(): void
    {
        // Must be exactly 'admin', not 'Admin' or 'ADMIN'
        $_SESSION['user_role'] = 'Admin';
        $this->assertFalse(isAdmin());
    }

    // ── currentUser ───────────────────────────────────────────────────────────

    #[Test]
    public function currentUserReturnsDataFromSession(): void
    {
        $_SESSION['user_id']   = 42;
        $_SESSION['user_name'] = 'Jane Doe';
        $_SESSION['user_role'] = 'admin';

        $user = currentUser();

        $this->assertSame(42, $user['id']);
        $this->assertSame('Jane Doe', $user['name']);
        $this->assertSame('admin', $user['role']);
    }

    #[Test]
    public function currentUserReturnsDefaultsWhenSessionIsEmpty(): void
    {
        $user = currentUser();

        $this->assertSame(0, $user['id']);
        $this->assertSame('Unknown', $user['name']);
        $this->assertSame('staff', $user['role']);
    }

    #[Test]
    public function currentUserAlwaysContainsIdNameAndRole(): void
    {
        $user = currentUser();

        $this->assertArrayHasKey('id', $user);
        $this->assertArrayHasKey('name', $user);
        $this->assertArrayHasKey('role', $user);
    }

    #[Test]
    public function currentUserIdIsAlwaysAnInteger(): void
    {
        $user = currentUser();
        $this->assertIsInt($user['id']);
    }

    #[Test]
    public function currentUserReflectsPartialSessionData(): void
    {
        // Only id is set; name and role should fall back to defaults
        $_SESSION['user_id'] = 7;

        $user = currentUser();

        $this->assertSame(7, $user['id']);
        $this->assertSame('Unknown', $user['name']);
        $this->assertSame('staff', $user['role']);
    }

    // ── logout ────────────────────────────────────────────────────────────────

    #[Test]
    public function logoutClearsAllSessionVariables(): void
    {
        $_SESSION['user_id']   = 1;
        $_SESSION['user_name'] = 'Test User';
        $_SESSION['user_role'] = 'admin';
        $_SESSION['extra']     = 'data';

        logout();

        $this->assertEmpty($_SESSION);
    }

    #[Test]
    public function logoutWorksSafelyOnEmptySession(): void
    {
        // Should not throw even when session is already empty
        logout();
        $this->assertEmpty($_SESSION);
    }
}
