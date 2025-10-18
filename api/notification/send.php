<?php
/**
 * Kurye Full System - Push Notification Gönderme API
 * Admin ve mekanların bildiri gönderebilmesi için
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

handleAPIRequest('/notification/send', null, function($user, $data) {
    // Sadece admin ve mekan sahipleri bildirim gönderebilir
    if (!in_array($user['user_type'], ['admin', 'mekan'])) {
        throw new Exception('Bu işlem için yeterli yetkiniz yok');
    }
    
    // Validation
    validateRequired($data, ['title', 'message', 'target_type']);
    
    $title = clean($data['title']);
    $message = clean($data['message']);
    $target_type = clean($data['target_type']); // 'all', 'kurye', 'mekan', 'admin', 'user_id'
    $target_id = isset($data['target_id']) ? (int)$data['target_id'] : null;
    $notification_data = $data['data'] ?? [];
    
    // Mesaj uzunluk kontrolü
    if (strlen($title) > 100) {
        throw new Exception('Başlık çok uzun (maksimum 100 karakter)');
    }
    
    if (strlen($message) > 500) {
        throw new Exception('Mesaj çok uzun (maksimum 500 karakter)');
    }
    
    $db = getDB();
    
    // Hedef kullanıcıları belirle
    $target_users = [];
    
    switch ($target_type) {
        case 'all':
            if ($user['user_type'] !== 'admin') {
                throw new Exception('Tüm kullanıcılara bildirim sadece admin gönderebilir');
            }
            $stmt = $db->query("SELECT id, username, device_token FROM users WHERE device_token IS NOT NULL AND status = 'active'");
            $target_users = $stmt->fetchAll();
            break;
            
        case 'kurye':
            $stmt = $db->query("SELECT id, username, device_token FROM users WHERE user_type = 'kurye' AND device_token IS NOT NULL AND status = 'active'");
            $target_users = $stmt->fetchAll();
            break;
            
        case 'mekan':
            if ($user['user_type'] !== 'admin') {
                throw new Exception('Mekan sahiplerine bildirim sadece admin gönderebilir');
            }
            $stmt = $db->query("SELECT id, username, device_token FROM users WHERE user_type = 'mekan' AND device_token IS NOT NULL AND status = 'active'");
            $target_users = $stmt->fetchAll();
            break;
            
        case 'admin':
            if ($user['user_type'] !== 'admin') {
                throw new Exception('Admin kullanıcılarına bildirim sadece admin gönderebilir');
            }
            $stmt = $db->query("SELECT id, username, device_token FROM users WHERE user_type = 'admin' AND device_token IS NOT NULL AND status = 'active'");
            $target_users = $stmt->fetchAll();
            break;
            
        case 'user_id':
            if (!$target_id) {
                throw new Exception('user_id hedefi için target_id gereklidir');
            }
            
            // Mekan sahipleri sadece kendi kuryelerine bildirim gönderebilir
            if ($user['user_type'] === 'mekan') {
                // Hedef kullanıcının kurye olup olmadığını ve bu mekana ait olup olmadığını kontrol et
                $stmt = $db->query("
                    SELECT u.id, u.username, u.device_token 
                    FROM users u 
                    JOIN kuryeler k ON u.id = k.user_id 
                    WHERE u.id = ? AND u.user_type = 'kurye' AND u.device_token IS NOT NULL AND u.status = 'active'
                ", [$target_id]);
                $target_user = $stmt->fetch();
                
                if (!$target_user) {
                    throw new Exception('Hedef kullanıcı bulunamadı veya erişim yetkiniz yok');
                }
                
                $target_users = [$target_user];
            } else {
                // Admin tüm kullanıcılara gönderebilir
                $stmt = $db->query("SELECT id, username, device_token FROM users WHERE id = ? AND device_token IS NOT NULL AND status = 'active'", [$target_id]);
                $target_user = $stmt->fetch();
                
                if (!$target_user) {
                    throw new Exception('Hedef kullanıcı bulunamadı');
                }
                
                $target_users = [$target_user];
            }
            break;
            
        default:
            throw new Exception('Geçersiz hedef tipi');
    }
    
    if (empty($target_users)) {
        throw new Exception('Bildirim gönderilecek kullanıcı bulunamadı');
    }
    
    // Device token'ları topla
    $device_tokens = array_column($target_users, 'device_token');
    $device_tokens = array_filter($device_tokens); // Boş olanları filtrele
    
    if (empty($device_tokens)) {
        throw new Exception('Aktif device token bulunamadı');
    }
    
    // Bildirim verisi
    $notification_payload = [
        'title' => $title,
        'message' => $message,
        'sender_id' => $user['user_id'],
        'sender_name' => $user['full_name'] ?? $user['username'],
        'sender_type' => $user['user_type'],
        'target_type' => $target_type,
        'timestamp' => date('c')
    ];
    
    // Ek veri varsa ekle
    if (!empty($notification_data)) {
        $notification_payload['data'] = $notification_data;
    }
    
    // Push notification gönder
    $success = sendPushNotification($device_tokens, $title, $message, $notification_payload);
    
    // Bildirim geçmişine kaydet (her hedef kullanıcı için)
    foreach ($target_users as $target_user) {
        try {
            $db->query(
                "INSERT INTO bildirimler (user_id, type, title, message, data, is_sent) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $target_user['id'],
                    'system',
                    $title,
                    $message,
                    json_encode($notification_payload),
                    $success ? 1 : 0
                ]
            );
        } catch (Exception $e) {
            writeLog("Bildirim veritabanına kaydedilemedi: " . $e->getMessage(), 'WARNING', 'notifications.log');
        }
    }
    
    // Log kaydet
    writeLog("Notification sent by {$user['username']} to {$target_type}: {$title}", 'INFO', 'notifications.log');
    
    // Response
    return [
        'success' => true,
        'message' => 'Bildirim başarıyla gönderildi',
        'data' => [
            'target_count' => count($target_users),
            'device_token_count' => count($device_tokens),
            'notification_sent' => $success,
            'sent_at' => date('c')
        ]
    ];
    
}, 10); // Rate limit: dakikada 10 bildirim gönderme
?>
