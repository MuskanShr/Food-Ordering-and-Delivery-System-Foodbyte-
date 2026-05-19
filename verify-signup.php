<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/PHPMailer/src/PHPMailer.php';
require_once 'includes/PHPMailer/src/SMTP.php';
require_once 'includes/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const SIGNUP_CODE_TTL_MIN = 10;
const MAX_ATTEMPTS        = 5;     // brute-force guard
const RESEND_COOLDOWN_SEC = 60;    // anti-spam on the resend button

// Must arrive here via signup.php (it sets pending_email in session)
if (empty($_SESSION['pending_email'])) {
    header('Location: signup.php');
    exit;
}
$email = $_SESSION['pending_email'];

$error   = '';
$success = '';

/* ---------- Resend handler ---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $row = fetchPending($pdo, $email);
    if (!$row) {
        $error = "Your signup has expired. Please start over.";
    } elseif (strtotime($row['last_sent_at']) + RESEND_COOLDOWN_SEC > time()) {
        $wait  = (strtotime($row['last_sent_at']) + RESEND_COOLDOWN_SEC) - time();
        $error = "Please wait {$wait} more second(s) before resending.";
    } else {
        // Generate a NEW code, reset attempts, bump expiry & last_sent
        $code     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = hash('sha256', $code);
        $expires  = date('Y-m-d H:i:s', time() + SIGNUP_CODE_TTL_MIN * 60);

        $pdo->prepare(
            "UPDATE pending_signups
                SET code_hash = ?, expires_at = ?, attempts = 0, last_sent_at = NOW()
              WHERE email = ?"
        )->execute([$codeHash, $expires, $email]);

        if (sendVerificationEmail($email, $row['username'], $code)) {
            $success = "A new code has been sent to your email.";
        } else {
            $error = "Could not resend code. Try again in a moment.";
        }
    }
}

/* ---------- Verify handler ---------------------------------------- */
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codeInput = preg_replace('/\D+/', '', $_POST['code'] ?? '');  // strip spaces, dashes

    $row = fetchPending($pdo, $email);
    if (!$row) {
        $error = "Your signup has expired. Please start over.";
    } elseif (strtotime($row['expires_at']) < time()) {
        $pdo->prepare("DELETE FROM pending_signups WHERE email = ?")->execute([$email]);
        $error = "The code has expired. Please request a new one.";
    } elseif ($row['attempts'] >= MAX_ATTEMPTS) {
        $error = "Too many failed attempts. Please request a new code.";
    } elseif (strlen($codeInput) !== 6) {
        $pdo->prepare("UPDATE pending_signups SET attempts = attempts + 1 WHERE email = ?")
            ->execute([$email]);
        $error = "Please enter the 6-digit code.";
    } else {
        $providedHash = hash('sha256', $codeInput);
        if (!hash_equals($row['code_hash'], $providedHash)) {
            $pdo->prepare("UPDATE pending_signups SET attempts = attempts + 1 WHERE email = ?")
                ->execute([$email]);
            $remaining = MAX_ATTEMPTS - ($row['attempts'] + 1);
            $error = "Incorrect code." . ($remaining > 0 ? " {$remaining} attempt(s) left." : "");
        } else {
            // ---- SUCCESS: promote pending row to real user ---------
            // Last-second uniqueness check in case someone else
            // registered the username/email in the meantime.
            $stmt = $pdo->prepare(
                "SELECT id FROM users WHERE username = ? OR email = ?"
            );
            $stmt->execute([$row['username'], $row['email']]);
            if ($stmt->fetch()) {
                $pdo->prepare("DELETE FROM pending_signups WHERE email = ?")->execute([$email]);
                unset($_SESSION['pending_email']);
                header('Location: signup.php?taken=1');
                exit;
            }

            try {
                $pdo->beginTransaction();
                $pdo->prepare(
                    "INSERT INTO users (username, email, password, role)
                     VALUES (?, ?, ?, 'customer')"
                )->execute([$row['username'], $row['email'], $row['password_hash']]);
                $pdo->prepare("DELETE FROM pending_signups WHERE email = ?")->execute([$email]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Could not create account. Please try again.";
            }

            if (!$error) {
                unset($_SESSION['pending_email']);
                header('Location: login.php?registered=1');
                exit;
            }
        }
    }
}

function fetchPending(PDO $pdo, string $email): ?array {
    $stmt = $pdo->prepare("SELECT * FROM pending_signups WHERE email = ?");
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

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
        $mail->Subject = 'Your new FoodByte verification code';
        $mail->isHTML(true);

        $safeName = htmlspecialchars($username);
        $mail->Body = "
            <div style='font-family:DM Sans,sans-serif;max-width:400px;margin:auto;padding:2rem;'>
                <h2 style='color:#F97316'>🍴 FoodByte</h2>
                <p>Hi <strong>{$safeName}</strong>,</p>
                <p>Here is your new verification code:</p>
                <div style='font-size:2.5rem;font-weight:800;color:#F97316;
                            letter-spacing:8px;text-align:center;padding:1rem;
                            background:#FFF7ED;border-radius:12px;margin:1rem 0'>
                    {$code}
                </div>
                <p style='color:#6B7280;font-size:0.85rem'>
                    This code expires in <strong>10 minutes</strong>.
                </p>
            </div>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('verify-signup mail failed: ' . $e->getMessage());
        return false;
    }
}

// For the masked email shown on the page
$maskedEmail = maskEmail($email);
function maskEmail(string $e): string {
    [$local, $domain] = array_pad(explode('@', $e, 2), 2, '');
    if (!$domain) return $e;
    $vis = max(1, (int)floor(strlen($local) / 3));
    return substr($local, 0, $vis) . str_repeat('*', max(2, strlen($local) - $vis)) . '@' . $domain;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify Email – FoodByte</title>
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
        .auth-header p { margin-top: 0.4rem; opacity: 0.9; font-size: 0.9rem; }
        .auth-body { padding: 2rem; }
        .auth-error {
            background: #FFEBEE; border: 1px solid #FFCDD2; border-radius: 9px;
            padding: 0.75rem 1rem; color: #C62828; font-size: 0.87rem; margin-bottom: 1rem;
        }
        .auth-success {
            background: #E8F5E9; border: 1px solid #C8E6C9; border-radius: 9px;
            padding: 0.75rem 1rem; color: #2E7D32; font-size: 0.87rem; margin-bottom: 1rem;
        }
        .otp-input {
            width: 100%; padding: 0.85rem;
            border: 1.5px solid var(--border); border-radius: 10px;
            font-family: 'DM Sans', sans-serif; font-size: 1.5rem; font-weight: 700;
            outline: none; background: #FAFAF9;
            text-align: center; letter-spacing: 12px;
        }
        .otp-input:focus { border-color: var(--orange); background: white; }
        .auth-submit {
            width: 100%; background: var(--orange); color: white;
            border: none; border-radius: 10px; padding: 0.85rem;
            font-size: 0.95rem; font-weight: 700; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: all 0.2s; margin-top: 1rem;
        }
        .auth-submit:hover { background: var(--orange-dark); }
        .resend-row {
            text-align: center; margin-top: 1rem; font-size: 0.87rem; color: var(--gray);
        }
        .resend-btn {
            background: none; border: none; color: var(--orange);
            font-weight: 700; cursor: pointer; font-family: inherit; font-size: inherit;
            text-decoration: underline;
        }
        .resend-btn:disabled { color: var(--gray); cursor: not-allowed; text-decoration: none; }
        .info-line { font-size: 0.87rem; color: var(--gray); text-align: center; margin-bottom: 1rem; }
        .auth-footer {
            text-align: center; padding: 1rem 2rem 1.5rem;
            font-size: 0.87rem; color: var(--gray);
            border-top: 1px solid var(--light-gray);
        }
        .auth-footer a { color: var(--orange); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <div class="logo">🍴 FoodByte</div>
            <p>Verify your email</p>
        </div>
        <div class="auth-body">

            <p class="info-line">
                We sent a 6-digit code to<br><strong><?= htmlspecialchars($maskedEmail) ?></strong>
            </p>

            <?php if ($error): ?>
                <div class="auth-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="auth-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="text"
                       name="code"
                       class="otp-input"
                       placeholder="••••••"
                       inputmode="numeric"
                       autocomplete="one-time-code"
                       maxlength="6"
                       pattern="[0-9]{6}"
                       required
                       autofocus>
                <button type="submit" class="auth-submit">Verify & Create Account</button>
            </form>

            <div class="resend-row">
                <form method="POST" style="display:inline">
                    Didn't get it?
                    <button type="submit" name="resend" value="1" class="resend-btn"
                            id="resendBtn">Resend code</button>
                </form>
            </div>
        </div>
        <div class="auth-footer">
            Wrong email? <a href="signup.php">Start over</a>
        </div>
    </div>
</div>

<script>
// 60-second visual cooldown on the resend button.
// Server enforces this independently — the JS is just to avoid
// confusing users who'd otherwise click and get an error.
(function () {
    const btn = document.getElementById('resendBtn');
    if (!btn) return;
    const KEY = 'fb_resend_until';
    const now = Date.now();

    // If we just submitted a resend, the page reload happens after
    // the server processed it. Start the countdown either from the
    // success/error response or from page load if we saw "resend" in
    // the previously-submitted form.
    const justResent = <?= isset($_POST['resend']) && !$error ? 'true' : 'false' ?>;
    if (justResent) localStorage.setItem(KEY, String(now + 60_000));

    function tick() {
        const until = parseInt(localStorage.getItem(KEY) || '0', 10);
        const left  = Math.ceil((until - Date.now()) / 1000);
        if (left > 0) {
            btn.disabled    = true;
            btn.textContent = `Resend code (${left}s)`;
            setTimeout(tick, 500);
        } else {
            btn.disabled    = false;
            btn.textContent = 'Resend code';
        }
    }
    tick();
})();
</script>
</body>
</html>
