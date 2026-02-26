# Installation & Setup

This guide covers setting up Giftz on a local XAMPP environment (Windows).

---

## Prerequisites

| Requirement | Minimum Version | Notes |
|---|---|---|
| PHP | 8.2 | Bundled with XAMPP 8.2+ |
| MySQL | 5.7 | Bundled with XAMPP |
| Apache | 2.4 | Bundled with XAMPP |
| Composer | 2.x | Required for dev/testing only |

---

## Step 1 — Place the Files

Clone or copy the project into the XAMPP web root:

```
C:\xampp\htdocs\giftz\
```

The application must be accessible at `http://localhost/giftz/`.

---

## Step 2 — Start XAMPP Services

Open the XAMPP Control Panel and start:
- **Apache**
- **MySQL**

---

## Step 3 — Create the Database

Open phpMyAdmin at `http://localhost/phpmyadmin` or use the MySQL CLI:

```sql
CREATE DATABASE giftz_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## Step 4 — Import the Schema

**Option A — Import schema + migrations individually:**

```bash
mysql -u root giftz_db < database/giftz_db.sql
mysql -u root giftz_db < database/add_returns.sql
mysql -u root giftz_db < database/add_expenses.sql
mysql -u root giftz_db < database/add_announcements.sql
mysql -u root giftz_db < database/add_visitor_logs.sql
```

**Option B — Use the all-in-one seed file (recommended for first-time setup):**

```bash
mysql -u root < database/seed.sql
```

The seed file drops and recreates `giftz_db` from scratch, applies all migrations, and inserts realistic sample data covering 6 months of activity.

---

## Step 5 — Configure the Application

Open `config/config.php` and verify the settings:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'giftz_db');
define('DB_USER', 'root');
define('DB_PASS', '');           // Change if your MySQL has a password
define('BASE_URL', 'http://localhost/giftz');
```

See [Configuration](configuration.md) for all available constants.

---

## Step 6 — Install Composer Dependencies (Optional)

Required only if you want to run the PHPUnit test suite:

```bash
cd C:\xampp\htdocs\giftz
composer install
```

---

## Step 7 — Open the Application

Navigate to:

```
http://localhost/giftz/
```

You will be redirected to the login page. Use the default admin credentials:

| Field | Value |
|---|---|
| Email | admin@giftz.local |
| Password | Admin@123 |

---

## Database Files Reference

| File | Purpose |
|---|---|
| `database/giftz_db.sql` | Base schema — 10 core tables |
| `database/add_returns.sql` | Migration — `sale_returns`, `sale_return_items` |
| `database/add_expenses.sql` | Migration — `expense_categories`, `expenses` |
| `database/add_announcements.sql` | Migration — `announcements` |
| `database/add_visitor_logs.sql` | Migration — `visitor_logs` |
| `database/seed.sql` | All-in-one: schema + migrations + sample data |

---

## Troubleshooting

### Blank page or PHP errors
- Ensure PHP 8.2+ is active in XAMPP
- Check `config/config.php` for correct DB credentials
- Check Apache error log: `C:\xampp\logs\error.log`

### Database connection failed
- Confirm MySQL is running in XAMPP Control Panel
- Verify `DB_USER`, `DB_PASS`, and `DB_NAME` in `config/config.php`

### Images not uploading
- Ensure `assets/uploads/products/` directory exists and is writable
- Check PHP `upload_max_filesize` and `post_max_size` in `php.ini`

### Visitor analytics not logging
- Run `database/add_visitor_logs.sql` or visit `/visitors/migrate.php`
- Confirm the `visitor_logs` table exists in `giftz_db`

---

## Related Pages

- [Configuration](configuration.md) — all config constants explained
- [Database Schema](database.md) — table structure reference
- [Testing](testing.md) — run the PHPUnit test suite
