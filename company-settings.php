<?php
/**
 * Firma - Profil ve Hizmet Bölgesi Ayarları
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

requireLogin();
$user = currentUser();
checkUserStatus();
requireRole('company');

$pdo     = getDB();
$company = getCompanyByUserId($user['id']);

if (!$company) {
    header('Location: ' . APP_URL . '/logout.php');
    exit;
}

$companyId = (int)$company['id'];
$errors    = [];
$success   = '';

// POST: Firma bilgileri güncelle
if (isPost() && isset($_POST['update_company'])) {
    verifyCsrf();

    $companyName    = trim($_POST['company_name']    ?? '');
    $authorizedName = trim($_POST['authorized_name'] ?? '');
    $phone          = trim($_POST['phone']           ?? '');
    $email          = trim($_POST['email']           ?? '');
    $cityId         = (int)($_POST['city_id']        ?? 0);
    $districtId     = (int)($_POST['district_id']    ?? 0);
    $address        = trim($_POST['address']         ?? '');
    $description    = trim($_POST['description']     ?? '');
    $pricePerM2     = (float)($_POST['price_per_m2'] ?? 0);
    $minOrderPrice  = (float)($_POST['min_order_price'] ?? 0);

    if (!$companyName)    $errors[] = 'Firma adı zorunludur.';
    if (!$authorizedName) $errors[] = 'Yetkili adı zorunludur.';
    if (!$phone)          $errors[] = 'Telefon zorunludur.';

    if (empty($errors)) {
        $logoPath = $company['logo'];
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $newLogo = uploadImage($_FILES['logo'], 'logos');
            if ($newLogo) $logoPath = $newLogo;
        }

        $pdo->prepare(
            'UPDATE companies SET company_name=?, authorized_name=?, phone=?, email=?,
             city_id=?, district_id=?, address=?, description=?,
             price_per_m2=?, min_order_price=?, logo=?, updated_at=NOW()
             WHERE id=?'
        )->execute([
            $companyName, $authorizedName, $phone, $email,
            $cityId ?: null, $districtId ?: null, $address, $description,
            $pricePerM2, $minOrderPrice, $logoPath,
            $companyId,
        ]);
        $success = 'Firma bilgileri güncellendi.';
        $company = getCompanyByUserId($user['id']);
    }
}

// POST: Hizmet bölgesi ekle
if (isPost() && isset($_POST['add_area'])) {
    verifyCsrf();
    $areaCityId  = (int)($_POST['area_city_id']         ?? 0);
    $areaDistId  = (int)($_POST['area_district_id']     ?? 0);
    $areaNeighId = (int)($_POST['area_neighborhood_id'] ?? 0);

    if ($areaCityId) {
        // Aynı bölge var mı?
        $chk = $pdo->prepare(
            'SELECT id FROM company_service_areas WHERE company_id=? AND city_id=?
             AND (district_id <=> ?) AND (neighborhood_id <=> ?)'
        );
        $chk->execute([$companyId, $areaCityId, $areaDistId ?: null, $areaNeighId ?: null]);
        if (!$chk->fetch()) {
            $pdo->prepare(
                'INSERT INTO company_service_areas (company_id, city_id, district_id, neighborhood_id)
                 VALUES (?, ?, ?, ?)'
            )->execute([$companyId, $areaCityId, $areaDistId ?: null, $areaNeighId ?: null]);
            $success = 'Hizmet bölgesi eklendi.';
        } else {
            $errors[] = 'Bu bölge zaten ekli.';
        }
    }
}

// POST: Hizmet bölgesi sil
if (isPost() && isset($_POST['remove_area'])) {
    verifyCsrf();
    $areaId = (int)$_POST['area_id'];
    $pdo->prepare('DELETE FROM company_service_areas WHERE id=? AND company_id=?')
        ->execute([$areaId, $companyId]);
    $success = 'Hizmet bölgesi silindi.';
}

// Veriler
$cities = $pdo->query('SELECT id, name FROM locations_cities ORDER BY name')->fetchAll();

$serviceAreas = $pdo->prepare(
    'SELECT csa.*, ci.name AS city_name, d.name AS district_name, n.name AS neighborhood_name
     FROM company_service_areas csa
     JOIN locations_cities ci ON ci.id = csa.city_id
     LEFT JOIN locations_districts d ON d.id = csa.district_id
     LEFT JOIN locations_neighborhoods n ON n.id = csa.neighborhood_id
     WHERE csa.company_id = ?
     ORDER BY ci.name, d.name, n.name'
);
$serviceAreas->execute([$companyId]);
$serviceAreas = $serviceAreas->fetchAll();

// Firma adres ilçeleri
$companyDistricts = [];
if ($company['city_id']) {
    $stmt = $pdo->prepare('SELECT id, name FROM locations_districts WHERE city_id=? ORDER BY name');
    $stmt->execute([$company['city_id']]);
    $companyDistricts = $stmt->fetchAll();
}

$pageTitle = 'Firma Ayarları';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>⚙️ Firma Ayarları</h1>
    <p>Firma bilgilerini ve hizmet bölgelerini yönetin</p>
  </div>
</div>

<div class="page-content">
  <div class="container">

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">⚠️ <?= implode('<br>', array_map('e', $errors)) ?></div>
    <?php endif; ?>

    <!-- Firma Bilgileri -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header"><h2 class="card-title">🏢 Firma Bilgileri</h2></div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" data-once>
          <?= csrfInput() ?>

          <?php if ($company['logo']): ?>
            <div style="margin-bottom:16px;">
              <img src="<?= UPLOAD_URL . e($company['logo']) ?>" alt="Logo"
                   style="max-width:100px;border-radius:8px;border:1px solid var(--border);">
            </div>
          <?php endif; ?>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label class="form-label" for="company_name">Firma Adı *</label>
              <input type="text" id="company_name" name="company_name" class="form-control"
                     value="<?= e($company['company_name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="authorized_name">Yetkili *</label>
              <input type="text" id="authorized_name" name="authorized_name" class="form-control"
                     value="<?= e($company['authorized_name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="co_phone">Telefon *</label>
              <input type="tel" id="co_phone" name="phone" class="form-control"
                     value="<?= e($company['phone']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="co_email">E-posta</label>
              <input type="email" id="co_email" name="email" class="form-control"
                     value="<?= e($company['email']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="price_per_m2">m² Fiyatı (TL)</label>
              <input type="number" id="price_per_m2" name="price_per_m2" class="form-control"
                     value="<?= e($company['price_per_m2']) ?>" min="0" step="0.5">
            </div>
            <div class="form-group">
              <label class="form-label" for="min_order_price">Min. Sipariş (TL)</label>
              <input type="number" id="min_order_price" name="min_order_price" class="form-control"
                     value="<?= e($company['min_order_price']) ?>" min="0">
            </div>
            <div class="form-group">
              <label class="form-label" for="area_city">Firma İli</label>
              <select id="area_city" name="city_id" class="form-control" id="company_city_id">
                <option value="">İl Seçin</option>
                <?php foreach ($cities as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= $company['city_id'] == $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="company_district_id">Firma İlçesi</label>
              <select name="district_id" class="form-control" id="company_district_id">
                <option value="">İlçe Seçin</option>
                <?php foreach ($companyDistricts as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= $company['district_id'] == $d['id'] ? 'selected' : '' ?>>
                    <?= e($d['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="address">Açık Adres</label>
            <textarea id="address" name="address" class="form-control" rows="2"><?= e($company['address'] ?? '') ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label" for="description">Firma Açıklaması</label>
            <textarea id="description" name="description" class="form-control" rows="3" maxlength="1000"><?= e($company['description'] ?? '') ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label" for="logo">Yeni Logo (isteğe bağlı)</label>
            <input type="file" id="logo" name="logo" class="form-control"
                   accept="image/jpeg,image/png,image/webp" data-preview="logoPreview2">
            <img id="logoPreview2" src="" style="display:none;margin-top:8px;max-width:80px;border-radius:8px;">
          </div>

          <button type="submit" name="update_company" class="btn btn-primary">
            💾 Firma Bilgilerini Kaydet
          </button>
        </form>
      </div>
    </div>

    <!-- Hizmet Bölgeleri -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">📍 Hizmet Bölgeleri</h2></div>
      <div class="card-body">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
          Hizmet verdiğiniz bölgeleri ekleyin. Müşteriler yalnızca bu bölgelerdeki talepleri göreceksiniz.
          İl seçerseniz o ilin tamamına, ilçe seçerseniz sadece o ilçeye hizmet verilecek.
        </p>

        <!-- Bölge Listesi -->
        <?php if ($serviceAreas): ?>
          <div style="margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($serviceAreas as $area): ?>
              <div style="display:inline-flex;align-items:center;gap:6px;background:#f0f4f8;border-radius:20px;padding:6px 14px;font-size:13px;">
                📍 <?= e($area['city_name']) ?>
                <?= $area['district_name'] ? ' › ' . e($area['district_name']) : '' ?>
                <?= $area['neighborhood_name'] ? ' › ' . e($area['neighborhood_name']) : '' ?>
                <form method="POST" style="display:inline;">
                  <?= csrfInput() ?>
                  <input type="hidden" name="area_id" value="<?= (int)$area['id'] ?>">
                  <button type="submit" name="remove_area" style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:14px;padding:0 0 0 4px;"
                          data-confirm="Bu bölgeyi kaldırmak istiyor musunuz?">✕</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-warning">⚠️ Henüz hizmet bölgesi eklemediniz. Bölge eklemeden talepleri göremezsiniz!</div>
        <?php endif; ?>

        <!-- Bölge Ekle Formu -->
        <h4 style="font-size:14px;font-weight:700;margin-bottom:12px;">Yeni Bölge Ekle</h4>
        <form method="POST" data-once>
          <?= csrfInput() ?>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
              <label class="form-label">İl *</label>
              <select name="area_city_id" id="areaCityId" class="form-control" required>
                <option value="">İl Seçin</option>
                <?php foreach ($cities as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">İlçe</label>
              <select name="area_district_id" id="areaDistrictId" class="form-control">
                <option value="">Tüm İlçeler</option>
              </select>
            </div>
            <div class="form-group" style="margin:0;">
              <label class="form-label">Mahalle</label>
              <select name="area_neighborhood_id" id="areaNeighborhoodId" class="form-control">
                <option value="">Tüm Mahalleler</option>
              </select>
            </div>
            <button type="submit" name="add_area" class="btn btn-success">+ Ekle</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
// Firma şehri değiştiğinde ilçeleri getir
document.querySelector('[name="city_id"]')?.addEventListener('change', function() {
  const distSel = document.getElementById('company_district_id');
  distSel.innerHTML = '<option value="">İlçe Seçin</option>';
  if (!this.value) return;
  fetch('<?= APP_URL ?>/api/districts.php?city_id=' + this.value)
    .then(r => r.json())
    .then(data => data.forEach(d => distSel.innerHTML += `<option value="${d.id}">${d.name}</option>`));
});

// Hizmet bölgesi kaskad
document.getElementById('areaCityId').addEventListener('change', function() {
  const distSel = document.getElementById('areaDistrictId');
  const neighSel = document.getElementById('areaNeighborhoodId');
  distSel.innerHTML = '<option value="">Tüm İlçeler</option>';
  neighSel.innerHTML = '<option value="">Tüm Mahalleler</option>';
  if (!this.value) return;
  fetch('<?= APP_URL ?>/api/districts.php?city_id=' + this.value)
    .then(r => r.json())
    .then(data => data.forEach(d => distSel.innerHTML += `<option value="${d.id}">${d.name}</option>`));
});

document.getElementById('areaDistrictId').addEventListener('change', function() {
  const neighSel = document.getElementById('areaNeighborhoodId');
  neighSel.innerHTML = '<option value="">Tüm Mahalleler</option>';
  if (!this.value) return;
  fetch('<?= APP_URL ?>/api/neighborhoods.php?district_id=' + this.value)
    .then(r => r.json())
    .then(data => data.forEach(n => neighSel.innerHTML += `<option value="${n.id}">${n.name}</option>`));
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
