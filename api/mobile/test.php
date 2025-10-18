<?php
require_once '../../config/config.php';

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
    // Test database connection
    $db_test = $db->query("SELECT 1 as test")->fetch();
    
    // Test data
    $test_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => $db_test ? 'connected' : 'disconnected',
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'server_info' => [
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        ],
        'test_endpoints' => [
            'login' => '/api/mobile/auth/login',
            'dashboard' => '/api/mobile/kurye/dashboard',
            'accept_order' => '/api/mobile/kurye/accept-order',
            'location' => '/api/mobile/kurye/location',
            'order_status' => '/api/mobile/kurye/order-status',
        ],
        'sample_data' => [
            'stats' => [
                'today_deliveries' => 5,
                'today_earnings' => 125.50,
                'active_orders' => 2,
                'rating' => 4.7
            ],
            'kurye_status' => [
                'is_online' => true,
                'is_available' => true,
                'vehicle_type' => 'motorcycle',
                'last_location_update' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
            ],
            'active_orders' => [
                [
                    'id' => 1,
                    'order_number' => 'ORD001',
                    'status' => 'ready',
                    'customer' => [
                        'name' => 'Ahmet Yılmaz',
                        'phone' => '+90 555 123 4567',
                        'address' => 'Atatürk Cad. No:123 Konak/İzmir'
                    ],
                    'restaurant' => [
                        'name' => 'Pizza Palace',
                        'address' => 'Cumhuriyet Cad. No:45 Alsancak/İzmir',
                        'distance' => 2.5
                    ],
                    'total_amount' => 45.00,
                    'delivery_fee' => 8.00,
                    'net_earning' => 6.00,
                    'payment_method' => 'cash',
                    'preparation_time' => 15,
                    'estimated_ready_minutes' => 5,
                    'order_age_minutes' => 10,
                    'priority' => 'normal',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
                ]
            ],
            'available_orders' => [
                [
                    'id' => 2,
                    'order_number' => 'ORD002',
                    'status' => 'pending',
                    'customer' => [
                        'name' => 'Fatma Demir',
                        'phone' => '+90 555 987 6543',
                        'address' => 'İnönü Cad. No:67 Bornova/İzmir'
                    ],
                    'restaurant' => [
                        'name' => 'Burger King',
                        'address' => 'Şair Eşref Blv. No:89 Buca/İzmir',
                        'distance' => 1.8
                    ],
                    'total_amount' => 32.50,
                    'delivery_fee' => 7.00,
                    'net_earning' => 5.25,
                    'payment_method' => 'online',
                    'preparation_time' => 20,
                    'estimated_ready_minutes' => 15,
                    'order_age_minutes' => 3,
                    'priority' => 'express',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-3 minutes')),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+27 minutes'))
                ]
            ]
        ]
    ];
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'API test successful',
        'data' => $test_data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => 'API test failed',
            'details' => $e->getMessage()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
