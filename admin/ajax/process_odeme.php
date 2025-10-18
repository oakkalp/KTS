<?php
/**
 * Kurye Full System - Kurye Ödeme İşleme AJAX
 */

error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/config.php';

header('Content-Type: application/json');

// Admin yetkisi kontrol et
requireUserType('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']);
    exit;
}

try {
    $kurye_id = (int)($_POST['kurye_id'] ?? 0);
    $odeme_tutari = (float)($_POST['odeme_tutari'] ?? 0);
    $aciklama = trim($_POST['aciklama'] ?? '');
    
    if (!$kurye_id) {
        throw new Exception('Geçersiz kurye ID');
    }
    
    if ($odeme_tutari <= 0) {
        throw new Exception('Ödeme tutarı 0\'dan büyük olmalıdır');
    }
    
    $db = getDB();
    $db->beginTransaction();
    
    // Kurye bilgilerini al
    $stmt = $db->query("
        SELECT k.*, u.full_name, u.username 
        FROM kuryeler k 
        JOIN users u ON k.user_id = u.id 
        WHERE k.id = ?
    ", [$kurye_id]);
    
    $kurye = $stmt->fetch();
    if (!$kurye) {
        throw new Exception('Kurye bulunamadı');
    }
    
    // Mevcut bakiye durumunu al
    $stmt = $db->query("
        SELECT bakiye 
        FROM bakiye 
        WHERE user_id = ? AND user_type = 'kurye'
    ", [$kurye['user_id']]);
    
    $bakiye_row = $stmt->fetch();
    $current_balance = $bakiye_row ? (float)$bakiye_row['bakiye'] : 0;
    
    if ($current_balance <= 0) {
        throw new Exception('Bu kuryenin ödenmesi gereken tutarı bulunmuyor');
    }
    
    // Yeni bakiye hesapla (ödeme yapıldığı için bakiye azalır)
    $new_balance = $current_balance - $odeme_tutari;
    
    // Bakiye tablosunu güncelle
    $db->query("
        INSERT INTO bakiye (user_id, user_type, bakiye, son_guncelleme) 
        VALUES (?, 'kurye', ?, NOW()) 
        ON DUPLICATE KEY UPDATE 
        bakiye = ?, son_guncelleme = NOW()
    ", [$kurye['user_id'], $new_balance, $new_balance]);
    
    // Ödeme kaydını ekle
    $db->query("
        INSERT INTO odemeler (user_id, user_type, odeme_tipi, tutar, aciklama, created_at) 
        VALUES (?, 'kurye', 'odeme', ?, ?, NOW())
    ", [$kurye['user_id'], $odeme_tutari, $aciklama]);
    
    // Tüm ödeme bekleyen siparişleri "ödeme yapıldı" olarak işaretle
    $updated_orders = $db->query("
        UPDATE siparisler 
        SET odeme_durumu = 'odeme_yapildi', odeme_tarihi = NOW() 
        WHERE kurye_id = ? 
        AND status = 'delivered' 
        AND (odeme_durumu IS NULL OR odeme_durumu = 'bekliyor')
    ", [$kurye_id]);
    
    $affected_orders = $updated_orders->rowCount();
    
    $db->commit();
    
    $balance_message = '';
    if ($new_balance > 0) {
        $balance_message = " Kalan bakiye: ₺" . number_format($new_balance, 2) . " (kurye alacaklı)";
    } elseif ($new_balance < 0) {
        $balance_message = " Kalan bakiye: ₺" . number_format(abs($new_balance), 2) . " (kurye borçlu)";
    } else {
        $balance_message = " Hesap temizlendi.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => "₺" . number_format($odeme_tutari, 2) . " ödeme başarıyla gerçekleştirildi. " . 
                    $affected_orders . " sipariş ödeme yapıldı olarak işaretlendi." . $balance_message
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>