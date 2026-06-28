<?php
/**
 * Firma Ana Panel
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/wallet-functions.php';

requireLogin();
$user = currentUser();
checkUserStatus();
requireRole('company');

$pdo     = getDB();
$company = getCompanyByUserId($user['id']);

if (!$company) {
    $_SESSION['flash'][] = ['type' => 'error', 'text' => 'Firma bilginiz bulunamadı.'];
    header('Location: ' . APP_URL . '/logout.php');
    exit;
}

if ($company['status'] === 'pending') {
    $pageTitle = 'Onay Bekleniyor';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="page-content">
      <div class="container" style="max-width:560px;">
        <div class="card" style="padding:40px;text-align:center;">
          <div style="font-size:48px;margin-bottom:16px;">⏳</div>
          <h2>Başvurunuz İnceleniyor</h2>
          <p style="color:var(--text-muted);margin-top:12px;">
            Admin ekibimiz başvurunuzu inceliyor. Onaylandıktan sonra e-posta ile bilgilendirileceksiniz.
          </p>
        </div>
      </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

if ($company['status'] === 'rejected') {
    $pageTitle = 'Başvuru Reddedildi';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="page-content">
      <div class="container" style="max-width:560px;">
        <div class="card" style="padding:40px;text-align:center;">
          <div style="font-size:48px;margin-bottom:16px;">❌</div>
          <h2>Başvurunuz Reddedildi</h2>
          <p style="color:var(--text-muted);margin-top:12px;">
            Daha fazla bilgi için lütfen bizimle iletişime geçin.
          </p>
        </div>
      </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$companyId = (int)$company['id'];

// İstatistikler
$stats = $pdo->prepare(
    'SELECT
       COUNT(CASE WHEN status NOT IN ("cancelled") THEN 1 END) AS total,
       COUNT(CASE WHEN status = "delivered" THEN 1 END) AS delivered,
       COUNT(CASE WHEN status = "cancelled" THEN 1 END) AS cancelled,
       COUNT(CASE WHEN status NOT IN ("delivered","cancelled","pending") THEN 1 END) AS active
     FROM orders WHERE company_id = ?'
);
$stats->execute([$companyId]);
$stats = $stats->fetch();

// Yeni talepler (hizmet bölgesi)
$newRequests = $pdo->prepare(
    'SELECT COUNT(DISTINCT o.id) AS cnt FROM orders o
     WHERE o.status = "pending" AND o.company_id IS NULL
     AND EXISTS (
       SELECT 1 FROM company_service_areas csa
       WHERE csa.company_id = ?
       AND csa.city_id = o.city_id
       AND (csa.district_id IS NULL OR csa.district_id = o.district_id)
       AND (csa.neighborhood_id IS NULL OR csa.neighborhood_id = o.neighborhood_id OR o.neighborhood_id IS NULL)
     )
     AND NOT EXISTS (SELECT 1 FROM offers of2 WHERE of2.order_id = o.id AND of2.company_id = ?)'
);
$newRequests->execute([$companyId, $companyId]);
$newReqCount = (int)$newRequests->fetchColumn();

// Son aktif siparişler
$activeOrders = $pdo->prepare(
    'SELECT o.*, u.name AS customer_name,
            ci.name AS city_name, d.name AS district_name
     FROM orders o
     JOIN users u ON u.id = o.customer_id
     LEFT JOIN locations_cities ci ON ci.id = o.city_id
     LEFT JOIN locations_districts d ON d.id = o.district_id
     WHERE o.company_id = ? AND o.status NOT IN ("delivered","cancelled")
     ORDER BY o.updated_at DESC LIMIT 5'
);
$activeOrders->execute([$companyId]);
$activeOrders = $activeOrders->fetchAll();

$balance = getCompanyBalance($companyId);
$platformFee = getSetting('platform_fee', '25.00');

$pageTitle = 'Firma Paneli';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>📊 <?= e($company['company_name']) ?></h1>
    <p>Hoş geldiniz, <?= e($user['name']) ?>!</p>
  </div>
</div>

<div class="page-content">
  <div class="container">

    <!-- Durum Uyarısı -->
    <?php if ($company['status'] === 'suspended'): ?>
      <div class="alert alert-danger">⚠️ Hesabınız askıya alınmıştır. Lütfen iletişime geçin.</div>
    <?php endif; ?>

    <!-- İstatistik Kartları -->
    <div class="stats-grid">
      <div class="stat-card">
        <span class="stat-val"><?= formatMoney($balance) ?></span>
        <div class="stat-label">Mevcut Bakiye</div>
      </div>
      <div class="stat-card">
        <span class="stat-val"><?= $newReqCount ?></span>
        <div class="stat-label">Yeni Talep</div>
      </div>
      <div class="stat-card">
        <span class="stat-val"><?= (int)$stats['active'] ?></span>
        <div class="stat-label">Aktif Sipariş</div>
      </div>
      <div class="stat-card">
        <span class="stat-val"><?= (int)$stats['delivered'] ?></span>
        <div class="stat-label">Tamamlanan</div>
      </div>
    </div>

    <!-- Platform Ücreti Uyarısı -->
    <?php if ($balance < (float)$platformFee): ?>
      <div class="alert alert-warning">
        ⚠️ Bakiyeniz sipariş başı platform ücretinin (<?= formatMoney($platformFee) ?>) altında.
        Yeni talep kabul edemezsiniz.
        <a href="<?= APP_URL ?>/company-wallet.php" style="color:inherit;font-weight:700;">Bakiye Yükle →</a>
      </div>
    <?php endif; ?>

    <!-- Hızlı İşlemler -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px;">
      <a href="<?= APP_URL ?>/company-offers.php" class="btn btn-primary">
        📥 Talepler <?php if ($newReqCount > 0): ?><span class="badge badge-danger" style="margin-left:6px;"><?= $newReqCount ?></span><?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/company-orders.php" class="btn btn-info">📋 Siparişler</a>
      <a href="<?= APP_URL ?>/company-wallet.php" class="btn btn-success">💰 Bakiye</a>
      <a href="<?= APP_URL ?>/company-settings.php" class="btn btn-secondary">⚙️ Ayarlar</a>
    </div>

    <!-- Aktif Siparişler -->
    <?php if (!empty($activeOrders)): ?>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">🔄 Aktif Siparişler</h2>
        <a href="<?= APP_URL ?>/company-orders.php" class="btn btn-sm btn-outline-primary">Tümü →</a>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Takip Kodu</th>
                <th>Müşteri</th>
                <th>Konum</th>
                <th>Durum</th>
                <th>İşlem</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activeOrders as $o): ?>
                <tr>
                  <td><strong><?= e($o['tracking_code']) ?></strong></td>
                  <td><?= e($o['customer_name']) ?></td>
                  <td><?= e($o['city_name']) ?><?= $o['district_name'] ? ', ' . e($o['district_name']) : '' ?></td>
                  <td><span class="badge badge-<?= orderStatusColor($o['status']) ?>"><?= e(orderStatusLabel($o['status'])) ?></span></td>
                  <td>
                    <a href="<?= APP_URL ?>/company-orders.php?id=<?= (int)$o['id'] ?>"
                       class="btn btn-sm btn-outline-primary">Detay</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <h4>Aktif sipariş yok</h4>
        <p>Bölgenizdeki yeni taleplere teklif verin.</p>
        <a href="<?= APP_URL ?>/company-offers.php" class="btn btn-primary" style="margin-top:12px;">
          📥 Taleplere Bak
        </a>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
