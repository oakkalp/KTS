<?php
/**
 * Kurye Full System - Mobile Authentication
 * Mobil uygulama için JWT tabanlı giriş sistemi
 */

require_once '../../../config/config.php';

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
    $device_info = $input['device_info'] ?? [];
    
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
    $rate_limit_key = 'mobile_login_' . md5($client_ip);
    
    // Basit rate limiting
    $attempts_file = LOGS_PATH . '/' . $rate_limit_key . '.tmp';
    $current_time = time();
    $max_attempts = 15; // Mobil için biraz daha yüksek
    $time_window = 300; // 5 dakika
    
    $attempts = [];
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true) ?: [];
        $attempts = array_filter($attempts, function($time) use ($current_time, $time_window) {
            return ($current_time - $time) < $time_window;
        });
    }
    
    if (count($attempts) >= $max_attempts) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Çok fazla login denemesi. 5 dakika sonra tekrar deneyin.'
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
                END as additional_info,
                CASE 
                    WHEN u.user_type = 'kurye' THEN k.vehicle_type
                    ELSE NULL 
                END as vehicle_type
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
        writeLog("Failed mobile login attempt: {$username} from {$client_ip}", 'WARNING', 'mobile_api.log');
        
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Kullanıcı adı veya şifre hatalı'
            ]
        ], 401);
    }
    
    // JWT token oluştur (30 gün geçerli)
    $token_expiry = time() + (30 * 24 * 60 * 60); // 30 gün
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user['id'],
        'username' => $user['username'],
        'user_type' => $user['user_type'],
        'type_id' => $user['type_id'],
        'app_type' => 'mobile',
        'device_id' => $device_info['device_id'] ?? null,
        'iat' => time(),
        'exp' => $token_expiry
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    $jwt = $base64Header . "." . $base64Payload . "." . $base64Signature;
    
    // Refresh token oluştur
    $refresh_token = 'rt_' . bin2hex(random_bytes(32));
    
    // Device bilgilerini kaydet/güncelle
    if (!empty($device_info)) {
        $device_data = [
            'device_id' => $device_info['device_id'] ?? null,
            'device_name' => $device_info['device_name'] ?? null,
            'platform' => $device_info['platform'] ?? null,
            'app_version' => $device_info['app_version'] ?? null,
            'fcm_token' => $device_info['fcm_token'] ?? null
        ];
        
        // Device tablosunu kontrol et ve oluştur
        $db->query("
            INSERT INTO user_devices (user_id, device_id, device_name, platform, app_version, fcm_token, refresh_token, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                device_name = VALUES(device_name),
                platform = VALUES(platform),
                app_version = VALUES(app_version),
                fcm_token = VALUES(fcm_token),
                refresh_token = VALUES(refresh_token),
                updated_at = NOW()
        ", [
            $user['id'],
            $device_data['device_id'],
            $device_data['device_name'],
            $device_data['platform'],
            $device_data['app_version'],
            $device_data['fcm_token'],
            $refresh_token
        ]);
    }
    
    // Son giriş zamanını güncelle
    $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    
    // Başarılı girişi logla
    writeLog("Successful mobile login: {$username} ({$user['user_type']}) from {$client_ip}", 'INFO', 'mobile_api.log');
    
    // Rate limiting dosyasını temizle
    if (file_exists($attempts_file)) {
        unlink($attempts_file);
    }
    
    // User type'a göre özel bilgiler
    $user_data = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'user_type' => $user['user_type'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'last_login' => $user['last_login']
    ];
    
    // Kurye için ek bilgiler
    if ($user['user_type'] === 'kurye') {
        $user_data['kurye_id'] = $user['type_id'] ? (int)$user['type_id'] : null;
        $user_data['license_plate'] = $user['additional_info'];
        $user_data['vehicle_type'] = $user['vehicle_type'];
        
        // Kurye istatistikleri
        $stats = $db->query("
            SELECT 
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as total_deliveries,
                SUM(CASE WHEN status = 'delivered' THEN delivery_fee ELSE 0 END) as total_earnings
            FROM siparisler 
            WHERE kurye_id = ?
        ", [$user['type_id']])->fetch();
        
        // Kurye rating'ini ayrıca al
        $kurye_rating = $db->query("
            SELECT rating FROM kuryeler WHERE id = ?
        ", [$user['type_id']])->fetch();
        
        $user_data['stats'] = [
            'total_deliveries' => (int)($stats['total_deliveries'] ?? 0),
            'rating' => $kurye_rating['rating'] ? round($kurye_rating['rating'], 1) : null,
            'total_earnings' => (float)($stats['total_earnings'] ?? 0)
        ];
    }
    
    // Mekan için ek bilgiler
    if ($user['user_type'] === 'mekan') {
        $user_data['mekan_id'] = $user['type_id'] ? (int)$user['type_id'] : null;
        $user_data['mekan_name'] = $user['additional_info'];
        
        // Mekan bilgileri
        $mekan = $db->query("
            SELECT address, cuisine_type, is_open, opening_hours 
            FROM mekanlar 
            WHERE id = ?
        ", [$user['type_id']])->fetch();
        
        if ($mekan) {
            $user_data['address'] = $mekan['address'];
            $user_data['cuisine_type'] = $mekan['cuisine_type'];
            $user_data['is_open'] = (bool)$mekan['is_open'];
            $user_data['opening_hours'] = $mekan['opening_hours'];
        }
    }
    
    // Response
    jsonResponse([
        'success' => true,
        'token' => $jwt,
        'refresh_token' => $refresh_token,
        'user' => $user_data,
        'expires_at' => date('c', $token_expiry),
        'server_time' => date('c')
    ]);
    
} catch (Exception $e) {
    writeLog("Mobile API login error: " . $e->getMessage(), 'ERROR', 'mobile_api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Bir hata oluştu. Lütfen tekrar deneyin.'
        ]
    ], 500);
}
?>
