<?php
/**
 * Kurye Full System - Mobile Kurye Dashboard API
 * Kurye dashboard verileri (istatistikler, aktif siparişler, yeni siparişler)
 */

require_once '../../../config/config.php';
require_once '../../includes/auth.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Sadece GET metodunu kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Sadece GET metodu desteklenir'
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
    $kurye_id = $user['type_id'];
    
    if (!$kurye_id) {
        throw new Exception('Kurye bilgisi bulunamadı');
    }
    
    // Komisyon oranını al
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    
    // Bugünkü istatistikler
    $today_stats = $db->query("
        SELECT 
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as today_deliveries,
            SUM(CASE WHEN status = 'delivered' THEN delivery_fee ELSE 0 END) as today_gross_earnings,
            COUNT(CASE WHEN status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering') THEN 1 END) as active_orders
        FROM siparisler 
        WHERE kurye_id = ? AND DATE(created_at) = CURDATE()
    ", [$kurye_id])->fetch();
    
    // Kurye rating'ini ayrıca al
    $kurye_rating = $db->query("
        SELECT rating FROM kuryeler WHERE id = ?
    ", [$kurye_id])->fetch();
    
    // Komisyon düşüldükten sonraki bugünkü kazanç
    $today_gross = (float)($today_stats['today_gross_earnings'] ?? 0);
    $today_commission = ($today_gross * $commission_rate) / 100;
    $today_net_earnings = $today_gross - $today_commission;
    
    // Aktif siparişler
    $active_orders = $db->query("
        SELECT s.*, m.mekan_name, m.address as mekan_address, m.phone as mekan_phone,
               TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) as order_age_minutes,
               CASE 
                   WHEN s.status = 'accepted' THEN TIMESTAMPDIFF(MINUTE, s.created_at, ADDTIME(s.created_at, SEC_TO_TIME(s.preparation_time * 60)))
                   WHEN s.status = 'preparing' THEN TIMESTAMPDIFF(MINUTE, NOW(), ADDTIME(s.created_at, SEC_TO_TIME(s.preparation_time * 60)))
                   ELSE NULL
               END as estimated_ready_minutes
        FROM siparisler s
        JOIN mekanlar m ON s.mekan_id = m.id
        WHERE s.kurye_id = ? AND s.status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
        ORDER BY s.created_at ASC
        LIMIT 10
    ", [$kurye_id])->fetchAll();
    
    // Aktif siparişleri formatla
    $formatted_active_orders = [];
    foreach ($active_orders as $order) {
        $gross_fee = (float)$order['delivery_fee'];
        $commission = ($gross_fee * $commission_rate) / 100;
        $net_earning = $gross_fee - $commission;
        
        $formatted_active_orders[] = [
            'id' => (int)$order['id'],
            'order_number' => $order['order_number'],
            'status' => $order['status'],
            'customer' => [
                'name' => $order['customer_name'],
                'phone' => $order['customer_phone'],
                'address' => $order['delivery_address']
            ],
            'restaurant' => [
                'name' => $order['mekan_name'],
                'address' => $order['mekan_address'],
                'phone' => $order['mekan_phone']
            ],
            'total_amount' => (float)$order['total_amount'],
            'delivery_fee' => $gross_fee,
            'net_earning' => round($net_earning, 2),
            'payment_method' => $order['payment_method'],
            'preparation_time' => (int)$order['preparation_time'],
            'estimated_ready_minutes' => $order['estimated_ready_minutes'],
            'order_age_minutes' => (int)$order['order_age_minutes'],
            'priority' => $order['priority'],
            'created_at' => date('c', strtotime($order['created_at'])),
            'notes' => $order['notes']
        ];
    }
    
    // Kurye'nin konum bilgisini al
    $kurye_location = $db->query("
        SELECT current_latitude, current_longitude 
        FROM kuryeler 
        WHERE id = ?
    ", [$kurye_id])->fetch();
    
    // Konum bilgisi yoksa varsayılan konum kullan (İstanbul)
    $kurye_lat = $kurye_location['current_latitude'] ?? 41.0082;
    $kurye_lng = $kurye_location['current_longitude'] ?? 28.9784;
    
    // Yeni siparişler (atanmamış) - konum filtrelemesi olmadan
    $available_orders = $db->query("
        SELECT s.*, m.mekan_name, m.address as mekan_address,
               TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) as order_age_minutes,
               (6371 * acos(cos(radians(?)) * cos(radians(m.latitude)) * cos(radians(m.longitude) - radians(?)) + sin(radians(?)) * sin(radians(m.latitude)))) AS distance_km
        FROM siparisler s
        JOIN mekanlar m ON s.mekan_id = m.id
        WHERE s.status = 'pending' AND s.kurye_id IS NULL
        ORDER BY s.priority DESC, s.created_at ASC
        LIMIT 20
    ", [$kurye_lat, $kurye_lng, $kurye_lat])->fetchAll();
    
    // Yeni siparişleri formatla
    $formatted_available_orders = [];
    foreach ($available_orders as $order) {
        $gross_fee = (float)$order['delivery_fee'];
        $commission = ($gross_fee * $commission_rate) / 100;
        $net_earning = $gross_fee - $commission;
        
        $formatted_available_orders[] = [
            'id' => (int)$order['id'],
            'order_number' => $order['order_number'],
            'restaurant' => [
                'name' => $order['mekan_name'],
                'address' => $order['mekan_address'],
                'distance' => round($order['distance_km'], 1)
            ],
            'customer' => [
                'name' => $order['customer_name'],
                'address' => $order['delivery_address']
            ],
            'total_amount' => (float)$order['total_amount'],
            'delivery_fee' => $gross_fee,
            'net_earning' => round($net_earning, 2),
            'payment_method' => $order['payment_method'],
            'preparation_time' => (int)$order['preparation_time'],
            'estimated_time' => (int)$order['preparation_time'] + 15, // +15dk teslimat süresi
            'order_age_minutes' => (int)$order['order_age_minutes'],
            'priority' => $order['priority'],
            'created_at' => date('c', strtotime($order['created_at'])),
            'expires_at' => date('c', strtotime($order['created_at']) + (30 * 60)) // 30dk sonra expire
        ];
    }
    
    // Kurye durumu
    $kurye_status = $db->query("
        SELECT is_online, is_available, current_latitude, current_longitude, 
               last_location_update, vehicle_type
        FROM kuryeler 
        WHERE id = ?
    ", [$kurye_id])->fetch();
    
    // Response
    jsonResponse([
        'success' => true,
        'data' => [
            'stats' => [
                'today_deliveries' => (int)($today_stats['today_deliveries'] ?? 0),
                'today_earnings' => round($today_net_earnings, 2),
                'active_orders' => (int)($today_stats['active_orders'] ?? 0),
                'rating' => $kurye_rating['rating'] ? round($kurye_rating['rating'], 1) : null
            ],
            'kurye_status' => [
                'is_online' => (bool)($kurye_status['is_online'] ?? false),
                'is_available' => (bool)($kurye_status['is_available'] ?? false),
                'vehicle_type' => $kurye_status['vehicle_type'] ?? 'motorcycle',
                'last_location_update' => $kurye_status['last_location_update'] 
                    ? date('c', strtotime($kurye_status['last_location_update']))
                    : null
            ],
            'active_orders' => $formatted_active_orders,
            'available_orders' => $formatted_available_orders
        ],
        'server_time' => date('c')
    ]);
    
} catch (Exception $e) {
    writeLog("Mobile kurye dashboard error: " . $e->getMessage(), 'ERROR', 'mobile_api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Veriler yüklenirken bir hata oluştu'
        ]
    ], 500);
}
?>
