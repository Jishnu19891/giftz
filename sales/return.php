<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo    = db();
$errors = [];
$saleId = intval($_GET['sale_id'] ?? $_POST['sale_id'] ?? 0);

// ── Load sale ────────────────────────────────────────────────
$saleStmt = $pdo->prepare("
    SELECT s.*, COALESCE(c.name, 'Walk-in') AS customer_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    WHERE s.id = ?
");
$saleStmt->execute([$saleId]);
$sale = $saleStmt->fetch();

if (!$sale) {
    flash('error', 'Sale not found.');
    header('Location: ' . BASE_URL . '/sales/index.php');
    exit;
}

if ($sale['status'] !== 'completed') {
    flash('error', 'Only completed sales can have returns processed.');
    header('Location: ' . BASE_URL . '/sales/invoice.php?id=' . $saleId);
    exit;
}

// ── Load items with already-returned quantities ──────────────
$itemsStmt = $pdo->prepare("
    SELECT si.id          AS sale_item_id,
           si.quantity    AS orig_qty,
           si.unit_price,
           p.id           AS product_id,
           p.name         AS product_name,
           p.sku,
           COALESCE(SUM(sri.quantity), 0) AS returned_qty
    FROM sale_items si
    LEFT JOIN products p             ON p.id  = si.product_id
    LEFT JOIN sale_return_items sri  ON sri.sale_item_id = si.id
    WHERE si.sale_id = ?
    GROUP BY si.id
    ORDER BY si.id ASC
");
$itemsStmt->execute([$saleId]);
$items = $itemsStmt->fetchAll();

// Only items that still have stock to return
$returnable = array_filter($items, fn($i) => ($i['orig_qty'] - $i['returned_qty']) > 0);

if (empty($returnable)) {
    flash('info', 'All items in this sale have already been returned.');
    header('Location: ' . BASE_URL . '/sales/invoice.php?id=' . $saleId);
    exit;
}

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason     = trim($_POST['reason'] ?? '');
    $returnQtys = $_POST['return_qty'] ?? [];   // [sale_item_id => qty]

    $toReturn    = [];
    $totalRefund = 0.0;

    foreach ($returnable as $item) {
        $siId   = $item['sale_item_id'];
        $qty    = intval($returnQtys[$siId] ?? 0);
        $maxQty = $item['orig_qty'] - $item['returned_qty'];

        if ($qty <= 0) continue;

        if ($qty > $maxQty) {
            $errors[] = '"' . $item['product_name'] . '" — return qty (' . $qty
                      . ') exceeds returnable qty (' . $maxQty . ').';
            continue;
        }

        $lineTotal  = round($qty * $item['unit_price'], 2);
        $toReturn[] = [
            'sale_item_id' => $siId,
            'product_id'   => $item['product_id'],
            'quantity'     => $qty,
            'unit_price'   => $item['unit_price'],
            'total_price'  => $lineTotal,
        ];
        $totalRefund += $lineTotal;
    }

    if (empty($toReturn) && empty($errors)) {
        $errors[] = 'Enter a return quantity for at least one item.';
    }

    if (empty($errors)) {
        $returnNo = generateReturnNo();
        $userId   = currentUser()['id'];

        try {
            $pdo->beginTransaction();

            $pdo->prepare("
                INSERT INTO sale_returns (return_no, sale_id, total_refund, reason, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$returnNo, $saleId, $totalRefund, $reason ?: null, $userId]);

            $returnId = (int)$pdo->lastInsertId();

            foreach ($toReturn as $ri) {
                $pdo->prepare("
                    INSERT INTO sale_return_items
                        (return_id, sale_item_id, product_id, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([
                    $returnId, $ri['sale_item_id'], $ri['product_id'],
                    $ri['quantity'], $ri['unit_price'], $ri['total_price'],
                ]);

                if ($ri['product_id']) {
                    updateStock($ri['product_id'], $ri['quantity'], 'in', $returnNo, $userId);
                }
            }

            $pdo->commit();
            flash('success', 'Return ' . $returnNo . ' processed — '
                . formatCurrency($totalRefund) . ' to refund. Stock restored.');
            header('Location: ' . BASE_URL . '/sales/returns.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to save return: ' . $e->getMessage();
        }
    }
}

$pageTitle   = 'Process Return';
$breadcrumbs = [
    'Sales'              => BASE_URL . '/sales/index.php',
    $sale['invoice_no']  => BASE_URL . '/sales/invoice.php?id=' . $saleId,
    'Return'             => '',
];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">↩</span> Process Return</h1>
    <a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $saleId ?>" class="btn btn-secondary">← Back to Invoice</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul style="margin:0 0 0 1rem">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="sale_id" value="<?= $saleId ?>">

<div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start">

    <!-- Items table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Select Items to Return</div>
            <div class="text-muted fs-sm">Only items with remaining returnable quantity are shown.</div>
        </div>
        <div class="table-responsive">
            <table id="returnTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-center">Sold</th>
                        <th class="text-center">Returned</th>
                        <th class="text-center">Returnable</th>
                        <th class="text-center" style="width:110px">Qty to Return</th>
                        <th class="text-right">Refund</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($returnable as $item):
                    $maxQty = $item['orig_qty'] - $item['returned_qty'];
                ?>
                <tr>
                    <td class="fw-bold"><?= e($item['product_name'] ?? 'Deleted Product') ?></td>
                    <td class="fs-sm text-muted"><?= e($item['sku'] ?? '—') ?></td>
                    <td class="text-right"><?= formatCurrency($item['unit_price']) ?></td>
                    <td class="text-center"><?= $item['orig_qty'] ?></td>
                    <td class="text-center text-muted">
                        <?= $item['returned_qty'] > 0
                            ? '<span class="badge badge-warning">' . $item['returned_qty'] . '</span>'
                            : '—' ?>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-info"><?= $maxQty ?></span>
                    </td>
                    <td class="text-center">
                        <input type="number"
                               name="return_qty[<?= $item['sale_item_id'] ?>]"
                               class="form-control return-qty-input"
                               min="0" max="<?= $maxQty ?>"
                               value="0"
                               data-unit-price="<?= floatval($item['unit_price']) ?>"
                               style="width:80px;text-align:center;margin:0 auto">
                    </td>
                    <td class="text-right fw-bold refund-cell" style="color:var(--danger)">
                        <?= CURRENCY_SYMBOL ?>0.00
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F9FAFB">
                        <td colspan="7" class="text-right fw-bold" style="padding:.85rem 1rem">
                            Total Refund:
                        </td>
                        <td class="text-right fw-bold" id="totalRefundCell"
                            style="color:var(--danger);font-size:1.1rem;padding:.85rem 1rem">
                            <?= CURRENCY_SYMBOL ?>0.00
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Right panel -->
    <div style="display:flex;flex-direction:column;gap:1.25rem">

        <!-- Original sale summary -->
        <div class="card">
            <div class="card-header"><div class="card-title">Original Sale</div></div>
            <div class="card-body">
                <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                    <tr>
                        <td style="color:var(--text-muted);padding:.3rem 0">Invoice</td>
                        <td class="fw-bold">
                            <a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $saleId ?>"
                               style="color:var(--accent)"><?= e($sale['invoice_no']) ?></a>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted)">Customer</td>
                        <td><?= e($sale['customer_name']) ?></td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted)">Date</td>
                        <td><?= formatDate($sale['created_at']) ?></td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted)">Paid</td>
                        <td class="fw-bold"><?= formatCurrency($sale['total']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Reason -->
        <div class="card">
            <div class="card-header"><div class="card-title">Return Details</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Reason for Return</label>
                    <textarea name="reason" class="form-control" rows="4"
                              placeholder="Defective item, wrong size, customer changed mind..."
                    ><?= e($_POST['reason'] ?? '') ?></textarea>
                </div>

                <div style="margin-top:1rem;padding:1rem;background:rgba(239,68,68,.06);
                            border:1px solid rgba(239,68,68,.2);border-radius:var(--radius)">
                    <div class="fs-sm text-muted" style="margin-bottom:.25rem">Total to Refund</div>
                    <div id="refundSummary" style="font-size:1.5rem;font-weight:700;color:var(--danger)">
                        <?= CURRENCY_SYMBOL ?>0.00
                    </div>
                    <div class="fs-sm text-muted" style="margin-top:.3rem">
                        Returned items will be added back to stock.
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-danger w-100" id="submitBtn" disabled>
            ↩ Confirm Return
        </button>
    </div>

</div>
</form>

<script>
const sym = window.CURRENCY_SYMBOL || '₱';

function fmtCurrency(n) {
    return sym + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function recalc() {
    let total = 0;

    document.querySelectorAll('.return-qty-input').forEach(input => {
        const row      = input.closest('tr');
        const qty      = Math.max(0, parseInt(input.value) || 0);
        const price    = parseFloat(input.dataset.unitPrice) || 0;
        const lineAmt  = qty * price;

        row.querySelector('.refund-cell').textContent = fmtCurrency(lineAmt);
        total += lineAmt;
    });

    document.getElementById('totalRefundCell').textContent = fmtCurrency(total);
    document.getElementById('refundSummary').textContent   = fmtCurrency(total);
    document.getElementById('submitBtn').disabled          = total <= 0;
}

document.querySelectorAll('.return-qty-input').forEach(input => {
    input.addEventListener('input', recalc);

    // Clamp to max on blur
    input.addEventListener('blur', () => {
        const max = parseInt(input.max) || 0;
        const val = parseInt(input.value) || 0;
        if (val > max) { input.value = max; recalc(); }
        if (val < 0)   { input.value = 0;   recalc(); }
    });
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
