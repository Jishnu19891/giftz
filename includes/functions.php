<?php
require_once __DIR__ . '/db.php';

// ─── Currency ─────────────────────────────────────────────────────────────────
function formatCurrency(float $amount): string {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

// ─── SKU Generation ───────────────────────────────────────────────────────────
function generateSKU(string $categoryCode, string $type): string {
    $prefix = strtoupper(substr($categoryCode, 0, 3));
    $suffix = strtoupper(substr($type, 0, 1));
    $num    = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $suffix . $num;
}

// ─── Invoice Number ───────────────────────────────────────────────────────────
function generateInvoiceNo(): string {
    $year  = date('Y');
    $stmt  = db()->prepare(
        "SELECT COUNT(*) FROM sales WHERE YEAR(created_at) = ?"
    );
    $stmt->execute([$year]);
    $count = (int)$stmt->fetchColumn() + 1;
    return 'INV-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

// ─── PO Reference ─────────────────────────────────────────────────────────────
function generatePoRef(): string {
    $year  = date('Y');
    $stmt  = db()->prepare(
        "SELECT COUNT(*) FROM purchases WHERE YEAR(created_at) = ?"
    );
    $stmt->execute([$year]);
    $count = (int)$stmt->fetchColumn() + 1;
    return 'PO-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// ─── Expense Number ────────────────────────────────────────────────────────────
function generateExpenseNo(): string {
    $year  = date('Y');
    $stmt  = db()->prepare(
        "SELECT COUNT(*) FROM expenses WHERE YEAR(expense_date) = ?"
    );
    $stmt->execute([$year]);
    $count = (int)$stmt->fetchColumn() + 1;
    return 'EXP-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

// ─── Return Number ─────────────────────────────────────────────────────────────
function generateReturnNo(): string {
    $year  = date('Y');
    $stmt  = db()->prepare(
        "SELECT COUNT(*) FROM sale_returns WHERE YEAR(created_at) = ?"
    );
    $stmt->execute([$year]);
    $count = (int)$stmt->fetchColumn() + 1;
    return 'RET-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

// ─── Image Upload ─────────────────────────────────────────────────────────────
function uploadImage(array $file, string $dir = UPLOAD_PATH): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mime    = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) return false;

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . strtolower($ext);
    $dest     = rtrim($dir, '/') . '/' . $filename;

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return move_uploaded_file($file['tmp_name'], $dest) ? $filename : false;
}

// ─── Stock Movement ───────────────────────────────────────────────────────────
function updateStock(int $productId, int $qty, string $direction, string $reference, int $userId): void {
    $pdo = db();

    if ($direction === 'in') {
        $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?')
            ->execute([$qty, $productId]);
    } elseif ($direction === 'out') {
        $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?')
            ->execute([$qty, $productId]);
    } elseif ($direction === 'adjustment') {
        $pdo->prepare('UPDATE products SET stock_qty = ? WHERE id = ?')
            ->execute([$qty, $productId]);
    }

    $pdo->prepare(
        'INSERT INTO stock_movements (product_id, type, quantity, reference, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    )->execute([$productId, $direction, $qty, $reference, $userId]);
}

// ─── Pagination ───────────────────────────────────────────────────────────────
function paginate(int $total, int $page, int $perPage = ROWS_PER_PAGE): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;

    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $page,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $totalPages,
        'prev'        => $page - 1,
        'next'        => $page + 1,
    ];
}

function paginationLinks(array $p, string $baseUrl): string {
    if ($p['total_pages'] <= 1) return '';

    $html = '<nav class="pagination">';
    if ($p['has_prev']) {
        $html .= '<a href="' . $baseUrl . '&page=' . $p['prev'] . '" class="page-btn">&laquo; Prev</a>';
    }
    for ($i = max(1, $p['current'] - 2); $i <= min($p['total_pages'], $p['current'] + 2); $i++) {
        $active = $i === $p['current'] ? ' active' : '';
        $html .= '<a href="' . $baseUrl . '&page=' . $i . '" class="page-btn' . $active . '">' . $i . '</a>';
    }
    if ($p['has_next']) {
        $html .= '<a href="' . $baseUrl . '&page=' . $p['next'] . '" class="page-btn">Next &raquo;</a>';
    }
    $html .= '</nav>';
    return $html;
}

// ─── Flash Messages ───────────────────────────────────────────────────────────
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlash(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ─── Low Stock Count ──────────────────────────────────────────────────────────
function getLowStockCount(): int {
    $stmt = db()->query(
        'SELECT COUNT(*) FROM products WHERE stock_qty <= min_stock_level AND status = "active"'
    );
    return (int)$stmt->fetchColumn();
}

// ─── Sanitize Output ──────────────────────────────────────────────────────────
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Date Helpers ─────────────────────────────────────────────────────────────
function formatDate(string $date, string $format = 'M d, Y'): string {
    if (empty($date) || $date === '0000-00-00') return '—';
    return date($format, strtotime($date));
}

function formatDateTime(string $datetime): string {
    if (empty($datetime)) return '—';
    return date('M d, Y h:i A', strtotime($datetime));
}

// ─── Status Badge ─────────────────────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'active'    => 'badge-success',
        'inactive'  => 'badge-secondary',
        'received'  => 'badge-success',
        'pending'   => 'badge-warning',
        'partial'   => 'badge-info',
        'cancelled' => 'badge-danger',
        'completed' => 'badge-success',
        'voided'    => 'badge-danger',
        'admin'     => 'badge-purple',
        'staff'     => 'badge-info',
    ];
    $class = $map[strtolower($status)] ?? 'badge-secondary';
    return '<span class="badge ' . $class . '">' . e(ucfirst($status)) . '</span>';
}
