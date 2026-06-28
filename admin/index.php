<?php
/**
 * Admin Ana Panel
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

requireAdmin();
$user = currentUser();

// Varsayılan şifre uyarısı
$defaultPwHash = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$usingDefault  = password_verify('password', $defaultPwHash);

$pdo = getDB();

// İstatistikler
$userCount     = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer"')->fetchColumn();
$companyCount  = $pdo->query('SELECT COUNT(*) FROM companies WHERE status = "active"')->fetchColumn();
$pendingCos    = $pdo->query('SELECT COUNT(*) FROM companies WHERE status = "pending"')->fetchColumn();
$orderCount    = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$activeOrders  = $pdo->query('SELECT COUNT(*) FROM orders WHERE status NOT IN ("delivered","cancelled")')->fetchColumn();
$totalRevenue  = $pdo->query('SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type = "debit"')->fetchColumn();

// Son firmalar (onay bekleyen)
$pendingCoList = $pdo->query(
    'SELECT c.*, u.email AS user_email FROM companies c JOIN users u ON u.id = c.user_id
     WHERE c.status = "pending" ORDER BY c.created_at DESC LIMIT 5'
)->fetchAll();

// Son siparişler
$recentOrders = $pdo->query(
    'SELECT o.*, u.name AS customer_name, c.company_name,
            ci.name AS city_name
     FROM orders o
     JOIN users u ON u.id = o.customer_id
     LEFT JOIN companies c ON c.id = o.company_id
     LEFT JOIN locations_cities ci ON ci.id = o.city_id
     ORDER BY o.created_at DESC LIMIT 8'
)->fetchAll();

$pageTitle = 'Admin Panel';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>🛡️ Admin Paneli</h1>
    <p>Sistem yönetimi</p>
  </div>
</div>

<div class="page-content">
  <div class="container">

    <!-- Güvenlik Uyarısı -->
    <?php if ($usingDefault): ?>
      <div class="alert alert-danger">
        🚨 <strong>GÜVENLİK UYARISI:</strong> Varsayılan admin şifresi kullanılıyor!
        <a href="<?= APP_URL ?>/profile.php" style="color:inherit;font-weight:700;">Hemen değiştirin →</a>
      </div>
    <?php endif; ?>

    <?php if ((int)$pendingCos > 0): ?>
      <div class="alert alert-warning">
        ⏳ <?= (int)$pendingCos ?> firma başvurusu onay bekliyor.
        <a href="<?= APP_URL ?>/admin/companies.php?status=pending" style="color:inherit;font-weight:700;">İncele →</a>
      </div>
    <?php endif; ?>

    <!-- İstatistik Kartları -->
    <div class="stats-grid">
      <div class="stat-card">
        <span class="stat-val"><?= number_format($userCount) ?></span>
        <div class="stat-label">Kayıtlı Müşteri</div>
      </div>
      <div class="stat-card">
        <span class="stat-val"><?= number_format($companyCount) ?></span>
        <div class="stat-label">Aktif Firma</div>
      </div>
      <div class="stat-card">
        <span class="stat-val"><?= number_format($orderCount) ?></span>
        <div class="stat-label">Toplam Sipariş</div>
      </div>
      <div class="stat-card">
        <span class="stat-val"><?= number_format($activeOrders) ?></span>
        <div class="stat-label">Aktif Sipariş</div>
      </div>
      <div class="stat-card">
        <span class="stat-val"><?= formatMoney($totalRevenue) ?></span>
        <div class="stat-label">Toplam Platform Geliri</div>
      </div>
    </div>

    <!-- Hızlı Bağlantılar -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px;">
      <a href="<?= APP_URL ?>/admin/companies.php" class="btn btn-primary">🏢 Firmalar</a>
      <a href="<?= APP_URL ?>/admin/orders.php"    class="btn btn-info">📦 Siparişler</a>
      <a href="<?= APP_URL ?>/admin/users.php"     class="btn btn-secondary">👥 Kullanıcılar</a>
      <a href="<?= APP_URL ?>/admin/wallet.php"    class="btn btn-success">💰 Bakiye</a>
      <a href="<?= APP_URL ?>/admin/reviews.php"   class="btn btn-warning">⭐ Yorumlar</a>
      <a href="<?= APP_URL ?>/admin/settings.php"  class="btn btn-dark">⚙️ Ayarlar</a>
    </div>

    <!-- Onay Bekleyen Firmalar -->
    <?php if (!empty($pendingCoList)): ?>
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header">
        <h2 class="card-title">⏳ Onay Bekleyen Firmalar</h2>
        <a href="<?= APP_URL ?>/admin/companies.php?status=pending" class="btn btn-sm btn-outline-primary">Tümü</a>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Firma</th><th>Yetkili</th><th>E-posta</th><th>Tarih</th><th>İşlem</th></tr></thead>
            <tbody>
              <?php foreach ($pendingCoList as $c): ?>
                <tr>
                  <td><strong><?= e($c['company_name']) ?></strong></td>
                  <td><?= e($c['authorized_name']) ?></td>
                  <td><?= e($c['user_email']) ?></td>
                  <td><?= formatDateTR($c['created_at']) ?></td>
                  <td><a href="<?= APP_URL ?>/admin/companies.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-primary">İncele</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Son Siparişler -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">📦 Son Siparişler</h2>
        <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-sm btn-outline-primary">Tümü</a>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Takip Kodu</th><th>Müşteri</th><th>Firma</th><th>İl</th><th>Durum</th><th>Tarih</th></tr></thead>
            <tbody>
              <?php foreach ($recentOrders as $o): ?>
                <tr>
                  <td><strong><?= e($o['tracking_code']) ?></strong></td>
                  <td><?= e($o['customer_name']) ?></td>
                  <td><?= $o['company_name'] ? e($o['company_name']) : '<span style="color:var(--text-muted)">Atanmadı</span>' ?></td>
                  <td><?= e($o['city_name'] ?? '-') ?></td>
                  <td><span class="badge badge-<?= orderStatusColor($o['status']) ?>"><?= e(orderStatusLabel($o['status'])) ?></span></td>
                  <td><?= formatDateTR($o['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
