<?php
/**
 * Kurye Full System - Admin Raporlar
 */

require_once '../config/config.php';
requireUserType('admin');

// Tarih filtreleri
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Bu ayın başı
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Bugün

try {
    $db = getDB();
    
    // Sistem ayarlarını al
    $delivery_fee = (float)getSetting('delivery_fee', 40.00);
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    
    // Genel istatistikler
    $stats_stmt = $db->query("
        SELECT 
            COUNT(DISTINCT s.id) as total_orders,
            COUNT(DISTINCT CASE WHEN s.status = 'delivered' THEN s.id END) as completed_orders,
            COUNT(DISTINCT CASE WHEN s.status = 'cancelled' THEN s.id END) as cancelled_orders,
            SUM(CASE WHEN s.status = 'delivered' THEN s.delivery_fee ELSE 0 END) as total_delivery_fees,
            SUM(CASE WHEN s.status = 'delivered' THEN s.total_amount ELSE 0 END) as total_order_amount,
            COUNT(DISTINCT m.id) as active_venues,
            COUNT(DISTINCT k.id) as active_couriers,
            AVG(CASE WHEN s.status = 'delivered' AND s.delivered_at IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, s.created_at, s.delivered_at) END) as avg_delivery_time
        FROM siparisler s
        LEFT JOIN mekanlar m ON s.mekan_id = m.id
        LEFT JOIN kuryeler k ON s.kurye_id = k.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
    ", [$start_date, $end_date]);
    
    $general_stats = $stats_stmt->fetch();
    
    // Mali hesaplamalar
    $completed_orders = (int)($general_stats['completed_orders'] ?? 0);
    $total_delivery_fees = (float)($general_stats['total_delivery_fees'] ?? 0);
    
    // Komisyon gelirimiz (sistemin kazancı)
    $our_commission_income = $total_delivery_fees * ($commission_rate / 100);
    
    // Kuryelere ödenecek tutar
    $courier_payments = $total_delivery_fees * (1 - $commission_rate / 100);
    
    // Mali durumlar - ayrı sorgularla
    $payments_stmt = $db->query("
        SELECT 
            COALESCE(SUM(CASE WHEN user_type = 'kurye' AND odeme_tipi = 'odeme' THEN tutar ELSE 0 END), 0) as total_courier_payments,
            COALESCE(SUM(CASE WHEN user_type = 'mekan' AND odeme_tipi = 'tahsilat' THEN tutar ELSE 0 END), 0) as total_venue_collections
        FROM odemeler 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ", [$start_date, $end_date]);
    
    $payments_stats = $payments_stmt->fetch();
    
    $balances_stmt = $db->query("
        SELECT 
            COALESCE(SUM(CASE WHEN user_type = 'kurye' AND bakiye > 0 THEN bakiye ELSE 0 END), 0) as pending_courier_payments,
            COALESCE(SUM(CASE WHEN user_type = 'mekan' AND bakiye > 0 THEN bakiye ELSE 0 END), 0) as pending_venue_collections
        FROM bakiye
    ");
    
    $balance_stats = $balances_stmt->fetch();
    
    // Mali durumları birleştir
    $financial_stats = [
        'total_courier_payments' => $payments_stats['total_courier_payments'] ?? 0,
        'total_venue_collections' => $payments_stats['total_venue_collections'] ?? 0,
        'pending_courier_payments' => $balance_stats['pending_courier_payments'] ?? 0,
        'pending_venue_collections' => $balance_stats['pending_venue_collections'] ?? 0
    ];
    
    // Günlük satış grafiği için veriler
    $daily_stats_stmt = $db->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed,
            SUM(CASE WHEN status = 'delivered' THEN delivery_fee * (? / 100) ELSE 0 END) as commission_revenue
        FROM siparisler 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ", [$commission_rate, $start_date, $end_date]);
    
    $daily_stats = $daily_stats_stmt->fetchAll();
    
    // En çok sipariş alan mekanlar
    $top_venues_stmt = $db->query("
        SELECT 
            m.mekan_name,
            COUNT(s.id) as total_orders,
            COUNT(CASE WHEN s.status = 'delivered' THEN 1 END) as completed_orders,
            SUM(CASE WHEN s.status = 'delivered' THEN s.delivery_fee ELSE 0 END) as revenue
        FROM mekanlar m
        LEFT JOIN siparisler s ON m.id = s.mekan_id AND DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY m.id
        HAVING total_orders > 0
        ORDER BY total_orders DESC
        LIMIT 10
    ", [$start_date, $end_date]);
    
    $top_venues = $top_venues_stmt->fetchAll();
    
    // En aktif kuryeler
    $top_couriers_stmt = $db->query("
        SELECT 
            u.full_name,
            COUNT(s.id) as total_deliveries,
            COUNT(CASE WHEN s.status = 'delivered' THEN 1 END) as completed_deliveries,
            SUM(CASE WHEN s.status = 'delivered' THEN s.delivery_fee ELSE 0 END) as gross_earnings,
            AVG(CASE WHEN s.status = 'delivered' AND s.delivered_at IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, s.created_at, s.delivered_at) END) as avg_delivery_time
        FROM kuryeler k
        JOIN users u ON k.user_id = u.id
        LEFT JOIN siparisler s ON k.id = s.kurye_id AND DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY k.id
        HAVING total_deliveries > 0
        ORDER BY completed_deliveries DESC
        LIMIT 10
    ", [$start_date, $end_date]);
    
    $top_couriers_raw = $top_couriers_stmt->fetchAll();
    
    // Teslimat ücreti ve komisyon oranını al
    $delivery_fee = (float)getSetting('delivery_fee', 40.00);
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    $top_couriers = [];
    
    foreach ($top_couriers_raw as $courier) {
        $completed_deliveries = (int)($courier['completed_deliveries'] ?? 0);
        // Kurye kazancı = (Teslimat ücreti - Komisyon) × Teslim edilen sipariş sayısı
        $net_per_delivery = $delivery_fee * (1 - $commission_rate / 100);
        $net_earnings = $completed_deliveries * $net_per_delivery;
        
        $top_couriers[] = [
            'full_name' => $courier['full_name'],
            'total_deliveries' => $courier['total_deliveries'],
            'completed_deliveries' => $completed_deliveries,
            'total_earnings' => $net_earnings, // Net kazanç (komisyon sonrası)
            'avg_delivery_time' => $courier['avg_delivery_time']
        ];
    }
    
    // Saatlik dağılım
    $hourly_stats_stmt = $db->query("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as orders
        FROM siparisler 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ", [$start_date, $end_date]);
    
    $hourly_stats = $hourly_stats_stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Raporlar yüklenemedi: ' . $e->getMessage();
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
                
                <?php if (isset($general_stats)): ?>
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
                                    <div class="h2 text-success"><?= formatMoney($our_commission_income) ?></div>
                                    <div class="text-muted">Komisyon Gelirimiz</div>
                                    <small class="text-info">
                                        %<?= $commission_rate ?> × ₺<?= formatMoney($total_delivery_fees) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="h2 text-info"><?= number_format($general_stats['active_venues']) ?></div>
                                    <div class="text-muted">Aktif Mekan</div>
                                    <small class="text-muted">
                                        <?= number_format($general_stats['active_couriers']) ?> kurye
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="h2 text-warning"><?= round($general_stats['avg_delivery_time'] ?? 0) ?></div>
                                    <div class="text-muted">Ort. Teslimat (dk)</div>
                                    <small class="text-success">
                                        <?= number_format($general_stats['completed_orders']) ?> tamamlanan
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
                                    <canvas id="dailySalesChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Saatlik Dağılım -->
                        <div class="col-lg-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Saatlik Dağılım</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="hourlyChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mali Özet -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-coins me-2"></i>
                                        Mali Özet
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-4">
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center p-3 bg-light rounded">
                                                <h4 class="text-primary"><?= formatMoney($total_delivery_fees) ?></h4>
                                                <small class="text-muted">Toplam Teslimat Ücreti</small>
                                                <br><small class="text-info"><?= $completed_orders ?> teslimat</small>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                                                <h4 class="text-success"><?= formatMoney($our_commission_income) ?></h4>
                                                <small class="text-muted">Bizim Komisyon Gelirimiz</small>
                                                <br><small class="text-success">%<?= $commission_rate ?> komisyon</small>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center p-3 bg-warning bg-opacity-10 rounded">
                                                <h4 class="text-warning"><?= formatMoney($courier_payments) ?></h4>
                                                <small class="text-muted">Kuryelere Ödenecek</small>
                                                <br><small class="text-warning">Toplam hak ediş</small>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center p-3 bg-info bg-opacity-10 rounded">
                                                <h4 class="text-info"><?= formatMoney($general_stats['total_order_amount']) ?></h4>
                                                <small class="text-muted">Toplam Sipariş Tutarı</small>
                                                <br><small class="text-info">Restoran cirosu</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <div class="row g-4">
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center p-3 border rounded">
                                                <h5 class="text-danger"><?= formatMoney($financial_stats['pending_courier_payments']) ?></h5>
                                                <small class="text-muted">Bekleyen Kurye Ödemeleri</small>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center p-3 border rounded">
                                                <h5 class="text-success"><?= formatMoney($financial_stats['pending_venue_collections']) ?></h5>
                                                <small class="text-muted">Bekleyen Mekan Tahsilatları</small>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center p-3 border rounded">
                                                <h5 class="text-primary"><?= formatMoney($financial_stats['total_courier_payments']) ?></h5>
                                                <small class="text-muted">Dönem Kurye Ödemeleri</small>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center p-3 border rounded">
                                                <h5 class="text-info"><?= formatMoney($financial_stats['total_venue_collections']) ?></h5>
                                                <small class="text-muted">Dönem Mekan Tahsilatları</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- En İyi Mekanlar -->
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">En Çok Sipariş Alan Mekanlar</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_venues)): ?>
                                        <p class="text-muted text-center">Veri bulunamadı</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Mekan</th>
                                                        <th>Sipariş</th>
                                                        <th>Tamamlanan</th>
                                                        <th>Gelir</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($top_venues as $venue): ?>
                                                        <tr>
                                                            <td><?= sanitize($venue['mekan_name']) ?></td>
                                                            <td><?= number_format($venue['total_orders']) ?></td>
                                                            <td><?= number_format($venue['completed_orders']) ?></td>
                                                            <td><?= formatMoney($venue['revenue']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- En İyi Kuryeler -->
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">En Aktif Kuryeler</h5>
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
                                                        <th>Ort. Süre</th>
                                                        <th>Kazanç</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($top_couriers as $courier): ?>
                                                        <tr>
                                                            <td><?= sanitize($courier['full_name']) ?></td>
                                                            <td><?= number_format($courier['completed_deliveries']) ?></td>
                                                            <td><?= round($courier['avg_delivery_time'] ?? 0) ?> dk</td>
                                                            <td><?= formatMoney($courier['total_earnings']) ?></td>
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
                <?php endif; ?>
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
                    label: 'Komisyon Gelirimiz (₺)',
                    data: [<?= implode(',', array_column($daily_stats, 'commission_revenue')) ?>],
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
        
        // Saatlik dağılım grafiği
        <?php if (!empty($hourly_stats)): ?>
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'doughnut',
            data: {
                labels: [<?= implode(',', array_map(function($h) { return '"' . $h['hour'] . ':00"'; }, $hourly_stats)) ?>],
                datasets: [{
                    data: [<?= implode(',', array_column($hourly_stats, 'orders')) ?>],
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                        '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF',
                        '#4BC0C0', '#FF6384', '#36A2EB', '#FFCE56'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
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
