# Reports

**Files:** `reports/sales.php`, `reports/profit.php`, `reports/inventory.php`, `reports/stock_movements.php`
**Access:** All logged-in users

Four report pages provide business intelligence across sales performance, profitability, stock valuation, and inventory audit.

---

## Sales Report (`reports/sales.php`)

Analyzes revenue over a selected time period.

### Filters
- **Period grouping:** day / week / month
- **Date range:** custom from/to dates

### KPI Cards
- Total Revenue (period)
- Number of Transactions
- Average Transaction Value
- Top Payment Method

### Charts
- **Revenue Trend** — line chart of revenue per period group
- **Payment Method Breakdown** — doughnut chart (cash, card, GCash, Maya, bank)

### Period Breakdown Table
Columns: Period, Transactions, Revenue, Avg. Order Value

---

## Profit & Loss Report (`reports/profit.php`)

Calculates true business profitability by combining sales revenue, cost of goods sold (COGS), and operating expenses.

### KPI Cards (two rows of three)

**Row 1:**
| Card | Calculation |
|---|---|
| Revenue | SUM of `sales.total` (completed) |
| COGS | SUM of `cost_price × qty` from `sale_items` |
| Gross Profit | Revenue − COGS |

**Row 2:**
| Card | Calculation |
|---|---|
| Operating Expenses | SUM of `expenses.amount` |
| Net Profit | Gross Profit − Operating Expenses |
| Net Margin | Net Profit ÷ Revenue × 100 |

### Chart
- **Stacked Bar Chart** — per period: Revenue (blue), COGS (orange), Expenses (red), Net Profit (teal)
- Rendered by `GiftzCharts.profitBar(el, labels, revenue, cogs, expenses, netProfit)`

### Period Breakdown Table
Columns: Period, Revenue, COGS, Gross Profit, Expenses, Net Profit

Periods with expenses but no sales are included in the table.

### Filters
- **Period grouping:** day / week / month
- **Date range:** custom from/to

---

## Inventory Report (`reports/inventory.php`)

Snapshot of current stock levels and valuations.

### KPI Cards
- Total Active Products
- Total Stock Units (across all products)
- Total Stock Value (qty × cost_price)
- Low Stock Products (at or below min_stock_level)

### Stock Valuation Table
All active products listed with:
- Product name, SKU, category
- Current qty, min level, status (ok / low / out)
- Cost price, selling price
- Stock value (qty × cost_price)
- Potential revenue (qty × selling_price)

### Slow Movers
Products with low recent sales velocity — helpful for identifying items to discount or discontinue.

### Reorder Recommendations
Products where `stock_qty <= min_stock_level`, sorted by stock level ascending.

---

## Stock Movement Log (`reports/stock_movements.php`)

Full audit trail of every inventory change.

### KPI Cards
- Stock In (total units received — type = 'in')
- Stock Out (total units sold/removed — type = 'out')
- Adjustments (total units adjusted)
- Total Entries (count of movement rows)

### Filters

| Filter | Notes |
|---|---|
| Date range | `created_at` from/to |
| Movement type | in / out / adjustment |
| Product | Dropdown of all products |
| Search | Product name, SKU, or reference |

### Columns

| Column | Notes |
|---|---|
| Date/Time | `created_at` |
| Product | Name linked to product edit page |
| SKU | |
| Type | in / out / adjustment badge |
| Quantity | Signed: +N (in), −N (out), ±N (adjustment) |
| Reference | Auto-linked: INV-... → invoice, PO-... → PO, RET-... → return |
| Performed By | User who triggered the movement |

### CSV Export
- **Button:** "Export CSV"
- Honours all active filters
- Downloads a file with the same columns as the table

---

## Reference Linking

The Reference column in the stock log auto-links based on prefix:

| Prefix | Links To |
|---|---|
| `INV-` | `sales/invoice.php?id=...` |
| `PO-` | `purchases/view.php?id=...` |
| `RET-` | `sales/returns.php` (with search) |

---

## Related Pages

- [Sales Management](sales.md) — individual sale records
- [Expenses](expenses.md) — operating costs that feed into P&L
- [Products & Inventory](products.md) — manage stock levels
