<?php
/**
 * Admin - Yorum Yönetimi
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

requireAdmin();
$pdo = getDB();

// POST: Yorum durumu değiştir
if (isPost() && isset($_POST['toggle_review'])) {
    verifyCsrf();
    $reviewId = (int)$_POST['review_id'];
    $pdo->prepare(
        'UPDATE reviews SET status = CASE WHEN status = "active" THEN "hidden" ELSE "active" END WHERE id = ?'
    )->execute([$reviewId]);
    $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Yorum güncellendi.'];
    header('Location: ' . APP_URL . '/admin/reviews.php');
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 20;
$pag  = paginate((int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn(), $per, $page);

$stmt = $pdo->prepare(
    'SELECT r.*, u.name AS customer_name, c.company_name, o.tracking_code
     FROM reviews r
     JOIN users u ON u.id = r.customer_id
     JOIN companies c ON c.id = r.company_id
     JOIN orders o ON o.id = r.order_id
     ORDER BY r.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->execute([$per, $pag['offset']]);
$reviews = $stmt->fetchAll();

$pageTitle = 'Yorum Yönetimi';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>⭐ Yorum Yönetimi</h1>
  </div>
</div>

<div class="page-content">
  <div class="container">
    <?php if (empty($reviews)): ?>
      <div class="empty-state"><div class="empty-icon">⭐</div><h4>Henüz yorum yok</h4></div>
    <?php else: ?>
      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Sipariş</th><th>Müşteri</th><th>Firma</th><th>Puan</th><th>Yorum</th><th>Durum</th><th>Tarih</th><th>İşlem</th></tr></thead>
              <tbody>
                <?php foreach ($reviews as $r): ?>
                  <tr>
                    <td><?= e($r['tracking_code']) ?></td>
                    <td><?= e($r['customer_name']) ?></td>
                    <td><?= e($r['company_name']) ?></td>
                    <td class="stars" style="font-size:16px;"><?= starRating((int)$r['rating']) ?></td>
                    <td style="max-width:200px;font-size:12px;"><?= e(mb_substr($r['comment'] ?? '-', 0, 80)) ?></td>
                    <td>
                      <span class="badge badge-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?>">
                        <?= $r['status'] === 'active' ? 'Aktif' : 'Gizli' ?>
                      </span>
                    </td>
                    <td><?= formatDateTR($r['created_at']) ?></td>
                    <td>
                      <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" name="toggle_review"
                                class="btn btn-sm <?= $r['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>">
                          <?= $r['status'] === 'active' ? 'Gizle' : 'Göster' ?>
                        </button>
                      </form>
                    </td>
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
    <?php endif; ?>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
