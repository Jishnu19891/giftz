# Frontend Assets

**Directory:** `assets/`

All static frontend assets are in `assets/css/`, `assets/js/`, and `assets/uploads/`.

---

## CSS Files

### `assets/css/style.css` (~650 lines)

Global stylesheet applied to all admin pages.

**Includes:**
- CSS reset and base typography
- Button styles (`.btn`, `.btn-primary`, `.btn-danger`, `.btn-secondary`, etc.)
- Form element styles (inputs, selects, textareas, labels, `.input-icon-wrap`)
- Card component (`.card`, `.card-header`, `.card-body`)
- KPI grid (`.kpi-grid`, `.kpi-card`)
- Badge/pill styles (`.badge`, `.badge-success`, `.badge-danger`, `.badge-warning`, etc.)
- Table styles (`.table`, `.table-striped`, `.table-hover`)
- Pagination nav (`.pagination`)
- Flash/alert messages (`.alert`, auto-dismiss after 5 seconds via JS)
- Modal dialogs
- Responsive breakpoints (mobile-first, flexbox and CSS grid)

**Color scheme:**
- Primary: `#6C63FF` (purple)
- Accent: `#FF6584` (pink)
- Success: teal/green
- Danger: red
- Warning: amber

---

### `assets/css/sidebar.css` (~360 lines)

Styles specific to the sidebar navigation layout.

**Includes:**
- Sidebar container and positioning (sticky, full-height)
- Brand/logo area
- Navigation sections and items
- Active state highlighting (`.active` class on current page item)
- Badges on nav items (low-stock count)
- Mobile hamburger button
- Responsive collapse behavior
- User avatar in sidebar footer

---

### `assets/css/pos.css` (~530 lines)

Styles specific to the POS interface (`sales/pos.php`).

**Includes:**
- Two-column POS layout (product grid + cart panel)
- Product card grid (responsive: 2 cols mobile, 3 cols desktop)
- Product card: image, name, price, stock badge
- Cart panel: sticky, scrollable item list, fixed footer with totals
- Cart item row: product name, qty adjusters (+/−), remove button, line total
- Search bar and barcode input field (fixed 200px width)
- Category filter tabs
- Checkout form: customer search, discount, tax, payment method selector
- Out-of-stock card dimming

---

## JavaScript Files

### `assets/js/main.js` (~140 lines)

Global utilities loaded on all admin pages.

#### Toast Notifications

```javascript
showToast('Product saved!', 'success');
showToast('Stock not available.', 'error');
showToast('Low stock detected.', 'warning');
showToast('Note: duplicate found.', 'info');
```

Types: `success`, `error`, `warning`, `info`

Toasts appear in the bottom-right corner and auto-dismiss after 4 seconds.

#### Confirmation Dialogs

```javascript
confirmAction('Are you sure you want to delete this?', function() {
    // Executes only if user clicks OK
    form.submit();
});
```

#### DOM Helpers
- Auto-dismiss flash alerts after 5 seconds
- Active nav item detection
- General DOM utility functions

---

### `assets/js/charts.js` (~300 lines)

Chart.js factory functions. Each function creates and returns a Chart.js instance attached to a canvas element.

#### `GiftzCharts.salesTrend(el, labels, data)`
- **Type:** Line chart
- **Use:** Dashboard 7-day revenue trend, customer spending trend
- `el`: Canvas element or ID string
- `labels`: Array of date strings
- `data`: Array of revenue values

#### `GiftzCharts.categoryDoughnut(el, labels, data)`
- **Type:** Doughnut chart
- **Use:** Dashboard category sales breakdown
- `labels`: Category names
- `data`: Revenue per category

#### `GiftzCharts.profitBar(el, labels, revenue, cogs, expenses?, netProfit?)`
- **Type:** Grouped/stacked bar chart
- **Use:** Profit & Loss report
- `revenue`: Array of revenue per period
- `cogs`: Array of COGS per period
- `expenses` (optional): Array of operating expenses per period
- `netProfit` (optional): Array of net profit per period
- When expenses/netProfit are provided, two additional datasets (red, teal) are appended

#### `GiftzCharts.inventoryStacked(el, labels, valued, cost)`
- **Type:** Horizontal bar chart
- **Use:** Inventory valuation report
- `valued`: Stock value at selling price
- `cost`: Stock value at cost price

#### `GiftzCharts.stockMovement(el, labels, inData, outData, adjData)`
- **Type:** Grouped bar chart
- **Use:** Stock movement report
- Three datasets: In (green), Out (red), Adjustment (amber)

#### `GiftzCharts.trendLine(el, labels, visitors, pageviews)`
- **Type:** Dual-series line chart
- **Use:** Visitor analytics dashboard
- `visitors`: Unique visitors per day
- `pageviews`: Total pageviews per day

---

### `assets/js/pos.js` (~375 lines)

POS cart logic and barcode scanner, wrapped in an IIFE (`POS` object).

#### Cart API

| Method | Description |
|---|---|
| `POS.addItem(productId, qty, price)` | Adds item or increments quantity if already in cart |
| `POS.removeItem(productId)` | Removes item from cart entirely |
| `POS.updateQty(productId, qty)` | Sets exact quantity; removes item if qty reaches 0 |
| `POS.calculateTotal(subtotal, discount, discType, tax)` | Returns computed final total |

Cart state is held in a JavaScript array in memory (not persisted to localStorage). Navigating away from the POS page clears the cart.

#### Barcode Scanner Functions (internal)

| Function | Description |
|---|---|
| `processBarcode(code)` | Looks up SKU in product cards, triggers click on match |
| `flashBarcodeInput(type)` | Colors barcode field green/red/amber for 600 ms |

#### Checkout Submission
On form submit, the cart array is serialized to hidden fields and POSTed to `sales/pos.php`.

---

## Uploads Directory

**Path:** `assets/uploads/products/`

Stores product images uploaded via the Add/Edit Product forms.

- Files are named by the `uploadImage()` function (timestamp + random suffix)
- Referenced in `products.image` column as filename only (not full path)
- Full URL constructed as `UPLOAD_URL . '/' . $product['image']`
- Seed data includes 25 sample images: `img_prod_001.jpg` through `img_prod_025.jpg`

---

## External Dependencies (CDN)

| Library | Version | Used For |
|---|---|---|
| Chart.js | Latest stable | All charts in reports and dashboard |

No other frontend frameworks or libraries are used. No npm, no bundler.

---

## Related Pages

- [Dashboard](dashboard.md) — uses salesTrend and categoryDoughnut charts
- [Reports](reports.md) — uses profitBar, inventoryStacked, stockMovement, trendLine charts
- [Point of Sale (POS)](pos.md) — uses pos.js for cart and barcode logic
