<?php
/**
 * Kurye Full System - Kurye Siparişleri API
 * Kurye siparişlerini listeler
 */

require_once '../../config/config.php';
require_once '../includes/auth.php';

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

handleAPIRequest('/kurye/orders', 'kurye', function($user, $data) {
    $db = getDB();
    
    // Kurye bilgilerini al
    $stmt = $db->query("SELECT id FROM kuryeler WHERE user_id = ?", [$user['user_id']]);
    $kurye = $stmt->fetch();
    
    if (!$kurye) {
        throw new Exception('Kurye bilgileri bulunamadı');
    }
    
    // Pagination parametreleri
    [$limit, $offset, $page] = getPaginationParams($data);
    
    // Filter parametreleri
    $status_filter = $data['status'] ?? 'all';
    $date_from = $data['date_from'] ?? null;
    $date_to = $data['date_to'] ?? null;
    
    // WHERE koşulları
    $where_conditions = ["s.kurye_id = ?"];
    $params = [$kurye['id']];
    
    // Status filtresi
    switch ($status_filter) {
        case 'active':
            $where_conditions[] = "s.status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')";
            break;
        case 'completed':
            $where_conditions[] = "s.status = 'delivered'";
            break;
        case 'cancelled':
            $where_conditions[] = "s.status = 'cancelled'";
            break;
        // 'all' için ek koşul yok
    }
    
    // Tarih filtreleri
    if ($date_from) {
        $where_conditions[] = "DATE(s.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(s.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Toplam kayıt sayısını al
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM siparisler s 
        {$where_clause}
    ";
    
    $stmt = $db->query($count_sql, $params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Siparişleri al
    $sql = "
        SELECT s.*, 
               m.mekan_name, 
               m.address as mekan_address,
               m.phone as mekan_phone,
               m.latitude as mekan_lat,
               m.longitude as mekan_lng
        FROM siparisler s 
        LEFT JOIN mekanlar m ON s.mekan_id = m.id 
        {$where_clause}
        ORDER BY 
            CASE s.status 
                WHEN 'delivering' THEN 1
                WHEN 'picked_up' THEN 2  
                WHEN 'ready' THEN 3
                WHEN 'preparing' THEN 4
                WHEN 'accepted' THEN 5
                ELSE 6
            END,
            s.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->query($sql, $params);
    $orders = $stmt->fetchAll();
    
    // Sipariş verilerini formatla
    $formatted_orders = [];
    foreach ($orders as $order) {
        // Sipariş detaylarını JSON'dan çözümle
        $order_details = json_decode($order['order_details'], true) ?? [];
        
        // Teslimat süresini hesapla (tamamlanmış siparişler için)
        $delivery_time = null;
        if ($order['status'] === 'delivered' && $order['delivered_at'] && $order['created_at']) {
            $start = new DateTime($order['created_at']);
            $end = new DateTime($order['delivered_at']);
            $delivery_time = $end->diff($start)->format('%H:%I:%S');
        }
        
        // Mesafe hesapla (kurye konumu varsa)
        $distance_to_restaurant = null;
        $distance_to_customer = null;
        
        if ($order['mekan_lat'] && $order['mekan_lng']) {
            // Kurye mevcut konumunu al
            $stmt = $db->query(
                "SELECT current_latitude, current_longitude FROM kuryeler WHERE id = ?", 
                [$kurye['id']]
            );
            $kurye_location = $stmt->fetch();
            
            if ($kurye_location && $kurye_location['current_latitude'] && $kurye_location['current_longitude']) {
                $distance_to_restaurant = calculateDistance(
                    $kurye_location['current_latitude'],
                    $kurye_location['current_longitude'],
                    $order['mekan_lat'],
                    $order['mekan_lng']
                );
                
                if ($order['customer_latitude'] && $order['customer_longitude']) {
                    $distance_to_customer = calculateDistance(
                        $kurye_location['current_latitude'],
                        $kurye_location['current_longitude'],
                        $order['customer_latitude'],
                        $order['customer_longitude']
                    );
                }
            }
        }
        
        $formatted_orders[] = [
            'id' => (int)$order['id'],
            'order_number' => $order['order_number'],
            'status' => $order['status'],
            'priority' => $order['priority'],
            'customer' => [
                'name' => $order['customer_name'],
                'phone' => $order['customer_phone'],
                'address' => $order['customer_address'],
                'latitude' => $order['customer_latitude'] ? (float)$order['customer_latitude'] : null,
                'longitude' => $order['customer_longitude'] ? (float)$order['customer_longitude'] : null
            ],
            'restaurant' => [
                'name' => $order['mekan_name'],
                'address' => $order['mekan_address'],
                'phone' => $order['mekan_phone'],
                'latitude' => $order['mekan_lat'] ? (float)$order['mekan_lat'] : null,
                'longitude' => $order['mekan_lng'] ? (float)$order['mekan_lng'] : null
            ],
            'order_details' => $order_details,
            'amounts' => [
                'total_amount' => (float)$order['total_amount'],
                'delivery_fee' => (float)$order['delivery_fee'],
                'commission_amount' => (float)$order['commission_amount']
            ],
            'distances' => [
                'to_restaurant_km' => $distance_to_restaurant,
                'to_customer_km' => $distance_to_customer
            ],
            'estimated_delivery_time' => $order['estimated_delivery_time'],
            'delivery_time' => $delivery_time,
            'notes' => $order['notes'],
            'timestamps' => [
                'created_at' => $order['created_at'],
                'accepted_at' => $order['accepted_at'],
                'picked_up_at' => $order['picked_up_at'],
                'delivered_at' => $order['delivered_at'],
                'cancelled_at' => $order['cancelled_at']
            ]
        ];
    }
    
    // Response
    return [
        'success' => true,
        'orders' => $formatted_orders,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => (int)$total_pages,
            'total_records' => (int)$total_records,
            'per_page' => $limit,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        ],
        'filters' => [
            'status' => $status_filter,
            'date_from' => $date_from,
            'date_to' => $date_to
        ]
    ];
    
}, 30); // Rate limit: dakikada 30 sipariş listesi çekme
?>
