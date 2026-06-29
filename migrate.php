<?php
/**
 * Veritabanı kurulum scripti.
 * Tablolar zaten varsa hiçbir şey yapmaz (güvenli, tekrar çalıştırılabilir).
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

try {
    $pdo = getDB();

    // Tablolar zaten kuruluysa sadece şema güncellemelerini çalıştır
    if ($pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn()) {
        $updates = [];

        // consultant rolü ENUM'a ekle (yoksa)
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
        if ($col && strpos($col['Type'], 'consultant') === false) {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('customer','company','consultant','admin') NOT NULL DEFAULT 'customer'");
            $updates[] = 'consultant role eklendi';
        }

        // messages tablosunu oluştur (yoksa)
        if (!$pdo->query("SHOW TABLES LIKE 'messages'")->fetchColumn()) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `messages` (
              `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `order_id`   INT UNSIGNED NOT NULL,
              `company_id` INT UNSIGNED NOT NULL,
              `sender_id`  INT UNSIGNED NOT NULL,
              `message`    TEXT NOT NULL,
              `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              INDEX `idx_order_company` (`order_id`, `company_id`),
              INDEX `idx_unread` (`is_read`),
              FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)    ON DELETE CASCADE,
              FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
              FOREIGN KEY (`sender_id`)  REFERENCES `users`(`id`)     ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $updates[] = 'messages tablosu oluşturuldu';
        }

        echo json_encode(['status' => 'already_installed', 'updates' => $updates]);
        exit;
    }

    $sqlFile = __DIR__ . '/database/install.sql';
    if (!file_exists($sqlFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'install.sql bulunamadı']);
        exit;
    }

    // SQL satırlarını oku, yorumları temizle, ifadeleri çalıştır
    $lines = file($sqlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $buffer = '';

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '--') || str_starts_with($line, '#')) continue;

        $buffer .= ' ' . $line;

        if (str_ends_with($line, ';')) {
            $stmt = trim(rtrim(trim($buffer), ';'));
            if ($stmt !== '') {
                try {
                    $pdo->exec($stmt);
                } catch (\PDOException $e) {
                    // Zaten var hatalarını yoksay
                    if (!in_array($e->getCode(), ['42S01', '23000'])) throw $e;
                }
            }
            $buffer = '';
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['status' => 'success', 'tables_created' => count($tables)]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
