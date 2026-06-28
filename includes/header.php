<?php
/**
 * Ortak üst bölüm - Tüm sayfalarda kullanılır
 */

// DB gerektiren getSetting() yerine güvenli fallback
$siteName  = 'Halı Yıkama Pazaryeri';
try { $siteName = getSetting('site_name', 'Halı Yıkama Pazaryeri'); } catch (\Throwable $e) {}

$pageTitle = $pageTitle ?? $siteName;
$user      = null;
try { $user = currentUser(); } catch (\Throwable $e) {}
$role      = $user['role'] ?? null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="theme-color" content="#1a3a5c">

  <!-- WebView için meta etiketleri -->
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

  <title><?= e($pageTitle) ?> | <?= e($siteName) ?></title>

  <!-- Ana CSS -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">

  <?php if (!empty($extraCss)) echo $extraCss; ?>
</head>
<body>

<!-- Flash Mesajları -->
<?php if (!empty($_SESSION['flash'])): ?>
<div class="flash-messages" id="flashMessages">
  <?php foreach ($_SESSION['flash'] as $msg): ?>
    <div class="toast <?= e($msg['type']) ?>"><?= e($msg['text']) ?></div>
  <?php endforeach; ?>
  <?php unset($_SESSION['flash']); ?>
</div>
<?php endif; ?>

<!-- Üst Navbar -->
<header class="navbar" id="mainNav">
  <div class="container">
    <a href="<?= APP_URL ?>/" class="navbar-brand">
      <div class="brand-icon">🏡</div>
      <span><?= e($siteName) ?></span>
    </a>

    <!-- Masaüstü Menü -->
    <nav class="navbar-links" aria-label="Ana menü">
      <?php if (!$user): ?>
        <a href="<?= APP_URL ?>/index.php">Ana Sayfa</a>
        <a href="<?= APP_URL ?>/track.php">Sipariş Takip</a>
        <a href="<?= APP_URL ?>/company-register.php">Firma Başvurusu</a>
        <a href="<?= APP_URL ?>/login.php" class="btn-nav">Giriş Yap</a>
        <a href="<?= APP_URL ?>/register.php" class="btn-nav" style="background:var(--success)">Kayıt Ol</a>
      <?php elseif ($role === 'customer'): ?>
        <a href="<?= APP_URL ?>/index.php">Ana Sayfa</a>
        <a href="<?= APP_URL ?>/create-order.php">Talep Oluştur</a>
        <a href="<?= APP_URL ?>/my-orders.php">Siparişlerim</a>
        <a href="<?= APP_URL ?>/track.php">Takip Et</a>
        <a href="<?= APP_URL ?>/profile.php">👤 <?= e($user['name']) ?></a>
        <a href="<?= APP_URL ?>/logout.php">Çıkış</a>
      <?php elseif ($role === 'company'): ?>
        <a href="<?= APP_URL ?>/company-panel.php">Panel</a>
        <a href="<?= APP_URL ?>/company-offers.php">Talepler</a>
        <a href="<?= APP_URL ?>/company-orders.php">Siparişler</a>
        <a href="<?= APP_URL ?>/company-wallet.php">Bakiye</a>
        <a href="<?= APP_URL ?>/profile.php">👤 Profil</a>
        <a href="<?= APP_URL ?>/logout.php">Çıkış</a>
      <?php elseif ($role === 'admin'): ?>
        <a href="<?= APP_URL ?>/admin/">Admin Panel</a>
        <a href="<?= APP_URL ?>/logout.php">Çıkış</a>
      <?php endif; ?>
    </nav>

    <!-- Hamburger (Mobil) -->
    <button class="hamburger" id="hamburger" aria-label="Menüyü aç" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<!-- Mobil Dropdown Menü -->
<div class="navbar-mobile" id="navMobile">
  <?php if (!$user): ?>
    <a href="<?= APP_URL ?>/index.php">🏠 Ana Sayfa</a>
    <a href="<?= APP_URL ?>/track.php">📦 Sipariş Takip</a>
    <a href="<?= APP_URL ?>/company-register.php">🏢 Firma Başvurusu</a>
    <a href="<?= APP_URL ?>/login.php">🔑 Giriş Yap</a>
    <a href="<?= APP_URL ?>/register.php">✍️ Kayıt Ol</a>
  <?php elseif ($role === 'customer'): ?>
    <a href="<?= APP_URL ?>/index.php">🏠 Ana Sayfa</a>
    <a href="<?= APP_URL ?>/create-order.php">➕ Talep Oluştur</a>
    <a href="<?= APP_URL ?>/my-orders.php">📋 Siparişlerim</a>
    <a href="<?= APP_URL ?>/track.php">📦 Sipariş Takip</a>
    <a href="<?= APP_URL ?>/profile.php">👤 Profil</a>
    <a href="<?= APP_URL ?>/logout.php">🚪 Çıkış Yap</a>
  <?php elseif ($role === 'company'): ?>
    <a href="<?= APP_URL ?>/company-panel.php">📊 Panel</a>
    <a href="<?= APP_URL ?>/company-offers.php">📥 Talepler</a>
    <a href="<?= APP_URL ?>/company-orders.php">📋 Siparişler</a>
    <a href="<?= APP_URL ?>/company-wallet.php">💰 Bakiye</a>
    <a href="<?= APP_URL ?>/company-settings.php">⚙️ Firma Ayarları</a>
    <a href="<?= APP_URL ?>/profile.php">👤 Profil</a>
    <a href="<?= APP_URL ?>/logout.php">🚪 Çıkış Yap</a>
  <?php elseif ($role === 'admin'): ?>
    <a href="<?= APP_URL ?>/admin/">🛡️ Admin Panel</a>
    <a href="<?= APP_URL ?>/admin/companies.php">🏢 Firmalar</a>
    <a href="<?= APP_URL ?>/admin/orders.php">📦 Siparişler</a>
    <a href="<?= APP_URL ?>/admin/users.php">👥 Kullanıcılar</a>
    <a href="<?= APP_URL ?>/admin/settings.php">⚙️ Ayarlar</a>
    <a href="<?= APP_URL ?>/logout.php">🚪 Çıkış Yap</a>
  <?php endif; ?>
</div>

<!-- Ana içerik başlangıcı -->
<main id="mainContent">
