<?php
/**
 * Kurye Full System - Kurye Durum Değiştirme API
 * Kurye online/offline durumunu değiştirir
 */

require_once '../../config/config.php';
require_once '../includes/auth.php';

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

handleAPIRequest('/kurye/toggle-status', 'kurye', function($user, $data) {
    // Validation
    if (!isset($data['is_online'])) {
        throw new Exception('is_online parametresi gereklidir');
    }
    
    $is_online = (bool)$data['is_online'];
    $is_available = isset($data['is_available']) ? (bool)$data['is_available'] : true;
    
    $db = getDB();
    
    // Kurye bilgilerini al
    $stmt = $db->query("SELECT id, is_online, is_available FROM kuryeler WHERE user_id = ?", [$user['user_id']]);
    $kurye = $stmt->fetch();
    
    if (!$kurye) {
        throw new Exception('Kurye bilgileri bulunamadı');
    }
    
    // Eğer offline olmaya çalışıyorsa, aktif sipariş kontrolü yap
    if (!$is_online && $kurye['is_online']) {
        $stmt = $db->query(
            "SELECT COUNT(*) as count FROM siparisler 
             WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')", 
            [$kurye['id']]
        );
        $active_orders = $stmt->fetch()['count'];
        
        if ($active_orders > 0) {
            throw new Exception('Aktif siparişiniz varken offline olamazsınız');
        }
    }
    
    // Durum güncelle
    $db->query(
        "UPDATE kuryeler 
         SET is_online = ?, is_available = ?, updated_at = NOW() 
         WHERE id = ?", 
        [$is_online, $is_available, $kurye['id']]
    );
    
    // Eğer offline oluyorsa konum bilgisini sıfırla (isteğe bağlı)
    if (!$is_online) {
        $db->query(
            "UPDATE kuryeler 
             SET current_latitude = NULL, current_longitude = NULL, last_location_update = NULL 
             WHERE id = ?", 
            [$kurye['id']]
        );
    }
    
    // Log kaydı
    $status_text = $is_online ? 'online' : 'offline';
    writeLog("Kurye status changed: {$user['username']} -> {$status_text}", 'INFO', 'kurye.log');
    
    // Bildirim gönder (admin ve mekanlar için)
    try {
        $notification_data = [
            'kurye_id' => $kurye['id'],
            'kurye_name' => $user['full_name'] ?? $user['username'],
            'status' => $status_text,
            'timestamp' => date('c')
        ];
        
        // Admin kullanıcılarına bildirim
        $stmt = $db->query("SELECT device_token FROM users WHERE user_type = 'admin' AND device_token IS NOT NULL");
        $admin_tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($admin_tokens)) {
            $title = 'Kurye Durum Değişikliği';
            $message = "{$user['full_name']} {$status_text} oldu";
            sendPushNotification($admin_tokens, $title, $message, $notification_data);
        }
        
    } catch (Exception $e) {
        writeLog("Notification error: " . $e->getMessage(), 'WARNING', 'api.log');
        // Bildirim hatası ana işlemi etkilemesin
    }
    
    // Response
    return [
        'success' => true,
        'message' => 'Durum başarıyla güncellendi',
        'data' => [
            'is_online' => $is_online,
            'is_available' => $is_available,
            'updated_at' => date('c')
        ]
    ];
    
}, 10); // Rate limit: dakikada 10 durum değişikliği
?>
