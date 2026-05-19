<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot-password.php');
    exit;
}

$error = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $otp = trim($_POST['otp']); 

$stmt = $pdo->prepare("SELECT * FROM password_resets 
                        WHERE email = ? AND CAST(otp AS CHAR) = ? 
                        AND expires_at > NOW() 
                        ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$email, (string)$otp]);
    $reset = $stmt->fetch();

    if ($reset) {
        // OTP valid! Let them reset password
        $_SESSION['otp_verified'] = true;
        header('Location: reset-password.php');
        exit;
    } else {
        $error = "Invalid or expired OTP. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify OTP – FoodByte</title>
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
        .auth-error {
            background: #FFEBEE; border: 1px solid #FFCDD2; border-radius: 9px;
            padding: 0.75rem 1rem; color: #C62828; font-size: 0.87rem; margin-bottom: 1rem;
        }
        .otp-hint {
            background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 9px;
            padding: 0.75rem 1rem; color: #92400E; font-size: 0.85rem; margin-bottom: 1.2rem;
        }
        .auth-field { margin-bottom: 1.2rem; }
        .auth-field label {
            display: block; font-size: 0.83rem; font-weight: 600; color: var(--gray);
            margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .otp-input {
            width: 100%; padding: 1rem; border: 1.5px solid var(--border);
            border-radius: 10px; font-family: 'DM Sans', sans-serif;
            font-size: 1.8rem; font-weight: 700; text-align: center;
            letter-spacing: 8px; outline: none; transition: border 0.2s; background: #FAFAF9;
        }
        .otp-input:focus { border-color: var(--orange); background: white; }
        .auth-submit {
            width: 100%; background: var(--orange); color: white; border: none;
            border-radius: 10px; padding: 0.85rem; font-size: 0.95rem; font-weight: 700;
            cursor: pointer; font-family: 'DM Sans', sans-serif; transition: all 0.2s; margin-top: 0.5rem;
        }
        .auth-submit:hover { background: var(--orange-dark); }
        .auth-footer {
            text-align: center; padding: 1rem 2rem 1.5rem; font-size: 0.87rem;
            color: var(--gray); border-top: 1px solid var(--light-gray);
        }
        .auth-footer a { color: var(--orange); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <div class="logo">🍴 FoodByte</div>
            <p>Enter the OTP sent to your email</p>
        </div>
        <div class="auth-body">
            <?php if ($error): ?>
                <div class="auth-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <div class="otp-hint">
                📧 OTP sent to <strong><?= htmlspecialchars($email) ?></strong><br>
                ⏱️ Expires in 10 minutes
            </div>
            <form method="POST">
                <div class="auth-field">
                    <label>Enter OTP</label>
                    <input type="text" name="otp" class="otp-input"
                           placeholder="------" maxlength="6" required>
                </div>
                <button type="submit" class="auth-submit">Verify OTP →</button>
            </form>
        </div>
        <div class="auth-footer">
            Didn't get OTP? <a href="forgot-password.php">Resend</a>
        </div>
    </div>
</div>
</body>
</html>