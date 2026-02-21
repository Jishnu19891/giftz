<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();
$pdo = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name      = trim($_POST['name']      ?? '');
        $type      = $_POST['type']           ?? 'both';
        $parentId  = intval($_POST['parent_id'] ?? 0) ?: null;
        $sortOrder = intval($_POST['sort_order'] ?? 0);

        if (!in_array($type, ['gift','cloth','both'])) $type = 'both';

        if (empty($name)) {
            flash('error', 'Category name is required.');
        } else {
            if ($action === 'add') {
                $pdo->prepare('INSERT INTO categories (name,type,parent_id,sort_order) VALUES (?,?,?,?)')
                    ->execute([$name, $type, $parentId, $sortOrder]);
                flash('success', "Category \"$name\" added.");
            } else {
                $id = intval($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE categories SET name=?,type=?,parent_id=?,sort_order=? WHERE id=?')
                    ->execute([$name, $type, $parentId, $sortOrder, $id]);
                flash('success', "Category updated.");
            }
        }
        header('Location: ' . BASE_URL . '/categories/index.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $inUse = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id=?');
        $inUse->execute([$id]);
        if ($inUse->fetchColumn() > 0) {
            flash('error', 'Cannot delete: category has products. Reassign them first.');
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
            flash('success', 'Category deleted.');
        }
        header('Location: ' . BASE_URL . '/categories/index.php');
        exit;
    }
}

// Fetch all categories
$categories = $pdo->query("
    SELECT c.*, p.name as parent_name,
           (SELECT COUNT(*) FROM products WHERE category_id=c.id) as product_count
    FROM categories c
    LEFT JOIN categories p ON p.id=c.parent_id
    ORDER BY c.sort_order ASC, c.name ASC
")->fetchAll();

$pageTitle   = 'Categories';
$breadcrumbs = ['Categories' => ''];

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="title-icon">🏷</span> Categories</h1>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">+ Add Category</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Parent</th>
                    <th class="text-center">Products</th>
                    <th class="text-center">Sort</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="icon">🏷</div>
                            <h3>No categories yet</h3>
                            <p>Add your first category above.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td class="text-muted fs-sm"><?= $cat['id'] ?></td>
                    <td><strong><?= e($cat['name']) ?></strong></td>
                    <td><?= statusBadge($cat['type']) ?></td>
                    <td class="text-muted"><?= e($cat['parent_name'] ?? '—') ?></td>
                    <td class="text-center"><span class="badge badge-secondary"><?= $cat['product_count'] ?></span></td>
                    <td class="text-center text-muted"><?= $cat['sort_order'] ?></td>
                    <td class="text-right">
                        <div class="btn-group" style="justify-content:flex-end">
                            <button class="btn btn-sm btn-secondary"
                                onclick='openEdit(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>)'>✏ Edit</button>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Delete category \'<?= e(addslashes($cat['name'])) ?>\'? This cannot be undone.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= $cat['id'] ?>">
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

<!-- Add Modal -->
<div class="modal-backdrop" id="addModal">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <div class="modal-title">Add Category</div>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid" style="grid-template-columns:1fr">
                    <div class="form-group">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-control">
                            <option value="gift">Gift</option>
                            <option value="cloth">Cloth</option>
                            <option value="both" selected>Both</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Parent Category</label>
                        <select name="parent_id" class="form-control">
                            <option value="">— None (top-level) —</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-backdrop" id="editModal">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <div class="modal-title">Edit Category</div>
            <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">×</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editCatId">
            <div class="modal-body">
                <div class="form-grid" style="grid-template-columns:1fr">
                    <div class="form-group">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" id="editType" class="form-control">
                            <option value="gift">Gift</option>
                            <option value="cloth">Cloth</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Parent Category</label>
                        <select name="parent_id" id="editParent" class="form-control">
                            <option value="">— None (top-level) —</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" id="editSort" class="form-control" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(cat) {
    document.getElementById('editCatId').value  = cat.id;
    document.getElementById('editName').value   = cat.name;
    document.getElementById('editType').value   = cat.type;
    document.getElementById('editParent').value = cat.parent_id || '';
    document.getElementById('editSort').value   = cat.sort_order || 0;
    document.getElementById('editModal').classList.add('open');
}
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
