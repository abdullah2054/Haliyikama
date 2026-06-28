<?php
/**
 * Admin kullanıcısını oluşturur veya şifresini sıfırlar.
 * Kullandıktan sonra sunucudan silin!
 */
$key = $_GET['key'] ?? '';
if (!hash_equals('hali2024setup', $key)) {
    http_response_code(403);
    die('Erişim reddedildi. ?key=hali2024setup parametresi ekleyin.');
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$email    = 'admin@siteadi.com';
$password = 'admin123';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $pdo = getDB();

    // Var mı kontrol et
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        // Güncelle
        $pdo->prepare('UPDATE users SET password = ?, role = "admin", status = "active" WHERE email = ?')
            ->execute([$hash, $email]);
        $msg = 'Admin şifresi güncellendi.';
    } else {
        // Oluştur
        $pdo->prepare('INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, "admin", "active")')
            ->execute(['Admin', $email, '05001234567', $hash]);
        $msg = 'Admin kullanıcısı oluşturuldu.';
    }

    echo '<div style="font-family:sans-serif;padding:40px;max-width:500px;margin:auto">
        <h2 style="color:#16a34a">✓ ' . $msg . '</h2>
        <p><strong>E-posta:</strong> ' . $email . '</p>
        <p><strong>Şifre:</strong> admin123</p>
        <p><a href="' . APP_URL . '/login.php">→ Giriş Yap</a></p>
        <hr>
        <p style="color:#dc2626;font-size:13px;">
          ⚠ Bu dosyayı <strong>admin-setup.php</strong> sunucudan hemen silin!
        </p>
    </div>';

} catch (\Throwable $e) {
    echo '<p style="color:red">Hata: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
