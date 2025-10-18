<?php
/**
 * Kurye Full System - Admin Dashboard
 * Sistem yöneticisi ana paneli
 */

require_once '../config/config.php';

// Admin yetkisi kontrol et
requireUserType('admin');

// Dashboard istatistikleri
try {
    $db = getDB();
    
    // Temel sayılar
    $stats = [];
    
    // Kullanıcı sayıları
    $stmt = $db->query("SELECT user_type, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY user_type");
    while ($row = $stmt->fetch()) {
        $stats['users'][$row['user_type']] = $row['count'];
    }
    
    // Sipariş istatistikleri
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM siparisler GROUP BY status");
    while ($row = $stmt->fetch()) {
        $stats['orders'][$row['status']] = $row['count'];
    }
    
    // Bugünkü siparişler
    $stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE DATE(created_at) = CURDATE()");
    $stats['today_orders'] = $stmt->fetch()['count'];
    
    // Bu ayki ciro (teslimat ücretleri toplamı)
    $stmt = $db->query("SELECT SUM(delivery_fee) as total FROM siparisler WHERE status = 'delivered' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $gross_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Komisyon hesapla
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    $commission_amount = ($gross_revenue * $commission_rate) / 100;
    $net_revenue = $gross_revenue - $commission_amount;
    
    $stats['monthly_revenue'] = $gross_revenue;      // Toplam ciro
    $stats['commission_amount'] = $commission_amount; // Kurye gideri
    $stats['net_revenue'] = $net_revenue;            // Net kazanç
    
    // Aktif kuryeler
    $stmt = $db->query("SELECT COUNT(*) as count FROM kuryeler k JOIN users u ON k.user_id = u.id WHERE k.is_online = 1 AND u.status = 'active'");
    $stats['active_couriers'] = $stmt->fetch()['count'];
    
    // Son siparişler
    $stmt = $db->query("
        SELECT s.*, m.mekan_name, k.user_id as kurye_user_id, u_kurye.full_name as kurye_name
        FROM siparisler s 
        LEFT JOIN mekanlar m ON s.mekan_id = m.id 
        LEFT JOIN kuryeler k ON s.kurye_id = k.id 
        LEFT JOIN users u_kurye ON k.user_id = u_kurye.id 
        ORDER BY s.created_at DESC 
        LIMIT 10
    ");
    $recent_orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    writeLog("Dashboard error: " . $e->getMessage(), 'ERROR');
    $stats = [];
    $recent_orders = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .chart-container {
            position: relative;
            height: 300px;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
                        <small>Admin Panel</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>
                                Kullanıcılar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="mekanlar.php">
                                <i class="fas fa-store me-2"></i>
                                Mekanlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="kuryeler.php">
                                <i class="fas fa-motorcycle me-2"></i>
                                Kuryeler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="siparisler.php">
                                <i class="fas fa-shopping-bag me-2"></i>
                                Siparişler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="raporlar.php">
                                <i class="fas fa-chart-bar me-2"></i>
                                Raporlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="map-tracking.php">
                                <i class="fas fa-map me-2"></i>
                                Kurye Takip (Canlı)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="konum-gecmisi.php">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                Konum Geçmişi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="ayarlar.php">
                                <i class="fas fa-cog me-2"></i>
                                Sistem Ayarları
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="api-docs.php">
                                <i class="fas fa-code me-2"></i>
                                API Dokümantasyonu
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-white-50">
                    
                    <div class="text-white-50 small px-3">
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
                        <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                        Dashboard
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
                                <div class="stat-icon bg-success me-3">
                                    <i class="fas fa-motorcycle"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Aktif Kuryeler</div>
                                    <div class="h4 mb-0"><?= number_format($stats['active_couriers'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-warning me-3">
                                    <i class="fas fa-store"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Aktif Mekanlar</div>
                                    <div class="h4 mb-0"><?= number_format($stats['users']['mekan'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-info me-3">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Aylık Ciro</div>
                                    <div class="h4 mb-0"><?= formatMoney($stats['monthly_revenue'] ?? 0) ?></div>
                                    <small class="text-muted">Teslimat ücretleri</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mali Durum Kartları -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-4 col-md-4">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-success me-3">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Toplam Ciro</div>
                                    <div class="h4 mb-0 text-success"><?= formatMoney($stats['monthly_revenue'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-4">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-danger me-3">
                                    <i class="fas fa-minus-circle"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Kurye Gideri</div>
                                    <div class="h4 mb-0 text-danger"><?= formatMoney($stats['commission_amount'] ?? 0) ?></div>
                                    <small class="text-muted">%<?= number_format($commission_rate, 0) ?> komisyon</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-4">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-primary me-3">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Net Kazanç</div>
                                    <div class="h4 mb-0 text-primary"><?= formatMoney($stats['net_revenue'] ?? 0) ?></div>
                                    <small class="text-muted">Ciro - Gider</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <!-- Sipariş Durumları Grafiği -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    Sipariş Durumları
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="orderStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kullanıcı Dağılımı -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-users me-2"></i>
                                    Kullanıcı Dağılımı
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="userDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Son Siparişler -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    Son Siparişler
                                </h5>
                                <a href="siparisler.php" class="btn btn-sm btn-primary">
                                    Tümünü Gör
                                    <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_orders)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>Henüz sipariş bulunmuyor.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Sipariş No</th>
                                                    <th>Mekan</th>
                                                    <th>Müşteri</th>
                                                    <th>Kurye</th>
                                                    <th>Tutar</th>
                                                    <th>Durum</th>
                                                    <th>Tarih</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= sanitize($order['order_number']) ?></strong>
                                                        </td>
                                                        <td><?= sanitize($order['mekan_name']) ?></td>
                                                        <td>
                                                            <?= sanitize($order['customer_name']) ?>
                                                            <br>
                                                            <small class="text-muted"><?= formatPhone($order['customer_phone']) ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ($order['kurye_name']): ?>
                                                                <?= sanitize($order['kurye_name']) ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= formatMoney($order['total_amount']) ?></td>
                                                        <td>
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
                                                        </td>
                                                        <td>
                                                            <?= formatDate($order['created_at']) ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sipariş Durumları Grafiği
        const orderStatusData = {
            labels: [
                <?php 
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
                foreach ($stats['orders'] ?? [] as $status => $count) {
                    echo "'" . ($status_texts[$status] ?? $status) . "',";
                }
                ?>
            ],
            datasets: [{
                data: [<?= implode(',', array_values($stats['orders'] ?? [])) ?>],
                backgroundColor: [
                    '#ffc107', '#17a2b8', '#007bff', '#6c757d',
                    '#343a40', '#007bff', '#28a745', '#dc3545'
                ]
            }]
        };

        const orderStatusChart = new Chart(document.getElementById('orderStatusChart'), {
            type: 'doughnut',
            data: orderStatusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Kullanıcı Dağılımı Grafiği
        const userDistributionData = {
            labels: ['Admin', 'Mekan', 'Kurye'],
            datasets: [{
                data: [
                    <?= $stats['users']['admin'] ?? 0 ?>,
                    <?= $stats['users']['mekan'] ?? 0 ?>,
                    <?= $stats['users']['kurye'] ?? 0 ?>
                ],
                backgroundColor: ['#007bff', '#28a745', '#ffc107']
            }]
        };

        const userDistributionChart = new Chart(document.getElementById('userDistributionChart'), {
            type: 'pie',
            data: userDistributionData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Auto refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
