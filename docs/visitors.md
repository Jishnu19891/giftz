# Visitor Analytics

**Files:** `visitors/index.php`, `visitors/migrate.php`, `includes/visitor_tracker.php`
**Access:** All logged-in users (analytics dashboard)
**Migration:** Run `database/add_visitor_logs.sql` or visit `/visitors/migrate.php`

Tracks customer visits to the public storefront without storing any personally identifiable information (PII). All identifying data is hashed with SHA-256.

---

## How Tracking Works

The `trackVisit()` function is called at the top of each public page:

```php
// public/catalog.php
trackVisit('catalog');

// public/product.php
trackVisit('product', $productId);
```

### What Gets Recorded

| Data | Storage Method |
|---|---|
| Session identity | SHA-256 hash of PHP `session_id()` |
| Visitor IP | SHA-256 hash of `$_SERVER['REMOTE_ADDR']` |
| Page type | `catalog` / `product` / `home` |
| Page ID | Product ID (for product pages only) |
| HTTP Referrer | `$_SERVER['HTTP_REFERER']` (raw, up to 500 chars) |
| Device type | Detected from User-Agent string |
| Timestamp | `created_at` |

### Privacy
- IP addresses and session IDs are **never stored in plaintext**
- SHA-256 hashes are one-way — original values cannot be recovered
- No cookies are set by the tracker

### Bot Detection
Visits from bots are detected by User-Agent and skipped (not logged). Detected patterns:

`bot`, `crawl`, `spider`, `slurp`, `mediapartners`, `facebookexternalhit`, `ia_archiver`

### Device Detection

| Device | Detection |
|---|---|
| `mobile` | UA contains: mobile, android (non-tablet), iphone, ipod, blackberry, windows phone |
| `tablet` | UA contains: tablet, ipad, android (without "mobile") |
| `bot` | UA matches bot patterns |
| `desktop` | Everything else |

### Deduplication
Only one row is written per unique `session_hash + page` combination per hour. Refreshing the catalog page does not create duplicate entries within the same hour.

---

## Analytics Dashboard (`visitors/index.php`)

### KPI Cards

| Card | Calculation |
|---|---|
| **Today's Visitors** | COUNT DISTINCT session_hash where `DATE(created_at) = TODAY()` |
| **This Week** | Unique visitors in the past 7 days |
| **This Month** | Unique visitors in the past 30 days |
| **Total Pageviews** | COUNT(*) for the selected date range |

### Date Range Filter
- Last 7 days
- Last 30 days
- Last 90 days
- All time

### Visitor Trend Chart
- **Type:** Dual-line chart (unique visitors + pageviews per day)
- **Rendered by:** `GiftzCharts.trendLine(el, labels, visitors, pageviews)`

### Device Breakdown
- **Type:** Pie chart
- Segments: desktop / mobile / tablet
- Counts unique sessions per device type

### Page Breakdown Table
| Page | Pageviews | Unique Visitors |
|---|---|---|
| Catalog | COUNT(*) | COUNT DISTINCT session_hash |
| Product | COUNT(*) | COUNT DISTINCT session_hash |
| Home | COUNT(*) | COUNT DISTINCT session_hash |

### Top 10 Products Viewed
Products from `visitor_logs` where `page = 'product'`, ranked by view count.
Linked to `products/edit.php` for quick access.

### Recent Visits
Paginated table (25 per page):
- Device icon (desktop/mobile/tablet)
- Page (catalog / product name)
- Referrer (truncated)
- Timestamp

---

## Migration (`visitors/migrate.php`)

A one-time migration page that checks whether the `visitor_logs` table exists and creates it if not. Can be visited in the browser or run via CLI.

Alternatively, import manually:
```bash
mysql -u root giftz_db < database/add_visitor_logs.sql
```

---

## `visitor_logs` Table

| Column | Type | Notes |
|---|---|---|
| id | BIGINT AUTO_INCREMENT | |
| session_hash | CHAR(64) | SHA-256 of session ID |
| ip_hash | CHAR(64) | SHA-256 of IP address |
| page | ENUM('catalog','product','home') | |
| page_id | INT NULL | Product ID for product pages |
| referrer | VARCHAR(500) | HTTP_REFERER |
| device | ENUM('desktop','mobile','tablet','bot') | |
| created_at | DATETIME | Indexed |

**Indexes:** `created_at`, `page`, `(session_hash, created_at)` (for deduplication)

---

## Related Pages

- [Public Storefront](storefront.md) — pages where tracking occurs
- [Dashboard](dashboard.md) — today's visitor count KPI
