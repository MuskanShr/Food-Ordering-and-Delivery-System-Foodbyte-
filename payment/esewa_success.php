<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payment_config.php';
requireLogin();

function fail(string $msg, ?int $orderId = null): void {
    $url = '/foodbyte/payment/esewa_failure.php?reason=' . urlencode($msg);
    if ($orderId) $url .= '&order_id=' . $orderId;
    header('Location: ' . $url);
    exit;
}

$rawData = $_GET['data'] ?? '';
if ($rawData === '') fail('Missing eSewa response.');

$json = base64_decode($rawData, true);
if ($json === false) fail('Could not decode eSewa response.');
$payload = json_decode($json, true);
if (!is_array($payload)) fail('Malformed eSewa response.');

$orderUuid       = $payload['transaction_uuid'] ?? '';
$txCode          = $payload['transaction_code']  ?? '';
$gatewayStatus   = $payload['status']            ?? '';
$totalAmount     = $payload['total_amount']      ?? '';
$productCode     = $payload['product_code']      ?? '';
$signedFieldNames= $payload['signed_field_names']?? '';
$givenSignature  = $payload['signature']         ?? '';

if ($orderUuid === '' || $signedFieldNames === '' || $givenSignature === '') {
    fail('Incomplete eSewa response.');
}

// ---- 1. Verify signature ----
$expected = esewaSign($payload, $signedFieldNames, ESEWA_SECRET_KEY);
if (!hash_equals($expected, $givenSignature)) {
    error_log('eSewa signature mismatch. uuid=' . $orderUuid);
    fail('Payment verification failed (signature).');
}

// ---- 2. Look up our order via the transaction_uuid ----
//   transaction_uuid = "{order_id}-{random}"
$orderId = (int) explode('-', $orderUuid)[0];
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND payment_reference = ?");
$stmt->execute([$orderId, (int) $_SESSION['user_id'], $orderUuid]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) fail('Order not found for this transaction.');

// Idempotency: if already marked paid, just show success.
if ($order['payment_status'] === 'paid') {
    header('Location: /foodbyte/payment/order_success.php?order_id=' . $orderId);
    exit;
}

// ---- 3. Status API verification (defence in depth) ----
$amountClean = str_replace(',', '', $totalAmount);  // eSewa sends "1,000.0"
$statusUrl = ESEWA_STATUS_URL . '?'
           . http_build_query([
                'product_code'     => $productCode,
                'total_amount'     => $amountClean,
                'transaction_uuid' => $orderUuid,
             ]);

$ch = curl_init($statusUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$apiResponse = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr     = curl_error($ch);
curl_close($ch);

if ($apiResponse === false || $httpCode !== 200) {
    error_log("eSewa status API failed: $curlErr (HTTP $httpCode)");
    fail('Could not verify payment with eSewa.', $orderId);
}

$apiData = json_decode($apiResponse, true);
if (!is_array($apiData) || ($apiData['status'] ?? '') !== 'COMPLETE') {
    // Log + fail
    $pdo->prepare("
        INSERT INTO payment_transactions
            (order_id, gateway, transaction_uuid, gateway_ref, amount, status, gateway_response)
        VALUES (?, 'esewa', ?, ?, ?, 'failed', ?)
    ")->execute([$orderId, $orderUuid, $txCode, (float) $amountClean, $apiResponse]);

    $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([$orderId]);
    fail('eSewa reports payment incomplete.', $orderId);
}

// ---- 4. Finalise: mark paid + commit voucher ----
try {
    $pdo->beginTransaction();

    // Re-check status under lock (idempotency for racing callbacks).
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
    $stmt->execute([$orderId]);
    $orderLocked = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($orderLocked['payment_status'] === 'paid') {
        $pdo->commit();
        header('Location: /foodbyte/payment/order_success.php?order_id=' . $orderId);
        exit;
    }

    $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")
        ->execute([$orderId]);

    // Commit voucher usage now that payment is real.
    if (!empty($orderLocked['voucher_id'])) {
        $pdo->prepare("UPDATE vouchers SET usage_count = usage_count + 1 WHERE id = ?")
            ->execute([(int) $orderLocked['voucher_id']]);
    }

    $pdo->prepare("
        INSERT INTO payment_transactions
            (order_id, gateway, transaction_uuid, gateway_ref, amount, status, gateway_response)
        VALUES (?, 'esewa', ?, ?, ?, 'success', ?)
    ")->execute([$orderId, $orderUuid, $txCode, (float) $amountClean, $apiResponse]);

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('eSewa finalise error: ' . $e->getMessage());
    fail('Could not finalise your order. Contact support with order #' . $orderId, $orderId);
}

// Clear cart & voucher session now that the order is fully paid.
$_SESSION['cart'] = [];
unset($_SESSION['applied_voucher']);
$_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Payment successful! Your order is confirmed.'];

header('Location: /foodbyte/payment/order_success.php?order_id=' . $orderId);
exit;
