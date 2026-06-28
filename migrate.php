<?php
/**
 * Veritabanı kurulum scripti — GitHub Actions tarafından otomatik çağrılır.
 * MIGRATE_KEY secret ile korunur.
 */
$key         = $_GET['key'] ?? $_SERVER['HTTP_X_MIGRATE_KEY'] ?? '';
$expectedKey = getenv('MIGRATE_KEY') ?: '';

if (!$expectedKey || !hash_equals($expectedKey, $key)) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

try {
    $pdo = getDB();

    // Tablolar zaten varsa atla
    $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($exists) {
        echo json_encode(['status' => 'already_installed']);
        exit;
    }

    $sqlFile = __DIR__ . '/database/install.sql';
    if (!file_exists($sqlFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'install.sql bulunamadı']);
        exit;
    }

    // SQL'i satırlara böl, yorum satırlarını temizle, ifadeleri birleştir
    $lines      = file($sqlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $sql        = '';
    $statements = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) continue;
        $sql .= ' ' . $trimmed;
        if (str_ends_with($trimmed, ';')) {
            $statement = trim($sql);
            if ($statement !== ';' && strlen($statement) > 1) {
                $statements[] = rtrim($statement, ';');
            }
            $sql = '';
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
        } catch (\PDOException $e) {
            // Zaten var olan tablo/veri hatalarını yoksay
            if (!in_array($e->getCode(), ['42S01', '23000'])) {
                throw $e;
            }
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['status' => 'success', 'tables' => $tables]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
