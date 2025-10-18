<?php
/**
 * Kurye Full System - Mobile Kurye Sipariş Kabul API
 * Kurye siparişi kabul etme endpoint'i
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
    $current_location = $input['current_location'] ?? [];
    
    if (!$order_id) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Sipariş ID gereklidir'
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
        WHERE s.id = ? AND s.status = 'pending' AND s.kurye_id IS NULL
        FOR UPDATE
    ", [$order_id])->fetch();
    
    if (!$order) {
        $db->rollback();
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'ORDER_NOT_AVAILABLE',
                'message' => 'Sipariş mevcut değil veya başka kurye tarafından alındı'
            ]
        ], 409);
    }
    
    // Kurye müsaitlik kontrolü
    $kurye_status = $db->query("
        SELECT is_online, is_available 
        FROM kuryeler 
        WHERE id = ?
    ", [$kurye_id])->fetch();
    
    if (!$kurye_status['is_online']) {
        $db->rollback();
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'COURIER_NOT_ONLINE',
                'message' => 'Kurye çevrimdışı'
            ]
        ], 409);
    }
    
    // Kurye başına maksimum sipariş sayısı kontrolü
    $max_orders_per_courier = (int)getSetting('max_orders_per_courier', 5); // Varsayılan 5 sipariş
    
    $active_orders_count = $db->query("
        SELECT COUNT(*) as count 
        FROM siparisler 
        WHERE kurye_id = ? 
        AND status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
    ", [$kurye_id])->fetchColumn();
    
    if ($active_orders_count >= $max_orders_per_courier) {
        $db->rollback();
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'MAX_ORDERS_REACHED',
                'message' => "Maksimum sipariş sayısına ulaştınız ($max_orders_per_courier sipariş). Önce mevcut siparişlerinizi tamamlayın."
            ]
        ], 409);
    }
    
    // Siparişi kurye'ye ata
    $db->query("
        UPDATE siparisler 
        SET kurye_id = ?, status = 'accepted', accepted_at = NOW()
        WHERE id = ?
    ", [$kurye_id, $order_id]);
    
    // Kurye durumunu güncelle (meşgul eşiği kontrolü)
    $busy_threshold = (int)getSetting('busy_threshold', 3);
    
    if ($active_orders_count + 1 >= $busy_threshold) {
        // Meşgul olarak işaretle ama sipariş almaya devam etsin
        $db->query("
            UPDATE kuryeler 
            SET is_available = 0
            WHERE id = ?
        ", [$kurye_id]);
    }
    
    // Konum bilgisini güncelle (eğer gönderilmişse)
    if (!empty($current_location['latitude']) && !empty($current_location['longitude'])) {
        $db->query("
            UPDATE kuryeler 
            SET current_latitude = ?, current_longitude = ?, last_location_update = NOW()
            WHERE id = ?
        ", [
            $current_location['latitude'],
            $current_location['longitude'],
            $kurye_id
        ]);
        
        // Konum geçmişine kaydet
        $db->query("
            INSERT INTO kurye_konum_gecmisi (kurye_id, latitude, longitude, accuracy, siparis_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ", [
            $kurye_id,
            $current_location['latitude'],
            $current_location['longitude'],
            $current_location['accuracy'] ?? 10.0,
            $order_id
        ]);
    }
    
    // Sipariş geçmişine kaydet (eğer tablo varsa)
    try {
        $db->query("
            INSERT INTO siparis_durum_gecmisi (siparis_id, old_status, new_status, changed_by, notes) 
            VALUES (?, 'pending', 'accepted', ?, 'Mobil uygulamadan kabul edildi')
        ", [$order_id, $user['user_id']]);
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
    
    // Komisyon hesapla
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    $gross_fee = (float)$updated_order['delivery_fee'];
    $commission = ($gross_fee * $commission_rate) / 100;
    $net_earning = $gross_fee - $commission;
    
    // Log kaydet
    writeLog("Order {$order['order_number']} accepted by courier {$user['username']} via mobile app", 'INFO', 'mobile_api.log');
    
    // Push notification gönder (mekan'a)
    // TODO: Firebase push notification implementation
    
    // Response
    jsonResponse([
        'success' => true,
        'message' => 'Sipariş başarıyla kabul edildi',
        'order' => [
            'id' => (int)$updated_order['id'],
            'order_number' => $updated_order['order_number'],
            'status' => $updated_order['status'],
            'customer' => [
                'name' => $updated_order['customer_name'],
                'phone' => $updated_order['customer_phone'],
                'address' => $updated_order['delivery_address']
            ],
            'restaurant' => [
                'name' => $updated_order['mekan_name'],
                'address' => $updated_order['mekan_address'],
                'phone' => $updated_order['mekan_phone']
            ],
            'total_amount' => (float)$updated_order['total_amount'],
            'delivery_fee' => $gross_fee,
            'net_earning' => round($net_earning, 2),
            'payment_method' => $updated_order['payment_method'],
            'preparation_time' => (int)$updated_order['preparation_time'],
            'estimated_pickup_time' => date('c', strtotime($updated_order['created_at']) + ($updated_order['preparation_time'] * 60)),
            'priority' => $updated_order['priority'],
            'notes' => $updated_order['notes'],
            'created_at' => $updated_order['created_at'],
            'accepted_at' => $updated_order['accepted_at']
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    writeLog("Mobile accept order error: " . $e->getMessage(), 'ERROR', 'mobile_api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Sipariş kabul edilirken bir hata oluştu'
        ]
    ], 500);
}
?>
