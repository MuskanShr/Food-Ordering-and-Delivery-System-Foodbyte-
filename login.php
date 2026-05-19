<?php
require_once 'includes/auth.php';   // session_start + remember-me helpers
require_once 'includes/db.php';

// If already logged in, redirect properly
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin/dashboard.php' : 'index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$login, $login]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        // Prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];

        // Remember me
        if ($remember) {
            issueRememberToken((int)$user['id']);
        }

        // Redirect based on role
        if ($user['role'] === 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: index.php');
        }

        exit;

    } else {
        $error = "Invalid username/email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – FoodByte</title>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --orange: #F97316;
            --orange-dark: #EA6C0A;
            --gray: #6B7280;
            --border: #E5E7EB;
            --light-gray: #F3F4F6;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.18);
        }

        body {
            font-family: 'DM Sans', sans-serif;
        }

        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-size: cover;
            background-position: center;

            position: relative;
        }

        .auth-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(2px);
        }

        /* Back button */

        .back-home {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 2;

            display: inline-flex;
            align-items: center;
            gap: 0.45rem;

            padding: 0.6rem 1.1rem;

            background: rgba(255, 255, 255, 0.92);
            color: #1A1A1A;

            border: 1.5px solid rgba(255, 255, 255, 0.6);
            border-radius: 10px;

            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;

            transition: all 0.2s;

            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
        }

        .back-home:hover {
            background: white;
            color: var(--orange);
            transform: translateX(-2px);
        }

        .back-home .arrow {
            font-size: 1rem;
            line-height: 1;
        }

        @media (max-width: 480px) {
            .back-home {
                top: 1rem;
                left: 1rem;
                padding: 0.5rem 0.9rem;
                font-size: 0.82rem;
            }
        }

        /* Auth box */

        .auth-box {
            position: relative;
            z-index: 1;

            background: white;
            border-radius: 20px;

            box-shadow: var(--shadow-lg);

            overflow: hidden;

            width: 100%;
            max-width: 420px;
        }

        .auth-header {
            background: linear-gradient(135deg, var(--orange), var(--orange-dark));
            color: white;

            padding: 2.5rem 2rem 2rem;

            text-align: center;
        }

        .auth-header .logo {
            font-size: 2rem;
            font-weight: 800;
        }

        .auth-header p {
            margin-top: 0.4rem;
            opacity: 0.85;
            font-size: 0.9rem;
        }

        .auth-body {
            padding: 2rem;
        }

        .auth-error {
            background: #FFEBEE;
            border: 1px solid #FFCDD2;

            border-radius: 9px;

            padding: 0.75rem 1rem;

            color: #C62828;
            font-size: 0.87rem;

            margin-bottom: 1rem;
        }

        .auth-field {
            margin-bottom: 1.2rem;
        }

        .auth-field label {
            display: block;

            font-size: 0.83rem;
            font-weight: 600;

            color: var(--gray);

            margin-bottom: 0.4rem;

            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .auth-field input[type="text"],
        .auth-field input[type="password"] {

            width: 100%;

            padding: 0.75rem 1rem;

            border: 1.5px solid var(--border);
            border-radius: 10px;

            font-family: 'DM Sans', sans-serif;
            font-size: 0.93rem;

            outline: none;

            transition: border 0.2s;

            background: #FAFAF9;
        }

        .auth-field input:focus {
            border-color: var(--orange);
            background: white;
        }

        /* Password toggle */

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 2.8rem;
        }

        .toggle-password {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray);
            font-size: 1.1rem;
            padding: 0;
            line-height: 1;
        }

        .toggle-password:hover {
            color: var(--orange);
        }

        /* Remember row */

        .auth-row {
            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-top: -0.4rem;
            margin-bottom: 0.8rem;
        }

        .remember-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;

            font-size: 0.87rem;
            color: var(--gray);

            cursor: pointer;
            user-select: none;
        }

        .remember-label input {
            width: 16px;
            height: 16px;

            accent-color: var(--orange);
            cursor: pointer;
        }

        .forgot-link {
            color: var(--orange);
            font-size: 0.85rem;
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        /* Button */

        .auth-submit {

            width: 100%;

            background: var(--orange);
            color: white;

            border: none;
            border-radius: 10px;

            padding: 0.85rem;

            font-size: 0.95rem;
            font-weight: 700;

            cursor: pointer;

            font-family: 'DM Sans', sans-serif;

            transition: all 0.2s;

            margin-top: 0.5rem;
        }

        .auth-submit:hover {
            background: var(--orange-dark);
        }

        /* Footer */

        .auth-footer {

            text-align: center;

            padding: 1rem 2rem 1.5rem;

            font-size: 0.87rem;
            color: var(--gray);

            border-top: 1px solid var(--light-gray);
        }

        .auth-footer a {
            color: var(--orange);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

    </style>
</head>

<body>

<div class="auth-page">

    <!-- Back to Home Button -->

    <a href="index.php" class="back-home" aria-label="Back to home">
        <span class="arrow">←</span>
        Back to Home
    </a>

    <div class="auth-box">

        <div class="auth-header">
            <div class="logo">🍴 FoodByte</div>
            <p>Welcome back! Sign in to continue.</p>
        </div>

        <div class="auth-body">

            <?php if ($error): ?>
                <div class="auth-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="auth-field">
                    <label>Username or Email</label>

                    <input
                        type="text"
                        name="login"
                        placeholder="Enter your username or email"
                        value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="auth-field">
                    <label>Password</label>

                    <div class="password-wrapper">
                        <input
                            type="password"
                            name="password"
                            id="passwordInput"
                            placeholder="Enter your password"
                            required
                        >
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                            <!-- Eye open -->
                            <svg id="iconEyeOpen" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <!-- Eye crossed -->
                            <svg id="iconEyeOff" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="auth-row">

                    <label class="remember-label">

                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            <?= !empty($_POST['remember']) ? 'checked' : '' ?>
                        >

                        Remember me

                    </label>

                    <a href="forgot-password.php" class="forgot-link">
                        Forgot password?
                    </a>

                </div>

                <button type="submit" class="auth-submit">
                    Login
                </button>

            </form>

        </div>

        <div class="auth-footer">
            Don't have an account?
            <a href="signup.php">Sign Up</a>
        </div>

    </div>

</div>

<script>
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('passwordInput');

    const iconOpen = document.getElementById('iconEyeOpen');
    const iconOff  = document.getElementById('iconEyeOff');

    toggleBtn.addEventListener('click', function () {
        const isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';
        iconOpen.style.display = isHidden ? 'none' : 'block';
        iconOff.style.display  = isHidden ? 'block' : 'none';
    });
</script>

</body>
</html>