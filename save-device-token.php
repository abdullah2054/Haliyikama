<?php
/**
 * Android WebView - Cihaz Token Kayıt Endpoint'i
 * Firebase push bildirimi için altyapı (şimdilik pasif)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Sadece POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Giriş yapılmamışsa token yok
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
}

$token    = trim($_POST['device_token'] ?? '');
$platform = $_POST['platform'] ?? 'android_webview';

$allowedPlatforms = ['android_webview', 'web', 'ios_future'];
if (!$token) {
    jsonResponse(['success' => false, 'error' => 'Token required']);
}
if (!in_array($platform, $allowedPlatforms, true)) {
    $platform = 'android_webview';
}

$userId = (int)$_SESSION['user_id'];
$pdo    = getDB();

// INSERT OR UPDATE (ON DUPLICATE KEY)
$pdo->prepare(
    'INSERT INTO device_tokens (user_id, device_token, platform, updated_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE platform = VALUES(platform), updated_at = NOW()'
)->execute([$userId, $token, $platform]);

jsonResponse(['success' => true]);
