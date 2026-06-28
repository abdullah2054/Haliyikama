<?php
/**
 * API: Mahalle listesi (İlçe ID'sine göre)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$districtId = (int)($_GET['district_id'] ?? 0);

if (!$districtId) {
    echo '[]';
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare('SELECT id, name FROM locations_neighborhoods WHERE district_id = ? ORDER BY name');
$stmt->execute([$districtId]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
