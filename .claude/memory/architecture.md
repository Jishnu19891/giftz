# Architecture Notes

## Request Flow (Admin)
1. index.php → redirect to login or dashboard
2. login.php POST → attemptLogin() → sets $_SESSION → redirect to dashboard.php
3. Every admin page: require config.php (auto session_start) → requireLogin() → requireRole() if needed → logic → render via header.php / footer.php

## Request Flow (POS Checkout)
1. sales/pos.php GET → renders product grid + cart panel
2. POST: validate stock → PDO::beginTransaction → INSERT sales → INSERT sale_items → updateStock('out') per item → COMMIT → redirect to invoice

## Request Flow (Return)
1. sales/return.php GET → loads sale + calculates returnable qty per item
2. POST: validate → beginTransaction → INSERT sale_returns → INSERT sale_return_items → updateStock('in') per item → COMMIT

## Stock Movement Invariant
Every change to products.stock_qty MUST be paired with a stock_movements insert via updateStock(). Never update stock_qty directly with raw SQL outside of updateStock().

## Key updateStock() Signature
```php
updateStock(int $productId, int $qty, string $direction, string $reference, int $userId)
// direction: 'in' | 'out' | 'adjustment'
```

## Visitor Tracking
- trackVisit($page, $pageId=null) called on all public/ pages
- Deduplicates per session_hash+page per hour
- Bots detected by UA and skipped entirely
- SHA-256 hashes for session + IP (no PII stored)

## CSS Architecture
- style.css — global (admin layout, components)
- sidebar.css — nav sidebar only
- pos.css — POS page only
- All loaded via header.php

## JS Architecture
- main.js — toast notifications, confirm dialogs, flash auto-dismiss
- charts.js — GiftzCharts.* factory functions wrapping Chart.js
- pos.js — POS IIFE: cart state, barcode scanner (F2 shortcut, global accumulator fallback)

## Security Gaps (not yet fixed)
- No CSRF tokens on any POST form
- display_errors=1 in config.php (dev mode)
- No login rate limiting / account lockout
- No HTTPS enforcement (localhost only currently)
