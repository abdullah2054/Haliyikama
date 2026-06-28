<?php
/**
 * Mobil alt navigasyon menüsü - Role göre değişir
 * WebView uygulaması hissi için sabit alt menü
 */

if (!isLoggedIn()) {
    // Giriş yapılmamış: misafir menüsü
    ?>
    <nav class="bottom-nav" id="bottomNav" aria-label="Alt menü">
        <div class="bottom-nav-inner">
            <a href="<?= APP_URL ?>/index.php" class="bottom-nav-item" title="Ana Sayfa">
                <span class="nav-icon">🏠</span>
                <span>Ana Sayfa</span>
            </a>
            <a href="<?= APP_URL ?>/track.php" class="bottom-nav-item" title="Takip Et">
                <span class="nav-icon">📦</span>
                <span>Takip Et</span>
            </a>
            <a href="<?= APP_URL ?>/register.php" class="bottom-nav-item" title="Kayıt Ol">
                <span class="nav-icon">✍️</span>
                <span>Kayıt Ol</span>
            </a>
            <a href="<?= APP_URL ?>/login.php" class="bottom-nav-item" title="Giriş Yap">
                <span class="nav-icon">🔑</span>
                <span>Giriş</span>
            </a>
        </div>
    </nav>
    <?php
    return;
}

$user = currentUser();
$role = $user['role'] ?? 'customer';
?>
<nav class="bottom-nav" id="bottomNav" aria-label="Alt menü">
    <div class="bottom-nav-inner">
    <?php if ($role === 'customer'): ?>
        <a href="<?= APP_URL ?>/index.php" class="bottom-nav-item" title="Ana Sayfa">
            <span class="nav-icon">🏠</span>
            <span>Ana Sayfa</span>
        </a>
        <a href="<?= APP_URL ?>/create-order.php" class="bottom-nav-item" title="Talep Oluştur">
            <span class="nav-icon">➕</span>
            <span>Talep</span>
        </a>
        <a href="<?= APP_URL ?>/my-orders.php" class="bottom-nav-item" title="Siparişlerim">
            <span class="nav-icon">📋</span>
            <span>Siparişlerim</span>
        </a>
        <a href="<?= APP_URL ?>/track.php" class="bottom-nav-item" title="Takip Et">
            <span class="nav-icon">📦</span>
            <span>Takip</span>
        </a>
        <a href="<?= APP_URL ?>/profile.php" class="bottom-nav-item" title="Profil">
            <span class="nav-icon">👤</span>
            <span>Profil</span>
        </a>
    <?php elseif ($role === 'company'): ?>
        <a href="<?= APP_URL ?>/company-panel.php" class="bottom-nav-item" title="Panel">
            <span class="nav-icon">📊</span>
            <span>Panel</span>
        </a>
        <a href="<?= APP_URL ?>/company-offers.php" class="bottom-nav-item" title="Talepler">
            <span class="nav-icon">📥</span>
            <span>Talepler</span>
        </a>
        <a href="<?= APP_URL ?>/company-orders.php" class="bottom-nav-item" title="Siparişler">
            <span class="nav-icon">📋</span>
            <span>Siparişler</span>
        </a>
        <a href="<?= APP_URL ?>/company-wallet.php" class="bottom-nav-item" title="Bakiye">
            <span class="nav-icon">💰</span>
            <span>Bakiye</span>
        </a>
        <a href="<?= APP_URL ?>/profile.php" class="bottom-nav-item" title="Profil">
            <span class="nav-icon">👤</span>
            <span>Profil</span>
        </a>
    <?php elseif ($role === 'admin'): ?>
        <a href="<?= APP_URL ?>/admin/index.php" class="bottom-nav-item" title="Panel">
            <span class="nav-icon">🛡️</span>
            <span>Panel</span>
        </a>
        <a href="<?= APP_URL ?>/admin/companies.php" class="bottom-nav-item" title="Firmalar">
            <span class="nav-icon">🏢</span>
            <span>Firmalar</span>
        </a>
        <a href="<?= APP_URL ?>/admin/orders.php" class="bottom-nav-item" title="Siparişler">
            <span class="nav-icon">📦</span>
            <span>Siparişler</span>
        </a>
        <a href="<?= APP_URL ?>/admin/users.php" class="bottom-nav-item" title="Kullanıcılar">
            <span class="nav-icon">👥</span>
            <span>Kullanıcılar</span>
        </a>
        <a href="<?= APP_URL ?>/admin/settings.php" class="bottom-nav-item" title="Ayarlar">
            <span class="nav-icon">⚙️</span>
            <span>Ayarlar</span>
        </a>
    <?php endif; ?>
    </div>
</nav>
