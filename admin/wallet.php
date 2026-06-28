<?php
/**
 * Admin - Bakiye Hareketleri
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

requireAdmin();
$pdo = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 25;
$pag  = paginate((int)$pdo->query('SELECT COUNT(*) FROM wallet_transactions')->fetchColumn(), $per, $page);

$stmt = $pdo->prepare(
    'SELECT wt.*, c.company_name, o.tracking_code
     FROM wallet_transactions wt
     JOIN companies c ON c.id = wt.company_id
     LEFT JOIN orders o ON o.id = wt.order_id
     ORDER BY wt.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->execute([$per, $pag['offset']]);
$transactions = $stmt->fetchAll();

// Özet
$summary = $pdo->query(
    'SELECT
       SUM(CASE WHEN type = "credit" THEN amount ELSE 0 END) AS total_loaded,
       SUM(CASE WHEN type = "debit"  THEN amount ELSE 0 END) AS total_earned,
       SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) AS total_refunded
     FROM wallet_transactions'
)->fetch();

$pageTitle = 'Bakiye Hareketleri';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>💰 Bakiye Hareketleri</h1>
    <p>Tüm firma bakiye işlemleri</p>
  </div>
</div>

<div class="page-content">
  <div class="container">

    <div class="stats-grid" style="margin-bottom:24px;">
      <div class="stat-card">
        <span class="stat-val"><?= formatMoney($summary['total_loaded'] ?? 0) ?></span>
        <div class="stat-label">Toplam Yüklenen</div>
      </div>
      <div class="stat-card">
        <span class="stat-val" style="color:var(--success);"><?= formatMoney($summary['total_earned'] ?? 0) ?></span>
        <div class="stat-label">Platform Geliri</div>
      </div>
      <div class="stat-card">
        <span class="stat-val" style="color:var(--info);"><?= formatMoney($summary['total_refunded'] ?? 0) ?></span>
        <div class="stat-label">Toplam İade</div>
      </div>
    </div>

    <div class="card">
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Firma</th><th>Sipariş</th><th>Tür</th><th>Açıklama</th><th style="text-align:right;">Tutar</th><th>Tarih</th></tr></thead>
            <tbody>
              <?php foreach ($transactions as $tx): ?>
                <?php
                $typeColors = ['credit' => 'success', 'debit' => 'danger', 'block' => 'warning', 'refund' => 'info'];
                $typeLabels = ['credit' => 'Yükleme', 'debit' => 'Kesinti', 'block' => 'Bloke', 'refund' => 'İade'];
                $sign = in_array($tx['type'], ['credit', 'refund']) ? '+' : '-';
                $color = in_array($tx['type'], ['credit', 'refund']) ? 'var(--success)' : 'var(--danger)';
                ?>
                <tr>
                  <td><?= e($tx['company_name']) ?></td>
                  <td><?= $tx['tracking_code'] ? e($tx['tracking_code']) : '-' ?></td>
                  <td><span class="badge badge-<?= $typeColors[$tx['type']] ?? 'secondary' ?>"><?= $typeLabels[$tx['type']] ?? $tx['type'] ?></span></td>
                  <td style="font-size:12px;max-width:200px;"><?= e($tx['description'] ?? '-') ?></td>
                  <td style="text-align:right;font-weight:700;color:<?= $color ?>;"><?= $sign ?><?= formatMoney($tx['amount']) ?></td>
                  <td><?= formatDateTimeTR($tx['created_at']) ?></td>
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
          <?php else: ?><a href="?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
