# Purchase Orders

**Files:** `purchases/index.php`, `purchases/add.php`, `purchases/view.php`, `purchases/cancel.php`
**Access:** All logged-in users

Purchase orders (POs) track inventory procurement from suppliers. Stock is added to the system only when a PO is received, not when it is created.

---

## Purchase Order List (`purchases/index.php`)

Paginated list of all purchase orders.

### Filters

| Filter | Options |
|---|---|
| Status | pending / received / partial / cancelled |
| Date range | `purchase_date` |
| Search | Reference number, supplier name |

### Columns

| Column | Notes |
|---|---|
| Reference No | PO-YYYY-NNN format; linked to detail view |
| Supplier | |
| Purchase Date | |
| Total Amount | |
| Status | Color-coded badge |
| Actions | View, Cancel (pending only) |

---

## Create a Purchase Order (`purchases/add.php`)

### Fields

| Field | Notes |
|---|---|
| Supplier | Select from dropdown (optional) |
| Purchase Date | Defaults to today |
| Notes | Free text |
| Line Items | Dynamic rows: product, quantity, unit cost |

### Line Items
- Click **"Add Item"** to add a product row
- Each row: product dropdown, quantity, unit cost per unit, line total (calculated)
- At least one item is required

### On Submit
- Auto-generates `PO-YYYY-NNN` reference number via `generatePoRef()`
- Inserts one row into `purchases`
- Inserts one row per line item into `purchase_items`
- Status is set to `pending`

### Reference Number Format
`PO-YYYY-NNN` — e.g. `PO-2026-011`

---

## View & Receive a Purchase Order (`purchases/view.php`)

**URL:** `purchases/view.php?id={poId}`

### Details Shown
- Supplier, reference number, purchase date, status, notes
- Line items table: product, ordered qty, unit cost, line total
- PO total

### Receiving Items
Available when status is `pending` or `partial`.

1. Click **"Mark as Received"**
2. A modal shows each line item with an input for received quantity
3. Submit the modal form

**On receive POST:**
- For each item: `updateStock($productId, $qty, 'in', $poRef, $userId)` — adds to `products.stock_qty` and logs to `stock_movements`
- PO status updated:
  - All items fully received → `received`
  - Some items received → `partial`

### Cancel PO
Available only when status is `pending`. See [Cancel](#cancel-a-purchase-order-purchasescancelphp).

---

## Cancel a Purchase Order (`purchases/cancel.php`)

**Method:** POST only

### Rules
- Only `pending` POs can be cancelled
- POs with status `received`, `partial`, or `cancelled` are rejected with a clear error

### What Happens
- `purchases.status` set to `'cancelled'`
- **No stock movement is created** — stock is only affected when items are received, not when a PO is created or cancelled

### Guard
Both `purchases/index.php` (list) and `purchases/view.php` (detail) show the Cancel button only for `pending` POs. A `confirm()` dialog is shown before submission.

---

## PO Statuses

| Status | Meaning |
|---|---|
| `pending` | Created, no items received yet |
| `partial` | Some items received |
| `received` | All items received; stock updated |
| `cancelled` | PO was cancelled before any items were received |

---

## Stock Impact

| Action | Stock Effect |
|---|---|
| Create PO | None |
| Receive items | `stock_qty += received_qty`; movement logged as `type = 'in'` |
| Cancel PO | None |

---

## Related Pages

- [Suppliers](suppliers.md) — manage supplier records
- [Products & Inventory](products.md) — view current stock levels
- [Reports → Stock Log](reports.md#stock-movement-log) — audit trail of all stock movements
