<?php
/**
 * GetirYemek API - Kurye Bildirimi Webhook
 * GetirYemek'ten gelen kurye bildirimlerini işler
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
    if (empty($input['orderId']) || empty($input['restaurantId'])) {
        throw new Exception('Gerekli alanlar eksik');
    }
    
    $db = getDB();
    
    // GetirYemek sipariş ID'sini al
    $getir_order_id = $input['orderId'];
    $restaurant_id = $input['restaurantId'];
    $calculation_date = $input['calculationDate'] ?? date('Y-m-d H:i:s');
    $pickup = $input['pickup'] ?? [];
    $pickup_min = $pickup['min'] ?? '';
    $pickup_max = $pickup['max'] ?? '';
    
    // Siparişi bul
    $stmt = $db->query("SELECT * FROM siparisler WHERE getir_order_id = ?", [$getir_order_id]);
    $siparis = $stmt->fetch();
    
    if (!$siparis) {
        throw new Exception("Sipariş bulunamadı: $getir_order_id");
    }
    
    // Kurye bildirimini kaydet
    $db->query("
        INSERT INTO getir_courier_notifications (
            siparis_id, getir_order_id, restaurant_id, calculation_date,
            pickup_min, pickup_max, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ", [
        $siparis['id'], $getir_order_id, $restaurant_id, $calculation_date,
        $pickup_min, $pickup_max
    ]);
    
    // Siparişi güncelle
    $db->query("
        UPDATE siparisler 
        SET courier_pickup_min = ?,
            courier_pickup_max = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [$pickup_min, $pickup_max, $siparis['id']]);
    
    // Restoran sahibine bildirim gönder (eğer varsa)
    $stmt = $db->query("
        SELECT u.device_token, m.mekan_name
        FROM users u
        JOIN mekanlar m ON u.id = m.user_id
        WHERE m.id = ? AND u.device_token IS NOT NULL
    ", [$siparis['mekan_id']]);
    
    $mekan_info = $stmt->fetch();
    
    if ($mekan_info && $mekan_info['device_token']) {
        $title = "Kurye Bildirimi";
        $message = "{$siparis['order_number']} numaralı sipariş için kurye {$pickup_min} - {$pickup_max} arası gelecek.";
        
        $notification_data = [
            'type' => 'courier_notification',
            'order_id' => (string)$siparis['id'],
            'order_number' => $siparis['order_number'],
            'pickup_min' => $pickup_min,
            'pickup_max' => $pickup_max
        ];
        
        sendPushNotification(
            $mekan_info['device_token'],
            $title,
            $message,
            $notification_data
        );
    }
    
    // Log kaydet
    writeLog("GetirYemek kurye bildirimi: {$siparis['order_number']} - Kurye {$pickup_min} - {$pickup_max} arası gelecek", 'INFO', 'getir.log');
    
    // Başarılı yanıt
    jsonResponse([
        'success' => true,
        'message' => 'Kurye bildirimi başarıyla alındı',
        'data' => [
            'order_id' => $siparis['id'],
            'order_number' => $siparis['order_number'],
            'getir_order_id' => $getir_order_id,
            'pickup_min' => $pickup_min,
            'pickup_max' => $pickup_max
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("GetirYemek kurye bildirimi hatası: " . $e->getMessage(), 'ERROR', 'getir.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Kurye bildirimi işlenirken hata oluştu: ' . $e->getMessage()
        ]
    ], 500);
}
?>

