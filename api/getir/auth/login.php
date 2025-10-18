<?php
/**
 * GetirYemek API - Authentication
 * GetirYemek API'sine giriş yapar ve token alır
 */

require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
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
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON formatı');
    }
    
    // Gerekli alanları kontrol et
    if (empty($input['appSecretKey']) || empty($input['restaurantSecretKey'])) {
        throw new Exception('Gerekli alanlar eksik');
    }
    
    $app_secret_key = $input['appSecretKey'];
    $restaurant_secret_key = $input['restaurantSecretKey'];
    
    // GetirYemek API'sine giriş yap
    $login_data = [
        'appSecretKey' => $app_secret_key,
        'restaurantSecretKey' => $restaurant_secret_key
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GETIR_API_BASE_URL . '/auth/login');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($login_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception("cURL hatası: $curl_error");
    }
    
    if ($http_code !== 200) {
        throw new Exception("GetirYemek API hatası: HTTP $http_code - $result");
    }
    
    $response = json_decode($result, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('GetirYemek API yanıtı geçersiz JSON formatında');
    }
    
    if (empty($response['token'])) {
        throw new Exception('GetirYemek API\'den token alınamadı');
    }
    
    $token = $response['token'];
    $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 saat sonra
    
    // Token'ı veritabanına kaydet
    $db = getDB();
    $db->query("
        INSERT INTO getir_tokens (
            app_secret_key, restaurant_secret_key, token, expires_at, created_at
        ) VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        token = VALUES(token),
        expires_at = VALUES(expires_at),
        updated_at = NOW()
    ", [$app_secret_key, $restaurant_secret_key, $token, $expires_at]);
    
    // Log kaydet
    writeLog("GetirYemek API giriş başarılı - Token alındı", 'INFO', 'getir.log');
    
    // Başarılı yanıt
    jsonResponse([
        'success' => true,
        'message' => 'GetirYemek API girişi başarılı',
        'data' => [
            'token' => $token,
            'expires_at' => $expires_at
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("GetirYemek API giriş hatası: " . $e->getMessage(), 'ERROR', 'getir.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'AUTHENTICATION_ERROR',
            'message' => 'GetirYemek API girişi başarısız: ' . $e->getMessage()
        ]
    ], 401);
}
?>

