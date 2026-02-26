# Database Schema

**Database:** `giftz_db`
**Engine:** MySQL (InnoDB recommended, MyISAM compatible)
**Charset:** utf8mb4

The schema consists of **14 tables**: 10 in the base schema (`giftz_db.sql`) and 4 added by migrations.

---

## Entity Relationship Overview

```
users ──────────────────────────────────────────────────┐
  │                                                      │ created_by
  │                                                      ▼
categories ◄── products ◄── sale_items ◄── sales ◄──────┤
                  │              │                       │
                  │              ▼                       │
                  │         sale_return_items ◄── sale_returns
                  │
                  ▼
             purchase_items ◄── purchases ◄── suppliers
                  │
                  ▼
            stock_movements

expenses ──── expense_categories
visitor_logs (standalone)
announcements (standalone)
```

---

## Core Tables

### `users`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT | |
| name | VARCHAR(100) | NOT NULL | |
| email | VARCHAR(150) | NOT NULL, UNIQUE | Login identifier |
| password | VARCHAR(255) | NOT NULL | bcrypt hash |
| role | ENUM('admin','staff') | NOT NULL | |
| status | ENUM('active','inactive') | NOT NULL, DEFAULT 'active' | |
| last_login | DATETIME | NULL | Updated on each login |
| created_at | DATETIME | DEFAULT NOW() | |
| updated_at | DATETIME | ON UPDATE NOW() | |

---

### `categories`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT | |
| name | VARCHAR(100) | NOT NULL | |
| type | ENUM('gift','cloth','both') | NOT NULL | |
| parent_id | INT | NULL, FK→categories.id | Self-referential hierarchy |
| sort_order | INT | DEFAULT 0 | Ascending = displayed first |
| created_at | DATETIME | DEFAULT NOW() | |

---

### `suppliers`

| Column | Type | Constraints |
|---|---|---|
| id | INT | PK, AUTO_INCREMENT |
| name | VARCHAR(150) | NOT NULL |
| contact_person | VARCHAR(100) | NULL |
| phone | VARCHAR(20) | NULL |
| email | VARCHAR(150) | NULL |
| address | TEXT | NULL |
| created_at | DATETIME | DEFAULT NOW() |
| updated_at | DATETIME | ON UPDATE NOW() |

---

### `products`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT | |
| sku | VARCHAR(30) | NOT NULL, UNIQUE | Auto-generated |
| name | VARCHAR(200) | NOT NULL | |
| category_id | INT | NOT NULL, FK→categories.id | INDEX |
| type | ENUM('gift','cloth','both') | NOT NULL | |
| cost_price | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | Purchase cost |
| selling_price | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | Retail price |
| stock_qty | INT | NOT NULL, DEFAULT 0 | Current stock |
| min_stock_level | INT | NOT NULL, DEFAULT 5 | Alert threshold |
| size | VARCHAR(50) | NULL | |
| color | VARCHAR(50) | NULL | |
| occasion | VARCHAR(100) | NULL | e.g. Birthday, Wedding |
| image | VARCHAR(255) | NULL | Filename in uploads dir |
| status | ENUM('active','inactive') | NOT NULL, DEFAULT 'active' | Soft delete |
| created_at | DATETIME | DEFAULT NOW() | |
| updated_at | DATETIME | ON UPDATE NOW() | |

---

### `customers`

| Column | Type | Constraints |
|---|---|---|
| id | INT | PK, AUTO_INCREMENT |
| name | VARCHAR(100) | NOT NULL |
| phone | VARCHAR(20) | NULL |
| email | VARCHAR(150) | NULL |
| address | TEXT | NULL |
| created_at | DATETIME | DEFAULT NOW() |
| updated_at | DATETIME | ON UPDATE NOW() |

---

### `sales`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT | |
| invoice_no | VARCHAR(30) | NOT NULL, UNIQUE | INV-YYYY-NNNNN |
| customer_id | INT | NULL, FK→customers.id | NULL = walk-in |
| subtotal | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | Before discount & tax |
| discount | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | |
| discount_type | ENUM('flat','percent') | NOT NULL, DEFAULT 'flat' | |
| tax | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | |
| total | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | Final amount charged |
| payment_method | ENUM('cash','card','gcash','maya','bank') | NOT NULL | |
| status | ENUM('completed','voided') | NOT NULL, DEFAULT 'completed' | |
| notes | TEXT | NULL | |
| created_by | INT | NOT NULL, FK→users.id | |
| created_at | DATETIME | DEFAULT NOW() | |

---

### `sale_items`

| Column | Type | Constraints |
|---|---|---|
| id | INT | PK, AUTO_INCREMENT |
| sale_id | INT | NOT NULL, FK→sales.id |
| product_id | INT | NULL, FK→products.id |
| quantity | INT | NOT NULL |
| unit_price | DECIMAL(12,2) | NOT NULL |
| total_price | DECIMAL(12,2) | NOT NULL |

---

### `purchases`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT | |
| supplier_id | INT | NULL, FK→suppliers.id | |
| reference_no | VARCHAR(30) | NOT NULL, UNIQUE | PO-YYYY-NNN |
| purchase_date | DATE | NOT NULL | |
| total_amount | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | |
| status | ENUM('pending','received','partial','cancelled') | NOT NULL, DEFAULT 'pending' | |
| notes | TEXT | NULL | |
| created_by | INT | NOT NULL, FK→users.id | |
| created_at | DATETIME | DEFAULT NOW() | |
| updated_at | DATETIME | ON UPDATE NOW() | |

---

### `purchase_items`

| Column | Type | Constraints |
|---|---|---|
| id | INT | PK, AUTO_INCREMENT |
| purchase_id | INT | NOT NULL, FK→purchases.id |
| product_id | INT | NULL, FK→products.id |
| quantity | INT | NOT NULL |
| unit_cost | DECIMAL(12,2) | NOT NULL |

---

### `stock_movements`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT | |
| product_id | INT | NULL, FK→products.id | |
| type | ENUM('in','out','adjustment') | NOT NULL | |
| quantity | INT | NOT NULL | Always positive |
| reference | VARCHAR(100) | NULL | Invoice/PO/Return No |
| created_by | INT | NULL, FK→users.id | |
| created_at | DATETIME | DEFAULT NOW() | INDEX |

---

## Migration Tables

### `sale_returns` (add_returns.sql)

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT | |
| return_no | VARCHAR(30) | NOT NULL, UNIQUE | RET-YYYY-NNNNN |
| sale_id | INT | NOT NULL, FK→sales.id | |
| total_refund | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | |
| reason | TEXT | NULL | |
| created_by | INT | NOT NULL, FK→users.id | |
| created_at | DATETIME | DEFAULT NOW() | |

---

### `sale_return_items` (add_returns.sql)

| Column | Type | Constraints |
|---|---|---|
| id | INT | PK, AUTO_INCREMENT |
| return_id | INT | NOT NULL, FK→sale_returns.id |
| sale_item_id | INT | NULL, FK→sale_items.id |
| product_id | INT | NULL, FK→products.id |
| quantity | INT | NOT NULL |
| unit_price | DECIMAL(12,2) | NOT NULL |
| total_price | DECIMAL(12,2) | NOT NULL |

---

### `expense_categories` (add_expenses.sql)

| Column | Type | Constraints |
|---|---|---|
| id | INT | PK, AUTO_INCREMENT |
| name | VARCHAR(100) | NOT NULL |
| sort_order | INT | DEFAULT 0 |

---

### `expenses` (add_expenses.sql)

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT | |
| reference_no | VARCHAR(30) | NOT NULL, UNIQUE | EXP-YYYY-NNNNN |
| category_id | INT | NOT NULL, FK→expense_categories.id | |
| amount | DECIMAL(12,2) | NOT NULL | |
| description | VARCHAR(255) | NOT NULL | |
| paid_to | VARCHAR(150) | NULL | |
| payment_method | ENUM('cash','card','gcash','maya','bank','transfer') | NOT NULL | |
| expense_date | DATE | NOT NULL | |
| notes | TEXT | NULL | |
| created_by | INT | NOT NULL, FK→users.id | |
| created_at | DATETIME | DEFAULT NOW() | |
| updated_at | DATETIME | ON UPDATE NOW() | |

---

### `announcements` (add_announcements.sql)

| Column | Type | Constraints |
|---|---|---|
| id | INT | PK, AUTO_INCREMENT |
| message | TEXT | NOT NULL |
| emoji | VARCHAR(10) | NULL |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 |
| sort_order | INT | DEFAULT 0 |
| created_at | DATETIME | DEFAULT NOW() |
| updated_at | DATETIME | ON UPDATE NOW() |

---

### `visitor_logs` (add_visitor_logs.sql)

| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | BIGINT | PK, AUTO_INCREMENT | |
| session_hash | CHAR(64) | NOT NULL | SHA-256 of session_id() |
| ip_hash | CHAR(64) | NOT NULL | SHA-256 of REMOTE_ADDR |
| page | ENUM('catalog','product','home') | NOT NULL | |
| page_id | INT | NULL | Product ID for product pages |
| referrer | VARCHAR(500) | NULL | HTTP_REFERER |
| device | ENUM('desktop','mobile','tablet','bot') | NOT NULL | |
| created_at | DATETIME | DEFAULT NOW() | INDEX |

**Additional indexes:** `page`, `(session_hash, created_at)` for deduplication queries.

---

## Reference Number Formats

| Table | Column | Format | Example |
|---|---|---|---|
| `products` | `sku` | `{CAT}-{TYPE}{NNNN}` | `GFT-G0001` |
| `sales` | `invoice_no` | `INV-{YYYY}-{NNNNN}` | `INV-2026-00037` |
| `purchases` | `reference_no` | `PO-{YYYY}-{NNN}` | `PO-2026-011` |
| `expenses` | `reference_no` | `EXP-{YYYY}-{NNNNN}` | `EXP-2026-00015` |
| `sale_returns` | `return_no` | `RET-{YYYY}-{NNNNN}` | `RET-2026-00003` |

---

## Related Pages

- [Installation & Setup](installation.md) — how to import the schema
- [Core Utilities](utilities.md) — `updateStock()`, reference number generators
