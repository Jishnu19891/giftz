# Testing

**Framework:** PHPUnit 11.0
**Total tests:** 95
**Test directory:** `tests/`

---

## Running Tests

```bash
# Run all tests
composer test

# Equivalent
./vendor/bin/phpunit

# Run only the Unit suite
composer test:unit

# Run a single test file
./vendor/bin/phpunit tests/Unit/AuthTest.php

# Run a single test method
./vendor/bin/phpunit --filter testIsAdminReturnsTrueForAdmin
```

---

## Test Suite Structure

```
tests/
├── bootstrap.php           # Environment setup
└── Unit/
    ├── AuthTest.php        # 38 tests — auth functions
    ├── FunctionsTest.php   # 42 tests — utility functions
    └── VisitorTrackerTest.php  # 15 tests — visitor tracking
```

---

## Bootstrap (`tests/bootstrap.php`)

Sets up the test environment before any tests run:

1. Requires the Composer autoloader (`vendor/autoload.php`)
2. Starts a PHP session if not already active (`PHP_SESSION_NONE` check)
3. Requires `config/config.php` (constants, timezone, session)
4. Requires `includes/db.php`, `includes/functions.php`, `includes/visitor_tracker.php`, `includes/auth.php`

The PDO connection is lazy — it is only created when `db()` is first called. Tests that don't need a database connection don't trigger a connection attempt.

---

## Test Files

### `AuthTest.php` — 38 Tests

Tests all functions in `includes/auth.php` by directly manipulating `$_SESSION`.

**Covered functions:**

| Function | Tests |
|---|---|
| `isAdmin()` | Returns `true` when role is `'admin'`, `false` for `'staff'`, `false` when session is empty, case-sensitivity |
| `currentUser()` | Returns correct array from session, returns defaults when session missing, handles partial session data |
| `logout()` | Clears all session variables, destroys session cookie, calls `session_destroy()` |

**Example test cases:**
- `testIsAdminReturnsTrueForAdmin`
- `testIsAdminReturnsFalseForStaff`
- `testIsAdminReturnsFalseWhenNoSession`
- `testCurrentUserReturnsSessionData`
- `testLogoutClearsSession`
- `testLogoutDestroysSessionCookie`

---

### `FunctionsTest.php` — 42 Tests

Tests all utility functions in `includes/functions.php`.

**Covered functions:**

| Function | Tests |
|---|---|
| `formatCurrency()` | ₹ symbol, two decimal places, thousands separator, zero, negative |
| `generateSKU()` | Correct format, category code, type code, zero-padded sequence |
| `generateInvoiceNo()` | INV-YYYY-NNNNN format, year matches current year |
| `generatePoRef()` | PO-YYYY-NNN format |
| `generateExpenseNo()` | EXP-YYYY-NNNNN format |
| `generateReturnNo()` | RET-YYYY-NNNNN format |
| `uploadImage()` | Valid MIME accepted, invalid MIME rejected, file moved to correct directory, returns filename |
| `updateStock()` | `'in'` increases stock, `'out'` decreases stock, `'adjustment'` sets stock, `stock_movements` row inserted |
| `paginate()` | Correct page count, correct offset, `has_prev`/`has_next` flags |
| `paginationLinks()` | HTML nav rendered, active page class, correct URLs |
| `statusBadge()` | Correct badge class per status value |
| `e()` | Escapes `<`, `>`, `"`, `'`, `&` |
| `formatDate()` | Parses and formats date string correctly |
| `formatDateTime()` | Parses and formats datetime string correctly |
| `productEmoji()` | Returns correct emoji for known categories |

---

### `VisitorTrackerTest.php` — 15 Tests

Tests the `trackVisit()` function and its helpers in `includes/visitor_tracker.php`.

**Covered behavior:**

| Test | What It Verifies |
|---|---|
| Device detection — mobile | UA with "iphone" detected as `mobile` |
| Device detection — tablet | UA with "ipad" detected as `tablet` |
| Device detection — desktop | Standard UA detected as `desktop` |
| Device detection — bot | UA with "googlebot" detected as `bot` |
| Bot tracking skipped | `trackVisit()` inserts no row when device is bot |
| Visitor log insertion | Correct row inserted with expected columns |
| Session hash | Stored as SHA-256 of `session_id()` |
| IP hash | Stored as SHA-256 of `REMOTE_ADDR` |
| Deduplication | Second call within 1 hour does not insert a duplicate row |
| Deduplication resets | Same session, different page → new row inserted |
| Page ID stored | `product` page stores `page_id = $productId` |
| Catalog page | `page = 'catalog'`, `page_id = NULL` |
| DB unavailable | No exception thrown; silent fail |

---

## PHPUnit Configuration (`phpunit.xml`)

```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.0/phpunit.xsd"
         bootstrap="tests/bootstrap.php">

    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory suffix=".php">includes</directory>
        </include>
    </source>

</phpunit>
```

**Coverage source:** `includes/` directory — `auth.php`, `db.php`, `functions.php`, `visitor_tracker.php`

---

## Composer Scripts

Defined in `composer.json`:

```json
"scripts": {
    "test": "phpunit",
    "test:unit": "phpunit --testsuite=Unit"
}
```

---

## Dependencies

| Package | Version | Purpose |
|---|---|---|
| `phpunit/phpunit` | ^11.0 | Test framework (dev dependency) |

Install with:
```bash
composer install
```

---

## Related Pages

- [Core Utilities](utilities.md) — the functions being tested
- [Installation & Setup](installation.md) — composer install step
