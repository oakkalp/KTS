<?php
/**
 * AJAX - Kurye konumlarını getir
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

// Admin kontrolü
if (!isLoggedIn() || getUserType() !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Yetkisiz erişim'], 401);
}

try {
    $db = getDB();
    $stmt = $db->query("
        SELECT k.*, u.full_name, u.phone, u.last_login,
               COUNT(s.id) as active_orders
        FROM kuryeler k 
        JOIN users u ON k.user_id = u.id 
        LEFT JOIN siparisler s ON k.id = s.kurye_id AND s.status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
        WHERE u.status = 'active'
        GROUP BY k.id
        ORDER BY k.is_online DESC, k.last_location_update DESC
    ");
    
    $couriers = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'couriers' => $couriers,
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    writeLog("Get courier locations error: " . $e->getMessage(), 'ERROR');
    jsonResponse(['success' => false, 'message' => 'Veriler yüklenemedi'], 500);
}
?>
