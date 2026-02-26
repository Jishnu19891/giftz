# Categories

**File:** `categories/index.php`
**Access:** All logged-in users

Categories classify products and are used for filtering in the POS, product list, and public storefront.

---

## Category List (`categories/index.php`)

Displays all categories in a table with inline add/edit actions.

### Columns

| Column | Notes |
|---|---|
| Name | Category display name |
| Type | gift / cloth / both |
| Parent | Name of parent category (or — if top-level) |
| Sort Order | Controls display ordering |
| Actions | Edit, Delete |

---

## Fields

| Field | Required | Notes |
|---|---|---|
| Name | Yes | Unique display name |
| Type | Yes | `gift`, `cloth`, or `both` |
| Parent Category | No | Select a parent to create a sub-category |
| Sort Order | No | Integer; lower = displayed first |

---

## Hierarchy

Categories support one level of parent/child nesting via the `parent_id` column (self-referential foreign key on `categories.id`).

**Example structure:**
```
Clothing (parent_id = NULL)
├── Men's Wear (parent_id = Clothing.id)
└── Women's Wear (parent_id = Clothing.id)
Gift Items (parent_id = NULL)
├── Souvenirs
└── Seasonal Gifts
```

---

## Product Types

| Value | Displayed In |
|---|---|
| `gift` | Gift-type product filter |
| `cloth` | Clothing-type product filter |
| `both` | Both filter contexts |

---

## Seed Data (8 Categories)

| Name | Type | Parent |
|---|---|---|
| Gift Items | gift | — |
| Clothing | cloth | — |
| Souvenirs | gift | Gift Items |
| Accessories | both | — |
| Seasonal Gifts | gift | Gift Items |
| Children's | cloth | Clothing |
| Men's Wear | cloth | Clothing |
| Women's Wear | cloth | Clothing |

---

## Related Pages

- [Products & Inventory](products.md) — products are assigned to categories
- [Public Storefront](storefront.md) — catalog can be filtered by category
