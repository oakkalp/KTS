<?php
/**
 * Kurye Full System - Mekan Tahsilat Bilgisi AJAX
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

// Admin yetkisi kontrol et
requireUserType('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$mekan_id = (int)($input['mekan_id'] ?? 0);

if (!$mekan_id) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz mekan ID']);
    exit;
}

try {
    $db = getDB();
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    
    // Mekan bilgilerini al
    $stmt = $db->query("
        SELECT m.*, u.full_name, u.username 
        FROM mekanlar m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id = ?
    ", [$mekan_id]);
    
    $mekan = $stmt->fetch();
    if (!$mekan) {
        throw new Exception('Mekan bulunamadı');
    }
    
    // Teslimat ücretini al
    $delivery_fee = (float)getSetting('delivery_fee', 5.00);
    
    // Toplam teslim edilen sipariş sayısını al
    $stmt = $db->query("
        SELECT COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_orders
        FROM siparisler 
        WHERE mekan_id = ?
    ", [$mekan_id]);
    
    $stats = $stmt->fetch();
    $completed_orders = (int)($stats['completed_orders'] ?? 0);
    
    // Mevcut bakiye durumunu kontrol et (tek bakiye değeri)
    $stmt = $db->query("
        SELECT bakiye 
        FROM bakiye 
        WHERE user_id = ? AND user_type = 'mekan'
    ", [$mekan['user_id']]);
    
    $bakiye_row = $stmt->fetch();
    $current_balance = 0;
    
    if (!$bakiye_row) {
        // İlk kez bakiye kaydı oluştur
        $db->query("
            INSERT INTO bakiye (user_id, user_type, bakiye) 
            VALUES (?, 'mekan', 0.00)
        ", [$mekan['user_id']]);
    } else {
        $current_balance = (float)$bakiye_row['bakiye'];
    }
    
    // Toplam tahsil edilecek tutar = mevcut bakiye
    $toplam_tahsilat = $current_balance;
    
    // HTML içeriği oluştur
    $html = '
    <form id="tahsilatForm">
        <input type="hidden" name="mekan_id" value="' . $mekan_id . '">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <h6><i class="fas fa-store me-2"></i>' . htmlspecialchars($mekan['mekan_name']) . '</h6>
                <small class="text-muted">@' . htmlspecialchars($mekan['username']) . '</small>
            </div>
            <div class="col-md-6 text-end">
                <small class="text-muted">Toplam Teslimat</small>
                <br><strong>' . $completed_orders . ' paket</strong>
            </div>
        </div>
        
        <div class="alert alert-info">
            <h6><i class="fas fa-calculator me-2"></i>Hesap Özeti</h6>
            <div class="row text-center">
                <div class="col-md-4">
                    <strong>' . $completed_orders . '</strong>
                    <br><small>Toplam Paket</small>
                </div>
                <div class="col-md-4">
                    <strong>₺' . number_format($delivery_fee, 2) . '</strong>
                    <br><small>Paket Ücreti</small>
                </div>
                <div class="col-md-4">
                    <strong>₺' . number_format($completed_orders * $delivery_fee, 2) . '</strong>
                    <br><small>Toplam Hak Edişi</small>
                </div>
            </div>
        </div>';
    
    if ($current_balance != 0) {
        $balance_class = $current_balance > 0 ? 'alert-danger' : 'alert-success';
        $balance_text = $current_balance > 0 ? 'Kalan Borç' : 'Bizim Borcumuz';
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
        <div class="alert alert-success">
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
                <label class="form-label"><strong>Tahsil Edilecek Tutar</strong></label>
                <div class="input-group">
                    <span class="input-group-text">₺</span>
                    <input type="text" class="form-control fw-bold text-primary" 
                           value="' . number_format($current_balance, 2) . '" 
                           id="toplamTahsilat" readonly>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Aldığımız Tutar</label>
                <div class="input-group">
                    <span class="input-group-text">₺</span>
                    <input type="number" step="0.01" class="form-control" 
                           name="tahsil_tutari" id="tahsilTutari"
                           value="' . number_format($current_balance, 2) . '"
                           min="0"
                           onchange="window.calculateBalance()"
                           oninput="window.calculateBalance()">
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
                      placeholder="Tahsilat açıklaması...">' . date('d.m.Y') . ' günü tahsilatı</textarea>
        </div>
        
        <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
            <button type="button" class="btn btn-success" onclick="window.processTahsilat()">
                <i class="fas fa-money-bill me-2"></i>
                Tahsilat Yap
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
