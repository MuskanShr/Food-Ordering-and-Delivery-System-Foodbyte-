<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

if (!is_dir('../uploads')) mkdir('../uploads', 0755, true);

/* ---------------------------------------------------------------
 * Tag helpers
 * ------------------------------------------------------------- */

// Load every tag once — used by handlers (validation) and the UI.
$allTags     = $pdo->query("SELECT id, slug, label FROM tags ORDER BY label")
                   ->fetchAll(PDO::FETCH_ASSOC);
$validTagIds = array_map('intval', array_column($allTags, 'id'));

/**
 * Take the raw $_POST['tag_ids'] (array of strings from checkboxes),
 * cast to int, and DROP anything that isn't a real tag in the DB.
 * Never trust client-provided IDs.
 */
function parseTagIds($posted, array $validIds): array {
    if (!is_array($posted)) return [];
    $clean = [];
    foreach ($posted as $tid) {
        $tid = (int)$tid;
        if (in_array($tid, $validIds, true)) $clean[$tid] = true;
    }
    return array_keys($clean);
}

/**
 * Replace all tags for an item in one shot. Caller must already be
 * inside a transaction.
 */
function saveItemTags(PDO $pdo, int $itemId, array $tagIds): void {
    $pdo->prepare("DELETE FROM item_tags WHERE item_id = ?")->execute([$itemId]);
    if (empty($tagIds)) return;
    $ins = $pdo->prepare("INSERT INTO item_tags (item_id, tag_id) VALUES (?, ?)");
    foreach ($tagIds as $tid) $ins->execute([$itemId, $tid]);
}

/* ---------------------------------------------------------------
 * ADD
 * ------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $cat_id = (int)($_POST['category_id'] ?? 0);
    $price  = (float)($_POST['price'] ?? 0);
    $tagIds = parseTagIds($_POST['tag_ids'] ?? [], $validTagIds);
    $image  = null;

    if ($name && $cat_id && $price > 0) {
        if (!empty($_FILES['image']['name'])) {
            $ext     = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array(strtolower($ext), $allowed)) {
                $filename = uniqid('item_') . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $filename);
                $image = $filename;
            }
        }
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO items (name, description, category_id, price, image)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $desc, $cat_id, $price, $image]);
            $newId = (int)$pdo->lastInsertId();
            saveItemTags($pdo, $newId, $tagIds);
            $pdo->commit();

            $msg = '✅ Item added!';
            if (empty($tagIds)) {
                $msg .= ' ⚠️ No tags selected — this item will only show in '
                      . 'chatbot results via keyword fallback.';
            }
            $_SESSION['flash'] = ['type' => 'success', 'msg' => $msg];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'error', 'msg' => '⚠️ Save failed: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => '⚠️ Please fill all required fields.'];
    }
    header('Location: items.php'); exit;
}

/* ---------------------------------------------------------------
 * DELETE — unchanged.
 * item_tags has FK ON DELETE CASCADE so tag links are auto-cleaned.
 * ------------------------------------------------------------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT image FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['image'] && file_exists('../uploads/' . $row['image'])) {
        unlink('../uploads/' . $row['image']);
    }
    $pdo->prepare("DELETE FROM items WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Item deleted.'];
    header('Location: items.php'); exit;
}

/* ---------------------------------------------------------------
 * EDIT
 * ------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id     = (int)$_POST['id'];
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $cat_id = (int)($_POST['category_id'] ?? 0);
    $price  = (float)($_POST['price'] ?? 0);
    $tagIds = parseTagIds($_POST['tag_ids'] ?? [], $validTagIds);
    $image  = null;

    if (!empty($_FILES['image']['name'])) {
        $ext     = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $filename = uniqid('item_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $filename);
            $image = $filename;
        }
    }

    if ($name && $cat_id && $price > 0) {
        try {
            $pdo->beginTransaction();
            if ($image) {
                $stmt = $pdo->prepare(
                    "UPDATE items SET name=?, description=?, category_id=?, price=?, image=? WHERE id=?"
                );
                $stmt->execute([$name, $desc, $cat_id, $price, $image, $id]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE items SET name=?, description=?, category_id=?, price=? WHERE id=?"
                );
                $stmt->execute([$name, $desc, $cat_id, $price, $id]);
            }
            saveItemTags($pdo, $id, $tagIds);
            $pdo->commit();

            $msg = '✅ Item updated.';
            if (empty($tagIds)) {
                $msg .= ' ⚠️ No tags selected — won\'t appear in chatbot tag matches.';
            }
            $_SESSION['flash'] = ['type' => 'success', 'msg' => $msg];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'error', 'msg' => '⚠️ Save failed: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => '⚠️ Please fill all required fields.'];
    }
    header('Location: items.php'); exit;
}

/* ---------------------------------------------------------------
 * Load data for the page
 * ------------------------------------------------------------- */
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$items = $pdo->query("
    SELECT i.*, c.name as cat_name
    FROM items i JOIN categories c ON i.category_id = c.id
    ORDER BY i.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// item_id -> [tag_id,...]  (for JS) and item_id -> [label,...] (for table)
$itemTagMap    = [];
$itemTagLabels = [];
$tagRows = $pdo->query("
    SELECT it.item_id, it.tag_id, t.label
    FROM item_tags it JOIN tags t ON t.id = it.tag_id
    ORDER BY t.label
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($tagRows as $r) {
    $iid = (int)$r['item_id'];
    $itemTagMap[$iid][]    = (int)$r['tag_id'];
    $itemTagLabels[$iid][] = $r['label'];
}

/* ---------------------------------------------------------------
 * UI grouping for the tag picker.
 * Only affects display order; new tags not listed here drop into "Other".
 * ------------------------------------------------------------- */
$tagGroups = [
    'Flavour' => ['spicy','sweet','savory','creamy','crispy','cheesy'],
    'Cooking' => ['fried','grilled','baked'],
    'Form'    => ['cold','drink','dessert'],
    'Diet'    => ['vegetarian','vegan','chicken','meat'],
    'Size'    => ['light','filling'],
    'Price'   => ['cheap','midrange','premium'],
    'Other'   => ['refreshing','healthy'],
];

/**
 * Render checkbox chips grouped by category.
 * $selected = array of tag IDs to pre-check.
 * $idPrefix needed because we render TWO copies (add form + edit modal),
 * and HTML id attributes must be unique on the page.
 */
function renderTagPicker(array $allTags, array $tagGroups, array $selected, string $idPrefix): string {
    $bySlug = [];
    foreach ($allTags as $t) $bySlug[$t['slug']] = $t;

    $out      = '';
    $usedIds  = [];

    foreach ($tagGroups as $groupName => $slugs) {
        $tagsInGroup = [];
        foreach ($slugs as $s) if (isset($bySlug[$s])) $tagsInGroup[] = $bySlug[$s];
        if (empty($tagsInGroup)) continue;

        $out .= '<div class="tag-group">';
        $out .= '<div class="tag-group-label">' . htmlspecialchars($groupName) . '</div>';
        $out .= '<div class="tag-chips">';
        foreach ($tagsInGroup as $t) {
            $tid       = (int)$t['id'];
            $usedIds[] = $tid;
            $checked   = in_array($tid, $selected, true) ? 'checked' : '';
            $domId     = $idPrefix . '_tag_' . $tid;
            $out .= '<label class="tag-chip" for="' . $domId . '">'
                  . '<input type="checkbox" name="tag_ids[]" value="' . $tid
                  . '" id="' . $domId . '" ' . $checked . '>'
                  . '<span>' . htmlspecialchars($t['label']) . '</span>'
                  . '</label>';
        }
        $out .= '</div></div>';
    }

    // Tags not assigned to any group land in "Uncategorized"
    $orphans = array_filter($allTags, fn($t) => !in_array((int)$t['id'], $usedIds, true));
    if ($orphans) {
        $out .= '<div class="tag-group">';
        $out .= '<div class="tag-group-label">Uncategorized</div>';
        $out .= '<div class="tag-chips">';
        foreach ($orphans as $t) {
            $tid     = (int)$t['id'];
            $checked = in_array($tid, $selected, true) ? 'checked' : '';
            $domId   = $idPrefix . '_tag_' . $tid;
            $out .= '<label class="tag-chip" for="' . $domId . '">'
                  . '<input type="checkbox" name="tag_ids[]" value="' . $tid
                  . '" id="' . $domId . '" ' . $checked . '>'
                  . '<span>' . htmlspecialchars($t['label']) . '</span>'
                  . '</label>';
        }
        $out .= '</div></div>';
    }

    return $out;
}

$pageTitle = 'Items';
include 'header.php';
?>

<style>
.page-grid { display:grid; grid-template-columns:380px 1fr; gap:1.5rem; align-items:start; }
.item-thumb {
    width:44px; height:44px; border-radius:8px;
    object-fit:cover; background:#FFF0E6;
    display:flex; align-items:center; justify-content:center;
    font-size:1.4rem; overflow:hidden; border:1px solid var(--border);
}
.item-thumb img { width:100%; height:100%; object-fit:cover; }

/* Tag picker (shared by add form + edit modal) */
.tag-group { margin-bottom: 0.7rem; }
.tag-group-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--gray);
    margin-bottom: 0.3rem;
    font-weight: 700;
}
.tag-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.tag-chip { cursor: pointer; user-select: none; }
.tag-chip input { position:absolute; opacity:0; width:0; height:0; }
.tag-chip span {
    display: inline-block;
    padding: 0.28rem 0.7rem;
    border-radius: 16px;
    background: var(--cream, #FAF7F2);
    border: 1px solid var(--border);
    font-size: 0.78rem;
    color: var(--charcoal);
    transition: all 0.15s;
}
.tag-chip:hover span { border-color: var(--orange); }
.tag-chip input:checked + span {
    background: var(--orange, #E8521A);
    color: #fff;
    border-color: var(--orange);
}
.tag-chip input:focus-visible + span {
    outline: 2px solid var(--orange);
    outline-offset: 2px;
}

/* Inline tag pills shown in the items list */
.item-tag-list { display:flex; flex-wrap:wrap; gap:0.2rem; max-width:220px; }
.item-tag-pill {
    font-size: 0.66rem;
    padding: 0.1rem 0.45rem;
    background: var(--cream);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--gray);
    white-space: nowrap;
}
.item-tag-empty {
    font-size: 0.7rem;
    color: #C62828;
    font-style: italic;
}
</style>

<div class="page-grid">
    <!-- ADD FORM -->
    <div class="card">
        <div class="card-header">Add New Item</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Item Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Chicken Pizza" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" placeholder="Short description…"></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select</option>
                            <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Price (Rs) *</label>
                        <input type="number" name="price" class="form-control" placeholder="0" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Tags <span style="font-weight:400;color:var(--gray);font-size:0.78rem"></span></label>
                    <?= renderTagPicker($allTags, $tagGroups, [], 'add') ?>
                </div>

                <button type="submit" name="add" class="btn btn-primary">+ Add Item</button>
            </form>
        </div>
    </div>

    <!-- ITEMS LIST -->
    <div class="card">
        <div class="card-header">
            All Items <span style="font-size:0.8rem;color:var(--gray);font-weight:400">(<?= count($items) ?>)</span>
        </div>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Image</th><th>Name</th>
                        <th>Category</th><th>Price</th><th>Tags</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($items)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--gray);padding:2rem">No items yet</td></tr>
                <?php else: ?>
                    <?php foreach($items as $item):
                        $iid       = (int)$item['id'];
                        $thisTags  = $itemTagMap[$iid]    ?? [];
                        $thisLabels = $itemTagLabels[$iid] ?? [];
                    ?>
                    <tr>
                        <td><?= $iid ?></td>
                        <td>
                            <div class="item-thumb">
                                <?php if($item['image'] && file_exists('../uploads/'.$item['image'])): ?>
                                    <img src="/foodbyte/uploads/<?= htmlspecialchars($item['image']) ?>" alt="">
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($item['name']) ?></strong>
                            <div style="font-size:0.77rem;color:var(--gray);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($item['description']) ?>
                            </div>
                        </td>
                        <td><span class="badge badge-preparing"><?= htmlspecialchars($item['cat_name']) ?></span></td>
                        <td style="font-weight:700;color:var(--orange)">Rs <?= number_format($item['price'],0) ?></td>
                        <td>
                            <?php if (empty($thisLabels)): ?>
                                <span class="item-tag-empty">no tags</span>
                            <?php else: ?>
                                <div class="item-tag-list">
                                    <?php foreach ($thisLabels as $lbl): ?>
                                        <span class="item-tag-pill"><?= htmlspecialchars($lbl) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.4rem">
                                <button class="btn btn-outline btn-sm"
                                    onclick='openEditItem(<?= $iid ?>, <?= json_encode($item['name']) ?>, <?= json_encode($item['description']) ?>, <?= (int)$item['category_id'] ?>, <?= (float)$item['price'] ?>, <?= json_encode($thisTags) ?>)'>
                                    ✏️ Edit
                                </button>
                                <a href="?delete=<?= $iid ?>" class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this item?')">🗑</a>
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
<div class="modal-overlay" id="editItemModal">
    <div class="modal" style="text-align:left;max-width:520px;max-height:90vh;overflow-y:auto">
        <h3 style="margin-bottom:1.2rem">Edit Item</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" id="editItemId">
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="name" id="editItemName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="editItemDesc" class="form-control"></textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" id="editItemCat" class="form-control" required>
                        <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price (Rs) *</label>
                    <input type="number" name="price" id="editItemPrice" class="form-control" min="0" step="0.01" required>
                </div>
            </div>
            <div class="form-group">
                <label>New Image (optional)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>

            <div class="form-group">
                <label>Tags <span style="font-weight:400;color:var(--gray);font-size:0.78rem">(used by chatbot)</span></label>
                <?= renderTagPicker($allTags, $tagGroups, [], 'edit') ?>
            </div>

            <div class="modal-actions" style="justify-content:flex-start;margin-top:1rem">
                <button type="submit" name="edit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-outline"
                    onclick="document.getElementById('editItemModal').classList.remove('show')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditItem(id, name, desc, catId, price, tagIds) {
    document.getElementById('editItemId').value    = id;
    document.getElementById('editItemName').value  = name;
    document.getElementById('editItemDesc').value  = desc || '';
    document.getElementById('editItemCat').value   = catId;
    document.getElementById('editItemPrice').value = price;

    // Set tag checkboxes from passed array. tagIds is JSON-encoded by PHP,
    // so it's already a real JS array of integers.
    const wanted = new Set((tagIds || []).map(Number));
    document
        .querySelectorAll('#editItemModal input[name="tag_ids[]"]')
        .forEach(cb => { cb.checked = wanted.has(Number(cb.value)); });

    document.getElementById('editItemModal').classList.add('show');
}
</script>

<?php include 'footer.php'; ?>