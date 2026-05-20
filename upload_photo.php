<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$uid = (int) $_SESSION['user_id'];

// ── Remove photo ──────────────────────────────────────────────
if (isset($_POST['remove_photo'])) {
    $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();

    if (!empty($row['profile_photo'])) {
        $filePath = __DIR__ . '/uploads/avatars/' . $row['profile_photo'];
        if (file_exists($filePath)) unlink($filePath);
    }

    $pdo->prepare("UPDATE users SET profile_photo = NULL WHERE id = ?")->execute([$uid]);
    $_SESSION['photo_flash'] = ['type' => 'success', 'msg' => 'Profile photo removed.'];
    header('Location: profile.php');
    exit;
}

// ── Upload cropped photo (base64 data URL from canvas) ────────
if (!empty($_POST['profile_photo_data'])) {
    $dataUrl = $_POST['profile_photo_data'];

    // Validate data URL format (must be JPEG or PNG from canvas)
    if (!preg_match('/^data:image\/(jpeg|png);base64,/', $dataUrl, $matches)) {
        $_SESSION['photo_flash'] = ['type' => 'error', 'msg' => 'Invalid image data. Please try again.'];
        header('Location: profile.php');
        exit;
    }

    // Decode base64
    $base64  = substr($dataUrl, strpos($dataUrl, ',') + 1);
    $imgData = base64_decode($base64);

    if ($imgData === false || strlen($imgData) < 100) {
        $_SESSION['photo_flash'] = ['type' => 'error', 'msg' => 'Could not decode image. Please try again.'];
        header('Location: profile.php');
        exit;
    }

    // Size limit: 5 MB on decoded bytes
    if (strlen($imgData) > 5 * 1024 * 1024) {
        $_SESSION['photo_flash'] = ['type' => 'error', 'msg' => 'Image is too large. Please try again.'];
        header('Location: profile.php');
        exit;
    }

    // Verify JPEG magic bytes (FF D8 FF) or PNG magic bytes (89 50 4E 47)
    $magic = substr($imgData, 0, 4);
    $isJpeg = (substr($magic, 0, 3) === "\xFF\xD8\xFF");
    $isPng  = ($magic === "\x89\x50\x4E\x47");

    if (!$isJpeg && !$isPng) {
        $_SESSION['photo_flash'] = ['type' => 'error', 'msg' => 'Invalid image format. Please upload a JPG or PNG.'];
        header('Location: profile.php');
        exit;
    }

    // Create avatars directory if needed
    $avatarDir = __DIR__ . '/uploads/avatars/';
    if (!is_dir($avatarDir)) mkdir($avatarDir, 0755, true);

    // Delete old avatar file
    $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!empty($row['profile_photo'])) {
        $oldFile = $avatarDir . $row['profile_photo'];
        if (file_exists($oldFile)) unlink($oldFile);
    }

    // Save new file (always .jpg since canvas outputs JPEG)
    $filename = 'avatar_' . $uid . '_' . bin2hex(random_bytes(6)) . '.jpg';
    $dest     = $avatarDir . $filename;

    if (file_put_contents($dest, $imgData) === false) {
        $_SESSION['photo_flash'] = ['type' => 'error', 'msg' => 'Could not save image. Please try again.'];
        header('Location: profile.php');
        exit;
    }

    $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?")->execute([$filename, $uid]);
    $_SESSION['photo_flash'] = ['type' => 'success', 'msg' => '✅ Profile photo updated!'];

} else {
    $_SESSION['photo_flash'] = ['type' => 'error', 'msg' => 'No image data received. Please try again.'];
}

header('Location: profile.php');
exit;