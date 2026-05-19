<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin(); 

$allowedTypes = ['percent', 'fixed'];


function readVoucherForm(array $allowedTypes): array
{
    $errors = [];
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
        $errors[] = 'Code must be 3–50 chars: A–Z, 0–9, _ or -.';
    }

    $type = $_POST['discount_type'] ?? '';
    if (!in_array($type, $allowedTypes, true)) $errors[] = 'Invalid discount type.';

    $value = (float) ($_POST['discount_value'] ?? 0);
    if ($value <= 0) $errors[] = 'Discount value must be greater than 0.';
    if ($type === 'percent' && $value > 100) $errors[] = 'Percent discount cannot exceed 100.';

    $minOrder = (float) ($_POST['min_order_amount'] ?? 0);
    if ($minOrder < 0) $errors[] = 'Min order amount cannot be negative.';

    $maxDisc = $_POST['max_discount_amount'] === '' || $_POST['max_discount_amount'] === null
                ? null : (float) $_POST['max_discount_amount'];
    if ($maxDisc !== null && $maxDisc < 0) $errors[] = 'Max discount cap cannot be negative.';

    $usageLimit = $_POST['usage_limit'] === '' || $_POST['usage_limit'] === null
                ? null : (int) $_POST['usage_limit'];
    if ($usageLimit !== null && $usageLimit < 1) $errors[] = 'Usage limit must be ≥ 1 (or leave blank for unlimited).';

    $validFrom  = trim($_POST['valid_from']  ?? '') ?: null;
    $validUntil = trim($_POST['valid_until'] ?? '') ?: null;
    if ($validFrom && $validUntil && strtotime($validFrom) > strtotime($validUntil)) {
        $errors[] = '"Valid from" must be before "Valid until".';
    }

    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $desc     = trim($_POST['description'] ?? '');

    return [[
        'code'                => $code,
        'description'         => $desc,
        'discount_type'       => $type,
        'discount_value'      => $value,
        'min_order_amount'    => $minOrder,
        'max_discount_amount' => $maxDisc,
        'usage_limit'         => $usageLimit,
        'valid_from'          => $validFrom,
        'valid_until'         => $validUntil,
        'is_active'           => $isActive,
    ], $errors];
}

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    [$d, $errors] = readVoucherForm($allowedTypes);
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO vouchers
                  (code, description, discount_type, discount_value,
                   min_order_amount, max_discount_amount,
                   usage_limit, valid_from, valid_until, is_active)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $d['code'], $d['description'], $d['discount_type'], $d['discount_value'],
                $d['min_order_amount'], $d['max_discount_amount'],
                $d['usage_limit'], $d['valid_from'], $d['valid_until'], $d['is_active'],
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Voucher created.'];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'A voucher with that code already exists.';
            } else {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
    if ($errors) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => '⚠️ ' . implode(' ', $errors)];
    }
    header('Location: vouchers.php'); exit;
}

// EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id = (int) ($_POST['id'] ?? 0);
    [$d, $errors] = readVoucherForm($allowedTypes);
    if (empty($errors) && $id) {
        try {
            $stmt = $pdo->prepare("
                UPDATE vouchers SET
                  code = ?, description = ?, discount_type = ?, discount_value = ?,
                  min_order_amount = ?, max_discount_amount = ?,
                  usage_limit = ?, valid_from = ?, valid_until = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $d['code'], $d['description'], $d['discount_type'], $d['discount_value'],
                $d['min_order_amount'], $d['max_discount_amount'],
                $d['usage_limit'], $d['valid_from'], $d['valid_until'], $d['is_active'],
                $id,
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Voucher updated.'];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'A voucher with that code already exists.';
            } else {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
    if ($errors) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => '⚠️ ' . implode(' ', $errors)];
    }
    header('Location: vouchers.php'); exit;
}

// TOGGLE ACTIVE
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $pdo->prepare("UPDATE vouchers SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Voucher status changed.'];
    header('Location: vouchers.php'); exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM vouchers WHERE id = ?")->execute([(int) $_GET['delete']]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Voucher deleted.'];
    header('Location: vouchers.php'); exit;
}

$vouchers = $pdo->query("SELECT * FROM vouchers ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Vouchers';
include 'header.php';
?>

<style>
    .page-grid { display:grid; grid-template-columns:450px 1fr; gap:1.5rem; align-items:start; }

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.7rem 0.9rem;
}
.form-group label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.form-group .hint {
    font-size: 0.72rem;
    color: var(--gray);
    margin-top: 0.25rem;
    line-height: 1.3;
}
.form-group .checkbox-inline {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    height: 100%;
    padding-top: 0.5rem;
    font-size: 0.88rem;
}
.card-body input.form-control,
.card-body select.form-control {
    max-width: 200px;
}
@media (max-width: 1100px) {
    .page-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .form-grid { grid-template-columns: 1fr; }
}

.muted { 
    color:var(--gray); 
    font-size:0.82rem; 
}
.status-on  { 
    color:#2E7D32; 
    font-weight:700; 
}
.status-off { 
    color:#C62828; 
    font-weight:700; }
.code-pill {
    display:inline-block; 
    background:var(--cream);
    border:1px solid var(--border); 
    border-radius:6px;
    padding:0.15rem 0.5rem; 
    font-family:monospace;
    font-size:0.85rem; 
    font-weight:700;
}
.form-group .hint { 
    font-size:0.75rem; 
    color:var(--gray); 
    margin-top:0.2rem; }
</style>

<div class="page-grid">
    <!-- ADD FORM -->
    <div class="card">
        <div class="card-header">Create New Voucher</div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Code *</label>
                    <input type="text" name="code" class="form-control" placeholder="WELCOME10" required style="text-transform:uppercase">
                    <div class="hint">3–50 characters: letters, numbers, _ or -</div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" placeholder="10% off your first order">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Type *</label>
                        <select name="discount_type" class="form-control" required>
                            <option value="fixed">Fixed (Rs)</option>
                            <option value="percent">Percent (%)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Value *</label>
                        <input type="number" step="0.01" min="0" name="discount_value" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Min Order (Rs)</label>
                        <input type="number" step="0.01" min="0" name="min_order_amount" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label>Max Discount Cap (Rs)</label>
                        <input type="number" step="0.01" min="0" name="max_discount_amount" class="form-control" placeholder="No cap">
                        <div class="hint">For percent type</div>
                    </div>
                    <div class="form-group">
                        <label>Usage Limit</label>
                        <input type="number" min="1" name="usage_limit" class="form-control" placeholder="Unlimited">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:500">
                            <input type="checkbox" name="is_active" checked> Active
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Valid From</label>
                        <input type="datetime-local" name="valid_from" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Valid Until</label>
                        <input type="datetime-local" name="valid_until" class="form-control">
                    </div>
                </div>
                <button type="submit" name="add" class="btn btn-primary">+ Create Voucher</button>
            </form>
        </div>
    </div>

    <!-- LIST -->
    <div class="card">
        <div class="card-header">All Vouchers <span class="muted">(<?= count($vouchers) ?>)</span></div>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Min Order</th>
                        <th>Used / Limit</th>
                        <th>Valid Until</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($vouchers)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--gray);padding:2rem">No vouchers yet</td></tr>
                <?php else: ?>
                    <?php foreach($vouchers as $v):
                        $disc = $v['discount_type'] === 'percent'
                                ? rtrim(rtrim(number_format($v['discount_value'],2), '0'), '.') . '%'
                                : 'Rs ' . number_format($v['discount_value'],0);
                        if ($v['discount_type'] === 'percent' && $v['max_discount_amount']) {
                            $disc .= ' (max Rs '. number_format($v['max_discount_amount'],0) .')';
                        }
                        $expired = !empty($v['valid_until']) && strtotime($v['valid_until']) < time();
                        $exhausted = $v['usage_limit'] !== null && (int)$v['usage_count'] >= (int)$v['usage_limit'];
                    ?>
                    <tr>
                        <td>
                            <span class="code-pill"><?= htmlspecialchars($v['code']) ?></span>
                            <?php if($v['description']): ?>
                                <div class="muted" style="margin-top:0.2rem"><?= htmlspecialchars($v['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $disc ?></td>
                        <td><?= $v['min_order_amount'] > 0 ? 'Rs ' . number_format($v['min_order_amount'],0) : '—' ?></td>
                        <td>
                            <?= (int)$v['usage_count'] ?> / <?= $v['usage_limit'] === null ? '∞' : (int)$v['usage_limit'] ?>
                            <?php if($exhausted): ?> <span class="muted">(full)</span><?php endif; ?>
                        </td>
                        <td style="font-size:0.83rem">
                            <?php if($v['valid_until']): ?>
                                <?= date('Y-m-d', strtotime($v['valid_until'])) ?>
                                <?php if($expired): ?><div style="color:#C62828;font-size:0.78rem">Expired</div><?php endif; ?>
                            <?php else: ?>
                                <span class="muted">No expiry</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($v['is_active']): ?>
                                <span class="status-on">Active</span>
                            <?php else: ?>
                                <span class="status-off">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
                                <button class="btn btn-outline btn-sm"
                                    onclick='openEdit(<?= json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    ✏️ Edit
                                </button>
                                <a href="?toggle=<?= $v['id'] ?>" class="btn btn-outline btn-sm">
                                    <?= $v['is_active'] ? '⏸ Disable' : '▶ Enable' ?>
                                </a>
                                <a href="?delete=<?= $v['id'] ?>" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Delete this voucher? Orders that used it will keep the code as a snapshot.')">🗑</a>
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
    <div class="modal" style="text-align:left;max-width:520px">
        <h3 style="margin-bottom:1rem">Edit Voucher</h3>
        <form method="POST">
            <input type="hidden" name="id" id="e_id">
            <div class="form-group">
                <label>Code *</label>
                <input type="text" name="code" id="e_code" class="form-control" required style="text-transform:uppercase">
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" id="e_description" class="form-control">
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Type *</label>
                    <select name="discount_type" id="e_discount_type" class="form-control" required>
                        <option value="fixed">Fixed (Rs)</option>
                        <option value="percent">Percent (%)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Value *</label>
                    <input type="number" step="0.01" min="0" name="discount_value" id="e_discount_value" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Min Order (Rs)</label>
                    <input type="number" step="0.01" min="0" name="min_order_amount" id="e_min_order_amount" class="form-control">
                </div>
                <div class="form-group">
                    <label>Max Discount Cap (Rs)</label>
                    <input type="number" step="0.01" min="0" name="max_discount_amount" id="e_max_discount_amount" class="form-control">
                </div>
                <div class="form-group">
                    <label>Usage Limit</label>
                    <input type="number" min="1" name="usage_limit" id="e_usage_limit" class="form-control">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label style="display:flex;align-items:center;gap:0.5rem;font-weight:500">
                        <input type="checkbox" name="is_active" id="e_is_active"> Active
                    </label>
                </div>
                <div class="form-group">
                    <label>Valid From</label>
                    <input type="datetime-local" name="valid_from" id="e_valid_from" class="form-control">
                </div>
                <div class="form-group">
                    <label>Valid Until</label>
                    <input type="datetime-local" name="valid_until" id="e_valid_until" class="form-control">
                </div>
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
function toLocal(dt) {
    // Convert "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DDTHH:MM" for datetime-local input
    if (!dt) return '';
    return dt.replace(' ', 'T').slice(0, 16);
}
function openEdit(v) {
    document.getElementById('e_id').value                  = v.id;
    document.getElementById('e_code').value                = v.code || '';
    document.getElementById('e_description').value         = v.description || '';
    document.getElementById('e_discount_type').value       = v.discount_type;
    document.getElementById('e_discount_value').value      = v.discount_value;
    document.getElementById('e_min_order_amount').value    = v.min_order_amount;
    document.getElementById('e_max_discount_amount').value = v.max_discount_amount || '';
    document.getElementById('e_usage_limit').value         = v.usage_limit || '';
    document.getElementById('e_valid_from').value          = toLocal(v.valid_from);
    document.getElementById('e_valid_until').value         = toLocal(v.valid_until);
    document.getElementById('e_is_active').checked         = Number(v.is_active) === 1;
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php include 'footer.php'; ?>
