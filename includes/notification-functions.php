<?php
/**
 * Bildirim sistemi - Modüler yapı
 * Aktif: E-posta
 * Pasif altyapı: SMS, Firebase Push
 */

require_once APP_ROOT . '/config/mail.php';

/**
 * Sipariş durumuna göre tüm bildirimleri gönderir.
 * İleride SMS veya Push aktifleştirilmek istenirse
 * sadece ilgili fonksiyon güncellenir.
 */
function sendOrderNotification(int $orderId, string $status, ?int $changedBy = null): void
{
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT o.*, u.name AS customer_name, u.email AS customer_email,
                c.company_name, cu.email AS company_email
         FROM orders o
         JOIN users u ON u.id = o.customer_id
         LEFT JOIN companies c ON c.id = o.company_id
         LEFT JOIN users cu ON cu.id = c.user_id
         WHERE o.id = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return;

    // E-posta her zaman dene
    sendEmailNotification($order, $status);

    // SMS altyapısı (şimdilik pasif)
    if (getSetting('sms_enabled') === '1') {
        sendSmsNotification($order, $status);
    }

    // Push bildirim altyapısı (şimdilik pasif)
    if (getSetting('push_enabled') === '1') {
        sendPushNotification(
            (int)$order['customer_id'],
            orderStatusLabel($status),
            $order['tracking_code'] . ' siparişiniz güncellendi.'
        );
    }

    // Bildirim kaydı
    $pdo->prepare(
        'INSERT INTO notifications (user_id, order_id, type, title, message, status)
         VALUES (?, ?, "email", ?, ?, "sent")'
    )->execute([
        $order['customer_id'],
        $orderId,
        orderStatusLabel($status),
        $order['tracking_code'] . ' numaralı siparişiniz güncellendi: ' . orderStatusLabel($status),
    ]);
}

/**
 * E-posta bildirimi
 */
function sendEmailNotification(array $order, string $status): void
{
    $siteName    = getSetting('site_name', 'Halı Yıkama Pazaryeri');
    $trackingUrl = APP_URL . '/track.php?kod=' . urlencode($order['tracking_code']);

    $subject = match($status) {
        'pending'    => 'Halı yıkama talebiniz oluşturuldu',
        'accepted'   => 'Firmanız talebinizi kabul etti',
        'picked_up'  => 'Halılarınız teslim alındı',
        'washing'    => 'Halılarınız yıkama aşamasında',
        'drying'     => 'Halılarınız kurutma aşamasında',
        'packaging'  => 'Halılarınız paketleniyor',
        'delivering' => 'Halılarınız teslimata çıktı',
        'delivered'  => 'Siparişiniz tamamlandı',
        'cancelled'  => 'Siparişiniz iptal edildi',
        default      => 'Sipariş durumu güncellendi',
    };

    $companyInfo = !empty($order['company_name'])
        ? '<strong>Firma:</strong> ' . e($order['company_name']) . '<br>'
        : '';

    $body = buildEmailTemplate($siteName, $subject, "
        <p>Merhaba <strong>" . e($order['customer_name']) . "</strong>,</p>
        <p><strong>" . e($order['tracking_code']) . "</strong> numaralı halı yıkama siparişinizin durumu güncellendi.</p>
        <div class='status-box'>
            <p><strong>Yeni Durum:</strong><br><span class='status-text'>" . e(orderStatusLabel($status)) . "</span></p>
            " . $companyInfo . "
        </div>
        <p>Siparişinizi aşağıdaki bağlantıdan takip edebilirsiniz:</p>
        <p><a href='" . $trackingUrl . "' class='btn-link'>Siparişimi Takip Et</a></p>
        <p>Bizi tercih ettiğiniz için teşekkür ederiz.</p>
    ");

    sendMail($order['customer_email'], $order['customer_name'], $subject, $body);
}

/**
 * Firma onay/ret e-postası
 */
function sendCompanyStatusEmail(array $company, string $status): void
{
    $siteName = getSetting('site_name', 'Halı Yıkama Pazaryeri');

    if ($status === 'active') {
        $subject = 'Firma başvurunuz onaylandı!';
        $content = '<p>Merhaba <strong>' . e($company['authorized_name']) . '</strong>,</p>
            <p>Firma başvurunuz onaylandı. Artık müşteri taleplerini görüntüleyebilir ve teklif verebilirsiniz.</p>
            <p><a href="' . APP_URL . '/login.php" class="btn-link">Panele Giriş Yap</a></p>';
    } else {
        $subject = 'Firma başvurunuz hakkında bilgi';
        $content = '<p>Merhaba <strong>' . e($company['authorized_name']) . '</strong>,</p>
            <p>Maalesef firma başvurunuz reddedildi. Daha fazla bilgi için bizimle iletişime geçebilirsiniz.</p>';
    }

    sendMail($company['email'], $company['authorized_name'], $subject,
        buildEmailTemplate($siteName, $subject, $content));
}

/**
 * Yeni teklif e-postası müşteriye
 */
function sendOfferNotificationEmail(array $order, array $offer, array $company): void
{
    $siteName = getSetting('site_name', 'Halı Yıkama Pazaryeri');
    $subject  = 'Talebinize yeni bir teklif geldi';
    $orderUrl = APP_URL . '/order-detail.php?id=' . $order['id'];

    $body = buildEmailTemplate($siteName, $subject, "
        <p>Merhaba <strong>" . e($order['customer_name']) . "</strong>,</p>
        <p><strong>" . e($order['tracking_code']) . "</strong> numaralı talebinize <strong>" . e($company['company_name']) . "</strong> firmasından teklif geldi.</p>
        <div class='status-box'>
            <p><strong>Teklif Fiyatı:</strong> " . formatMoney($offer['price']) . "</p>
            <p><strong>Tahmini Teslim:</strong> " . formatDateTR($offer['estimated_delivery_date']) . "</p>
        </div>
        <p><a href='" . $orderUrl . "' class='btn-link'>Teklifi Görüntüle</a></p>
    ");

    sendMail($order['customer_email'], $order['customer_name'], $subject, $body);
}

/**
 * E-posta şablonu oluşturucu
 */
function buildEmailTemplate(string $siteName, string $title, string $content): string
{
    return '<!DOCTYPE html>
<html lang="tr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . e($title) . '</title>
<style>
  body{margin:0;padding:0;background:#f0f4f8;font-family:Arial,sans-serif;color:#333;}
  .wrapper{max-width:600px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);}
  .header{background:linear-gradient(135deg,#1a3a5c,#2563eb);padding:28px 32px;text-align:center;}
  .header h1{color:#fff;margin:0;font-size:22px;}
  .body{padding:32px;}
  .body p{line-height:1.7;margin:0 0 16px;}
  .status-box{background:#f0f7ff;border-left:4px solid #2563eb;padding:16px 20px;border-radius:6px;margin:20px 0;}
  .status-text{font-size:18px;font-weight:bold;color:#2563eb;}
  .btn-link{display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;text-decoration:none;border-radius:8px;font-weight:bold;margin-top:8px;}
  .footer{background:#f8f9fa;padding:20px 32px;text-align:center;font-size:13px;color:#888;border-top:1px solid #eee;}
</style></head>
<body>
<div class="wrapper">
  <div class="header"><h1>' . e($siteName) . '</h1></div>
  <div class="body">' . $content . '</div>
  <div class="footer">&copy; ' . date('Y') . ' ' . e($siteName) . ' &mdash; Bu e-posta otomatik olarak gönderilmiştir.</div>
</div>
</body></html>';
}

/* ============================================================
 * SMS Altyapısı (Şimdilik Pasif)
 * ============================================================ */
function sendSmsNotification(array $order, string $status): void
{
    // İleride Netgsm, Twilio vb. entegrasyon buraya eklenecek
    // Örnek:
    // $phone   = $order['customer_phone'];
    // $message = $order['tracking_code'] . ' siparişiniz: ' . orderStatusLabel($status);
    // netgsmSend($phone, $message);
}

/* ============================================================
 * Firebase Push Bildirim Altyapısı (Şimdilik Pasif)
 * ============================================================ */
function sendPushNotification(int $userId, string $title, string $message): void
{
    // İleride Firebase FCM entegrasyonu buraya eklenecek
    // Device token'lar device_tokens tablosundan çekilir
    // $pdo   = getDB();
    // $stmt  = $pdo->prepare('SELECT device_token, platform FROM device_tokens WHERE user_id = ?');
    // $stmt->execute([$userId]);
    // $tokens = $stmt->fetchAll();
    // foreach ($tokens as $t) { fcmSend($t['device_token'], $title, $message); }
}
