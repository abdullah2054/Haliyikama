<?php
/**
 * Admin - Kullanıcı Yönetimi
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

requireAdmin();
$pdo = getDB();

// POST: Kullanıcı durumu güncelle
if (isPost() && isset($_POST['update_user'])) {
    verifyCsrf();
    $uid    = (int)$_POST['user_id'];
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['active', 'inactive', 'banned'])) {
        $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status, $uid]);
        $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Kullanıcı güncellendi.'];
    }
    header('Location: ' . APP_URL . '/admin/users.php');
    exit;
}

$roleFilter   = $_GET['role']   ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$per          = 20;

$where  = '1=1';
$params = [];
if ($roleFilter && array_key_exists($roleFilter, USER_ROLES)) {
    $where   .= ' AND role = ?';
    $params[] = $roleFilter;
}
if ($search) {
    $where   .= ' AND (name LIKE ? OR email LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $per, $page);

$stmt = $pdo->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([...$params, $per, $pag['offset']]);
$users = $stmt->fetchAll();

$pageTitle = 'Kullanıcı Yönetimi';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>👥 Kullanıcı Yönetimi</h1>
    <p>Toplam: <?= number_format($total) ?> kullanıcı</p>
  </div>
</div>

<div class="page-content">
  <div class="container">
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;">
      <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px;">
        <input type="text" name="q" class="form-control" placeholder="İsim veya e-posta ara..."
               value="<?= e($search) ?>" style="max-width:300px;">
        <?php if ($roleFilter): ?><input type="hidden" name="role" value="<?= e($roleFilter) ?>"><?php endif; ?>
        <button type="submit" class="btn btn-primary btn-sm">Ara</button>
      </form>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
      <a href="?" class="btn btn-sm <?= !$roleFilter ? 'btn-dark' : 'btn-outline-primary' ?>">Tümü</a>
      <?php foreach (USER_ROLES as $k => $v): ?>
        <a href="?role=<?= $k ?>" class="btn btn-sm <?= $roleFilter===$k ? 'btn-dark' : 'btn-outline-primary' ?>">
          <?= e($v) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($users)): ?>
      <div class="empty-state"><div class="empty-icon">👥</div><h4>Kullanıcı bulunamadı</h4></div>
    <?php else: ?>
      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Ad Soyad</th><th>E-posta</th><th>Telefon</th><th>Rol</th><th>Durum</th><th>Kayıt</th><th>İşlem</th></tr></thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                  <tr>
                    <td><strong><?= e($u['name']) ?></strong></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['phone'] ?? '-') ?></td>
                    <td><span class="badge badge-info"><?= USER_ROLES[$u['role']] ?? $u['role'] ?></span></td>
                    <td>
                      <span class="badge badge-<?= match($u['status']) {'active'=>'success','banned'=>'danger',default=>'warning'} ?>">
                        <?= e($u['status']) ?>
                      </span>
                    </td>
                    <td><?= formatDateTR($u['created_at']) ?></td>
                    <td>
                      <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline;">
                          <?= csrfInput() ?>
                          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                          <select name="status" class="form-control" style="display:inline;width:auto;font-size:12px;padding:4px 8px;">
                            <option value="active" <?= $u['status']==='active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="inactive" <?= $u['status']==='inactive' ? 'selected' : '' ?>>Pasif</option>
                            <option value="banned" <?= $u['status']==='banned' ? 'selected' : '' ?>>Yasaklı</option>
                          </select>
                          <button type="submit" name="update_user" class="btn btn-sm btn-primary">💾</button>
                        </form>
                      <?php else: ?>
                        <span style="font-size:12px;color:var(--text-muted);">Sen</span>
                      <?php endif; ?>
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
            <?php else: ?><a href="?page=<?= $i ?>&role=<?= e($roleFilter) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a><?php endif; ?>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
