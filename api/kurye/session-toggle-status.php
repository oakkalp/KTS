<?php
/**
 * Kurye Full System - Session Tabanlı Durum Değiştirme
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
    // Session kontrolü
    if (!isLoggedIn() || getUserType() !== 'kurye') {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Oturum geçersiz veya kurye yetkisi yok'
            ]
        ], 401);
    }
    
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON formatı');
    }
    
    // Validation
    if (!isset($input['is_online'])) {
        throw new Exception('is_online parametresi gereklidir');
    }
    
    $is_online = (bool)$input['is_online'];
    $is_available = isset($input['is_available']) ? (bool)$input['is_available'] : true;
    
    $db = getDB();
    $user_id = getUserId();
    
    // Kurye bilgilerini al
    $stmt = $db->query("SELECT id, is_online, is_available FROM kuryeler WHERE user_id = ?", [$user_id]);
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
    
    // Log kaydet
    $username = $_SESSION['username'] ?? 'unknown';
    $status_text = $is_online ? 'online' : 'offline';
    writeLog("Kurye status changed: {$username} -> {$status_text}", 'INFO', 'kurye.log');
    
    // Response
    jsonResponse([
        'success' => true,
        'message' => 'Durum başarıyla güncellendi',
        'data' => [
            'is_online' => $is_online,
            'is_available' => $is_available,
            'updated_at' => date('c')
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("Session toggle status error: " . $e->getMessage(), 'ERROR', 'api.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => $e->getMessage()
        ]
    ], 500);
}
?>
