<?php
/**
 * Veritabanı kurulum scripti - GitHub Actions tarafından otomatik çağrılır
 * Manuel çalıştırma: ?key=MIGRATE_KEY değeri ile
 */

$key = $_GET['key'] ?? $_SERVER['HTTP_X_MIGRATE_KEY'] ?? '';
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

    // Zaten kuruluysa atla
    $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($exists) {
        echo json_encode(['status' => 'already_installed', 'message' => 'Tablolar zaten mevcut, kurulum atlandı.']);
        exit;
    }

    // install.sql oku ve çalıştır
    $sqlFile = __DIR__ . '/database/install.sql';
    if (!file_exists($sqlFile)) {
        echo json_encode(['error' => 'install.sql bulunamadı']);
        exit;
    }

    $sql = file_get_contents($sqlFile);

    // SQL ifadelerini ayır ve çalıştır
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => $s !== '' && !preg_match('/^--/', $s)
    );

    $pdo->beginTransaction();
    foreach ($statements as $statement) {
        if (trim($statement) === '') continue;
        $pdo->exec($statement);
    }
    $pdo->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Veritabanı başarıyla kuruldu.',
        'tables'  => $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN),
    ]);

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
