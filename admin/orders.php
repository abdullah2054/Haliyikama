<?php
/**
 * Admin - Sipariş Yönetimi
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

requireAdmin();
$pdo = getDB();

$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$per          = 20;

$where  = '1=1';
$params = [];

if ($statusFilter && array_key_exists($statusFilter, ORDER_STATUSES)) {
    $where   .= ' AND o.status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $where   .= ' AND (o.tracking_code LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.customer_id WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $per, $page);

$stmt = $pdo->prepare(
    "SELECT o.*, u.name AS customer_name, c.company_name, ci.name AS city_name
     FROM orders o
     JOIN users u ON u.id = o.customer_id
     LEFT JOIN companies c ON c.id = o.company_id
     LEFT JOIN locations_cities ci ON ci.id = o.city_id
     WHERE $where
     ORDER BY o.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([...$params, $per, $pag['offset']]);
$orders = $stmt->fetchAll();

$pageTitle = 'Sipariş Yönetimi';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>📦 Sipariş Yönetimi</h1>
    <p>Tüm siparişler · Toplam: <?= number_format($total) ?></p>
  </div>
</div>

<div class="page-content">
  <div class="container">

    <!-- Filtre -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;">
      <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px;">
        <input type="text" name="q" class="form-control" placeholder="Takip kodu, müşteri ara..."
               value="<?= e($search) ?>" style="max-width:300px;">
        <?php if ($statusFilter): ?>
          <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-sm">Ara</button>
      </form>
    </div>

    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;">
      <a href="?" class="btn btn-sm <?= !$statusFilter ? 'btn-dark' : 'btn-outline-primary' ?>">Tümü</a>
      <?php foreach (ORDER_STATUSES as $k => $v): ?>
        <a href="?status=<?= $k ?>&q=<?= urlencode($search) ?>"
           class="btn btn-sm <?= $statusFilter === $k ? 'btn-dark' : 'btn-outline-primary' ?>">
          <?= e($v) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
      <div class="empty-state"><div class="empty-icon">📭</div><h4>Sipariş bulunamadı</h4></div>
    <?php else: ?>
      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Takip Kodu</th>
                  <th>Müşteri</th>
                  <th>Firma</th>
                  <th>İl</th>
                  <th>Halı</th>
                  <th>Fiyat</th>
                  <th>Durum</th>
                  <th>Tarih</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $o): ?>
                  <tr>
                    <td>
                      <a href="<?= APP_URL ?>/track.php?kod=<?= urlencode($o['tracking_code']) ?>" target="_blank">
                        <strong><?= e($o['tracking_code']) ?></strong>
                      </a>
                    </td>
                    <td><?= e($o['customer_name']) ?></td>
                    <td><?= $o['company_name'] ? e($o['company_name']) : '<span style="color:var(--text-muted)">-</span>' ?></td>
                    <td><?= e($o['city_name'] ?? '-') ?></td>
                    <td><?= (int)$o['carpet_count'] ?> / <?= e($o['total_m2']) ?> m²</td>
                    <td><?= $o['total_price'] > 0 ? formatMoney($o['total_price']) : '-' ?></td>
                    <td><span class="badge badge-<?= orderStatusColor($o['status']) ?>"><?= e(orderStatusLabel($o['status'])) ?></span></td>
                    <td><?= formatDateTR($o['created_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <?php if ($pag['total_pages'] > 1): ?>
        <div class="pagination">
          <?php for ($i=1; $i<=$pag['total_pages']; $i++): ?>
            <?php if ($i===$page): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="?page=<?= $i ?>&status=<?= e($statusFilter) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a><?php endif; ?>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
