<?php
/**
 * Kurye Full System - Session Tabanlı Konum Güncelleme
 * Web panelinden çağrılacak (JWT yerine session kullanır)
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
    // Debug: Session bilgilerini logla
    error_log("Location API - Session check started");
    error_log("Session data: " . print_r($_SESSION, true));
    error_log("isLoggedIn(): " . (isLoggedIn() ? 'true' : 'false'));
    error_log("getUserType(): " . getUserType());
    
    // Session kontrolü - daha esnek
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'kurye') {
        error_log("Location API - Unauthorized access");
        error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
        error_log("Session user_type: " . ($_SESSION['user_type'] ?? 'not set'));
        
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Oturum geçersiz veya kurye yetkisi yok'
            ]
        ], 401);
    }
    
    error_log("Location API - Session check passed");
    
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
    
    // Koordinat validasyonu
    if ($latitude < -90 || $latitude > 90) {
        throw new Exception('Geçersiz enlem değeri (-90 ile 90 arasında olmalı)');
    }
    
    if ($longitude < -180 || $longitude > 180) {
        throw new Exception('Geçersiz boylam değeri (-180 ile 180 arasında olmalı)');
    }
    
    $db = getDB();
    $user_id = getUserId();
    
    error_log("Location API - user_id: " . $user_id);
    
    // Eksik tabloları otomatik oluştur
    try {
        $db->query("CREATE TABLE IF NOT EXISTS `kurye_konum_gecmisi` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `kurye_id` int(11) NOT NULL,
          `latitude` decimal(10,8) NOT NULL,
          `longitude` decimal(11,8) NOT NULL,
          `accuracy` decimal(8,2) DEFAULT NULL,
          `speed` decimal(8,2) DEFAULT NULL,
          `heading` decimal(8,2) DEFAULT NULL,
          `altitude` decimal(8,2) DEFAULT NULL,
          `siparis_id` int(11) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_kurye_id` (`kurye_id`),
          KEY `idx_created_at` (`created_at`),
          KEY `idx_siparis_id` (`siparis_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $db->query("CREATE TABLE IF NOT EXISTS `kurye_konum` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `kurye_id` int(11) NOT NULL,
          `latitude` decimal(10,8) NOT NULL,
          `longitude` decimal(11,8) NOT NULL,
          `accuracy` decimal(8,2) DEFAULT NULL,
          `speed` decimal(8,2) DEFAULT NULL,
          `heading` decimal(8,2) DEFAULT NULL,
          `altitude` decimal(8,2) DEFAULT NULL,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `idx_kurye_id` (`kurye_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Eksik sütunları ekle (mevcut tablolar için)
        try {
            // Sütun var mı kontrol et
            $checkColumn = $db->query("SHOW COLUMNS FROM kurye_konum_gecmisi LIKE 'siparis_id'")->fetch();
            if (!$checkColumn) {
                $db->query("ALTER TABLE kurye_konum_gecmisi ADD COLUMN siparis_id int(11) DEFAULT NULL");
                $db->query("ALTER TABLE kurye_konum_gecmisi ADD INDEX idx_siparis_id (siparis_id)");
                error_log("Location API - siparis_id sütunu eklendi");
            }
        } catch (Exception $alterError) {
            error_log("Location API - Sütun ekleme hatası: " . $alterError->getMessage());
        }
        
        error_log("Location API - Tablolar kontrol edildi/oluşturuldu");
    } catch (Exception $e) {
        error_log("Location API - Tablo oluşturma hatası: " . $e->getMessage());
    }
    
    // Kurye bilgilerini al
    error_log("Location API - Kurye sorgusu çalıştırılıyor: user_id = " . $user_id);
    $stmt = $db->query("SELECT id, is_online FROM kuryeler WHERE user_id = ?", [$user_id]);
    $kurye = $stmt->fetch();
    
    error_log("Location API - Kurye sorgu sonucu: " . print_r($kurye, true));
    
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
                'updated_at' => date('c'),
                'has_active_order' => (bool)$active_order
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    writeLog("Session location update error: " . $e->getMessage(), 'ERROR', 'api.log');
    error_log("Location API Exception: " . $e->getMessage());
    error_log("Location API Stack trace: " . $e->getTraceAsString());
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => $e->getMessage(),
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]
    ], 500);
}
?>
