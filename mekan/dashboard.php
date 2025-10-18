<?php
/**
 * Kurye Full System - Mekan Dashboard
 * Restoran/Mağaza yönetim paneli
 */

require_once '../config/config.php';

// Mekan yetkisi kontrol et
requireUserType('mekan');

// Mekan bilgilerini al
try {
    $db = getDB();
    $user_id = getUserId();
    
    // Mekan bilgileri
    $stmt = $db->query("SELECT * FROM mekanlar WHERE user_id = ?", [$user_id]);
    $mekan = $stmt->fetch();
    
    if (!$mekan) {
        throw new Exception("Mekan bilgileri bulunamadı");
    }
    
    $mekan_id = $mekan['id'];
    
    // İstatistikler
    $stats = [];
    
    // Bugünkü siparişler
    $stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE mekan_id = ? AND DATE(created_at) = CURDATE()", [$mekan_id]);
    $stats['today_orders'] = $stmt->fetch()['count'];
    
    // Bekleyen siparişler
    $stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE mekan_id = ? AND status IN ('pending', 'accepted')", [$mekan_id]);
    $stats['pending_orders'] = $stmt->fetch()['count'];
    
    // Bu ayki gelir
    $stmt = $db->query("
        SELECT SUM(total_amount) as total 
        FROM siparisler 
        WHERE mekan_id = ? AND status = 'delivered' 
        AND MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())", 
        [$mekan_id]
    );
    $stats['monthly_revenue'] = $stmt->fetch()['total'] ?? 0;
    
    // Ortalama teslimat süresi (dakika)
    $stmt = $db->query("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) as avg_time 
        FROM siparisler 
        WHERE mekan_id = ? AND status = 'delivered' 
        AND delivered_at IS NOT NULL
        AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", 
        [$mekan_id]
    );
    $stats['avg_delivery_time'] = round($stmt->fetch()['avg_time'] ?? 0);
    
    // Son siparişler
    $stmt = $db->query("
        SELECT s.*, k.user_id as kurye_user_id, u_kurye.full_name as kurye_name, u_kurye.phone as kurye_phone
        FROM siparisler s 
        LEFT JOIN kuryeler k ON s.kurye_id = k.id 
        LEFT JOIN users u_kurye ON k.user_id = u_kurye.id 
        WHERE s.mekan_id = ?
        ORDER BY s.created_at DESC 
        LIMIT 10", 
        [$mekan_id]
    );
    $recent_orders = $stmt->fetchAll();
    
    // Aktif kuryeler (bu mekana sipariş alabilecek)
    $stmt = $db->query("
        SELECT k.*, u.full_name, u.phone 
        FROM kuryeler k 
        JOIN users u ON k.user_id = u.id 
        WHERE k.is_online = 1 AND k.is_available = 1 AND u.status = 'active'
    ");
    $available_couriers = $stmt->fetchAll();
    
} catch (Exception $e) {
    writeLog("Mekan dashboard error: " . $e->getMessage(), 'ERROR');
    $stats = [];
    $recent_orders = [];
    $available_couriers = [];
    $mekan = null;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mekan Dashboard - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
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
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
        }
        .order-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .courier-online {
            position: relative;
        }
        .courier-online::before {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background: #28a745;
            border: 2px solid white;
            border-radius: 50%;
            top: -2px;
            right: -2px;
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
                        <h4><i class="fas fa-store me-2"></i><?= APP_NAME ?></h4>
                        <small>Mekan Panel</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="siparisler.php">
                                <i class="fas fa-shopping-bag me-2"></i>
                                Siparişler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="yeni-siparis.php">
                                <i class="fas fa-plus me-2"></i>
                                Yeni Sipariş
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="kuryeler.php">
                                <i class="fas fa-motorcycle me-2"></i>
                                Kuryeler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="raporlar.php">
                                <i class="fas fa-chart-bar me-2"></i>
                                Raporlar
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
                        <div class="mb-2">
                            <i class="fas fa-store me-2"></i>
                            <?= sanitize($mekan['mekan_name'] ?? 'Mekan') ?>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-user me-2"></i>
                            <?= sanitize($_SESSION['full_name']) ?>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-clock me-2"></i>
                            <?= date('d.m.Y H:i') ?>
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
                        <i class="fas fa-tachometer-alt me-2 text-success"></i>
                        Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-success" onclick="window.location.href='yeni-siparis.php'">
                                <i class="fas fa-plus me-1"></i>
                                Yeni Sipariş
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-1"></i>
                                Yenile
                            </button>
                        </div>
                    </div>
                </div>
                
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
                                    <div class="text-muted small">Bekleyen Siparişler</div>
                                    <div class="h4 mb-0"><?= number_format($stats['pending_orders'] ?? 0) ?></div>
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
                                    <div class="text-muted small">Aylık Gelir</div>
                                    <div class="h4 mb-0"><?= formatMoney($stats['monthly_revenue'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-info me-3">
                                    <i class="fas fa-stopwatch"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Ort. Teslimat Süresi</div>
                                    <div class="h4 mb-0"><?= $stats['avg_delivery_time'] ?? 0 ?> dk</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <!-- Son Siparişler -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    Son Siparişler
                                </h5>
                                <a href="siparisler.php" class="btn btn-sm btn-success">
                                    Tümünü Gör
                                    <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_orders)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>Henüz sipariş bulunmuyor.</p>
                                        <a href="yeni-siparis.php" class="btn btn-success">
                                            <i class="fas fa-plus me-2"></i>
                                            İlk Siparişi Oluştur
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="row g-3">
                                        <?php foreach (array_slice($recent_orders, 0, 6) as $order): ?>
                                            <div class="col-12">
                                                <div class="card order-card">
                                                    <div class="card-body">
                                                        <div class="row align-items-center">
                                                            <div class="col-md-3">
                                                                <strong><?= sanitize($order['order_number']) ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?= formatDate($order['created_at']) ?></small>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="fw-semibold"><?= sanitize($order['customer_name']) ?></div>
                                                                <small class="text-muted"><?= formatPhone($order['customer_phone']) ?></small>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <?php if ($order['kurye_name']): ?>
                                                                    <div class="fw-semibold"><?= sanitize($order['kurye_name']) ?></div>
                                                                    <small class="text-muted"><?= formatPhone($order['kurye_phone']) ?></small>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Kurye atanmadı</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="fw-bold text-success"><?= formatMoney($order['total_amount']) ?></div>
                                                            </div>
                                                            <div class="col-md-2 text-end">
                                                                <?php
                                                                $status_colors = [
                                                                    'pending' => 'warning',
                                                                    'accepted' => 'info',
                                                                    'preparing' => 'primary',
                                                                    'ready' => 'secondary',
                                                                    'picked_up' => 'dark',
                                                                    'delivering' => 'primary',
                                                                    'delivered' => 'success',
                                                                    'cancelled' => 'danger'
                                                                ];
                                                                $status_texts = [
                                                                    'pending' => 'Bekliyor',
                                                                    'accepted' => 'Kabul Edildi',
                                                                    'preparing' => 'Hazırlanıyor',
                                                                    'ready' => 'Hazır',
                                                                    'picked_up' => 'Alındı',
                                                                    'delivering' => 'Yolda',
                                                                    'delivered' => 'Teslim Edildi',
                                                                    'cancelled' => 'İptal'
                                                                ];
                                                                ?>
                                                                <span class="badge bg-<?= $status_colors[$order['status']] ?? 'secondary' ?> status-badge">
                                                                    <?= $status_texts[$order['status']] ?? $order['status'] ?>
                                                                </span>
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
                    
                    <!-- Aktif Kuryeler -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-motorcycle me-2"></i>
                                    Aktif Kuryeler
                                    <span class="badge bg-success ms-2"><?= count($available_couriers) ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($available_couriers)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-motorcycle fa-2x mb-2"></i>
                                        <p class="mb-0">Şu anda aktif kurye bulunmuyor.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach (array_slice($available_couriers, 0, 8) as $courier): ?>
                                            <div class="list-group-item border-0 px-0">
                                                <div class="d-flex align-items-center">
                                                    <div class="courier-online me-3">
                                                        <i class="fas fa-user-circle fa-2x text-muted"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="fw-semibold"><?= sanitize($courier['full_name']) ?></div>
                                                        <small class="text-muted">
                                                            <?= formatPhone($courier['phone']) ?>
                                                            <?php if ($courier['license_plate']): ?>
                                                                | <?= sanitize($courier['license_plate']) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-<?= ucfirst($courier['vehicle_type']) === 'Motosiklet' ? 'primary' : 'secondary' ?> status-badge">
                                                            <?= ucfirst($courier['vehicle_type']) ?>
                                                        </span>
                                                    </div>
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
        // Auto refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
        
        // Real-time notifications (WebSocket connection would be here)
        // For now, we'll use periodic polling
        function checkNewOrders() {
            // This would be replaced with WebSocket in production
            console.log('Checking for new orders...');
        }
        
        // Check for new orders every 10 seconds
        setInterval(checkNewOrders, 10000);
    </script>
</body>
</html>
