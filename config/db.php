<?php
define('DB_HOST',    'localhost');
define('DB_NAME',    'u929469444_asm_demo');
define('DB_USER',    'u929469444_asm_demo');
define('DB_PASS',    'Yildirim.88');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB Bağlantı Hatası: ' . $e->getMessage());
            http_response_code(503);
            die('<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bağlantı Hatası</title>
<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f0f4f8;}
.box{background:#fff;padding:40px;border-radius:12px;text-align:center;max-width:480px;box-shadow:0 4px 20px rgba(0,0,0,.1);}
h2{color:#dc2626;}p{color:#6b7280;font-size:14px;}</style></head>
<body><div class="box"><h2>⚠️ Veritabanı Bağlantı Hatası</h2>
<p>Sunucu geçici olarak kullanılamıyor. Lütfen daha sonra tekrar deneyin.</p>
</div></body></html>');
        }
    }

    return $pdo;
}
