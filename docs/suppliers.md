# Suppliers

**Files:** `suppliers/index.php`, `suppliers/add.php`, `suppliers/edit.php`
**Access:** All logged-in users

Suppliers are the vendors from whom products are purchased. Each purchase order is optionally linked to a supplier.

---

## Supplier List (`suppliers/index.php`)

Paginated list of all supplier records.

### Columns

| Column | Notes |
|---|---|
| Name | Company/vendor name |
| Contact Person | Primary contact |
| Phone | |
| Email | |
| Address | Truncated in list; full in edit form |
| Actions | Edit |

---

## Add Supplier (`suppliers/add.php`)

### Fields

| Field | Required | Notes |
|---|---|---|
| Name | Yes | Company or vendor name |
| Contact Person | No | Primary contact name |
| Phone | No | |
| Email | No | |
| Address | No | Full mailing address |

---

## Edit Supplier (`suppliers/edit.php`)

**URL:** `suppliers/edit.php?id={supplierId}`

All fields are editable. Changes take effect immediately.

> Editing or deleting a supplier does not affect existing purchase orders — the `supplier_id` FK is preserved in the `purchases` table.

---

## Relationship to Purchase Orders

- Supplier selection is **optional** when creating a PO
- If selected, `purchases.supplier_id` stores the link
- Supplier name appears in the PO list and detail view

---

## Seed Data (2 Suppliers)

| Name | Contact | Notes |
|---|---|---|
| Gift World PH | — | Gift items supplier |
| Fashion Hub MNL | — | Clothing supplier |

---

## Related Pages

- [Purchase Orders](purchases.md) — suppliers are referenced in POs
