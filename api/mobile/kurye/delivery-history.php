<?php
/**
 * Kurye Full System - Mobile Kurye Teslimat Geçmişi API
 * Kurye teslimat geçmişini getirme endpoint'i
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
    
    $kurye_id = $user['type_id'];
    
    if (!$kurye_id) {
        throw new Exception('Kurye bilgisi bulunamadı');
    }
    
    $db = getDB();
    
    // Query parametrelerini al
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    $status = $_GET['status'] ?? null;
    
    // WHERE koşulları
    $where_conditions = ['s.kurye_id = ?'];
    $params = [$kurye_id];
    
    if ($date_from) {
        $where_conditions[] = 'DATE(s.created_at) >= ?';
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = 'DATE(s.created_at) <= ?';
        $params[] = $date_to;
    }
    
    if ($status) {
        $where_conditions[] = 's.status = ?';
        $params[] = $status;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Toplam kayıt sayısını al
    $count_query = "
        SELECT COUNT(*) as total
        FROM siparisler s
        JOIN mekanlar m ON s.mekan_id = m.id
        WHERE $where_clause
    ";
    $total_count = $db->query($count_query, $params)->fetch()['total'];
    
    // Teslimat geçmişini al
    $query = "
        SELECT 
            s.*,
            m.mekan_name,
            m.address as mekan_address,
            m.phone as mekan_phone,
            TIMESTAMPDIFF(MINUTE, s.created_at, s.delivered_at) as delivery_duration_minutes,
            TIMESTAMPDIFF(MINUTE, s.picked_up_at, s.delivered_at) as travel_duration_minutes
        FROM siparisler s
        JOIN mekanlar m ON s.mekan_id = m.id
        WHERE $where_clause
        ORDER BY s.delivered_at DESC, s.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $orders = $db->query($query, $params)->fetchAll();
    
    // Komisyon oranını al
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    
    // Siparişleri formatla
    $formatted_orders = [];
    foreach ($orders as $order) {
        $gross_fee = (float)$order['delivery_fee'];
        $commission = ($gross_fee * $commission_rate) / 100;
        $net_earning = $gross_fee - $commission;
        
        $formatted_orders[] = [
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
            'commission' => round($commission, 2),
            'payment_method' => $order['payment_method'],
            'priority' => $order['priority'],
            'created_at' => date('c', strtotime($order['created_at'])),
            'accepted_at' => $order['accepted_at'] ? date('c', strtotime($order['accepted_at'])) : null,
            'picked_up_at' => $order['picked_up_at'] ? date('c', strtotime($order['picked_up_at'])) : null,
            'delivered_at' => $order['delivered_at'] ? date('c', strtotime($order['delivered_at'])) : null,
            'delivery_duration_minutes' => (int)$order['delivery_duration_minutes'],
            'travel_duration_minutes' => (int)$order['travel_duration_minutes'],
            'performance_score' => $order['delivery_performance_score'] ? (int)$order['delivery_performance_score'] : null,
            'notes' => $order['notes']
        ];
    }
    
    // İstatistikler
    $stats_query = "
        SELECT 
            COUNT(*) as total_deliveries,
            SUM(delivery_fee) as total_gross_earnings,
            AVG(delivery_fee) as avg_delivery_fee,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) as avg_delivery_time,
            AVG(delivery_performance_score) as avg_performance_score
        FROM siparisler 
        WHERE kurye_id = ? AND status = 'delivered'
    ";
    
    $stats_params = [$kurye_id];
    if ($date_from) {
        $stats_query .= ' AND DATE(created_at) >= ?';
        $stats_params[] = $date_from;
    }
    if ($date_to) {
        $stats_query .= ' AND DATE(created_at) <= ?';
        $stats_params[] = $date_to;
    }
    
    $stats = $db->query($stats_query, $stats_params)->fetch();
    
    $total_gross = (float)($stats['total_gross_earnings'] ?? 0);
    $total_commission = ($total_gross * $commission_rate) / 100;
    $total_net = $total_gross - $total_commission;
    
    $statistics = [
        'total_deliveries' => (int)($stats['total_deliveries'] ?? 0),
        'total_gross_earnings' => round($total_gross, 2),
        'total_net_earnings' => round($total_net, 2),
        'total_commission' => round($total_commission, 2),
        'avg_delivery_fee' => round((float)($stats['avg_delivery_fee'] ?? 0), 2),
        'avg_delivery_time_minutes' => round((float)($stats['avg_delivery_time'] ?? 0), 1),
        'avg_performance_score' => round((float)($stats['avg_performance_score'] ?? 0), 1),
    ];
    
    // Response
    jsonResponse([
        'success' => true,
        'data' => [
            'orders' => $formatted_orders,
            'statistics' => $statistics,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total_count,
                'total_pages' => ceil($total_count / $limit),
                'has_next' => $page < ceil($total_count / $limit),
                'has_prev' => $page > 1,
            ]
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("Mobile delivery history error: " . $e->getMessage(), 'ERROR', 'mobile_api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Teslimat geçmişi alınırken bir hata oluştu'
        ]
    ], 500);
}
?>
