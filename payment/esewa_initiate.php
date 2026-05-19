<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payment_config.php';
requireLogin();

$orderId = (int) ($_GET['order_id'] ?? 0);
if ($orderId <= 0) { http_response_code(400); die('Bad request.'); }

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, (int) $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) { http_response_code(404); die('Order not found.'); }

if ($order['payment_method'] !== 'esewa') {
    die('This order is not configured for eSewa payment.');
}
if ($order['payment_status'] === 'paid') {
    header('Location: /foodbyte/payment/order_success.php?order_id=' . $orderId);
    exit;
}
if (!in_array($order['payment_status'], ['pending', 'failed'], true)) {
    die('Order is not eligible for payment.');
}

// If retrying, flip status back to 'pending' so the success handler can finalise.
if ($order['payment_status'] === 'failed') {
    $pdo->prepare("UPDATE orders SET payment_status = 'pending' WHERE id = ?")
        ->execute([$orderId]);
}

// Generate a fresh transaction_uuid for THIS attempt.
$transactionUuid = $orderId . '-' . bin2hex(random_bytes(4));

// Save reference + log the attempt.
$pdo->prepare("UPDATE orders SET payment_reference = ? WHERE id = ?")
    ->execute([$transactionUuid, $orderId]);

$pdo->prepare("
    INSERT INTO payment_transactions
        (order_id, gateway, transaction_uuid, amount, status, gateway_response)
    VALUES (?, 'esewa', ?, ?, 'initiated', 'Redirect to eSewa form')
")->execute([$orderId, $transactionUuid, $order['total']]);

$totalAmount   = (float) $order['total'];
$deliveryCharge= (float) $order['delivery_charge'];
$itemsNet      = max(0.0, $totalAmount - $deliveryCharge);

$fields = [
    'amount'                   => esewaAmount($itemsNet),
    'tax_amount'               => esewaAmount(0),
    'total_amount'             => esewaAmount($totalAmount),
    'transaction_uuid'         => $transactionUuid,
    'product_code'             => ESEWA_PRODUCT_CODE,
    'product_service_charge'   => esewaAmount(0),
    'product_delivery_charge'  => esewaAmount($deliveryCharge),
    'success_url'              => SITE_BASE_URL . '/payment/esewa_success.php',
    'failure_url'              => SITE_BASE_URL . '/payment/esewa_failure.php?order_id=' . $orderId,
    'signed_field_names'       => 'total_amount,transaction_uuid,product_code',
];
$fields['signature'] = esewaSign($fields, $fields['signed_field_names'], ESEWA_SECRET_KEY);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Redirecting to eSewa…</title>
    <style>
        body { font-family: 'DM Sans', sans-serif; background:#FAF7F2; color:#1A1A1A;
               display:flex; align-items:center; justify-content:center;
               min-height:100vh; margin:0; }
        .box { background:white; padding:2.5rem 3rem; border-radius:16px;
               box-shadow:0 4px 24px rgba(0,0,0,0.08); text-align:center; max-width:420px; }
        h2 { font-size:1.2rem; margin-bottom:0.5rem; }
        p  { color:#6B6B6B; font-size:0.9rem; margin-bottom:1.5rem; }
        .spinner { width:40px; height:40px; margin:0 auto 1rem;
                   border:4px solid #E8E4DF; border-top-color:#60BB46;
                   border-radius:50%; animation:spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        button { background:#60BB46; color:white; border:none;
                 padding:0.7rem 1.5rem; border-radius:9px; font-weight:700;
                 font-size:0.9rem; cursor:pointer; font-family:inherit; }
        button:hover { background:#4FA037; }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner"></div>
        <h2>Redirecting to eSewa…</h2>
        <p>You're being securely sent to eSewa to complete your payment of Rs <?= number_format($totalAmount,2) ?>.</p>
        <form id="esewa-form" method="POST" action="<?= htmlspecialchars(ESEWA_FORM_URL) ?>">
            <?php foreach ($fields as $k => $v): ?>
            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
            <?php endforeach; ?>
            <noscript>
                <button type="submit">Continue to eSewa</button>
            </noscript>
        </form>
        <script>document.getElementById('esewa-form').submit();</script>
    </div>
</body>
</html>
