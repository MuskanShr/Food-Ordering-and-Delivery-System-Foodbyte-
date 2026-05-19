<?php
require_once __DIR__ . '/db.php';  

// ---------- Config --------------------------------------------------
const REMEMBER_COOKIE_NAME = 'foodbyte_remember';
const REMEMBER_LIFETIME    = 60 * 60 * 24 * 30;   // 30 days
const REMEMBER_SELECTOR_BYTES  = 9;               // -> 12 base64url chars
const REMEMBER_VALIDATOR_BYTES = 32;              // -> 43 base64url chars

// ---------- Session bootstrap --------------------------------------
// Tighten session cookie params BEFORE session_start. Defaults are bad.
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    session_set_cookie_params([
        'lifetime' => 0,            // session cookie: dies when browser closes
        'path'     => '/',
        'secure'   => $isHttps,     // XAMPP local = HTTP, so false there
        'httponly' => true,         // JS can't read it
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------- Existing API (unchanged signatures) ---------------------
function isLoggedIn(): bool {
    // First check the live session
    if (isset($_SESSION['user_id'])) return true;
    // Then try to restore from remember-me cookie
    return tryAutoLogin();
}

function isAdmin(): bool {
    if (!isLoggedIn()) return false;
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /foodbyte/login.php');
        exit;
    }
}

function requireAdmin(): void {
    if (!isLoggedIn() || !isAdmin()) {
        header('Location: /foodbyte/login.php');
        exit;
    }
}

function getCartCount(): int {
    return isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
}

/* =====================================================================
 * Remember-me API
 * ===================================================================== */

/**
 * Issue a new remember-me token for $userId, write the DB row, set the
 * cookie. Call this immediately after a successful password login when
 * the "remember me" checkbox was ticked.
 */
function issueRememberToken(int $userId): void {
    global $pdo;

    $selector  = base64UrlEncode(random_bytes(REMEMBER_SELECTOR_BYTES));
    $validator = base64UrlEncode(random_bytes(REMEMBER_VALIDATOR_BYTES));
    $hash      = hash('sha256', $validator);
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_LIFETIME);
    $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $stmt = $pdo->prepare(
        "INSERT INTO auth_tokens (user_id, selector, validator_hash, expires_at, user_agent)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $selector, $hash, $expires, $ua]);

    setRememberCookie($selector . ':' . $validator, time() + REMEMBER_LIFETIME);
}

/**
 * If the user has a valid remember-me cookie and is not already
 * logged in, restore the session. Returns true if it succeeded.
 *
 * Rotates the validator on success (one-use tokens). Deletes the
 * stored token if validation fails — that's the theft-detection
 * hook: a mismatched validator means either tampering or the
 * cookie was stolen and already used.
 */
function tryAutoLogin(): bool {
    global $pdo;

    if (isset($_SESSION['user_id'])) return true;             // already in
    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) return false;

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
    if (count($parts) !== 2) {
        clearRememberCookie();
        return false;
    }
    [$selector, $validator] = $parts;

    // Basic shape check before hitting DB
    if (!preg_match('/^[A-Za-z0-9_\-]{6,32}$/', $selector)
     || !preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $validator)) {
        clearRememberCookie();
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT t.id, t.user_id, t.validator_hash, t.expires_at,
                u.username, u.role
         FROM auth_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.selector = ?"
    );
    $stmt->execute([$selector]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        clearRememberCookie();
        return false;
    }

    // Expired?
    if (strtotime($row['expires_at']) < time()) {
        $pdo->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$row['id']]);
        clearRememberCookie();
        return false;
    }

    // Constant-time compare of validator hashes (prevents timing attacks)
    $providedHash = hash('sha256', $validator);
    if (!hash_equals($row['validator_hash'], $providedHash)) {
        // Selector matched but validator didn't.  Two possibilities:
        //   1. A stolen cookie was already used (rotation invalidated it).
        //   2. Someone is fishing for valid selectors.
        // Conservative response: nuke ALL tokens for this user so any
        // attacker copy is also useless, then require fresh login.
        $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")
            ->execute([$row['user_id']]);
        clearRememberCookie();
        return false;
    }

    // ----- Valid. Log in. -------------------------------------------
    // Regenerate the PHP session ID to prevent session fixation:
    // a fresh login should always get a fresh session ID.
    session_regenerate_id(true);

    $_SESSION['user_id']  = (int)$row['user_id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['role']     = $row['role'];

    // ----- Rotate the validator (one-use token) ---------------------
    $newValidator     = base64UrlEncode(random_bytes(REMEMBER_VALIDATOR_BYTES));
    $newHash          = hash('sha256', $newValidator);
    $newExpires       = date('Y-m-d H:i:s', time() + REMEMBER_LIFETIME);

    $pdo->prepare(
        "UPDATE auth_tokens
            SET validator_hash = ?, expires_at = ?, last_used_at = NOW()
          WHERE id = ?"
    )->execute([$newHash, $newExpires, $row['id']]);

    setRememberCookie($selector . ':' . $newValidator, time() + REMEMBER_LIFETIME);
    return true;
}

/**
 * Delete the current device's token from DB and clear the cookie.
 * Call this from logout.php.
 */
function clearRememberToken(): void {
    global $pdo;
    if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
        if (count($parts) === 2) {
            $pdo->prepare("DELETE FROM auth_tokens WHERE selector = ?")
                ->execute([$parts[0]]);
        }
    }
    clearRememberCookie();
}

/**
 * Nuke every remember-me token for a user. Call this on password
 * change, email change, or "log out everywhere".
 */
function invalidateAllRememberTokens(int $userId): void {
    global $pdo;
    $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$userId]);
}

/* ---------- Internal helpers --------------------------------------- */

function setRememberCookie(string $value, int $expires): void {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    setcookie(REMEMBER_COOKIE_NAME, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // Also reflect into $_COOKIE so same-request reads see the new value
    $_COOKIE[REMEMBER_COOKIE_NAME] = $value;
}

function clearRememberCookie(): void {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

/** URL-safe base64 (no padding) — safe for cookie values. */
function base64UrlEncode(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}