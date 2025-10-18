<?php
/**
 * Kurye Full System - Test Notification API
 * Test bildirimi gönderme endpoint'i
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
    
    if ($user['user_type'] !== 'kurye') {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Sadece kuryeler erişebilir'
            ]
        ], 403);
    }
    
    $db = getDB();
    
    // Kurye'nin device token'ını al
    $stmt = $db->query("SELECT device_token FROM users WHERE id = ?", [$user['user_id']]);
    $user_data = $stmt->fetch();
    
    if (!$user_data || !$user_data['device_token']) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'NO_DEVICE_TOKEN',
                'message' => 'Device token bulunamadı'
            ]
        ], 400);
    }
    
    // Test bildirimi gönder
    $success = sendPushNotification(
        [$user_data['device_token']],
        'Test Bildirimi 🧪',
        'Bu bir test bildirimidir. Notification sistemi çalışıyor!',
        [
            'type' => 'test',
            'timestamp' => date('c'),
            'user_id' => $user['user_id']
        ]
    );
    
    // Log kaydet
    writeLog("Test notification sent to user: {$user['username']}", 'INFO', 'notifications.log');
    
    // Response
    jsonResponse([
        'success' => true,
        'message' => 'Test bildirimi gönderildi',
        'data' => [
            'notification_sent' => $success,
            'sent_at' => date('c'),
            'device_token' => substr($user_data['device_token'], 0, 20) . '...'
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("Test notification error: " . $e->getMessage(), 'ERROR', 'notifications.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => $e->getMessage()
        ]
    ], 500);
}
?>
