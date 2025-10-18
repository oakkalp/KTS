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

// jsonResponse function already defined in config.php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'Sadece POST istekleri kabul edilir'
            ]
        ], 405);
    }
    
    // JSON input al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'MISSING_FIELDS',
                'message' => 'Kullanıcı adı ve şifre gereklidir'
            ]
        ], 400);
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    
    // Kullanıcıyı veritabanından bul
    $user_query = "
        SELECT u.*, k.id as kurye_id, k.vehicle_type, k.is_online, k.is_available,
               m.id as mekan_id, m.mekan_name
        FROM users u
        LEFT JOIN kuryeler k ON u.id = k.user_id
        LEFT JOIN mekanlar m ON u.id = m.user_id
        WHERE u.username = ?
    ";
    
    $user_stmt = $db->query($user_query, [$username]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Kullanıcı adı veya şifre hatalı',
                'debug' => 'User not found: ' . $username
            ]
        ], 401);
    }
    
    // Şifre kontrolü
    if (!password_verify($password, $user['password'])) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Kullanıcı adı veya şifre hatalı',
                'debug' => 'Password verification failed for user: ' . $username
            ]
        ], 401);
    }
    
    // JWT token oluştur
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => (int)$user['id'],
        'username' => $user['username'],
        'user_type' => $user['user_type'],
        'kurye_id' => $user['kurye_id'] ? (int)$user['kurye_id'] : null,
        'mekan_id' => $user['mekan_id'] ? (int)$user['mekan_id'] : null,
        'iat' => time(),
        'exp' => time() + (30 * 24 * 60 * 60) // 30 gün
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    $jwt = $base64Header . "." . $base64Payload . "." . $base64Signature;
    
    // Son giriş zamanını güncelle
    $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    
    // Başarılı response
    jsonResponse([
        'success' => true,
        'message' => 'Giriş başarılı',
        'data' => [
            'token' => $jwt,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'user_type' => $user['user_type'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'kurye_id' => $user['kurye_id'] ? (int)$user['kurye_id'] : null,
                'mekan_id' => $user['mekan_id'] ? (int)$user['mekan_id'] : null,
                'vehicle_type' => $user['vehicle_type'],
                'mekan_name' => $user['mekan_name'],
                'last_login' => $user['last_login']
            ]
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
