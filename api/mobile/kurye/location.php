<?php
/**
 * Kurye Full System - Mobile Kurye Konum Güncelleme API
 * Gerçek zamanlı konum güncelleme endpoint'i
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

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
    
    $latitude = (float)($input['latitude'] ?? 0);
    $longitude = (float)($input['longitude'] ?? 0);
    $accuracy = (float)($input['accuracy'] ?? 0);
    $speed = (float)($input['speed'] ?? 0);
    $heading = (float)($input['heading'] ?? 0);
    $timestamp = $input['timestamp'] ?? date('c');
    
    // Validation
    if (!$latitude || !$longitude) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Latitude ve longitude gereklidir'
            ]
        ], 400);
    }
    
    // Koordinat geçerliliği kontrolü
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_COORDINATES',
                'message' => 'Geçersiz koordinat değerleri'
            ]
        ], 400);
    }
    
    $db = getDB();
    $kurye_id = $user['type_id'];
    
    if (!$kurye_id) {
        throw new Exception('Kurye bilgisi bulunamadı');
    }
    
    // Rate limiting kontrolü (saniyede max 2 güncelleme)
    $rate_limit_key = 'location_update_' . $kurye_id;
    $cache_file = LOGS_PATH . '/' . $rate_limit_key . '.tmp';
    $current_time = time();
    
    if (file_exists($cache_file)) {
        $last_update = (int)file_get_contents($cache_file);
        if (($current_time - $last_update) < 0.5) { // 500ms minimum interval
            jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Konum güncellemeleri çok sık'
                ]
            ], 429);
        }
    }
    
    // Rate limit dosyasını güncelle
    file_put_contents($cache_file, $current_time);
    
    // Kurye'nin güncel konumunu güncelle
    $db->query("
        UPDATE kuryeler 
        SET current_latitude = ?, current_longitude = ?, last_location_update = NOW()
        WHERE id = ?
    ", [$latitude, $longitude, $kurye_id]);
    
    // Konum geçmişine kaydet
    $db->query("
        INSERT INTO kurye_konum_gecmisi (kurye_id, latitude, longitude, accuracy, speed, heading, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ", [$kurye_id, $latitude, $longitude, $accuracy, $speed, $heading]);
    
    // Aktif siparişi kontrol et
    $active_order = $db->query("
        SELECT id, status, customer_latitude, customer_longitude 
        FROM siparisler 
        WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
        ORDER BY created_at DESC
        LIMIT 1
    ", [$kurye_id])->fetch();
    
    $distance_to_customer = null;
    $estimated_arrival = null;
    
    if ($active_order) {
        // Konum geçmişini aktif sipariş ile ilişkilendir
        $db->query("
            UPDATE kurye_konum_gecmisi 
            SET siparis_id = ? 
            WHERE kurye_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ", [$active_order['id'], $kurye_id]);
        
        // Müşteriye mesafe hesapla (eğer koordinatlar varsa)
        if ($active_order['customer_latitude'] && $active_order['customer_longitude']) {
            $distance_to_customer = calculateDistance(
                $latitude, $longitude,
                $active_order['customer_latitude'], $active_order['customer_longitude']
            );
            
            // Tahmini varış süresi (ortalama 30 km/h hız)
            $estimated_arrival_minutes = ($distance_to_customer / 30) * 60;
            $estimated_arrival = date('c', time() + ($estimated_arrival_minutes * 60));
        }
    }
    
    // WebSocket için real-time update gönder (eğer implementasyon varsa)
    // TODO: WebSocket implementation
    
    // Response
    jsonResponse([
        'success' => true,
        'message' => 'Konum başarıyla güncellendi',
        'location' => [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'speed' => $speed,
            'heading' => $heading,
            'timestamp' => $timestamp,
            'updated_at' => date('c')
        ],
        'active_order_info' => $active_order ? [
            'order_id' => (int)$active_order['id'],
            'status' => $active_order['status'],
            'distance_to_customer' => $distance_to_customer,
            'estimated_arrival' => $estimated_arrival
        ] : null
    ]);
    
} catch (Exception $e) {
    writeLog("Mobile location update error: " . $e->getMessage(), 'ERROR', 'mobile_api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Konum güncellenirken bir hata oluştu'
        ]
    ], 500);
}
?>
