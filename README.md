# Giftz Inventory Management System

A full-featured web-based **Inventory Management & Point of Sale (POS)** system for a gift and clothing shop. Handles inventory tracking, sales, purchase orders, expenses, returns/refunds, customer management, and storefront visitor analytics.

---

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Tech Stack](#tech-stack)
4. [Project Structure](#project-structure)
5. [Installation & Setup](#installation--setup)
6. [Configuration](#configuration)
7. [Default Credentials](#default-credentials)
8. [Database Schema](#database-schema)
9. [Module Documentation](#module-documentation)
   - [Dashboard](#dashboard)
   - [Point of Sale (POS)](#point-of-sale-pos)
   - [Sales Management](#sales-management)
   - [Purchase Orders](#purchase-orders)
   - [Products & Inventory](#products--inventory)
   - [Categories](#categories)
   - [Suppliers](#suppliers)
   - [Customers](#customers)
   - [Expenses](#expenses)
   - [Reports](#reports)
   - [Visitor Analytics](#visitor-analytics)
   - [Announcements](#announcements)
   - [Users](#users)
   - [Public Storefront](#public-storefront)
10. [Core Utilities](#core-utilities)
11. [Frontend Assets](#frontend-assets)
12. [Authentication & Authorization](#authentication--authorization)
13. [Testing](#testing)
14. [Security](#security)
15. [Known Limitations](#known-limitations)

---

## Overview

**Giftz** is a localhost-hosted PHP/MySQL web application built for small retail shops. It provides a complete back-office suite alongside a public-facing product catalog. The system tracks every inventory movement from purchase receipt to sale, return, or adjustment, giving the business full auditability.

- **Version:** 1.0.0
- **Currency:** Indian Rupee (₹ / INR)
- **Timezone:** Asia/Kolkata (IST)
- **Deployment target:** XAMPP on Windows (localhost)
- Practicing Git workflow — branch created on 25 June 2026.

---

## Features

| Category | Feature |
|---|---|
| **POS** | Cart-based checkout, barcode scanner (USB/BT), multi-payment methods, discount (flat/%) |
| **Sales** | Sales history, printable invoices, sale void, partial returns/refunds |
| **Inventory** | Product CRUD with image upload, stock tracking, low-stock alerts, auto-SKU |
| **Purchases** | Purchase orders (PO), item receiving, PO cancellation, supplier management |
| **Expenses** | Expense ledger, 9 preset categories, auto-reference numbers, profit integration |
| **Customers** | Customer database, purchase history, lifetime value, spending trend chart |
| **Reports** | Sales report, profit/loss analysis, inventory valuation, stock movement audit log |
| **Analytics** | Storefront visitor tracking (privacy-safe hashing), device breakdown, top products |
| **Announcements** | Manage public storefront banners (active/inactive toggle, ordering) |
| **Users** | Role-based access (admin/staff), profile self-service, password change |
| **Storefront** | Public product catalog with search, filter, and pagination (no login required) |
| **Testing** | PHPUnit 11.0 suite — 95 tests across auth, functions, and visitor tracker |

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.2+ |
| **Database** | MySQL 5.7+ (PDO, InnoDB) |
| **Frontend** | HTML5, CSS3, Vanilla JavaScript |
| **Charts** | Chart.js (CDN) |
| **Dependency Manager** | Composer |
| **Testing** | PHPUnit 11.0 |

No PHP frameworks are used. All queries are written as PDO prepared statements.

---

## Project Structure

```
giftz/
├── config/
│   └── config.php              # App constants, DB credentials, session start
├── database/
│   ├── giftz_db.sql            # Base schema (10 core tables)
│   ├── seed.sql                # Full production seed data
│   ├── add_announcements.sql   # Migration: announcements table
│   ├── add_expenses.sql        # Migration: expense_categories + expenses tables
│   ├── add_returns.sql         # Migration: sale_returns + sale_return_items tables
│   ├── add_visitor_logs.sql    # Migration: visitor_logs table
│   └── setup.php               # Setup helper
├── includes/
│   ├── auth.php                # requireLogin(), requireRole(), attemptLogin(), logout()
│   ├── db.php                  # PDO singleton — db() function
│   ├── functions.php           # 20+ shared utility functions
│   ├── visitor_tracker.php     # trackVisit() — public page analytics
│   ├── header.php              # Sidebar layout template
│   └── footer.php              # Footer template
├── public/                     # Customer-facing storefront (no auth required)
│   ├── index.php               # Redirect to catalog
│   ├── catalog.php             # Product catalog with search & filters
│   └── product.php             # Single product detail page
├── dashboard.php               # Main admin dashboard
├── login.php                   # Login page
├── logout.php                  # Session termination
├── index.php                   # Root redirect (login → dashboard)
├── products/                   # Product management
│   ├── index.php, add.php, edit.php, delete.php
├── categories/
│   └── index.php               # Category CRUD
├── suppliers/
│   ├── index.php, add.php, edit.php
├── customers/
│   ├── index.php, add.php, edit.php, view.php
├── sales/
│   ├── pos.php                 # POS checkout interface
│   ├── invoice.php             # Print-friendly invoice
│   ├── index.php               # Sales history
│   ├── return.php              # Process a return
│   ├── returns.php             # Returns history list
│   └── void.php                # Void a sale
├── purchases/
│   ├── index.php, add.php, view.php, cancel.php
├── expenses/
│   ├── index.php, add.php, edit.php, delete.php
├── reports/
│   ├── sales.php               # Revenue by period & payment method
│   ├── profit.php              # P&L: revenue, COGS, expenses, net profit
│   ├── inventory.php           # Stock valuation & slow movers
│   └── stock_movements.php     # Audit trail with CSV export
├── users/
│   ├── index.php, add.php, profile.php
├── announcements/
│   ├── index.php, add.php, edit.php, delete.php, toggle.php
├── visitors/
│   ├── index.php               # Analytics dashboard
│   └── migrate.php             # Ensures visitor_logs table exists
├── assets/
│   ├── css/
│   │   ├── style.css           # Main stylesheet (~650 lines)
│   │   ├── sidebar.css         # Sidebar navigation (~360 lines)
│   │   └── pos.css             # POS-specific styles (~530 lines)
│   ├── js/
│   │   ├── main.js             # Toast notifications, helpers (~140 lines)
│   │   ├── charts.js           # Chart.js wrappers (~300 lines)
│   │   └── pos.js              # POS cart + barcode scanner (~375 lines)
│   └── uploads/products/       # Product images
├── tests/
│   ├── bootstrap.php           # PHPUnit test environment setup
│   └── Unit/
│       ├── AuthTest.php        # 38 tests
│       ├── FunctionsTest.php   # 42 tests
│       └── VisitorTrackerTest.php  # 15 tests
├── vendor/                     # Composer dependencies
├── composer.json
├── phpunit.xml
└── CHANGELOG.md
```

---

## Installation & Setup

### Prerequisites

- PHP 8.2 or higher
- MySQL 5.7 or higher
- XAMPP (or equivalent: Apache + MySQL + PHP)
- Composer (for development/testing only)

### Steps

**1. Place files**

Clone or copy the project into your web server root:
```
C:\xampp\htdocs\giftz\
```

**2. Create the database**

```sql
CREATE DATABASE giftz_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**3. Import the base schema**

```bash
mysql -u root giftz_db < database/giftz_db.sql
```

**4. Run migrations** (in order)

```bash
mysql -u root giftz_db < database/add_returns.sql
mysql -u root giftz_db < database/add_expenses.sql
mysql -u root giftz_db < database/add_announcements.sql
mysql -u root giftz_db < database/add_visitor_logs.sql
```

> Alternatively, use `database/seed.sql` which drops and recreates the entire database including all migrations and sample data in one step:
> ```bash
> mysql -u root < database/seed.sql
> ```

**5. (Optional) Load seed data**

The seed file includes 2 users, 8 categories, 25 products, 12 customers, 11 purchase orders, 37 sales, 3 returns, and 105 stock movement rows — enough for all reports and charts to display meaningful data.

```bash
mysql -u root < database/seed.sql
```

**6. Configure the application**

Edit `config/config.php` if your database credentials differ from the defaults (see [Configuration](#configuration)).

**7. Install Composer dependencies** (required for tests only)

```bash
composer install
```

**8. Open in browser**

```
http://localhost/giftz/
```

---

## Configuration

All application settings are in `config/config.php`.

| Constant | Default | Description |
|---|---|---|
| `DB_HOST` | `localhost` | MySQL host |
| `DB_NAME` | `giftz_db` | Database name |
| `DB_USER` | `root` | MySQL username |
| `DB_PASS` | `` | MySQL password |
| `DB_CHARSET` | `utf8mb4` | Connection charset |
| `APP_NAME` | `Giftz Inventory` | Application display name |
| `APP_VERSION` | `1.0.0` | Version string |
| `BASE_URL` | `http://localhost/giftz` | Base URL for links |
| `BASE_PATH` | *(auto)* | Absolute filesystem path |
| `UPLOAD_PATH` | `assets/uploads/products` | Product image directory |
| `CURRENCY_SYMBOL` | `₹` | Currency symbol |
| `CURRENCY_CODE` | `INR` | ISO 4217 currency code |
| `ROWS_PER_PAGE` | `20` | Default pagination limit |
| `LOW_STOCK_THRESHOLD` | `5` | Alert threshold for low stock |

The file also sets the timezone (`Asia/Kolkata`) and starts the PHP session.

---

## Default Credentials

| Role | Email | Password |
|---|---|---|
| Admin | admin@giftz.local | Admin@123 |
| Staff | staff@giftz.local | Admin@123 |

> Change these immediately in a production environment. Passwords are stored as bcrypt hashes.

---

## Database Schema

The database has **14 tables**: 10 in the base schema, 4 added via migrations.

### Core Tables

#### `users`
Stores admin and staff accounts.

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| name | VARCHAR(100) | |
| email | VARCHAR(150) UNIQUE | Login identifier |
| password | VARCHAR(255) | bcrypt hash |
| role | ENUM('admin','staff') | |
| status | ENUM('active','inactive') | |
| last_login | DATETIME | Updated on each login |
| created_at / updated_at | DATETIME | |

#### `categories`
Hierarchical product categories.

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| name | VARCHAR(100) | |
| type | ENUM('gift','cloth','both') | |
| parent_id | INT NULL FK→categories | Self-referential |
| sort_order | INT | Display ordering |

#### `suppliers`
Vendor/supplier contact information.

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| name | VARCHAR(150) | |
| contact_person | VARCHAR(100) | |
| phone | VARCHAR(20) | |
| email | VARCHAR(150) | |
| address | TEXT | |

#### `products`
Central inventory table.

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| sku | VARCHAR(30) UNIQUE | Auto-generated (e.g. GFT-G0001) |
| name | VARCHAR(200) | |
| category_id | INT FK→categories | |
| type | ENUM('gift','cloth','both') | |
| cost_price | DECIMAL(12,2) | Purchase cost |
| selling_price | DECIMAL(12,2) | |
| stock_qty | INT | Current stock level |
| min_stock_level | INT | Triggers low-stock alert |
| size / color / occasion | VARCHAR | Optional attributes |
| image | VARCHAR(255) | Filename in uploads dir |
| status | ENUM('active','inactive') | Soft delete via inactive |

#### `customers`

| Column | Type |
|---|---|
| id | INT AUTO_INCREMENT PK |
| name | VARCHAR(100) |
| phone | VARCHAR(20) |
| email | VARCHAR(150) |
| address | TEXT |

#### `sales`
One row per completed checkout or voided transaction.

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| invoice_no | VARCHAR(30) UNIQUE | INV-YYYY-NNNNN |
| customer_id | INT NULL FK→customers | NULL = walk-in |
| subtotal | DECIMAL(12,2) | Before discount & tax |
| discount | DECIMAL(12,2) | |
| discount_type | ENUM('flat','percent') | |
| tax | DECIMAL(12,2) | |
| total | DECIMAL(12,2) | Final amount charged |
| payment_method | ENUM('cash','card','gcash','maya','bank') | |
| status | ENUM('completed','voided') | |
| notes | TEXT | |
| created_by | INT FK→users | |

#### `sale_items`
Line items for each sale (one row per product per sale).

| Column | Type |
|---|---|
| id | INT AUTO_INCREMENT PK |
| sale_id | INT FK→sales |
| product_id | INT NULL FK→products |
| quantity | INT |
| unit_price | DECIMAL(12,2) |
| total_price | DECIMAL(12,2) |

#### `purchases`
Purchase orders sent to suppliers.

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| supplier_id | INT NULL FK→suppliers | |
| reference_no | VARCHAR(30) UNIQUE | PO-YYYY-NNN |
| purchase_date | DATE | |
| total_amount | DECIMAL(12,2) | |
| status | ENUM('pending','received','partial','cancelled') | |
| created_by | INT FK→users | |

#### `purchase_items`

| Column | Type |
|---|---|
| id | INT AUTO_INCREMENT PK |
| purchase_id | INT FK→purchases |
| product_id | INT NULL FK→products |
| quantity | INT |
| unit_cost | DECIMAL(12,2) |

#### `stock_movements`
Immutable audit log of every inventory change.

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| product_id | INT NULL FK→products | |
| type | ENUM('in','out','adjustment') | |
| quantity | INT | Always positive; type indicates direction |
| reference | VARCHAR(100) | Invoice no, PO ref, RET no, etc. |
| created_by | INT FK→users | |

### Migration Tables

#### `sale_returns` (add_returns.sql)

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| return_no | VARCHAR(30) UNIQUE | RET-YYYY-NNNNN |
| sale_id | INT FK→sales | Original sale |
| total_refund | DECIMAL(12,2) | |
| reason | TEXT | |
| created_by | INT FK→users | |

#### `sale_return_items` (add_returns.sql)

| Column | Type |
|---|---|
| id | INT AUTO_INCREMENT PK |
| return_id | INT FK→sale_returns |
| sale_item_id | INT NULL FK→sale_items |
| product_id | INT NULL FK→products |
| quantity | INT |
| unit_price / total_price | DECIMAL(12,2) |

#### `expense_categories` + `expenses` (add_expenses.sql)

**expense_categories** — 9 preset types: Rent & Utilities, Salaries & Wages, Office Supplies, Marketing & Ads, Equipment, Maintenance, Delivery & Freight, Professional Fees, Miscellaneous.

**expenses:**

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| reference_no | VARCHAR(30) UNIQUE | EXP-YYYY-NNNNN |
| category_id | INT FK→expense_categories | |
| amount | DECIMAL(12,2) | |
| description | VARCHAR(255) | |
| paid_to | VARCHAR(150) | Payee name |
| payment_method | ENUM('cash','card','gcash','maya','bank','transfer') | |
| expense_date | DATE | |
| notes | TEXT | |
| created_by | INT FK→users | |

#### `announcements` (add_announcements.sql)

| Column | Type |
|---|---|
| id | INT AUTO_INCREMENT PK |
| message | TEXT |
| emoji | VARCHAR(10) |
| is_active | TINYINT(1) |
| sort_order | INT |

#### `visitor_logs` (add_visitor_logs.sql)

| Column | Type | Notes |
|---|---|---|
| id | BIGINT AUTO_INCREMENT PK | |
| session_hash | CHAR(64) | SHA-256 of PHP session ID |
| ip_hash | CHAR(64) | SHA-256 of visitor IP |
| page | ENUM('catalog','product','home') | |
| page_id | INT NULL | Product ID when page='product' |
| referrer | VARCHAR(500) | HTTP_REFERER |
| device | ENUM('desktop','mobile','tablet','bot') | |
| created_at | DATETIME | Indexed for queries |

---

## Module Documentation

### Dashboard

**File:** `dashboard.php`

The main landing page after login. Displays a real-time business overview.

**KPI Cards:**
- Today's Revenue
- This Month's Revenue
- Total Active Products (with low-stock sub-count)
- Total Customers
- Today's Unique Visitors (from visitor_logs)

**Charts:**
- Sales Trend — 7-day line chart of daily revenue
- Category Sales — Doughnut chart of top 8 categories this month

**Tables:**
- Recent Sales — last 8 completed sales
- Low Stock Alerts — products at or below their `min_stock_level`

---

### Point of Sale (POS)

**Files:** `sales/pos.php`, `assets/js/pos.js`, `assets/css/pos.css`

The primary checkout interface for cashiers.

**Layout:** Two-column — product grid (left) + cart panel (right).

**Workflow:**
1. Search or browse products by category
2. Click a product card (or scan its barcode) to add it to the cart
3. Adjust quantities using +/- buttons in the cart
4. Select or create a customer (optional — defaults to Walk-in)
5. Apply a discount (flat ₹ amount or percentage)
6. Set tax amount
7. Choose payment method: cash, card, GCash, Maya, or bank
8. Submit → creates `sales` + `sale_items` records, deducts stock, logs movements
9. Redirected to printable invoice

**Barcode Scanner Support:**
- Press **F2** to focus the barcode input field from anywhere on the page
- Scanner sends characters in rapid bursts followed by Enter
- `processBarcode(code)` looks up the SKU against all product cards and calls `card.click()` on a match
- Visual feedback: barcode field flashes green (found), red (not found), or amber (out of stock) for 600 ms
- **Global accumulator fallback**: when no form element has focus, keystroke bursts are captured automatically (distinguishes fast scanner input from slow human typing)

**Payment Methods:** cash, card, gcash, maya, bank

---

### Sales Management

**Files:** `sales/index.php`, `sales/invoice.php`, `sales/void.php`, `sales/return.php`, `sales/returns.php`

**Sales List (`sales/index.php`):**
- Paginated history with filters: date range, customer, payment method, status
- Actions: View Invoice, Process Return, Void

**Invoice (`sales/invoice.php`):**
- Print-friendly view with all line items, totals, and payment details
- Return button (completed sales only)

**Void (`sales/void.php`):**
- POST-only handler
- Marks sale `status = 'voided'`
- Restores all item quantities back to stock via `updateStock()` with `type = 'in'`

**Return / Refund (`sales/return.php`):**
- Shows returnable items (quantity not yet returned)
- Prevents over-returning via query of existing `sale_return_items`
- Live JS recalculates refund total as quantities are adjusted
- On submit: writes `sale_returns` + `sale_return_items` in a transaction, restores stock

**Returns List (`sales/returns.php`):**
- Paginated list with KPI cards: count, total refunded, average refund
- Links back to original invoices

---

### Purchase Orders

**Files:** `purchases/index.php`, `purchases/add.php`, `purchases/view.php`, `purchases/cancel.php`

**Create PO (`purchases/add.php`):**
- Select supplier
- Add line items: product, quantity, unit cost
- Auto-generates `PO-YYYY-NNN` reference

**PO List (`purchases/index.php`):**
- Filter by status: pending / received / partial / cancelled
- Cancel button for pending POs

**Receive Items (`purchases/view.php`):**
- Shows all line items with ordered vs. received quantities
- "Mark Received" modal to record received items
- On receive: `stock_qty += qty`, `stock_movements` row inserted with `type = 'in'`

**Cancel (`purchases/cancel.php`):**
- POST-only; only allowed for `pending` POs
- Sets `status = 'cancelled'`; no stock effect (stock only moves on receive)

---

### Products & Inventory

**Files:** `products/index.php`, `products/add.php`, `products/edit.php`, `products/delete.php`

**Product List:**
- Filter by search term, category, type, status
- Stock level indicators (color-coded)
- Inline low-stock warning badge

**Add/Edit Product:**
- Auto-generates SKU on add (format: `GFT-G0001`)
- Fields: name, category, type, cost price, selling price, stock qty, min stock, size, color, occasion, image upload, status
- Image upload validates MIME type (JPEG, PNG, GIF, WebP)

**Delete (`products/delete.php`):**
- POST-only
- Soft delete: sets `status = 'inactive'` (preserves historical data)

**Stock Update (`updateStock()`):**
- Called by POS checkout, purchase receiving, sale void, and returns
- Signature: `updateStock($productId, $qty, $direction, $reference, $userId)`
- `direction`: `'in'`, `'out'`, or `'adjustment'`
- Atomically updates `products.stock_qty` and inserts a `stock_movements` row

---

### Categories

**File:** `categories/index.php`

- View and manage product categories
- Supports parent/child hierarchy via `parent_id`
- `sort_order` field controls display ordering
- Types: gift, cloth, or both

---

### Suppliers

**Files:** `suppliers/index.php`, `suppliers/add.php`, `suppliers/edit.php`

Standard CRUD for supplier/vendor records. Each supplier has: name, contact person, phone, email, address.

---

### Customers

**Files:** `customers/index.php`, `customers/add.php`, `customers/edit.php`, `customers/view.php`

**Customer List:** Paginated with search. Displays total spend per customer.

**Customer Profile (`customers/view.php`):**
- KPIs: Total Orders, Total Spent (lifetime value), Average Order Value, Total Saved (discounts)
- Profile card with avatar initial
- Top 5 Products by spend
- 6-month spending trend chart
- Full paginated purchase history

---

### Expenses

**Files:** `expenses/index.php`, `expenses/add.php`, `expenses/edit.php`, `expenses/delete.php`

**Expense Ledger:**
- Filters: date range, category, payment method, free text
- KPI cards: Period Expenses, Expense Count, Top Category, Today's Expenses
- Auto-generates `EXP-YYYY-NNNNN` reference numbers

**Categories (9 presets):**
Rent & Utilities, Salaries & Wages, Office Supplies, Marketing & Ads, Equipment, Maintenance, Delivery & Freight, Professional Fees, Miscellaneous

**Delete:** POST-only, admin-only. Permanently removes the record.

---

### Reports

**Files:** `reports/sales.php`, `reports/profit.php`, `reports/inventory.php`, `reports/stock_movements.php`

**Sales Report (`reports/sales.php`):**
- Revenue grouped by day / week / month
- Payment method breakdown

**Profit & Loss (`reports/profit.php`):**
- Revenue, COGS (cost of sold items), Gross Profit
- Operating Expenses (from `expenses` table), Net Profit, Net Margin
- Period breakdown table with stacked bar chart

**Inventory Report (`reports/inventory.php`):**
- Current stock levels and valuation (qty × cost price)
- Slow-moving items (low sales velocity)
- Reorder recommendations

**Stock Movement Log (`reports/stock_movements.php`):**
- Full audit trail from `stock_movements` table
- Filters: date range, type (in/out/adjustment), product, free text
- Reference field auto-links to the relevant invoice or PO
- **CSV export** (honours all active filters)
- KPI cards: Stock In units, Stock Out units, Adjustments, Total entries

---

### Visitor Analytics

**Files:** `visitors/index.php`, `visitors/migrate.php`, `includes/visitor_tracker.php`

**Tracking:**
- Call `trackVisit($page, $pageId)` on any public page
- Detects device type from User-Agent (desktop / mobile / tablet); skips bots
- Hashes session ID and IP address (SHA-256) for privacy — no PII stored
- Deduplicates: one log entry per session + page per hour

**Analytics Dashboard (`visitors/index.php`):**
- KPIs: Today's Unique Visitors, Weekly, Monthly, Total Pageviews
- Trend Chart: line graph of unique visitors + pageviews per day
- Device Breakdown: pie chart (desktop / mobile / tablet)
- Page Breakdown: hits + unique visitors per page type
- Top 10 Products by view count
- Recent Visits: paginated table with device, page, referrer, timestamp
- Date range filter: Last 7 / 30 / 90 days or all time

---

### Announcements

**Files:** `announcements/index.php`, `announcements/add.php`, `announcements/edit.php`, `announcements/delete.php`, `announcements/toggle.php`

- Create banner messages with an emoji and sort order
- Toggle active/inactive status (AJAX-friendly POST endpoint)
- Active announcements are displayed in the public storefront header

---

### Users

**Files:** `users/index.php`, `users/add.php`, `users/profile.php`

**User List:** Admin-only. Shows role and status badges.

**Add User:** Admin-only. Set name, email, password, role (admin/staff), status (active/inactive).

**Profile (`users/profile.php`):** Available to all logged-in users.
- **Edit Info tab:** Update name and email; session updated immediately (no re-login needed)
- **Password tab:** Requires current password; live match indicator colors confirm field
- **KPI cards:** Total Sales processed, Lifetime Revenue, Today's activity, Purchase Orders created
- **Recent Sales table:** Last 8 transactions by this user

---

### Public Storefront

**Files:** `public/catalog.php`, `public/product.php`, `public/index.php`

No login required. Intended for customers browsing products.

**Catalog (`public/catalog.php`):**
- Paginated product grid
- Search by name or SKU
- Filter by category
- Sort by: name (A-Z), price (low-high), price (high-low), newest
- Calls `trackVisit('catalog')` for analytics

**Product Detail (`public/product.php`):**
- Shows product image, name, price, category, color, size, occasion
- Calls `trackVisit('product', $productId)` for analytics

**Announcements:** Active announcements from the DB are shown as a banner on catalog pages.

---

## Core Utilities

### `includes/db.php`
Returns a **PDO singleton** connection. Call `db()` anywhere.

```php
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
```

- Exception error mode (PDOException on failure)
- Associative fetch mode by default
- Emulated prepares disabled (native prepared statements)

### `includes/auth.php`

| Function | Description |
|---|---|
| `requireLogin()` | Redirects to `/login.php` if no valid session |
| `requireRole('admin')` | Redirects with error flash if role doesn't match |
| `isAdmin()` | Returns `true` if `$_SESSION['user_role'] === 'admin'` |
| `currentUser()` | Returns `['id', 'name', 'role']` from session |
| `attemptLogin($email, $pass)` | Verifies credentials, sets session, updates `last_login` |
| `logout()` | Clears session data, destroys cookies, calls `session_destroy()` |

### `includes/functions.php`

**Formatting:**
- `formatCurrency($amount)` → `₹1,234.56`
- `formatDate($date)` → `Feb 26, 2026`
- `formatDateTime($dt)` → `Feb 26, 2026 02:30 PM`
- `e($str)` → `htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`
- `statusBadge($status)` → HTML `<span class="badge badge-*">`
- `productEmoji($category, $type)` → category-appropriate emoji

**Reference Number Generators:**
- `generateSKU($categoryCode, $type)` → `GFT-G0001`
- `generateInvoiceNo()` → `INV-2026-00001`
- `generatePoRef()` → `PO-2026-001`
- `generateExpenseNo()` → `EXP-2026-00001`
- `generateReturnNo()` → `RET-2026-00001`

**Stock:**
- `updateStock($productId, $qty, $direction, $reference, $userId)` — updates `products.stock_qty` and inserts into `stock_movements`

**Pagination:**
- `paginate($total, $page, $perPage)` → returns array with `total`, `current`, `offset`, `has_prev`, `has_next`
- `paginationLinks($p, $baseUrl)` → HTML `<nav>` element

**Flash Messages:**
- `flash($type, $message)` → stores in `$_SESSION['flash'][]`
- `getFlash()` → returns and clears all flash messages

**Image Upload:**
- `uploadImage($file, $dir)` → validates MIME type, moves to upload directory, returns filename

**Low Stock:**
- `getLowStockCount()` → `COUNT(*)` of products at or below `min_stock_level`

---

## Frontend Assets

### CSS (`assets/css/`)

| File | Lines | Purpose |
|---|---|---|
| `style.css` | ~650 | Global: reset, typography, buttons, forms, cards, badges, tables, pagination |
| `sidebar.css` | ~360 | Sidebar navigation, brand, active states, mobile hamburger |
| `pos.css` | ~530 | POS layout: product grid, cart panel, barcode input, checkout form |

**Color scheme:** Purple gradients (`#6C63FF`), pink (`#FF6584`), teal and green accents.

### JavaScript (`assets/js/`)

| File | Lines | Purpose |
|---|---|---|
| `main.js` | ~140 | Toast notifications, confirmation dialogs, DOM helpers |
| `charts.js` | ~300 | Chart.js factory functions for all report charts |
| `pos.js` | ~375 | POS cart IIFE: cart state, quantity management, barcode scanner |

**Chart types available (`charts.js`):**
- `GiftzCharts.salesTrend(el, labels, data)` — line chart
- `GiftzCharts.categoryDoughnut(el, labels, data)` — doughnut
- `GiftzCharts.profitBar(el, labels, revenue, cogs, expenses?, netProfit?)` — stacked bar
- `GiftzCharts.inventoryStacked(el, labels, valued, cost)` — horizontal bar
- `GiftzCharts.stockMovement(el, labels, in, out, adj)` — grouped bar
- `GiftzCharts.trendLine(el, labels, visitors, pageviews)` — dual-series line

**POS cart API (`pos.js` — internal IIFE):**
- `POS.addItem(productId, qty, price)` — adds/increments cart item
- `POS.removeItem(productId)` — removes from cart
- `POS.updateQty(productId, qty)` — sets exact quantity
- `POS.calculateTotal(subtotal, discount, discType, tax)` — returns final total

---

## Authentication & Authorization

**Mechanism:** PHP session-based (no JWT, no cookies beyond session cookie).

**Session variables set on login:**

| Key | Value |
|---|---|
| `$_SESSION['user_id']` | User primary key |
| `$_SESSION['user_name']` | Display name |
| `$_SESSION['user_role']` | `'admin'` or `'staff'` |
| `$_SESSION['user_email']` | Email address |

**Role capabilities:**

| Action | Admin | Staff |
|---|---|---|
| Dashboard, POS, Sales, Purchases | Yes | Yes |
| Products, Categories, Suppliers, Customers | Yes | Yes |
| Expenses (view/add/edit) | Yes | Yes |
| Delete Expense | Yes | No |
| Users list, add | Yes | No |
| Announcements management | Yes | No |
| Reports | Yes | Yes |

**Public pages** (no login required): `public/catalog.php`, `public/product.php`

---

## Testing

**Framework:** PHPUnit 11.0
**Test count:** 95 tests across 3 test classes

### Run tests

```bash
composer test
# or
./vendor/bin/phpunit
```

### Test files

| File | Tests | Coverage |
|---|---|---|
| `tests/Unit/AuthTest.php` | 38 | `isAdmin()`, `currentUser()`, `logout()` — all branches |
| `tests/Unit/FunctionsTest.php` | 42 | All utility functions in `functions.php` |
| `tests/Unit/VisitorTrackerTest.php` | 15 | Device detection, tracking deduplication, bot skipping, privacy hashing |

### Configuration (`phpunit.xml`)

```xml
<phpunit bootstrap="tests/bootstrap.php">
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

The bootstrap file starts a PHP session (if not already started), loads Composer autoloader, and requires all `includes/` files.

---

## Security

| Area | Implementation |
|---|---|
| **Password storage** | bcrypt via PHP `password_hash()` / `password_verify()` |
| **SQL injection** | All queries use PDO prepared statements with `?` placeholders |
| **XSS prevention** | All output escaped with `e()` → `htmlspecialchars(ENT_QUOTES)` |
| **Session auth** | `requireLogin()` called at the top of every admin page |
| **Role enforcement** | `requireRole('admin')` guards destructive/admin-only actions |
| **Privacy** | Visitor IPs and session IDs stored as SHA-256 hashes, never plaintext |
| **Image upload** | MIME type validated before accepting uploaded files |
| **Transactions** | POS checkout and returns use `PDO::beginTransaction` / `rollBack` |

**Known gap:** CSRF tokens are not implemented on POST forms. Before deploying to a shared or internet-facing server, add CSRF token validation to all forms.

---

## Known Limitations

| # | Limitation | Notes |
|---|---|---|
| 1 | **No CSRF protection** | Forms lack hidden token fields |
| 2 | **No REST API** | All interactions are form-based page loads |
| 3 | **No bulk import/export** | Products and customers must be entered one at a time |
| 4 | **Single location** | No multi-branch or multi-store support |
| 5 | **No payment gateway** | Payment method is recorded but no real-time payment processing |
| 6 | **No email notifications** | No automated alerts for low stock, order confirmation, etc. |
| 7 | **No mobile app** | Web-only; no native iOS/Android app |
| 8 | **Error reporting on** | `display_errors = 1` in `config.php` — turn off for production |

---

## Reference Numbers

All generated reference numbers follow a consistent pattern:

| Type | Format | Example |
|---|---|---|
| Product SKU | `{CAT}-{TYPE}{NNNN}` | `GFT-G0001` |
| Sales Invoice | `INV-{YYYY}-{NNNNN}` | `INV-2026-00037` |
| Purchase Order | `PO-{YYYY}-{NNN}` | `PO-2026-011` |
| Expense | `EXP-{YYYY}-{NNNNN}` | `EXP-2026-00015` |
| Return | `RET-{YYYY}-{NNNNN}` | `RET-2026-00003` |

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full version history with detailed descriptions of every change, the files affected, and the reason for each change.
