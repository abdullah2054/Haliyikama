<?php
/**
 * Firma - Bakiye Sayfası
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
    header('Location: ' . APP_URL . '/company-panel.php');
    exit;
}

$companyId = (int)$company['id'];
$page      = max(1, (int)($_GET['page'] ?? 1));
$walletData = getWalletHistory($companyId, $page);
$balance    = getCompanyBalance($companyId);
$platformFee = (float)getSetting('platform_fee', '25.00');

// Özet istatistikler
$summaryStmt = $pdo->prepare(
    'SELECT
       SUM(CASE WHEN type = "credit" THEN amount ELSE 0 END) AS total_credit,
       SUM(CASE WHEN type = "debit"  THEN amount ELSE 0 END) AS total_debit,
       SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) AS total_refund
     FROM wallet_transactions WHERE company_id = ?'
);
$summaryStmt->execute([$companyId]);
$summary = $summaryStmt->fetch();

$pageTitle = 'Bakiye';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>💰 Bakiye</h1>
    <p>Bakiye durumu ve hareket geçmişi</p>
  </div>
</div>

<div class="page-content">
  <div class="container">

    <!-- Bakiye Kartları -->
    <div class="stats-grid" style="margin-bottom:24px;">
      <div class="stat-card" style="background:linear-gradient(135deg,#1a3a5c,#2563eb);color:#fff;">
        <span class="stat-val" style="color:#fff;"><?= formatMoney($balance) ?></span>
        <div class="stat-label" style="color:rgba(255,255,255,.8);">Mevcut Bakiye</div>
      </div>
      <div class="stat-card">
        <span class="stat-val" style="color:var(--success);"><?= formatMoney($summary['total_credit'] ?? 0) ?></span>
        <div class="stat-label">Toplam Yükleme</div>
      </div>
      <div class="stat-card">
        <span class="stat-val" style="color:var(--danger);"><?= formatMoney($summary['total_debit'] ?? 0) ?></span>
        <div class="stat-label">Toplam Kesinti</div>
      </div>
      <div class="stat-card">
        <span class="stat-val" style="color:var(--info);"><?= formatMoney($summary['total_refund'] ?? 0) ?></span>
        <div class="stat-label">Toplam İade</div>
      </div>
    </div>

    <!-- Bakiye Yükleme Bilgisi -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header"><h2 class="card-title">💳 Bakiye Yükleme</h2></div>
      <div class="card-body">
        <div class="alert alert-info">
          ℹ️ Bakiye yükleme için lütfen yönetici ile iletişime geçin.
          Her sipariş kabul edildiğinde <strong><?= formatMoney($platformFee) ?></strong> platform hizmet bedeli bakiyenizden kesilir.
        </div>
        <p style="font-size:13px;color:var(--text-muted);">
          Bakiyeniz sipariş başı platform ücretinin altına düştüğünde yeni talep kabul edemezsiniz.
        </p>
        <?php if ($balance < $platformFee): ?>
          <div class="alert alert-warning">⚠️ Bakiyeniz yetersiz! Yeni talep kabul edemezsiniz.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Hareket Geçmişi -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">📊 Hareket Geçmişi</h2>
        <span style="font-size:13px;color:var(--text-muted);"><?= $walletData['total'] ?> işlem</span>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if (empty($walletData['rows'])): ?>
          <div class="empty-state">
            <div class="empty-icon">💳</div>
            <h4>Henüz işlem yok</h4>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Tarih</th>
                  <th>İşlem</th>
                  <th>Sipariş</th>
                  <th>Açıklama</th>
                  <th style="text-align:right;">Tutar</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($walletData['rows'] as $tx): ?>
                  <?php
                  $typeLabels = [
                    'credit' => ['label' => 'Yükleme',  'color' => 'success'],
                    'debit'  => ['label' => 'Kesinti',  'color' => 'danger'],
                    'block'  => ['label' => 'Bloke',    'color' => 'warning'],
                    'refund' => ['label' => 'İade',     'color' => 'info'],
                  ];
                  $type = $typeLabels[$tx['type']] ?? ['label' => $tx['type'], 'color' => 'secondary'];
                  $sign = in_array($tx['type'], ['credit', 'refund']) ? '+' : '-';
                  $color = in_array($tx['type'], ['credit', 'refund']) ? 'var(--success)' : 'var(--danger)';
                  ?>
                  <tr>
                    <td><?= formatDateTimeTR($tx['created_at']) ?></td>
                    <td><span class="badge badge-<?= $type['color'] ?>"><?= $type['label'] ?></span></td>
                    <td><?= $tx['tracking_code'] ? '<a href="' . APP_URL . '/company-orders.php?id=' . (int)$tx['order_id'] . '">' . e($tx['tracking_code']) . '</a>' : '-' ?></td>
                    <td style="font-size:12px;"><?= e($tx['description'] ?? '-') ?></td>
                    <td style="text-align:right;font-weight:700;color:<?= $color ?>;">
                      <?= $sign ?><?= formatMoney($tx['amount']) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Sayfalama -->
          <?php if ($walletData['total_pages'] > 1): ?>
            <div class="pagination">
              <?php for ($i = 1; $i <= $walletData['total_pages']; $i++): ?>
                <?php if ($i === $page): ?>
                  <span class="current"><?= $i ?></span>
                <?php else: ?>
                  <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
              <?php endfor; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
