<?php
/**
 * GetirYemek API - Restoran Kurye Yönetimi
 * Restoran kurye servisini aktif/pasif yapma
 */

require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

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
    
    if ($user['user_type'] !== 'mekan') {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Sadece mekanlar erişebilir'
            ]
        ], 403);
    }
    
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON formatı');
    }
    
    // Gerekli alanları kontrol et
    if (empty($input['action'])) {
        throw new Exception('Action parametresi eksik');
    }
    
    $db = getDB();
    $mekan_id = $user['type_id'];
    $action = $input['action']; // 'enable' veya 'disable'
    $time_off_amount = $input['timeOffAmount'] ?? 15; // 15, 30, 45 dakika
    
    // Mekan bilgilerini al
    $stmt = $db->query("
        SELECT m.*, u.username 
        FROM mekanlar m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = ? AND m.getir_restaurant_id IS NOT NULL
    ", [$mekan_id]);
    
    $mekan = $stmt->fetch();
    
    if (!$mekan) {
        throw new Exception('Mekan bulunamadı veya GetirYemek entegrasyonu yapılmamış');
    }
    
    if (empty($mekan['getir_restaurant_id'])) {
        throw new Exception('GetirYemek restoran ID\'si bulunamadı');
    }
    
    // GetirYemek API'sine kurye durum değişikliği isteği gönder
    $getir_token = getGetirToken();
    
    $endpoint = ($action === 'enable') ? '/restaurants/courier/enable' : '/restaurants/courier/disable';
    $request_data = [];
    
    if ($action === 'disable') {
        $request_data['timeOffAmount'] = $time_off_amount;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GETIR_API_BASE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $getir_token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if (!empty($request_data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
    }
    
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
    
    // Mekan kurye durumunu güncelle
    $courier_enabled = ($action === 'enable') ? 1 : 0;
    
    $db->query("
        UPDATE mekanlar 
        SET courier_enabled = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [$courier_enabled, $mekan_id]);
    
    // Log kaydet
    $action_text = ($action === 'enable') ? 'aktif edildi' : 'pasif edildi';
    writeLog("GetirYemek restoran kurye servisi {$action_text}: {$mekan['mekan_name']} (Getir ID: {$mekan['getir_restaurant_id']})", 'INFO', 'getir.log');
    
    // Başarılı yanıt
    jsonResponse([
        'success' => true,
        'message' => "Kurye servisi başarıyla {$action_text}",
        'data' => [
            'mekan_id' => $mekan_id,
            'mekan_name' => $mekan['mekan_name'],
            'getir_restaurant_id' => $mekan['getir_restaurant_id'],
            'action' => $action,
            'courier_enabled' => $courier_enabled
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("GetirYemek kurye durum değişikliği hatası: " . $e->getMessage(), 'ERROR', 'getir.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Kurye durumu değiştirilirken hata oluştu: ' . $e->getMessage()
        ]
    ], 500);
}

/**
 * GetirYemek API token'ını al
 */
function getGetirToken() {
    $db = getDB();
    
    // Aktif token'ı bul
    $stmt = $db->query("
        SELECT token FROM getir_tokens 
        WHERE expires_at > NOW() 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $token_data = $stmt->fetch();
    
    if ($token_data) {
        return $token_data['token'];
    }
    
    // Token yoksa veya süresi dolmuşsa yeni token al
    $stmt = $db->query("
        SELECT app_secret_key, restaurant_secret_key 
        FROM getir_tokens 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $credentials = $stmt->fetch();
    
    if (!$credentials) {
        throw new Exception('GetirYemek API kimlik bilgileri bulunamadı');
    }
    
    // Yeni token al
    $login_data = [
        'appSecretKey' => $credentials['app_secret_key'],
        'restaurantSecretKey' => $credentials['restaurant_secret_key']
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
    $db->query("
        INSERT INTO getir_tokens (
            app_secret_key, restaurant_secret_key, token, expires_at, created_at
        ) VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        token = VALUES(token),
        expires_at = VALUES(expires_at),
        updated_at = NOW()
    ", [$credentials['app_secret_key'], $credentials['restaurant_secret_key'], $token, $expires_at]);
    
    return $token;
}
?>






