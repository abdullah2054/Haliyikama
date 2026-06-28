<?php
/**
 * Admin - Site Ayarları
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

requireAdmin();
$pdo     = getDB();
$success = '';

if (isPost()) {
    verifyCsrf();
    $settingKeys = [
        'site_name', 'site_email', 'platform_fee',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass',
        'smtp_from_name', 'smtp_encryption',
        'sms_enabled', 'push_enabled', 'maintenance_mode',
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach ($settingKeys as $key) {
        if (isset($_POST[$key])) {
            $stmt->execute([$key, trim($_POST[$key])]);
        }
    }
    $success = 'Ayarlar kaydedildi.';
}

// Ayarları yükle
$allSettings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Site Ayarları';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>⚙️ Site Ayarları</h1>
    <p>Sistem konfigürasyonu</p>
  </div>
</div>

<div class="page-content">
  <div class="container" style="max-width:760px;">

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" data-once>
      <?= csrfInput() ?>

      <!-- Genel Ayarlar -->
      <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h2 class="card-title">🌐 Genel Ayarlar</h2></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label class="form-label">Site Adı</label>
              <input type="text" name="site_name" class="form-control"
                     value="<?= e($allSettings['site_name'] ?? 'Halı Yıkama Pazaryeri') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Site E-postası</label>
              <input type="email" name="site_email" class="form-control"
                     value="<?= e($allSettings['site_email'] ?? 'info@siteadi.com') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Platform Hizmet Bedeli (TL)</label>
            <input type="number" name="platform_fee" class="form-control" min="0" step="0.01"
                   value="<?= e($allSettings['platform_fee'] ?? '25.00') ?>">
            <div class="form-hint">Firma her sipariş kabul ettiğinde kesilecek sabit ücret</div>
          </div>
          <div class="form-group">
            <label class="form-label">Bakım Modu</label>
            <select name="maintenance_mode" class="form-control">
              <option value="0" <?= ($allSettings['maintenance_mode'] ?? '0') === '0' ? 'selected' : '' ?>>Kapalı</option>
              <option value="1" <?= ($allSettings['maintenance_mode'] ?? '0') === '1' ? 'selected' : '' ?>>Açık</option>
            </select>
          </div>
        </div>
      </div>

      <!-- SMTP Ayarları -->
      <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h2 class="card-title">✉️ SMTP / E-posta Ayarları</h2></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label class="form-label">SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control"
                     value="<?= e($allSettings['smtp_host'] ?? '') ?>" placeholder="smtp.example.com">
            </div>
            <div class="form-group">
              <label class="form-label">SMTP Port</label>
              <input type="number" name="smtp_port" class="form-control"
                     value="<?= e($allSettings['smtp_port'] ?? '587') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">SMTP Kullanıcı</label>
              <input type="email" name="smtp_user" class="form-control"
                     value="<?= e($allSettings['smtp_user'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">SMTP Şifre</label>
              <input type="password" name="smtp_pass" class="form-control"
                     placeholder="Değiştirmek için girin">
            </div>
            <div class="form-group">
              <label class="form-label">Gönderen Adı</label>
              <input type="text" name="smtp_from_name" class="form-control"
                     value="<?= e($allSettings['smtp_from_name'] ?? 'Halı Yıkama Pazaryeri') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Şifreleme</label>
              <select name="smtp_encryption" class="form-control">
                <option value="tls" <?= ($allSettings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
                <option value="ssl" <?= ($allSettings['smtp_encryption'] ?? '') === 'ssl'  ? 'selected' : '' ?>>SSL (465)</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Bildirim Ayarları -->
      <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h2 class="card-title">🔔 Bildirim Altyapısı</h2></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label class="form-label">SMS Bildirimi</label>
              <select name="sms_enabled" class="form-control">
                <option value="0" <?= ($allSettings['sms_enabled'] ?? '0') === '0' ? 'selected' : '' ?>>Pasif (Altyapı Hazır)</option>
                <option value="1" <?= ($allSettings['sms_enabled'] ?? '0') === '1' ? 'selected' : '' ?>>Aktif</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Firebase Push</label>
              <select name="push_enabled" class="form-control">
                <option value="0" <?= ($allSettings['push_enabled'] ?? '0') === '0' ? 'selected' : '' ?>>Pasif (Altyapı Hazır)</option>
                <option value="1" <?= ($allSettings['push_enabled'] ?? '0') === '1' ? 'selected' : '' ?>>Aktif</option>
              </select>
            </div>
          </div>
          <div class="alert alert-info">
            ℹ️ SMS ve Firebase push bildirimleri altyapısı hazır fakat şu an pasif.
            Aktifleştirmek için <code>includes/notification-functions.php</code> dosyasındaki
            <code>sendSmsNotification()</code> ve <code>sendPushNotification()</code> fonksiyonlarını doldurun.
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg">💾 Tüm Ayarları Kaydet</button>
    </form>

  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
