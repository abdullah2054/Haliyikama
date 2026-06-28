<?php
/**
 * Halı Yıkama Talebi Oluşturma
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/notification-functions.php';

requireLogin();
$user = currentUser();
checkUserStatus();

// Firma kullanıcısı talep oluşturamaz
if ($user['role'] === 'company') {
    header('Location: ' . APP_URL . '/company-panel.php');
    exit;
}
if ($user['role'] === 'admin') {
    header('Location: ' . APP_URL . '/admin/');
    exit;
}

$pdo    = getDB();
$errors = [];
$values = [
    'name'          => $user['name'],
    'phone'         => $user['phone'] ?? '',
    'email'         => $user['email'],
    'city_id'       => '',
    'district_id'   => '',
    'neighborhood_id' => '',
    'address'       => '',
    'carpet_count'  => 1,
    'total_m2'      => '',
    'pickup_date'   => '',
    'note'          => '',
];

if (isPost()) {
    verifyCsrf();

    $values['phone']           = trim($_POST['phone']           ?? '');
    $values['city_id']         = (int)($_POST['city_id']        ?? 0);
    $values['district_id']     = (int)($_POST['district_id']    ?? 0);
    $values['neighborhood_id'] = (int)($_POST['neighborhood_id'] ?? 0);
    $values['address']         = trim($_POST['address']         ?? '');
    $values['carpet_count']    = max(1, (int)($_POST['carpet_count'] ?? 1));
    $values['total_m2']        = (float)($_POST['total_m2']     ?? 0);
    $values['pickup_date']     = trim($_POST['pickup_date']      ?? '');
    $values['note']            = trim($_POST['note']             ?? '');

    if (!$values['phone'])            $errors[] = 'Telefon numarası zorunludur.';
    if (!$values['city_id'])          $errors[] = 'İl seçiniz.';
    if (!$values['district_id'])      $errors[] = 'İlçe seçiniz.';
    if (!$values['address'])          $errors[] = 'Açık adres zorunludur.';
    if ($values['carpet_count'] < 1)  $errors[] = 'Halı adedi en az 1 olmalı.';
    if ($values['total_m2'] <= 0)     $errors[] = 'Toplam metrekare giriniz.';
    if (!$values['pickup_date'])      $errors[] = 'Teslim alma tarihi seçiniz.';
    elseif (strtotime($values['pickup_date']) < strtotime('today'))
        $errors[] = 'Teslim alma tarihi bugün veya sonrası olmalı.';

    if (empty($errors)) {
        $trackingCode = generateTrackingCode();

        $stmt = $pdo->prepare(
            'INSERT INTO orders
             (tracking_code, customer_id, city_id, district_id, neighborhood_id,
              address, carpet_count, total_m2, pickup_date, note, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
        );
        $stmt->execute([
            $trackingCode,
            $user['id'],
            $values['city_id'],
            $values['district_id'] ?: null,
            $values['neighborhood_id'] ?: null,
            $values['address'],
            $values['carpet_count'],
            $values['total_m2'],
            $values['pickup_date'],
            $values['note'] ?: null,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // Durum geçmişi
        $pdo->prepare(
            'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, "pending", "Talep oluşturuldu.", ?)'
        )->execute([$orderId, $user['id']]);

        // Müşteri telefon güncelle
        if (!$user['phone'] && $values['phone']) {
            $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')
                ->execute([$values['phone'], $user['id']]);
        }

        // E-posta bildirimi
        sendOrderNotification($orderId, 'pending', $user['id']);

        $_SESSION['flash'][] = [
            'type' => 'success',
            'text' => 'Talebiniz oluşturuldu! Takip kodunuz: ' . $trackingCode,
        ];
        header('Location: ' . APP_URL . '/order-detail.php?id=' . $orderId);
        exit;
    }
}

// İller
$cities = $pdo->query('SELECT id, name FROM locations_cities ORDER BY name')->fetchAll();

// Seçili ilçeler (form hatası durumunda)
$districts = [];
if ($values['city_id']) {
    $stmt = $pdo->prepare('SELECT id, name FROM locations_districts WHERE city_id = ? ORDER BY name');
    $stmt->execute([$values['city_id']]);
    $districts = $stmt->fetchAll();
}

// Seçili mahalleler
$neighborhoods = [];
if ($values['district_id']) {
    $stmt = $pdo->prepare('SELECT id, name FROM locations_neighborhoods WHERE district_id = ? ORDER BY name');
    $stmt->execute([$values['district_id']]);
    $neighborhoods = $stmt->fetchAll();
}

$pageTitle = 'Talep Oluştur';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>➕ Halı Yıkama Talebi Oluştur</h1>
    <p>Bilgilerinizi doldurun, firmalar size teklif göndersin</p>
  </div>
</div>

<div class="page-content">
  <div class="container" style="max-width:680px;">

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <span>⚠️</span>
        <div><?= implode('<br>', array_map('e', $errors)) ?></div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Talep Bilgileri</h2>
      </div>
      <div class="card-body">
        <form method="POST" data-once>
          <?= csrfInput() ?>

          <!-- İletişim -->
          <h3 style="font-size:15px;font-weight:700;margin-bottom:16px;color:var(--primary);">📞 İletişim Bilgileri</h3>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label class="form-label">Ad Soyad</label>
              <input type="text" class="form-control" value="<?= e($values['name']) ?>" readonly
                     style="background:#f9fafb;">
            </div>
            <div class="form-group">
              <label class="form-label" for="phone">Telefon *</label>
              <input type="tel" id="phone" name="phone" class="form-control"
                     value="<?= e($values['phone']) ?>" placeholder="05XX XXX XX XX"
                     required maxlength="20">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">E-posta</label>
            <input type="email" class="form-control" value="<?= e($values['email']) ?>" readonly
                   style="background:#f9fafb;">
          </div>

          <!-- Adres -->
          <h3 style="font-size:15px;font-weight:700;margin:24px 0 16px;color:var(--primary);">📍 Adres Bilgileri</h3>

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
              <label class="form-label" for="district_id">İlçe *</label>
              <select id="district_id" name="district_id" class="form-control" required>
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
            <label class="form-label" for="neighborhood_id">Mahalle</label>
            <select id="neighborhood_id" name="neighborhood_id" class="form-control">
              <option value="">Mahalle Seçin (isteğe bağlı)</option>
              <?php foreach ($neighborhoods as $n): ?>
                <option value="<?= $n['id'] ?>" <?= $values['neighborhood_id'] == $n['id'] ? 'selected' : '' ?>>
                  <?= e($n['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="address">Açık Adres *</label>
            <textarea id="address" name="address" class="form-control" required
                      placeholder="Mahalle, cadde, sokak, kapı no..." rows="3"><?= e($values['address']) ?></textarea>
          </div>

          <!-- Halı Detayları -->
          <h3 style="font-size:15px;font-weight:700;margin:24px 0 16px;color:var(--primary);">🏡 Halı Bilgileri</h3>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label class="form-label" for="carpet_count">Halı Adedi *</label>
              <input type="number" id="carpet_count" name="carpet_count" class="form-control"
                     value="<?= (int)$values['carpet_count'] ?>" min="1" max="100" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="total_m2">Toplam Metrekare *</label>
              <input type="number" id="total_m2" name="total_m2" class="form-control"
                     value="<?= e($values['total_m2']) ?>" min="1" max="9999" step="0.5"
                     placeholder="Örn: 25" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="pickup_date">Teslim Alma Tarihi *</label>
            <input type="date" id="pickup_date" name="pickup_date" class="form-control"
                   value="<?= e($values['pickup_date']) ?>"
                   min="<?= date('Y-m-d') ?>" required>
            <div class="form-hint">Halılarınızın kapınızdan teslim alınmasını istediğiniz tarih</div>
          </div>

          <div class="form-group">
            <label class="form-label" for="note">Ek Not</label>
            <textarea id="note" name="note" class="form-control" rows="3" maxlength="500"
                      placeholder="Özel istekleriniz, dikkat edilmesi gerekenler..."><?= e($values['note']) ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px;">
            ✅ Talebi Oluştur
          </button>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
// İlçe/mahalle kaskad için URL'ler (API endpoint)
const apiBase = '<?= APP_URL ?>';

document.getElementById('city_id').addEventListener('change', function() {
  const cityId = this.value;
  const distSel = document.getElementById('district_id');
  const neighSel = document.getElementById('neighborhood_id');
  distSel.innerHTML  = '<option value="">İlçe Seçin</option>';
  neighSel.innerHTML = '<option value="">Mahalle Seçin (isteğe bağlı)</option>';
  if (!cityId) return;
  distSel.disabled = true;
  fetch(apiBase + '/api/districts.php?city_id=' + cityId)
    .then(r => r.json())
    .then(data => {
      data.forEach(d => {
        distSel.innerHTML += `<option value="${d.id}">${d.name}</option>`;
      });
    })
    .finally(() => distSel.disabled = false);
});

document.getElementById('district_id').addEventListener('change', function() {
  const distId = this.value;
  const neighSel = document.getElementById('neighborhood_id');
  neighSel.innerHTML = '<option value="">Mahalle Seçin (isteğe bağlı)</option>';
  if (!distId) return;
  neighSel.disabled = true;
  fetch(apiBase + '/api/neighborhoods.php?district_id=' + distId)
    .then(r => r.json())
    .then(data => {
      data.forEach(n => {
        neighSel.innerHTML += `<option value="${n.id}">${n.name}</option>`;
      });
    })
    .finally(() => neighSel.disabled = false);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
