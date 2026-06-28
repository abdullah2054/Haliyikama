<?php
/**
 * Firma Başvuru Sayfası
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Giriş yapmışsa yönlendir
if (isLoggedIn()) {
    $u = currentUser();
    header('Location: ' . APP_URL . match($u['role']) {
        'admin'   => '/admin/',
        'company' => '/company-panel.php',
        default   => '/create-order.php',
    });
    exit;
}

$errors = [];
$values = [
    'company_name'    => '',
    'authorized_name' => '',
    'phone'           => '',
    'email'           => '',
    'city_id'         => '',
    'district_id'     => '',
    'address'         => '',
    'description'     => '',
    'price_per_m2'    => '',
    'min_order_price' => '',
    'user_name'       => '',
    'user_email'      => '',
];

try {
    $pdo    = getDB();
    $cities = $pdo->query('SELECT id, name FROM locations_cities ORDER BY name')->fetchAll();
} catch (\Throwable $e) {
    $pdo    = null;
    $cities = [];
    $errors[] = 'Veritabanı bağlantısı kurulamadı. Lütfen daha sonra tekrar deneyin.';
}

if (isPost()) {
    verifyCsrf();

    foreach (array_keys($values) as $key) {
        $values[$key] = trim($_POST[$key] ?? '');
    }
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Doğrulama
    if (mb_strlen($values['company_name']) < 2)    $errors[] = 'Firma adı zorunludur.';
    if (mb_strlen($values['authorized_name']) < 2)  $errors[] = 'Yetkili kişi adı zorunludur.';
    if (!$values['phone'])                          $errors[] = 'Firma telefonu zorunludur.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli firma e-postası girin.';
    if (!$values['city_id'])                        $errors[] = 'İl seçiniz.';
    if (!filter_var($values['user_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli giriş e-postası girin.';
    if (mb_strlen($values['user_name']) < 2)        $errors[] = 'Ad soyad zorunludur.';
    if (strlen($password) < 6)                      $errors[] = 'Şifre en az 6 karakter olmalı.';
    if ($password !== $password2)                   $errors[] = 'Şifreler eşleşmiyor.';

    if (empty($errors) && !$pdo) {
        $errors[] = 'Veritabanı bağlantısı yok. Lütfen daha sonra deneyin.';
    }

    if (empty($errors)) {
        // E-posta benzersizlik
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$values['user_email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Bu giriş e-postası zaten kayıtlı.';
        }
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Kullanıcı oluştur
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare(
                'INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, "company", "active")'
            )->execute([$values['user_name'], $values['user_email'], $values['phone'], $hash]);
            $userId = (int)$pdo->lastInsertId();

            // Firma kaydı (onay bekliyor)
            $logoPath = null;
            if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logoPath = uploadImage($_FILES['logo'], 'logos');
            }

            $pdo->prepare(
                'INSERT INTO companies
                 (user_id, company_name, authorized_name, phone, email, city_id, district_id,
                  address, description, price_per_m2, min_order_price, logo, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
            )->execute([
                $userId,
                $values['company_name'],
                $values['authorized_name'],
                $values['phone'],
                $values['email'],
                $values['city_id'],
                $values['district_id'] ?: null,
                $values['address'],
                $values['description'] ?: null,
                $values['price_per_m2'] ?: 0,
                $values['min_order_price'] ?: 0,
                $logoPath,
            ]);

            $pdo->commit();

            $_SESSION['flash'][] = [
                'type' => 'success',
                'text' => 'Başvurunuz alındı! Admin onayından sonra hesabınız aktif olacak ve e-posta ile bilgilendirileceksiniz.',
            ];
            header('Location: ' . APP_URL . '/login.php');
            exit;
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $errors[] = 'Bir hata oluştu, lütfen tekrar deneyin.';
        }
    }
}

$districts = [];
if ($values['city_id'] && $pdo) {
    $stmt = $pdo->prepare('SELECT id, name FROM locations_districts WHERE city_id = ? ORDER BY name');
    $stmt->execute([$values['city_id']]);
    $districts = $stmt->fetchAll();
}

$pageTitle = 'Firma Başvurusu';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>🏢 Firma Başvurusu</h1>
    <p>Halı yıkama pazaryerimize katılın, yeni müşterilere ulaşın</p>
  </div>
</div>

<div class="page-content">
  <div class="container" style="max-width:720px;">

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <span>⚠️</span>
        <div><?= implode('<br>', array_map('e', $errors)) ?></div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Başvuru Formu</h2>
        <span class="badge badge-warning">Admin onayı gerektirir</span>
      </div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" data-once>
          <?= csrfInput() ?>

          <!-- Firma Bilgileri -->
          <h3 style="font-size:15px;font-weight:700;margin-bottom:16px;color:var(--primary);">🏢 Firma Bilgileri</h3>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label class="form-label" for="company_name">Firma Adı *</label>
              <input type="text" id="company_name" name="company_name" class="form-control"
                     value="<?= e($values['company_name']) ?>" required maxlength="200">
            </div>
            <div class="form-group">
              <label class="form-label" for="authorized_name">Yetkili Kişi *</label>
              <input type="text" id="authorized_name" name="authorized_name" class="form-control"
                     value="<?= e($values['authorized_name']) ?>" required maxlength="150">
            </div>
            <div class="form-group">
              <label class="form-label" for="co_phone">Firma Telefonu *</label>
              <input type="tel" id="co_phone" name="phone" class="form-control"
                     value="<?= e($values['phone']) ?>" required maxlength="20">
            </div>
            <div class="form-group">
              <label class="form-label" for="co_email">Firma E-postası *</label>
              <input type="email" id="co_email" name="email" class="form-control"
                     value="<?= e($values['email']) ?>" required maxlength="200">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="description">Firma Açıklaması</label>
            <textarea id="description" name="description" class="form-control" rows="3" maxlength="1000"><?= e($values['description']) ?></textarea>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label class="form-label" for="price_per_m2">m² Fiyatı (TL)</label>
              <input type="number" id="price_per_m2" name="price_per_m2" class="form-control"
                     value="<?= e($values['price_per_m2']) ?>" min="0" step="0.50" placeholder="Örn: 15.00">
            </div>
            <div class="form-group">
              <label class="form-label" for="min_order_price">Min. Sipariş Tutarı (TL)</label>
              <input type="number" id="min_order_price" name="min_order_price" class="form-control"
                     value="<?= e($values['min_order_price']) ?>" min="0" step="1" placeholder="Örn: 200">
            </div>
          </div>

          <!-- Firma Logo -->
          <div class="form-group">
            <label class="form-label" for="logo">Firma Logosu (isteğe bağlı)</label>
            <input type="file" id="logo" name="logo" class="form-control"
                   accept="image/jpeg,image/png,image/webp" data-preview="logoPreview">
            <div class="form-hint">JPG, PNG veya WebP, max 5 MB</div>
            <img id="logoPreview" src="" alt="Logo Önizleme"
                 style="display:none;margin-top:10px;max-width:120px;border-radius:8px;">
          </div>

          <!-- Firma Adresi -->
          <h3 style="font-size:15px;font-weight:700;margin:24px 0 16px;color:var(--primary);">📍 Firma Adresi</h3>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label class="form-label" for="city_id">İl *</label>
              <select id="city_id" name="city_id" class="form-control" required>
                <option value="">İl Seçin</option>
                <?php foreach ($cities as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= $values['city_id'] == $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="district_id">İlçe</label>
              <select id="district_id" name="district_id" class="form-control">
                <option value="">İlçe Seçin</option>
                <?php foreach ($districts as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= $values['district_id'] == $d['id'] ? 'selected' : '' ?>>
                    <?= e($d['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="address">Açık Adres</label>
            <textarea id="address" name="address" class="form-control" rows="2"><?= e($values['address']) ?></textarea>
          </div>

          <!-- Giriş Bilgileri -->
          <h3 style="font-size:15px;font-weight:700;margin:24px 0 16px;color:var(--primary);">🔑 Giriş Bilgileri</h3>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label class="form-label" for="user_name">Ad Soyad *</label>
              <input type="text" id="user_name" name="user_name" class="form-control"
                     value="<?= e($values['user_name']) ?>" required maxlength="150">
            </div>
            <div class="form-group">
              <label class="form-label" for="user_email">Giriş E-postası *</label>
              <input type="email" id="user_email" name="user_email" class="form-control"
                     value="<?= e($values['user_email']) ?>" required maxlength="200">
            </div>
            <div class="form-group">
              <label class="form-label" for="password">Şifre *</label>
              <input type="password" id="password" name="password" class="form-control"
                     placeholder="En az 6 karakter" required minlength="6">
            </div>
            <div class="form-group">
              <label class="form-label" for="password2">Şifre Tekrar *</label>
              <input type="password" id="password2" name="password2" class="form-control"
                     placeholder="Şifre tekrar" required minlength="6">
            </div>
          </div>

          <div class="form-check" style="margin-bottom:20px;">
            <input type="checkbox" id="terms" name="terms" required>
            <label for="terms" style="font-size:13px;">
              <a href="#">Kullanım Şartları</a> ve <a href="#">Gizlilik Politikası</a>'nı kabul ediyorum.
            </label>
          </div>

          <button type="submit" class="btn btn-primary btn-full btn-lg">
            🚀 Başvuruyu Gönder
          </button>
        </form>
      </div>
    </div>

    <div style="text-align:center;margin-top:16px;font-size:14px;">
      Zaten hesabınız var mı? <a href="<?= APP_URL ?>/login.php">Giriş Yap</a>
    </div>

  </div>
</div>

<script>
document.getElementById('city_id').addEventListener('change', function() {
  const cityId = this.value;
  const distSel = document.getElementById('district_id');
  distSel.innerHTML = '<option value="">İlçe Seçin</option>';
  if (!cityId) return;
  distSel.disabled = true;
  fetch('<?= APP_URL ?>/api/districts.php?city_id=' + cityId)
    .then(r => r.json())
    .then(data => {
      data.forEach(d => {
        distSel.innerHTML += `<option value="${d.id}">${d.name}</option>`;
      });
    })
    .finally(() => distSel.disabled = false);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
