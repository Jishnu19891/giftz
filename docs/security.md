# Security

This page documents the security measures in place and known gaps to address before deploying to a production or internet-facing environment.

---

## Authentication & Authorization

### Session-Based Authentication

All admin pages are protected by session-based authentication. No JWT or cookie tokens are used.

**Login flow:**
1. User submits email and password via `login.php`
2. `attemptLogin()` fetches the user record by email where `status = 'active'`
3. `password_verify()` compares the submitted password against the stored bcrypt hash
4. On success: `$_SESSION` variables are set and `last_login` is updated
5. On failure: no session is created; error flash is shown

**Session variables:**

| Variable | Value |
|---|---|
| `$_SESSION['user_id']` | User primary key |
| `$_SESSION['user_name']` | Display name |
| `$_SESSION['user_role']` | `'admin'` or `'staff'` |
| `$_SESSION['user_email']` | Email address |

**Guard functions:**
- `requireLogin()` — redirects to `/login.php` if no session exists; called at the top of every admin page
- `requireRole('admin')` — redirects with error if role doesn't match; called for admin-only actions

### Role Capabilities

| Action | Admin | Staff |
|---|---|---|
| Dashboard | Yes | Yes |
| Point of Sale | Yes | Yes |
| Sales history, invoices | Yes | Yes |
| Void a sale | Yes | Yes |
| Process returns | Yes | Yes |
| Purchase orders (all actions) | Yes | Yes |
| Products (view/add/edit) | Yes | Yes |
| Delete product (soft) | Yes | No |
| Categories (all actions) | Yes | Yes |
| Suppliers (all actions) | Yes | Yes |
| Customers (all actions) | Yes | Yes |
| Expenses (view/add/edit) | Yes | Yes |
| Delete expense (hard) | Yes | No |
| Reports (all) | Yes | Yes |
| Visitor analytics | Yes | Yes |
| Announcements (all actions) | Yes | No |
| User list, add user | Yes | No |
| Own profile (view/edit/password) | Yes | Yes |

---

## Password Security

### Storage
- Passwords are stored as **bcrypt hashes** using PHP's `password_hash($password, PASSWORD_DEFAULT)`
- `PASSWORD_DEFAULT` currently maps to bcrypt with a cost factor of 10+
- No plaintext passwords are stored anywhere in the system

### Verification
- `password_verify($input, $hash)` is used for all password checks — this is timing-safe
- Raw passwords are never logged, echoed, or stored in session

### Changing Passwords
- The profile page (`users/profile.php`) requires the current password before accepting a new one
- New passwords are hashed with `password_hash()` before being written to the database

---

## SQL Injection Prevention

All database queries use **PDO prepared statements** with parameter binding. No string interpolation is used in SQL.

**Safe pattern (used throughout):**
```php
$stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND status = ?');
$stmt->execute([$id, 'active']);
```

**Never done:**
```php
// This pattern does NOT exist in the codebase
$result = db()->query("SELECT * FROM products WHERE id = $id");
```

---

## XSS Prevention

All user-supplied data is escaped before being output to HTML using the `e()` helper:

```php
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
```

Usage throughout templates:
```php
echo e($product['name']);
echo e($customer['email']);
```

`ENT_QUOTES` escapes both single (`'`) and double (`"`) quotes. `ENT_SUBSTITUTE` replaces invalid encoding sequences with the Unicode replacement character instead of dropping them.

---

## Transaction Safety

Operations that must be atomic (all-or-nothing) use PDO transactions:

**POS checkout:**
```php
$pdo->beginTransaction();
// Insert sales row
// Insert sale_items rows
// Update stock for each item
$pdo->commit();
// On any error:
$pdo->rollBack();
```

**Return processing:**
```php
$pdo->beginTransaction();
// Insert sale_returns row
// Insert sale_return_items rows
// Restore stock for each item
$pdo->commit();
```

This prevents partial writes (e.g., a sale recorded but stock not deducted, or stock deducted but sale not recorded).

---

## Privacy (Visitor Tracking)

The visitor tracker stores no personally identifiable information:
- IP addresses are stored as **SHA-256 hashes** — one-way, not reversible
- Session IDs are stored as **SHA-256 hashes**
- No names, emails, or cookies are set by the tracker

---

## Image Upload Validation

The `uploadImage()` function validates MIME type server-side before accepting any uploaded file:

```php
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$mime = mime_content_type($file['tmp_name']); // Reads actual file bytes
if (!in_array($mime, $allowed)) {
    return false;
}
```

Validation is based on the actual file content (via `mime_content_type()`), not the file extension or the browser-supplied MIME type, which can be spoofed.

---

## Known Gaps

### CSRF Protection (Not Implemented)

**Risk:** An attacker could craft a malicious page that submits a form to this application on behalf of a logged-in user (Cross-Site Request Forgery).

**Affected:** All POST forms (POS checkout, product delete, sale void, user management, etc.)

**Recommended fix:** Add a CSRF token to every form:
```php
// Generate and store token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// In form
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

// On POST handler
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}
```

### Error Display (Development Mode)

`config/config.php` currently has:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

This exposes PHP errors, file paths, and stack traces to the browser. **Disable before any non-localhost deployment:**
```php
error_reporting(0);
ini_set('display_errors', 0);
```

### No Rate Limiting on Login

The login form has no rate limiting or account lockout after repeated failed attempts. An attacker could brute-force passwords.

**Recommended fix:** Track failed login attempts per IP/email in the database or session and lock out after N failures.

### No HTTPS Enforcement

The application is configured for `http://localhost`. On any network-accessible deployment, HTTPS must be enforced to protect session cookies and login credentials in transit.

### Session Cookie Flags

For internet-facing deployments, set secure cookie flags in `php.ini` or via `session_set_cookie_params()`:
```php
session_set_cookie_params([
    'secure'   => true,   // HTTPS only
    'httponly' => true,   // Not accessible via JavaScript
    'samesite' => 'Strict',
]);
```

---

## Summary

| Measure | Status |
|---|---|
| bcrypt password hashing | Implemented |
| PDO prepared statements | Implemented |
| Output escaping (XSS) | Implemented |
| Role-based access control | Implemented |
| PDO transactions on critical writes | Implemented |
| MIME-validated image uploads | Implemented |
| Privacy-safe visitor tracking | Implemented |
| CSRF token validation | **Not implemented** |
| Login rate limiting | **Not implemented** |
| HTTPS enforcement | **Not applicable (localhost)** |
| Error display disabled | **Not done (dev mode)** |
