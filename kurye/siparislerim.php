<?php
/**
 * Kurye Full System - Kurye Aktif Siparişler
 */

require_once '../config/config.php';
requireUserType('kurye');

// Kurye ID'sini al
$kurye_id = getKuryeId();

// Aktif siparişleri getir
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT s.*, 
               u.username as mekan_username,
               u.full_name as mekan_name,
               u.phone as mekan_phone
        FROM siparisler s 
        LEFT JOIN users u ON s.mekan_id = u.id
        WHERE s.kurye_id = ? AND s.status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
        ORDER BY s.created_at DESC
    ", [$kurye_id]);
    
    $active_orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Siparişler yüklenemedi: ' . $e->getMessage();
    $active_orders = [];
}

// Sipariş durumu güncelleme
if ($_POST && isset($_POST['action'])) {
    try {
        $db = getDB();
        $order_id = (int)$_POST['order_id'];
        $action = clean($_POST['action']);
        
        // Siparişin kurye'ye ait olduğunu kontrol et
        $stmt = $db->query("SELECT id FROM siparisler WHERE id = ? AND kurye_id = ?", [$order_id, $kurye_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Bu siparişe erişim yetkiniz yok');
        }
        
        $new_status = '';
        $message = '';
        
        switch ($action) {
            case 'pickup':
                $new_status = 'picked_up';
                $db->query("UPDATE siparisler SET picked_up_at = NOW() WHERE id = ?", [$order_id]);
                
                // Performans hesaplama - gecikme süresi
                $stmt = $db->query("SELECT expected_pickup_time FROM siparisler WHERE id = ?", [$order_id]);
                $expected_time = $stmt->fetch()['expected_pickup_time'] ?? null;
                
                if ($expected_time) {
                    $expected_timestamp = strtotime($expected_time);
                    $actual_timestamp = time();
                    $delay_minutes = max(0, ($actual_timestamp - $expected_timestamp) / 60);
                    
                    $db->query("UPDATE siparisler SET pickup_delay_minutes = ? WHERE id = ?", [$delay_minutes, $order_id]);
                }
                
                $message = 'Sipariş alındı - Teslimat başlatıldı';
                break;
            case 'complete':
                $new_status = 'delivered';
                $db->query("UPDATE siparisler SET delivered_at = NOW() WHERE id = ?", [$order_id]);
                
                // Performans puanı hesaplama
                $stmt = $db->query("SELECT picked_up_at, pickup_delay_minutes FROM siparisler WHERE id = ?", [$order_id]);
                $order_data = $stmt->fetch();
                
                if ($order_data && $order_data['picked_up_at']) {
                    $pickup_time = strtotime($order_data['picked_up_at']);
                    $delivery_time = time();
                    $delivery_duration = ($delivery_time - $pickup_time) / 60; // dakika
                    
                    // Sistem ayarlarından maksimum teslimat süresini al
                    $max_delivery_time = (float)getSetting('max_delivery_time', 25);
                    $pickup_delay = (float)($order_data['pickup_delay_minutes'] ?? 0);
                    
                    // Performans puanı hesaplama (0-10 arası)
                    $performance_score = 10.0;
                    
                    // Alım gecikmesi cezası
                    if ($pickup_delay > 0) {
                        $performance_score -= min(3.0, $pickup_delay * 0.1);
                    }
                    
                    // Teslimat süresi cezası
                    if ($delivery_duration > $max_delivery_time) {
                        $overtime = $delivery_duration - $max_delivery_time;
                        $performance_score -= min(4.0, $overtime * 0.1);
                    }
                    
                    $performance_score = max(0.0, $performance_score);
                    
                    $db->query("UPDATE siparisler SET delivery_performance_score = ? WHERE id = ?", [$performance_score, $order_id]);
                }
                
                // Kurye'nin müsaitlik durumunu güncelle (aktif sipariş sayısına göre)
                $stmt = $db->query("SELECT COUNT(id) as active_orders FROM siparisler WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up')", [$kurye_id]);
                $active_count = $stmt->fetch()['active_orders'] ?? 0;
                
                $max_orders = (int)getSetting('max_orders_per_courier', 5);
                $is_available = ($active_count < $max_orders) ? 1 : 0;
                $db->query("UPDATE kuryeler SET is_available = ? WHERE id = ?", [$is_available, $kurye_id]);
                $message = 'Sipariş teslim edildi';
                break;
            case 'cancel':
                $new_status = 'cancelled';
                $db->query("UPDATE siparisler SET cancelled_at = NOW() WHERE id = ?", [$order_id]);
                // Kurye'nin müsaitlik durumunu güncelle (aktif sipariş sayısına göre)
                $stmt = $db->query("SELECT COUNT(id) as active_orders FROM siparisler WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up')", [$kurye_id]);
                $active_count = $stmt->fetch()['active_orders'] ?? 0;
                
                $max_orders = (int)getSetting('max_orders_per_courier', 5);
                $is_available = ($active_count < $max_orders) ? 1 : 0;
                $db->query("UPDATE kuryeler SET is_available = ? WHERE id = ?", [$is_available, $kurye_id]);
                $message = 'Sipariş iptal edildi';
                break;
        }
        
        if ($new_status) {
            $db->query("UPDATE siparisler SET status = ? WHERE id = ?", [$new_status, $order_id]);
            $success_message = $message;
        }
        
        // Sayfayı yenile
        header("Location: siparislerim.php?success=" . urlencode($success_message ?? ''));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siparişlerim - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-card {
            border-left: 4px solid #17a2b8;
            transition: all 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-accepted { border-left-color: #17a2b8; }
        .status-preparing { border-left-color: #6f42c1; }
        .status-ready { border-left-color: #6c757d; }
        .status-picked_up { border-left-color: #fd7e14; }
        .status-delivering { border-left-color: #20c997; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tasks me-2 text-primary"></i>
                        Aktif Siparişlerim
                    </h1>
                    <button class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>
                        Yenile
                    </button>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= sanitize($_GET['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= sanitize($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row g-3">
                    <?php if (empty($active_orders)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                    <h5>Aktif sipariş bulunmuyor</h5>
                                    <p class="text-muted">Yeni siparişler için bekleyin veya "Yeni Siparişler" sayfasını kontrol edin.</p>
                                    <a href="yeni-siparisler.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>
                                        Yeni Siparişler
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_orders as $order): ?>
                            <div class="col-lg-6">
                                <div class="card order-card status-<?= $order['status'] ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="card-title mb-1">
                                                    <strong><?= sanitize($order['order_number']) ?></strong>
                                                    <?php if ($order['priority'] !== 'normal'): ?>
                                                        <span class="badge bg-<?= $order['priority'] === 'urgent' ? 'danger' : 'warning' ?> ms-2">
                                                            <?= $order['priority'] === 'urgent' ? 'ACİL' : 'EKSPRES' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?= formatDate($order['created_at']) ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="h5 text-success mb-0"><?= formatMoney($order['total_amount']) ?></div>
                                                <small class="text-muted">Teslimat: <?= formatMoney($order['delivery_fee']) ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Mekan:</strong><br>
                                                <?= sanitize($order['mekan_name'] ?? $order['mekan_username'] ?? 'Belirtilmemiş') ?><br>
                                                <?php if ($order['mekan_phone']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <a href="tel:<?= $order['mekan_phone'] ?>"><?= htmlspecialchars($order['mekan_phone']) ?></a>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Müşteri:</strong><br>
                                                <?= sanitize($order['customer_name'] ?? 'Belirtilmemiş') ?><br>
                                                <?php if ($order['customer_phone']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <a href="tel:<?= $order['customer_phone'] ?>"><?= htmlspecialchars($order['customer_phone']) ?></a>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <strong>Teslimat Adresi:</strong><br>
                                            <small>
                                                <?php if (!empty($order['delivery_address'])): ?>
                                                    <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                                                    <?= sanitize($order['delivery_address']) ?>
                                                    <br>
                                                    <a href="https://maps.google.com/maps?q=<?= urlencode($order['delivery_address']) ?>" 
                                                       target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                                        <i class="fas fa-map me-1"></i>Haritada Aç
                                                    </a>
                                                <?php else: ?>
                                                    Belirtilmemiş
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        
                                        <?php if (!empty($order['preparation_time'])): ?>
                                            <div class="mb-3">
                                                <strong>Hazırlık Süresi:</strong><br>
                                                <small class="text-warning">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= $order['preparation_time'] ?> dakika
                                                    <?php if ($order['status'] === 'accepted'): ?>
                                                        <br><em>Bu süre sonunda mekana gitmeniz önerilir.</em>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($order['notes'])): ?>
                                            <div class="mb-3">
                                                <strong>Notlar:</strong><br>
                                                <small class="text-muted"><?= sanitize($order['notes']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php
                                                $status_colors = [
                                                    'accepted' => 'info',
                                                    'preparing' => 'primary',
                                                    'ready' => 'secondary',
                                                    'picked_up' => 'warning',
                                                    'delivering' => 'success'
                                                ];
                                                $status_texts = [
                                                    'accepted' => 'Kabul Edildi',
                                                    'preparing' => 'Hazırlanıyor',
                                                    'ready' => 'Hazır',
                                                    'picked_up' => 'Alındı',
                                                    'delivering' => 'Teslim Ediliyor'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $status_colors[$order['status']] ?? 'secondary' ?> px-3 py-2">
                                                    <?= $status_texts[$order['status']] ?? $order['status'] ?>
                                                </span>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <a href="siparis-detay.php?id=<?= $order['id'] ?>" class="btn btn-outline-info">
                                                    <i class="fas fa-eye me-1"></i>
                                                    Detay
                                                </a>
                                                <?php if (in_array($order['status'], ['accepted', 'preparing', 'ready'])): ?>
                                                    <button class="btn btn-warning" onclick="updateStatus(<?= $order['id'] ?>, 'pickup')">
                                                        <i class="fas fa-hand-paper me-1"></i>
                                                        Siparişi Al
                                                    </button>
                                                <?php elseif ($order['status'] === 'picked_up'): ?>
                                                    <button class="btn btn-success" onclick="updateStatus(<?= $order['id'] ?>, 'complete')">
                                                        <i class="fas fa-check-circle me-1"></i>
                                                        Teslim Edildi
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (!in_array($order['status'], ['delivered', 'cancelled'])): ?>
                                                    <button class="btn btn-outline-danger" onclick="updateStatus(<?= $order['id'] ?>, 'cancel')">
                                                        <i class="fas fa-times me-1"></i>
                                                        İptal
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStatus(orderId, action) {
            const actionTexts = {
                'pickup': 'almak',
                'complete': 'teslim edildi olarak işaretlemek',
                'cancel': 'iptal etmek'
            };
            
            if (confirm(`Bu siparişi ${actionTexts[action]} istediğinizden emin misiniz?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="${action}">
                    <input type="hidden" name="order_id" value="${orderId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
