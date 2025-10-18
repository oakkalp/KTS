<?php
/**
 * Kurye Full System - Kurye Kazançları
 */

require_once '../config/config.php';
requireUserType('kurye');

// Kurye ID'sini al
$kurye_id = getKuryeId();

// Tarih filtreleri
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Bu ayın başı
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Bugün

try {
    $db = getDB();
    
    // Genel kazanç istatistikleri
    // Komisyon oranını al
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    
    $stats_stmt = $db->query("
        SELECT 
            COUNT(*) as total_deliveries,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_deliveries,
            SUM(CASE WHEN status = 'delivered' THEN delivery_fee ELSE 0 END) as gross_earnings,
            SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END) as total_order_value,
            AVG(CASE WHEN status = 'delivered' THEN delivery_fee ELSE 0 END) as avg_earning_per_delivery,
            AVG(CASE WHEN status = 'delivered' AND delivered_at IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, created_at, delivered_at) END) as avg_delivery_time
        FROM siparisler 
        WHERE kurye_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ", [$kurye_id, $start_date, $end_date]);
    
    $stats = $stats_stmt->fetch();
    
    // Net kazanç hesapla (komisyon düşüldükten sonra)
    $gross_earnings = (float)($stats['gross_earnings'] ?? 0);
    $commission_amount = ($gross_earnings * $commission_rate) / 100;
    $net_earnings = $gross_earnings - $commission_amount;
    $completed_deliveries = (int)($stats['completed_deliveries'] ?? 0);
    
    $stats['total_earnings'] = $net_earnings;
    $stats['commission_amount'] = $commission_amount;
    
    // Teslimat başına net kazanç hesapla
    if ($completed_deliveries > 0) {
        $stats['avg_earning_per_delivery'] = $net_earnings / $completed_deliveries;
    } else {
        $stats['avg_earning_per_delivery'] = 0;
    }
    
    // Günlük kazanç grafiği için veriler (komisyon düşüldükten sonra)
    $daily_earnings_stmt = $db->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as deliveries,
            SUM(CASE WHEN status = 'delivered' THEN delivery_fee ELSE 0 END) as gross_earnings
        FROM siparisler 
        WHERE kurye_id = ? AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ", [$kurye_id, $start_date, $end_date]);
    
    $daily_earnings_raw = $daily_earnings_stmt->fetchAll();
    
    // Net günlük kazançları hesapla
    $daily_earnings = [];
    foreach ($daily_earnings_raw as $day) {
        $gross = (float)($day['gross_earnings'] ?? 0);
        $commission = ($gross * $commission_rate) / 100;
        $net = $gross - $commission;
        
        $daily_earnings[] = [
            'date' => $day['date'],
            'deliveries' => $day['deliveries'],
            'earnings' => $net,
            'gross_earnings' => $gross,
            'commission' => $commission
        ];
    }
    
    // Son teslimatlar
    $recent_deliveries_stmt = $db->query("
        SELECT s.*, m.mekan_name,
               TIMESTAMPDIFF(MINUTE, s.created_at, s.delivered_at) as delivery_time_minutes
        FROM siparisler s 
        JOIN mekanlar m ON s.mekan_id = m.id
        WHERE s.kurye_id = ? AND s.status = 'delivered' AND DATE(s.created_at) BETWEEN ? AND ?
        ORDER BY s.delivered_at DESC
        LIMIT 20
    ", [$kurye_id, $start_date, $end_date]);
    
    $recent_deliveries = $recent_deliveries_stmt->fetchAll();
    
    // Saatlik kazanç dağılımı (komisyon düşüldükten sonra)
    $hourly_earnings_stmt = $db->query("
        SELECT 
            HOUR(delivered_at) as hour,
            COUNT(*) as deliveries,
            SUM(delivery_fee) as gross_earnings
        FROM siparisler 
        WHERE kurye_id = ? AND status = 'delivered' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY HOUR(delivered_at)
        ORDER BY hour
    ", [$kurye_id, $start_date, $end_date]);
    
    $hourly_earnings_raw = $hourly_earnings_stmt->fetchAll();
    
    // Net saatlik kazançları hesapla
    $hourly_earnings = [];
    foreach ($hourly_earnings_raw as $hour) {
        $gross = (float)($hour['gross_earnings'] ?? 0);
        $commission = ($gross * $commission_rate) / 100;
        $net = $gross - $commission;
        
        $hourly_earnings[] = [
            'hour' => $hour['hour'],
            'deliveries' => $hour['deliveries'],
            'earnings' => $net
        ];
    }
    
} catch (Exception $e) {
    $error = 'Veriler yüklenemedi: ' . $e->getMessage();
    $stats = ['total_deliveries' => 0, 'completed_deliveries' => 0, 'total_earnings' => 0, 'total_order_value' => 0, 'avg_earning_per_delivery' => 0, 'avg_delivery_time' => 0];
    $daily_earnings = [];
    $recent_deliveries = [];
    $hourly_earnings = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kazançlarım - <?= SITE_NAME ?></title>
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
                        <i class="fas fa-coins me-2 text-warning"></i>
                        Kazançlarım
                    </h1>
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
                
                <!-- Kazanç İstatistikleri -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-success"><?= formatMoney($stats['total_earnings']) ?></div>
                                <div class="text-muted">Toplam Kazanç</div>
                                <?php if ($stats['completed_deliveries'] > 0): ?>
                                    <small class="text-info">
                                        <?= $stats['completed_deliveries'] ?> teslimat
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-primary"><?= number_format($stats['completed_deliveries']) ?></div>
                                <div class="text-muted">Tamamlanan Teslimat</div>
                                <?php if ($stats['total_deliveries'] > 0): ?>
                                    <small class="text-success">
                                        %<?= round(($stats['completed_deliveries'] / $stats['total_deliveries']) * 100) ?> başarı
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-info"><?= formatMoney($stats['avg_earning_per_delivery']) ?></div>
                                <div class="text-muted">Teslimat Başına</div>
                                <small class="text-muted">Ortalama kazanç</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-warning"><?= round($stats['avg_delivery_time'] ?? 0) ?></div>
                                <div class="text-muted">Ort. Teslimat (dk)</div>
                                <small class="text-muted">
                                    <?= formatMoney($stats['total_order_value']) ?> toplam sipariş
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Günlük Kazanç Grafiği -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Günlük Kazanç Grafiği</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($daily_earnings)): ?>
                                    <canvas id="dailyEarningsChart" height="100"></canvas>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Seçilen tarih aralığında kazanç verisi bulunamadı.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Saatlik Kazanç Dağılımı -->
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Saatlik Dağılım</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($hourly_earnings)): ?>
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
                
                <!-- Son Teslimatlar -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Son Teslimatlar</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_deliveries)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Seçilen tarih aralığında teslimat bulunamadı.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Mekan</th>
                                            <th>Müşteri</th>
                                            <th>Kazanç</th>
                                            <th>Süre</th>
                                            <th>Teslim Tarihi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_deliveries as $delivery): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= sanitize($delivery['order_number']) ?></strong>
                                                    <?php if ($delivery['priority'] !== 'normal'): ?>
                                                        <br><span class="badge bg-<?= $delivery['priority'] === 'urgent' ? 'danger' : 'warning' ?> badge-sm">
                                                            <?= $delivery['priority'] === 'urgent' ? 'ACİL' : 'EKSPRES' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= sanitize($delivery['mekan_name']) ?></td>
                                                <td><?= sanitize($delivery['customer_name']) ?></td>
                                                <td>
                                                    <?php
                                                    $gross_fee = (float)$delivery['delivery_fee'];
                                                    $commission = ($gross_fee * $commission_rate) / 100;
                                                    $net_fee = $gross_fee - $commission;
                                                    ?>
                                                    <strong class="text-success"><?= formatMoney($net_fee) ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($delivery['delivery_time_minutes']): ?>
                                                        <span class="badge bg-<?= $delivery['delivery_time_minutes'] <= 30 ? 'success' : ($delivery['delivery_time_minutes'] <= 60 ? 'warning' : 'danger') ?>">
                                                            <?= $delivery['delivery_time_minutes'] ?> dk
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= formatDate($delivery['delivered_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Günlük kazanç grafiği
        <?php if (!empty($daily_earnings)): ?>
        try {
            const dailyCtx = document.getElementById('dailyEarningsChart');
            if (dailyCtx) {
                new Chart(dailyCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: [<?= implode(',', array_map(function($d) { return '"' . date('d/m', strtotime($d['date'])) . '"'; }, $daily_earnings)) ?>],
                        datasets: [{
                            label: 'Teslimat Sayısı',
                            data: [<?= implode(',', array_column($daily_earnings, 'deliveries')) ?>],
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            tension: 0.1,
                            yAxisID: 'y'
                        }, {
                            label: 'Kazanç (₺)',
                            data: [<?= implode(',', array_column($daily_earnings, 'earnings')) ?>],
                            borderColor: 'rgb(255, 193, 7)',
                            backgroundColor: 'rgba(255, 193, 7, 0.1)',
                            tension: 0.1,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Teslimat Sayısı'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Kazanç (₺)'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                }
                            }
                        }
                    }
                });
            } else {
                console.error('dailyEarningsChart canvas element bulunamadı');
            }
        } catch (error) {
            console.error('Günlük kazanç grafiği oluşturulamadı:', error);
        }
        <?php endif; ?>
        
        // Saatlik dağılım grafiği
        <?php if (!empty($hourly_earnings)): ?>
        try {
            const hourlyCtx = document.getElementById('hourlyChart');
            if (hourlyCtx) {
                new Chart(hourlyCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: [<?= implode(',', array_map(function($h) { return '"' . $h['hour'] . ':00"'; }, $hourly_earnings)) ?>],
                        datasets: [{
                            data: [<?= implode(',', array_column($hourly_earnings, 'earnings')) ?>],
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF',
                                '#4BC0C0', '#FF6384', '#36A2EB', '#FFCE56'
                            ]
                        }]
                    },
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
            } else {
                console.error('hourlyChart canvas element bulunamadı');
            }
        } catch (error) {
            console.error('Saatlik dağılım grafiği oluşturulamadı:', error);
        }
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
