<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Changed – FoodByte</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --orange: #F97316; --orange-dark: #EA6C0A; --shadow-lg: 0 10px 40px rgba(0,0,0,0.18); }
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
            width: 100%; max-width: 420px;
            text-align: center; padding: 3rem 2rem;
        }
        .success-icon { font-size: 4rem; margin-bottom: 1rem; }
        h2 { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; }
        p { color: #6B7280; margin-bottom: 2rem; font-size: 0.95rem; }
        .btn {
            display: inline-block; background: var(--orange); color: white;
            text-decoration: none; border-radius: 10px; padding: 0.85rem 2rem;
            font-size: 0.95rem; font-weight: 700; transition: all 0.2s;
        }
        .btn:hover { background: var(--orange-dark); }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="success-icon">✅</div>
        <h2>Password Changed!</h2>
        <p>Your password has been reset successfully.<br>You can now log in with your new password.</p>
        <a href="login.php" class="btn">Go to Login →</a>
    </div>
</div>
</body>
</html>
