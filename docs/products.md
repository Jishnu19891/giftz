# Products & Inventory

**Files:** `products/index.php`, `products/add.php`, `products/edit.php`, `products/delete.php`
**Access:** All logged-in users (delete: admin only)

---

## Product List (`products/index.php`)

Paginated list of all products.

### Filters

| Filter | Notes |
|---|---|
| Search | Matches name or SKU (partial) |
| Category | Dropdown of all categories |
| Type | gift / cloth / both |
| Status | active / inactive |

### Columns

| Column | Notes |
|---|---|
| Image | Thumbnail |
| SKU | Auto-generated unique code |
| Name | |
| Category | |
| Type | |
| Cost Price | Purchase cost (₹) |
| Selling Price | |
| Stock | Color-coded: green (ok), amber (low), red (out) |
| Status | active / inactive badge |
| Actions | Edit, Delete |

### Low Stock Indicator
Products where `stock_qty <= min_stock_level` show an amber badge. The sidebar also shows a count badge on the Products nav item.

---

## Add Product (`products/add.php`)

### Fields

| Field | Required | Notes |
|---|---|---|
| Name | Yes | |
| Category | Yes | Select from dropdown |
| Type | Yes | gift / cloth / both |
| Cost Price | Yes | Purchase cost in ₹ |
| Selling Price | Yes | Must be ≥ cost price recommended |
| Stock Quantity | Yes | Opening stock |
| Min Stock Level | Yes | Triggers low-stock alert |
| Size | No | Optional attribute |
| Color | No | Optional attribute |
| Occasion | No | e.g. Birthday, Wedding |
| Image | No | JPEG, PNG, GIF, or WebP; stored in `assets/uploads/products/` |
| Status | Yes | active / inactive |

### SKU Auto-Generation
SKU is generated automatically on save using `generateSKU($categoryCode, $type)`.

**Format:** `{CAT}-{TYPE}{NNNN}`
- Category code: first 3 letters of category name (uppercase)
- Type code: G (gift), C (cloth), B (both)
- Sequence: 4-digit zero-padded number

**Example:** `GFT-G0001` for the first gift item in the Gift category.

### Image Upload
- Validates MIME type server-side (not just file extension)
- Accepted: `image/jpeg`, `image/png`, `image/gif`, `image/webp`
- Stored in `assets/uploads/products/`
- Filename returned by `uploadImage($file, $dir)` and saved to `products.image`

---

## Edit Product (`products/edit.php`)

**URL:** `products/edit.php?id={productId}`

Same fields as Add, with these differences:
- **SKU is not editable** — displayed read-only
- All other fields (including prices and stock qty) are editable
- Image can be replaced; old image is overwritten

> Changing `stock_qty` directly via edit does **not** log a stock movement. Use purchase orders or manual adjustments for auditable stock changes.

---

## Delete Product (`products/delete.php`)

**Method:** POST only
**Access:** Admin only

**Soft delete** — sets `status = 'inactive'` rather than removing the row. This preserves:
- Historical sales data (sale_items references product_id)
- Stock movement audit trail
- Purchase order history

Inactive products do not appear in the POS product grid or the public catalog.

---

## Stock Management

Stock is tracked in `products.stock_qty`. Every change is also recorded in `stock_movements` for auditability.

### updateStock()

```php
updateStock($productId, $qty, $direction, $reference, $userId)
```

| Parameter | Type | Description |
|---|---|---|
| `$productId` | int | Product to update |
| `$qty` | int | Units to move (always positive) |
| `$direction` | string | `'in'`, `'out'`, or `'adjustment'` |
| `$reference` | string | Invoice No, PO Ref, Return No, etc. |
| `$userId` | int | User performing the action |

**Effect:**
- `'in'` → `stock_qty += qty`
- `'out'` → `stock_qty -= qty`
- `'adjustment'` → `stock_qty` set to qty (or delta depending on implementation)

Every call inserts one row into `stock_movements`.

### Automatic Stock Events

| Event | Direction | Reference |
|---|---|---|
| POS sale | out | Invoice No (INV-...) |
| Sale void | in | Invoice No (INV-...) |
| Sale return | in | Return No (RET-...) |
| Purchase receive | in | PO Reference (PO-...) |

---

## Product Types

| Type | Meaning |
|---|---|
| `gift` | Gift item only |
| `cloth` | Clothing item only |
| `both` | Suitable for both categories |

---

## Related Pages

- [Categories](categories.md) — manage product categories
- [Point of Sale (POS)](pos.md) — sell products
- [Purchase Orders](purchases.md) — restock products
- [Reports → Inventory](reports.md#inventory-report) — stock valuation and slow movers
- [Reports → Stock Log](reports.md#stock-movement-log) — movement audit trail
