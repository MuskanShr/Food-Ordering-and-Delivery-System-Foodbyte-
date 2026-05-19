<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/voucher.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// CSRF check (token is set by cart.php / checkout.php).
if (empty($_SESSION['csrf_token']) ||
    ($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid request token.']);
    exit;
}

$action = $_POST['action'] ?? 'apply';
$cart   = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    echo json_encode(['ok' => false, 'error' => 'Your cart is empty.']);
    exit;
}

// Recompute subtotal from DB prices (do not trust session prices).
$ids  = array_keys($cart);
$stmt = $pdo->prepare(
    "SELECT id, price FROM items WHERE id IN ("
    . implode(',', array_fill(0, count($ids), '?')) . ")"
);
$stmt->execute($ids);
$dbPrices = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$subtotal = 0.0;
foreach ($cart as $id => $item) {
    if (isset($dbPrices[$id])) {
        $subtotal += (float) $dbPrices[$id] * (int) $item['qty'];
    }
}

$delivery = 100;

// ---- REMOVE ---------------------------------------------------------
if ($action === 'remove') {
    unset($_SESSION['applied_voucher']);
    echo json_encode([
        'ok'       => true,
        'removed'  => true,
        'subtotal' => $subtotal,
        'delivery' => $delivery,
        'discount' => 0,
        'total'    => $subtotal + $delivery,
    ]);
    exit;
}

// ---- APPLY ----------------------------------------------------------
$code   = (string) ($_POST['code'] ?? '');
$result = validateVoucher($pdo, $code, $subtotal, (int) $_SESSION['user_id']);

if (!$result['ok']) {
    // Make sure stale voucher in session is cleared on a failure.
    unset($_SESSION['applied_voucher']);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$_SESSION['applied_voucher'] = [
    'voucher_id' => (int) $result['voucher']['id'],
    'code'       => $result['voucher']['code'],
    'discount'   => $result['discount'],
];

$total = max(0, $subtotal - $result['discount']) + $delivery;

echo json_encode([
    'ok'          => true,
    'code'        => $result['voucher']['code'],
    'description' => $result['voucher']['description'],
    'subtotal'    => $subtotal,
    'discount'    => $result['discount'],
    'delivery'    => $delivery,
    'total'       => $total,
]);
