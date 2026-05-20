<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

$allowed_statuses        = ['Pending','Preparing','Out for Delivery','Delivered'];
$allowed_payment_status  = ['pending','paid','failed'];

// UPDATE OPERATIONAL STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['status'];
    if (in_array($status, $allowed_statuses)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $oid]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Order status updated.'];
    }
    header('Location: orders.php' . ($_GET['view'] ?? '' ? '?view=' . (int)$_GET['view'] : '')); exit;
}

// MARK COD AS PAID (cash collected on delivery)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $oid = (int)$_POST['order_id'];
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$oid]);
    $o = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($o && $o['payment_method'] === 'cod' && $o['payment_status'] === 'pending') {
        $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=?")->execute([$oid]);
        $pdo->prepare("
            INSERT INTO payment_transactions (order_id, gateway, amount, status, gateway_response)
            VALUES (?, 'cod', ?, 'success', 'Marked paid by admin')
        ")->execute([$oid, $o['total']]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Order marked as paid.'];
    }
    header('Location: orders.php?view=' . $oid); exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Order deleted.'];
    header('Location: orders.php'); exit;
}

// VIEW SINGLE ORDER
$viewOrder = null;
$viewItems = [];
$viewTxns  = [];
if (isset($_GET['view'])) {
    $id = (int)$_GET['view'];
    $stmt = $pdo->prepare("SELECT o.*, u.username, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$id]);
    $viewOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($viewOrder) {
        $stmt = $pdo->prepare("SELECT oi.*, i.name FROM order_items oi JOIN items i ON oi.item_id = i.id WHERE oi.order_id = ?");
        $stmt->execute([$id]);
        $viewItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE order_id = ? ORDER BY created_at ASC");
        $stmt->execute([$id]);
        $viewTxns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// FILTER
$filterStatus  = $_GET['status']  ?? '';
$filterPayment = $_GET['payment'] ?? '';
$where = []; $params = [];
if ($filterStatus && in_array($filterStatus, $allowed_statuses)) {
    $where[] = "o.status = ?"; $params[] = $filterStatus;
}
if ($filterPayment && in_array($filterPayment, $allowed_payment_status)) {
    $where[] = "o.payment_status = ?"; $params[] = $filterPayment;
}
$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $pdo->prepare("
    SELECT o.*, u.username,
    GROUP_CONCAT(i.name ORDER BY i.name SEPARATOR ', ') as item_names
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN items i ON oi.item_id = i.id
    $whereClause
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$badgeMap = [
    'Pending'          => 'badge-pending',
    'Preparing'        => 'badge-preparing',
    'Out for Delivery' => 'badge-delivery',
    'Delivered'        => 'badge-delivered',
];

$pageTitle = 'Orders';
include 'header.php';
?>

<style>
.filter-bar { display:flex; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap; align-items:center; }
.filter-bar .filter-label { font-size:0.78rem; text-transform:uppercase; letter-spacing:0.8px; color:var(--gray); margin-right:0.3rem; }
.filter-btn {
    padding:0.4rem 1rem; border-radius:20px;
    border:1.5px solid var(--border);
    background:white; font-family:'DM Sans',sans-serif;
    font-size:0.83rem; font-weight:600; cursor:pointer;
    text-decoration:none; color:var(--charcoal); transition:all 0.2s;
}
.filter-btn:hover, .filter-btn.active { background:var(--orange); color:white; border-color:var(--orange); }

.detail-panel {
    background:white; border-radius:14px;
    border:1px solid var(--border); box-shadow:var(--shadow);
    margin-bottom:1.5rem; overflow:hidden;
}
.detail-panel .panel-header {
    background:var(--charcoal); color:white;
    padding:1rem 1.5rem;
    display:flex; align-items:center; justify-content:space-between;
}
.detail-panel .panel-header h3 { font-family:'Playfair Display',serif; font-size:1.1rem; }
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:0; }
.detail-section { padding:1.5rem; border-right:1px solid var(--border); }
.detail-section:last-child { border-right:none; }
.detail-section h4 { font-size:0.8rem; text-transform:uppercase; letter-spacing:1px; color:var(--gray); margin-bottom:1rem; }
.detail-row { display:flex; gap:0.5rem; margin-bottom:0.5rem; font-size:0.9rem; }
.detail-label { font-weight:600; color:var(--charcoal); min-width:90px; }
.detail-value { color:var(--gray); }
.code-pill {
    display:inline-block; background:var(--cream);
    border:1px solid var(--border); border-radius:6px;
    padding:0.1rem 0.45rem; font-family:monospace;
    font-size:0.82rem; font-weight:700;
}

.payment-status { display:inline-block; padding:0.18rem 0.55rem; border-radius:20px; font-size:0.72rem; font-weight:700; text-transform:uppercase; }
.ps-paid    { background:#E8F5E9; color:#2E7D32; }
.ps-pending { background:#FFF3E0; color:#E65100; }
.ps-failed  { background:#FFEBEE; color:#C62828; }

.method-pill { display:inline-block; padding:0.15rem 0.5rem; border-radius:6px; font-size:0.74rem; font-weight:700; }
.method-cod   { background:#FFFAF0; color:#8D6E00; border:1px solid #FFE082; }
.method-esewa { background:#E8F5E9; color:#2E7D32; border:1px solid #A5D6A7; }

.warn-banner {
    background:#FFF3E0; border:1px solid #FFB74D;
    color:#E65100; padding:0.7rem 1rem;
    border-radius:9px; font-size:0.85rem; font-weight:600;
    margin:1rem 1.5rem;
}

.order-items-table { width:100%; border-collapse:collapse; margin:0; }
.order-items-table th { padding:0.6rem 1.5rem; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.8px; color:var(--gray); background:var(--cream); border-bottom:1px solid var(--border); text-align:left; }
.order-items-table td { padding:0.75rem 1.5rem; border-bottom:1px solid var(--light-gray); font-size:0.88rem; }
.order-items-table tr:last-child td { border-bottom:none; }
.order-total-row { padding:1rem 1.5rem; display:flex; justify-content:space-between; background:var(--cream); font-weight:700; font-size:0.95rem; }
.order-total-row.discount { color:#2E7D32; background:#F1F8E9; }

.txn-table { width:100%; border-collapse:collapse; }
.txn-table th, .txn-table td { padding:0.55rem 1rem; font-size:0.82rem; border-bottom:1px solid var(--light-gray); }
.txn-table th { background:var(--cream); color:var(--gray); text-transform:uppercase; font-size:0.72rem; letter-spacing:0.7px; text-align:left; }
.txn-table td.resp { font-family:monospace; font-size:0.75rem; max-width:280px; word-break:break-all; color:var(--gray); }
</style>

<!-- FILTER BAR -->
<div class="filter-bar">
    <span class="filter-label">Status:</span>
    <a href="orders.php<?= $filterPayment ? '?payment='.urlencode($filterPayment) : '' ?>" class="filter-btn <?= !$filterStatus ? 'active' : '' ?>">All</a>
    <?php foreach($allowed_statuses as $s): ?>
    <a href="?status=<?= urlencode($s) ?><?= $filterPayment ? '&payment='.urlencode($filterPayment) : '' ?>" class="filter-btn <?= $filterStatus === $s ? 'active' : '' ?>"><?= $s ?></a>
    <?php endforeach; ?>
</div>
<div class="filter-bar">
    <span class="filter-label">Payment:</span>
    <a href="orders.php<?= $filterStatus ? '?status='.urlencode($filterStatus) : '' ?>" class="filter-btn <?= !$filterPayment ? 'active' : '' ?>">All</a>
    <?php foreach($allowed_payment_status as $ps): ?>
    <a href="?payment=<?= urlencode($ps) ?><?= $filterStatus ? '&status='.urlencode($filterStatus) : '' ?>" class="filter-btn <?= $filterPayment === $ps ? 'active' : '' ?>"><?= ucfirst($ps) ?></a>
    <?php endforeach; ?>
</div>

<?php if($viewOrder):
    $itemsSubtotal = 0;
    foreach ($viewItems as $vi) { $itemsSubtotal += $vi['price'] * $vi['quantity']; }
    $awaitingPayment = $viewOrder['payment_method'] === 'esewa' && $viewOrder['payment_status'] === 'pending';
?>
<div class="detail-panel">
    <div class="panel-header">
        <h3>Order #<?= $viewOrder['id'] ?></h3>
        <a href="orders.php" class="btn btn-outline btn-sm" style="color:white;border-color:rgba(255,255,255,0.3)">← Back</a>
    </div>

    <?php if ($awaitingPayment): ?>
    <div class="warn-banner">
        ⚠ This eSewa order is <strong>awaiting payment</strong>. Do not start preparing until payment is confirmed.
    </div>
    <?php endif; ?>

    <div class="detail-grid">
        <div class="detail-section">
            <h4>Customer Details</h4>
            <div class="detail-row"><span class="detail-label">Name:</span><span class="detail-value"><?= htmlspecialchars($viewOrder['name']) ?></span></div>
            <div class="detail-row"><span class="detail-label">Email:</span><span class="detail-value"><?= htmlspecialchars($viewOrder['email']) ?></span></div>
            <div class="detail-row"><span class="detail-label">Phone:</span><span class="detail-value"><?= htmlspecialchars($viewOrder['phone']) ?></span></div>
            <div class="detail-row"><span class="detail-label">Address:</span><span class="detail-value"><?= htmlspecialchars($viewOrder['address']) ?></span></div>
        </div>
        <div class="detail-section">
            <h4>Order & Payment</h4>
            <div class="detail-row"><span class="detail-label">Date:</span><span class="detail-value"><?= date('Y-m-d H:i', strtotime($viewOrder['created_at'])) ?></span></div>
            <div class="detail-row"><span class="detail-label">Status:</span>
                <span class="badge <?= $badgeMap[$viewOrder['status']] ?? '' ?>"><?= $viewOrder['status'] ?></span>
            </div>
            <div class="detail-row"><span class="detail-label">Method:</span>
                <span class="method-pill method-<?= $viewOrder['payment_method'] ?>">
                    <?= $viewOrder['payment_method'] === 'esewa' ? 'eSewa' : 'COD' ?>
                </span>
            </div>
            <div class="detail-row"><span class="detail-label">Payment:</span>
                <span class="payment-status ps-<?= $viewOrder['payment_status'] ?>"><?= $viewOrder['payment_status'] ?></span>
            </div>
            <?php if(!empty($viewOrder['payment_reference'])): ?>
            <div class="detail-row"><span class="detail-label">Ref:</span>
                <span class="detail-value" style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($viewOrder['payment_reference']) ?></span>
            </div>
            <?php endif; ?>
            <?php if(!empty($viewOrder['voucher_code'])): ?>
            <div class="detail-row"><span class="detail-label">Voucher:</span>
                <span class="detail-value">
                    <span class="code-pill"><?= htmlspecialchars($viewOrder['voucher_code']) ?></span>
                    <span style="color:#2E7D32;font-weight:600">− Rs <?= number_format($viewOrder['discount_amount'],0) ?></span>
                </span>
            </div>
            <?php endif; ?>
            <div class="detail-row"><span class="detail-label">Total:</span>
                <span class="detail-value" style="font-weight:700;color:var(--orange)">Rs <?= number_format($viewOrder['total'],0) ?></span>
            </div>

            <form method="POST" style="margin-top:1rem;display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="order_id" value="<?= $viewOrder['id'] ?>">
                <select name="status" class="form-control" style="padding:0.3rem 0.5rem;font-size:0.82rem;width:160px">
                    <?php foreach($allowed_statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $viewOrder['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update Status</button>
            </form>

            <?php if($viewOrder['payment_method']==='cod' && $viewOrder['payment_status']==='pending'): ?>
            <form method="POST" style="margin-top:0.6rem">
                <input type="hidden" name="order_id" value="<?= $viewOrder['id'] ?>">
                <button type="submit" name="mark_paid" class="btn btn-success btn-sm"
                        onclick="return confirm('Confirm cash collected for this order?')">
                    💵 Mark Paid (Cash Received)
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div style="border-top:1px solid var(--border)">
        <div style="padding:1rem 1.5rem;font-size:0.8rem;text-transform:uppercase;letter-spacing:1px;color:var(--gray);background:var(--cream)">Order Items</div>
        <table class="order-items-table">
            <thead><tr><th>Item</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php foreach($viewItems as $vi): ?>
            <tr>
                <td><?= htmlspecialchars($vi['name']) ?></td>
                <td><?= $vi['quantity'] ?></td>
                <td>Rs <?= number_format($vi['price'],0) ?></td>
                <td>Rs <?= number_format($vi['price'] * $vi['quantity'],0) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="order-total-row"><span>Subtotal</span><span>Rs <?= number_format($itemsSubtotal,0) ?></span></div>
        <?php if($viewOrder['discount_amount'] > 0): ?>
        <div class="order-total-row discount">
            <span>Discount<?= !empty($viewOrder['voucher_code']) ? ' ('.htmlspecialchars($viewOrder['voucher_code']).')' : '' ?></span>
            <span>− Rs <?= number_format($viewOrder['discount_amount'],0) ?></span>
        </div>
        <?php endif; ?>
        <div class="order-total-row"><span>Delivery</span><span>Rs <?= number_format($viewOrder['delivery_charge'],0) ?></span></div>
        <div class="order-total-row" style="color:var(--orange)"><span>Total</span><span>Rs <?= number_format($viewOrder['total'],0) ?></span></div>
    </div>

    <?php if (!empty($viewTxns)): ?>
    <div style="border-top:1px solid var(--border)">
        <div style="padding:1rem 1.5rem;font-size:0.8rem;text-transform:uppercase;letter-spacing:1px;color:var(--gray);background:var(--cream)">Payment Transactions</div>
        <table class="txn-table">
            <thead>
                <tr>
                    <th>Time</th><th>Gateway</th><th>UUID / Ref</th>
                    <th>Amount</th><th>Status</th><th>Response</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($viewTxns as $t): ?>
                <tr>
                    <td><?= date('Y-m-d H:i:s', strtotime($t['created_at'])) ?></td>
                    <td><?= htmlspecialchars($t['gateway']) ?></td>
                    <td style="font-family:monospace;font-size:0.78rem">
                        <?= htmlspecialchars($t['transaction_uuid'] ?: '-') ?>
                        <?php if($t['gateway_ref']): ?><br><small><?= htmlspecialchars($t['gateway_ref']) ?></small><?php endif; ?>
                    </td>
                    <td>Rs <?= number_format($t['amount'],0) ?></td>
                    <td><span class="payment-status ps-<?= $t['status']==='success'?'paid':($t['status']==='failed'?'failed':'pending') ?>"><?= htmlspecialchars($t['status']) ?></span></td>
                    <td class="resp"><?= htmlspecialchars(mb_strimwidth($t['gateway_response'] ?? '', 0, 200, '…')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ORDERS TABLE -->
<div class="card">
    <div class="card-header">
        All Orders <span style="font-size:0.8rem;font-weight:400;color:var(--gray)">(<?= count($orders) ?>)</span>
    </div>
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order ID</th><th>Customer</th><th>Items</th>
                    <th>Total</th><th>Method</th><th>Payment</th>
                    <th>Voucher</th><th>Status</th><th>Date</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($orders)): ?>
                <tr><td colspan="10" style="text-align:center;color:var(--gray);padding:2rem">No orders found</td></tr>
            <?php else: ?>
                <?php foreach($orders as $order): ?>
                <tr>
                    <td><strong>#<?= $order['id'] ?></strong></td>
                    <td><?= htmlspecialchars($order['username']) ?></td>
                    <td style="max-width:160px;font-size:0.83rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($order['item_names']) ?></td>
                    <td style="font-weight:700;color:var(--orange)">Rs <?= number_format($order['total'],0) ?></td>
                    <td><span class="method-pill method-<?= $order['payment_method'] ?>"><?= $order['payment_method']==='esewa'?'eSewa':'COD' ?></span></td>
                    <td><span class="payment-status ps-<?= $order['payment_status'] ?>"><?= $order['payment_status'] ?></span></td>
                    <td style="font-size:0.82rem">
                        <?php if(!empty($order['voucher_code'])): ?>
                            <span class="code-pill"><?= htmlspecialchars($order['voucher_code']) ?></span>
                        <?php else: ?>
                            <span style="color:var(--gray)">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $badgeMap[$order['status']] ?? '' ?>"><?= $order['status'] ?></span></td>
                    <td style="font-size:0.83rem"><?= date('Y-m-d', strtotime($order['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
                            <a href="?view=<?= $order['id'] ?>" class="btn btn-outline btn-sm">👁 View</a>
                            <a href="?delete=<?= $order['id'] ?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete order #<?= $order['id'] ?>?')">🗑</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Leaflet map for admin order view -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php if (isset($viewOrder) && $viewOrder && !empty($viewOrder['latitude']) && !empty($viewOrder['longitude'])): ?>
<script>
(function(){
    var lat = <?= (float)$viewOrder['latitude'] ?>;
    var lng = <?= (float)$viewOrder['longitude'] ?>;
    var mapEl = document.getElementById('admin-delivery-map');
    if (!mapEl) return;
    var map = L.map('admin-delivery-map').setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data OpenStreetMap contributors', maxZoom: 19
    }).addTo(map);
    var marker = L.marker([lat, lng]).addTo(map);
    marker.bindPopup('<b>Delivery Location</b><br><?= htmlspecialchars(addslashes($viewOrder['address'])) ?>').openPopup();
})();
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>