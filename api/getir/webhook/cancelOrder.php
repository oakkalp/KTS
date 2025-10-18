<?php
/**
 * GetirYemek API - Sipariş İptal Webhook
 * GetirYemek'ten gelen sipariş iptallerini işler
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
    if (empty($input['id'])) {
        throw new Exception('Sipariş ID eksik');
    }
    
    $db = getDB();
    
    // GetirYemek sipariş ID'sini al
    $getir_order_id = $input['id'];
    $cancel_note = $input['cancelNote'] ?? '';
    $cancel_reason = $input['cancelReason']['messages']['tr'] ?? '';
    $cancel_date = $input['cancelDate'] ?? date('Y-m-d H:i:s');
    
    // Siparişi bul
    $stmt = $db->query("SELECT * FROM siparisler WHERE getir_order_id = ?", [$getir_order_id]);
    $siparis = $stmt->fetch();
    
    if (!$siparis) {
        throw new Exception("Sipariş bulunamadı: $getir_order_id");
    }
    
    // Siparişi iptal et
    $db->query("
        UPDATE siparisler 
        SET status = 'cancelled', 
            cancelled_at = NOW(),
            cancel_reason = ?,
            cancel_note = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [$cancel_reason, $cancel_note, $siparis['id']]);
    
    // Eğer kurye atanmışsa, kuryeyi müsait yap
    if ($siparis['kurye_id']) {
        $db->query("
            UPDATE kuryeler 
            SET is_available = 1, 
                updated_at = NOW()
            WHERE id = ?
        ", [$siparis['kurye_id']]);
        
        // Kuryeye bildirim gönder
        $stmt = $db->query("
            SELECT u.device_token, k.full_name
            FROM users u
            JOIN kuryeler k ON u.id = k.user_id
            WHERE k.id = ? AND u.device_token IS NOT NULL
        ", [$siparis['kurye_id']]);
        
        $kurye_info = $stmt->fetch();
        
        if ($kurye_info && $kurye_info['device_token']) {
            $title = "Sipariş İptal Edildi";
            $message = "{$siparis['order_number']} numaralı sipariş iptal edildi. Yeni siparişler alabilirsiniz.";
            
            $notification_data = [
                'type' => 'order_cancelled',
                'order_id' => (string)$siparis['id'],
                'order_number' => $siparis['order_number'],
                'cancel_reason' => $cancel_reason
            ];
            
            sendPushNotification(
                $kurye_info['device_token'],
                $title,
                $message,
                $notification_data
            );
        }
    }
    
    // Log kaydet
    writeLog("GetirYemek sipariş iptal edildi: {$siparis['order_number']} (Getir ID: $getir_order_id) - Sebep: $cancel_reason", 'INFO', 'getir.log');
    
    // Başarılı yanıt
    jsonResponse([
        'success' => true,
        'message' => 'Sipariş başarıyla iptal edildi',
        'data' => [
            'order_id' => $siparis['id'],
            'order_number' => $siparis['order_number'],
            'getir_order_id' => $getir_order_id,
            'cancel_reason' => $cancel_reason
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("GetirYemek sipariş iptal hatası: " . $e->getMessage(), 'ERROR', 'getir.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Sipariş iptal edilirken hata oluştu: ' . $e->getMessage()
        ]
    ], 500);
}
?>

