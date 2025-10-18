<?php
/**
 * Kurye Full System - Sipariş Kabul Etme API
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

// Session kontrolü
if (!isLoggedIn() || !isKurye()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetki hatası']);
    exit;
}

try {
    $order_id = (int)($input['order_id'] ?? 0);
    
    if (!$order_id) {
        throw new Exception('Geçersiz sipariş ID');
    }
    
    $db = getDB();
    $kurye_id = getKuryeId();
    
    // Debug bilgisi
    error_log("Accept Order Debug - User ID: " . getUserId() . ", Kurye ID: " . ($kurye_id ?? 'NULL'));
    
    if (!$kurye_id) {
        throw new Exception('Kurye bilgileri bulunamadı. User ID: ' . getUserId());
    }
    
    // Kuryenin online olduğunu kontrol et
    $stmt = $db->query("SELECT is_online FROM kuryeler WHERE id = ?", [$kurye_id]);
    $kurye_status = $stmt->fetch();
    
    if (!$kurye_status) {
        throw new Exception('Kurye durumu alınamadı');
    }
    
    if (!$kurye_status['is_online']) {
        throw new Exception('Sipariş kabul etmek için online olmalısınız');
    }
    
    // Aktif sipariş sayısını kontrol et
    $stmt = $db->query("SELECT COUNT(id) as active_orders FROM siparisler WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up')", [$kurye_id]);
    $active_orders_count = $stmt->fetch()['active_orders'] ?? 0;
    
    $max_orders = (int)getSetting('max_orders_per_courier', 5);
    
    if ($active_orders_count >= $max_orders) {
        throw new Exception("Maksimum sipariş limitinize ({$max_orders}) ulaştınız. Mevcut siparişlerinizi tamamlayın.");
    }
    
    // Siparişin hala müsait olduğunu kontrol et
    $stmt = $db->query("SELECT id, status, kurye_id FROM siparisler WHERE id = ?", [$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Sipariş bulunamadı');
    }
    
    if ($order['status'] !== 'pending') {
        throw new Exception('Bu sipariş artık müsait değil');
    }
    
    if ($order['kurye_id'] !== null) {
        throw new Exception('Bu sipariş başka bir kurye tarafından alınmış');
    }
    
    $db->beginTransaction();
    
    // Siparişi kurye'ye ata
    $db->query("UPDATE siparisler SET kurye_id = ?, status = 'accepted', accepted_at = NOW() WHERE id = ?", [$kurye_id, $order_id]);
    
    // Kurye'nin müsaitlik durumunu güncelle (aktif sipariş sayısına göre)
    $stmt = $db->query("SELECT COUNT(id) as active_orders FROM siparisler WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up')", [$kurye_id]);
    $new_active_count = $stmt->fetch()['active_orders'] ?? 0;
    
    $is_available = ($new_active_count < $max_orders) ? 1 : 0;
    $db->query("UPDATE kuryeler SET is_available = ? WHERE id = ?", [$is_available, $kurye_id]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sipariş başarıyla kabul edildi',
        'redirect' => 'siparislerim.php'
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
