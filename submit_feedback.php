<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$uid = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /foodbyte/profile.php');
    exit;
}

$order_id = (int) ($_POST['order_id'] ?? 0);
$rating   = (int) ($_POST['rating']   ?? 0);
$comment  = trim($_POST['comment']    ?? '');

$errors = [];

// Validate order belongs to user and is Delivered
$stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $uid]);
$order = $stmt->fetch();

if (!$order) {
    $errors[] = "Order not found.";
} elseif ($order['status'] !== 'Delivered') {
    $errors[] = "You can only leave feedback for delivered orders.";
}

if ($rating < 1 || $rating > 5) {
    $errors[] = "Please select a rating between 1 and 5 stars.";
}

if (empty($comment)) {
    $errors[] = "Please write a comment before submitting.";
}

// Check for duplicate
if (empty($errors)) {
    $stmt = $pdo->prepare("SELECT id FROM feedback WHERE user_id = ? AND order_id = ?");
    $stmt->execute([$uid, $order_id]);
    if ($stmt->fetch()) {
        $errors[] = "You have already submitted feedback for this order.";
    }
}

if (!empty($errors)) {
    $_SESSION['feedback_errors'] = $errors;
    $_SESSION['feedback_order']  = $order_id;
    header('Location: /foodbyte/profile.php#feedback');
    exit;
}

// Insert feedback
$stmt = $pdo->prepare("INSERT INTO feedback (user_id, order_id, rating, comment) VALUES (?, ?, ?, ?)");
$stmt->execute([$uid, $order_id, $rating, $comment]);

$_SESSION['flash'] = ['type' => 'success', 'msg' => '⭐ Thank you! Your feedback has been submitted.'];
header('Location: /foodbyte/profile.php#feedback');
exit;