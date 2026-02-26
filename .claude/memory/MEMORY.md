# Giftz Project Memory

## Project Identity
- **Name:** Giftz Inventory Management System
- **Repo:** https://github.com/Jishnu19891/giftz
- **Root:** C:\xampp\htdocs\giftz (served at http://localhost/giftz/)
- **Version:** 1.0.0
- **Stack:** PHP 8.2+, MySQL, PDO, Vanilla JS, Chart.js, PHPUnit 11.0
- **Currency:** ₹ INR · **Timezone:** Asia/Kolkata

## Default Credentials
- admin@giftz.local / Admin@123 (role: admin)
- staff@giftz.local / Admin@123 (role: staff)

## Key Files
- `config/config.php` — all constants, session_start(), timezone
- `includes/db.php` — PDO singleton via db()
- `includes/auth.php` — requireLogin(), requireRole(), attemptLogin(), logout()
- `includes/functions.php` — 20+ utilities (formatCurrency, updateStock, paginate, flash, etc.)
- `includes/visitor_tracker.php` — trackVisit() for public storefront analytics
- `includes/header.php` / `footer.php` — shared layout templates
- `database/seed.sql` — all-in-one schema + migrations + sample data

## Database
- **DB name:** giftz_db · **User:** root · **Pass:** (empty)
- **14 tables:** users, categories, suppliers, products, customers, sales, sale_items, purchases, purchase_items, stock_movements + sale_returns, sale_return_items, expense_categories, expenses, announcements, visitor_logs
- Migrations: add_returns.sql, add_expenses.sql, add_announcements.sql, add_visitor_logs.sql

## Modules (with file paths)
| Module | Entry point |
|---|---|
| Dashboard | dashboard.php |
| POS | sales/pos.php |
| Sales | sales/index.php, invoice.php, void.php, return.php, returns.php |
| Purchases | purchases/index.php, add.php, view.php, cancel.php |
| Products | products/index.php, add.php, edit.php, delete.php |
| Categories | categories/index.php |
| Suppliers | suppliers/index.php, add.php, edit.php |
| Customers | customers/index.php, add.php, edit.php, view.php |
| Expenses | expenses/index.php, add.php, edit.php, delete.php |
| Reports | reports/sales.php, profit.php, inventory.php, stock_movements.php |
| Visitors | visitors/index.php, migrate.php |
| Announcements | announcements/index.php, add.php, edit.php, delete.php, toggle.php |
| Users | users/index.php, add.php, profile.php |
| Storefront | public/catalog.php, product.php |

## Reference Number Formats
- SKU: `GFT-G0001` · Invoice: `INV-2026-00001` · PO: `PO-2026-001`
- Expense: `EXP-2026-00001` · Return: `RET-2026-00001`

## Tests
- PHPUnit 11.0 · 95 tests in tests/Unit/
- AuthTest.php (38), FunctionsTest.php (42), VisitorTrackerTest.php (15)
- Run: `composer test`

## Documentation
- **README.md** — full project documentation (976 lines)
- **docs/** — 24 Markdown files: 22 module/topic pages + _Sidebar.md + _Footer.md
  - `_Sidebar.md` — navigation links using standard relative Markdown links
  - `_Footer.md` — version, source link, issue tracker, index link
- **GitHub Wiki** — https://github.com/Jishnu19891/giftz/wiki
  - 22 content pages + _Sidebar.md + _Footer.md (24 files total, mirrors docs/)
  - Wiki repo: https://github.com/Jishnu19891/giftz.wiki.git (clone to /tmp/giftz.wiki to edit)
  - Wiki initialized by user via browser; all subsequent updates pushed via git
  - **Sync rule:** docs/_Sidebar uses `[Label](file.md)` relative links; wiki/_Sidebar uses `[[Page-Name|Label]]` syntax — same structure, different link format; update both when pages are added

## Git / GitHub
- Branch: main · Remote: https://github.com/Jishnu19891/giftz.git
- gh CLI not authenticated — wiki managed by cloning wiki.git directly
- Wiki initialized manually by user; then pushed via git clone of wiki repo

## Patterns & Conventions
- All admin pages call requireLogin() at top; admin-only pages also call requireRole('admin')
- Soft delete for products (status='inactive'), hard delete for expenses
- POST-only handlers for destructive actions (void, cancel, delete)
- PDO transactions used in POS checkout and returns
- Flash messages via flash($type, $msg) / getFlash()
- Output always escaped with e() helper
- See [architecture.md](architecture.md) for deeper notes
