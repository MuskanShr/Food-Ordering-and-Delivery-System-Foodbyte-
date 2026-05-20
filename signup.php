<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/PHPMailer/src/PHPMailer.php';
require_once 'includes/PHPMailer/src/SMTP.php';
require_once 'includes/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const SIGNUP_CODE_TTL_MIN = 10;   // verification code lifetime (minutes)

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    // ---- Validate ---------------------------------------------------
    if (!$username)
        $errors[] = "Username is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Valid email is required.";
    if (strlen($password) < 6)
        $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm)
        $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        // ---- Conflict checks against the REAL users table ----------
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Username or email already taken.";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM pending_signups
                               WHERE username = ? AND email <> ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Username is being verified by another signup. Try another.";
        }
    }

    if (empty($errors)) {
        // ---- Generate code & store pending row ---------------------
        // random_int is cryptographically secure; rand() is NOT.
        $code        = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash    = hash('sha256', $code);
        $pwHash      = password_hash($password, PASSWORD_DEFAULT);
        $expires     = date('Y-m-d H:i:s', time() + SIGNUP_CODE_TTL_MIN * 60);

        // REPLACE handles "same email retrying" cleanly: any old row
        // (with its old code/attempts) is wiped and we start fresh.
        $stmt = $pdo->prepare(
            "REPLACE INTO pending_signups
               (email, username, password_hash, code_hash, expires_at, attempts, last_sent_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())"
        );
        $stmt->execute([$email, $username, $pwHash, $codeHash, $expires]);

        // ---- Send the email ----------------------------------------
        if (sendVerificationEmail($email, $username, $code)) {
            $_SESSION['pending_email'] = $email;
            header('Location: verify-signup.php');
            exit;
        } else {
            // Roll back the pending row so a stuck pending row doesn't
            // block retry
            $pdo->prepare("DELETE FROM pending_signups WHERE email = ?")->execute([$email]);
            $errors[] = "Could not send verification email. Please try again.";
        }
    }
}

/**
 * Send a 6-digit verification code via Mailtrap SMTP.
 * Returns true on success, false on failure (logged via error_log).
 */
function sendVerificationEmail(string $email, string $username, string $code): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'd8290f9167fd05';
        $mail->Password   = '83dcf50c762890';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 2525;

        $mail->setFrom('noreply@foodbyte.com', 'FoodByte');
        $mail->addAddress($email, $username);
        $mail->Subject = 'Verify your FoodByte account';
        $mail->isHTML(true);

        $safeName = htmlspecialchars($username);
        $mail->Body = "
            <div style='font-family:DM Sans,sans-serif;max-width:400px;margin:auto;padding:2rem;'>
                <h2 style='color:#F97316'>🍴 FoodByte</h2>
                <p>Hi <strong>{$safeName}</strong>,</p>
                <p>Welcome! Use this code to verify your account:</p>
                <div style='font-size:2.5rem;font-weight:800;color:#F97316;
                            letter-spacing:8px;text-align:center;padding:1rem;
                            background:#FFF7ED;border-radius:12px;margin:1rem 0'>
                    {$code}
                </div>
                <p style='color:#6B7280;font-size:0.85rem'>
                    This code expires in <strong>10 minutes</strong>.<br>
                    If you didn't sign up for FoodByte, ignore this email.
                </p>
            </div>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('signup verify mail failed: ' . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign Up – FoodByte</title>
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
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem; background-size: cover; background-position: center; position: relative;
        }
        .auth-page::before {
            content: ''; position: absolute; inset: 0;
            background: rgba(0,0,0,0.55); backdrop-filter: blur(2px);
        }
        .auth-box {
            position: relative; z-index: 1; background: white; border-radius: 20px;
            box-shadow: var(--shadow-lg); overflow: hidden;
            width: 100%; max-width: 420px;
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
            display: block; font-size: 0.83rem; font-weight: 600;
            color: var(--gray); margin-bottom: 0.4rem;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .auth-field input {
            width: 100%; padding: 0.75rem 1rem;
            border: 1.5px solid var(--border); border-radius: 10px;
            font-family: 'DM Sans', sans-serif; font-size: 0.93rem;
            outline: none; transition: border 0.2s; background: #FAFAF9;
        }
        .auth-field input:focus { border-color: var(--orange); background: white; }
        .auth-submit {
            width: 100%; background: var(--orange); color: white;
            border: none; border-radius: 10px; padding: 0.85rem;
            font-size: 0.95rem; font-weight: 700; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: all 0.2s; margin-top: 0.5rem;
        }
        .auth-submit:hover { background: var(--orange-dark); }
        .auth-footer {
            text-align: center; padding: 1rem 2rem 1.5rem;
            font-size: 0.87rem; color: var(--gray);
            border-top: 1px solid var(--light-gray);
        }
        .auth-footer a { color: var(--orange); text-decoration: none; font-weight: 600; }
        .auth-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <div class="logo">🍴 FoodByte</div>
            <p>Create your account today</p>
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
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Choose a username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="auth-field">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Enter your email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="auth-field">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Min. 6 characters" required>
                </div>
                <div class="auth-field">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm" placeholder="Re-enter your password" required>
                </div>
                <button type="submit" class="auth-submit">Continue →</button>
            </form>
        </div>
        <div class="auth-footer">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>
</div>
</body>
</html>