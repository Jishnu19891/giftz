# Announcements

**Files:** `announcements/index.php`, `announcements/add.php`, `announcements/edit.php`, `announcements/delete.php`, `announcements/toggle.php`
**Access:** Admin only

Announcements are banner messages displayed at the top of the public storefront catalog page. They can be enabled or disabled individually without deleting them.

---

## Announcement List (`announcements/index.php`)

Displays all announcements in a table sorted by `sort_order`.

### Columns

| Column | Notes |
|---|---|
| Sort Order | Controls display position on storefront |
| Emoji | Decorative icon shown before the message |
| Message | Banner text |
| Status | Active (shown on storefront) / Inactive (hidden) |
| Actions | Edit, Toggle, Delete |

---

## Add Announcement (`announcements/add.php`)

### Fields

| Field | Required | Notes |
|---|---|---|
| Message | Yes | Banner text displayed to visitors |
| Emoji | No | Single emoji character (e.g. 🎁, 🛍️, 🎉) |
| Sort Order | No | Integer; lower numbers appear first |
| Active | Yes | Whether to show on storefront immediately |

---

## Edit Announcement (`announcements/edit.php`)

**URL:** `announcements/edit.php?id={id}`

All fields are editable. No restriction on changing active status.

---

## Toggle Active Status (`announcements/toggle.php`)

**Method:** POST only

Flips `is_active` between 0 and 1 for the given announcement. Returns a redirect (or JSON-compatible response for AJAX callers).

Used by the toggle button in the list view — allows fast enable/disable without opening the edit form.

---

## Delete Announcement (`announcements/delete.php`)

**Method:** POST only

Permanently removes the announcement (hard delete).

---

## Display on Storefront

Active announcements (where `is_active = 1`) are fetched and displayed as a banner on `public/catalog.php`, ordered by `sort_order ASC`.

**Example banner output:**
```
🎁 Free gift wrapping on orders above ₹500   🛍️ New arrivals every weekend
```

---

## `announcements` Table

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| message | TEXT | |
| emoji | VARCHAR(10) | |
| is_active | TINYINT(1) | 1 = shown, 0 = hidden |
| sort_order | INT | |
| created_at / updated_at | DATETIME | |

---

## Seed Data (4 Announcements)

Four sample announcements are included in the seed data, with a mix of active and inactive statuses.

---

## Related Pages

- [Public Storefront](storefront.md) — where announcements are displayed
