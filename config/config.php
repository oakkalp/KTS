<?php
/**
 * Kurye Full System - Main Configuration
 * Ana konfigürasyon dosyası
 */

// Hata raporlamayı aç (development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Zaman dilimi ayarla
date_default_timezone_set('Europe/Istanbul');

// Session ayarları
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // HTTP için 0

// Uygulama ayarları
define('APP_NAME', 'Kurye Full System');
define('SITE_NAME', 'Kurye Full System');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', true); // Development için true

// URL ayarları
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_path = '/kuryefullsistem';

define('BASE_URL', $protocol . '://' . $host . $base_path);
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Dosya yolları
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Log dizinini oluştur
if (!file_exists(LOGS_PATH)) {
    mkdir(LOGS_PATH, 0755, true);
}

// Uploads dizinini oluştur
if (!file_exists(UPLOADS_PATH)) {
    mkdir(UPLOADS_PATH, 0755, true);
}

// Güvenlik ayarları
define('SECURITY_SALT', 'kurye_system_2024_security_salt_change_this');
define('JWT_SECRET', 'kurye_jwt_secret_key_change_this_in_production');
define('API_RATE_LIMIT', 100); // Dakikada maksimum API çağrısı

// Dosya yükleme ayarları
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Email ayarları
define('MAIL_FROM_EMAIL', 'noreply@kuryesystem.com');
define('MAIL_FROM_NAME', 'Kurye System');

// Push notification ayarları
define('FCM_SERVER_KEY', 'YOUR_LEGACY_SERVER_KEY_HERE'); // Firebase Cloud Messaging Legacy Server Key (AAAA... ile başlamalı)
define('FCM_SENDER_ID', '102349954988407659724'); // Firebase sender ID (client_id'den)

// Firebase Service Account bilgileri
define('FIREBASE_PROJECT_ID', 'kuryebildirim');
define('FIREBASE_CLIENT_EMAIL', 'firebase-adminsdk-fbsvc@kuryebildirim.iam.gserviceaccount.com');

// GetirYemek API ayarları
define('GETIR_API_BASE_URL', 'https://api.getir.com');
define('GETIR_API_KEY', 'YOUR_GETIR_API_KEY_HERE'); // Webhook'lar için API anahtarı
define('FIREBASE_PRIVATE_KEY', '-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCJFIup7A6FgyaA
uMVAAoOhRRntooxCrC7usHNhIoJRfNL63Q/sX611zDig54PX9ks8HCg+8u0Aj+ef
TwQWE3AuoCW5T1C/e+enw2ZbvsYVVnEhSkNIzMCGecd4S0KDgJHRGECyN1UpFqoX
IJiP7qYqccbrVt6+YpyeHsyi34nW9gTn2e4ZoF8wK6gTpI+XTDIQ1pWjZCueMMLm
+rjLvX1TQ9K35Yde6lVls7HVVYNNtDJAa+1MWcDGnXLad5IjpWzjqgeAss0Jh/+U
vkDpzQ3FVA/CEAoEP+Wdhi8+s/NMzzZcNdjXQ9kXnUYlNrQq30e4celYAwyMwsW5
OBfJW+zpAgMBAAECggEACWVtYl8iKrAv+DRSHv5dH/WQ3qtc8mgDioxxSDf5l1TX
UGdNQ15WkUPHoO3lqWzCQPNMJe54ZOi4T4D2zn2UG7oSA+cGKa34VdVMw56qzMUh
WY8R2CBi1QTtcZc1DrcGJ79CFgU5cujRDWdIVdtdq/yjDjgk6gdv5V3GmKZ6eA/I
3U6ZAi26Tc53Nv/23CRyMp7kmeuqoUGUiMDCWOGnhVUTvAWeJygiaCDr97uRxydx
27LCijb/j1jx84Fn84RQYpII/YnqNJOrcnmmTPte1psS3eoTbXu1S6thgfJn5kgS
JKg+Rc6fyHHMl8uv4wdk2yP2csVSt3Ea53/sgDeCAQKBgQC7ssJvUElM0VtX3U/J
JuP3ypTj5raB/bRfP3659cElycv5hTpcUXGTFHY5WK1TGcwIUrvxk8Pbr4PPDYEd
GA29obWcnj/yxaQEB8ALr4V1bCwTgvvkz/WwllRilV5R60KxauASFSLGLzyAg4za
piofZe/V0xBpChg+2+tM3DG/6QKBgQC69mh/o8lU7eSOh45TZ5l1lHOwEQhML9Mm
VNnGshVzRrPH3kPXOSoVt5WYefKvGaMuJs9pjPz/TH8o00+HyL7wpIEO3EyoSb0l
A8qMNftMulL4KIPc+EZYOX1h9oroY/VSn9mesY1F2agL4HnI84uxFta5tW2lXJSY
d6xjbhGlAQKBgGIHdq0UTXQlU4OMPoNcIGvmDeUJNgCmNHIag2E0DPBjeHiVMGnO
z3Z1lVlWCw//xSQDscz107EE9d5Ju0kqquKDdoqZy+RLfOHt+ksgHJ+7Izn9ivlx
82uK1/+061H1gRuQKf7HsyW2ve6Qxhvb9Nv5LU2LsmJpt0f9K+M0jXchAoGAfU4w
83s+sOFQUgagaV4CCEAa2WJRAV78UbCE1Rr+LWixjb92EIWLo0qLxMnW0WyJZaE1
WjTYS/NlNmOJ5iOxdW+L3/3ektv5HRnRYu+7Ic2vVgsxdaQg4XiGhGXM67wy69Ge
9TFi0fHzIyKr/PbeJS59c7IZbr8CCL4MmAdpZAECgYByFMGdwkoPvkZZEb7Q+Hzi
Wf307TpBJKZZwyYR7bheMV1L4XwV8uAv9E65mX4GRYlPSPHOmcsy3xbYAgiqz4r9
XKs0wZAoksZEVijEmuxe6VkC9d7Y9JpQlrUEdRachlm1MhJduLAo7I5guXKTxR7n
PgZoTQaVJ89waIoA0FwLaw==
-----END PRIVATE KEY-----');

// SMS ayarları
define('SMS_API_URL', '');
define('SMS_API_KEY', '');
define('SMS_SENDER_NAME', 'KURYE');

// Google Maps API
define('GOOGLE_MAPS_API_KEY', 'AIzaSyC-L4E5--L2M9dDvyLmcP-t9G2r84Y8GDY'); // Google Maps API key

// Konum takip ayarları
define('LOCATION_UPDATE_INTERVAL', 30); // Saniye
define('LOCATION_ACCURACY_THRESHOLD', 100); // Metre

// Sipariş ayarları
define('DEFAULT_DELIVERY_FEE', 5.00);
define('DEFAULT_COMMISSION_RATE', 15.00); // Yüzde
define('ORDER_TIMEOUT', 30); // Dakika - Sipariş otomatik iptal süresi

// Cache ayarları
define('CACHE_ENABLED', false);
define('CACHE_DURATION', 3600); // Saniye

// Autoloader
spl_autoload_register(function ($class) {
    $file = INCLUDES_PATH . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Yardımcı fonksiyonları yükle
require_once INCLUDES_PATH . '/functions.php';

// Veritabanı bağlantısını yükle
require_once CONFIG_PATH . '/database.php';

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Kullanıcı oturum kontrolü için yardımcı fonksiyonlar
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function requireLogin($redirect = 'login.php') {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function requireUserType($type, $redirect = 'index.php') {
    requireLogin();
    if (getUserType() !== $type) {
        header('Location: ' . $redirect);
        exit;
    }
}

// CSRF token doğrulama
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Log fonksiyonu
function writeLog($message, $level = 'INFO', $file = 'system.log') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents(LOGS_PATH . '/' . $file, $log_message, FILE_APPEND | LOCK_EX);
}

// API Response helper
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hata yakalama
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        writeLog("Error: {$message} in {$file}:{$line}", 'ERROR');
        if (APP_DEBUG) {
            echo "<div style='background: #ff6b6b; color: white; padding: 10px; margin: 5px; border-radius: 5px;'>";
            echo "<strong>Error:</strong> {$message}<br>";
            echo "<strong>File:</strong> {$file}:{$line}";
            echo "</div>";
        }
    }
});

// Exception yakalama
set_exception_handler(function($exception) {
    writeLog("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine(), 'ERROR');
    if (APP_DEBUG) {
        echo "<div style='background: #ff6b6b; color: white; padding: 10px; margin: 5px; border-radius: 5px;'>";
        echo "<strong>Exception:</strong> " . $exception->getMessage() . "<br>";
        echo "<strong>File:</strong> " . $exception->getFile() . ":" . $exception->getLine();
        echo "</div>";
    } else {
        echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
    }
});

// Sistem başlatıldığını logla
writeLog("System initialized - " . $_SERVER['REQUEST_URI'] ?? 'CLI');

// Kurye durumu ayarları
define('MAX_ORDERS_PER_COURIER', 5); // Kurye başına maksimum aktif sipariş sayısı
define('BUSY_THRESHOLD', 3); // Kurye meşgul olma eşiği (ama sipariş alabilir)
define('AUTO_AVAILABLE_THRESHOLD', 2); // Otomatik müsait olma eşiği
?>
