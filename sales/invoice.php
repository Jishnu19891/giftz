<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();
$id  = intval($_GET['id'] ?? 0);

$saleStmt = $pdo->prepare("
    SELECT s.*,
           COALESCE(c.name,'Walk-in Customer') as customer_name,
           c.phone as customer_phone, c.email as customer_email, c.address as customer_address,
           u.name as cashier_name
    FROM sales s
    LEFT JOIN customers c ON c.id=s.customer_id
    LEFT JOIN users u ON u.id=s.created_by
    WHERE s.id=?
");
$saleStmt->execute([$id]);
$sale = $saleStmt->fetch();

if (!$sale) {
    flash('error', 'Invoice not found.');
    header('Location: ' . BASE_URL . '/sales/index.php');
    exit;
}

$itemsStmt = $pdo->prepare("
    SELECT si.*, p.name as product_name, p.sku
    FROM sale_items si
    LEFT JOIN products p ON p.id=si.product_id
    WHERE si.sale_id=?
    ORDER BY si.id
");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$pageTitle   = 'Invoice ' . $sale['invoice_no'];
$breadcrumbs = ['Sales' => BASE_URL . '/sales/index.php', $sale['invoice_no'] => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header no-print">
    <h1 class="page-title"><span class="title-icon">🖨</span> Invoice</h1>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/sales/index.php" class="btn btn-secondary">← Back</a>
        <?php if ($sale['status'] === 'completed'): ?>
        <a href="<?= BASE_URL ?>/sales/return.php?sale_id=<?= $sale['id'] ?>"
           class="btn btn-warning">↩ Return</a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-primary">🖨 Print</button>
    </div>
</div>

<div class="card invoice-page">
    <div class="card-body" style="padding:2.5rem">

        <!-- Header -->
        <div class="invoice-header">
            <div class="invoice-logo">
                <div class="invoice-logo-icon">🎁</div>
                <div>
                    <div class="invoice-company-name"><?= APP_NAME ?></div>
                    <div class="invoice-company-sub">Gift &amp; Clothing Shop</div>
                </div>
            </div>
            <div class="invoice-meta">
                <div class="invoice-no"><?= e($sale['invoice_no']) ?></div>
                <div class="invoice-date">Date: <?= formatDate($sale['created_at'], 'F d, Y') ?></div>
                <div class="invoice-date">Time: <?= formatDate($sale['created_at'], 'h:i A') ?></div>
                <div style="margin-top:.5rem"><?= statusBadge($sale['status']) ?></div>
            </div>
        </div>

        <!-- Parties -->
        <div class="invoice-parties">
            <div>
                <div class="party-label">Bill To</div>
                <div class="party-name"><?= e($sale['customer_name']) ?></div>
                <div class="party-detail">
                    <?php if ($sale['customer_phone']): ?><?= e($sale['customer_phone']) ?><br><?php endif; ?>
                    <?php if ($sale['customer_email']): ?><?= e($sale['customer_email']) ?><br><?php endif; ?>
                    <?php if ($sale['customer_address']): ?><?= e($sale['customer_address']) ?><?php endif; ?>
                </div>
            </div>
            <div>
                <div class="party-label">Payment Details</div>
                <div class="party-name">Method: <?= e(strtoupper($sale['payment_method'])) ?></div>
                <div class="party-detail">Cashier: <?= e($sale['cashier_name'] ?? '—') ?></div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="invoice-table">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td><?= e($item['product_name'] ?? 'Deleted Product') ?></td>
                    <td class="text-muted fs-sm"><?= e($item['sku'] ?? '—') ?></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-right"><?= formatCurrency($item['unit_price']) ?></td>
                    <td class="text-right fw-bold"><?= formatCurrency($item['total_price']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="invoice-totals">
            <div class="invoice-total-row">
                <span>Subtotal</span><span class="val"><?= formatCurrency($sale['subtotal']) ?></span>
            </div>
            <?php if ($sale['discount'] > 0): ?>
            <div class="invoice-total-row">
                <span>Discount<?= $sale['discount_type']==='percent' ? '' : '' ?></span>
                <span class="val" style="color:var(--danger)">−<?= formatCurrency($sale['discount']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($sale['tax'] > 0): ?>
            <div class="invoice-total-row">
                <span>Tax</span><span class="val"><?= formatCurrency($sale['tax']) ?></span>
            </div>
            <?php endif; ?>
            <div class="invoice-total-row grand-total">
                <span>TOTAL</span><span class="val"><?= formatCurrency($sale['total']) ?></span>
            </div>
        </div>

        <?php if ($sale['notes']): ?>
        <div style="margin-top:2rem;padding:1rem;background:#F9FAFB;border-radius:8px;border-left:4px solid var(--accent)">
            <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:.3rem">Notes</div>
            <div class="fs-sm"><?= e($sale['notes']) ?></div>
        </div>
        <?php endif; ?>

        <div class="invoice-footer">
            <p>Thank you for shopping at <?= APP_NAME ?>! 🎁</p>
            <p style="margin-top:.3rem;font-size:.72rem">This is a computer-generated receipt. No signature required.</p>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print, .topbar, .sidebar, .flash-container { display:none!important; }
    .main-content { margin-left:0!important; }
    .page-body { padding:0; }
    body { background:#fff; }
    .card { box-shadow:none; border:none; }
}
</style>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
