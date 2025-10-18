<?php
require_once '../../../config/config.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'Sadece GET istekleri kabul edilir'
            ]
        ], 405);
    }
    
    // JWT token kontrolü
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';
    
    if (!$auth_header || !preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'MISSING_TOKEN',
                'message' => 'Authorization token gereklidir'
            ]
        ], 401);
    }
    
    $token = $matches[1];
    
    // JWT decode (basit)
    $token_parts = explode('.', $token);
    if (count($token_parts) !== 3) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_TOKEN',
                'message' => 'Geçersiz token formatı'
            ]
        ], 401);
    }
    
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $token_parts[1])), true);
    
    if (!$payload || !isset($payload['user_id']) || !isset($payload['kurye_id'])) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_TOKEN',
                'message' => 'Token geçersiz veya kurye değil'
            ]
        ], 401);
    }
    
    $user_id = $payload['user_id'];
    $kurye_id = $payload['kurye_id'];
    
    // Kurye bugünkü istatistikleri
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    
    $stats_query = "
        SELECT 
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as today_deliveries,
            SUM(CASE WHEN status = 'delivered' THEN delivery_fee ELSE 0 END) as total_delivery_fees,
            COUNT(CASE WHEN status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering') THEN 1 END) as active_orders
        FROM siparisler 
        WHERE kurye_id = ? AND created_at BETWEEN ? AND ?
    ";
    
    $stats_stmt = $db->query($stats_query, [$kurye_id, $today_start, $today_end]);
    $stats_raw = $stats_stmt->fetch();
    
    // Komisyon hesapla
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    $total_commission = ($stats_raw['total_delivery_fees'] * $commission_rate) / 100;
    $today_earnings = $stats_raw['total_delivery_fees'] - $total_commission;
    
    // Kurye durumu
    $kurye_status_query = "
        SELECT is_online, is_available, vehicle_type, 
               current_latitude, current_longitude, last_location_update
        FROM kuryeler 
        WHERE id = ?
    ";
    $kurye_status_stmt = $db->query($kurye_status_query, [$kurye_id]);
    $kurye_status = $kurye_status_stmt->fetch();
    
    if (!$kurye_status) {
        $kurye_status = [
            'is_online' => false,
            'is_available' => false,
            'vehicle_type' => 'motorcycle',
            'current_latitude' => null,
            'current_longitude' => null,
            'last_location_update' => null
        ];
    }
    
    // Aktif siparişler
    $active_orders_query = "
        SELECT s.*, m.mekan_name, m.address as mekan_address
        FROM siparisler s
        LEFT JOIN mekanlar m ON s.mekan_id = m.id
        WHERE s.kurye_id = ? AND s.status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
        ORDER BY s.created_at ASC
        LIMIT 10
    ";
    $active_orders_stmt = $db->query($active_orders_query, [$kurye_id]);
    $active_orders_raw = $active_orders_stmt->fetchAll();
    
    // Yeni siparişler
    $available_orders_query = "
        SELECT s.*, m.mekan_name, m.address as mekan_address
        FROM siparisler s
        LEFT JOIN mekanlar m ON s.mekan_id = m.id
        WHERE s.status = 'pending' AND s.kurye_id IS NULL
        ORDER BY s.created_at ASC
        LIMIT 10
    ";
    $available_orders_stmt = $db->query($available_orders_query);
    $available_orders_raw = $available_orders_stmt->fetchAll();
    
    // Siparişleri formatla
    $format_order = function($order) use ($commission_rate) {
        $commission = ($order['delivery_fee'] * $commission_rate) / 100;
        $net_earning = $order['delivery_fee'] - $commission;
        
        return [
            'id' => (int)$order['id'],
            'order_number' => $order['order_number'] ?? 'ORD' . str_pad($order['id'], 6, '0', STR_PAD_LEFT),
            'status' => $order['status'],
            'customer' => [
                'name' => $order['customer_name'],
                'phone' => $order['customer_phone'],
                'address' => $order['delivery_address']
            ],
            'restaurant' => [
                'name' => $order['mekan_name'] ?? 'Bilinmeyen Mekan',
                'address' => $order['mekan_address'] ?? ''
            ],
            'total_amount' => (float)$order['total_amount'],
            'delivery_fee' => (float)$order['delivery_fee'],
            'net_earning' => round($net_earning, 2),
            'payment_method' => $order['payment_method'],
            'created_at' => $order['created_at'],
            'order_age_minutes' => floor((time() - strtotime($order['created_at'])) / 60)
        ];
    };
    
    $active_orders = array_map($format_order, $active_orders_raw);
    $available_orders = array_map($format_order, $available_orders_raw);
    
    // Response
    jsonResponse([
        'success' => true,
        'data' => [
            'stats' => [
                'today_deliveries' => (int)($stats_raw['today_deliveries'] ?? 0),
                'today_earnings' => round($today_earnings, 2),
                'active_orders' => (int)($stats_raw['active_orders'] ?? 0),
                'rating' => null // TODO: Rating hesaplaması
            ],
            'kurye_status' => [
                'is_online' => (bool)($kurye_status['is_online'] ?? false),
                'is_available' => (bool)($kurye_status['is_available'] ?? false),
                'vehicle_type' => $kurye_status['vehicle_type'] ?? 'motorcycle',
                'last_location_update' => $kurye_status['last_location_update']
            ],
            'active_orders' => $active_orders,
            'available_orders' => $available_orders
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'SERVER_ERROR',
            'message' => 'Sunucu hatası oluştu',
            'debug' => $e->getMessage()
        ]
    ], 500);
}
?>
