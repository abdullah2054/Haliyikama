<?php
/**
 * Ana Sayfa
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$pageTitle = 'Ana Sayfa';
$siteName  = getSetting('site_name', 'Halı Yıkama Pazaryeri');

// Takip formu
$trackError   = '';
$trackCode    = '';
if (isPost() && isset($_POST['track_code'])) {
    $trackCode = trim($_POST['track_code']);
    if ($trackCode) {
        header('Location: ' . APP_URL . '/track.php?kod=' . urlencode($trackCode));
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <h1>Halı Yıkama Hizmeti<br>Artık Çok Kolay</h1>
    <p>Adresinizi girin, bölgenizdeki güvenilir halı yıkama firmalarından teklif alın. Halılarınız kapınızdan alınsın, temizlenip tekrar kapınıza teslim edilsin.</p>
    <div class="hero-btns">
      <a href="<?= APP_URL ?>/create-order.php" class="btn btn-lg" style="background:#fff;color:var(--primary)">
        🏡 Hemen Talep Oluştur
      </a>
      <a href="<?= APP_URL ?>/company-register.php" class="btn btn-lg btn-outline-primary" style="border-color:rgba(255,255,255,.6);color:#fff">
        🏢 Firma Olarak Katıl
      </a>
    </div>
  </div>
</section>

<!-- TAKIP WIDGET -->
<section class="section" style="padding:32px 0 24px;background:#fff;">
  <div class="container">
    <div class="track-widget">
      <h3>📦 Sipariş Takip</h3>
      <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">
        Takip kodunuzu girerek siparişinizin güncel durumunu sorgulayın.
      </p>
      <form action="<?= APP_URL ?>/track.php" method="GET" class="track-form">
        <input type="text" name="kod" class="form-control"
               placeholder="Örn: HK-2026-000145"
               value="<?= e($trackCode) ?>"
               style="text-transform:uppercase" maxlength="20">
        <button type="submit" class="btn btn-primary">Sorgula</button>
      </form>
    </div>
  </div>
</section>

<!-- NASIL ÇALIŞIR -->
<section class="section">
  <div class="container">
    <h2 class="section-title">Nasıl Çalışır?</h2>
    <p class="section-subtitle">3 adımda temiz halılara kavuşun</p>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-num">1</div>
        <div class="step-icon">📝</div>
        <h4>Talebini Oluştur</h4>
        <p>İl, ilçe ve adres bilgilerinizi girerek halı yıkama talebi oluşturun.</p>
      </div>
      <div class="step-card">
        <div class="step-num">2</div>
        <div class="step-icon">💬</div>
        <h4>Teklif Al</h4>
        <p>Bölgenizde hizmet veren güvenilir firmalar size teklif gönderir.</p>
      </div>
      <div class="step-card">
        <div class="step-num">3</div>
        <div class="step-icon">✅</div>
        <h4>Firma Seç</h4>
        <p>En uygun teklifi seçin. Firma sizinle anlaşsın.</p>
      </div>
      <div class="step-card">
        <div class="step-num">4</div>
        <div class="step-icon">🚚</div>
        <h4>Halılar Alınsın</h4>
        <p>Firma halılarınızı kapınızdan teslim alır, yıkayıp teslim eder.</p>
      </div>
      <div class="step-card">
        <div class="step-num">5</div>
        <div class="step-icon">📦</div>
        <h4>Durumu Takip Et</h4>
        <p>Takip kodunuzla siparişinizin her aşamasını anlık izleyin.</p>
      </div>
    </div>
  </div>
</section>

<!-- FİRMA AVANTAJLARI -->
<section class="section" style="background:#fff;">
  <div class="container">
    <h2 class="section-title">Firmanızı Büyütün</h2>
    <p class="section-subtitle">Halı yıkama pazaryerimize katılın, yeni müşterilere ulaşın</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-top:8px;">

      <div class="card" style="padding:24px;text-align:center;">
        <div style="font-size:36px;margin-bottom:12px;">🎯</div>
        <h4 style="margin-bottom:8px;">Hedefli Müşteriler</h4>
        <p style="font-size:13px;color:var(--text-muted);">Sadece hizmet verdiğiniz bölgedeki talepleri görün, gereksiz uzaklara gitmeyin.</p>
      </div>

      <div class="card" style="padding:24px;text-align:center;">
        <div style="font-size:36px;margin-bottom:12px;">💰</div>
        <h4 style="margin-bottom:8px;">Kullandığın Kadar Öde</h4>
        <p style="font-size:13px;color:var(--text-muted);">Sabit abonelik yok. Sadece kabul ettiğiniz siparişler için platform ücreti kesilir.</p>
      </div>

      <div class="card" style="padding:24px;text-align:center;">
        <div style="font-size:36px;margin-bottom:12px;">📱</div>
        <h4 style="margin-bottom:8px;">Kolay Yönetim</h4>
        <p style="font-size:13px;color:var(--text-muted);">Siparişleri telefonunuzdan yönetin, müşterilere anlık durum güncellemesi gönderin.</p>
      </div>

      <div class="card" style="padding:24px;text-align:center;">
        <div style="font-size:36px;margin-bottom:12px;">⭐</div>
        <h4 style="margin-bottom:8px;">Güven Oluşturun</h4>
        <p style="font-size:13px;color:var(--text-muted);">Müşteri yorumları ve puanlarıyla itibarınızı artırın.</p>
      </div>

    </div>
    <div style="text-align:center;margin-top:28px;">
      <a href="<?= APP_URL ?>/company-register.php" class="btn btn-primary btn-lg">
        🏢 Hemen Başvur
      </a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
