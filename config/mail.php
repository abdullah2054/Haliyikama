<?php
/**
 * PHPMailer SMTP yapılandırması
 * Composer ile PHPMailer kurulmamışsa vendor/autoload.php yolunu düzenleyin.
 * Hostinger'da composer kullanılabilir veya PHPMailer manuel eklenebilir.
 */

// Composer autoload (varsa)
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * SMTP ayarlarını veritabanından veya sabit değerlerden döndürür.
 */
function getSmtpConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    // Veritabanından ayarları çek
    try {
        $pdo = getDB();
        $keys = ['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from_name','smtp_encryption','site_email','site_name'];
        $in   = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($in)");
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $config = $rows;
    } catch (Exception $e) {
        // Fallback sabit değerler
        $config = [
            'smtp_host'       => 'smtp.example.com',
            'smtp_port'       => '587',
            'smtp_user'       => 'mail@example.com',
            'smtp_pass'       => '',
            'smtp_from_name'  => 'Halı Yıkama Pazaryeri',
            'smtp_encryption' => 'tls',
            'site_email'      => 'info@siteadi.com',
            'site_name'       => 'Halı Yıkama Pazaryeri',
        ];
    }

    return $config;
}

/**
 * E-posta gönderir.
 *
 * @param string $toEmail   Alıcı e-posta
 * @param string $toName    Alıcı adı
 * @param string $subject   Konu
 * @param string $htmlBody  HTML içerik
 * @return bool
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log('PHPMailer yüklü değil. E-posta gönderilemedi.');
        return false;
    }

    $cfg  = getSmtpConfig();
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'] ?? 'localhost';
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_user'] ?? '';
        $mail->Password   = $cfg['smtp_pass'] ?? '';
        $mail->Port       = (int)($cfg['smtp_port'] ?? 587);

        $enc = strtolower($cfg['smtp_encryption'] ?? 'tls');
        $mail->SMTPSecure = ($enc === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom(
            $cfg['smtp_user']      ?? 'no-reply@siteadi.com',
            $cfg['smtp_from_name'] ?? 'Halı Yıkama Pazaryeri'
        );
        $mail->addAddress($toEmail, $toName);
        $mail->CharSet  = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject  = $subject;
        $mail->Body     = $htmlBody;
        $mail->AltBody  = strip_tags(str_replace(['<br>', '<br/>','<br />','</p>'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('E-posta gönderim hatası: ' . $mail->ErrorInfo);
        return false;
    }
}
