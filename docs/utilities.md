# Core Utilities

The `includes/` directory contains all shared PHP utilities loaded on every page.

---

## `includes/db.php` — Database Connection

Returns a **PDO singleton**. The connection is created on the first call and reused for all subsequent calls within the same request.

```php
$pdo = db();
```

### PDO Configuration
- Error mode: `PDO::ERRMODE_EXCEPTION` — throws `PDOException` on failure
- Fetch mode: `PDO::FETCH_ASSOC` — returns associative arrays
- Emulated prepares: disabled — uses native prepared statements

### Usage Pattern

```php
$pdo = db();

// Fetch multiple rows
$stmt = $pdo->prepare('SELECT * FROM products WHERE status = ?');
$stmt->execute(['active']);
$products = $stmt->fetchAll();

// Fetch single row
$stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
$stmt->execute([$id]);
$sale = $stmt->fetch();

// Insert with last insert ID
$stmt = $pdo->prepare('INSERT INTO customers (name, phone) VALUES (?, ?)');
$stmt->execute([$name, $phone]);
$newId = $pdo->lastInsertId();
```

---

## `includes/auth.php` — Authentication

### `requireLogin()`
Redirects to `/login.php` with a flash error if no valid session exists. Call at the top of every admin page.

```php
requireLogin();
```

### `requireRole(string $role)`
Redirects back with an error flash if the current user's role doesn't match. Call after `requireLogin()`.

```php
requireRole('admin'); // Only admins can proceed
```

### `isAdmin(): bool`
Returns `true` if `$_SESSION['user_role'] === 'admin'`.

```php
if (isAdmin()) {
    // Show admin-only controls
}
```

### `currentUser(): array`
Returns an array with the current user's session data.

```php
$user = currentUser();
// ['id' => 1, 'name' => 'Admin', 'role' => 'admin', 'email' => 'admin@giftz.local']
```

### `attemptLogin(string $email, string $password): bool`
Validates credentials against the database. On success:
- Sets all `$_SESSION` variables
- Updates `users.last_login`
- Returns `true`

On failure: returns `false`.

### `logout(): void`
- Clears all `$_SESSION` values
- Destroys the session cookie
- Calls `session_destroy()`

---

## `includes/functions.php` — Utility Functions

### Formatting

#### `formatCurrency(float $amount): string`
Formats a number as Indian Rupee with thousands separator.
```php
formatCurrency(1234.5);  // → "₹1,234.50"
formatCurrency(0);       // → "₹0.00"
```

#### `formatDate(string $date, string $format = 'M d, Y'): string`
Parses a datetime string and formats it.
```php
formatDate('2026-02-26');           // → "Feb 26, 2026"
formatDate('2026-02-26', 'd/m/Y'); // → "26/02/2026"
```

#### `formatDateTime(string $datetime): string`
Formats a datetime string as `M d, Y h:i A`.
```php
formatDateTime('2026-02-26 14:30:00'); // → "Feb 26, 2026 02:30 PM"
```

#### `e(string $str): string`
Escapes a string for safe HTML output. Always use this when outputting user-supplied data.
```php
echo e($userInput); // htmlspecialchars with ENT_QUOTES | ENT_SUBSTITUTE, UTF-8
```

#### `statusBadge(string $status): string`
Returns an HTML `<span>` badge with color coding based on status value.
```php
echo statusBadge('completed');  // → <span class="badge badge-success">completed</span>
echo statusBadge('voided');     // → <span class="badge badge-danger">voided</span>
echo statusBadge('pending');    // → <span class="badge badge-warning">pending</span>
```

#### `productEmoji(string $category, string $type): string`
Returns a category-appropriate emoji for display in product lists.
```php
productEmoji('Gift Items', 'gift');  // → "🎁"
productEmoji('Clothing', 'cloth');   // → "👗"
```

---

### Reference Number Generators

All generators query the current year's count and increment it.

#### `generateSKU(string $categoryCode, string $type): string`
```php
generateSKU('GFT', 'G');  // → "GFT-G0001"
```
Format: `{CAT}-{TYPE}{NNNN}`

#### `generateInvoiceNo(): string`
```php
generateInvoiceNo();  // → "INV-2026-00038"
```
Format: `INV-{YYYY}-{NNNNN}`

#### `generatePoRef(): string`
```php
generatePoRef();  // → "PO-2026-012"
```
Format: `PO-{YYYY}-{NNN}`

#### `generateExpenseNo(): string`
```php
generateExpenseNo();  // → "EXP-2026-00016"
```
Format: `EXP-{YYYY}-{NNNNN}`

#### `generateReturnNo(): string`
```php
generateReturnNo();  // → "RET-2026-00004"
```
Format: `RET-{YYYY}-{NNNNN}`

---

### Stock Management

#### `updateStock(int $productId, int $qty, string $direction, string $reference, int $userId): void`

Updates `products.stock_qty` and inserts a row into `stock_movements`.

| Parameter | Type | Notes |
|---|---|---|
| `$productId` | int | Product to update |
| `$qty` | int | Units to move (always positive) |
| `$direction` | string | `'in'`, `'out'`, or `'adjustment'` |
| `$reference` | string | Invoice No, PO Ref, Return No, or free text |
| `$userId` | int | User performing the action |

```php
// Deduct stock on sale
updateStock($productId, 2, 'out', 'INV-2026-00037', $userId);

// Restore stock on return
updateStock($productId, 1, 'in', 'RET-2026-00003', $userId);

// Add stock on PO receive
updateStock($productId, 10, 'in', 'PO-2026-011', $userId);
```

---

### Pagination

#### `paginate(int $total, int $page, int $perPage = ROWS_PER_PAGE): array`

Calculates pagination values.

```php
$p = paginate(150, 2, 20);
// [
//   'total'    => 150,
//   'pages'    => 8,
//   'current'  => 2,
//   'offset'   => 20,
//   'per_page' => 20,
//   'has_prev' => true,
//   'has_next' => true,
// ]
```

#### `paginationLinks(array $p, string $baseUrl): string`

Returns an HTML `<nav>` element with page number links.

```php
echo paginationLinks($p, 'products/index.php?category=1');
```

---

### Flash Messages

#### `flash(string $type, string $message): void`

Stores a flash message in the session to be displayed on the next page load.

```php
flash('success', 'Product saved successfully.');
flash('error', 'Stock not available.');
flash('warning', 'Low stock detected.');
```

Types: `success`, `error`, `warning`, `info`

#### `getFlash(): array`

Returns and clears all flash messages from the session.

```php
$messages = getFlash();
foreach ($messages as $msg) {
    echo '<div class="alert alert-' . $msg['type'] . '">' . e($msg['message']) . '</div>';
}
```

---

### Image Upload

#### `uploadImage(array $file, string $dir): string|false`

Validates and moves an uploaded file to the specified directory.

```php
$filename = uploadImage($_FILES['image'], UPLOAD_PATH);
if ($filename) {
    // Save $filename to database
}
```

- Validates MIME type: `image/jpeg`, `image/png`, `image/gif`, `image/webp`
- Returns the filename on success, `false` on failure
- Does not resize or compress images

---

### Utilities

#### `getLowStockCount(): int`

Returns the count of products where `stock_qty <= min_stock_level`.

```php
$count = getLowStockCount(); // Used in sidebar badge and dashboard KPI
```

---

## `includes/visitor_tracker.php` — Visit Logging

### `trackVisit(string $page, int|null $pageId = null): void`

Logs a visit to the `visitor_logs` table. Call once at the top of each public page.

```php
trackVisit('catalog');           // Catalog page
trackVisit('product', $id);      // Product detail page
trackVisit('home');              // Home/index page
```

- Skips logging if the visitor is a bot
- Deduplicates: only one row per `session_hash + page` per hour
- Hashes session ID and IP address (SHA-256) for privacy
- Silent fail: no exception thrown if the DB is unavailable

See [Visitor Analytics](visitors.md) for full documentation.

---

## `includes/header.php` — Layout Template

Outputs the full HTML header, including:
- `<head>` with CSS links
- Sidebar navigation (with active state detection)
- Topbar with user dropdown
- Flash message display area
- Opening `<main>` tag

**Sidebar sections:**
- Main: Dashboard, POS
- Inventory: Products (+ low-stock badge), Categories
- Commerce: Sales, Returns, Expenses, Purchases, Customers, Suppliers
- Analytics: Reports, Stock Log, Visitor Analytics
- Admin: Users, Announcements

## `includes/footer.php` — Layout Template

Closes the `<main>` tag and outputs:
- JS script tags (main.js, charts.js)
- Closing `</body></html>`
