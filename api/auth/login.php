<?php
/**
 * Kurye Full System - API Authentication
 * JWT tabanlı kullanıcı girişi
 */

require_once __DIR__ . '/../../config/config.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Sadece POST metodunu kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Sadece POST metodu desteklenir'
        ]
    ], 405);
}

try {
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON formatı');
    }
    
    $username = clean($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validation
    if (empty($username) || empty($password)) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Kullanıcı adı ve şifre gereklidir'
            ]
        ], 400);
    }
    
    // Rate limiting kontrolü
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_limit_key = 'login_attempts_' . md5($client_ip);
    
    // Basit rate limiting (production'da Redis kullanılmalı)
    $attempts_file = LOGS_PATH . '/' . $rate_limit_key . '.tmp';
    $current_time = time();
    $max_attempts = 10;
    $time_window = 60; // 1 dakika
    
    $attempts = [];
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true) ?: [];
        // Eski denemeleri temizle
        $attempts = array_filter($attempts, function($time) use ($current_time, $time_window) {
            return ($current_time - $time) < $time_window;
        });
    }
    
    if (count($attempts) >= $max_attempts) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Çok fazla login denemesi. 1 dakika sonra tekrar deneyin.'
            ]
        ], 429);
    }
    
    $db = getDB();
    
    // Kullanıcıyı bul
    $stmt = $db->query(
        "SELECT u.*, 
                CASE 
                    WHEN u.user_type = 'mekan' THEN m.id
                    WHEN u.user_type = 'kurye' THEN k.id
                    ELSE NULL 
                END as type_id,
                CASE 
                    WHEN u.user_type = 'mekan' THEN m.mekan_name
                    WHEN u.user_type = 'kurye' THEN k.license_plate
                    ELSE NULL 
                END as additional_info
         FROM users u 
         LEFT JOIN mekanlar m ON u.id = m.user_id 
         LEFT JOIN kuryeler k ON u.id = k.user_id 
         WHERE u.username = ? AND u.status = 'active'", 
        [$username]
    );
    
    $user = $stmt->fetch();
    
    // Login attempt'i kaydet
    $attempts[] = $current_time;
    file_put_contents($attempts_file, json_encode($attempts));
    
    if (!$user || !verifyPassword($password, $user['password'])) {
        writeLog("Failed API login attempt: {$username} from {$client_ip}", 'WARNING', 'api.log');
        
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Kullanıcı adı veya şifre hatalı'
            ]
        ], 401);
    }
    
    // JWT token oluştur
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user['id'],
        'username' => $user['username'],
        'user_type' => $user['user_type'],
        'type_id' => $user['type_id'],
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60) // 24 saat
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    $jwt = $base64Header . "." . $base64Payload . "." . $base64Signature;
    
    // Son giriş zamanını güncelle
    $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    
    // Device token'ı güncelle (eğer gönderilmişse)
    if (!empty($input['device_token'])) {
        $db->query("UPDATE users SET device_token = ? WHERE id = ?", [$input['device_token'], $user['id']]);
    }
    
    // Başarılı girişi logla
    writeLog("Successful API login: {$username} ({$user['user_type']}) from {$client_ip}", 'INFO', 'api.log');
    
    // Rate limiting dosyasını temizle (başarılı giriş)
    if (file_exists($attempts_file)) {
        unlink($attempts_file);
    }
    
    // Response
    jsonResponse([
        'success' => true,
        'token' => $jwt,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'user_type' => $user['user_type'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'type_id' => $user['type_id'] ? (int)$user['type_id'] : null,
            'additional_info' => $user['additional_info'],
            'last_login' => $user['last_login']
        ],
        'expires_at' => date('c', time() + (24 * 60 * 60))
    ]);
    
} catch (Exception $e) {
    writeLog("API login error: " . $e->getMessage(), 'ERROR', 'api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Bir hata oluştu. Lütfen tekrar deneyin.'
        ]
    ], 500);
}
?>
