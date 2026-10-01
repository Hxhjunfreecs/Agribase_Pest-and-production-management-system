<?php
$secureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

header('X-Frame-Options: DENY', true);
header('X-Content-Type-Options: nosniff', true);
header('Referrer-Policy: strict-origin-when-cross-origin', true);
header('Permissions-Policy: camera=(), microphone=(), geolocation=()', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
header('Pragma: no-cache', true);
$isMapPage = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'map.php';
$mapImageSources = $isMapPage ? ' https://unpkg.com https://tile.openstreetmap.org' : '';
$mapAssetSource = $isMapPage ? ' https://unpkg.com' : '';
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:$mapImageSources; style-src 'self' 'unsafe-inline'$mapAssetSource; script-src 'self' 'unsafe-inline'$mapAssetSource; font-src 'self' data:; connect-src 'self';", true);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $secureCookie ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
session_name('AGRIBASESESSID');
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => $secureCookie,
  'httponly' => true,
  'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');
const DB_HOST='127.0.0.1';
const DB_NAME='agribase';
const DB_USER='root';
const DB_PASS='';
try {
  $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
} catch(PDOException $e){ http_response_code(500); die('Database connection failed. Import database.sql and check includes/config.php.'); }

$pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
 user_id INT NOT NULL PRIMARY KEY,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$advisoryColumns = $pdo->query('SHOW COLUMNS FROM advisories')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('details', $advisoryColumns, true)) {
  $pdo->exec('ALTER TABLE advisories ADD COLUMN details TEXT NULL AFTER content');
}
if (!in_array('image_path', $advisoryColumns, true)) {
  $pdo->exec('ALTER TABLE advisories ADD COLUMN image_path VARCHAR(255) NULL AFTER details');
}
if (!in_array('image_alt', $advisoryColumns, true)) {
  $pdo->exec('ALTER TABLE advisories ADD COLUMN image_alt VARCHAR(180) NULL AFTER image_path');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS incident_assessments (
 id INT AUTO_INCREMENT PRIMARY KEY,
 incident_id INT NOT NULL,
 assessed_by INT NOT NULL,
 assessment TEXT NOT NULL,
 procedures TEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_incident_assessments_incident (incident_id),
 FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
 FOREIGN KEY (assessed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
