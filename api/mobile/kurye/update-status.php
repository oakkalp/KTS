<?php
/**
 * Kurye Full System - Mobile Kurye Durum Güncelleme API
 * Kurye durumunu güncelleme endpoint'i
 */

require_once '../../../config/config.php';
require_once '../../includes/auth.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
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
    
    if ($user['user_type'] !== 'kurye') {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Sadece kuryeler erişebilir'
            ]
        ], 403);
    }
    
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON formatı');
    }
    
    $is_online = isset($input['is_online']) ? (int)$input['is_online'] : null;
    $is_available = isset($input['is_available']) ? (int)$input['is_available'] : null;
    
    if ($is_online === null && $is_available === null) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'En az bir durum parametresi gereklidir'
            ]
        ], 400);
    }
    
    $db = getDB();
    $kurye_id = $user['type_id'];
    
    if (!$kurye_id) {
        throw new Exception('Kurye bilgisi bulunamadı');
    }
    
    // Güncelleme sorgusu oluştur
    $update_fields = [];
    $update_values = [];
    
    if ($is_online !== null) {
        $update_fields[] = 'is_online = ?';
        $update_values[] = $is_online;
    }
    
    if ($is_available !== null) {
        $update_fields[] = 'is_available = ?';
        $update_values[] = $is_available;
    }
    
    $update_values[] = $kurye_id;
    
    $sql = "UPDATE kuryeler SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = ?";
    
    $db->query($sql, $update_values);
    
    // Güncellenmiş durumu al
    $updated_status = $db->query("
        SELECT is_online, is_available, vehicle_type, last_location_update
        FROM kuryeler 
        WHERE id = ?
    ", [$kurye_id])->fetch();
    
    // Log kaydet
    $status_text = [];
    if ($is_online !== null) {
        $status_text[] = 'is_online: ' . ($is_online ? 'true' : 'false');
    }
    if ($is_available !== null) {
        $status_text[] = 'is_available: ' . ($is_available ? 'true' : 'false');
    }
    
    writeLog("Courier status updated for {$user['username']}: " . implode(', ', $status_text), 'INFO', 'mobile_api.log');
    
    // Response
    jsonResponse([
        'success' => true,
        'message' => 'Kurye durumu başarıyla güncellendi',
        'status' => [
            'is_online' => (bool)$updated_status['is_online'],
            'is_available' => (bool)$updated_status['is_available'],
            'vehicle_type' => $updated_status['vehicle_type'],
            'last_location_update' => $updated_status['last_location_update'],
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("Mobile update courier status error: " . $e->getMessage(), 'ERROR', 'mobile_api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Durum güncellenirken bir hata oluştu'
        ]
    ], 500);
}
?>
