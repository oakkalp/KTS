<?php
/**
 * Kurye Full System - Sipariş Durumu Güncelleme API
 * Kurye'nin sipariş durumunu güncellemesi için endpoint
 */

require_once '../../config/config.php';
require_once '../includes/auth.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// PUT ve POST metodunu kabul et
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Sadece PUT/POST metodu desteklenir'
        ]
    ], 405);
}

handleAPIRequest('/kurye/order-status', 'kurye', function($user, $data) {
    // Validation
    if (!isset($data['order_id']) || !isset($data['status'])) {
        throw new Exception('order_id ve status parametreleri gereklidir');
    }
    
    $order_id = (int)$data['order_id'];
    $new_status = trim($data['status']);
    $notes = trim($data['notes'] ?? '');
    $latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
    $longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
    
    // Geçerli durum kontrolü
    $valid_statuses = ['accepted', 'preparing', 'ready', 'picked_up', 'delivering', 'delivered', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception('Geçersiz sipariş durumu: ' . $new_status);
    }
    
    $db = getDB();
    
    // Kurye bilgilerini al
    $stmt = $db->query("SELECT id FROM kuryeler WHERE user_id = ?", [$user['user_id']]);
    $kurye = $stmt->fetch();
    
    if (!$kurye) {
        throw new Exception('Kurye bilgileri bulunamadı');
    }
    
    // Sipariş kontrolü
    $stmt = $db->query("
        SELECT s.*, m.id as mekan_id, m.user_id as mekan_user_id 
        FROM siparisler s 
        JOIN mekanlar m ON s.mekan_id = m.id 
        WHERE s.id = ? AND s.kurye_id = ?
    ", [$order_id, $kurye['id']]);
    
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Bu siparişe erişim yetkiniz yok veya sipariş bulunamadı');
    }
    
    // Durum değişikliği kontrolü
    $valid_transitions = [
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['picked_up', 'cancelled'],
        'picked_up' => ['delivering', 'cancelled'],
        'delivering' => ['delivered', 'cancelled'],
        'delivered' => [], // Teslim edilen sipariş değiştirilemez
        'cancelled' => []  // İptal edilen sipariş değiştirilemez
    ];
    
    if (!in_array($new_status, $valid_transitions[$order['status']] ?? [])) {
        throw new Exception("Sipariş durumu '{$order['status']}' iken '{$new_status}' durumuna geçilemez");
    }
    
    $db->beginTransaction();
    
    try {
        // Sipariş durumunu güncelle
        $update_fields = ['status = ?'];
        $update_params = [$new_status];
        
        // Durum bazlı timestamp ekle
        switch ($new_status) {
            case 'preparing':
                $update_fields[] = 'preparing_at = NOW()';
                break;
            case 'ready':
                $update_fields[] = 'ready_at = NOW()';
                break;
            case 'picked_up':
                $update_fields[] = 'picked_up_at = NOW()';
                break;
            case 'delivering':
                $update_fields[] = 'delivering_at = NOW()';
                break;
            case 'delivered':
                $update_fields[] = 'delivered_at = NOW()';
                break;
            case 'cancelled':
                $update_fields[] = 'cancelled_at = NOW()';
                break;
        }
        
        // Konum bilgisi varsa ekle
        if ($latitude && $longitude) {
            $update_fields[] = 'last_latitude = ?';
            $update_fields[] = 'last_longitude = ?';
            $update_params[] = $latitude;
            $update_params[] = $longitude;
        }
        
        // Notes varsa ekle
        if ($notes) {
            $update_fields[] = 'notes = ?';
            $update_params[] = $notes;
        }
        
        $update_params[] = $order_id;
        
        $sql = "UPDATE siparisler SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $db->query($sql, $update_params);
        
        // Sipariş delivered durumuna geldiğinde mekan bakiyesini artır
        if ($new_status === 'delivered') {
            $delivery_fee = (float)getSetting('delivery_fee', 5.00);
            
            // Mevcut bakiyeyi al
            $stmt = $db->query("
                SELECT bakiye 
                FROM bakiye 
                WHERE user_id = ? AND user_type = 'mekan'
            ", [$order['mekan_user_id']]);
            
            $bakiye_row = $stmt->fetch();
            
            if (!$bakiye_row) {
                // İlk kez bakiye kaydı oluştur
                $db->query("
                    INSERT INTO bakiye (user_id, user_type, bakiye) 
                    VALUES (?, 'mekan', ?)
                ", [$order['mekan_user_id'], $delivery_fee]);
            } else {
                // Mevcut bakiyeye ekle
                $new_balance = (float)$bakiye_row['bakiye'] + $delivery_fee;
                $db->query("
                    UPDATE bakiye 
                    SET bakiye = ?, son_guncelleme = NOW() 
                    WHERE user_id = ? AND user_type = 'mekan'
                ", [$new_balance, $order['mekan_user_id']]);
            }
            
            // Teslimat ücretini siparişe kaydet
            $db->query("
                UPDATE siparisler 
                SET delivery_fee = ? 
                WHERE id = ?
            ", [$delivery_fee, $order_id]);
        }
        
        // Kurye durumunu güncelle (cancelled/delivered durumunda müsaitlik artırılabilir)
        if (in_array($new_status, ['delivered', 'cancelled'])) {
            $max_orders = (int)getSetting('max_orders_per_courier', 5);
            
            $stmt = $db->query("
                SELECT COUNT(id) as active_orders 
                FROM siparisler 
                WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
            ", [$kurye['id']]);
            
            $active_orders = $stmt->fetch()['active_orders'] ?? 0;
            $is_available = ($active_orders < $max_orders) ? 1 : 0;
            
            $db->query("
                UPDATE kuryeler 
                SET is_available = ? 
                WHERE id = ?
            ", [$is_available, $kurye['id']]);
        }
        
        $db->commit();
        
        // Log kaydı
        writeLog("Order status updated: Order #{$order['order_number']} -> {$new_status} by {$user['username']}", 'INFO', 'api.log');
        
        // Güncellenmiş sipariş bilgilerini al
        $stmt = $db->query("
            SELECT id, order_number, status, updated_at 
            FROM siparisler 
            WHERE id = ?
        ", [$order_id]);
        
        $updated_order = $stmt->fetch();
        
        return [
            'success' => true,
            'message' => 'Sipariş durumu başarıyla güncellendi',
            'order' => [
                'id' => (int)$updated_order['id'],
                'order_number' => $updated_order['order_number'],
                'status' => $updated_order['status'],
                'updated_at' => $updated_order['updated_at']
            ]
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
});
?>
