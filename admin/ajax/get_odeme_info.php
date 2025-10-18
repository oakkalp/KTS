<?php
/**
 * Kurye Full System - Kurye Ödeme Bilgisi AJAX
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

$input = json_decode(file_get_contents('php://input'), true);
$kurye_id = (int)($input['kurye_id'] ?? 0);

if (!$kurye_id) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz kurye ID']);
    exit;
}

try {
    $db = getDB();
    
    // Sistem ayarlarını al
    $delivery_fee = (float)getSetting('delivery_fee', 40.00);
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    $net_per_delivery = $delivery_fee * (1 - $commission_rate / 100);
    
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
    
    // Toplam ödeme bekleyen sipariş sayısını al
    $stmt = $db->query("
        SELECT COUNT(CASE WHEN status = 'delivered' AND (odeme_durumu IS NULL OR odeme_durumu = 'bekliyor') THEN 1 END) as pending_orders
        FROM siparisler 
        WHERE kurye_id = ?
    ", [$kurye_id]);
    
    $stats = $stmt->fetch();
    $pending_orders = (int)($stats['pending_orders'] ?? 0);
    
    // Mevcut bakiye durumunu kontrol et
    $stmt = $db->query("
        SELECT bakiye 
        FROM bakiye 
        WHERE user_id = ? AND user_type = 'kurye'
    ", [$kurye['user_id']]);
    
    $bakiye_row = $stmt->fetch();
    $current_balance = 0;
    
    if (!$bakiye_row) {
        // İlk kez bakiye kaydı oluştur
        $db->query("
            INSERT INTO bakiye (user_id, user_type, bakiye) 
            VALUES (?, 'kurye', 0.00)
        ", [$kurye['user_id']]);
    } else {
        $current_balance = (float)$bakiye_row['bakiye'];
    }
    
    // HTML içeriği oluştur
    $html = '
    <form id="odemeForm">
        <input type="hidden" name="kurye_id" value="' . $kurye_id . '">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <h6><i class="fas fa-motorcycle me-2"></i>' . htmlspecialchars($kurye['full_name']) . '</h6>
                <small class="text-muted">@' . htmlspecialchars($kurye['username']) . '</small>
            </div>
            <div class="col-md-6 text-end">
                <small class="text-muted">Ödeme Bekleyen</small>
                <br><strong>' . $pending_orders . ' teslimat</strong>
            </div>
        </div>
        
        <div class="alert alert-info">
            <h6><i class="fas fa-calculator me-2"></i>Hesap Özeti</h6>
            <div class="row text-center">
                <div class="col-md-4">
                    <strong>' . $pending_orders . '</strong>
                    <br><small>Ödeme Bekleyen</small>
                </div>
                <div class="col-md-4">
                    <strong>₺' . number_format($net_per_delivery, 2) . '</strong>
                    <br><small>Teslimat Başı</small>
                </div>
                <div class="col-md-4">
                    <strong>₺' . number_format($pending_orders * $net_per_delivery, 2) . '</strong>
                    <br><small>Toplam Hak Edişi</small>
                </div>
            </div>
        </div>';
    
    if ($current_balance != 0) {
        $balance_class = $current_balance > 0 ? 'alert-success' : 'alert-danger';
        $balance_text = $current_balance > 0 ? 'Kurye Alacağı' : 'Kurye Borcu';
        $balance_amount = abs($current_balance);
        
        $html .= '
        <div class="alert ' . $balance_class . '">
            <h6><i class="fas fa-balance-scale me-2"></i>' . $balance_text . '</h6>
            <div class="text-center">
                <h4>₺' . number_format($balance_amount, 2) . '</h4>
            </div>
        </div>';
    } else {
        $html .= '
        <div class="alert alert-secondary">
            <h6><i class="fas fa-check-circle me-2"></i>Hesap Temiz</h6>
            <div class="text-center">
                <h5>₺0.00</h5>
                <small>Ödenmesi gereken tutar bulunmuyor</small>
            </div>
        </div>';
    }
    
    $html .= '
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label"><strong>Ödenecek Tutar</strong></label>
                <div class="input-group">
                    <span class="input-group-text">₺</span>
                    <input type="text" class="form-control fw-bold text-success" 
                           value="' . number_format($current_balance, 2) . '" 
                           id="toplamOdeme" readonly>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Ödediğimiz Tutar</label>
                <div class="input-group">
                    <span class="input-group-text">₺</span>
                    <input type="number" step="0.01" class="form-control" 
                           name="odeme_tutari" id="odemeTutari"
                           value="' . number_format($current_balance, 2) . '"
                           min="0"
                           onchange="window.calculateKuryeBalance()"
                           oninput="window.calculateKuryeBalance()">
                </div>
            </div>
        </div>
        
        <div class="row g-3 mb-3" id="balanceInfo" style="display:none;">
            <div class="col-12">
                <div class="alert alert-warning">
                    <span id="balanceText"></span>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Açıklama</label>
            <textarea class="form-control" name="aciklama" rows="2" 
                      placeholder="Ödeme açıklaması...">' . date('d.m.Y') . ' günü kurye ödemesi</textarea>
        </div>
        
        <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
            <button type="button" class="btn btn-success" onclick="window.processOdeme()">
                <i class="fas fa-hand-holding-usd me-2"></i>
                Ödemeyi Gerçekleştir
            </button>
        </div>
    </form>';
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>