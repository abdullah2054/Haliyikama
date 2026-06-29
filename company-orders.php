<?php
/**
 * Firma - Sipariş Yönetimi
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/notification-functions.php';
require_once __DIR__ . '/includes/wallet-functions.php';

requireLogin();
$user = currentUser();
checkUserStatus();
requireRole('company');

$pdo     = getDB();
$company = getCompanyByUserId($user['id']);

if (!$company || $company['status'] !== 'active') {
    header('Location: ' . APP_URL . '/company-panel.php');
    exit;
}

$companyId = (int)$company['id'];

// POST: Durum güncelle veya iptal et
if (isPost() && isset($_POST['update_status'])) {
    verifyCsrf();
    $orderId   = (int)$_POST['order_id'];
    $newStatus = $_POST['new_status'] ?? '';
    $note      = trim($_POST['note'] ?? '');

    $allowedNext = [
        'accepted'   => ['picked_up', 'cancelled'],
        'picked_up'  => ['washing', 'cancelled'],
        'washing'    => ['drying'],
        'drying'     => ['packaging'],
        'packaging'  => ['delivering'],
        'delivering' => ['delivered'],
    ];

    // Siparişin bu firmaya ait olduğunu doğrula
    $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND company_id = ?');
    $orderStmt->execute([$orderId, $companyId]);
    $order = $orderStmt->fetch();

    if ($order && isset($allowedNext[$order['status']]) && in_array($newStatus, $allowedNext[$order['status']])) {
        $cancelledBy = ($newStatus === 'cancelled') ? 'company' : null;
        $pdo->prepare(
            'UPDATE orders SET status = ?, cancelled_by = ?, cancel_reason = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$newStatus, $cancelledBy, ($newStatus === 'cancelled' ? ($note ?: 'Firma tarafından iptal edildi') : null), $orderId]);

        $pdo->prepare(
            'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)'
        )->execute([$orderId, $newStatus, $note ?: null, $user['id']]);

        // İptal: firma kaynaklı - ücret iade edilmez
        if ($newStatus === 'cancelled') {
            $pdo->prepare('UPDATE orders SET fee_status = "waived" WHERE id = ? AND fee_status = "charged"')
                ->execute([$orderId]);
        }

        // Teslim: ücret kesinleşti (zaten kesildi)
        sendOrderNotification($orderId, $newStatus, $user['id']);

        $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Sipariş durumu güncellendi.'];
    } else {
        $_SESSION['flash'][] = ['type' => 'error', 'text' => 'Geçersiz durum geçişi.'];
    }
    header('Location: ' . APP_URL . '/company-orders.php?id=' . $orderId);
    exit;
}

// Detay görünümü
$detailOrder = null;
if (isset($_GET['id'])) {
    $dStmt = $pdo->prepare(
        'SELECT o.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email,
                ci.name AS city_name, d.name AS district_name, n.name AS neighborhood_name
         FROM orders o
         JOIN users u ON u.id = o.customer_id
         LEFT JOIN locations_cities ci ON ci.id = o.city_id
         LEFT JOIN locations_districts d ON d.id = o.district_id
         LEFT JOIN locations_neighborhoods n ON n.id = o.neighborhood_id
         WHERE o.id = ? AND o.company_id = ?'
    );
    $dStmt->execute([(int)$_GET['id'], $companyId]);
    $detailOrder = $dStmt->fetch();

    if ($detailOrder) {
        $histStmt = $pdo->prepare('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC');
        $histStmt->execute([$detailOrder['id']]);
        $detailHistory = $histStmt->fetchAll();
    }
}

// Sipariş listesi
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 10;
$filter = $_GET['status'] ?? '';

$where  = 'WHERE o.company_id = ?';
$params = [$companyId];
if ($filter && array_key_exists($filter, ORDER_STATUSES)) {
    $where   .= ' AND o.status = ?';
    $params[] = $filter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $per, $page);

$listStmt = $pdo->prepare(
    "SELECT o.*, u.name AS customer_name, ci.name AS city_name, d.name AS district_name
     FROM orders o
     JOIN users u ON u.id = o.customer_id
     LEFT JOIN locations_cities ci ON ci.id = o.city_id
     LEFT JOIN locations_districts d ON d.id = o.district_id
     $where
     ORDER BY o.updated_at DESC
     LIMIT ? OFFSET ?"
);
$listStmt->execute([...$params, $per, $pag['offset']]);
$orders = $listStmt->fetchAll();

$pageTitle = 'Siparişler';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>📋 Siparişler</h1>
    <p><?= e($company['company_name']) ?></p>
  </div>
</div>

<div class="page-content">
  <div class="container">
  <?php if ($detailOrder): ?>

    <!-- Detay Görünümü -->
    <div style="margin-bottom:16px;">
      <a href="<?= APP_URL ?>/company-orders.php" class="btn btn-sm btn-outline-primary">← Listeye Dön</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">
      <div>
        <!-- Sipariş Bilgisi -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h2 class="card-title"><?= e($detailOrder['tracking_code']) ?></h2>
            <span class="badge badge-<?= orderStatusColor($detailOrder['status']) ?>">
              <?= e(orderStatusLabel($detailOrder['status'])) ?>
            </span>
          </div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:14px;">
              <div><strong>Müşteri:</strong><br><?= e($detailOrder['customer_name']) ?></div>
              <div><strong>Telefon:</strong><br><a href="tel:<?= e($detailOrder['customer_phone']) ?>"><?= e($detailOrder['customer_phone']) ?></a></div>
              <div><strong>E-posta:</strong><br><?= e($detailOrder['customer_email']) ?></div>
              <div><strong>Halı:</strong><br><?= (int)$detailOrder['carpet_count'] ?> adet / <?= e($detailOrder['total_m2']) ?> m²</div>
              <div>
                <strong>Adres:</strong><br>
                <?= e($detailOrder['city_name']) ?><?= $detailOrder['district_name'] ? ', ' . e($detailOrder['district_name']) : '' ?><br>
                <?= e($detailOrder['address']) ?>
              </div>
              <div>
                <strong>Teslim Alma:</strong><br><?= formatDateTR($detailOrder['pickup_date']) ?>
                <?php if ($detailOrder['estimated_delivery_date']): ?>
                  <br><strong>Tahmini Teslim:</strong><br><?= formatDateTR($detailOrder['estimated_delivery_date']) ?>
                <?php endif; ?>
              </div>
              <?php if ($detailOrder['note']): ?>
                <div style="grid-column:1/-1"><strong>Müşteri Notu:</strong><br><?= e($detailOrder['note']) ?></div>
              <?php endif; ?>
              <?php if ($detailOrder['total_price'] > 0): ?>
                <div><strong>Fiyat:</strong><br><span style="color:var(--success);font-weight:700;"><?= formatMoney($detailOrder['total_price']) ?></span></div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Durum Geçmişi -->
        <div class="card">
          <div class="card-header"><h2 class="card-title">📜 Durum Geçmişi</h2></div>
          <div class="card-body">
            <ul class="timeline">
              <?php foreach ($detailHistory ?? [] as $idx => $h): ?>
                <?php
                $isCancelled = $h['status'] === 'cancelled';
                $isLast      = $idx === count($detailHistory) - 1;
                $dotClass    = $isCancelled ? 'cancel' : ($isLast ? 'active' : 'done');
                ?>
                <li class="timeline-item">
                  <div class="timeline-dot <?= $dotClass ?>">✓</div>
                  <div class="timeline-content">
                    <strong><?= e(orderStatusLabel($h['status'])) ?></strong>
                    <?php if ($h['note']): ?><br><span style="font-size:12px;color:var(--text-muted);"><?= e($h['note']) ?></span><?php endif; ?>
                    <br><span><?= formatDateTimeTR($h['created_at']) ?></span>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>

      <!-- Mesajlaşma -->
      <div style="margin-bottom:16px;">
        <a href="<?= APP_URL ?>/chat.php?order_id=<?= (int)$detailOrder['id'] ?>&company_id=<?= $companyId ?>"
           class="btn btn-primary btn-full">💬 Müşteri ile Mesajlaş</a>
      </div>

      <!-- Durum Güncelleme Paneli -->
      <?php
      $nextStatuses = [
        'accepted'   => ['picked_up' => 'Halıları Teslim Al', 'cancelled' => 'İptal Et'],
        'picked_up'  => ['washing' => 'Yıkamaya Başla', 'cancelled' => 'İptal Et'],
        'washing'    => ['drying' => 'Kurutmaya Al'],
        'drying'     => ['packaging' => 'Paketlemeye Başla'],
        'packaging'  => ['delivering' => 'Teslimata Çıkar'],
        'delivering' => ['delivered' => 'Teslim Edildi Olarak İşaretle'],
      ];
      $currentStatus = $detailOrder['status'];
      ?>
      <div>
        <?php if (isset($nextStatuses[$currentStatus])): ?>
          <div class="card">
            <div class="card-header"><h2 class="card-title">⚡ Durum Güncelle</h2></div>
            <div class="card-body">
              <form method="POST" data-once>
                <?= csrfInput() ?>
                <input type="hidden" name="order_id" value="<?= (int)$detailOrder['id'] ?>">
                <div class="form-group">
                  <label class="form-label">Not (isteğe bağlı)</label>
                  <textarea name="note" class="form-control" rows="2" placeholder="Müşteriye gönderilecek not..."></textarea>
                </div>
                <?php foreach ($nextStatuses[$currentStatus] as $status => $label): ?>
                  <button type="submit" name="update_status" value="1"
                          onclick="document.querySelector('[name=new_status]').value='<?= $status ?>'"
                          class="btn btn-full btn-<?= $status === 'cancelled' ? 'danger' : ($status === 'delivered' ? 'success' : 'primary') ?>"
                          style="margin-bottom:10px;"
                          <?= $status === 'cancelled' ? 'data-confirm="Siparişi iptal etmek istediğinizden emin misiniz?"' : '' ?>>
                    <?= e($label) ?>
                  </button>
                <?php endforeach; ?>
                <input type="hidden" name="new_status" value="">
              </form>
            </div>
          </div>
        <?php else: ?>
          <div class="card" style="padding:20px;text-align:center;">
            <span class="badge badge-<?= orderStatusColor($currentStatus) ?>" style="font-size:16px;padding:10px 20px;">
              <?= e(orderStatusLabel($currentStatus)) ?>
            </span>
          </div>
        <?php endif; ?>
      </div>
    </div>

  <?php else: ?>

    <!-- Liste Görünümü -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
      <a href="?" class="btn btn-sm <?= !$filter ? 'btn-dark' : 'btn-outline-primary' ?>">Tümü</a>
      <?php foreach (ORDER_STATUSES as $k => $v): ?>
        <a href="?status=<?= $k ?>" class="btn btn-sm <?= $filter === $k ? 'btn-dark' : 'btn-outline-primary' ?>">
          <?= e($v) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <h4>Sipariş bulunamadı</h4>
        <p>Bölgenizdeki taleplere teklif vererek sipariş alın.</p>
        <a href="<?= APP_URL ?>/company-offers.php" class="btn btn-primary" style="margin-top:12px;">📥 Taleplere Git</a>
      </div>
    <?php else: ?>
      <?php foreach ($orders as $o): ?>
        <div class="order-card">
          <div>
            <div class="tracking"><?= e($o['tracking_code']) ?></div>
            <div class="order-meta">
              <span>👤 <?= e($o['customer_name']) ?></span>
              <span>📍 <?= e($o['city_name']) ?><?= $o['district_name'] ? ', ' . e($o['district_name']) : '' ?></span>
              <span>🏡 <?= (int)$o['carpet_count'] ?> Halı / <?= e($o['total_m2']) ?> m²</span>
              <?php if ($o['total_price'] > 0): ?>
                <span>💰 <?= formatMoney($o['total_price']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
            <span class="badge badge-<?= orderStatusColor($o['status']) ?>">
              <?= e(orderStatusLabel($o['status'])) ?>
            </span>
            <a href="?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-primary">Detay →</a>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- Sayfalama -->
      <?php if ($pag['total_pages'] > 1): ?>
        <div class="pagination">
          <?php for ($i = 1; $i <= $pag['total_pages']; $i++): ?>
            <?php if ($i === $page): ?>
              <span class="current"><?= $i ?></span>
            <?php else: ?>
              <a href="?page=<?= $i ?>&status=<?= e($filter) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
