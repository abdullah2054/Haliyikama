<?php
/**
 * Sipariş Detay Sayfası (Müşteri + Firma erişebilir)
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

$pdo     = getDB();
$orderId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
            c.company_name, c.phone AS company_phone, c.email AS company_email,
            ci.name AS city_name, d.name AS district_name, n.name AS neighborhood_name
     FROM orders o
     JOIN users u ON u.id = o.customer_id
     LEFT JOIN companies c ON c.id = o.company_id
     LEFT JOIN locations_cities ci ON ci.id = o.city_id
     LEFT JOIN locations_districts d ON d.id = o.district_id
     LEFT JOIN locations_neighborhoods n ON n.id = o.neighborhood_id
     WHERE o.id = ?'
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order || !canAccessOrder($order)) {
    http_response_code(404);
    $_SESSION['flash'][] = ['type' => 'error', 'text' => 'Sipariş bulunamadı veya erişim yetkiniz yok.'];
    header('Location: ' . APP_URL . '/my-orders.php');
    exit;
}

// Teklifler (müşteri için)
$offers = [];
if ($user['role'] === 'customer') {
    $stmt = $pdo->prepare(
        'SELECT of.*, c.company_name, c.phone AS co_phone, c.price_per_m2,
                AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
         FROM offers of
         JOIN companies c ON c.id = of.company_id
         LEFT JOIN reviews r ON r.company_id = c.id AND r.status = "active"
         WHERE of.order_id = ?
         GROUP BY of.id
         ORDER BY of.status DESC, of.price ASC'
    );
    $stmt->execute([$orderId]);
    $offers = $stmt->fetchAll();
}

// POST: Teklifi kabul et
if (isPost() && isset($_POST['accept_offer']) && $user['role'] === 'customer') {
    verifyCsrf();
    $offerId = (int)$_POST['offer_id'];

    $offerStmt = $pdo->prepare('SELECT * FROM offers WHERE id = ? AND order_id = ? AND status = "pending"');
    $offerStmt->execute([$offerId, $orderId]);
    $offer = $offerStmt->fetch();

    if ($offer && $order['status'] === 'pending') {
        require_once __DIR__ . '/includes/wallet-functions.php';
        $ok = chargePlatformFee((int)$offer['company_id'], $orderId);
        if (!$ok) {
            $_SESSION['flash'][] = ['type' => 'warn', 'text' => 'Firma bakiyesi yetersiz, bu teklifi kabul edemezsiniz.'];
        } else {
            // Siparişi güncelle
            $pdo->prepare(
                'UPDATE orders SET company_id = ?, status = "accepted", total_price = ?,
                 estimated_delivery_date = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$offer['company_id'], $offer['price'], $offer['estimated_delivery_date'], $orderId]);

            // Teklif durumunu güncelle
            $pdo->prepare('UPDATE offers SET status = "accepted" WHERE id = ?')->execute([$offerId]);
            $pdo->prepare('UPDATE offers SET status = "rejected" WHERE order_id = ? AND id != ?')
                ->execute([$orderId, $offerId]);

            // Durum geçmişi
            $pdo->prepare(
                'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, "accepted", ?, ?)'
            )->execute([$orderId, 'Firma teklifi kabul edildi.', $user['id']]);

            sendOrderNotification($orderId, 'accepted', $user['id']);

            $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Teklif kabul edildi!'];
            header('Location: ' . APP_URL . '/order-detail.php?id=' . $orderId);
            exit;
        }
    }
}

// POST: İptal et (müşteri)
if (isPost() && isset($_POST['cancel_order']) && $user['role'] === 'customer') {
    verifyCsrf();
    if (in_array($order['status'], ['pending', 'accepted'])) {
        $reason = trim($_POST['cancel_reason'] ?? 'Müşteri talebi');
        $pdo->prepare(
            'UPDATE orders SET status = "cancelled", cancel_reason = ?, cancelled_by = "customer", updated_at = NOW() WHERE id = ?'
        )->execute([$reason, $orderId]);

        $pdo->prepare(
            'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, "cancelled", ?, ?)'
        )->execute([$orderId, 'Müşteri tarafından iptal edildi: ' . $reason, $user['id']]);

        // Müşteri kaynaklı iade
        if ($order['company_id'] && $order['fee_status'] === 'charged') {
            require_once __DIR__ . '/includes/wallet-functions.php';
            refundPlatformFee((int)$order['company_id'], $orderId);
        }

        sendOrderNotification($orderId, 'cancelled', $user['id']);
        $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Sipariş iptal edildi.'];
        header('Location: ' . APP_URL . '/order-detail.php?id=' . $orderId);
        exit;
    }
}

// Durum geçmişi
$histStmt = $pdo->prepare(
    'SELECT h.*, u.name AS changer_name FROM order_status_history h
     LEFT JOIN users u ON u.id = h.changed_by
     WHERE h.order_id = ? ORDER BY h.created_at ASC'
);
$histStmt->execute([$orderId]);
$history = $histStmt->fetchAll();

// Yorum
$review = null;
if ($order['status'] === 'delivered' && $user['role'] === 'customer') {
    $revStmt = $pdo->prepare('SELECT * FROM reviews WHERE order_id = ?');
    $revStmt->execute([$orderId]);
    $review = $revStmt->fetch();
}

// POST: Yorum ekle
if (isPost() && isset($_POST['submit_review']) && $user['role'] === 'customer'
    && $order['status'] === 'delivered' && !$review) {
    verifyCsrf();
    $rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');

    $pdo->prepare(
        'INSERT INTO reviews (order_id, customer_id, company_id, rating, comment) VALUES (?, ?, ?, ?, ?)'
    )->execute([$orderId, $user['id'], $order['company_id'], $rating, $comment ?: null]);

    $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Yorumunuz kaydedildi, teşekkür ederiz!'];
    header('Location: ' . APP_URL . '/order-detail.php?id=' . $orderId);
    exit;
}

// Yeniden yükle (sipariş güncel hali)
$stmt->execute([$orderId]);
$order = $stmt->fetch();

$statusSteps = array_keys(ORDER_STATUSES);

$pageTitle = 'Sipariş ' . $order['tracking_code'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>📦 <?= e($order['tracking_code']) ?></h1>
    <p>
      <span class="badge badge-<?= orderStatusColor($order['status']) ?>" style="font-size:13px;">
        <?= e(orderStatusLabel($order['status'])) ?>
      </span>
    </p>
  </div>
</div>

<div class="page-content">
  <div class="container" style="max-width:800px;">

    <!-- Durum Zaman Çizelgesi -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h2 class="card-title">Sipariş Süreci</h2></div>
      <div class="card-body">
        <ul class="timeline">
          <?php foreach ($history as $h): ?>
            <?php
            $isCancelled = $h['status'] === 'cancelled';
            $dotClass    = $isCancelled ? 'cancel' : ($h === end($history) ? 'active' : 'done');
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
                <?php if ($h['note']): ?><br><span style="font-size:12px;color:var(--text-muted);"><?= e($h['note']) ?></span><?php endif; ?>
                <br><span><?= formatDateTimeTR($h['created_at']) ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <!-- Sipariş Bilgileri -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h2 class="card-title">Sipariş Detayları</h2></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:14px;">
          <div><strong>Takip Kodu:</strong><br><?= e($order['tracking_code']) ?></div>
          <div><strong>Oluşturulma:</strong><br><?= formatDateTimeTR($order['created_at']) ?></div>
          <div><strong>Adres:</strong><br>
            <?= e($order['city_name']) ?><?= $order['district_name'] ? ', ' . e($order['district_name']) : '' ?><?= $order['neighborhood_name'] ? ', ' . e($order['neighborhood_name']) : '' ?><br>
            <?= e($order['address']) ?>
          </div>
          <div>
            <strong>Halı Adedi:</strong><br><?= (int)$order['carpet_count'] ?> adet<br>
            <strong>Toplam Alan:</strong><br><?= e($order['total_m2']) ?> m²
          </div>
          <div><strong>Teslim Alma Tarihi:</strong><br><?= formatDateTR($order['pickup_date']) ?></div>
          <?php if ($order['estimated_delivery_date']): ?>
          <div><strong>Tahmini Teslim:</strong><br><?= formatDateTR($order['estimated_delivery_date']) ?></div>
          <?php endif; ?>
          <?php if ($order['total_price'] > 0): ?>
          <div><strong>Toplam Ücret:</strong><br><strong style="color:var(--success)"><?= formatMoney($order['total_price']) ?></strong></div>
          <?php endif; ?>
          <?php if ($order['note']): ?>
          <div style="grid-column:1/-1"><strong>Not:</strong><br><?= e($order['note']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Firma Bilgisi -->
    <?php if ($order['company_name']): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h2 class="card-title">🏢 Firma Bilgisi</h2></div>
      <div class="card-body" style="font-size:14px;">
        <strong><?= e($order['company_name']) ?></strong><br>
        📞 <?= e($order['company_phone']) ?><br>
        ✉️ <?= e($order['company_email']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Teklifler (Müşteri için, sadece pending durumda) -->
    <?php if ($user['role'] === 'customer' && $order['status'] === 'pending' && !empty($offers)): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header">
        <h2 class="card-title">💬 Firmalardan Teklifler</h2>
        <span class="badge badge-info"><?= count($offers) ?> teklif</span>
      </div>
      <div class="card-body">
        <?php foreach ($offers as $of): ?>
          <div class="offer-card <?= $of['status'] === 'accepted' ? 'accepted' : '' ?>">
            <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:start;">
              <div>
                <strong><?= e($of['company_name']) ?></strong>
                <?php if ($of['avg_rating']): ?>
                  <span class="stars" style="font-size:14px;"><?= starRating((int)round($of['avg_rating'])) ?></span>
                  <span style="font-size:12px;color:var(--text-muted);">(<?= (int)$of['review_count'] ?> yorum)</span>
                <?php endif; ?>
                <div style="margin-top:8px;font-size:14px;">
                  <strong style="color:var(--success);font-size:18px;"><?= formatMoney($of['price']) ?></strong>
                  <?php if ($of['estimated_delivery_date']): ?>
                    &nbsp;·&nbsp; Tahmini: <?= formatDateTR($of['estimated_delivery_date']) ?>
                  <?php endif; ?>
                </div>
                <?php if ($of['message']): ?>
                  <p style="font-size:13px;color:var(--text-muted);margin-top:6px;"><?= e($of['message']) ?></p>
                <?php endif; ?>
              </div>
              <div>
                <?php if ($of['status'] === 'pending'): ?>
                  <form method="POST" data-once>
                    <?= csrfInput() ?>
                    <input type="hidden" name="offer_id" value="<?= (int)$of['id'] ?>">
                    <button type="submit" name="accept_offer" class="btn btn-success btn-sm"
                            data-confirm="Bu teklifi kabul etmek istediğinizden emin misiniz?">
                      ✅ Kabul Et
                    </button>
                  </form>
                <?php elseif ($of['status'] === 'accepted'): ?>
                  <span class="badge badge-success">Kabul Edildi</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php elseif ($user['role'] === 'customer' && $order['status'] === 'pending' && empty($offers)): ?>
    <div class="card" style="margin-bottom:20px;padding:20px;">
      <div class="empty-state" style="padding:24px;">
        <div class="empty-icon">⏳</div>
        <h4>Teklif Bekleniyor</h4>
        <p>Bölgenizdeki firmalar talebinizi inceliyor. Teklif geldiğinde e-posta ile bildirim alacaksınız.</p>
      </div>
    </div>
    <?php endif; ?>

    <!-- İptal Butonu -->
    <?php if ($user['role'] === 'customer' && in_array($order['status'], ['pending', 'accepted'])): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h2 class="card-title">⚠️ Sipariş İptali</h2></div>
      <div class="card-body">
        <form method="POST" data-once>
          <?= csrfInput() ?>
          <div class="form-group">
            <label class="form-label" for="cancel_reason">İptal Nedeni</label>
            <textarea id="cancel_reason" name="cancel_reason" class="form-control" rows="2"
                      placeholder="Neden iptal etmek istiyorsunuz?"></textarea>
          </div>
          <button type="submit" name="cancel_order" class="btn btn-danger"
                  data-confirm="Siparişi iptal etmek istediğinizden emin misiniz?">
            ❌ Siparişi İptal Et
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Yorum Formu -->
    <?php if ($user['role'] === 'customer' && $order['status'] === 'delivered'): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h2 class="card-title">⭐ Değerlendirme</h2></div>
      <div class="card-body">
        <?php if ($review): ?>
          <div class="alert alert-success">Değerlendirmeniz kaydedildi, teşekkür ederiz!</div>
          <div class="stars"><?= starRating((int)$review['rating']) ?></div>
          <?php if ($review['comment']): ?>
            <p style="margin-top:8px;font-size:14px;"><?= e($review['comment']) ?></p>
          <?php endif; ?>
        <?php else: ?>
          <form method="POST" data-once>
            <?= csrfInput() ?>
            <div class="form-group">
              <label class="form-label">Puan (1-5 Yıldız) *</label>
              <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <label style="cursor:pointer;font-size:14px;">
                    <input type="radio" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                    <?= str_repeat('★', $i) ?>
                  </label>
                <?php endfor; ?>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="comment">Yorumunuz</label>
              <textarea id="comment" name="comment" class="form-control" rows="3" maxlength="500"
                        placeholder="Hizmet hakkındaki düşüncelerinizi paylaşın..."></textarea>
            </div>
            <button type="submit" name="submit_review" class="btn btn-primary">
              ⭐ Değerlendirmeyi Gönder
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div style="text-align:center;margin-top:8px;">
      <a href="<?= APP_URL ?>/track.php?kod=<?= urlencode($order['tracking_code']) ?>"
         class="btn btn-outline-primary btn-sm">
        🔗 Takip Linkini Paylaş
      </a>
    </div>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
