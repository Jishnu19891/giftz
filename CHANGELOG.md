# Giftz Inventory — Changelog

All notable changes to this project are documented here in reverse-chronological order.
Format: `## [YYYY-MM-DD HH:MM] — <summary>`

---

## [2026-02-21] — Add expense tracking feature

**Files changed:**
- `database/add_expenses.sql` *(new — migration)*
- `expenses/index.php` *(new)*
- `expenses/add.php` *(new)*
- `expenses/edit.php` *(new)*
- `expenses/delete.php` *(new)*
- `includes/functions.php` *(modified)*
- `includes/header.php` *(modified)*
- `reports/profit.php` *(modified)*
- `assets/js/charts.js` *(modified)*

**What:**
- Added two new tables via `database/add_expenses.sql`:
  - `expense_categories` — 9 preset categories (Rent & Utilities, Salaries &
    Wages, Office Supplies, Marketing & Ads, Equipment, Maintenance, Delivery &
    Freight, Professional Fees, Miscellaneous) with a `sort_order` field.
  - `expenses` — one row per expense: `reference_no` (EXP-YYYY-NNNNN),
    `category_id`, `amount`, `description`, `paid_to`, `payment_method`
    (cash/card/gcash/maya/bank/transfer), `expense_date`, `notes`, `created_by`.
- `expenses/index.php` — filterable (date range, category, payment method, free
  text), paginated expense list with 4 KPI cards (Period Expenses, Expense
  Count, Top Category, Today's Expenses) and per-row Edit/Delete actions.
- `expenses/add.php` — form with server-side validation; auto-generates the
  reference number and timestamps the record.
- `expenses/edit.php` — pre-filled form; updates all fields except the original
  reference number.
- `expenses/delete.php` — POST-only, admin-only; flashes the reference number on
  success, redirects to the index.
- `generateExpenseNo()` added to `includes/functions.php` — produces sequential
  `EXP-YYYY-NNNNN` identifiers.
- **"💸 Expenses"** nav item added to the Commerce section of the sidebar
  (between Returns and Purchases).
- `reports/profit.php` updated:
  - Queries expenses grouped by the same period format as revenue so they can
    be merged into a unified timeline (periods with only expenses, no sales, are
    included via a post-merge loop).
  - Computes **Gross Profit** (Revenue − COGS), **Net Profit** (Gross Profit −
    Expenses), and **Net Margin** (Net Profit ÷ Revenue × 100).
  - KPI section reorganised into two rows of three cards: Revenue, COGS, Gross
    Profit / Operating Expenses, Net Profit, Net Margin.
  - Period Breakdown table extended with Expenses and Net Profit columns; both
    the table body and footer use the correct renamed variables.
- `assets/js/charts.js` `profitBar()` extended with two optional parameters
  `expensesData` and `netProfitData`; when supplied, Expenses (red) and Net
  Profit (teal) datasets are appended to the chart.

**Why:** The business had no way to record operating costs (rent, salaries, ads,
etc.) and therefore could not calculate true net profit. Revenue minus COGS gives
gross profit, but net profit requires subtracting operating expenses — this feature
closes that gap across both the expense ledger and the profit report.

**Migration required:** Run `database/add_expenses.sql` against `giftz_db` before
using this feature (already applied to the seeded database).

---

## [2026-02-21] — Create database and seed all tables

**Files changed:**
- `database/seed.sql` *(new)*

**What:**
- Created `database/seed.sql` — a single, self-contained file that drops and recreates
  `giftz_db` from scratch, creates every table (core schema + `sale_returns` /
  `sale_return_items`), and inserts a full, realistic dataset:
  - **2 users** — admin@giftz.local and staff@giftz.local (password: `Admin@123`)
  - **8 categories**, **5 suppliers**, **25 products** across all categories
  - **12 customers** (Walk-in + 11 named regulars)
  - **11 purchase orders** — 7 received, 1 partial, 1 pending, 1 cancelled
  - **34 purchase line items**
  - **37 sales** — 35 completed, 1 voided, spread Nov 2025 → Feb 2026
  - **71 sale line items**
  - **105 stock movement rows** — one row per product per PO receipt, sale, void
    restore, and return; every product's `stock_qty` equals its movement net (verified)
  - **3 sale returns** with **3 return line items**
- Imported via `mysql -u root < database/seed.sql` + PHP CLI password update.
- Verified: all 12 tables populated; every `products.stock_qty` == sum of
  `stock_movements` for that product (25/25 match).

**Why:** The application had no data. The seed provides enough history across multiple
months (purchases, sales, returns, a void, a cancelled PO) for every dashboard KPI,
chart, report, and list page to render with meaningful output.

---

## [2026-02-21] — Add barcode scanner support in POS

**Files changed:**
- `sales/pos.php` *(modified)*
- `assets/js/pos.js` *(modified)*
- `assets/css/pos.css` *(modified)*

**What:**
- Added a **"▦ Scan barcode…"** input field to the POS search bar (right of the product
  search box, fixed 200 px wide) for USB/Bluetooth barcode scanner input.
- **F2** keyboard shortcut focuses the barcode field from anywhere on the page.
- **Dedicated input mode**: scanner types the SKU into `#barcodeInput` and sends Enter
  → the field content is processed and cleared immediately.
- **Global accumulator mode** (fallback): when no form element has focus, keystrokes from
  a scanner are accumulated in a buffer. Scanners send characters in rapid bursts
  (< 80 ms between chars); if typing stalls the buffer auto-resets, preventing accidental
  accumulation from slow human typing. Enter flushes the buffer.
- `processBarcode(code)` — looks up the scanned SKU (case-insensitive) against every
  `.pos-product-card[data-sku]`. Calls `card.click()` to trigger `POS.addItem()` on a
  match, shows an error toast if no product is found, and a warning toast if the product
  is out of stock.
- `flashBarcodeInput(type)` — briefly colours the barcode field's border and background
  green (success), red (not found), or amber (out of stock) for 600 ms so cashiers get
  instant visual feedback even without looking at the toast.
- Both `processBarcode` and `flashBarcodeInput` are internal to the `POS` IIFE; no new
  public API surface is exposed.

**Why:** Physical barcode scanners are standard POS hardware. Without this feature,
cashiers had to manually search or tap product cards. With it, scanning any product's
SKU barcode instantly adds it to the cart, speeding up checkout and reducing errors.

---

## [2026-02-21] — Add return/refund feature

**Files changed:**
- `database/add_returns.sql` *(new — migration)*
- `sales/return.php` *(new)*
- `sales/returns.php` *(new)*
- `includes/functions.php` *(modified)*
- `sales/invoice.php` *(modified)*
- `sales/index.php` *(modified)*
- `includes/header.php` *(modified)*

**What:**
- Added two new tables via `database/add_returns.sql`:
  - `sale_returns` — one row per return transaction (return_no, sale_id,
    total_refund, reason, created_by, created_at).
  - `sale_return_items` — one row per returned line item (links back to the
    original `sale_items` row so already-returned quantities can be tracked
    accurately).
- `sales/return.php` — return form + POST handler:
  - Loads the original sale and its items, calculates how many units of each
    item have already been returned (so partial returns work correctly and
    over-returning is blocked).
  - Shows only items that still have a returnable quantity; redirects back to
    the invoice if everything has already been returned.
  - Live JavaScript recalculates the refund total and enables/disables the
    submit button as the user adjusts quantities.
  - On POST: validates quantities, writes `sale_returns` + `sale_return_items`
    rows in a transaction, calls `updateStock()` with type `in` and the return
    number as the reference (so the stock log reflects the restore).
- `sales/returns.php` — returns list with date-range + search filters, 3 KPI
  cards (count, total refunded, average refund), paginated table linking back
  to the original invoices.
- `generateReturnNo()` added to `includes/functions.php` — produces sequential
  `RET-YYYY-NNNNN` identifiers, following the same pattern as invoice and PO
  numbers.
- **"↩ Return"** button added to `sales/invoice.php` (only shown for completed
  sales) and to the actions column in `sales/index.php`.
- **"↩ Returns"** nav item added to the Commerce section of the sidebar, below
  Sales History.

**Why:** There was no way to record or track customer returns. Returned items
were not going back into stock, and there was no refund history to report on.
The `cancelled` vs `voided` sale distinction also meant partial returns (e.g.
returning one item from a three-item sale) were impossible without voiding the
whole transaction.

**Migration required:** Run `database/add_returns.sql` against `giftz_db` before
using this feature.

---

## [2026-02-21] — Add purchase order cancel feature

**Files changed:**
- `purchases/cancel.php` *(new)*
- `purchases/index.php` *(modified)*
- `purchases/view.php` *(modified)*

**What:**
- Created `purchases/cancel.php`, a POST-only handler (mirroring the pattern of
  `sales/void.php`) that sets a pending PO's status to `cancelled`.
- Guard rails: rejects non-POST requests, rejects POs that are not `pending`
  (already received, partial, or cancelled), and returns a clear error message
  with the current status so the user knows why it was blocked.
- Added a **"✕ Cancel"** button in `purchases/index.php` next to the existing
  Receive button — visible only on `pending` rows.
- Added a **"✕ Cancel PO"** button in `purchases/view.php` next to the existing
  Mark Received button — visible only while status is `pending`.
- Both buttons use a `confirm()` dialog before submitting.
- No stock movements are written on cancel because stock is only added when a
  PO is *received*; a pending PO has never touched inventory.

**Why:** There was no way to dismiss a mistakenly created or obsolete purchase
order. The `cancelled` status already existed in the database schema but was
unreachable from the UI.

---

## [2026-02-21 — Session] — Introduce CHANGELOG.md

**What:** Created this file and added retroactive entries for all changes made during
this development session.
**Why:** User requested that every future change be recorded here with a timestamp,
description of what changed, and the reason for the change.

---

## [2026-02-21] — Add user profile page (`users/profile.php`)

**Files changed:**
- `users/profile.php` *(new)*
- `includes/header.php` *(modified)*

**What:**
- Created a self-service profile page accessible to every logged-in user (not
  admin-only).
- **Edit Info tab** — lets the user update their own name and email; syncs the
  session immediately so the topbar/sidebar reflect the new name without
  requiring a re-login.
- **Password tab** — verifies the current password before accepting a new one;
  live match indicator colours the confirm field green/red as the user types.
- **4 KPI cards** — Total Sales processed, Lifetime Revenue generated, Today's
  sales count + revenue, Purchase Orders created.
- **Recent Sales table** — last 8 transactions processed by this user with
  invoice links.
- Added a **"👤 My Profile"** item to the topbar user dropdown.
- Changed the **sidebar footer avatar** link from logout → profile page (logout
  remains in the dropdown).

**Why:** Users had no way to change their own name, email, or password without
admin intervention. The profile page also gives each staff member visibility into
their own performance.

---

## [2026-02-21] — Add customer profile page (`customers/view.php`)

**Files changed:**
- `customers/view.php` *(new)*
- `customers/index.php` *(modified)*

**What:**
- Created a per-customer profile page showing:
  - **4 KPI cards** — Total Orders, Total Spent (lifetime value), Average Order
    Value, Total Saved via discounts.
  - **Profile card** — avatar initial, contact details, joined date.
  - **Top 5 Products** — ranked by amount spent, with units bought.
  - **Spending Trend chart** — 6-month line chart using the existing
    `GiftzCharts.salesTrend()` factory.
  - **Full paginated purchase history** — invoice (linked), item count, payment
    method, cashier, subtotal, discount, total, status, print button.
- Added a **"👤 View"** button in `customers/index.php` next to the existing
  Edit button.

**Why:** The customer list showed aggregate spend but gave no way to drill into a
specific customer's history or understand their purchasing behaviour.

---

## [2026-02-21] — Add stock movement log page (`reports/stock_movements.php`)

**Files changed:**
- `reports/stock_movements.php` *(new)*
- `includes/header.php` *(modified)*

**What:**
- Created a filterable, paginated audit log for all stock movements drawn from
  the existing `stock_movements` table.
- **4 KPI cards** — Stock In units, Stock Out units, Adjustment units, Total
  entries for the selected period.
- **Filters** — date range, movement type (in / out / adjustment), product
  dropdown, free-text search (product name, SKU, reference).
- **Table** — date/time, product name (links to edit page), SKU, type badge,
  signed quantity (+/−/±), reference field auto-linked to the relevant invoice
  or purchase order, user who performed the action.
- **CSV export** — honours all active filters.
- Added a **"📋 Stock Log"** nav item under the Analytics section of the sidebar.

**Why:** The `stock_movements` table had been fully populated by POS sales,
purchase receives, manual edits, and voids since day one, but there was no UI to
browse or audit it.

---
