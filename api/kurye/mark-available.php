<?php
/**
 * Kurye Full System - Kurye Müsait Yapma API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/config.php';

// Session kontrolü
if (!isLoggedIn() || !isKurye()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetki hatası']);
    exit;
}

try {
    $db = getDB();
    $kurye_id = getKuryeId();
    
    if (!$kurye_id) {
        throw new Exception('Kurye bilgileri bulunamadı');
    }
    
    // Aktif sipariş sayısını kontrol et
    $stmt = $db->query("
        SELECT COUNT(*) as active_count 
        FROM siparisler 
        WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up')
    ", [$kurye_id]);
    
    $active_orders = $stmt->fetch()['active_count'];
    $max_orders = (int)getSetting('max_orders_per_courier', 5);
    
    if ($active_orders >= $max_orders) {
        throw new Exception("Maksimum sipariş limitinize ({$max_orders}) ulaştınız. Mevcut siparişlerinizi tamamlayın.");
    }
    
    // Kurye'yi müsait yap
    $db->query("UPDATE kuryeler SET is_available = 1 WHERE id = ?", [$kurye_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Artık müsaitsiniz'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
