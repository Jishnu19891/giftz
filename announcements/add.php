<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo    = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message   = trim($_POST['message']    ?? '');
    $emoji     = trim($_POST['emoji']      ?? '🎉');
    $isActive  = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = intval($_POST['sort_order'] ?? 0);

    if (!$message)              $errors[] = 'Message is required.';
    if (mb_strlen($message) > 255) $errors[] = 'Message must be 255 characters or less.';
    if (!$emoji)                $errors[] = 'Emoji is required.';

    if (empty($errors)) {
        $pdo->prepare(
            'INSERT INTO announcements (message, emoji, is_active, sort_order) VALUES (?, ?, ?, ?)'
        )->execute([$message, $emoji, $isActive, $sortOrder]);

        flash('success', 'Announcement added successfully.');
        header('Location: ' . BASE_URL . '/announcements/index.php');
        exit;
    }
}

$pageTitle   = 'Add Announcement';
$breadcrumbs = ['Announcements' => BASE_URL . '/announcements/index.php', 'Add' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">📢</span> Add Announcement</h1>
</div>

<div class="card" style="max-width:640px">
    <div class="card-header">
        <div class="card-title">New Announcement</div>
    </div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-grid">
                <div class="form-group" style="max-width:120px">
                    <label class="form-label required">Emoji</label>
                    <input type="text" name="emoji" class="form-control"
                           value="<?= e($_POST['emoji'] ?? '🎉') ?>"
                           maxlength="10" required style="font-size:1.3rem;text-align:center">
                </div>

                <div class="form-group full-width">
                    <label class="form-label required">Message</label>
                    <input type="text" name="message" class="form-control"
                           value="<?= e($_POST['message'] ?? '') ?>"
                           maxlength="255" required
                           placeholder="e.g. New arrivals just landed — shop the latest collection!">
                    <span class="form-text">Max 255 characters. Shown in the storefront announcement bar.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="<?= intval($_POST['sort_order'] ?? 0) ?>" min="0">
                    <span class="form-text">Lower numbers rotate first.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <label style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem;font-size:.875rem">
                        <input type="checkbox" name="is_active"
                               <?= (!isset($_POST['message']) || isset($_POST['is_active'])) ? 'checked' : '' ?>>
                        Active — show on storefront
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Announcement</button>
                <a href="<?= BASE_URL ?>/announcements/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
