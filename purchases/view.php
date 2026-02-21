<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();
$id  = intval($_GET['id'] ?? 0);

$purStmt = $pdo->prepare("
    SELECT p.*, s.name as supplier_name, s.contact_person, s.phone, s.email
    FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id
    WHERE p.id=?
");
$purStmt->execute([$id]);
$purchase = $purStmt->fetch();

if (!$purchase) {
    flash('error', 'Purchase order not found.');
    header('Location: ' . BASE_URL . '/purchases/index.php');
    exit;
}

$itemsStmt = $pdo->prepare("
    SELECT pi.*, pr.name as product_name, pr.sku
    FROM purchase_items pi LEFT JOIN products pr ON pr.id=pi.product_id
    WHERE pi.purchase_id=?
    ORDER BY pi.id
");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$pageTitle   = $purchase['reference_no'];
$breadcrumbs = ['Purchases' => BASE_URL . '/purchases/index.php', $purchase['reference_no'] => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">📋</span> <?= e($purchase['reference_no']) ?></h1>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/purchases/index.php" class="btn btn-secondary">← Back</a>
        <?php if ($purchase['status'] === 'pending'): ?>
        <form method="POST" action="<?= BASE_URL ?>/purchases/index.php" style="display:inline"
              onsubmit="return confirm('Mark as received? This will update product stock.')">
            <input type="hidden" name="action" value="receive">
            <input type="hidden" name="id" value="<?= $purchase['id'] ?>">
            <button type="submit" class="btn btn-success">✓ Mark Received</button>
        </form>
        <form method="POST" action="<?= BASE_URL ?>/purchases/cancel.php" style="display:inline"
              onsubmit="return confirm('Cancel <?= e($purchase[\'reference_no\']) ?>? This cannot be undone.')">
            <input type="hidden" name="id" value="<?= $purchase['id'] ?>">
            <button type="submit" class="btn btn-danger">✕ Cancel PO</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start">

    <div class="card">
        <div class="card-header"><div class="card-title">Items</div></div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Unit Cost</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php $grandTotal = 0; foreach ($items as $item):
                    $sub = $item['quantity'] * $item['unit_cost'];
                    $grandTotal += $sub;
                ?>
                <tr>
                    <td><?= e($item['product_name'] ?? '—') ?></td>
                    <td class="text-muted fs-sm"><?= e($item['sku'] ?? '') ?></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-right"><?= formatCurrency($item['unit_cost']) ?></td>
                    <td class="text-right fw-bold"><?= formatCurrency($sub) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right fw-bold" style="padding:.85rem 1rem">Grand Total:</td>
                        <td class="text-right fw-bold" style="color:var(--accent);font-size:1.05rem;padding:.85rem 1rem"><?= formatCurrency($grandTotal) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:1rem">
        <div class="card">
            <div class="card-header"><div class="card-title">PO Details</div></div>
            <div class="card-body">
                <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                    <tr><td style="color:var(--text-muted);padding:.35rem 0">Ref No</td><td class="fw-bold"><?= e($purchase['reference_no']) ?></td></tr>
                    <tr><td style="color:var(--text-muted)">Date</td><td><?= formatDate($purchase['purchase_date']) ?></td></tr>
                    <tr><td style="color:var(--text-muted)">Status</td><td><?= statusBadge($purchase['status']) ?></td></tr>
                    <tr><td style="color:var(--text-muted)">Supplier</td><td><?= e($purchase['supplier_name'] ?? '—') ?></td></tr>
                    <?php if ($purchase['contact_person']): ?><tr><td style="color:var(--text-muted)">Contact</td><td><?= e($purchase['contact_person']) ?></td></tr><?php endif; ?>
                    <?php if ($purchase['phone']): ?><tr><td style="color:var(--text-muted)">Phone</td><td><?= e($purchase['phone']) ?></td></tr><?php endif; ?>
                </table>
                <?php if ($purchase['notes']): ?>
                <div style="margin-top:.85rem;padding:.75rem;background:#F9FAFB;border-radius:6px;font-size:.875rem;border-left:3px solid var(--accent)">
                    <?= e($purchase['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
