# Configuration

**File:** `config/config.php`

All application-wide settings are defined as PHP constants in this file. It also starts the PHP session and sets the timezone.

---

## Database

| Constant | Default | Description |
|---|---|---|
| `DB_HOST` | `localhost` | MySQL server hostname |
| `DB_NAME` | `giftz_db` | Database name |
| `DB_USER` | `root` | MySQL username |
| `DB_PASS` | `` | MySQL password (empty for XAMPP default) |
| `DB_CHARSET` | `utf8mb4` | Connection character set |

**Usage:** These constants are used by `includes/db.php` to open the PDO connection.

---

## Application

| Constant | Default | Description |
|---|---|---|
| `APP_NAME` | `Giftz Inventory` | Displayed in the browser title and sidebar header |
| `APP_VERSION` | `1.0.0` | Version string (shown in footer) |
| `BASE_URL` | `http://localhost/giftz` | Base URL for all generated links and redirects |
| `BASE_PATH` | *(auto)* | Absolute filesystem path to the project root (`dirname(__DIR__)` from config/) |

**Important:** `BASE_URL` must match the actual URL of the application. If you move the project (e.g., to a different subdirectory or server), update this value.

---

## File Uploads

| Constant | Default | Description |
|---|---|---|
| `UPLOAD_PATH` | `{BASE_PATH}/assets/uploads/products` | Absolute filesystem path for product image uploads |
| `UPLOAD_URL` | `{BASE_URL}/assets/uploads/products` | Public URL for serving uploaded product images |

---

## Currency

| Constant | Default | Description |
|---|---|---|
| `CURRENCY_SYMBOL` | `₹` | Displayed before all monetary values |
| `CURRENCY_CODE` | `INR` | ISO 4217 currency code (informational) |

To change currency, update both constants. The `formatCurrency()` function uses `CURRENCY_SYMBOL`.

---

## Pagination

| Constant | Default | Description |
|---|---|---|
| `ROWS_PER_PAGE` | `20` | Default number of rows per page across all list views |

---

## Stock Alerts

| Constant | Default | Description |
|---|---|---|
| `LOW_STOCK_THRESHOLD` | `5` | Global fallback threshold; individual products use their own `min_stock_level` |

---

## Timezone

```php
date_default_timezone_set('Asia/Kolkata');
```

All `created_at` / `updated_at` timestamps are stored and displayed in IST. To change the timezone, update this line.

---

## Error Reporting

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

**Development only.** Before deploying to any internet-facing server, change to:

```php
error_reporting(0);
ini_set('display_errors', 0);
```

---

## Session

```php
session_start();
```

The session is started automatically when `config.php` is loaded. Every admin page requires an active session (via `requireLogin()`).

---

## Full File Reference

```php
<?php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'giftz_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application
define('APP_NAME', 'Giftz Inventory');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/giftz');
define('BASE_PATH', dirname(__DIR__));

// Uploads
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/products');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/products');

// Currency
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_CODE', 'INR');

// Pagination
define('ROWS_PER_PAGE', 20);

// Stock alerts
define('LOW_STOCK_THRESHOLD', 5);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting (disable for production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session
session_start();
```

---

## Related Pages

- [Installation & Setup](installation.md) — step-by-step setup guide
- [Database Schema](database.md) — table structure
