<?php
/**
 * Kurye Full System - Tahsilat İşleme AJAX
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

// Admin yetkisi kontrol et
requireUserType('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']);
    exit;
}

$mekan_id = (int)($_POST['mekan_id'] ?? 0);
$tahsil_tutari = (float)($_POST['tahsil_tutari'] ?? 0);
$aciklama = clean($_POST['aciklama'] ?? '');

if (!$mekan_id || $tahsil_tutari <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz veriler']);
    exit;
}

try {
    $db = getDB();
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    
    // Mekan bilgilerini al
    $stmt = $db->query("
        SELECT m.*, u.id as user_id, u.full_name 
        FROM mekanlar m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id = ?
    ", [$mekan_id]);
    
    $mekan = $stmt->fetch();
    if (!$mekan) {
        throw new Exception('Mekan bulunamadı');
    }
    
    // Mevcut bakiye durumunu al
    $stmt = $db->query("
        SELECT bakiye 
        FROM bakiye 
        WHERE user_id = ? AND user_type = 'mekan'
    ", [$mekan['user_id']]);
    
    $bakiye_row = $stmt->fetch();
    $current_balance = 0;
    
    if (!$bakiye_row) {
        $db->query("
            INSERT INTO bakiye (user_id, user_type, bakiye) 
            VALUES (?, 'mekan', 0.00)
        ", [$mekan['user_id']]);
    } else {
        $current_balance = (float)$bakiye_row['bakiye'];
    }
    
    $db->beginTransaction();
    
    // Tahsilat kaydını ekle
    $db->query("
        INSERT INTO odemeler (user_id, user_type, odeme_tipi, tutar, aciklama) 
        VALUES (?, 'mekan', 'tahsilat', ?, ?)
    ", [$mekan['user_id'], $tahsil_tutari, $aciklama]);
    
    // Yeni bakiye = Eski bakiye - Ödenen tutar
    $new_balance = $current_balance - $tahsil_tutari;
    
    // Bakiyeyi güncelle
    $db->query("
        UPDATE bakiye 
        SET bakiye = ?, son_guncelleme = NOW() 
        WHERE user_id = ? AND user_type = 'mekan'
    ", [$new_balance, $mekan['user_id']]);
    
    // Tüm bekleyen siparişleri tahsil edildi olarak işaretle
    $updated_orders = $db->query("
        UPDATE siparisler 
        SET tahsilat_durumu = 'tahsil_edildi', tahsilat_tarihi = NOW() 
        WHERE mekan_id = ? 
        AND status = 'delivered' 
        AND (tahsilat_durumu IS NULL OR tahsilat_durumu = 'bekliyor')
    ", [$mekan_id]);
    
    $affected_orders = $updated_orders->rowCount();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Tahsilat başarıyla kaydedildi ($affected_orders sipariş tahsil edildi olarak işaretlendi)",
        'tahsil_tutari' => $tahsil_tutari,
        'yeni_bakiye' => $new_balance,
        'affected_orders' => $affected_orders
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>









