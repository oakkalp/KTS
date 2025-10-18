<?php
/**
 * Kurye Full System - Mobile Kurye Sipariş Durumu Güncelleme API
 * Sipariş durumunu güncelleme endpoint'i (picked_up, delivering, delivered)
 */

require_once '../../../config/config.php';
require_once '../../includes/auth.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Sadece PUT metodunu kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Sadece PUT metodu desteklenir'
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
    $new_status = $input['status'] ?? '';
    $location = $input['location'] ?? [];
    $notes = $input['notes'] ?? '';
    $photo = $input['photo'] ?? ''; // Base64 encoded image
    
    // Validation
    if (!$order_id || !$new_status) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Sipariş ID ve durum gereklidir'
            ]
        ], 400);
    }
    
    // Geçerli durumlar
    $valid_statuses = ['picked_up', 'delivering', 'delivered', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
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
    
    // Durum geçiş kontrolü
    $current_status = $order['status'];
    $valid_transitions = [
        'ready' => ['picked_up', 'cancelled'],
        'picked_up' => ['delivering', 'delivered', 'cancelled'],
        'delivering' => ['delivered', 'cancelled']
    ];
    
    if (!isset($valid_transitions[$current_status]) || 
        !in_array($new_status, $valid_transitions[$current_status])) {
        $db->rollback();
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_TRANSITION',
                'message' => "'{$current_status}' durumundan '{$new_status}' durumuna geçiş yapılamaz"
            ]
        ], 409);
    }
    
    // Durum güncelleme
    $update_fields = ['status = ?'];
    $update_params = [$new_status];
    
    switch ($new_status) {
        case 'picked_up':
            $update_fields[] = 'picked_up_at = NOW()';
            break;
        case 'delivering':
            if (!$order['picked_up_at']) {
                $update_fields[] = 'picked_up_at = NOW()';
            }
            break;
        case 'delivered':
            $update_fields[] = 'delivered_at = NOW()';
            if (!$order['picked_up_at']) {
                $update_fields[] = 'picked_up_at = NOW()';
            }
            break;
    }
    
    if ($notes) {
        $update_fields[] = 'notes = ?';
        $update_params[] = $notes;
    }
    
    $update_params[] = $order_id;
    
    // Siparişi güncelle
    $db->query("
        UPDATE siparisler 
        SET " . implode(', ', $update_fields) . "
        WHERE id = ?
    ", $update_params);
    
    // Konum bilgisini güncelle (eğer gönderilmişse)
    if (!empty($location['latitude']) && !empty($location['longitude'])) {
        $db->query("
            UPDATE kuryeler 
            SET current_latitude = ?, current_longitude = ?, last_location_update = NOW()
            WHERE id = ?
        ", [$location['latitude'], $location['longitude'], $kurye_id]);
        
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
    
    // Fotoğraf kaydet (eğer gönderilmişse)
    $photo_path = null;
    if ($photo && $new_status === 'delivered') {
        $photo_data = base64_decode($photo);
        if ($photo_data) {
            $photo_filename = 'delivery_' . $order_id . '_' . time() . '.jpg';
            $photo_path = 'uploads/delivery_photos/' . $photo_filename;
            
            // Klasörü oluştur
            if (!file_exists(dirname($photo_path))) {
                mkdir(dirname($photo_path), 0755, true);
            }
            
            file_put_contents($photo_path, $photo_data);
            
            // Fotoğraf yolunu siparişe kaydet
            $db->query("
                UPDATE siparisler 
                SET delivery_photo = ?
                WHERE id = ?
            ", [$photo_path, $order_id]);
        }
    }
    
    // Kurye müsaitliğini güncelle
    if ($new_status === 'delivered' || $new_status === 'cancelled') {
        // Başka aktif siparişi var mı kontrol et
        $other_active = $db->query("
            SELECT COUNT(*) as count 
            FROM siparisler 
            WHERE kurye_id = ? AND id != ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
        ", [$kurye_id, $order_id])->fetch();
        
        if ($other_active['count'] == 0) {
            $db->query("
                UPDATE kuryeler 
                SET is_available = 1 
                WHERE id = ?
            ", [$kurye_id]);
        }
    }
    
    // Performans hesaplama (teslim edildi ise)
    if ($new_status === 'delivered') {
        $delivery_performance = calculateDeliveryPerformance($order);
        
        if ($delivery_performance !== null) {
            $db->query("
                UPDATE siparisler 
                SET delivery_performance_score = ?
                WHERE id = ?
            ", [$delivery_performance, $order_id]);
        }
    }
    
    // Sipariş geçmişine kaydet
    $db->query("
        INSERT INTO siparis_gecmisi (siparis_id, previous_status, new_status, changed_by_type, changed_by_id, notes, created_at)
        VALUES (?, ?, ?, 'kurye', ?, ?, NOW())
    ", [$order_id, $current_status, $new_status, $user['user_id'], $notes]);
    
    // Transaction'ı tamamla
    $db->commit();
    
    // Güncellenmiş sipariş bilgisini al
    $updated_order = $db->query("
        SELECT s.*, m.mekan_name, m.address as mekan_address
        FROM siparisler s
        JOIN mekanlar m ON s.mekan_id = m.id
        WHERE s.id = ?
    ", [$order_id])->fetch();
    
    // Log kaydet
    writeLog("Order {$order['order_number']} status changed to {$new_status} by courier {$user['username']} via mobile app", 'INFO', 'mobile_api.log');
    
    // Push notification gönder
    // TODO: Firebase push notification implementation
    
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
            'delivery_photo' => $photo_path,
            'updated_at' => date('c')
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    writeLog("Mobile order status update error: " . $e->getMessage(), 'ERROR', 'mobile_api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Sipariş durumu güncellenirken bir hata oluştu'
        ]
    ], 500);
}

/**
 * Teslimat performansını hesapla
 */
function calculateDeliveryPerformance($order) {
    try {
        $created_time = strtotime($order['created_at']);
        $delivered_time = strtotime($order['delivered_at']);
        
        if (!$created_time || !$delivered_time) {
            return null;
        }
        
        $total_minutes = ($delivered_time - $created_time) / 60;
        $expected_minutes = ($order['preparation_time'] ?? 15) + 20; // Hazırlık + 20dk teslimat
        
        if ($total_minutes <= $expected_minutes) {
            return 100; // Mükemmel
        } elseif ($total_minutes <= $expected_minutes * 1.2) {
            return 80; // İyi
        } elseif ($total_minutes <= $expected_minutes * 1.5) {
            return 60; // Orta
        } else {
            return 40; // Kötü
        }
    } catch (Exception $e) {
        return null;
    }
}
?>
