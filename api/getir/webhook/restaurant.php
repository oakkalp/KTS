<?php
/**
 * GetirYemek API - Restoran Bildirimi Webhook
 * GetirYemek'ten gelen restoran durum değişikliklerini işler
 */

require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-api-key');
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
    // API Key kontrolü
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($api_key) || $api_key !== GETIR_API_KEY) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Geçersiz API anahtarı'
            ]
        ], 401);
    }
    
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON formatı');
    }
    
    // Gerekli alanları kontrol et
    if (empty($input['restaurantId']) || empty($input['status'])) {
        throw new Exception('Gerekli alanlar eksik');
    }
    
    $db = getDB();
    
    // GetirYemek restoran ID'sini al
    $getir_restaurant_id = $input['restaurantId'];
    $restaurant_name = $input['restaurantName'] ?? '';
    $status = $input['status'];
    $status_change_date = $input['statusChangeDate'] ?? date('Y-m-d H:i:s');
    
    // Restoranı bul
    $stmt = $db->query("SELECT * FROM mekanlar WHERE getir_restaurant_id = ?", [$getir_restaurant_id]);
    $mekan = $stmt->fetch();
    
    if (!$mekan) {
        throw new Exception("Restoran bulunamadı: $getir_restaurant_id");
    }
    
    // Durum değişikliğini kaydet
    $db->query("
        INSERT INTO getir_restaurant_status_changes (
            mekan_id, getir_restaurant_id, restaurant_name, status,
            status_change_date, created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ", [
        $mekan['id'], $getir_restaurant_id, $restaurant_name, $status,
        $status_change_date
    ]);
    
    // Restoran durumunu güncelle
    $is_active = ($status == 'ACTIVE' || $status == 'OPEN') ? 1 : 0;
    
    $db->query("
        UPDATE mekanlar 
        SET is_active = ?,
            getir_status = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [$is_active, $status, $mekan['id']]);
    
    // Restoran sahibine bildirim gönder
    $stmt = $db->query("
        SELECT u.device_token, u.username
        FROM users u
        WHERE u.id = ? AND u.device_token IS NOT NULL
    ", [$mekan['user_id']]);
    
    $user_info = $stmt->fetch();
    
    if ($user_info && $user_info['device_token']) {
        $title = "Restoran Durum Değişikliği";
        $status_text = ($is_active) ? 'Aktif' : 'Pasif';
        $message = "{$mekan['mekan_name']} restoranınız GetirYemek'te {$status_text} duruma geçti.";
        
        $notification_data = [
            'type' => 'restaurant_status_change',
            'restaurant_id' => (string)$mekan['id'],
            'restaurant_name' => $mekan['mekan_name'],
            'status' => $status,
            'is_active' => $is_active
        ];
        
        sendPushNotification(
            $user_info['device_token'],
            $title,
            $message,
            $notification_data
        );
    }
    
    // Log kaydet
    writeLog("GetirYemek restoran durum değişikliği: {$mekan['mekan_name']} - Durum: $status", 'INFO', 'getir.log');
    
    // Başarılı yanıt
    jsonResponse([
        'success' => true,
        'message' => 'Restoran durum değişikliği başarıyla alındı',
        'data' => [
            'restaurant_id' => $mekan['id'],
            'restaurant_name' => $mekan['mekan_name'],
            'getir_restaurant_id' => $getir_restaurant_id,
            'status' => $status,
            'is_active' => $is_active
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("GetirYemek restoran durum değişikliği hatası: " . $e->getMessage(), 'ERROR', 'getir.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Restoran durum değişikliği işlenirken hata oluştu: ' . $e->getMessage()
        ]
    ], 500);
}
?>

