<?php
/**
 * Giriş Sayfası (Müşteri + Firma)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (isLoggedIn()) {
    $u = currentUser();
    header('Location: ' . match($u['role']) {
        'admin'      => APP_URL . '/admin/',
        'company'    => APP_URL . '/company-panel.php',
        'consultant' => APP_URL . '/consultant-panel.php',
        default      => APP_URL . '/my-orders.php',
    });
    exit;
}

$error = '';
$email = '';

// Banlı kullanıcı uyarısı
if (isset($_GET['error']) && $_GET['error'] === 'banned') {
    $error = 'Hesabınız askıya alınmıştır. Detay için iletişime geçin.';
}

if (isPost()) {
    verifyCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'E-posta ve şifre zorunludur.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id, name, email, password, role, status FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'E-posta veya şifre hatalı.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Hesabınız aktif değil. Lütfen iletişime geçin.';
        } else {
            loginUser($user);

            // Admin için admin paneline, diğerleri için rol paneline
            $redirect = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);

            if (!$redirect) {
                $redirect = match($user['role']) {
                    'admin'      => APP_URL . '/admin/',
                    'company'    => APP_URL . '/company-panel.php',
                    'consultant' => APP_URL . '/consultant-panel.php',
                    default      => APP_URL . '/my-orders.php',
                };
            }

            $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Hoş geldiniz, ' . $user['name'] . '!'];
            header('Location: ' . $redirect);
            exit;
        }
    }
}

$pageTitle = 'Giriş Yap';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <div style="font-size:48px;">🔑</div>
      <h2>Giriş Yap</h2>
      <p style="font-size:13px;color:var(--text-muted);">Hesabınıza giriş yapın</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" data-once>
      <?= csrfInput() ?>

      <div class="form-group">
        <label class="form-label" for="email">E-posta</label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= e($email) ?>" placeholder="ornek@email.com"
               required autocomplete="username">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Şifre</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="Şifreniz" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px;">
        🔑 Giriş Yap
      </button>
    </form>

    <div class="auth-divider">veya</div>

    <div style="text-align:center;font-size:14px;">
      Hesabınız yok mu? <a href="<?= APP_URL ?>/register.php"><strong>Kayıt Ol</strong></a>
    </div>
    <div style="text-align:center;font-size:13px;margin-top:12px;">
      Firma mısınız? <a href="<?= APP_URL ?>/company-register.php">Firma Başvurusu</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
