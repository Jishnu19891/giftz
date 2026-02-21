<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

// Handle checkout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_cart'])) {
    $cartJson  = $_POST['form_cart']        ?? '[]';
    $custId    = intval($_POST['form_customer_id'] ?? 0);
    $subtotal  = floatval($_POST['form_subtotal']  ?? 0);
    $discount  = floatval($_POST['form_discount']  ?? 0);
    $discType  = in_array($_POST['form_disc_type'] ?? '', ['flat','percent']) ? $_POST['form_disc_type'] : 'flat';
    $tax       = floatval($_POST['form_tax']        ?? 0);
    $total     = floatval($_POST['form_total']      ?? 0);
    $payMethod = $_POST['form_payment'] ?? 'cash';
    $notes     = trim($_POST['notes']   ?? '');

    $cart      = json_decode($cartJson, true);

    if (empty($cart)) {
        flash('error', 'Cart is empty.');
        header('Location: ' . BASE_URL . '/sales/pos.php');
        exit;
    }

    $allowed = ['cash','card','gcash','maya','bank'];
    if (!in_array($payMethod, $allowed)) $payMethod = 'cash';

    $invoiceNo = generateInvoiceNo();
    $userId    = currentUser()['id'];
    $cId       = $custId > 0 ? $custId : null;

    try {
        $pdo->beginTransaction();

        $pdo->prepare('
            INSERT INTO sales (invoice_no,customer_id,subtotal,discount,discount_type,tax,total,payment_method,status,notes,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,\'completed\',?,?,NOW())
        ')->execute([$invoiceNo, $cId, $subtotal, $discount, $discType, $tax, $total, $payMethod, $notes ?: null, $userId]);

        $saleId = (int)$pdo->lastInsertId();

        foreach ($cart as $item) {
            $pid   = intval($item['id']    ?? 0);
            $qty   = intval($item['qty']   ?? 0);
            $price = floatval($item['price']?? 0);

            if (!$pid || $qty <= 0) continue;

            // Stock check
            $stockStmt = $pdo->prepare('SELECT stock_qty FROM products WHERE id=?');
            $stockStmt->execute([$pid]);
            $stockQty  = (int)$stockStmt->fetchColumn();

            if ($stockQty < $qty) {
                throw new Exception("Insufficient stock for product ID $pid (available: $stockQty, needed: $qty)");
            }

            $pdo->prepare('INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,total_price) VALUES (?,?,?,?,?)')
                ->execute([$saleId, $pid, $qty, $price, $price * $qty]);

            updateStock($pid, $qty, 'out', $invoiceNo, $userId);
        }

        $pdo->commit();
        flash('success', "Sale $invoiceNo completed! Total: " . formatCurrency($total));
        header('Location: ' . BASE_URL . '/sales/invoice.php?id=' . $saleId);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Sale failed: ' . $e->getMessage());
        header('Location: ' . BASE_URL . '/sales/pos.php');
        exit;
    }
}

// Fetch active products with stock
$products = $pdo->query("
    SELECT p.*, c.name as cat_name, c.id as cat_id
    FROM products p
    LEFT JOIN categories c ON c.id=p.category_id
    WHERE p.status='active'
    ORDER BY p.name ASC
")->fetchAll();

$categories = $pdo->query('SELECT id,name FROM categories ORDER BY sort_order,name')->fetchAll();
$customers  = $pdo->query('SELECT id,name,phone FROM customers ORDER BY name')->fetchAll();

$pageTitle  = 'Point of Sale';
$breadcrumbs = ['POS' => ''];
$bodyClass  = 'pos-page';
$loadPosJs  = true;

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="pos-layout">

    <!-- Left: Products Panel -->
    <div class="pos-products-panel">
        <div class="pos-search-bar">
            <div class="input-icon-wrap" style="flex:1">
                <span class="icon">🔍</span>
                <input type="text" id="posSearch" class="form-control" placeholder="Search products...">
            </div>
            <div class="input-icon-wrap barcode-wrap" title="Scan a barcode here, or press F2 to focus">
                <span class="icon">▦</span>
                <input type="text" id="barcodeInput" class="form-control"
                       placeholder="Scan barcode… (F2)" autocomplete="off" spellcheck="false">
            </div>
        </div>

        <div class="pos-category-tabs">
            <button class="cat-tab active" data-cat-id="0">All</button>
            <?php foreach ($categories as $cat): ?>
            <button class="cat-tab" data-cat-id="<?= $cat['id'] ?>"><?= e($cat['name']) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="pos-grid-container">
            <div class="pos-product-grid">
                <?php foreach ($products as $p): ?>
                <?php $out = $p['stock_qty'] <= 0; ?>
                <div class="pos-product-card <?= $out ? 'out-of-stock' : '' ?>"
                     data-name="<?= e($p['name']) ?>"
                     data-sku="<?= e($p['sku']) ?>"
                     data-cat-id="<?= $p['cat_id'] ?>"
                     <?php if (!$out): ?>
                     onclick="POS.addItem({
                         id:<?= $p['id'] ?>,
                         sku:'<?= e(addslashes($p['sku'])) ?>',
                         name:'<?= e(addslashes($p['name'])) ?>',
                         price:<?= floatval($p['selling_price']) ?>,
                         stock:<?= intval($p['stock_qty']) ?>,
                         image:'<?= $p['image'] ? UPLOAD_URL . '/' . e($p['image']) : '' ?>'
                     })"
                     <?php endif; ?>
                >
                    <?php if ($p['image'] && file_exists(UPLOAD_PATH . '/' . $p['image'])): ?>
                    <img src="<?= UPLOAD_URL ?>/<?= e($p['image']) ?>" alt="" class="pos-product-img" loading="lazy">
                    <?php else: ?>
                    <div class="pos-product-img-placeholder">🎁</div>
                    <?php endif; ?>

                    <?php if ($out): ?><span class="pos-out-badge">OUT</span><?php endif; ?>

                    <div class="pos-product-info">
                        <div class="pos-product-name"><?= e($p['name']) ?></div>
                        <div class="pos-product-price"><?= formatCurrency($p['selling_price']) ?></div>
                        <div class="pos-product-stock">Stock: <?= $p['stock_qty'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right: Cart Panel -->
    <div class="pos-cart-panel">
        <div class="cart-header">
            <div class="cart-title">🛒 Cart <span class="cart-count" id="cartCount">0</span></div>
            <button class="cart-clear-btn" id="clearCartBtn">Clear All</button>
        </div>

        <div class="cart-items" id="cartItems">
            <div id="cartEmpty" class="cart-empty">
                <div class="icon">🛒</div>
                <p>Cart is empty.<br>Tap a product to add it.</p>
            </div>
        </div>

        <!-- Totals -->
        <div class="cart-totals">
            <div class="totals-row">
                <span class="label">Subtotal</span>
                <span class="value" id="subtotalVal"><?= CURRENCY_SYMBOL ?>0.00</span>
            </div>
            <div class="totals-row" style="flex-direction:column;align-items:flex-start;gap:.3rem">
                <span class="label">Discount</span>
                <div class="discount-row" style="width:100%">
                    <div class="discount-type-toggle">
                        <button class="discount-type-btn active" type="button" data-type="flat"><?= CURRENCY_SYMBOL ?></button>
                        <button class="discount-type-btn" type="button" data-type="percent">%</button>
                    </div>
                    <input type="number" id="discountInput" class="form-control" min="0" step="0.01" placeholder="0" style="flex:1;min-width:0">
                    <span id="discountVal" style="color:var(--danger);font-weight:600;white-space:nowrap"><?= CURRENCY_SYMBOL ?>0.00</span>
                </div>
            </div>
            <div class="totals-row total">
                <span class="label">TOTAL</span>
                <span class="value" id="totalVal"><?= CURRENCY_SYMBOL ?>0.00</span>
            </div>
        </div>

        <!-- Customer + Payment -->
        <div class="cart-customer">
            <div class="form-group">
                <label class="form-label">Customer</label>
                <select id="customerSelect" class="form-control">
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?><?= $c['phone'] ? ' — ' . e($c['phone']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-top:.5rem">
                <label class="form-label">Payment Method</label>
                <div class="payment-methods">
                    <button class="pm-btn active" type="button" data-method="cash">💵 Cash</button>
                    <button class="pm-btn" type="button" data-method="card">💳 Card</button>
                    <button class="pm-btn" type="button" data-method="gcash">📱 GCash</button>
                    <button class="pm-btn" type="button" data-method="maya">💜 Maya</button>
                    <button class="pm-btn" type="button" data-method="bank">🏦 Bank</button>
                </div>
            </div>
        </div>

        <!-- Checkout -->
        <div class="cart-checkout">
            <form method="POST" id="checkoutForm">
                <input type="hidden" id="form_cart"        name="form_cart"        value="">
                <input type="hidden" id="form_subtotal"    name="form_subtotal"    value="0">
                <input type="hidden" id="form_discount"    name="form_discount"    value="0">
                <input type="hidden" id="form_disc_type"   name="form_disc_type"   value="flat">
                <input type="hidden" id="form_tax"         name="form_tax"         value="0">
                <input type="hidden" id="form_total"       name="form_total"       value="0">
                <input type="hidden" id="form_payment"     name="form_payment"     value="cash">
                <input type="hidden" id="form_customer_id" name="form_customer_id" value="1">
                <input type="hidden" name="notes" value="">
                <button type="submit" class="btn-checkout" id="checkoutBtn" disabled>
                    ✓ Complete Sale
                </button>
            </form>
        </div>
    </div>

</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
