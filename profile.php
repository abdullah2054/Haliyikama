<?php
/**
 * Kullanıcı Profil Sayfası
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

requireLogin();
$user = currentUser();
checkUserStatus();

$pdo    = getDB();
$errors = [];
$success = '';

if (isPost()) {
    verifyCsrf();
    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $pw      = $_POST['password']     ?? '';
    $pw2     = $_POST['password2']    ?? '';

    if (mb_strlen($name) < 2) $errors[] = 'Ad soyad en az 2 karakter olmalı.';
    if ($pw && strlen($pw) < 6) $errors[] = 'Yeni şifre en az 6 karakter olmalı.';
    if ($pw && $pw !== $pw2) $errors[] = 'Şifreler eşleşmiyor.';

    if (empty($errors)) {
        if ($pw) {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET name = ?, phone = ?, password = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$name, $phone, $hash, $user['id']]);
        } else {
            $pdo->prepare('UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$name, $phone, $user['id']]);
        }
        $_SESSION['user_name'] = $name;
        $success = 'Profil bilgileriniz güncellendi.';
        // Kullanıcıyı yenile
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $user = $stmt->fetch();
    }
}

$pageTitle = 'Profilim';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>👤 Profilim</h1>
    <p>Hesap bilgilerinizi görüntüleyin ve güncelleyin</p>
  </div>
</div>

<div class="page-content">
  <div class="container" style="max-width:560px;">

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <span>⚠️</span>
        <div><?= implode('<br>', array_map('e', $errors)) ?></div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Profil Bilgileri</h2>
        <span class="badge badge-info"><?= USER_ROLES[$user['role']] ?? $user['role'] ?></span>
      </div>
      <div class="card-body">
        <form method="POST" data-once>
          <?= csrfInput() ?>

          <div class="form-group">
            <label class="form-label" for="name">Ad Soyad *</label>
            <input type="text" id="name" name="name" class="form-control"
                   value="<?= e($user['name']) ?>" required maxlength="150">
          </div>

          <div class="form-group">
            <label class="form-label">E-posta</label>
            <input type="email" class="form-control" value="<?= e($user['email']) ?>"
                   readonly style="background:#f9fafb;">
            <div class="form-hint">E-posta değiştirmek için destek ekibiyle iletişime geçin.</div>
          </div>

          <div class="form-group">
            <label class="form-label" for="phone">Telefon</label>
            <input type="tel" id="phone" name="phone" class="form-control"
                   value="<?= e($user['phone'] ?? '') ?>" placeholder="05XX XXX XX XX" maxlength="20">
          </div>

          <hr style="margin:24px 0;border-color:var(--border);">
          <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">
            Şifrenizi değiştirmek istemiyorsanız aşağıdaki alanları boş bırakın.
          </p>

          <div class="form-group">
            <label class="form-label" for="password">Yeni Şifre</label>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="En az 6 karakter" minlength="6" autocomplete="new-password">
          </div>

          <div class="form-group">
            <label class="form-label" for="password2">Yeni Şifre Tekrar</label>
            <input type="password" id="password2" name="password2" class="form-control"
                   placeholder="Şifrenizi tekrar girin" autocomplete="new-password">
          </div>

          <button type="submit" class="btn btn-primary btn-full">
            💾 Değişiklikleri Kaydet
          </button>
        </form>
      </div>
    </div>

    <div style="text-align:center;margin-top:20px;">
      <a href="<?= APP_URL ?>/logout.php" class="btn btn-danger btn-sm"
         data-confirm="Çıkış yapmak istediğinizden emin misiniz?">
        🚪 Çıkış Yap
      </a>
    </div>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
