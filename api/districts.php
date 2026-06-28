<?php
/**
 * API: İlçe listesi (İl ID'sine göre)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$cityId = (int)($_GET['city_id'] ?? 0);

if (!$cityId) {
    echo '[]';
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare('SELECT id, name FROM locations_districts WHERE city_id = ? ORDER BY name');
$stmt->execute([$cityId]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
