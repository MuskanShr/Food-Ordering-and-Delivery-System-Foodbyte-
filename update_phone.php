<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$uid   = (int)$_SESSION['user_id'];
$phone = trim($_POST['phone'] ?? '');

// Allow clearing the number (empty input wipes it).
if ($phone === '') {
    $pdo->prepare("UPDATE users SET phone = NULL WHERE id = ?")->execute([$uid]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Phone number removed.'];
    header('Location: profile.php');
    exit;
}

// Length cap first, so the user gets the exact message you asked for.
if (mb_strlen($phone) > 10) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'Max amount of numbers is 10.',
    ];
    header('Location: profile.php');
    exit;
}

// Format check: at least 7 chars, optional leading '+', rest digits.
// Total length already capped at 10 above.
if (!preg_match('/^\+?[0-9]+$/', $phone) || mb_strlen($phone) < 7) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'msg'  => 'Invalid phone number. Use 7-10 digits (you may start with +).',
    ];
    header('Location: profile.php');
    exit;
}

$pdo->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$phone, $uid]);
$_SESSION['flash'] = ['type' => 'success', 'msg' => '✅ Phone number updated.'];
header('Location: profile.php');
exit;