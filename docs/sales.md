# Sales Management

**Files:** `sales/index.php`, `sales/invoice.php`, `sales/void.php`, `sales/return.php`, `sales/returns.php`
**Access:** All logged-in users

---

## Sales List (`sales/index.php`)

Paginated history of all sales transactions.

### Filters

| Filter | Field |
|---|---|
| Date range | `created_at` (from / to) |
| Customer | Partial name match |
| Payment method | cash / card / gcash / maya / bank |
| Status | completed / voided |

### Columns

| Column | Notes |
|---|---|
| Invoice No | Linked to invoice view |
| Customer | Walk-in if no customer assigned |
| Items | Count of line items |
| Subtotal / Discount / Tax / Total | Financial breakdown |
| Payment Method | |
| Cashier | User who created the sale |
| Date | `created_at` |
| Actions | View Invoice, Return (completed only), Void (completed only) |

---

## Invoice (`sales/invoice.php`)

Print-friendly view of a single sale.

**URL:** `sales/invoice.php?id={saleId}`

### Contents
- Shop name and invoice number
- Sale date, cashier name
- Customer name (or Walk-in)
- Itemized table: product name, qty, unit price, line total
- Subtotal, discount (with type), tax, **Total**
- Payment method
- Return button (completed sales only)
- Print button (triggers `window.print()`)

---

## Void a Sale (`sales/void.php`)

Cancels a completed sale and restores all stock.

**Method:** POST only
**Access:** All logged-in users

### Rules
- Only `completed` sales can be voided
- Already `voided` sales are rejected with an error message

### What Happens on Void
1. `sales.status` set to `'voided'`
2. For each `sale_items` row: `updateStock($productId, $qty, 'in', $invoiceNo, $userId)` — stock is restored and a `stock_movements` row is inserted with `type = 'in'`

> Use void for same-day corrections. For after-the-fact returns, use the Return flow instead.

---

## Process a Return (`sales/return.php`)

Handles partial or full returns on a completed sale.

**URL:** `sales/return.php?sale_id={saleId}`
**Method:** GET (form display) / POST (submission)

### Rules
- Only `completed` sales can be returned
- Each item shows the **returnable quantity** = original qty minus already-returned qty
- Submitting 0 for all items is rejected
- Over-returning (qty > returnable) is blocked server-side

### Form Fields
- Per-item quantity inputs (0 to returnable max)
- Reason for return (text)
- Refund total updates live via JavaScript as quantities change

### What Happens on Submit
1. PDO transaction opened
2. `sale_returns` row inserted: `return_no`, `sale_id`, `total_refund`, `reason`
3. `sale_return_items` rows inserted for each returned item (links to original `sale_items` row)
4. `updateStock($productId, $qty, 'in', $returnNo, $userId)` called for each item — stock restored
5. Transaction committed (or rolled back on error)
6. Redirect to the returns list

### Reference Number Format
`RET-YYYY-NNNNN` — e.g. `RET-2026-00003`

---

## Returns List (`sales/returns.php`)

History of all return transactions.

### KPI Cards
- Total Returns (count)
- Total Refunded (₹ sum)
- Average Refund (₹)

### Filters
- Date range
- Search (return number, reason, customer name)

### Columns
- Return No (linked to original invoice)
- Original Invoice No
- Customer
- Items Returned
- Total Refund
- Reason
- Processed By
- Date

---

## Reference Numbers

| Type | Format | Example |
|---|---|---|
| Invoice | `INV-YYYY-NNNNN` | `INV-2026-00037` |
| Return | `RET-YYYY-NNNNN` | `RET-2026-00003` |

---

## Sale Statuses

| Status | Meaning |
|---|---|
| `completed` | Sale finalized; stock deducted |
| `voided` | Sale cancelled; stock restored |

A sale is never deleted — only voided. Returns are stored separately in `sale_returns` and do not change the original sale's status.

---

## Related Pages

- [Point of Sale (POS)](pos.md) — create new sales
- [Reports](reports.md) — aggregate sales reporting
- [Customers](customers.md) — view a customer's full purchase history
