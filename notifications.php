<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['unread' => 0, 'notifications' => [], 'vouchers' => []]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

// --- Unread count ---------------------------------------------------------
$cntStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL"
);
$cntStmt->execute([$userId]);
$unread = (int)$cntStmt->fetchColumn();

if ($action === 'count') {
    echo json_encode(['unread' => $unread]);
    exit;
}

// --- Full notification list (last 20) -------------------------------------
$stmt = $pdo->prepare(
    "SELECT id, order_id, type, title, message, read_at, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 20"
);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Active vouchers ------------------------------------------------------
// Show vouchers that are active, not expired, and haven't hit their usage limit.
$voucherStmt = $pdo->query(
    "SELECT code, description, discount_type, discount_value,
            min_order_amount, max_discount_amount, valid_until
     FROM vouchers
     WHERE is_active = 1
       AND (valid_from IS NULL OR valid_from <= NOW())
       AND (valid_until IS NULL OR valid_until >= NOW())
       AND (usage_limit IS NULL OR usage_count < usage_limit)
     ORDER BY created_at DESC
     LIMIT 10"
);
$vouchers = $voucherStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'unread'        => $unread,
    'notifications' => $notifications,
    'vouchers'      => $vouchers,
]);
