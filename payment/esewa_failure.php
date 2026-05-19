<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$orderId = (int) ($_GET['order_id'] ?? 0);
$reason  = trim($_GET['reason'] ?? '') ?: 'Payment was not completed.';

$order = null;
if ($orderId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, (int) $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order && $order['payment_status'] === 'pending') {
        $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")
            ->execute([$orderId]);

        $pdo->prepare("
            INSERT INTO payment_transactions
                (order_id, gateway, transaction_uuid, amount, status, gateway_response)
            VALUES (?, 'esewa', ?, ?, 'failed', ?)
        ")->execute([
            $orderId,
            $order['payment_reference'],
            $order['total'],
            'User cancelled or eSewa reported failure. Reason: ' . $reason,
        ]);
    }
}

$pageTitle = 'Payment Failed';
include __DIR__ . '/../includes/header.php';
?>
<style>
.fail-wrap { max-width:520px; margin:4rem auto; padding:0 2rem; }
.fail-card {
    background:white; border:1px solid var(--border);
    border-radius:16px; padding:2.5rem; text-align:center;
    box-shadow:var(--shadow);
}
.fail-icon {
    width:72px; height:72px; border-radius:50%;
    background:#FFEBEE; color:#C62828;
    display:flex; align-items:center; justify-content:center;
    font-size:2.2rem; font-weight:700;
    margin:0 auto 1rem;
}
.fail-card h2 {
    font-family:'Playfair Display',serif;
    font-size:1.5rem; margin-bottom:0.5rem;
}
.fail-card p { color:var(--gray); margin-bottom:1.5rem; font-size:0.92rem; }
.fail-card .reason {
    background:#FFF8E1; border:1px solid #FFE082;
    color:#8D6E00; padding:0.7rem 1rem;
    border-radius:9px; font-size:0.85rem;
    margin-bottom:1.5rem;
}
.fail-actions { display:flex; gap:0.7rem; justify-content:center; flex-wrap:wrap; }
.btn-retry {
    background:#60BB46; color:white;
    border:none; padding:0.75rem 1.4rem;
    border-radius:9px; font-weight:700; font-size:0.9rem;
    text-decoration:none; cursor:pointer;
    font-family:'DM Sans',sans-serif;
}
.btn-retry:hover { background:#4FA037; }
.btn-back {
    background:white; color:var(--charcoal);
    border:1.5px solid var(--border); padding:0.7rem 1.4rem;
    border-radius:9px; font-weight:600; font-size:0.9rem;
    text-decoration:none;
    font-family:'DM Sans',sans-serif;
}
.btn-back:hover { border-color:var(--orange); color:var(--orange); }
</style>

<div class="fail-wrap">
    <div class="fail-card">
        <div class="fail-icon">✕</div>
        <h2>Payment Failed</h2>
        <p>Your order was not completed.</p>
        <div class="reason"><?= htmlspecialchars($reason) ?></div>
        <?php if ($order): ?>
            <p style="font-size:0.85rem">Order #<?= $orderId ?> — Rs <?= number_format($order['total'],0) ?></p>
        <?php endif; ?>
        <div class="fail-actions">
            <?php if ($order && $order['payment_status'] === 'failed'): ?>
                <a href="/foodbyte/payment/esewa_initiate.php?order_id=<?= $orderId ?>" class="btn-retry">Retry Payment</a>
            <?php endif; ?>
            <a href="/foodbyte/menu.php" class="btn-back">Back to Menu</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
