# Public Storefront

**Files:** `public/index.php`, `public/catalog.php`, `public/product.php`
**Access:** No login required — open to all visitors

The public storefront is a customer-facing product browsing interface. It has no checkout or purchasing capability — it is display-only. Visitor traffic is tracked for analytics.

---

## Pages

### Root Redirect (`public/index.php`)
Immediately redirects to `public/catalog.php`.

---

### Product Catalog (`public/catalog.php`)

The main browsing page. Displays all active products in a paginated grid.

#### Announcements Banner
Active announcements from the `announcements` table are shown as a scrolling banner at the top of the page, ordered by `sort_order`.

#### Search & Filters

| Control | Notes |
|---|---|
| Search box | Partial match on product name or SKU |
| Category filter | Dropdown of all categories |
| Sort | Name A-Z, Price (low→high), Price (high→low), Newest |

#### Product Grid
- Displays active products only (`status = 'active'`)
- Each card shows: product image, name, category, price
- Click card → product detail page
- 20 products per page (configurable via `ROWS_PER_PAGE`)

#### Visitor Tracking
```php
trackVisit('catalog');
```
Called once at the top of the page. Logs one entry to `visitor_logs` per unique session per hour.

---

### Product Detail (`public/product.php`)

**URL:** `public/product.php?id={productId}`

Displays full information for a single product.

#### Details Shown
- Product image (full size)
- Name
- Category
- Price (₹)
- Color (if set)
- Size (if set)
- Occasion (if set)
- Stock status (In Stock / Out of Stock)

#### Visitor Tracking
```php
trackVisit('product', $productId);
```
Logs the specific product ID so the analytics dashboard can show "Top Products Viewed".

---

## Storefront vs Admin

| Feature | Storefront | Admin |
|---|---|---|
| Login required | No | Yes |
| Add to cart / purchase | No | Via POS |
| Product management | No | Yes (products/) |
| Announcement management | No | Yes (announcements/) |
| Analytics | No | Yes (visitors/) |

---

## Visitor Tracking Integration

Every public page call logs a visit. The data feeds into:
- **Dashboard KPI:** Today's unique visitors
- **Visitor Analytics dashboard:** trends, device breakdown, top products

See [Visitor Analytics](visitors.md) for full tracking documentation.

---

## URL Structure

| Page | URL |
|---|---|
| Catalog | `http://localhost/giftz/public/catalog.php` |
| Product detail | `http://localhost/giftz/public/product.php?id=5` |

---

## Related Pages

- [Visitor Analytics](visitors.md) — traffic data from storefront visits
- [Announcements](announcements.md) — manage banners shown on catalog
- [Products & Inventory](products.md) — manage which products appear on storefront
