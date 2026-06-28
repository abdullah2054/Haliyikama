<?php
/**
 * Admin Giriş Sayfası
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

if (isLoggedIn() && currentUser()['role'] === 'admin') {
    header('Location: ' . APP_URL . '/admin/');
    exit;
}

$error = '';

if (isPost()) {
    verifyCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id, name, email, password, role, status FROM users WHERE email = ? AND role = "admin"');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password']) && $user['status'] === 'active') {
            loginUser($user);
            header('Location: ' . APP_URL . '/admin/');
            exit;
        }
        $error = 'Geçersiz admin bilgileri.';
    } else {
        $error = 'E-posta ve şifre zorunludur.';
    }
}

$pageTitle = 'Admin Girişi';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Girişi</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <div style="font-size:48px;">🛡️</div>
      <h2>Admin Paneli</h2>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
    <?php endif; ?>
    <form method="POST" data-once>
      <?= csrfInput() ?>
      <div class="form-group">
        <label class="form-label">Admin E-posta</label>
        <input type="email" name="email" class="form-control" required autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label">Şifre</label>
        <input type="password" name="password" class="form-control" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg">🔑 Giriş Yap</button>
    </form>
    <div style="text-align:center;margin-top:20px;font-size:13px;color:var(--text-muted);">
      ⚠️ Varsayılan şifre: <code>password</code> — İlk girişten sonra mutlaka değiştirin!
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js" defer></script>
</body>
</html>
