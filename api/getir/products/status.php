<?php
/**
 * GetirYemek API - Ürün Durum Yönetimi
 * Ürün aktif/pasif durumunu yönetme
 */

require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
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
    
    $db = getDB();
    $mekan_id = $user['type_id'];
    
    // URL'den product_id'yi al
    $request_uri = $_SERVER['REQUEST_URI'];
    $path_parts = explode('/', trim($request_uri, '/'));
    $product_id = end($path_parts);
    
    if (empty($product_id) || $product_id === 'status.php') {
        throw new Exception('Ürün ID eksik');
    }
    
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
    
    // GetirYemek API token'ını al
    $getir_token = getGetirToken();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Ürün durumunu getir
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, GETIR_API_BASE_URL . '/products/' . $product_id . '/status');
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
        
        $response = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('GetirYemek API yanıtı geçersiz JSON formatında');
        }
        
        // Başarılı yanıt
        jsonResponse([
            'success' => true,
            'message' => 'Ürün durumu başarıyla alındı',
            'data' => [
                'product_id' => $product_id,
                'status' => $response['status'] ?? null
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Ürün durumunu güncelle
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Geçersiz JSON formatı');
        }
        
        if (!isset($input['status'])) {
            throw new Exception('Status parametresi eksik');
        }
        
        $status = (int)$input['status']; // 100 = ACTIVE, 200 = INACTIVE, 400 = DAILY_INACTIVE
        
        $request_data = ['status' => $status];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, GETIR_API_BASE_URL . '/products/' . $product_id . '/status');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $getir_token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
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
        
        // Log kaydet
        $status_text = match($status) {
            100 => 'Aktif',
            200 => 'Pasif',
            400 => 'Günlük Pasif',
            default => 'Bilinmeyen'
        };
        
        writeLog("GetirYemek ürün durumu güncellendi: {$mekan['mekan_name']} - Ürün ID: {$product_id} - Durum: {$status_text}", 'INFO', 'getir.log');
        
        // Başarılı yanıt
        jsonResponse([
            'success' => true,
            'message' => 'Ürün durumu başarıyla güncellendi',
            'data' => [
                'product_id' => $product_id,
                'status' => $status,
                'status_text' => $status_text
            ]
        ]);
        
    } else {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'Sadece GET ve PUT metotları desteklenir'
            ]
        ], 405);
    }
    
} catch (Exception $e) {
    writeLog("GetirYemek ürün durumu hatası: " . $e->getMessage(), 'ERROR', 'getir.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Ürün durumu işlenirken hata oluştu: ' . $e->getMessage()
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






