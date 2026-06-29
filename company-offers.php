<?php
/**
 * Firma - Talep Listesi ve Teklif Verme
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

// POST: Teklif ver
if (isPost() && isset($_POST['submit_offer'])) {
    verifyCsrf();
    $orderId  = (int)$_POST['order_id'];
    $price    = (float)$_POST['price'];
    $estDate  = trim($_POST['estimated_delivery_date'] ?? '');
    $message  = trim($_POST['message'] ?? '');

    if ($price <= 0) {
        $_SESSION['flash'][] = ['type' => 'error', 'text' => 'Geçerli bir fiyat girin.'];
    } else {
        // Bakiye kontrolü
        $fee     = (float)getSetting('platform_fee', '25.00');
        $balance = getCompanyBalance($companyId);
        if ($balance < $fee) {
            $_SESSION['flash'][] = ['type' => 'warn', 'text' => 'Bakiyeniz yetersiz. Teklif vermek için bakiye yükleyin.'];
        } else {
            // Daha önce teklif verildi mi?
            $chk = $pdo->prepare('SELECT id FROM offers WHERE order_id = ? AND company_id = ?');
            $chk->execute([$orderId, $companyId]);
            if ($chk->fetch()) {
                $_SESSION['flash'][] = ['type' => 'warn', 'text' => 'Bu talep için zaten teklif verdiniz.'];
            } else {
                $pdo->prepare(
                    'INSERT INTO offers (order_id, company_id, price, estimated_delivery_date, message)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([$orderId, $companyId, $price, $estDate ?: null, $message ?: null]);

                // Müşteriye bildirim
                $oStmt = $pdo->prepare('SELECT o.*, u.name AS customer_name, u.email AS customer_email FROM orders o JOIN users u ON u.id = o.customer_id WHERE o.id = ?');
                $oStmt->execute([$orderId]);
                $orderData = $oStmt->fetch();
                if ($orderData) {
                    sendOfferNotificationEmail(
                        $orderData,
                        ['price' => $price, 'estimated_delivery_date' => $estDate],
                        $company
                    );
                }

                $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Teklifiniz gönderildi!'];
            }
        }
    }
    header('Location: ' . APP_URL . '/company-offers.php');
    exit;
}

// Bölgeye göre talepleri getir (hizmet alanları tablosundan)
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 10;

$countStmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT o.id)
     FROM orders o
     WHERE o.status = "pending" AND o.company_id IS NULL
     AND EXISTS (
       SELECT 1 FROM company_service_areas csa
       WHERE csa.company_id = ?
       AND csa.city_id = o.city_id
       AND (csa.district_id IS NULL OR csa.district_id = o.district_id)
       AND (csa.neighborhood_id IS NULL OR csa.neighborhood_id = o.neighborhood_id OR o.neighborhood_id IS NULL)
     )'
);
$countStmt->execute([$companyId]);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $per, $page);

$stmt = $pdo->prepare(
    'SELECT o.*, ci.name AS city_name, d.name AS district_name, n.name AS neighborhood_name,
            u.name AS customer_name,
            (SELECT COUNT(*) FROM offers of2 WHERE of2.order_id = o.id AND of2.company_id = ?) AS my_offer
     FROM orders o
     JOIN users u ON u.id = o.customer_id
     LEFT JOIN locations_cities ci ON ci.id = o.city_id
     LEFT JOIN locations_districts d ON d.id = o.district_id
     LEFT JOIN locations_neighborhoods n ON n.id = o.neighborhood_id
     WHERE o.status = "pending" AND o.company_id IS NULL
     AND EXISTS (
       SELECT 1 FROM company_service_areas csa
       WHERE csa.company_id = ?
       AND csa.city_id = o.city_id
       AND (csa.district_id IS NULL OR csa.district_id = o.district_id)
       AND (csa.neighborhood_id IS NULL OR csa.neighborhood_id = o.neighborhood_id OR o.neighborhood_id IS NULL)
     )
     ORDER BY o.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->execute([$companyId, $companyId, $per, $pag['offset']]);
$requests = $stmt->fetchAll();

$pageTitle = 'Talepler';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>📥 Müşteri Talepleri</h1>
    <p>Hizmet bölgenizdeki yeni halı yıkama talepleri</p>
  </div>
</div>

<div class="page-content">
  <div class="container">

    <?php if (getCompanyBalance($companyId) < (float)getSetting('platform_fee', '25.00')): ?>
      <div class="alert alert-warning">
        ⚠️ Bakiyeniz yetersiz. Teklif verebilmek için
        <a href="<?= APP_URL ?>/company-wallet.php" style="color:inherit;font-weight:700;">bakiye yükleyin</a>.
      </div>
    <?php endif; ?>

    <?php if (empty($requests)): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <h4>Hizmet bölgenizde yeni talep yok</h4>
        <p>Hizmet bölgelerinizi güncelleyerek daha fazla talep görebilirsiniz.</p>
        <a href="<?= APP_URL ?>/company-settings.php" class="btn btn-outline-primary" style="margin-top:12px;">
          ⚙️ Hizmet Bölgelerini Düzenle
        </a>
      </div>
    <?php else: ?>
      <p style="color:var(--text-muted);font-size:14px;margin-bottom:16px;">
        Toplam <?= $total ?> talep bulundu
      </p>

      <?php foreach ($requests as $r): ?>
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <div>
              <strong><?= e($r['tracking_code']) ?></strong>
              <span style="font-size:13px;color:var(--text-muted);margin-left:12px;">
                <?= formatDateTimeTR($r['created_at']) ?>
              </span>
            </div>
            <?php if ($r['my_offer']): ?>
              <span class="badge badge-success">✅ Teklif Verildi</span>
            <?php else: ?>
              <span class="badge badge-warning">Teklif Bekleniyor</span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;font-size:14px;margin-bottom:16px;">
              <div>
                <div style="font-size:12px;color:var(--text-muted);">Konum</div>
                <strong>
                  <?= e($r['city_name']) ?>
                  <?= $r['district_name'] ? ', ' . e($r['district_name']) : '' ?>
                  <?= $r['neighborhood_name'] ? ', ' . e($r['neighborhood_name']) : '' ?>
                </strong>
              </div>
              <div>
                <div style="font-size:12px;color:var(--text-muted);">Halı Bilgisi</div>
                <strong><?= (int)$r['carpet_count'] ?> adet / <?= e($r['total_m2']) ?> m²</strong>
              </div>
              <div>
                <div style="font-size:12px;color:var(--text-muted);">Teslim Alma</div>
                <strong><?= formatDateTR($r['pickup_date']) ?></strong>
              </div>
              <?php if ($r['note']): ?>
              <div>
                <div style="font-size:12px;color:var(--text-muted);">Müşteri Notu</div>
                <?= e($r['note']) ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- Teklif Formu -->
            <?php if (!$r['my_offer'] && $company['status'] === 'active'): ?>
              <details style="margin-top:8px;">
                <summary class="btn btn-primary btn-sm" style="cursor:pointer;list-style:none;">
                  💬 Teklif Ver
                </summary>
                <div style="margin-top:16px;padding:16px;background:#f8f9fa;border-radius:8px;">
                  <form method="POST" data-once>
                    <?= csrfInput() ?>
                    <input type="hidden" name="order_id" value="<?= (int)$r['id'] ?>">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                      <div class="form-group">
                        <label class="form-label">Teklif Fiyatı (TL) *</label>
                        <input type="number" name="price" class="form-control"
                               min="1" step="0.01" required
                               placeholder="Toplam fiyat"
                               value="<?= $company['price_per_m2'] ? round($company['price_per_m2'] * $r['total_m2'], 2) : '' ?>">
                      </div>
                      <div class="form-group">
                        <label class="form-label">Tahmini Teslim Tarihi</label>
                        <input type="date" name="estimated_delivery_date" class="form-control"
                               min="<?= date('Y-m-d') ?>">
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Mesaj (isteğe bağlı)</label>
                      <textarea name="message" class="form-control" rows="2" maxlength="300"
                                placeholder="Müşteriye ek bilgi..."></textarea>
                    </div>
                    <button type="submit" name="submit_offer" class="btn btn-success">
                      ✅ Teklifi Gönder
                    </button>
                    <span style="font-size:12px;color:var(--text-muted);margin-left:12px;">
                      Platform ücreti: <?= formatMoney(getSetting('platform_fee','25.00')) ?> (teklif kabul edilince kesilir)
                    </span>
                  </form>
                </div>
              </details>
            <?php elseif ($r['my_offer']): ?>
              <div style="margin-top:8px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div class="alert alert-success" style="margin:0;flex:1;">
                  ✅ Bu talep için teklifiniz gönderildi. Müşterinin kararını bekliyorsunuz.
                </div>
                <a href="<?= APP_URL ?>/chat.php?order_id=<?= (int)$r['id'] ?>&company_id=<?= $companyId ?>"
                   class="btn btn-sm btn-primary">💬 Müşteri ile Mesajlaş</a>
              </div>
            <?php endif; ?>
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
              <a href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
