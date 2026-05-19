<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $desc]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Category added!'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => '⚠️ Category name is required.'];
    }
    header('Location: categories.php'); exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Category deleted.'];
    header('Location: categories.php'); exit;
}

// EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $desc, (int)$_POST['id']]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Category updated.'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => '⚠️ Category name is required.'];
    }
    header('Location: categories.php'); exit;
}

$stmt = $pdo->query("
    SELECT c.*, COUNT(i.id) as item_count
    FROM categories c
    LEFT JOIN items i ON c.id = i.category_id
    GROUP BY c.id ORDER BY c.id
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Categories';
include 'header.php';
?>

<style>
.page-grid { display:grid; grid-template-columns:380px 1fr; gap:1.5rem; align-items:start; }
</style>

<div class="page-grid">
    <!-- ADD FORM -->
    <div class="card">
        <div class="card-header">Add New Category</div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Appetizer" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" placeholder="Short description…"></textarea>
                </div>
                <button type="submit" name="add" class="btn btn-primary">+ Add Category</button>
            </form>
        </div>
    </div>

    <!-- LIST -->
    <div class="card">
        <div class="card-header">All Categories</div>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Items</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($categories)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--gray);padding:2rem">No categories yet</td></tr>
                <?php else: ?>
                    <?php foreach($categories as $cat): ?>
                    <tr>
                        <td><?= $cat['id'] ?></td>
                        <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                        <td style="font-size:0.85rem;color:var(--gray)"><?= htmlspecialchars($cat['description']) ?></td>
                        <td><span class="badge badge-preparing"><?= $cat['item_count'] ?></span></td>
                        <td>
                            <div style="display:flex;gap:0.4rem">
                                <button class="btn btn-outline btn-sm"
                                    onclick="openEdit(<?= $cat['id'] ?>, '<?= addslashes($cat['name']) ?>', '<?= addslashes($cat['description']) ?>')">
                                    ✏️ Edit
                                </button>
                                <a href="?delete=<?= $cat['id'] ?>" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Delete this category?')">🗑</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="text-align:left">
        <h3 style="margin-bottom:1rem">Edit Category</h3>
        <form method="POST">
            <input type="hidden" name="id" id="editId">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" id="editName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="editDesc" class="form-control"></textarea>
            </div>
            <div class="modal-actions" style="justify-content:flex-start;margin-top:1rem">
                <button type="submit" name="edit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-outline"
                    onclick="document.getElementById('editModal').classList.remove('show')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, desc) {
    document.getElementById('editId').value   = id;
    document.getElementById('editName').value = name;
    document.getElementById('editDesc').value = desc;
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php include 'footer.php'; ?>