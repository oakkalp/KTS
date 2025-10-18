<?php
/**
 * Kurye Full System - Admin Manuel Kurye Atama API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/config.php';

// JSON input al
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST metodu desteklenir']);
    exit;
}

// Session kontrolü - sadece admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetki hatası - Sadece admin erişebilir']);
    exit;
}

try {
    $order_id = (int)($input['order_id'] ?? 0);
    $courier_id = (int)($input['courier_id'] ?? 0);
    
    if (!$order_id || !$courier_id) {
        throw new Exception('Geçersiz sipariş veya kurye ID');
    }
    
    $db = getDB();
    
    // Siparişin durumunu kontrol et
    $stmt = $db->query("SELECT id, status, kurye_id, order_number FROM siparisler WHERE id = ?", [$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Sipariş bulunamadı');
    }
    
    if (!in_array($order['status'], ['pending', 'accepted'])) {
        throw new Exception('Bu sipariş durumunda kurye ataması yapılamaz');
    }
    
    // Kurye'nin durumunu kontrol et
    $stmt = $db->query("
        SELECT k.id, k.is_online, k.is_available, u.full_name, u.status as user_status
        FROM kuryeler k 
        JOIN users u ON k.user_id = u.id 
        WHERE k.id = ?
    ", [$courier_id]);
    
    $courier = $stmt->fetch();
    
    if (!$courier) {
        throw new Exception('Kurye bulunamadı');
    }
    
    if ($courier['user_status'] !== 'active') {
        throw new Exception('Kurye hesabı aktif değil');
    }
    
    if (!$courier['is_online']) {
        throw new Exception('Kurye şu anda offline - Manuel atama yapılabilir ancak kurye online olmadığı için bildirim alamayabilir');
    }
    
    // Kurye'nin aktif sipariş sayısını kontrol et
    $stmt = $db->query("SELECT COUNT(id) as active_orders FROM siparisler WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up')", [$courier_id]);
    $active_orders_count = $stmt->fetch()['active_orders'] ?? 0;
    
    $max_orders = (int)getSetting('max_orders_per_courier', 5);
    
    if ($active_orders_count >= $max_orders) {
        throw new Exception("Kurye maksimum sipariş limitine ({$max_orders}) ulaşmış. Başka bir kurye seçin.");
    }
    
    $db->beginTransaction();
    
    // Eğer sipariş zaten başka bir kurye'ye atanmışsa, o kurye'nin müsaitliğini güncelle
    if ($order['kurye_id']) {
        $stmt = $db->query("SELECT COUNT(id) as active_orders FROM siparisler WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up') AND id != ?", [$order['kurye_id'], $order_id]);
        $old_courier_active = $stmt->fetch()['active_orders'] ?? 0;
        
        $old_is_available = ($old_courier_active < $max_orders) ? 1 : 0;
        $db->query("UPDATE kuryeler SET is_available = ? WHERE id = ?", [$old_is_available, $order['kurye_id']]);
    }
    
    // Siparişi yeni kurye'ye ata
    $db->query("
        UPDATE siparisler 
        SET kurye_id = ?, status = 'accepted', accepted_at = NOW() 
        WHERE id = ?
    ", [$courier_id, $order_id]);
    
    // Yeni kurye'nin müsaitlik durumunu güncelle
    $stmt = $db->query("SELECT COUNT(id) as active_orders FROM siparisler WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up')", [$courier_id]);
    $new_active_count = $stmt->fetch()['active_orders'] ?? 0;
    
    $new_is_available = ($new_active_count < $max_orders) ? 1 : 0;
    $db->query("UPDATE kuryeler SET is_available = ? WHERE id = ?", [$new_is_available, $courier_id]);
    
    $db->commit();
    
    // Bildirim göndermeye çalış
    try {
        $stmt = $db->query("SELECT device_token FROM users WHERE id = (SELECT user_id FROM kuryeler WHERE id = ?)", [$courier_id]);
        $device_token = $stmt->fetch()['device_token'] ?? null;
        
        if ($device_token) {
            sendPushNotification(
                [$device_token],
                'Yeni Sipariş Atandı (Manuel)',
                "Admin tarafından sipariş atandı - No: {$order['order_number']}",
                ['order_id' => $order_id, 'type' => 'manual_assignment']
            );
        }
    } catch (Exception $e) {
        // Bildirim hatası önemli değil
        writeLog("Notification error for manual assignment: " . $e->getMessage(), 'WARNING');
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Sipariş {$courier['full_name']} kuryesine başarıyla atandı",
        'courier_name' => $courier['full_name'],
        'order_number' => $order['order_number']
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
