<?php
/**
 * Kurye Full System - Mekan Raporlar
 */

require_once '../config/config.php';
requireUserType('mekan');

// Mekan ID'sini al
$mekan_id = getMekanId();

// Tarih filtreleri
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Bu ayın başı
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Bugün

try {
    $db = getDB();
    
    // Genel istatistikler
    $stats_stmt = $db->query("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
            SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END) as total_revenue,
            SUM(CASE WHEN status = 'delivered' THEN delivery_fee ELSE 0 END) as total_delivery_fees,
            AVG(CASE WHEN status = 'delivered' AND delivered_at IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, created_at, delivered_at) END) as avg_delivery_time,
            COUNT(DISTINCT kurye_id) as unique_couriers
        FROM siparisler 
        WHERE mekan_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ", [$mekan_id, $start_date, $end_date]);
    
    $general_stats = $stats_stmt->fetch();
    
    // Günlük satış grafiği için veriler
    $daily_stats_stmt = $db->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed,
            SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END) as revenue
        FROM siparisler 
        WHERE mekan_id = ? AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ", [$mekan_id, $start_date, $end_date]);
    
    $daily_stats = $daily_stats_stmt->fetchAll();
    
    // En çok çalışılan kuryeler
    $top_couriers_stmt = $db->query("
        SELECT 
            u.full_name,
            u.phone,
            k.vehicle_type,
            k.license_plate,
            COUNT(s.id) as total_orders,
            COUNT(CASE WHEN s.status = 'delivered' THEN 1 END) as completed_orders,
            AVG(CASE WHEN s.status = 'delivered' AND s.delivered_at IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, s.created_at, s.delivered_at) END) as avg_delivery_time,
            MAX(s.delivered_at) as last_delivery
        FROM siparisler s
        JOIN kuryeler k ON s.kurye_id = k.id
        JOIN users u ON k.user_id = u.id
        WHERE s.mekan_id = ? AND DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY k.id
        HAVING total_orders > 0
        ORDER BY completed_orders DESC
        LIMIT 10
    ", [$mekan_id, $start_date, $end_date]);
    
    $top_couriers = $top_couriers_stmt->fetchAll();
    
    // Saatlik dağılım
    $hourly_stats_stmt = $db->query("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as orders,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed
        FROM siparisler 
        WHERE mekan_id = ? AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ", [$mekan_id, $start_date, $end_date]);
    
    $hourly_stats = $hourly_stats_stmt->fetchAll();
    
    // Sipariş durumu dağılımı
    $status_stats_stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM siparisler 
        WHERE mekan_id = ? AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY status
        ORDER BY count DESC
    ", [$mekan_id, $start_date, $end_date]);
    
    $status_stats = $status_stats_stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Raporlar yüklenemedi: ' . $e->getMessage();
    $general_stats = ['total_orders' => 0, 'completed_orders' => 0, 'cancelled_orders' => 0, 'total_revenue' => 0, 'total_delivery_fees' => 0, 'avg_delivery_time' => 0, 'unique_couriers' => 0];
    $daily_stats = [];
    $top_couriers = [];
    $hourly_stats = [];
    $status_stats = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-bar me-2 text-info"></i>
                        Raporlar
                    </h1>
                    <div class="btn-toolbar">
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>
                            Yazdır
                        </button>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= sanitize($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Tarih Filtresi -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Başlangıç Tarihi</label>
                                <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i>
                                        Filtrele
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setQuickDate('today')">Bugün</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setQuickDate('week')">Bu Hafta</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setQuickDate('month')">Bu Ay</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Genel İstatistikler -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-primary"><?= number_format($general_stats['total_orders']) ?></div>
                                <div class="text-muted">Toplam Sipariş</div>
                                <?php if ($general_stats['total_orders'] > 0): ?>
                                    <small class="text-success">
                                        %<?= round(($general_stats['completed_orders'] / $general_stats['total_orders']) * 100) ?> tamamlandı
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-success"><?= formatMoney($general_stats['total_revenue']) ?></div>
                                <div class="text-muted">Toplam Gelir</div>
                                <small class="text-info">
                                    Teslimat: <?= formatMoney($general_stats['total_delivery_fees']) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-info"><?= number_format($general_stats['unique_couriers']) ?></div>
                                <div class="text-muted">Çalışan Kurye</div>
                                <small class="text-muted">
                                    <?= number_format($general_stats['completed_orders']) ?> teslimat
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-warning"><?= round($general_stats['avg_delivery_time'] ?? 0) ?></div>
                                <div class="text-muted">Ort. Teslimat (dk)</div>
                                <small class="text-danger">
                                    <?= number_format($general_stats['cancelled_orders']) ?> iptal
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Günlük Satış Grafiği -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Günlük Satış Grafiği</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($daily_stats)): ?>
                                    <canvas id="dailySalesChart" height="100"></canvas>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Seçilen tarih aralığında veri bulunamadı.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sipariş Durumu Dağılımı -->
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Sipariş Durumu</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($status_stats)): ?>
                                    <canvas id="statusChart" height="200"></canvas>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Veri bulunamadı.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- En İyi Kuryeler -->
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">En Çok Çalışılan Kuryeler</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_couriers)): ?>
                                    <p class="text-muted text-center">Veri bulunamadı</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Kurye</th>
                                                    <th>Teslimat</th>
                                                    <th>Başarı</th>
                                                    <th>Ort. Süre</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_couriers as $courier): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= sanitize($courier['full_name']) ?></strong>
                                                            <br><small class="text-muted">
                                                                <i class="fas fa-motorcycle me-1"></i>
                                                                <?= ucfirst($courier['vehicle_type']) ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <strong><?= $courier['completed_orders'] ?></strong> / <?= $courier['total_orders'] ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $success_rate = $courier['total_orders'] > 0 ? 
                                                                ($courier['completed_orders'] / $courier['total_orders']) * 100 : 0;
                                                            ?>
                                                            <span class="badge bg-<?= $success_rate >= 90 ? 'success' : ($success_rate >= 70 ? 'warning' : 'danger') ?>">
                                                                %<?= round($success_rate) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= round($courier['avg_delivery_time'] ?? 0) ?> dk</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Saatlik Dağılım -->
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Saatlik Sipariş Dağılımı</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($hourly_stats)): ?>
                                    <canvas id="hourlyChart" height="200"></canvas>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Veri bulunamadı.</p>
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
        // Günlük satış grafiği
        <?php if (!empty($daily_stats)): ?>
        const dailyCtx = document.getElementById('dailySalesChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: [<?= implode(',', array_map(function($d) { return '"' . date('d/m', strtotime($d['date'])) . '"'; }, $daily_stats)) ?>],
                datasets: [{
                    label: 'Sipariş Sayısı',
                    data: [<?= implode(',', array_column($daily_stats, 'orders')) ?>],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Gelir (₺)',
                    data: [<?= implode(',', array_column($daily_stats, 'revenue')) ?>],
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Sipariş durumu grafiği
        <?php if (!empty($status_stats)): ?>
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: [<?= implode(',', array_map(function($s) { 
                    $labels = [
                        'pending' => 'Bekliyor',
                        'accepted' => 'Kabul Edildi',
                        'preparing' => 'Hazırlanıyor',
                        'ready' => 'Hazır',
                        'picked_up' => 'Alındı',
                        'delivering' => 'Yolda',
                        'delivered' => 'Teslim Edildi',
                        'cancelled' => 'İptal'
                    ];
                    return '"' . ($labels[$s['status']] ?? $s['status']) . '"'; 
                }, $status_stats)) ?>],
                datasets: [{
                    data: [<?= implode(',', array_column($status_stats, 'count')) ?>],
                    backgroundColor: [
                        '#FFC107', '#17A2B8', '#6F42C1', '#6C757D',
                        '#FD7E14', '#20C997', '#28A745', '#DC3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        <?php endif; ?>
        
        // Saatlik dağılım grafiği
        <?php if (!empty($hourly_stats)): ?>
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: [<?= implode(',', array_map(function($h) { return '"' . $h['hour'] . ':00"'; }, $hourly_stats)) ?>],
                datasets: [{
                    label: 'Sipariş Sayısı',
                    data: [<?= implode(',', array_column($hourly_stats, 'orders')) ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>
        
        function setQuickDate(period) {
            const today = new Date();
            let startDate, endDate;
            
            switch(period) {
                case 'today':
                    startDate = endDate = today.toISOString().split('T')[0];
                    break;
                case 'week':
                    startDate = new Date(today.setDate(today.getDate() - today.getDay())).toISOString().split('T')[0];
                    endDate = new Date().toISOString().split('T')[0];
                    break;
                case 'month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    endDate = new Date().toISOString().split('T')[0];
                    break;
            }
            
            document.querySelector('input[name="start_date"]').value = startDate;
            document.querySelector('input[name="end_date"]').value = endDate;
            document.querySelector('form').submit();
        }
    </script>
</body>
</html>
