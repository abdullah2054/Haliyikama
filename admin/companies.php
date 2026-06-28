<?php
/**
 * Admin - Firma Yönetimi
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/notification-functions.php';
require_once dirname(__DIR__) . '/includes/wallet-functions.php';

requireAdmin();
$pdo = getDB();

// POST: Firma durumu güncelle
if (isPost() && isset($_POST['update_status'])) {
    verifyCsrf();
    $companyId = (int)$_POST['company_id'];
    $newStatus = $_POST['status'] ?? '';
    $allowed   = ['active', 'rejected', 'suspended', 'pending'];

    if (in_array($newStatus, $allowed)) {
        $pdo->prepare('UPDATE companies SET status=? WHERE id=?')->execute([$newStatus, $companyId]);

        // Firma kullanıcı bilgisini getir
        $stmt = $pdo->prepare('SELECT c.*, u.name AS user_name FROM companies c JOIN users u ON u.id = c.user_id WHERE c.id = ?');
        $stmt->execute([$companyId]);
        $co = $stmt->fetch();

        if ($co && in_array($newStatus, ['active', 'rejected'])) {
            sendCompanyStatusEmail([
                'authorized_name' => $co['authorized_name'],
                'email'           => $co['email'],
            ], $newStatus);
        }

        $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Firma durumu güncellendi.'];
    }
    header('Location: ' . APP_URL . '/admin/companies.php');
    exit;
}

// POST: Bakiye güncelle
if (isPost() && isset($_POST['update_balance'])) {
    verifyCsrf();
    $companyId = (int)$_POST['company_id'];
    $amount    = (float)$_POST['amount'];
    $type      = $_POST['tx_type'] ?? 'credit';
    $desc      = trim($_POST['description'] ?? 'Admin tarafından işlem yapıldı');

    if ($amount > 0 && in_array($type, ['credit', 'debit'])) {
        walletTransaction($companyId, $type, $amount, $desc);
        $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Bakiye güncellendi.'];
    }
    header('Location: ' . APP_URL . '/admin/companies.php?id=' . $companyId);
    exit;
}

// Detay görünümü
$detailCompany = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare(
        'SELECT c.*, u.name AS user_name, u.email AS user_email,
                ci.name AS city_name, d.name AS district_name
         FROM companies c JOIN users u ON u.id = c.user_id
         LEFT JOIN locations_cities ci ON ci.id = c.city_id
         LEFT JOIN locations_districts d ON d.id = c.district_id
         WHERE c.id = ?'
    );
    $stmt->execute([(int)$_GET['id']]);
    $detailCompany = $stmt->fetch();

    if ($detailCompany) {
        $txStmt = $pdo->prepare(
            'SELECT wt.*, o.tracking_code FROM wallet_transactions wt
             LEFT JOIN orders o ON o.id = wt.order_id
             WHERE wt.company_id = ? ORDER BY wt.created_at DESC LIMIT 20'
        );
        $txStmt->execute([$detailCompany['id']]);
        $transactions = $txStmt->fetchAll();

        $areaStmt = $pdo->prepare(
            'SELECT csa.*, ci.name AS city_name, d.name AS district_name, n.name AS neighborhood_name
             FROM company_service_areas csa
             JOIN locations_cities ci ON ci.id = csa.city_id
             LEFT JOIN locations_districts d ON d.id = csa.district_id
             LEFT JOIN locations_neighborhoods n ON n.id = csa.neighborhood_id
             WHERE csa.company_id = ?'
        );
        $areaStmt->execute([$detailCompany['id']]);
        $serviceAreas = $areaStmt->fetchAll();
    }
}

// Liste
$statusFilter = $_GET['status'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$per          = 15;

$where  = '1=1';
$params = [];
if ($statusFilter) {
    $where   = 'c.status = ?';
    $params[] = $statusFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM companies c WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $per, $page);

$listStmt = $pdo->prepare(
    "SELECT c.*, u.name AS user_name, u.email AS user_email, ci.name AS city_name
     FROM companies c JOIN users u ON u.id = c.user_id
     LEFT JOIN locations_cities ci ON ci.id = c.city_id
     WHERE $where ORDER BY c.created_at DESC
     LIMIT ? OFFSET ?"
);
$listStmt->execute([...$params, $per, $pag['offset']]);
$companies = $listStmt->fetchAll();

$pageTitle = 'Firma Yönetimi';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>🏢 Firma Yönetimi</h1>
    <p>Tüm firmalar ve başvurular</p>
  </div>
</div>

<div class="page-content">
  <div class="container">

    <?php if ($detailCompany): ?>
      <!-- Detay -->
      <div style="margin-bottom:16px;">
        <a href="<?= APP_URL ?>/admin/companies.php" class="btn btn-sm btn-outline-primary">← Listeye Dön</a>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
        <div>
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
              <h2 class="card-title"><?= e($detailCompany['company_name']) ?></h2>
              <span class="badge badge-<?= match($detailCompany['status']) {
                'active' => 'success', 'pending' => 'warning', 'rejected' => 'danger', default => 'secondary'
              } ?>"><?= e($detailCompany['status']) ?></span>
            </div>
            <div class="card-body" style="font-size:14px;">
              <p><strong>Yetkili:</strong> <?= e($detailCompany['authorized_name']) ?></p>
              <p><strong>Telefon:</strong> <?= e($detailCompany['phone']) ?></p>
              <p><strong>E-posta:</strong> <?= e($detailCompany['email']) ?></p>
              <p><strong>Giriş E-postası:</strong> <?= e($detailCompany['user_email']) ?></p>
              <p><strong>İl/İlçe:</strong> <?= e($detailCompany['city_name'] ?? '-') ?> / <?= e($detailCompany['district_name'] ?? '-') ?></p>
              <p><strong>Adres:</strong> <?= e($detailCompany['address'] ?? '-') ?></p>
              <p><strong>m² Fiyatı:</strong> <?= formatMoney($detailCompany['price_per_m2']) ?></p>
              <p><strong>Bakiye:</strong> <strong style="color:var(--success);"><?= formatMoney($detailCompany['balance']) ?></strong></p>
              <p><strong>Başvuru Tarihi:</strong> <?= formatDateTimeTR($detailCompany['created_at']) ?></p>
            </div>
          </div>

          <!-- Hizmet Bölgeleri -->
          <?php if (!empty($serviceAreas)): ?>
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><h2 class="card-title">📍 Hizmet Bölgeleri</h2></div>
            <div class="card-body">
              <?php foreach ($serviceAreas as $a): ?>
                <span style="display:inline-block;background:#f0f4f8;border-radius:20px;padding:4px 12px;font-size:12px;margin:3px;">
                  <?= e($a['city_name']) ?>
                  <?= $a['district_name'] ? ' › ' . e($a['district_name']) : '' ?>
                  <?= $a['neighborhood_name'] ? ' › ' . e($a['neighborhood_name']) : '' ?>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <div>
          <!-- Durum Güncelle -->
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><h2 class="card-title">⚡ Durum Güncelle</h2></div>
            <div class="card-body">
              <form method="POST" data-once>
                <?= csrfInput() ?>
                <input type="hidden" name="company_id" value="<?= (int)$detailCompany['id'] ?>">
                <div class="form-group">
                  <label class="form-label">Yeni Durum</label>
                  <select name="status" class="form-control">
                    <option value="active"    <?= $detailCompany['status'] === 'active'    ? 'selected' : '' ?>>✅ Aktif (Onayla)</option>
                    <option value="rejected"  <?= $detailCompany['status'] === 'rejected'  ? 'selected' : '' ?>>❌ Reddedildi</option>
                    <option value="suspended" <?= $detailCompany['status'] === 'suspended' ? 'selected' : '' ?>>🚫 Askıya Alındı</option>
                    <option value="pending"   <?= $detailCompany['status'] === 'pending'   ? 'selected' : '' ?>>⏳ Beklemede</option>
                  </select>
                </div>
                <button type="submit" name="update_status" class="btn btn-primary btn-full">
                  💾 Durumu Kaydet
                </button>
              </form>
            </div>
          </div>

          <!-- Bakiye Güncelle -->
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><h2 class="card-title">💰 Bakiye İşlemi</h2></div>
            <div class="card-body">
              <form method="POST" data-once>
                <?= csrfInput() ?>
                <input type="hidden" name="company_id" value="<?= (int)$detailCompany['id'] ?>">
                <div class="form-group">
                  <label class="form-label">İşlem Türü</label>
                  <select name="tx_type" class="form-control">
                    <option value="credit">Bakiye Ekle</option>
                    <option value="debit">Bakiye Düş</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Tutar (TL)</label>
                  <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Açıklama</label>
                  <input type="text" name="description" class="form-control" maxlength="200" value="Admin tarafından işlem yapıldı">
                </div>
                <button type="submit" name="update_balance" class="btn btn-success btn-full">
                  💳 İşlemi Uygula
                </button>
              </form>
            </div>
          </div>

          <!-- Son İşlemler -->
          <?php if (!empty($transactions)): ?>
          <div class="card">
            <div class="card-header"><h2 class="card-title">📊 Son İşlemler</h2></div>
            <div class="card-body" style="padding:0;">
              <div class="table-wrap">
                <table class="data-table">
                  <thead><tr><th>Tür</th><th>Tutar</th><th>Tarih</th></tr></thead>
                  <tbody>
                    <?php foreach ($transactions as $tx): ?>
                      <tr>
                        <td><span class="badge badge-<?= in_array($tx['type'],['credit','refund']) ? 'success' : 'danger' ?>"><?= e($tx['type']) ?></span></td>
                        <td><?= formatMoney($tx['amount']) ?></td>
                        <td><?= formatDateTimeTR($tx['created_at']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?>

      <!-- Liste -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
        <a href="?" class="btn btn-sm <?= !$statusFilter ? 'btn-dark' : 'btn-outline-primary' ?>">Tümü (<?= $total ?>)</a>
        <a href="?status=pending"   class="btn btn-sm <?= $statusFilter==='pending'   ? 'btn-dark' : 'btn-outline-primary' ?>">⏳ Bekleyen</a>
        <a href="?status=active"    class="btn btn-sm <?= $statusFilter==='active'    ? 'btn-dark' : 'btn-outline-primary' ?>">✅ Aktif</a>
        <a href="?status=rejected"  class="btn btn-sm <?= $statusFilter==='rejected'  ? 'btn-dark' : 'btn-outline-primary' ?>">❌ Reddedildi</a>
        <a href="?status=suspended" class="btn btn-sm <?= $statusFilter==='suspended' ? 'btn-dark' : 'btn-outline-primary' ?>">🚫 Askıda</a>
      </div>

      <?php if (empty($companies)): ?>
        <div class="empty-state"><div class="empty-icon">🏢</div><h4>Firma bulunamadı</h4></div>
      <?php else: ?>
        <div class="card">
          <div class="card-body" style="padding:0;">
            <div class="table-wrap">
              <table class="data-table">
                <thead><tr><th>Firma Adı</th><th>Yetkili</th><th>İl</th><th>Bakiye</th><th>Durum</th><th>Tarih</th><th>İşlem</th></tr></thead>
                <tbody>
                  <?php foreach ($companies as $c): ?>
                    <tr>
                      <td><strong><?= e($c['company_name']) ?></strong><br><span style="font-size:12px;color:var(--text-muted);"><?= e($c['user_email']) ?></span></td>
                      <td><?= e($c['authorized_name']) ?></td>
                      <td><?= e($c['city_name'] ?? '-') ?></td>
                      <td><?= formatMoney($c['balance']) ?></td>
                      <td><span class="badge badge-<?= match($c['status']) {'active'=>'success','pending'=>'warning','rejected'=>'danger',default=>'secondary'} ?>"><?= e($c['status']) ?></span></td>
                      <td><?= formatDateTR($c['created_at']) ?></td>
                      <td><a href="?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary">İncele</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <!-- Sayfalama -->
        <?php if ($pag['total_pages'] > 1): ?>
          <div class="pagination">
            <?php for ($i=1; $i<=$pag['total_pages']; $i++): ?>
              <?php if ($i===$page): ?><span class="current"><?= $i ?></span>
              <?php else: ?><a href="?page=<?= $i ?>&status=<?= e($statusFilter) ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
