<?php
/**
 * Takip Kodu ile Sipariş Sorgulama (Giriş Gerektirmez)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$pdo      = getDB();
$order    = null;
$history  = [];
$error    = '';
$code     = strtoupper(trim($_GET['kod'] ?? ''));

if ($code) {
    $stmt = $pdo->prepare(
        'SELECT o.*, c.company_name, c.phone AS company_phone,
                ci.name AS city_name, d.name AS district_name
         FROM orders o
         LEFT JOIN companies c ON c.id = o.company_id
         LEFT JOIN locations_cities ci ON ci.id = o.city_id
         LEFT JOIN locations_districts d ON d.id = o.district_id
         WHERE o.tracking_code = ?'
    );
    $stmt->execute([$code]);
    $order = $stmt->fetch();

    if ($order) {
        $hStmt = $pdo->prepare(
            'SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC'
        );
        $hStmt->execute([$order['id']]);
        $history = $hStmt->fetchAll();
    } else {
        $error = 'Bu takip koduna ait sipariş bulunamadı. Lütfen kodunuzu kontrol edin.';
    }
}

$pageTitle = 'Sipariş Takip';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>📦 Sipariş Takip</h1>
    <p>Takip kodunuzla siparişinizin durumunu sorgulayın</p>
  </div>
</div>

<div class="page-content">
  <div class="container" style="max-width:680px;">

    <!-- Arama Formu -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-body">
        <form method="GET" class="track-form">
          <input type="text" name="kod" class="form-control"
                 placeholder="Takip kodunuzu girin (Örn: HK-2026-000145)"
                 value="<?= e($code) ?>"
                 style="text-transform:uppercase;font-size:16px;font-weight:600;"
                 maxlength="20" autocomplete="off">
          <button type="submit" class="btn btn-primary">Sorgula</button>
        </form>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($order): ?>
      <!-- Sipariş Özeti -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
          <h2 class="card-title">Sipariş: <?= e($order['tracking_code']) ?></h2>
          <span class="badge badge-<?= orderStatusColor($order['status']) ?>" style="font-size:13px;">
            <?= e(orderStatusLabel($order['status'])) ?>
          </span>
        </div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:14px;">
            <?php if ($order['company_name']): ?>
              <div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px;">Firma</div>
                <strong>🏢 <?= e($order['company_name']) ?></strong>
              </div>
            <?php endif; ?>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px;">Güncel Durum</div>
              <strong><?= e(orderStatusLabel($order['status'])) ?></strong>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px;">Teslim Alma</div>
              <strong><?= formatDateTR($order['pickup_date']) ?></strong>
            </div>
            <?php if ($order['estimated_delivery_date']): ?>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px;">Tahmini Teslim</div>
              <strong><?= formatDateTR($order['estimated_delivery_date']) ?></strong>
            </div>
            <?php endif; ?>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px;">Konum</div>
              <?= e($order['city_name']) ?><?= $order['district_name'] ? ', ' . e($order['district_name']) : '' ?>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px;">Halı</div>
              <?= (int)$order['carpet_count'] ?> adet / <?= e($order['total_m2']) ?> m²
            </div>
          </div>
        </div>
      </div>

      <!-- Durum Geçmişi -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h2 class="card-title">📜 Durum Geçmişi</h2></div>
        <div class="card-body">
          <?php if (empty($history)): ?>
            <p style="color:var(--text-muted);">Henüz durum kaydı yok.</p>
          <?php else: ?>
            <ul class="timeline">
              <?php foreach ($history as $idx => $h): ?>
                <?php
                $isCancelled = $h['status'] === 'cancelled';
                $isLast      = $idx === count($history) - 1;
                $dotClass    = $isCancelled ? 'cancel' : ($isLast ? 'active' : 'done');
                $icons = [
                  'pending'    => '📝',
                  'accepted'   => '✅',
                  'picked_up'  => '🚚',
                  'washing'    => '🧺',
                  'drying'     => '💨',
                  'packaging'  => '📦',
                  'delivering' => '🚛',
                  'delivered'  => '🏠',
                  'cancelled'  => '❌',
                ];
                ?>
                <li class="timeline-item">
                  <div class="timeline-dot <?= $dotClass ?>">
                    <?= $icons[$h['status']] ?? '•' ?>
                  </div>
                  <div class="timeline-content">
                    <strong><?= e(orderStatusLabel($h['status'])) ?></strong>
                    <?php if ($h['note']): ?>
                      <br><span style="font-size:12px;color:var(--text-muted);"><?= e($h['note']) ?></span>
                    <?php endif; ?>
                    <br><span><?= formatDateTimeTR($h['created_at']) ?></span>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($order['status'] === 'delivered'): ?>
        <div class="alert alert-success">
          ✅ Siparişiniz teslim edildi! Hizmetimizi tercih ettiğiniz için teşekkür ederiz.
        </div>
      <?php elseif ($order['status'] === 'cancelled'): ?>
        <div class="alert alert-danger">
          ❌ Bu sipariş iptal edildi<?= $order['cancel_reason'] ? ': ' . e($order['cancel_reason']) : '.' ?>
        </div>
      <?php endif; ?>

      <div style="text-align:center;margin-top:16px;">
        <a href="<?= APP_URL ?>/track.php?kod=<?= urlencode($order['tracking_code']) ?>"
           class="btn btn-outline-primary btn-sm" id="copyLink">
          🔗 Linki Kopyala
        </a>
      </div>

    <?php elseif (!$code): ?>
      <div class="card" style="padding:32px;text-align:center;">
        <div style="font-size:48px;margin-bottom:12px;">📦</div>
        <h3 style="margin-bottom:8px;">Siparişinizi Takip Edin</h3>
        <p style="color:var(--text-muted);font-size:14px;">
          Size iletilen takip kodunu yukarıdaki alana girin.<br>
          Takip kodu e-posta bildiriminizde yer almaktadır.
        </p>
      </div>
    <?php endif; ?>

    <?php if (!isLoggedIn()): ?>
      <div style="margin-top:20px;text-align:center;font-size:13px;color:var(--text-muted);">
        <a href="<?= APP_URL ?>/login.php">Giriş yaparak</a> daha detaylı takip yapabilirsiniz.
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
document.getElementById('copyLink')?.addEventListener('click', function(e) {
  e.preventDefault();
  navigator.clipboard.writeText(window.location.href).then(function() {
    const btn = document.getElementById('copyLink');
    btn.textContent = '✅ Kopyalandı!';
    setTimeout(() => btn.textContent = '🔗 Linki Kopyala', 2000);
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
