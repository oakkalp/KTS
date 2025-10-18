<?php
/**
 * Kurye Full System - Mobile Kurye Sipariş Durumu Güncelleme API
 * Kurye sipariş durumunu güncelleme endpoint'i
 */

require_once '../../../config/config.php';
require_once '../../includes/auth.php';

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
    
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON formatı');
    }
    
    $order_id = (int)($input['order_id'] ?? 0);
    $status = clean($input['status'] ?? '');
    $location = $input['location'] ?? [];
    $notes = clean($input['notes'] ?? '');
    $photo = clean($input['photo'] ?? '');
    
    if (!$order_id || !$status) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Sipariş ID ve durum gereklidir'
            ]
        ], 400);
    }
    
    // Geçerli durumlar
    $valid_statuses = ['accepted', 'preparing', 'ready', 'picked_up', 'delivering', 'delivered', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_STATUS',
                'message' => 'Geçersiz sipariş durumu'
            ]
        ], 400);
    }
    
    $db = getDB();
    $kurye_id = $user['type_id'];
    
    if (!$kurye_id) {
        throw new Exception('Kurye bilgisi bulunamadı');
    }
    
    // Transaction başlat
    $db->beginTransaction();
    
    // Siparişi kontrol et
    $order = $db->query("
        SELECT s.*, m.mekan_name 
        FROM siparisler s
        JOIN mekanlar m ON s.mekan_id = m.id
        WHERE s.id = ? AND s.kurye_id = ?
        FOR UPDATE
    ", [$order_id, $kurye_id])->fetch();
    
    if (!$order) {
        $db->rollback();
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'ORDER_NOT_FOUND',
                'message' => 'Sipariş bulunamadı veya size ait değil'
            ]
        ], 404);
    }
    
    // Durum geçişlerini kontrol et
    $current_status = $order['status'];
    $valid_transitions = [
        'accepted' => ['preparing', 'ready', 'picked_up', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['picked_up', 'cancelled'],
        'picked_up' => ['delivering', 'delivered', 'cancelled'],
        'delivering' => ['delivered', 'cancelled'],
    ];
    
    if (isset($valid_transitions[$current_status]) && !in_array($status, $valid_transitions[$current_status])) {
        $db->rollback();
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_TRANSITION',
                'message' => "Durum '$current_status' den '$status' e geçiş geçersiz"
            ]
        ], 400);
    }
    
    // Sipariş durumunu güncelle
    $update_fields = ['status = ?'];
    $update_values = [$status];
    
    // Zaman damgalarını ekle
    switch ($status) {
        case 'picked_up':
            $update_fields[] = 'picked_up_at = NOW()';
            break;
        case 'delivered':
            $update_fields[] = 'delivered_at = NOW()';
            break;
        case 'cancelled':
            $update_fields[] = 'cancelled_at = NOW()';
            break;
    }
    
    // Notları ekle
    if ($notes) {
        $update_fields[] = 'notes = ?';
        $update_values[] = $notes;
    }
    
    $update_values[] = $order_id;
    
    $sql = "UPDATE siparisler SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = ?";
    $db->query($sql, $update_values);
    
    // Konum bilgisini güncelle (eğer gönderilmişse)
    if (!empty($location['latitude']) && !empty($location['longitude'])) {
        $db->query("
            UPDATE kuryeler 
            SET current_latitude = ?, current_longitude = ?, last_location_update = NOW()
            WHERE id = ?
        ", [
            $location['latitude'],
            $location['longitude'],
            $kurye_id
        ]);
        
        // Konum geçmişine kaydet
        $db->query("
            INSERT INTO kurye_konum_gecmisi (kurye_id, latitude, longitude, accuracy, siparis_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ", [
            $kurye_id,
            $location['latitude'],
            $location['longitude'],
            $location['accuracy'] ?? 10.0,
            $order_id
        ]);
    }
    
    // Kurye durumunu otomatik güncelle (aktif sipariş sayısına göre)
    $active_orders_count = $db->query("
        SELECT COUNT(*) as count 
        FROM siparisler 
        WHERE kurye_id = ? 
        AND status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
    ", [$kurye_id])->fetchColumn();
    
    $auto_available_threshold = (int)getSetting('auto_available_threshold', 2);
    
    if ($active_orders_count <= $auto_available_threshold) {
        // Aktif sipariş sayısı eşiğin altındaysa otomatik müsait yap
        $db->query("
            UPDATE kuryeler 
            SET is_available = 1
            WHERE id = ?
        ", [$kurye_id]);
    }
    
    // Sipariş geçmişine kaydet (eğer tablo varsa)
    try {
        $db->query("
            INSERT INTO siparis_durum_gecmisi (siparis_id, old_status, new_status, changed_by, notes) 
            VALUES (?, ?, ?, ?, ?)
        ", [$order_id, $current_status, $status, $user['user_id'], $notes]);
    } catch (Exception $e) {
        // Sipariş geçmişi tablosu yoksa log'a yaz ve devam et
        writeLog("Sipariş geçmişi kaydedilemedi: " . $e->getMessage(), 'WARNING', 'mobile_api.log');
    }
    
    // Transaction'ı tamamla
    $db->commit();
    
    // Güncellenmiş sipariş bilgisini al
    $updated_order = $db->query("
        SELECT s.*, m.mekan_name, m.address as mekan_address, m.phone as mekan_phone
        FROM siparisler s
        JOIN mekanlar m ON s.mekan_id = m.id
        WHERE s.id = ?
    ", [$order_id])->fetch();
    
    // Log kaydet
    writeLog("Order {$order['order_number']} status updated from '$current_status' to '$status' by courier {$user['username']}", 'INFO', 'mobile_api.log');
    
    // Response
    jsonResponse([
        'success' => true,
        'message' => 'Sipariş durumu başarıyla güncellendi',
        'order' => [
            'id' => (int)$updated_order['id'],
            'order_number' => $updated_order['order_number'],
            'status' => $updated_order['status'],
            'picked_up_at' => $updated_order['picked_up_at'],
            'delivered_at' => $updated_order['delivered_at'],
            'notes' => $updated_order['notes'],
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    writeLog("Mobile update order status error: " . $e->getMessage(), 'ERROR', 'mobile_api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Sipariş durumu güncellenirken bir hata oluştu'
        ]
    ], 500);
}
?>
