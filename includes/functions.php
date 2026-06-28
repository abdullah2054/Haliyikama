<?php
/**
 * Genel yardımcı fonksiyonlar
 */

/**
 * XSS güvenli çıktı
 */
function e(mixed $val): string
{
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Benzersiz takip kodu üretir: HK-2026-000145
 */
function generateTrackingCode(): string
{
    $pdo  = getDB();
    $year = date('Y');

    do {
        $seq  = random_int(1, 999999);
        $code = TRACKING_PREFIX . '-' . $year . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT id FROM orders WHERE tracking_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetch());

    return $code;
}

/**
 * Sipariş durumu Türkçe etiketi
 */
function orderStatusLabel(string $status): string
{
    return ORDER_STATUSES[$status] ?? $status;
}

/**
 * Sipariş durum badge rengi
 */
function orderStatusColor(string $status): string
{
    return ORDER_STATUS_COLORS[$status] ?? 'secondary';
}

/**
 * Tarihi Türkçe formatlar: 28 Haziran 2026
 */
function formatDateTR(?string $date): string
{
    if (!$date) return '-';
    $months = [
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
        5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
        9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
    ];
    $ts = strtotime($date);
    return (int)date('d', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Tarih ve saati formatlar
 */
function formatDateTimeTR(?string $dt): string
{
    if (!$dt) return '-';
    $months = [
        1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis',
        5 => 'May', 6 => 'Haz', 7 => 'Tem', 8 => 'Ağu',
        9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara',
    ];
    $ts = strtotime($dt);
    return (int)date('d', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts)
         . ' ' . date('H:i', $ts);
}

/**
 * Para formatı: 1.250,00 TL
 */
function formatMoney(float|string $amount): string
{
    return number_format((float)$amount, 2, ',', '.') . ' TL';
}

/**
 * Site ayarını getirir
 */
function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $val  = $stmt->fetchColumn();
        $cache[$key] = ($val !== false) ? $val : $default;
    } catch (\Exception $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/**
 * İl adını getirir
 */
function getCityName(int $cityId): string
{
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT name FROM locations_cities WHERE id = ?');
    $stmt->execute([$cityId]);
    return $stmt->fetchColumn() ?: '';
}

/**
 * İlçe adını getirir
 */
function getDistrictName(int $districtId): string
{
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT name FROM locations_districts WHERE id = ?');
    $stmt->execute([$districtId]);
    return $stmt->fetchColumn() ?: '';
}

/**
 * Mahalle adını getirir
 */
function getNeighborhoodName(int $nId): string
{
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT name FROM locations_neighborhoods WHERE id = ?');
    $stmt->execute([$nId]);
    return $stmt->fetchColumn() ?: '';
}

/**
 * Firma bilgisini user_id'ye göre getirir
 */
function getCompanyByUserId(int $userId): ?array
{
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/**
 * Dosya yükleme (logo/fotoğraf)
 * Güvenli tip ve boyut kontrolü yapar.
 *
 * @return string|null  Kaydedilen dosya yolu veya null (hata)
 */
function uploadImage(array $file, string $subDir = 'logos'): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return null;
    }
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        return null;
    }

    $ext      = match($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg',
    };
    $dir      = UPLOAD_DIR . $subDir . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return $subDir . '/' . $filename;
}

/**
 * Kullanıcı hesabını engelleme durumunu kontrol eder
 */
function checkUserStatus(): void
{
    $user = currentUser();
    if ($user && $user['status'] !== 'active') {
        logoutUser();
        header('Location: ' . APP_URL . '/login.php?error=banned');
        exit;
    }
}

/**
 * Sayfalama yardımcısı
 */
function paginate(int $total, int $perPage, int $currentPage): array
{
    $totalPages = (int)ceil($total / $perPage);
    $offset     = ($currentPage - 1) * $perPage;
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
    ];
}

/**
 * JSON yanıt gönderir ve çıkar
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * POST isteği mi?
 */
function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Yıldız gösterimi (1-5)
 */
function starRating(int $rating): string
{
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $rating ? '★' : '☆';
    }
    return $stars;
}
