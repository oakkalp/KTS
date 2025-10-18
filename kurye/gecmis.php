<?php
/**
 * Kurye Full System - Kurye Teslimat Geçmişi
 */

require_once '../config/config.php';
requireUserType('kurye');

// Kurye ID'sini al
$kurye_id = getKuryeId();

// Filtreleme
$date_filter = $_GET['date'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Teslimat geçmişini getir
try {
    $db = getDB();
    
    // WHERE koşulları
    $where_conditions = ["s.kurye_id = ?"];
    $params = [$kurye_id];
    
    // Durum filtresi
    if ($status_filter !== 'all') {
        $where_conditions[] = "s.status = ?";
        $params[] = $status_filter;
    } else {
        // Sadece tamamlanmış ve iptal edilmiş siparişleri göster
        $where_conditions[] = "s.status IN ('delivered', 'cancelled')";
    }
    
    // Tarih filtresi
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(s.created_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $stmt = $db->query("
        SELECT s.*, m.mekan_name, m.address as mekan_address,
               u.full_name as mekan_contact, u.phone as mekan_contact_phone,
               TIMESTAMPDIFF(MINUTE, s.created_at, s.delivered_at) as delivery_time_minutes
        FROM siparisler s 
        JOIN mekanlar m ON s.mekan_id = m.id 
        JOIN users u ON m.user_id = u.id
        {$where_clause}
        ORDER BY s.created_at DESC
        LIMIT 100
    ", $params);
    
    $orders = $stmt->fetchAll();
    
    // Komisyon oranını al
    $commission_rate = (float)getSetting('commission_rate', 15.00);
    
    // İstatistikler - komisyon düşüldükten sonraki kazanç
    $stats_stmt = $db->query("
        SELECT 
            COUNT(*) as total_deliveries,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_deliveries,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_deliveries,
            SUM(CASE WHEN status = 'delivered' THEN delivery_fee ELSE 0 END) as total_delivery_fees,
            AVG(CASE WHEN status = 'delivered' AND delivered_at IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, created_at, delivered_at) END) as avg_delivery_time
        FROM siparisler s 
        {$where_clause}
    ", $params);
    
    $stats_raw = $stats_stmt->fetch();
    
    // Komisyon düşüldükten sonraki gerçek kazanç
    $total_commission = ($stats_raw['total_delivery_fees'] * $commission_rate) / 100;
    $stats = [
        'total_deliveries' => $stats_raw['total_deliveries'],
        'completed_deliveries' => $stats_raw['completed_deliveries'],
        'cancelled_deliveries' => $stats_raw['cancelled_deliveries'],
        'total_delivery_fees' => $stats_raw['total_delivery_fees'], // Toplam teslimat ücreti
        'total_earnings' => $stats_raw['total_delivery_fees'] - $total_commission, // Komisyon düşülmüş kazanç
        'avg_delivery_time' => $stats_raw['avg_delivery_time']
    ];
    
} catch (Exception $e) {
    $error = 'Veriler yüklenemedi: ' . $e->getMessage();
    $orders = [];
    $commission_rate = 15.00; // Varsayılan komisyon oranı
    $stats = ['total_deliveries' => 0, 'completed_deliveries' => 0, 'cancelled_deliveries' => 0, 'total_delivery_fees' => 0, 'total_earnings' => 0, 'avg_delivery_time' => 0];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teslimat Geçmişi - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-history me-2 text-info"></i>
                        Teslimat Geçmişi
                    </h1>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= sanitize($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- İstatistikler -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-primary"><?= number_format($stats['total_deliveries']) ?></div>
                                <div class="text-muted">Toplam Teslimat</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-success"><?= number_format($stats['completed_deliveries']) ?></div>
                                <div class="text-muted">Başarılı Teslimat</div>
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
                                <div class="h2 text-success"><?= formatMoney($stats['total_earnings']) ?></div>
                                <div class="text-muted">Toplam Kazanç</div>
                                <small class="text-muted">
                                    <?= $stats['completed_deliveries'] ?> teslimat × <?= formatMoney($stats['total_delivery_fees'] / max(1, $stats['completed_deliveries'])) ?> ücret
                                </small>
                                <?php if ($stats['completed_deliveries'] > 0): ?>
                                    <br><small class="text-info">
                                        Ort: <?= formatMoney($stats['total_earnings'] / $stats['completed_deliveries']) ?> (komisyon sonrası)
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h2 text-warning"><?= round($stats['avg_delivery_time'] ?? 0) ?></div>
                                <div class="text-muted">Ort. Süre (dk)</div>
                                <small class="text-danger">
                                    <?= number_format($stats['cancelled_deliveries']) ?> iptal
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtreler -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tarih</label>
                                <select name="date" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>Tüm Zamanlar</option>
                                    <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Bugün</option>
                                    <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>Bu Hafta</option>
                                    <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>Bu Ay</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Durum</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Tümü</option>
                                    <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Teslim Edildi</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>İptal Edildi</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <a href="gecmis.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        Temizle
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Teslimat Listesi -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5>Teslimat geçmişi bulunamadı</h5>
                                <p class="text-muted">Seçilen kriterlere uygun teslimat bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 10%;">Sipariş No</th>
                                            <th style="width: 15%;">Mekan</th>
                                            <th style="width: 25%;">Müşteri & Adres</th>
                                            <th style="width: 10%;">Tutar</th>
                                            <th style="width: 12%;">Kazanç</th>
                                            <th style="width: 8%;">Durum</th>
                                            <th style="width: 8%;">Süre</th>
                                            <th style="width: 12%;">Tarih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= sanitize($order['order_number']) ?></strong>
                                                    <?php if ($order['priority'] !== 'normal'): ?>
                                                        <br><span class="badge bg-<?= $order['priority'] === 'urgent' ? 'danger' : 'warning' ?> badge-sm">
                                                            <?= $order['priority'] === 'urgent' ? 'ACİL' : 'EKSPRES' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= sanitize($order['mekan_name']) ?></strong>
                                                    <br><small class="text-muted"><?= sanitize($order['mekan_address']) ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= sanitize($order['customer_name']) ?></strong>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-phone me-1"></i>
                                                        <?= formatPhone($order['customer_phone']) ?>
                                                    </small>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?= sanitize($order['delivery_address']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <strong><?= formatMoney($order['total_amount']) ?></strong>
                                                    <br><small class="badge bg-<?= $order['payment_method'] === 'cash' ? 'success' : ($order['payment_method'] === 'online' ? 'primary' : 'warning') ?> mt-1">
                                                        <?php
                                                        switch($order['payment_method']) {
                                                            case 'cash': echo '<i class="fas fa-money-bill me-1"></i>Nakit'; break;
                                                            case 'online': echo '<i class="fas fa-credit-card me-1"></i>Online'; break;
                                                            case 'credit_card': echo '<i class="fas fa-credit-card me-1"></i>Kredi Kartı'; break;
                                                            case 'credit_card_door': echo '<i class="fas fa-credit-card me-1"></i>Kapıda Kredi Kartı'; break;
                                                            default: echo '<i class="fas fa-question me-1"></i>' . sanitize($order['payment_method']);
                                                        }
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $commission = ($order['delivery_fee'] * $commission_rate) / 100;
                                                    $kurye_earning = $order['delivery_fee'] - $commission;
                                                    ?>
                                                    <strong class="text-success"><?= formatMoney($kurye_earning) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $order['status'] === 'delivered' ? 'success' : 'danger' ?>">
                                                        <?= $order['status'] === 'delivered' ? 'Teslim Edildi' : 'İptal Edildi' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($order['delivery_time_minutes'] && $order['status'] === 'delivered'): ?>
                                                        <span class="badge bg-<?= $order['delivery_time_minutes'] <= 30 ? 'success' : ($order['delivery_time_minutes'] <= 60 ? 'warning' : 'danger') ?>">
                                                            <?= $order['delivery_time_minutes'] ?> dk
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= formatDate($order['created_at']) ?>
                                                    <?php if ($order['delivered_at']): ?>
                                                        <br><small class="text-success">
                                                            Teslim: <?= formatDate($order['delivered_at']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
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
</body>
</html>
