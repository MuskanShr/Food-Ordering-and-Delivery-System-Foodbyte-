<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$orderId = (int) ($_GET['order_id'] ?? 0);
if ($orderId <= 0) { header('Location: /foodbyte/index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, (int) $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) { header('Location: /foodbyte/index.php'); exit; }

// Items
$stmt = $pdo->prepare("
    SELECT oi.*, i.name FROM order_items oi
    JOIN items i ON oi.item_id = i.id WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$itemsSubtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));

$pageTitle = 'Order Confirmed';
include __DIR__ . '/../includes/header.php';
?>
<style>
.success-wrap { max-width:600px; margin:3rem auto; padding:0 2rem; }
.success-card {
    background:white; border:1px solid var(--border);
    border-radius:16px; overflow:hidden; box-shadow:var(--shadow);
}
.success-banner {
    background:linear-gradient(135deg, #60BB46, #4FA037);
    color:white; padding:2rem; text-align:center;
}
.success-banner .check {
    width:64px; height:64px; border-radius:50%;
    background:rgba(255,255,255,0.2); margin:0 auto 0.8rem;
    display:flex; align-items:center; justify-content:center;
    font-size:2rem; font-weight:700;
}
.success-banner h2 {
    font-family:'Playfair Display',serif;
    font-size:1.5rem; margin-bottom:0.3rem;
}
.success-banner p { font-size:0.9rem; opacity:0.95; }

.order-body { padding:1.8rem; }
.summary-row {
    display:flex; justify-content:space-between;
    padding:0.5rem 0; font-size:0.9rem;
    border-bottom:1px solid var(--light-gray);
}
.summary-row:last-child { border-bottom:none; }
.summary-row.total { font-weight:800; font-size:1rem; padding-top:0.8rem; border-top:1px solid var(--border); border-bottom:none; }
.summary-row.discount { color:#2E7D32; font-weight:600; }

.meta-grid {
    display:grid; grid-template-columns:1fr 1fr; gap:0.6rem 1rem;
    background:var(--cream);
    padding:1rem 1.2rem;
    border-radius:10px;
    margin-bottom:1.2rem;
    font-size:0.85rem;
}
.meta-grid .label { color:var(--gray); }
.meta-grid .value { font-weight:600; }

.payment-status { display:inline-block; padding:0.2rem 0.6rem; border-radius:20px; font-size:0.75rem; font-weight:700; }
.ps-paid    { background:#E8F5E9; color:#2E7D32; }
.ps-pending { background:#FFF3E0; color:#E65100; }
.ps-failed  { background:#FFEBEE; color:#C62828; }

.actions { display:flex; gap:0.7rem; justify-content:center; margin-top:1.5rem; }
.btn-prim {
    background:var(--orange); color:white;
    border:none; padding:0.75rem 1.4rem; border-radius:9px;
    font-weight:700; font-size:0.9rem; text-decoration:none;
    font-family:'DM Sans',sans-serif;
}
.btn-prim:hover { background:var(--orange-dark); }
.btn-out {
    background:white; color:var(--charcoal);
    border:1.5px solid var(--border); padding:0.7rem 1.4rem;
    border-radius:9px; font-weight:600; font-size:0.9rem; text-decoration:none;
    font-family:'DM Sans',sans-serif;
}
.btn-out:hover { border-color:var(--orange); color:var(--orange); }
</style>

<div class="success-wrap">
    <div class="success-card">
        <div class="success-banner">
            <div class="check">✓</div>
            <h2>Order Confirmed</h2>
            <p>
                <?php if ($order['payment_method'] === 'cod'): ?>
                    Pay Rs <?= number_format($order['total'],0) ?> in cash on delivery.
                <?php else: ?>
                    Payment received via eSewa.
                <?php endif; ?>
            </p>
        </div>
        <div class="order-body">
            <div class="meta-grid">
                <div class="label">Order #</div>
                <div class="value">#<?= $order['id'] ?></div>

                <div class="label">Date</div>
                <div class="value"><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></div>

                <div class="label">Payment Method</div>
                <div class="value">
                    <?= $order['payment_method'] === 'esewa' ? 'eSewa' : 'Cash on Delivery' ?>
                </div>

                <div class="label">Payment Status</div>
                <div class="value">
                    <span class="payment-status ps-<?= htmlspecialchars($order['payment_status']) ?>">
                        <?= ucfirst($order['payment_status']) ?>
                    </span>
                </div>

                <div class="label">Deliver to</div>
                <div class="value"><?= htmlspecialchars($order['address']) ?></div>
            </div>

            <h3 style="font-family:'Playfair Display',serif;font-size:1rem;margin-bottom:0.8rem">Items</h3>
            <?php foreach ($items as $i): ?>
                <div class="summary-row">
                    <span><?= htmlspecialchars($i['name']) ?> × <?= (int) $i['quantity'] ?></span>
                    <span>Rs <?= number_format($i['price'] * $i['quantity'], 0) ?></span>
                </div>
            <?php endforeach; ?>

            <div class="summary-row">
                <span>Subtotal</span><span>Rs <?= number_format($itemsSubtotal,0) ?></span>
            </div>
            <?php if ($order['discount_amount'] > 0): ?>
            <div class="summary-row discount">
                <span>Discount<?= $order['voucher_code'] ? ' ('.htmlspecialchars($order['voucher_code']).')' : '' ?></span>
                <span>− Rs <?= number_format($order['discount_amount'],0) ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row">
                <span>Delivery</span><span>Rs <?= number_format($order['delivery_charge'],0) ?></span>
            </div>
            <div class="summary-row total">
                <span>Total</span><span>Rs <?= number_format($order['total'],0) ?></span>
            </div>

            <div class="actions">
                <a href="/foodbyte/menu.php" class="btn-prim">Order More</a>
                <a href="/foodbyte/profile.php" class="btn-out">View My Orders</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
