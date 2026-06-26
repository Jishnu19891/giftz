<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo    = db();
$errors = [];

$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY sort_order, name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catId     = intval($_POST['category_id'] ?? 0);
    $amount    = floatval($_POST['amount'] ?? 0);
    $desc      = trim($_POST['description'] ?? '');
    $paidTo    = trim($_POST['paid_to'] ?? '');
    $payMethod = $_POST['payment_method'] ?? 'cash';
    $date      = $_POST['expense_date'] ?? date('Y-m-d');
    $notes     = trim($_POST['notes'] ?? '');

    $allowedPay = ['cash','card','gcash','maya','bank','transfer'];
    if (!in_array($payMethod, $allowedPay)) $payMethod = 'cash';

    if ($desc === '')  $errors[] = 'Description is required.';
    if ($amount <= 0)  $errors[] = 'Amount must be greater than zero.';
    if ($date  === '')  $errors[] = 'Date is required.';

    if (empty($errors)) {
        $refNo = generateExpenseNo();
        $userId = currentUser()['id'];

        $pdo->prepare("
            INSERT INTO expenses
                (reference_no, category_id, amount, description, paid_to, payment_method, expense_date, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $refNo,
            $catId ?: null,
            $amount,
            $desc,
            $paidTo ?: null,
            $payMethod,
            $date,
            $notes ?: null,
            $userId,
        ]);

        flash('success', "Expense $refNo recorded successfully.");
        header('Location: ' . BASE_URL . '/expenses/index.php');
        exit;
    }
}

// Repopulate on error
$form = [
    'category_id'    => intval($_POST['category_id'] ?? 0),
    'amount'         => $_POST['amount'] ?? '',
    'description'    => $_POST['description'] ?? '',
    'paid_to'        => $_POST['paid_to'] ?? '',
    'payment_method' => $_POST['payment_method'] ?? 'cash',
    'expense_date'   => $_POST['expense_date'] ?? date('Y-m-d'),
    'notes'          => $_POST['notes'] ?? '',
];

$pageTitle   = 'Add Expense';
$breadcrumbs = ['Expenses' => BASE_URL . '/expenses/index.php', 'Add Expense' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">💸</span> Add Expense</h1>
    <a href="<?= BASE_URL ?>/expenses/index.php" class="btn btn-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="flash-container">
    <?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><span class="flash-icon">✕</span><span class="flash-msg"><?= e($err) ?></span></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:680px">
    <div class="card-header"><div class="card-title">Expense Details</div></div>
    <div class="card-body" style="padding:1.5rem">
        <form method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label class="form-label">Date <span style="color:var(--danger)">*</span></label>
                    <input type="date" name="expense_date" class="form-control"
                           value="<?= e($form['expense_date']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (<?= GIFTZ_CURRENCY_SYMBOL ?>) <span style="color:var(--danger)">*</span></label>
                    <input type="number" name="amount" class="form-control" min="0.01" step="0.01"
                           value="<?= e($form['amount']) ?>" placeholder="0.00" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description <span style="color:var(--danger)">*</span></label>
                <input type="text" name="description" class="form-control" maxlength="255"
                       value="<?= e($form['description']) ?>" placeholder="What was this expense for?" required>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control">
                        <option value="0">— Select Category —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $form['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <?php foreach (['cash','card','gcash','maya','bank','transfer'] as $pm): ?>
                        <option value="<?= $pm ?>" <?= $form['payment_method'] === $pm ? 'selected' : '' ?>>
                            <?= strtoupper($pm) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Paid To</label>
                <input type="text" name="paid_to" class="form-control" maxlength="150"
                       value="<?= e($form['paid_to']) ?>" placeholder="Vendor, landlord, employee…">
            </div>

            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3"
                          placeholder="Optional additional details…"><?= e($form['notes']) ?></textarea>
            </div>

            <div style="display:flex;gap:.75rem;margin-top:.5rem">
                <button type="submit" class="btn btn-primary">💾 Save Expense</button>
                <a href="<?= BASE_URL ?>/expenses/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
