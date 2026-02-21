<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

$announcements = $pdo->query(
    'SELECT * FROM announcements ORDER BY sort_order ASC, id ASC'
)->fetchAll();

$pageTitle   = 'Announcements';
$breadcrumbs = ['Announcements' => ''];
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">📢</span> Announcements</h1>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/public/catalog.php" class="btn btn-secondary" target="_blank">🛍 Preview Storefront</a>
        <a href="<?= BASE_URL ?>/announcements/add.php" class="btn btn-primary">+ Add Announcement</a>
    </div>
</div>

<div class="card">
    <div style="padding:.85rem 1.5rem;border-bottom:1px solid var(--border);background:#FAFAFA;font-size:.82rem;color:var(--text-muted)">
        📢 These messages rotate in the announcement bar at the top of the public storefront. Only <strong>active</strong> announcements are shown.
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width:60px">Order</th>
                    <th style="width:70px;text-align:center">Emoji</th>
                    <th>Message</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($announcements)): ?>
                <tr><td colspan="5"><div class="empty-state">
                    <div class="icon">📢</div>
                    <h3>No announcements yet</h3>
                    <p><a href="<?= BASE_URL ?>/announcements/add.php">Add your first announcement</a></p>
                </div></td></tr>
            <?php else: ?>
                <?php foreach ($announcements as $a): ?>
                <tr>
                    <td class="text-center text-muted fs-sm"><?= (int)$a['sort_order'] ?></td>
                    <td style="font-size:1.4rem;text-align:center"><?= e($a['emoji']) ?></td>
                    <td><?= e($a['message']) ?></td>
                    <td class="text-center">
                        <form method="POST" action="<?= BASE_URL ?>/announcements/toggle.php" style="display:inline">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit"
                                class="badge <?= $a['is_active'] ? 'badge-success' : 'badge-secondary' ?>"
                                style="border:none;cursor:pointer;font-size:.72rem"
                                title="Click to toggle">
                                <?= $a['is_active'] ? '● Active' : '○ Inactive' ?>
                            </button>
                        </form>
                    </td>
                    <td class="text-right">
                        <div class="btn-group" style="justify-content:flex-end">
                            <a href="<?= BASE_URL ?>/announcements/edit.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-secondary">✏ Edit</a>
                            <form method="POST" action="<?= BASE_URL ?>/announcements/delete.php"
                                  style="display:inline"
                                  onsubmit="return confirm('Delete this announcement?')">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
