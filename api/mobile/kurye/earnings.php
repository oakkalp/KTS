<?php
/**
 * Kurye Full System - Mobile Kurye Kazançlar API
 * Kurye kazanç bilgilerini getirme endpoint'i
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
    
    // Komisyon oranını al
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    
    // Bugünkü kazançlar
    $today_stats = $db->query("
        SELECT 
            COUNT(*) as deliveries,
            SUM(delivery_fee) as gross_earnings,
            AVG(delivery_fee) as avg_fee,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) as avg_delivery_time
        FROM siparisler 
        WHERE kurye_id = ? AND status = 'delivered' AND DATE(created_at) = CURDATE()
    ", [$kurye_id])->fetch();
    
    $today_gross = (float)($today_stats['gross_earnings'] ?? 0);
    $today_commission = ($today_gross * $commission_rate) / 100;
    $today_net = $today_gross - $today_commission;
    
    // Bu haftaki kazançlar
    $week_stats = $db->query("
        SELECT 
            COUNT(*) as deliveries,
            SUM(delivery_fee) as gross_earnings
        FROM siparisler 
        WHERE kurye_id = ? AND status = 'delivered' 
        AND YEARWEEK(created_at) = YEARWEEK(CURDATE())
    ", [$kurye_id])->fetch();
    
    $week_gross = (float)($week_stats['gross_earnings'] ?? 0);
    $week_commission = ($week_gross * $commission_rate) / 100;
    $week_net = $week_gross - $week_commission;
    
    // Bu ayki kazançlar
    $month_stats = $db->query("
        SELECT 
            COUNT(*) as deliveries,
            SUM(delivery_fee) as gross_earnings
        FROM siparisler 
        WHERE kurye_id = ? AND status = 'delivered' 
        AND MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
    ", [$kurye_id])->fetch();
    
    $month_gross = (float)($month_stats['gross_earnings'] ?? 0);
    $month_commission = ($month_gross * $commission_rate) / 100;
    $month_net = $month_gross - $month_commission;
    
    // Toplam kazançlar
    $total_stats = $db->query("
        SELECT 
            COUNT(*) as deliveries,
            SUM(delivery_fee) as gross_earnings,
            AVG(delivery_fee) as avg_fee,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) as avg_delivery_time,
            AVG(delivery_performance_score) as avg_performance_score
        FROM siparisler 
        WHERE kurye_id = ? AND status = 'delivered'
    ", [$kurye_id])->fetch();
    
    $total_gross = (float)($total_stats['gross_earnings'] ?? 0);
    $total_commission = ($total_gross * $commission_rate) / 100;
    $total_net = $total_gross - $total_commission;
    
    // Aylık kazanç geçmişi (son 12 ay)
    $monthly_history = $db->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as deliveries,
            SUM(delivery_fee) as gross_earnings
        FROM siparisler 
        WHERE kurye_id = ? AND status = 'delivered' 
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ", [$kurye_id])->fetchAll();
    
    $monthly_data = [];
    foreach ($monthly_history as $month) {
        $month_gross = (float)$month['gross_earnings'];
        $month_commission = ($month_gross * $commission_rate) / 100;
        $month_net = $month_gross - $month_commission;
        
        $monthly_data[] = [
            'month' => $month['month'],
            'deliveries' => (int)$month['deliveries'],
            'gross_earnings' => round($month_gross, 2),
            'net_earnings' => round($month_net, 2),
            'commission' => round($month_commission, 2),
        ];
    }
    
    // Haftalık kazanç geçmişi (son 8 hafta)
    $weekly_history = $db->query("
        SELECT 
            YEARWEEK(created_at) as week,
            COUNT(*) as deliveries,
            SUM(delivery_fee) as gross_earnings
        FROM siparisler 
        WHERE kurye_id = ? AND status = 'delivered' 
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
        GROUP BY YEARWEEK(created_at)
        ORDER BY week DESC
    ", [$kurye_id])->fetchAll();
    
    $weekly_data = [];
    foreach ($weekly_history as $week) {
        $week_gross = (float)$week['gross_earnings'];
        $week_commission = ($week_gross * $commission_rate) / 100;
        $week_net = $week_gross - $week_commission;
        
        $weekly_data[] = [
            'week' => $week['week'],
            'deliveries' => (int)$week['deliveries'],
            'gross_earnings' => round($week_gross, 2),
            'net_earnings' => round($week_net, 2),
            'commission' => round($week_commission, 2),
        ];
    }
    
    // Response
    jsonResponse([
        'success' => true,
        'data' => [
            'today' => [
                'deliveries' => (int)($today_stats['deliveries'] ?? 0),
                'gross_earnings' => round($today_gross, 2),
                'net_earnings' => round($today_net, 2),
                'commission' => round($today_commission, 2),
                'avg_fee' => round((float)($today_stats['avg_fee'] ?? 0), 2),
                'avg_delivery_time' => round((float)($today_stats['avg_delivery_time'] ?? 0), 1),
            ],
            'week' => [
                'deliveries' => (int)($week_stats['deliveries'] ?? 0),
                'gross_earnings' => round($week_gross, 2),
                'net_earnings' => round($week_net, 2),
                'commission' => round($week_commission, 2),
            ],
            'month' => [
                'deliveries' => (int)($month_stats['deliveries'] ?? 0),
                'gross_earnings' => round($month_gross, 2),
                'net_earnings' => round($month_net, 2),
                'commission' => round($month_commission, 2),
            ],
            'total' => [
                'deliveries' => (int)($total_stats['deliveries'] ?? 0),
                'gross_earnings' => round($total_gross, 2),
                'net_earnings' => round($total_net, 2),
                'commission' => round($total_commission, 2),
                'avg_fee' => round((float)($total_stats['avg_fee'] ?? 0), 2),
                'avg_delivery_time' => round((float)($total_stats['avg_delivery_time'] ?? 0), 1),
                'avg_performance_score' => round((float)($total_stats['avg_performance_score'] ?? 0), 1),
            ],
            'monthly_history' => $monthly_data,
            'weekly_history' => $weekly_data,
            'commission_rate' => $commission_rate,
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("Mobile earnings error: " . $e->getMessage(), 'ERROR', 'mobile_api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Kazanç bilgileri alınırken bir hata oluştu'
        ]
    ], 500);
}
?>
