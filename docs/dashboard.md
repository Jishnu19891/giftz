# Dashboard

**File:** `dashboard.php`
**Access:** All logged-in users

The dashboard is the main landing page after login. It provides a real-time snapshot of the business with KPI cards, charts, and summary tables.

---

## KPI Cards

Five metric cards are displayed at the top of the page.

| Card | Query Source | Notes |
|---|---|---|
| **Today's Revenue** | `SUM(sales.total)` where `DATE(created_at) = TODAY()` and `status = 'completed'` | Updates on every page load |
| **This Month's Revenue** | `SUM(sales.total)` where `MONTH/YEAR` match current month | |
| **Total Products** | `COUNT(*)` where `status = 'active'` | Sub-label shows low-stock count |
| **Total Customers** | `COUNT(*)` from `customers` | |
| **Today's Visitors** | `COUNT(DISTINCT ip_hash)` from `visitor_logs` where `DATE(created_at) = TODAY()` | Unique visitors only |

---

## Charts

### Sales Trend (7 Days)
- **Type:** Line chart
- **Data:** Daily revenue for the past 7 days
- **Source:** `sales` table, grouped by `DATE(created_at)`
- **Rendered by:** `GiftzCharts.salesTrend(el, labels, data)`

### Category Sales (This Month)
- **Type:** Doughnut chart
- **Data:** Top 8 categories by total sales revenue this month
- **Source:** `sale_items` joined with `products` and `categories`
- **Rendered by:** `GiftzCharts.categoryDoughnut(el, labels, data)`

---

## Summary Tables

### Recent Sales
- Last 8 completed sales
- Columns: Invoice No (linked), Customer, Payment Method, Total, Date
- Click invoice number to view the full invoice

### Low Stock Alerts
- Products where `stock_qty <= min_stock_level`
- Sorted by stock quantity ascending (most critical first)
- Shows up to 6 products; click product name to edit
- A badge in the sidebar navigation also shows the low-stock count

---

## Page Flow

```
User logs in
    → requireLogin() passes
    → dashboard.php runs 5 KPI queries + 2 chart queries + 2 table queries
    → Renders header (sidebar, topbar) + KPI grid + charts + tables
```

---

## Related Pages

- [Products & Inventory](products.md) — manage stock levels
- [Sales Management](sales.md) — full sales history
- [Visitor Analytics](visitors.md) — detailed visitor trends
- [Reports](reports.md) — deeper financial analysis
