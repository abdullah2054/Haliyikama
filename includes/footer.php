<?php
/**
 * Ortak alt bölüm
 */
$siteName = getSetting('site_name', 'Halı Yıkama Pazaryeri');
?>
</main><!-- /main -->

<!-- Site Footer -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-col">
        <h4>🏡 <?= e($siteName) ?></h4>
        <p style="font-size:13px;line-height:1.6;color:rgba(255,255,255,.6);">
          Bölgenizdeki güvenilir halı yıkama firmalarına kolayca ulaşın.
        </p>
      </div>
      <div class="footer-col">
        <h4>Hızlı Bağlantılar</h4>
        <a href="<?= APP_URL ?>/">Ana Sayfa</a>
        <a href="<?= APP_URL ?>/create-order.php">Talep Oluştur</a>
        <a href="<?= APP_URL ?>/track.php">Sipariş Takip</a>
        <a href="<?= APP_URL ?>/company-register.php">Firma Başvurusu</a>
      </div>
      <div class="footer-col">
        <h4>Kurumsal</h4>
        <a href="#">Hakkımızda</a>
        <a href="#">İletişim</a>
        <a href="#">Gizlilik Politikası</a>
        <a href="#">Kullanım Şartları</a>
      </div>
      <div class="footer-col">
        <h4>İletişim</h4>
        <a href="mailto:<?= e(getSetting('site_email', 'info@siteadi.com')) ?>">
          ✉️ <?= e(getSetting('site_email', 'info@siteadi.com')) ?>
        </a>
      </div>
    </div>
    <div class="footer-bottom">
      &copy; <?= date('Y') ?> <?= e($siteName) ?>. Tüm hakları saklıdır.
    </div>
  </div>
</footer>

<!-- Mobil Alt Menü -->
<?php include APP_ROOT . '/includes/mobile-nav.php'; ?>

<!-- JavaScript -->
<script src="<?= APP_URL ?>/assets/js/app.js" defer></script>
<?php if (!empty($extraJs)) echo $extraJs; ?>

</body>
</html>
