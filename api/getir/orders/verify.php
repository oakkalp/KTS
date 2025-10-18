<?php
/**
 * GetirYemek API - Sipariş Onaylama
 * GetirYemek siparişini onaylar
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
    if (empty($input['order_id'])) {
        throw new Exception('Sipariş ID eksik');
    }
    
    $db = getDB();
    $siparis_id = (int)$input['order_id'];
    $mekan_id = $user['type_id'];
    
    // Siparişi bul ve kontrol et
    $stmt = $db->query("
        SELECT s.*, m.getir_restaurant_id 
        FROM siparisler s
        JOIN mekanlar m ON s.mekan_id = m.id
        WHERE s.id = ? AND s.mekan_id = ? AND s.source = 'getir'
    ", [$siparis_id, $mekan_id]);
    
    $siparis = $stmt->fetch();
    
    if (!$siparis) {
        throw new Exception('Sipariş bulunamadı veya bu mekana ait değil');
    }
    
    if (empty($siparis['getir_order_id'])) {
        throw new Exception('GetirYemek sipariş ID\'si bulunamadı');
    }
    
    // GetirYemek API'sine sipariş onaylama isteği gönder
    $getir_token = getGetirToken();
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GETIR_API_BASE_URL . '/food-orders/' . $siparis['getir_order_id'] . '/verify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $getir_token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
    
    // Sipariş durumunu güncelle
    $db->query("
        UPDATE siparisler 
        SET status = 'accepted',
            getir_status = 350,
            updated_at = NOW()
        WHERE id = ?
    ", [$siparis_id]);
    
    // Log kaydet
    writeLog("GetirYemek sipariş onaylandı: {$siparis['order_number']} (Getir ID: {$siparis['getir_order_id']})", 'INFO', 'getir.log');
    
    // Başarılı yanıt
    jsonResponse([
        'success' => true,
        'message' => 'Sipariş başarıyla onaylandı',
        'data' => [
            'order_id' => $siparis_id,
            'order_number' => $siparis['order_number'],
            'getir_order_id' => $siparis['getir_order_id'],
            'status' => 'accepted'
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("GetirYemek sipariş onaylama hatası: " . $e->getMessage(), 'ERROR', 'getir.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Sipariş onaylanırken hata oluştu: ' . $e->getMessage()
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

