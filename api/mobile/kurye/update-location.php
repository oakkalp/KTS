<?php
/**
 * Kurye Full System - Mobile Konum Güncelleme API
 * JWT tabanlı konum güncelleme (mobil uygulama için)
 */

require_once '../../../config/config.php';
require_once '../../includes/auth.php';

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
    
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON formatı');
    }
    
    // Validation
    if (!isset($input['latitude']) || !isset($input['longitude'])) {
        throw new Exception('Latitude ve longitude gereklidir');
    }
    
    $latitude = (float)$input['latitude'];
    $longitude = (float)$input['longitude'];
    $accuracy = isset($input['accuracy']) ? (float)$input['accuracy'] : null;
    $speed = isset($input['speed']) ? (float)$input['speed'] : null;
    $heading = isset($input['heading']) ? (float)$input['heading'] : null;
    
    // Koordinat validasyonu
    if ($latitude < -90 || $latitude > 90) {
        throw new Exception('Geçersiz enlem değeri (-90 ile 90 arasında olmalı)');
    }
    
    if ($longitude < -180 || $longitude > 180) {
        throw new Exception('Geçersiz boylam değeri (-180 ile 180 arasında olmalı)');
    }
    
    $db = getDB();
    
    // Kurye bilgilerini al
    $stmt = $db->query("SELECT id, is_online FROM kuryeler WHERE user_id = ?", [$user['user_id']]);
    $kurye = $stmt->fetch();
    
    if (!$kurye) {
        throw new Exception('Kurye bilgileri bulunamadı');
    }
    
    // Kurye offline ise konum güncellemesine izin verme
    if (!$kurye['is_online']) {
        throw new Exception('Konum güncellemek için online olmanız gerekiyor');
    }
    
    // Aktif sipariş var mı kontrol et
    $stmt = $db->query(
        "SELECT id FROM siparisler 
         WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering') 
         LIMIT 1", 
        [$kurye['id']]
    );
    $active_order = $stmt->fetch();
    
    $db->beginTransaction();
    
    try {
        // Kurye tablosundaki konum bilgisini güncelle
        $db->query(
            "UPDATE kuryeler 
             SET current_latitude = ?, current_longitude = ?, last_location_update = NOW() 
             WHERE id = ?", 
            [$latitude, $longitude, $kurye['id']]
        );
        
        // Konum geçmişine kaydet
        $db->query(
            "INSERT INTO kurye_konum_gecmisi (kurye_id, latitude, longitude, accuracy, speed, siparis_id) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $kurye['id'], 
                $latitude, 
                $longitude, 
                $accuracy, 
                $speed, 
                $active_order ? $active_order['id'] : null
            ]
        );
        
        $db->commit();
        
        // Response
        jsonResponse([
            'success' => true,
            'message' => 'Konum başarıyla güncellendi',
            'data' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy,
                'speed' => $speed,
                'heading' => $heading,
                'updated_at' => date('c'),
                'has_active_order' => (bool)$active_order
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    writeLog("Mobile location update error: " . $e->getMessage(), 'ERROR', 'api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => $e->getMessage()
        ]
    ], 500);
}
?>
