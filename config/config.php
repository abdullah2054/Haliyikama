<?php
/**
 * Uygulama genel ayarları
 * Hostinger ve diğer shared hosting ortamları için uyarlanmıştır.
 */

// Hata gösterimini production'da kapatın (geliştirme için true yapın)
define('APP_DEBUG', true);

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

// Oturum ayarları - WebView uyumlu güvenli yapılandırma
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    $isHttps = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    session_start();
}

// ---------------------------------------------------------------
// APP_URL: Otomatik tespit eder — değiştirmenize gerek yok.
// Zorunlu olarak elle ayarlamak isterseniz aşağıdaki satırı
// açıp kendi domain'inizi yazın:
// define('APP_URL', 'https://www.siteadi.com');
// ---------------------------------------------------------------
if (!defined('APP_URL')) {
    $scheme = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('APP_URL', rtrim($scheme . '://' . $host, '/'));
}

// Uygulama kök dizini
define('APP_ROOT', dirname(__DIR__));

// Yükleme dizini (web'den erişilebilir olmalı)
define('UPLOAD_DIR', APP_ROOT . '/assets/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/');

// Dosya yükleme limitleri
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Takip kodu öneki
define('TRACKING_PREFIX', 'HK');

// CSRF token süresi (saniye)
define('CSRF_TOKEN_EXPIRE', 3600);

// Sipariş durum etiketleri
define('ORDER_STATUSES', [
    'pending'    => 'Talep Oluşturuldu',
    'accepted'   => 'Firma Talebi Aldı',
    'picked_up'  => 'Halılar Teslim Alındı',
    'washing'    => 'Yıkama Aşamasında',
    'drying'     => 'Kurutma Aşamasında',
    'packaging'  => 'Paketleniyor',
    'delivering' => 'Teslimata Çıktı',
    'delivered'  => 'Teslim Edildi',
    'cancelled'  => 'İptal Edildi',
]);

// Sipariş durum badge renkleri
define('ORDER_STATUS_COLORS', [
    'pending'    => 'warning',
    'accepted'   => 'info',
    'picked_up'  => 'primary',
    'washing'    => 'primary',
    'drying'     => 'primary',
    'packaging'  => 'secondary',
    'delivering' => 'info',
    'delivered'  => 'success',
    'cancelled'  => 'danger',
]);

// Rol etiketleri
define('USER_ROLES', [
    'customer' => 'Müşteri',
    'company'  => 'Firma',
    'admin'    => 'Admin',
]);
