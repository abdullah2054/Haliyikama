<?php
/**
 * Sistem Teşhis Sayfası
 * Sorun giderildikten sonra bu dosyayı silin!
 */

// Bu sayfanın sadece yetkili kişiler tarafından görülmesi için
// basit bir koruma (isteğe bağlı, silmeden önce kaldırın)
$secret = $_GET['key'] ?? '';
if ($secret !== 'hali2024check') {
    http_response_code(403);
    die('Erişim reddedildi. ?key=hali2024check parametresi ekleyin.');
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sistem Teşhis</title>
<style>
body { font-family: monospace; padding: 20px; background: #0f172a; color: #e2e8f0; }
h2 { color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 8px; }
.ok   { color: #4ade80; }
.fail { color: #f87171; }
.warn { color: #fbbf24; }
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
td, th { padding: 8px 12px; border: 1px solid #334155; text-align: left; }
th { background: #1e293b; color: #94a3b8; }
tr:hover td { background: #1e293b; }
pre { background: #1e293b; padding: 12px; border-radius: 6px; overflow: auto; font-size: 12px; }
</style>
</head>
<body>

<h2>PHP Sürümü</h2>
<table>
<tr><th>Kontrol</th><th>Sonuç</th></tr>
<tr><td>PHP Sürümü</td><td><?= PHP_VERSION ?></td></tr>
<tr>
  <td>PHP 8.1+ (zorunlu)</td>
  <td class="<?= version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'fail' ?>">
    <?= version_compare(PHP_VERSION, '8.1.0', '>=') ? '✓ Tamam' : '✗ HATA: En az PHP 8.1 gerekli!' ?>
  </td>
</tr>
<tr>
  <td>match() ifadesi (PHP 8.0+)</td>
  <td class="<?= version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'fail' ?>">
    <?= version_compare(PHP_VERSION, '8.0.0', '>=') ? '✓ Destekleniyor' : '✗ Desteklenmiyor' ?>
  </td>
</tr>
</table>

<h2>Dosya ve Dizin Kontrolleri</h2>
<table>
<tr><th>Dosya / Dizin</th><th>Durum</th></tr>
<?php
$checks = [
    'config/config.php'    => __DIR__ . '/config/config.php',
    'config/db.php'        => __DIR__ . '/config/db.php',
    'includes/functions.php' => __DIR__ . '/includes/functions.php',
    'includes/auth.php'    => __DIR__ . '/includes/auth.php',
    'vendor/ (PHPMailer)' => __DIR__ . '/vendor/autoload.php',
    'assets/uploads/ (yazılabilir)' => __DIR__ . '/assets/uploads/',
];
foreach ($checks as $label => $path) {
    $exists  = file_exists($path);
    $writable = is_dir($path) ? is_writable($path) : true;
    $status  = $exists ? ($writable ? '<span class="ok">✓ Mevcut</span>' : '<span class="warn">⚠ Yazma izni yok</span>') : '<span class="fail">✗ Eksik</span>';
    echo "<tr><td>{$label}</td><td>{$status}</td></tr>\n";
}
?>
</table>

<h2>Veritabanı Bağlantısı</h2>
<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
try {
    $pdo = getDB();
    echo '<p class="ok">✓ Veritabanı bağlantısı başarılı.</p>';

    // Tablo kontrolleri
    $tables = ['users','companies','orders','locations_cities','settings','wallet_transactions'];
    echo '<table><tr><th>Tablo</th><th>Durum</th><th>Kayıt Sayısı</th></tr>';
    foreach ($tables as $t) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            echo "<tr><td>{$t}</td><td class='ok'>✓ Var</td><td>{$count}</td></tr>\n";
        } catch (\Exception $e) {
            echo "<tr><td>{$t}</td><td class='fail'>✗ Yok veya hata</td><td>-</td></tr>\n";
        }
    }
    echo '</table>';

    // Şehir sayısı kontrolü
    $cityCount = $pdo->query('SELECT COUNT(*) FROM locations_cities')->fetchColumn();
    if ($cityCount == 0) {
        echo '<p class="warn">⚠ locations_cities tablosu boş — install.sql çalıştırıldı mı?</p>';
    }

    // Admin kontrolü
    $admin = $pdo->query('SELECT email, status FROM users WHERE role = "admin" LIMIT 1')->fetch();
    if ($admin) {
        echo '<p class="ok">✓ Admin kullanıcısı mevcut: ' . htmlspecialchars($admin['email']) . ' (durum: ' . $admin['status'] . ')</p>';
    } else {
        echo '<p class="fail">✗ Admin kullanıcısı bulunamadı — install.sql çalıştırıldı mı?</p>';
    }

} catch (\Throwable $e) {
    echo '<p class="fail">✗ Veritabanı bağlantısı BAŞARISIZ:</p>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<p class="warn">config/db.php dosyasındaki DB_HOST, DB_NAME, DB_USER, DB_PASS değerlerini kontrol edin.</p>';
}
?>

<h2>Oturum (Session)</h2>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['test_key'] = 'test_val_' . time();
$ok = ($_SESSION['test_key'] ?? '') !== '';
echo $ok
    ? '<p class="ok">✓ Oturum çalışıyor.</p>'
    : '<p class="fail">✗ Oturum çalışmıyor — session.save_path kontrol edin.</p>';
echo '<p>Kayıt yolu: <code>' . htmlspecialchars(session_save_path() ?: sys_get_temp_dir()) . '</code></p>';
?>

<h2>PHP Uzantıları</h2>
<table>
<tr><th>Uzantı</th><th>Durum</th></tr>
<?php
$exts = ['pdo','pdo_mysql','mbstring','json','openssl','fileinfo','session'];
foreach ($exts as $ext) {
    $loaded = extension_loaded($ext);
    echo "<tr><td>{$ext}</td><td class='" . ($loaded ? 'ok' : 'fail') . "'>" . ($loaded ? '✓ Yüklü' : '✗ Eksik') . "</td></tr>\n";
}
?>
</table>

<h2>APP_URL ve Ortam</h2>
<table>
<tr><th>Değişken</th><th>Değer</th></tr>
<tr><td>APP_URL</td><td><?= defined('APP_URL') ? htmlspecialchars(APP_URL) : '<span class="fail">Tanımlı değil</span>' ?></td></tr>
<tr><td>APP_ROOT</td><td><?= defined('APP_ROOT') ? htmlspecialchars(APP_ROOT) : '-' ?></td></tr>
<tr><td>APP_DEBUG</td><td><?= defined('APP_DEBUG') ? (APP_DEBUG ? 'true' : 'false') : '-' ?></td></tr>
<tr><td>HTTPS</td><td><?= isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Evet' : 'Hayır' ?></td></tr>
<tr><td>HTTP_HOST</td><td><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '-') ?></td></tr>
<tr><td>SERVER_SOFTWARE</td><td><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '-') ?></td></tr>
</table>

<hr>
<p style="color:#64748b;font-size:12px;">
  ⚠ Bu sayfayı sorun çözüldükten sonra sunucudan silin: <code>check.php</code>
</p>

</body>
</html>
