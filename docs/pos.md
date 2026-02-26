# Point of Sale (POS)

**Files:** `sales/pos.php`, `assets/js/pos.js`, `assets/css/pos.css`
**Access:** All logged-in users

The POS is the primary checkout interface. Cashiers use it to build a cart, apply discounts, and complete a sale in a single screen.

---

## Layout

The POS uses a two-column layout:

```
┌──────────────────────────────┬──────────────────────┐
│  Search bar + Barcode input  │                      │
├──────────────────────────────┤    Cart Panel        │
│                              │                      │
│   Product Grid (cards)       │  - Line items        │
│                              │  - Qty adjusters     │
│   [img] Name      ₹Price     │  - Subtotal          │
│   [img] Name      ₹Price     │  - Discount          │
│   ...                        │  - Tax               │
│                              │  - Total             │
│                              │  - Payment method    │
│                              │  - Checkout button   │
└──────────────────────────────┴──────────────────────┘
```

---

## Checkout Workflow

1. **Find a product** — use the search box (filters by name/SKU) or click a category tab
2. **Add to cart** — click a product card, or scan its barcode
3. **Adjust quantities** — use the + / − buttons in the cart panel
4. **Select customer** — search for an existing customer or leave as Walk-in
5. **Apply discount** — enter amount and choose flat (₹) or percentage (%)
6. **Set tax** — enter tax amount in rupees
7. **Choose payment method** — cash, card, GCash, Maya, or bank
8. **Submit** — POST to `sales/pos.php`; on success redirect to invoice

**On POST, the handler:**
- Opens a PDO transaction
- Validates stock availability for each cart item
- Inserts one row into `sales`
- Inserts one row per item into `sale_items`
- Calls `updateStock($productId, $qty, 'out', $invoiceNo, $userId)` for each item
- Commits transaction (or rolls back on any error)
- Redirects to `sales/invoice.php?id={saleId}`

---

## Barcode Scanner

The POS supports USB and Bluetooth barcode scanners without any additional drivers or configuration.

### Setup
No setup required. Plug in a USB barcode scanner — it behaves like a keyboard.

### Usage

| Action | Result |
|---|---|
| Press **F2** | Focuses the barcode input field |
| Scan a barcode (while barcode field is focused) | SKU is typed + Enter sent → product added to cart |
| Scan a barcode (while no field is focused) | Global accumulator captures keystrokes → same result |

### Visual Feedback

After every scan the barcode input field briefly changes color:

| Color | Meaning |
|---|---|
| Green border | Product found and added to cart |
| Red border | SKU not found in active products |
| Amber border | Product found but out of stock |

The color resets after 600 ms.

### How It Works (Technical)

**Dedicated input mode:**
- Scanner sends characters to `#barcodeInput` field, ending with Enter
- `processBarcode(code)` searches all `.pos-product-card[data-sku]` elements for a case-insensitive match
- On match: calls `card.click()` → triggers `POS.addItem()`

**Global accumulator mode (fallback):**
- When no form element has focus, keydown events are captured globally
- Scanner characters arrive in rapid bursts (< 80 ms apart); slow human typing stalls the buffer and resets it
- Enter flushes the accumulated buffer through `processBarcode()`

---

## Cart State (JavaScript)

The cart is managed by the `POS` IIFE in `assets/js/pos.js`. State is held in memory (not localStorage) for the duration of the page session.

| Function | Description |
|---|---|
| `POS.addItem(productId, qty, price)` | Adds item or increments quantity if already in cart |
| `POS.removeItem(productId)` | Removes item from cart |
| `POS.updateQty(productId, qty)` | Sets exact quantity for a cart item |
| `POS.calculateTotal(subtotal, discount, discType, tax)` | Returns computed total |

On checkout, the cart is serialized to hidden form fields and submitted with the payment/customer/discount/tax data.

---

## Payment Methods

| Value | Label |
|---|---|
| `cash` | Cash |
| `card` | Card |
| `gcash` | GCash |
| `maya` | Maya |
| `bank` | Bank Transfer |

---

## Product Card

Each product card displays:
- Product image (or placeholder)
- Product name
- Selling price
- Stock status badge (In Stock / Low Stock / Out of Stock)
- `data-sku` attribute (used by barcode lookup)

Out-of-stock products are visually dimmed and cannot be added to the cart.

---

## Related Pages

- [Sales Management](sales.md) — view, void, and return completed sales
- [Products & Inventory](products.md) — manage products and stock levels
