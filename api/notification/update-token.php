<?php
/**
 * Kurye Full System - FCM Token Güncelleme API
 * Firebase Cloud Messaging token'ını günceller
 */

require_once '../../config/config.php';
require_once '../includes/auth.php';

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
    // JWT token kontrolü
    $user = authenticateJWT();
    
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON formatı');
    }
    
    // Validation
    if (empty($input['device_token'])) {
        throw new Exception('Device token gereklidir');
    }
    
    $device_token = clean($input['device_token']);
    $device_type = clean($input['device_type'] ?? 'android');
    $app_version = clean($input['app_version'] ?? '1.0.0');
    
    // Token format kontrolü (FCM token genellikle 150+ karakter)
    if (strlen($device_token) < 10) {
        throw new Exception('Geçersiz device token formatı');
    }
    
    $db = getDB();
    
    // Kullanıcının device token'ını güncelle
    $db->query(
        "UPDATE users 
         SET device_token = ?, updated_at = NOW() 
         WHERE id = ?", 
        [$device_token, $user['user_id']]
    );
    
    // Başarılı güncellemeyi logla
    writeLog("Device token updated for user: {$user['username']} (Type: {$device_type})", 'INFO', 'notifications.log');
    
    // Response
    jsonResponse([
        'success' => true,
        'message' => 'Device token başarıyla güncellendi',
        'data' => [
            'device_type' => $device_type,
            'app_version' => $app_version,
            'updated_at' => date('c')
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("Device token update error: " . $e->getMessage(), 'ERROR', 'notifications.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => $e->getMessage()
        ]
    ], 500);
}
?>
