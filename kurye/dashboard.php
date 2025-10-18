<?php
/**
 * Kurye Full System - Kurye Dashboard
 * Kurye yönetim paneli ve konum takip sistemi
 */

require_once '../config/config.php';

// Kurye yetkisi kontrol et
requireUserType('kurye');

// Kurye bilgilerini al
try {
    $db = getDB();
    $user_id = getUserId();
    
    // Kurye bilgileri
    $stmt = $db->query("SELECT k.*, u.full_name, u.phone FROM kuryeler k JOIN users u ON k.user_id = u.id WHERE k.user_id = ?", [$user_id]);
    $kurye = $stmt->fetch();
    
    if (!$kurye) {
        throw new Exception("Kurye bilgileri bulunamadı");
    }
    
    $kurye_id = $kurye['id'];
    
    // Sipariş durumu güncelleme (siparislerim.php ile aynı mantık)
    if ($_POST && isset($_POST['action'])) {
        try {
            $order_id = (int)$_POST['order_id'];
            $action = $_POST['action'];
            
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
                        
                        // Performans puanı hesaplama (1-100 arası)
                        $performance_score = 100;
                        
                        // Teslim alma gecikmesi varsa puan düş
                        if ($order_data['pickup_delay_minutes'] > 0) {
                            $performance_score -= min(20, $order_data['pickup_delay_minutes']);
                        }
                        
                        // Teslimat süresi çok uzunsa puan düş (30 dk üzeri)
                        if ($delivery_duration > 30) {
                            $performance_score -= min(30, ($delivery_duration - 30));
                        }
                        
                        $performance_score = max(1, $performance_score);
                        
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
            header("Location: dashboard.php?success=" . urlencode($success_message ?? ''));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // İstatistikler
    $stats = [];
    
    // Bugünkü siparişler
    $stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE kurye_id = ? AND DATE(created_at) = CURDATE()", [$kurye_id]);
    $stats['today_orders'] = $stmt->fetch()['count'];
    
    // Bekleyen siparişler (kurye için atanmış)
    $stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE kurye_id = ? AND status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')", [$kurye_id]);
    $stats['active_orders'] = $stmt->fetch()['count'];
    
    // Bu ayki kazanç (tahmini - teslimat ücreti + bahşiş)
    $stmt = $db->query("
        SELECT SUM(delivery_fee) as total 
        FROM siparisler 
        WHERE kurye_id = ? AND status = 'delivered' 
        AND MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())", 
        [$kurye_id]
    );
    $gross_monthly = $stmt->fetch()['total'] ?? 0;
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    $commission_monthly = ($gross_monthly * $commission_rate) / 100;
    $stats['monthly_earnings'] = $gross_monthly - $commission_monthly;
    
    // Tamamlanan teslimat sayısı
    $stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE kurye_id = ? AND status = 'delivered'", [$kurye_id]);
    $stats['completed_deliveries'] = $stmt->fetch()['count'];
    
    // Aktif siparişler (detaylı)
    $stmt = $db->query("
        SELECT s.*, m.mekan_name, m.address as mekan_address, m.phone as mekan_phone
        FROM siparisler s 
        LEFT JOIN mekanlar m ON s.mekan_id = m.id 
        WHERE s.kurye_id = ? AND s.status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
        ORDER BY 
            CASE s.status 
                WHEN 'delivering' THEN 1
                WHEN 'picked_up' THEN 2  
                WHEN 'ready' THEN 3
                WHEN 'preparing' THEN 4
                WHEN 'accepted' THEN 5
            END,
            s.created_at ASC", 
        [$kurye_id]
    );
    $active_orders = $stmt->fetchAll();
    
    // Yeni atanabilir siparişler (kurye atanmamış)
    $stmt = $db->query("
        SELECT s.*, m.mekan_name, m.address as mekan_address, m.phone as mekan_phone,
               m.latitude as mekan_lat, m.longitude as mekan_lng
        FROM siparisler s 
        LEFT JOIN mekanlar m ON s.mekan_id = m.id 
        WHERE s.kurye_id IS NULL AND s.status = 'pending'
        ORDER BY s.created_at ASC 
        LIMIT 10
    ");
    $available_orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    writeLog("Kurye dashboard error: " . $e->getMessage(), 'ERROR');
    $stats = [];
    $active_orders = [];
    $available_orders = [];
    $kurye = null;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurye Dashboard - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            border-radius: 10px;
            margin: 5px 0;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .order-card {
            border-left: 4px solid #ffc107;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .order-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .order-card.urgent {
            border-left-color: #dc3545;
        }
        .order-card.express {
            border-left-color: #fd7e14;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .online-status {
            position: relative;
        }
        .online-indicator {
            position: absolute;
            width: 12px;
            height: 12px;
            background: #28a745;
            border: 2px solid white;
            border-radius: 50%;
            top: -2px;
            right: -2px;
            animation: pulse 2s infinite;
        }
        .offline-indicator {
            background: #dc3545;
            animation: none;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        .location-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center text-white mb-4">
                        <h4><i class="fas fa-motorcycle me-2"></i><?= APP_NAME ?></h4>
                        <small>Kurye Panel</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="siparislerim.php">
                                <i class="fas fa-shopping-bag me-2"></i>
                                Aktif Siparişler
                                <?php if ($stats['active_orders'] > 0): ?>
                                    <span class="badge bg-danger ms-2"><?= $stats['active_orders'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="yeni-siparisler.php">
                                <i class="fas fa-bell me-2"></i>
                                Yeni Siparişler
                                <?php if (count($available_orders) > 0): ?>
                                    <span class="badge bg-success ms-2"><?= count($available_orders) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="gecmis.php">
                                <i class="fas fa-history me-2"></i>
                                Teslimat Geçmişi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="kazanclarim.php">
                                <i class="fas fa-money-bill-wave me-2"></i>
                                Kazançlarım
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profil.php">
                                <i class="fas fa-user me-2"></i>
                                Profil
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-white-50">
                    
                    <div class="text-white-50 small px-3">
                        <div class="mb-2 online-status">
                            <i class="fas fa-motorcycle me-2"></i>
                            <?= sanitize($kurye['full_name'] ?? 'Kurye') ?>
                            <div class="online-indicator <?= $kurye['is_online'] ? '' : 'offline-indicator' ?>"></div>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-car me-2"></i>
                            <?= sanitize($kurye['license_plate'] ?? 'Plaka yok') ?>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-clock me-2"></i>
                            <?= date('d.m.Y H:i') ?>
                        </div>
                        
                        <div class="d-grid gap-2 mb-2">
                            <button class="btn btn-sm <?= $kurye['is_online'] ? 'btn-success' : 'btn-outline-light' ?>" 
                                    onclick="toggleOnlineStatus()" 
                                    id="onlineStatusBtn">
                                <i class="fas fa-power-off me-1"></i>
                                <?= $kurye['is_online'] ? 'Çevrimiçi' : 'Çevrimdışı' ?>
                            </button>
                            
                            <?php if ($kurye['is_online'] && !$kurye['is_available']): ?>
                                <button class="btn btn-sm btn-outline-warning" 
                                        onclick="markAvailable()" 
                                        id="availableBtn">
                                    <i class="fas fa-check me-1"></i>
                                    Müsait Ol
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <a href="../logout.php" class="btn btn-outline-light btn-sm w-100">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Çıkış Yap
                        </a>
                    </div>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt me-2 text-warning"></i>
                        Kurye Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-1"></i>
                                Yenile
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Başarı/Hata Mesajları -->
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
                
                <!-- İstatistik Kartları -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-primary me-3">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Bugünkü Siparişler</div>
                                    <div class="h4 mb-0"><?= number_format($stats['today_orders'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-warning me-3">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Aktif Siparişler</div>
                                    <div class="h4 mb-0"><?= number_format($stats['active_orders'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-success me-3">
                                    <i class="fas fa-lira-sign"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Aylık Kazanç</div>
                                    <div class="h4 mb-0"><?= formatMoney($stats['monthly_earnings'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-info me-3">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Toplam Teslimat</div>
                                    <div class="h4 mb-0"><?= number_format($stats['completed_deliveries'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Konum Bilgisi -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card location-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="mb-2">
                                            <i class="fas fa-map-marker-alt me-2"></i>
                                            Mevcut Konum
                                        </h5>
                                        <p class="mb-0" id="locationInfo">
                                            <?php if ($kurye['current_latitude'] && $kurye['current_longitude']): ?>
                                                Enlem: <?= $kurye['current_latitude'] ?>, Boylam: <?= $kurye['current_longitude'] ?>
                                                <br><small>Son güncelleme: <?= formatDate($kurye['last_location_update']) ?></small>
                                            <?php else: ?>
                                                Konum bilgisi bulunamadı. Lütfen konumu güncelleyin.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="d-flex align-items-center">
                                            <div id="locationStatus" class="me-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-sync-alt fa-spin me-1" id="locationIcon"></i>
                                                    <span id="locationText">Konum güncelleniyor...</span>
                                                </small>
                                            </div>
                                            <button class="btn btn-warning btn-sm" onclick="setTestLocation()" title="Manuel test konumu ayarla">
                                                <i class="fas fa-flask me-1"></i>Test Konumu
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <!-- Aktif Siparişler -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    Aktif Siparişlerim
                                    <?php if (count($active_orders) > 0): ?>
                                        <span class="badge bg-warning ms-2"><?= count($active_orders) ?></span>
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($active_orders)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-motorcycle fa-3x mb-3"></i>
                                        <p>Şu anda aktif siparişiniz bulunmuyor.</p>
                                        <p class="small">Yeni siparişler için "Yeni Siparişler" sekmesini kontrol edin.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row g-3">
                                        <?php foreach ($active_orders as $order): ?>
                                            <div class="col-12">
                                                <div class="card order-card <?= $order['priority'] !== 'normal' ? $order['priority'] : '' ?>">
                                                    <div class="card-body">
                                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                                <strong><?= sanitize($order['order_number']) ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?= formatDate($order['created_at']) ?></small>
                                                                <?php if ($order['priority'] !== 'normal'): ?>
                                                                    <br><span class="badge bg-<?= $order['priority'] === 'urgent' ? 'danger' : 'warning' ?> status-badge">
                                                                        <?= $order['priority'] === 'urgent' ? 'ACİL' : 'EKSPRES' ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                            <div class="col-md-2">
                                                                <div class="fw-semibold"><?= sanitize($order['mekan_name']) ?></div>
                                                                <small class="text-muted"><?= sanitize($order['mekan_address']) ?></small>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="fw-semibold"><?= sanitize($order['customer_name']) ?></div>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i><?= formatPhone($order['customer_phone']) ?>
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?= sanitize($order['delivery_address']) ?>
                                                </small>
                                                <br>
                                                <?php 
                                                // Mekan varış saati hesapla (sipariş saati + 15 dk)
                                                $order_time = new DateTime($order['created_at']);
                                                $arrival_time = clone $order_time;
                                                $arrival_time->add(new DateInterval('PT15M')); // 15 dakika ekle
                                                
                                                // Şu anki zaman
                                                $now = new DateTime();
                                                
                                                // Kalan süre hesapla
                                                $time_diff = $arrival_time->getTimestamp() - $now->getTimestamp();
                                                $minutes_left = round($time_diff / 60);
                                                
                                                if ($order['status'] === 'ready' || $order['status'] === 'accepted' || $order['status'] === 'preparing'): ?>
                                                    <small class="text-warning">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <strong>Mekanda: <?= $arrival_time->format('H:i') ?></strong>
                                                        <?php if ($minutes_left > 0): ?>
                                                            (<?= $minutes_left ?> dk kaldı)
                                                        <?php elseif ($minutes_left < 0): ?>
                                                            <span class="text-danger">(<?= abs($minutes_left) ?> dk geç)</span>
                                                        <?php else: ?>
                                                            (Şimdi!)
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="fw-bold text-success"><?= formatMoney($order['total_amount']) ?></div>
                                                                <small class="text-muted">Teslimat: <?= formatMoney($order['delivery_fee']) ?></small>
                                                            </div>
                                                            <div class="col-md-1 text-center">
                                                                <?php
                                                                $status_colors = [
                                                                    'accepted' => 'info',
                                                                    'preparing' => 'primary',
                                                                    'ready' => 'secondary',
                                                                    'picked_up' => 'dark',
                                                                    'delivering' => 'warning'
                                                                ];
                                                                $status_texts = [
                                                                    'accepted' => 'Kabul Edildi',
                                                                    'preparing' => 'Hazırlanıyor',
                                                                    'ready' => 'Hazır',
                                                                    'picked_up' => 'Alındı',
                                                                    'delivering' => 'Yolda'
                                                                ];
                                                                ?>
                                                                <span class="badge bg-<?= $status_colors[$order['status']] ?? 'secondary' ?> status-badge">
                                                                    <?= $status_texts[$order['status']] ?? $order['status'] ?>
                                                                </span>
                                                            </div>
                                                            <div class="col-md-1 text-end">
                                                                <div class="btn-group-vertical btn-group-sm d-grid gap-1">
                                                                    <a href="siparis-detay.php?id=<?= $order['id'] ?>" class="btn btn-outline-info btn-sm">
                                                                        <i class="fas fa-eye me-1"></i>Detay
                                                                    </a>
                                                                    <?php if (in_array($order['status'], ['accepted', 'preparing', 'ready'])): ?>
                                                                        <button class="btn btn-warning btn-sm" onclick="updateStatus(<?= $order['id'] ?>, 'pickup')">
                                                                            <i class="fas fa-hand-paper me-1"></i>Siparişi Al
                                                                        </button>
                                                                    <?php elseif ($order['status'] === 'picked_up'): ?>
                                                                        <button class="btn btn-success btn-sm" onclick="updateStatus(<?= $order['id'] ?>, 'complete')">
                                                                            <i class="fas fa-check-circle me-1"></i>Teslim Edildi
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Yeni Siparişler -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bell me-2"></i>
                                    Yeni Siparişler
                                    <?php if (count($available_orders) > 0): ?>
                                        <span class="badge bg-success ms-2"><?= count($available_orders) ?></span>
                                    <?php endif; ?>
                                </h5>
                                <a href="yeni-siparisler.php" class="btn btn-sm btn-warning">
                                    Tümü
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($available_orders)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-bell fa-2x mb-2"></i>
                                        <p class="mb-0">Yeni sipariş bulunmuyor.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach (array_slice($available_orders, 0, 5) as $order): ?>
                                            <div class="list-group-item border-0 px-0 py-2">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="fw-semibold"><?= sanitize($order['order_number']) ?></div>
                                                        
                                                        <!-- Mekan Bilgileri -->
                                                        <small class="text-muted">
                                                            <i class="fas fa-store me-1"></i><strong><?= sanitize($order['mekan_name']) ?></strong>
                                                            <br><i class="fas fa-map-marker-alt me-1"></i><?= sanitize($order['mekan_address']) ?>
                                                        </small>
                                                        
                                                        <!-- Müşteri Bilgileri -->
                                                        <small class="text-primary">
                                                            <br><i class="fas fa-user me-1"></i><?= sanitize($order['customer_name']) ?>
                                                            <br><i class="fas fa-map-marker-alt me-1"></i><?= sanitize($order['delivery_address']) ?>
                                                        </small>
                                                        
                                                        <!-- Mekan Varış Saati -->
                                                        <?php 
                                                        // Sipariş saati + 15 dakika = Mekanda olması gereken saat
                                                        $order_time = new DateTime($order['created_at']);
                                                        $arrival_time = clone $order_time;
                                                        $arrival_time->add(new DateInterval('PT15M')); // 15 dakika ekle
                                                        
                                                        // Şu anki zaman
                                                        $now = new DateTime();
                                                        
                                                        // Kalan süre hesapla
                                                        $time_diff = $arrival_time->getTimestamp() - $now->getTimestamp();
                                                        $minutes_left = round($time_diff / 60);
                                                        ?>
                                                        <small class="text-warning">
                                                            <br><i class="fas fa-clock me-1"></i><strong>Mekanda: <?= $arrival_time->format('H:i') ?></strong>
                                                            <?php if ($minutes_left > 0): ?>
                                                                (<?= $minutes_left ?> dk kaldı)
                                                            <?php elseif ($minutes_left < 0): ?>
                                                                <span class="text-danger">(<?= abs($minutes_left) ?> dk geç)</span>
                                                            <?php else: ?>
                                                                (Şimdi!)
                                                            <?php endif; ?>
                                                        </small>
                                                        
                                                        <div class="mt-1">
                                                            <span class="badge bg-success status-badge"><?= formatMoney($order['total_amount']) ?></span>
                                                            <?php if ($order['mekan_lat'] && $order['mekan_lng'] && $kurye['current_latitude'] && $kurye['current_longitude']): ?>
                                                                <?php 
                                                                $distance = calculateDistance(
                                                                    $kurye['current_latitude'], 
                                                                    $kurye['current_longitude'],
                                                                    $order['mekan_lat'], 
                                                                    $order['mekan_lng']
                                                                );
                                                                ?>
                                                                <small class="text-muted ms-2"><?= number_format($distance, 1) ?> km</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="acceptOrder(<?= $order['id'] ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentLocation = {
            lat: <?= $kurye['current_latitude'] ?? 'null' ?>,
            lng: <?= $kurye['current_longitude'] ?? 'null' ?>
        };
        
        let isOnline = <?= $kurye['is_online'] ? 'true' : 'false' ?>;
        
        // Auth token yönetimi (basitleştirilmiş - production'da localStorage kullanın)
        function getAuthToken() {
            // Geçici olarak session tabanlı auth (gerçek projede JWT token kullanın)
            return 'session_token_placeholder';
        }
        
        // Otomatik konum takip sistemi
        function getCurrentLocation(isAutomatic = false) {
            if (!isAutomatic) {
                console.log('Manuel konum güncellemesi başlatıldı');
            }
            
            if (navigator.geolocation) {
                // UI güncelleme
                updateLocationStatus('updating', 'Konum güncelleniyor...');
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        const accuracy = position.coords.accuracy;
                        
                        console.log(`Konum güncellendi: ${lat}, ${lng} (Doğruluk: ${accuracy}m)`);
                        updateLocationOnServer(lat, lng, accuracy);
                        
                        // Başarılı güncelleme
                        updateLocationStatus('success', 'Konum güncellendi');
                    },
                    function(error) {
                        console.error('Konum alınamadı:', error);
                        
                        if (error.code === error.PERMISSION_DENIED) {
                            // Konum izni reddedildi flag'ini set et
                            window.locationPermissionDenied = true;
                            
                            if (isAutomatic) {
                                // Otomatik modda sessizce test konumuna geç
                                console.log('Otomatik modda konum izni reddedildi, test konumuna geçiliyor...');
                                useTestLocationAutomatically();
                            } else {
                                // Manuel modda kullanıcıya sor
                                updateLocationStatus('error', 'Konum izni reddedildi');
                                if (confirm('Konum izni reddedildi.\n\nTest konumu kullanmak ister misiniz?')) {
                                    setTestLocation();
                                }
                            }
                        } else {
                            updateLocationStatus('error', 'Konum alınamadı');
                        }
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 30000 // 30 saniye cache
                    }
                );
            } else {
                updateLocationStatus('error', 'Konum desteklenmiyor');
            }
        }
        
        // Konum durumu UI güncellemesi
        function updateLocationStatus(status, message) {
            const icon = document.getElementById('locationIcon');
            const text = document.getElementById('locationText');
            
            if (!icon || !text) return;
            
            switch(status) {
                case 'updating':
                    icon.className = 'fas fa-sync-alt fa-spin me-1';
                    text.className = 'text-muted';
                    break;
                case 'success':
                    icon.className = 'fas fa-check-circle me-1';
                    text.className = 'text-success';
                    // 3 saniye sonra normal duruma dön
                    setTimeout(() => {
                        icon.className = 'fas fa-map-marker-alt me-1';
                        text.className = 'text-muted';
                        text.textContent = 'Konum aktif';
                    }, 3000);
                    break;
                case 'error':
                    icon.className = 'fas fa-exclamation-triangle me-1';
                    text.className = 'text-warning';
                    break;
            }
            
            text.textContent = message;
        }
        
        // Test için manuel konum girişi
        function setTestLocation() {
            console.log('Test konumu ayarlanıyor...');
            updateLocationStatus('updating', 'Test konumu ayarlanıyor...');
            
            // İzmir Ödemiş test koordinatları
            const lat = 38.2258;
            const lng = 27.9700;
            const accuracy = 50;
            
            console.log(`Test koordinatları: ${lat}, ${lng}`);
            updateLocationOnServer(lat, lng, accuracy);
            
            // UI güncelleme
            updateLocationStatus('success', 'Test konumu ayarlandı');
        }
        
        // Konum izni yardımı
        function showLocationHelp() {
            const helpText = `🗺️ KONUM İZNİ YARDIMI\n\n` +
                `📱 MOBIL TARAYICIDA:\n` +
                `1. Adres çubuğunun solundaki kilit/ünlem ikonuna dokunun\n` +
                `2. "Konum" veya "Location" ayarını bulun\n` +
                `3. "İzin Ver" veya "Allow" seçeneğini seçin\n` +
                `4. Sayfayı yenileyin (F5 veya aşağı çekme)\n\n` +
                `💻 MASAÜSTÜ TARAYICIDA:\n` +
                `1. Adres çubuğunun solundaki kilit ikonuna tıklayın\n` +
                `2. "Konum" iznini "İzin Ver" olarak değiştirin\n` +
                `3. Sayfayı yenileyin (F5)\n\n` +
                `🧪 ALTERNATIF:\n` +
                `"Test" butonunu kullanarak test konumu ayarlayabilirsiniz.\n` +
                `Bu konum izni gerektirmez ve hemen çalışır.`;
            
            alert(helpText);
        }
        
        // API Test fonksiyonu
        function testLocationAPI() {
            console.log('API Test başlıyor...');
            fetch('../api/kurye/test-location.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({test: true})
            })
            .then(response => {
                console.log('Test API Response Status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Test API Response:', data);
            })
            .catch(error => {
                console.error('Test API Error:', error);
            });
        }
        
        // Sunucuda konum güncelle
        function updateLocationOnServer(lat, lng, accuracy) {
            fetch('../api/kurye/session-update-location.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    latitude: lat,
                    longitude: lng,
                    accuracy: accuracy
                })
            })
            .then(response => {
                console.log('API Response Status:', response.status);
                
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('API Error Response (Text):', text);
                        try {
                            const errorData = JSON.parse(text);
                            console.error('API Error Response (JSON):', errorData);
                            throw new Error(errorData.error?.message || 'API Error: ' + response.status);
                        } catch (parseError) {
                            throw new Error('API Error: ' + response.status + ' - ' + text);
                        }
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('API Success Response:', data);
                if (data.success) {
                    currentLocation = { lat: lat, lng: lng };
                    document.getElementById('locationInfo').innerHTML = 
                        `Enlem: ${lat.toFixed(6)}, Boylam: ${lng.toFixed(6)}<br><small>Son güncelleme: Az önce</small>`;
                } else {
                    console.error('Konum güncellenemedi:', data.message);
                    if (data.error && data.error.debug) {
                        console.error('Debug Info:', data.error.debug);
                    }
                }
            })
            .catch(error => {
                console.error('Konum güncelleme hatası:', error.message);
                console.error('Full Error:', error);
            });
        }
        
        // Online/Offline durumu değiştir
        function toggleOnlineStatus() {
            const newStatus = !isOnline;
            
            fetch('../api/kurye/session-toggle-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    is_online: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    isOnline = newStatus;
                    updateOnlineStatusUI();
                    
                    if (isOnline) {
                        // HTTP'de konum alamayacağımızı biliyoruz, direkt test konumu kullan
                        if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                            console.log('HTTP protokolü - otomatik test konumu ayarlanıyor');
                            setTestLocation();
                        } else {
                            getCurrentLocation(); // HTTPS'de gerçek konum al
                        }
                        startLocationTracking(); // Konum takibini başlat
                    } else {
                        stopLocationTracking(); // Konum takibini durdur
                    }
                } else {
                    alert('Durum değiştirilemedi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Durum değiştirme hatası:', error);
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            });
        }
        
        // UI'da online durumunu güncelle
        function updateOnlineStatusUI() {
            const btn = document.getElementById('onlineStatusBtn');
            const indicator = document.querySelector('.online-indicator');
            
            if (isOnline) {
                btn.className = 'btn btn-sm btn-success';
                btn.innerHTML = '<i class="fas fa-power-off me-1"></i>Çevrimiçi';
                indicator.classList.remove('offline-indicator');
            } else {
                btn.className = 'btn btn-sm btn-outline-light';
                btn.innerHTML = '<i class="fas fa-power-off me-1"></i>Çevrimdışı';
                indicator.classList.add('offline-indicator');
            }
        }
        
        // Sipariş kabul et
        function acceptOrder(orderId) {
            if (confirm('Bu siparişi kabul etmek istediğinizden emin misiniz?')) {
                fetch('../api/kurye/accept-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: orderId
                    })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers.get('content-type'));
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.error('Response text:', text);
                            throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        alert('Sipariş başarıyla kabul edildi!');
                        location.reload();
                    } else {
                        alert('Sipariş kabul edilemedi: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Sipariş kabul etme hatası:', error);
                    alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                });
            }
        }
        
        // Müsait olma
        function markAvailable() {
            fetch('../api/kurye/mark-available.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Müsait olma hatası:', error);
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            });
        }
        
        // Sipariş detaylarını göster
        function showOrderDetails(orderId) {
            window.location.href = 'siparis-detay.php?id=' + orderId;
        }
        
        let locationTracker = null;
        
        // Konum takibini başlat (online iken)
        function startLocationTracking() {
            if (locationTracker) return; // Zaten çalışıyor
            
            console.log('Otomatik konum takibi başlatıldı (30 saniyede bir)');
            
            // İlk konum güncellemesi - konum izni kontrolü
            checkLocationPermissionAndStart();
            
            // 30 saniyede bir otomatik güncelleme
            locationTracker = setInterval(() => {
                if (isOnline) {
                    console.log('Otomatik konum güncellemesi...');
                    updateLocationAutomatically();
                }
            }, 30000); // 30 saniye
        }
        
        // Konum izni kontrolü ve başlatma
        function checkLocationPermissionAndStart() {
            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({name: 'geolocation'}).then(function(result) {
                    console.log('Konum izni durumu:', result.state);
                    
                    if (result.state === 'granted') {
                        getCurrentLocation(true);
                    } else if (result.state === 'denied') {
                        console.log('Konum izni reddedildi, test konumu kullanılıyor');
                        useTestLocationAutomatically();
                    } else {
                        // İzin durumu belirsiz, bir kez dene
                        getCurrentLocation(true);
                    }
                });
            } else {
                // Permissions API desteklenmiyor, direkt dene
                getCurrentLocation(true);
            }
        }
        
        // Otomatik konum güncellemesi
        function updateLocationAutomatically() {
            // Eğer daha önce konum izni reddedildiyse test konumu kullan
            if (window.locationPermissionDenied) {
                useTestLocationAutomatically();
            } else {
                getCurrentLocation(true);
            }
        }
        
        // Otomatik test konumu kullanımı
        function useTestLocationAutomatically() {
            console.log('Otomatik test konumu güncelleniyor...');
            updateLocationStatus('updating', 'Test konumu güncelleniyor...');
            
            const lat = 38.2258;
            const lng = 27.9700;
            const accuracy = 50;
            
            updateLocationOnServer(lat, lng, accuracy);
            updateLocationStatus('success', 'Test konumu güncellendi');
        }
        
        // Konum takibini durdur
        function stopLocationTracking() {
            if (locationTracker) {
                clearInterval(locationTracker);
                locationTracker = null;
            }
        }
        
        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // Online ise konum takibini başlat
            if (isOnline) {
                startLocationTracking();
            }
            
            // Sayfa kapanırken offline yap (isteğe bağlı)
            window.addEventListener('beforeunload', function() {
                if (isOnline) {
                    // Sync request ile hızlıca offline yap
                    navigator.sendBeacon('../api/kurye/toggle-status.php', 
                        JSON.stringify({ is_online: false }));
                }
            });
        });
        
        // Sipariş durumu güncelleme fonksiyonu (siparislerim.php ile aynı)
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
