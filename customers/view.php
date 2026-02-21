<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();
$id  = intval($_GET['id'] ?? 0);

// Fetch customer
$custStmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
$custStmt->execute([$id]);
$customer = $custStmt->fetch();

if (!$customer) {
    flash('error', 'Customer not found.');
    header('Location: ' . BASE_URL . '/customers/index.php');
    exit;
}

// ── Summary KPIs ────────────────────────────────────────────
$kpiStmt = $pdo->prepare("
    SELECT
        COUNT(*)                        AS total_orders,
        COALESCE(SUM(total), 0)         AS total_spent,
        COALESCE(AVG(total), 0)         AS avg_order,
        COALESCE(SUM(discount), 0)      AS total_saved,
        MAX(created_at)                 AS last_purchase
    FROM sales
    WHERE customer_id = ? AND status = 'completed'
");
$kpiStmt->execute([$id]);
$kpi = $kpiStmt->fetch();

// ── Spending trend – last 6 months ──────────────────────────
$trendStmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%b %Y') AS label,
           DATE_FORMAT(created_at, '%Y-%m') AS sort_key,
           COALESCE(SUM(total), 0)          AS revenue
    FROM sales
    WHERE customer_id = ? AND status = 'completed'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY sort_key, label
    ORDER BY sort_key ASC
");
$trendStmt->execute([$id]);
$trend = $trendStmt->fetchAll();
$trendLabels  = array_column($trend, 'label');
$trendRevenue = array_map('floatval', array_column($trend, 'revenue'));

// ── Top purchased products ───────────────────────────────────
$topStmt = $pdo->prepare("
    SELECT p.name, p.sku,
           SUM(si.quantity)   AS qty_bought,
           SUM(si.total_price) AS amount_spent
    FROM sale_items si
    JOIN sales s   ON s.id = si.sale_id AND s.customer_id = ? AND s.status = 'completed'
    JOIN products p ON p.id = si.product_id
    GROUP BY si.product_id, p.name, p.sku
    ORDER BY amount_spent DESC
    LIMIT 5
");
$topStmt->execute([$id]);
$topProducts = $topStmt->fetchAll();

// ── Purchase history (paginated) ─────────────────────────────
$page      = max(1, intval($_GET['page'] ?? 1));
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE customer_id = ?");
$countStmt->execute([$id]);
$totalSales = (int)$countStmt->fetchColumn();
$pg = paginate($totalSales, $page);

$salesStmt = $pdo->prepare("
    SELECT s.id, s.invoice_no, s.subtotal, s.discount, s.tax, s.total,
           s.payment_method, s.status, s.created_at,
           u.name AS cashier_name,
           (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) AS item_count
    FROM sales s
    LEFT JOIN users u ON u.id = s.created_by
    WHERE s.customer_id = ?
    ORDER BY s.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$salesStmt->execute([$id]);
$sales = $salesStmt->fetchAll();

// Inline JS for trend chart
$inlineJs = '';
if (!empty($trendLabels)) {
    $inlineJs = "
document.addEventListener('DOMContentLoaded', () => {
    GiftzCharts.salesTrend('customerTrendChart',
        " . json_encode($trendLabels) . ",
        " . json_encode($trendRevenue) . "
    );
});
";
}

$pageTitle   = e($customer['name']);
$breadcrumbs = ['Customers' => BASE_URL . '/customers/index.php', $customer['name'] => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <span class="title-icon">👤</span>
        <?= e($customer['name']) ?>
    </h1>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-secondary">← Back</a>
        <a href="<?= BASE_URL ?>/customers/add.php?id=<?= $customer['id'] ?>" class="btn btn-primary">✏ Edit</a>
    </div>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="margin-bottom:1.25rem;grid-template-columns:repeat(4,1fr)">
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon">🧾</div>
        <div class="kpi-info">
            <div class="kpi-label">Total Orders</div>
            <div class="kpi-value"><?= number_format($kpi['total_orders']) ?></div>
            <div class="kpi-sub">completed sales</div>
        </div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-icon">💰</div>
        <div class="kpi-info">
            <div class="kpi-label">Total Spent</div>
            <div class="kpi-value"><?= formatCurrency($kpi['total_spent']) ?></div>
            <div class="kpi-sub">lifetime value</div>
        </div>
    </div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-icon">📊</div>
        <div class="kpi-info">
            <div class="kpi-label">Avg. Order</div>
            <div class="kpi-value"><?= formatCurrency($kpi['avg_order']) ?></div>
            <div class="kpi-sub">per transaction</div>
        </div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon">🏷</div>
        <div class="kpi-info">
            <div class="kpi-label">Total Saved</div>
            <div class="kpi-value"><?= formatCurrency($kpi['total_saved']) ?></div>
            <div class="kpi-sub">via discounts</div>
        </div>
    </div>
</div>

<!-- Main Grid: Profile + Chart -->
<div style="display:grid;grid-template-columns:300px 1fr;gap:1.25rem;margin-bottom:1.25rem;align-items:start">

    <!-- Profile Card -->
    <div style="display:flex;flex-direction:column;gap:1rem">
        <div class="card">
            <div class="card-body" style="text-align:center;padding:2rem 1.5rem">
                <!-- Avatar -->
                <div style="width:72px;height:72px;border-radius:50%;
                            background:linear-gradient(135deg,var(--accent),var(--secondary));
                            display:grid;place-items:center;color:#fff;font-size:1.75rem;
                            font-weight:700;margin:0 auto 1rem">
                    <?= strtoupper(mb_substr($customer['name'], 0, 1)) ?>
                </div>
                <div style="font-size:1.1rem;font-weight:700;margin-bottom:.25rem"><?= e($customer['name']) ?></div>
                <?php if ($kpi['last_purchase']): ?>
                <div class="fs-sm text-muted">Last purchase <?= formatDate($kpi['last_purchase']) ?></div>
                <?php else: ?>
                <div class="fs-sm text-muted">No purchases yet</div>
                <?php endif; ?>
            </div>

            <div style="border-top:1px solid var(--border)">
                <?php
                $details = [
                    '📞' => $customer['phone']   ? e($customer['phone'])   : null,
                    '✉'  => $customer['email']   ? e($customer['email'])   : null,
                    '📍' => $customer['address'] ? e($customer['address']) : null,
                    '📅' => 'Joined ' . formatDate($customer['created_at']),
                ];
                foreach ($details as $icon => $value):
                    if (!$value) continue;
                ?>
                <div style="display:flex;align-items:flex-start;gap:.65rem;padding:.7rem 1.25rem;
                            border-bottom:1px solid var(--border);font-size:.875rem">
                    <span style="flex-shrink:0;width:1.1rem;text-align:center"><?= $icon ?></span>
                    <span style="color:var(--text-muted);word-break:break-word"><?= $value ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Products -->
        <?php if (!empty($topProducts)): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏆 Top Products</div>
            </div>
            <div style="padding:.5rem 0">
                <?php foreach ($topProducts as $i => $tp): ?>
                <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 1.25rem;
                            <?= $i < count($topProducts) - 1 ? 'border-bottom:1px solid var(--border)' : '' ?>">
                    <div style="width:24px;height:24px;border-radius:50%;
                                background:var(--accent);color:#fff;display:grid;
                                place-items:center;font-size:.72rem;font-weight:700;flex-shrink:0">
                        <?= $i + 1 ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div class="fw-bold fs-sm" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            <?= e($tp['name']) ?>
                        </div>
                        <div class="text-muted" style="font-size:.75rem"><?= e($tp['sku']) ?> · <?= $tp['qty_bought'] ?> units</div>
                    </div>
                    <div class="fw-bold fs-sm" style="flex-shrink:0"><?= formatCurrency($tp['amount_spent']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Spending Trend Chart -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">📈 Spending Trend (Last 6 Months)</div>
        </div>
        <div class="card-body">
            <?php if (empty($trendLabels)): ?>
            <div class="empty-state" style="padding:3rem 1rem">
                <div class="icon">📊</div>
                <p>No purchase data yet.</p>
            </div>
            <?php else: ?>
            <div class="chart-container" style="height:260px">
                <canvas id="customerTrendChart"></canvas>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Purchase History -->
<div class="card">
    <div class="card-header">
        <div class="card-title">🧾 Purchase History</div>
        <div class="text-muted fs-sm"><?= number_format($totalSales) ?> transaction<?= $totalSales !== 1 ? 's' : '' ?></div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th class="text-center">Items</th>
                    <th>Payment</th>
                    <th>Cashier</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">Discount</th>
                    <th class="text-right">Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($sales)): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <div class="icon">🧾</div>
                            <h3>No purchases yet</h3>
                            <p>This customer hasn't made any purchases.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($sales as $s): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $s['id'] ?>"
                           class="fw-bold" style="color:var(--accent)">
                            <?= e($s['invoice_no']) ?>
                        </a>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-secondary"><?= $s['item_count'] ?></span>
                    </td>
                    <td>
                        <span class="badge badge-info"><?= e(strtoupper($s['payment_method'])) ?></span>
                    </td>
                    <td class="text-muted"><?= e($s['cashier_name'] ?? '—') ?></td>
                    <td class="text-right"><?= formatCurrency($s['subtotal']) ?></td>
                    <td class="text-right text-muted">
                        <?= $s['discount'] > 0 ? '−' . formatCurrency($s['discount']) : '—' ?>
                    </td>
                    <td class="text-right fw-bold"><?= formatCurrency($s['total']) ?></td>
                    <td><?= statusBadge($s['status']) ?></td>
                    <td class="text-muted fs-sm"><?= formatDateTime($s['created_at']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $s['id'] ?>"
                           class="btn btn-sm btn-secondary" target="_blank">🖨 View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pg['total_pages'] > 1): ?>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);
                display:flex;align-items:center;justify-content:space-between">
        <div class="text-muted fs-sm">
            Showing <?= $pg['offset'] + 1 ?>–<?= min($pg['offset'] + $pg['per_page'], $totalSales) ?>
            of <?= number_format($totalSales) ?>
        </div>
        <?= paginationLinks($pg, BASE_URL . '/customers/view.php?id=' . $id) ?>
    </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
