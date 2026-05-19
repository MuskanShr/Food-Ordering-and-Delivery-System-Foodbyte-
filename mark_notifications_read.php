<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = $pdo->prepare(
    "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL"
);
$stmt->execute([(int)$_SESSION['user_id']]);

echo json_encode(['ok' => true, 'updated' => $stmt->rowCount()]);
