<?php
/**
 * Müşteri Kayıt Sayfası
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Zaten giriş yapmışsa yönlendir
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/my-orders.php');
    exit;
}

$errors = [];
$values = ['name' => '', 'email' => '', 'phone' => ''];

if (isPost()) {
    verifyCsrf();

    $values['name']  = trim($_POST['name']  ?? '');
    $values['email'] = trim($_POST['email'] ?? '');
    $values['phone'] = trim($_POST['phone'] ?? '');
    $password        = $_POST['password']  ?? '';
    $password2       = $_POST['password2'] ?? '';

    if (mb_strlen($values['name']) < 2)  $errors[] = 'Ad soyad en az 2 karakter olmalı.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta girin.';
    if (strlen($password) < 6) $errors[] = 'Şifre en az 6 karakter olmalı.';
    if ($password !== $password2) $errors[] = 'Şifreler eşleşmiyor.';

    if (empty($errors)) {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$values['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Bu e-posta adresi zaten kayıtlı.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare(
                'INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, "customer")'
            )->execute([$values['name'], $values['email'], $values['phone'], $hash]);

            $newId = (int)$pdo->lastInsertId();
            loginUser(['id' => $newId, 'role' => 'customer', 'name' => $values['name']]);
            $_SESSION['flash'][] = ['type' => 'success', 'text' => 'Hoş geldiniz! Hesabınız oluşturuldu.'];
            header('Location: ' . APP_URL . '/my-orders.php');
            exit;
        }
    }
}

$pageTitle = 'Kayıt Ol';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <div style="font-size:48px;">🏡</div>
      <h2>Hesap Oluştur</h2>
      <p style="font-size:13px;color:var(--text-muted);">Halı yıkama hizmetine hemen başlayın</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <span>⚠️</span>
        <div><?= implode('<br>', array_map('e', $errors)) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" data-once>
      <?= csrfInput() ?>

      <div class="form-group">
        <label class="form-label" for="name">Ad Soyad *</label>
        <input type="text" id="name" name="name" class="form-control"
               value="<?= e($values['name']) ?>" placeholder="Adınız Soyadınız"
               required maxlength="150" autocomplete="name">
      </div>

      <div class="form-group">
        <label class="form-label" for="email">E-posta *</label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= e($values['email']) ?>" placeholder="ornek@email.com"
               required maxlength="200" autocomplete="email">
      </div>

      <div class="form-group">
        <label class="form-label" for="phone">Telefon</label>
        <input type="tel" id="phone" name="phone" class="form-control"
               value="<?= e($values['phone']) ?>" placeholder="05XX XXX XX XX"
               maxlength="20" autocomplete="tel">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Şifre *</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="En az 6 karakter" required minlength="6" autocomplete="new-password">
      </div>

      <div class="form-group">
        <label class="form-label" for="password2">Şifre Tekrar *</label>
        <input type="password" id="password2" name="password2" class="form-control"
               placeholder="Şifrenizi tekrar girin" required minlength="6" autocomplete="new-password">
      </div>

      <div class="form-check" style="margin-bottom:20px;">
        <input type="checkbox" id="terms" name="terms" required>
        <label for="terms" style="font-size:13px;">
          <a href="#">Kullanım Şartları</a> ve <a href="#">Gizlilik Politikası</a>'nı kabul ediyorum.
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg">
        ✅ Hesap Oluştur
      </button>
    </form>

    <div class="auth-divider">veya</div>

    <div style="text-align:center;font-size:14px;">
      Zaten hesabınız var mı? <a href="<?= APP_URL ?>/login.php"><strong>Giriş Yap</strong></a>
    </div>
    <div style="text-align:center;font-size:13px;margin-top:12px;">
      Firma mısınız? <a href="<?= APP_URL ?>/company-register.php">Firma Başvurusu</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
