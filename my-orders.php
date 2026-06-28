<?php
/**
 * Müşteri - Siparişlerim
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

requireLogin();
$user = currentUser();
checkUserStatus();

if ($user['role'] !== 'customer') {
    header('Location: ' . APP_URL . ($user['role'] === 'admin' ? '/admin/' : '/company-panel.php'));
    exit;
}

$pdo  = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 10;

// Durum filtresi
$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = array_keys(ORDER_STATUSES);

$where  = 'WHERE o.customer_id = ?';
$params = [$user['id']];
if ($statusFilter && in_array($statusFilter, $allowedStatuses)) {
    $where   .= ' AND o.status = ?';
    $params[] = $statusFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $per, $page);

$stmt = $pdo->prepare(
    "SELECT o.*, c.company_name,
            ci.name AS city_name, d.name AS district_name
     FROM orders o
     LEFT JOIN companies c ON c.id = o.company_id
     LEFT JOIN locations_cities ci ON ci.id = o.city_id
     LEFT JOIN locations_districts d ON d.id = o.district_id
     $where
     ORDER BY o.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([...$params, $per, $pag['offset']]);
$orders = $stmt->fetchAll();

$pageTitle = 'Siparişlerim';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>📋 Siparişlerim</h1>
    <p>Tüm halı yıkama talepleriniz</p>
  </div>
</div>

<div class="page-content">
  <div class="container">

    <!-- Filtre + Yeni Talep -->
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px;">
      <a href="<?= APP_URL ?>/create-order.php" class="btn btn-primary">
        ➕ Yeni Talep Oluştur
      </a>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="?" class="btn btn-sm <?= !$statusFilter ? 'btn-dark' : 'btn-outline-primary' ?>">Tümü</a>
        <?php foreach (ORDER_STATUSES as $key => $label): ?>
          <a href="?status=<?= $key ?>"
             class="btn btn-sm <?= $statusFilter === $key ? 'btn-dark' : 'btn-outline-primary' ?>">
            <?= e($label) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($orders)): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <h4>Henüz sipariş yok</h4>
        <p>İlk halı yıkama talebinizi oluşturun.</p>
        <a href="<?= APP_URL ?>/create-order.php" class="btn btn-primary" style="margin-top:16px;">
          ➕ Talep Oluştur
        </a>
      </div>
    <?php else: ?>
      <?php foreach ($orders as $o): ?>
        <div class="order-card">
          <div>
            <div class="tracking"><?= e($o['tracking_code']) ?></div>
            <div class="order-meta">
              <span>📍 <?= e($o['city_name']) ?><?= $o['district_name'] ? ', ' . e($o['district_name']) : '' ?></span>
              <span>🏡 <?= e($o['carpet_count']) ?> Halı / <?= e($o['total_m2']) ?> m²</span>
              <span>📅 <?= formatDateTR($o['pickup_date']) ?></span>
              <?php if ($o['company_name']): ?>
                <span>🏢 <?= e($o['company_name']) ?></span>
              <?php endif; ?>
            </div>
            <div style="margin-top:8px;font-size:12px;color:var(--text-muted);">
              Oluşturuldu: <?= formatDateTimeTR($o['created_at']) ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
            <span class="badge badge-<?= orderStatusColor($o['status']) ?>">
              <?= e(orderStatusLabel($o['status'])) ?>
            </span>
            <a href="<?= APP_URL ?>/order-detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary">
              Detay →
            </a>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- Sayfalama -->
      <?php if ($pag['total_pages'] > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&status=<?= e($statusFilter) ?>">‹</a>
          <?php endif; ?>
          <?php for ($i = max(1, $page - 2); $i <= min($pag['total_pages'], $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
              <span class="current"><?= $i ?></span>
            <?php else: ?>
              <a href="?page=<?= $i ?>&status=<?= e($statusFilter) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($page < $pag['total_pages']): ?>
            <a href="?page=<?= $page + 1 ?>&status=<?= e($statusFilter) ?>">›</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
