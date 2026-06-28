<?php
/**
 * Danışman Paneli
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

requireRole('consultant', 'admin');
$pdo  = getDB();
$user = currentUser();

// İstatistikler
$totalOrders    = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$activeOrders   = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('delivered','cancelled')")->fetchColumn();
$totalCustomers = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer"')->fetchColumn();
$totalCompanies = $pdo->query('SELECT COUNT(*) FROM companies WHERE status = "active"')->fetchColumn();

// Son siparişler
$recentOrders = $pdo->query(
    'SELECT o.*, u.name AS customer_name, c.company_name,
            ci.name AS city_name
     FROM orders o
     JOIN users u ON u.id = o.customer_id
     LEFT JOIN companies c ON c.id = o.company_id
     LEFT JOIN locations_cities ci ON ci.id = o.city_id
     ORDER BY o.created_at DESC LIMIT 10'
)->fetchAll();

$pageTitle = 'Danışman Paneli';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>🎯 Danışman Paneli</h1>
    <p>Hoş geldiniz, <?= e($user['name']) ?></p>
  </div>
</div>

<div class="page-content">
  <div class="container">

    <!-- İstatistik Kartları -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
      <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:32px;">📦</div>
        <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= number_format($totalOrders) ?></div>
        <div style="font-size:13px;color:var(--text-muted);">Toplam Sipariş</div>
      </div>
      <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:32px;">⚡</div>
        <div style="font-size:28px;font-weight:700;color:var(--warning);"><?= number_format($activeOrders) ?></div>
        <div style="font-size:13px;color:var(--text-muted);">Aktif Sipariş</div>
      </div>
      <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:32px;">👥</div>
        <div style="font-size:28px;font-weight:700;color:var(--success);"><?= number_format($totalCustomers) ?></div>
        <div style="font-size:13px;color:var(--text-muted);">Müşteri</div>
      </div>
      <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:32px;">🏢</div>
        <div style="font-size:28px;font-weight:700;color:var(--info);"><?= number_format($totalCompanies) ?></div>
        <div style="font-size:13px;color:var(--text-muted);">Aktif Firma</div>
      </div>
    </div>

    <!-- Son Siparişler -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">📋 Son Siparişler</h2>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if (empty($recentOrders)): ?>
          <div class="empty-state"><div class="empty-icon">📦</div><h4>Henüz sipariş yok</h4></div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Takip Kodu</th>
                  <th>Müşteri</th>
                  <th>Firma</th>
                  <th>İl</th>
                  <th>Durum</th>
                  <th>Tarih</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $o): ?>
                  <tr>
                    <td><code><?= e($o['tracking_code']) ?></code></td>
                    <td><?= e($o['customer_name']) ?></td>
                    <td><?= e($o['company_name'] ?? '-') ?></td>
                    <td><?= e($o['city_name'] ?? '-') ?></td>
                    <td>
                      <span class="badge badge-<?= orderStatusColor($o['status']) ?>">
                        <?= orderStatusLabel($o['status']) ?>
                      </span>
                    </td>
                    <td><?= formatDateTR($o['created_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
