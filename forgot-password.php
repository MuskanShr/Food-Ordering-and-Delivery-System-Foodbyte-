<?php
// Start session to store reset email for the OTP verification step
session_start();
require_once 'includes/db.php';

// Load PHPMailer library files required for sending emails via SMTP
require_once 'includes/PHPMailer/src/PHPMailer.php';
require_once 'includes/PHPMailer/src/SMTP.php';
require_once 'includes/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize email input to remove extra whitespace
    $email = trim($_POST['email']);

    // Check if the submitted email exists in the users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // No account found with this email — show error message
        $error = "No account found with that email.";
    } else {
        // Generate a random 6-digit OTP for password reset
        $otp     = rand(100000, 999999);

        // Delete any existing OTPs for this email to prevent duplicates in the database
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        // Insert the new OTP with a 10-minute expiry time using MySQL DATE_ADD
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, otp, expires_at) 
                        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->execute([$email, $otp]);

        // Configure PHPMailer to send the OTP via SMTP using Mailtrap for testing
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'sandbox.smtp.mailtrap.io'; // Mailtrap SMTP host for testing
            $mail->SMTPAuth   = true;
            $mail->Username = 'd8290f9167fd05'; // SMTP username
            $mail->Password = '83dcf50c762890'; // SMTP password
            $mail->SMTPSecure = 'tls'; // Use TLS encryption for secure email transmission
            $mail->Port       = 2525; // Mailtrap SMTP port

            // Set the sender and recipient details for the email
            $mail->setFrom('noreply@foodbyte.com', 'FoodByte');
            $mail->addAddress($email, $user['username']);
            $mail->Subject = 'Your FoodByte Password Reset OTP';
            $mail->isHTML(true);

            // Build the HTML email body with the OTP displayed prominently
            $mail->Body = "
                <div style='font-family:DM Sans,sans-serif;max-width:400px;margin:auto;padding:2rem;'>
                    <h2 style='color:#F97316'>🍴 FoodByte</h2>
                    <p>Hi <strong>{$user['username']}</strong>,</p>
                    <p>Your OTP for password reset is:</p>
                    <div style='font-size:2.5rem;font-weight:800;color:#F97316;
                                letter-spacing:8px;text-align:center;padding:1rem;
                                background:#FFF7ED;border-radius:12px;margin:1rem 0'>
                        {$otp}
                    </div>
                    <p style='color:#6B7280;font-size:0.85rem'>
                        This OTP expires in <strong>10 minutes</strong>.<br>
                        If you didn't request this, ignore this email.
                    </p>
                </div>
            ";
            $mail->send();

            // Store the email in session so verify-otp.php can access it in the next step
            $_SESSION['reset_email'] = $email;

            // Redirect user to OTP verification page
            header('Location: verify-otp.php');
            exit;

        } catch (Exception $e) {
            // If email sending fails show a generic error — don't expose internal details
            $error = "Could not send OTP. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password – FoodByte</title>
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
            <p>Enter your email to receive an OTP</p>
        </div>
        <div class="auth-body">
            <?php if ($error): ?>
                <!-- Display error message if email not found or OTP sending failed -->
                <div class="auth-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="auth-field">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter your registered email" required>
                </div>
                <button type="submit" class="auth-submit">Send OTP →</button>
            </form>
        </div>
        <div class="auth-footer">
            Remember your password? <a href="login.php">Login</a>
        </div>
    </div>
</div>
</body>
</html>