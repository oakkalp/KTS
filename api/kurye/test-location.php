<?php
/**
 * Test Location API - Debug için basit endpoint
 */

require_once '../../config/config.php';

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

// Test response
$response = [
    'success' => true,
    'message' => 'Test endpoint çalışıyor',
    'debug' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'session_status' => session_status(),
        'session_id' => session_id(),
        'session_data' => $_SESSION ?? [],
        'user_logged_in' => function_exists('isLoggedIn') ? isLoggedIn() : 'function not found',
        'user_type' => function_exists('getUserType') ? getUserType() : 'function not found',
        'timestamp' => date('Y-m-d H:i:s')
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>

