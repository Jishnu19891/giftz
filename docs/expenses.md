# Expenses

**Files:** `expenses/index.php`, `expenses/add.php`, `expenses/edit.php`, `expenses/delete.php`
**Access:** All logged-in users (delete: admin only)
**Migration:** Run `database/add_expenses.sql` before using this module

Tracks operating costs (rent, salaries, supplies, etc.) so that net profit can be calculated accurately in the Profit & Loss report.

---

## Expense Ledger (`expenses/index.php`)

### KPI Cards

| Card | Calculation |
|---|---|
| **Period Expenses** | SUM of amounts for the selected date range |
| **Expense Count** | COUNT of expense records in the period |
| **Top Category** | Category with the highest total spend this period |
| **Today's Expenses** | SUM of amounts where `expense_date = TODAY()` |

### Filters

| Filter | Notes |
|---|---|
| Date range | `from` and `to` on `expense_date` |
| Category | Select from 9 presets |
| Payment method | cash / card / gcash / maya / bank / transfer |
| Search | Partial match on description, paid_to, or reference_no |

### Columns

| Column | Notes |
|---|---|
| Reference No | EXP-YYYY-NNNNN |
| Date | `expense_date` |
| Category | Preset category name |
| Description | Brief description |
| Paid To | Payee name |
| Amount | ₹ amount |
| Payment Method | |
| Added By | User who created the record |
| Actions | Edit, Delete (admin only) |

---

## Add Expense (`expenses/add.php`)

### Fields

| Field | Required | Notes |
|---|---|---|
| Date | Yes | Defaults to today |
| Category | Yes | Select from 9 presets |
| Description | Yes | Brief description of the expense |
| Amount | Yes | In ₹ |
| Paid To | No | Payee or vendor name |
| Payment Method | Yes | cash / card / gcash / maya / bank / transfer |
| Notes | No | Additional details |

Reference number (`EXP-YYYY-NNNNN`) is auto-generated on save via `generateExpenseNo()`.

---

## Edit Expense (`expenses/edit.php`)

**URL:** `expenses/edit.php?id={expenseId}`

All fields are editable except the reference number, which is immutable.

---

## Delete Expense (`expenses/delete.php`)

**Method:** POST only
**Access:** Admin only

Permanently removes the expense record (hard delete). The reference number is shown in the success flash message for audit purposes.

---

## Expense Categories (9 Presets)

| # | Category |
|---|---|
| 1 | Rent & Utilities |
| 2 | Salaries & Wages |
| 3 | Office Supplies |
| 4 | Marketing & Ads |
| 5 | Equipment |
| 6 | Maintenance |
| 7 | Delivery & Freight |
| 8 | Professional Fees |
| 9 | Miscellaneous |

---

## Payment Methods

| Value | Label |
|---|---|
| `cash` | Cash |
| `card` | Card |
| `gcash` | GCash |
| `maya` | Maya |
| `bank` | Bank Transfer |
| `transfer` | Wire Transfer |

---

## Reference Number Format

`EXP-YYYY-NNNNN` — e.g. `EXP-2026-00015`

---

## Integration with Profit Report

Expenses feed directly into the Profit & Loss report:

```
Revenue
  − COGS (cost of goods sold)
  = Gross Profit
  − Operating Expenses (from expenses table)
  = Net Profit
```

The profit report groups expenses by the same period format as revenue, so periods with expenses but no sales are included in the breakdown.

---

## Related Pages

- [Reports → Profit & Loss](reports.md#profit--loss-report) — net profit calculation
- [Dashboard](dashboard.md) — monthly revenue overview
