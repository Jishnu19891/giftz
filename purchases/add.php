<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo    = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId   = intval($_POST['supplier_id']  ?? 0) ?: null;
    $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');
    $notes        = trim($_POST['notes']    ?? '');
    $productIds   = $_POST['product_id']    ?? [];
    $quantities   = $_POST['quantity']      ?? [];
    $unitCosts    = $_POST['unit_cost']     ?? [];

    $items = [];
    $total = 0;
    foreach ($productIds as $i => $pid) {
        $pid  = intval($pid);
        $qty  = intval($quantities[$i]  ?? 0);
        $cost = floatval($unitCosts[$i] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $items[] = [$pid, $qty, $cost];
            $total  += $qty * $cost;
        }
    }

    if (empty($items)) $errors[] = 'Add at least one valid product item.';

    if (empty($errors)) {
        $refNo = generatePoRef();

        try {
            $pdo->beginTransaction();

            $pdo->prepare('
                INSERT INTO purchases (supplier_id,reference_no,purchase_date,total_amount,status,notes,created_by,created_at)
                VALUES (?,?,?,?,\'pending\',?,?,NOW())
            ')->execute([$supplierId, $refNo, $purchaseDate, $total, $notes ?: null, currentUser()['id']]);

            $poId = (int)$pdo->lastInsertId();

            foreach ($items as [$pid, $qty, $cost]) {
                $pdo->prepare('INSERT INTO purchase_items (purchase_id,product_id,quantity,unit_cost) VALUES (?,?,?,?)')
                    ->execute([$poId, $pid, $qty, $cost]);
            }

            $pdo->commit();
            flash('success', "Purchase order $refNo created (pending). Click Receive to update stock.");
            header('Location: ' . BASE_URL . '/purchases/index.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed: ' . $e->getMessage();
        }
    }
}

$suppliers = $pdo->query('SELECT id,name FROM suppliers ORDER BY name')->fetchAll();
$products  = $pdo->query("SELECT id,sku,name,cost_price FROM products WHERE status='active' ORDER BY name")->fetchAll();

$pageTitle   = 'New Purchase Order';
$breadcrumbs = ['Purchases' => BASE_URL . '/purchases/index.php', 'Add PO' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">+</span> New Purchase Order</h1>
    <a href="<?= BASE_URL ?>/purchases/index.php" class="btn btn-secondary">← Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul style="margin:0 0 0 1rem"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="POST">
<div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start">

    <div class="card">
        <div class="card-header">
            <div class="card-title">Order Items</div>
            <button type="button" class="btn btn-sm btn-secondary" id="addLineBtn">+ Add Row</button>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-responsive">
                <table id="lineItems">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="width:90px">Qty</th>
                            <th style="width:120px">Unit Cost</th>
                            <th class="text-right" style="width:110px">Subtotal</th>
                            <th style="width:44px"></th>
                        </tr>
                    </thead>
                    <tbody id="lineBody">
                        <tr class="line-row">
                            <td>
                                <select name="product_id[]" class="form-control prod-select" onchange="fillCost(this)" required>
                                    <option value="">— Select Product —</option>
                                    <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-cost="<?= $p['cost_price'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="quantity[]" class="form-control qty-inp" min="1" value="1" onchange="updateSub(this)"></td>
                            <td><input type="number" name="unit_cost[]" class="form-control cost-inp" min="0" step="0.01" value="0" onchange="updateSub(this)"></td>
                            <td class="text-right fw-bold sub-cell" style="color:var(--accent)">0.00</td>
                            <td><button type="button" class="btn btn-sm btn-danger btn-icon" onclick="removeLine(this)">✕</button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right fw-bold" style="padding:.85rem 1rem">Grand Total:</td>
                            <td class="text-right fw-bold" id="grandTotal" style="color:var(--accent);font-size:1.05rem;padding:.85rem 1rem">0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:1.25rem">
        <div class="card">
            <div class="card-header"><div class="card-title">PO Details</div></div>
            <div class="card-body">
                <div class="form-grid" style="grid-template-columns:1fr">
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">— No Supplier —</option>
                            <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" style="min-height:80px"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info" style="margin:0">
            <div><strong>Note:</strong> PO created as <em>Pending</em>. Use "Receive" to update product stock.</div>
        </div>

        <button type="submit" class="btn btn-primary w-100">Create Purchase Order</button>
    </div>

</div>
</form>

<script>
const productMap = {};
<?php foreach ($products as $p): ?>
productMap[<?= $p['id'] ?>] = { cost: <?= (float)$p['cost_price'] ?> };
<?php endforeach; ?>

function fillCost(sel) {
    const row  = sel.closest('tr');
    const data = productMap[sel.value];
    row.querySelector('.cost-inp').value = data ? data.cost.toFixed(2) : '0.00';
    updateSub(row.querySelector('.qty-inp'));
}

function updateSub(inp) {
    const row  = inp.closest('tr');
    const qty  = parseFloat(row.querySelector('.qty-inp').value)  || 0;
    const cost = parseFloat(row.querySelector('.cost-inp').value) || 0;
    row.querySelector('.sub-cell').textContent = (qty * cost).toFixed(2);
    updateTotal();
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('#lineBody .sub-cell').forEach(c => total += parseFloat(c.textContent) || 0);
    document.getElementById('grandTotal').textContent = total.toFixed(2);
}

function removeLine(btn) {
    const rows = document.querySelectorAll('#lineBody .line-row');
    if (rows.length <= 1) { alert('At least one item required.'); return; }
    btn.closest('tr').remove();
    updateTotal();
}

document.getElementById('addLineBtn').addEventListener('click', () => {
    const tbody   = document.getElementById('lineBody');
    const newRow  = tbody.querySelector('.line-row').cloneNode(true);
    // Reset values
    newRow.querySelector('.prod-select').value = '';
    newRow.querySelector('.qty-inp').value     = 1;
    newRow.querySelector('.cost-inp').value    = '0.00';
    newRow.querySelector('.sub-cell').textContent = '0.00';
    // Re-bind events
    newRow.querySelector('.prod-select').addEventListener('change', function() { fillCost(this); });
    newRow.querySelectorAll('.qty-inp, .cost-inp').forEach(el => el.addEventListener('change', function() { updateSub(this); }));
    newRow.querySelector('button').addEventListener('click', function() { removeLine(this); });
    tbody.appendChild(newRow);
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
