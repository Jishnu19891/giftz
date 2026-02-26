# Customers

**Files:** `customers/index.php`, `customers/add.php`, `customers/edit.php`, `customers/view.php`
**Access:** All logged-in users

Customers are optionally linked to sales. A special "Walk-in" customer (id = 1) is used for anonymous transactions.

---

## Customer List (`customers/index.php`)

Paginated list of all customers.

### Filters
- Search: partial match on name, phone, or email

### Columns

| Column | Notes |
|---|---|
| Name | Linked to customer profile |
| Phone | |
| Email | |
| Total Spent | Lifetime value — sum of all completed sales |
| Actions | View Profile, Edit |

---

## Add Customer (`customers/add.php`)

### Fields

| Field | Required |
|---|---|
| Name | Yes |
| Phone | No |
| Email | No |
| Address | No |

---

## Edit Customer (`customers/edit.php`)

**URL:** `customers/edit.php?id={customerId}`

All fields are editable. Changes take effect immediately and are reflected in all historical sale records that reference this customer.

---

## Customer Profile (`customers/view.php`)

**URL:** `customers/view.php?id={customerId}`

A comprehensive view of a single customer's activity.

### KPI Cards

| Card | Calculation |
|---|---|
| **Total Orders** | COUNT of completed sales linked to this customer |
| **Total Spent** | SUM of `sales.total` for completed sales |
| **Average Order Value** | Total Spent ÷ Total Orders |
| **Total Saved** | SUM of `sales.discount` across all sales |

### Profile Card
- Avatar initial (first letter of name)
- Contact details (phone, email, address)
- Member since (account created date)

### Top 5 Products
- Products this customer bought most by total spend
- Shown with units purchased and amount spent

### Spending Trend
- 6-month line chart of monthly spend
- Rendered by `GiftzCharts.salesTrend()`

### Purchase History
- Paginated table of all sales
- Columns: Invoice No (linked), Date, Items, Payment Method, Cashier, Subtotal, Discount, Total, Status, Print
- 20 rows per page

---

## Walk-in Customer

Sales without a selected customer are assigned to the default "Walk-in" customer (id = 1 in seed data). This preserves referential integrity while allowing anonymous transactions.

---

## Seed Data (12 Customers)

| # | Type |
|---|---|
| 1 | Walk-in (default) |
| 2–12 | Named regular customers |

---

## Related Pages

- [Sales Management](sales.md) — view sales linked to customers
- [Point of Sale (POS)](pos.md) — assign a customer during checkout
