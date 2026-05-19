<?php
session_start();
require_once 'includes/db.php';

// Must come through OTP verification
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['otp_verified'])) {
    header('Location: forgot-password.php');
    exit;
}

$errors = [];
$email  = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if (strlen($password) < 6)
        $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm)
        $errors[] = "Passwords do not match.";

    if (empty($errors)) {
    // Block reuse of the current password — a "reset" that keeps the
    // same password defeats the purpose of resetting.
    $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $oldHash = $stmt->fetchColumn();

    if ($oldHash && password_verify($password, $oldHash)) {
        $errors[] = "New password must be different from your current password.";
    }
}

if (empty($errors)) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")
        ->execute([$hash, $email]);

   

        // Clean up
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")
            ->execute([$email]);
        unset($_SESSION['reset_email'], $_SESSION['otp_verified']);

        header('Location: password-changed.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password – FoodByte</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --orange: #F97316; --orange-dark: #EA6C0A;
            --gray: #6B7280; --border: #E5E7EB;
            --light-gray: #F3F4F6;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.18);
        }
        body { font-family: 'DM Sans', sans-serif; }
        .auth-page {
            min-height: 100vh; display: flex;
            align-items: center; justify-content: center; padding: 2rem;
            background-image: url('/foodbyte/uploads/loginbg.jpg');
            background-size: cover; background-position: center; position: relative;
        }
        .auth-page::before {
            content: ''; position: absolute; inset: 0;
            background: rgba(0,0,0,0.55); backdrop-filter: blur(2px);
        }
        .auth-box {
            position: relative; z-index: 1; background: white;
            border-radius: 20px; box-shadow: var(--shadow-lg);
            overflow: hidden; width: 100%; max-width: 420px;
        }
        .auth-header {
            background: linear-gradient(135deg, var(--orange), var(--orange-dark));
            color: white; padding: 2.5rem 2rem 2rem; text-align: center;
        }
        .auth-header .logo { font-size: 2rem; font-weight: 800; }
        .auth-header p { margin-top: 0.4rem; opacity: 0.85; font-size: 0.9rem; }
        .auth-body { padding: 2rem; }
        .auth-errors {
            background: #FFEBEE; border: 1px solid #FFCDD2; border-radius: 9px;
            padding: 0.75rem 1rem; color: #C62828; font-size: 0.87rem; margin-bottom: 1rem;
        }
        .auth-errors ul { margin-left: 1.2rem; }
        .auth-field { margin-bottom: 1.2rem; }
        .auth-field label {
            display: block; font-size: 0.83rem; font-weight: 600; color: var(--gray);
            margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .auth-field input {
            width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--border);
            border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.93rem;
            outline: none; transition: border 0.2s; background: #FAFAF9;
        }
        .auth-field input:focus { border-color: var(--orange); background: white; }
        .auth-submit {
            width: 100%; background: var(--orange); color: white; border: none;
            border-radius: 10px; padding: 0.85rem; font-size: 0.95rem; font-weight: 700;
            cursor: pointer; font-family: 'DM Sans', sans-serif; transition: all 0.2s; margin-top: 0.5rem;
        }
        .auth-submit:hover { background: var(--orange-dark); }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <div class="logo">🍴 FoodByte</div>
            <p>Set your new password</p>
        </div>
        <div class="auth-body">
            <?php if (!empty($errors)): ?>
                <div class="auth-errors">
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="auth-field">
                    <label>New Password</label>
                    <input type="password" name="password" placeholder="Min. 6 characters" required>
                </div>
                <div class="auth-field">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm" placeholder="Re-enter new password" required>
                </div>
                <button type="submit" class="auth-submit">Save New Password →</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>