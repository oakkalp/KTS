<?php
/**
 * Kurye Full System - Admin Detaylı Günlük Rapor
 */

require_once '../config/config.php';

// Admin yetkisi kontrol et
requireUserType('admin');

$db = getDB();

// Tarih filtresi
$filter_type = $_GET['filter_type'] ?? 'daily';
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_week = $_GET['week'] ?? date('Y-\WW');
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_mekan = $_GET['mekan_id'] ?? '';
$selected_kurye = $_GET['kurye_id'] ?? '';

// Tarih aralığını belirle
switch ($filter_type) {
    case 'weekly':
        $year = substr($selected_week, 0, 4);
        $week = substr($selected_week, -2);
        $start_date = date('Y-m-d', strtotime($year . 'W' . $week));
        $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
        break;
    case 'monthly':
        $start_date = $selected_month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        break;
    default: // daily
        $start_date = $selected_date;
        $end_date = $selected_date;
        break;
}

// Sistem ayarları
$delivery_fee = (float)getSetting('delivery_fee', 40.00);
$commission_rate = (float)getSetting('commission_rate', 15.00);

// Mekan ve kurye listesi
$mekanlar_stmt = $db->query("SELECT id, mekan_name FROM mekanlar ORDER BY mekan_name");
$mekanlar = $mekanlar_stmt->fetchAll();

$kuryeler_stmt = $db->query("SELECT k.id, u.full_name FROM kuryeler k JOIN users u ON k.user_id = u.id ORDER BY u.full_name");
$kuryeler = $kuryeler_stmt->fetchAll();

// Detaylı sipariş listesi
$where_conditions = ["DATE(s.created_at) BETWEEN ? AND ?"];
$params = [$start_date, $end_date];

if ($selected_mekan) {
    $where_conditions[] = "s.mekan_id = ?";
    $params[] = $selected_mekan;
}

if ($selected_kurye) {
    $where_conditions[] = "s.kurye_id = ?";
    $params[] = $selected_kurye;
}

$where_clause = implode(' AND ', $where_conditions);

$orders_sql = "
    SELECT 
        s.*,
        m.mekan_name,
        m.address as mekan_address,
        m.phone as mekan_phone,
        u.full_name as kurye_name,
        TIMESTAMPDIFF(MINUTE, s.created_at, s.delivered_at) as delivery_time_minutes
    FROM siparisler s
    LEFT JOIN mekanlar m ON s.mekan_id = m.id
    LEFT JOIN kuryeler k ON s.kurye_id = k.id
    LEFT JOIN users u ON k.user_id = u.id
    WHERE {$where_clause}
    ORDER BY s.created_at DESC
";
$orders_stmt = $db->query($orders_sql, $params);

$orders = $orders_stmt->fetchAll();

// Özet istatistikler
$total_orders = count($orders);
$completed_orders = count(array_filter($orders, fn($o) => $o['status'] === 'delivered'));
$total_delivery_fees = array_sum(array_map(fn($o) => $o['status'] === 'delivered' ? $o['delivery_fee'] : 0, $orders));
$total_order_amount = array_sum(array_map(fn($o) => $o['status'] === 'delivered' ? $o['total_amount'] : 0, $orders));

// Mali hesaplamalar
$our_commission_income = ($total_delivery_fees * $commission_rate) / 100; // Bizim kazancımız
$courier_payments = $total_delivery_fees - $our_commission_income; // Kuryelere ödenecek

// Mekan bazlı özet
$mekan_summary = [];
foreach ($orders as $order) {
    if ($order['status'] !== 'delivered') continue;
    
    $mekan_id = $order['mekan_id'];
    if (!isset($mekan_summary[$mekan_id])) {
        $mekan_summary[$mekan_id] = [
            'mekan_name' => $order['mekan_name'],
            'orders' => 0,
            'total_order_amount' => 0,
            'delivery_fees' => 0,
            'our_commission' => 0,
            'collection_amount' => 0
        ];
    }
    
    $delivery_fee = (float)$order['delivery_fee'];
    $order_amount = (float)$order['total_amount'];
    $our_commission = ($delivery_fee * $commission_rate) / 100;
    
    $mekan_summary[$mekan_id]['orders']++;
    $mekan_summary[$mekan_id]['total_order_amount'] += $order_amount;
    $mekan_summary[$mekan_id]['delivery_fees'] += $delivery_fee;
    $mekan_summary[$mekan_id]['our_commission'] += $our_commission;
    $mekan_summary[$mekan_id]['collection_amount'] += $delivery_fee; // Mekandan tahsil edilecek = teslimat ücreti
}

// Gerçek tahsilat bilgilerini al
foreach ($mekan_summary as $mekan_id => &$summary) {
    // Bu mekanın user_id'sini bul
    $mekan_user_stmt = $db->query("SELECT user_id FROM mekanlar WHERE id = ?", [(int)$mekan_id]);
    $mekan_user = $mekan_user_stmt->fetch();
    
    if ($mekan_user) {
        $user_id = $mekan_user['user_id'];
        
        // Dönem içinde yapılan tahsilatlar
        $tahsilat_stmt = $db->query("
            SELECT COALESCE(SUM(tutar), 0) as collected_amount
            FROM odemeler 
            WHERE user_id = ? AND user_type = 'mekan' AND odeme_tipi = 'tahsilat'
            AND DATE(created_at) BETWEEN ? AND ?
        ", [(int)$user_id, $start_date, $end_date]);
        
        $tahsilat_data = $tahsilat_stmt->fetch();
        $summary['collected_amount'] = (float)($tahsilat_data['collected_amount'] ?? 0);
        
        // Mevcut bakiye durumu
        $bakiye_stmt = $db->query("
            SELECT bakiye FROM bakiye WHERE user_id = ? AND user_type = 'mekan'
        ", [(int)$user_id]);
        
        $bakiye_data = $bakiye_stmt->fetch();
        $summary['current_balance'] = (float)($bakiye_data['bakiye'] ?? 0);
        
        // Son tahsilat detayları (dönem içinde)
        $tahsilat_detay_stmt = $db->query("
            SELECT tutar, aciklama, created_at
            FROM odemeler 
            WHERE user_id = ? AND user_type = 'mekan' AND odeme_tipi = 'tahsilat'
            AND DATE(created_at) BETWEEN ? AND ?
            ORDER BY created_at DESC
            LIMIT 5
        ", [(int)$user_id, $start_date, $end_date]);
        
        $summary['payment_details'] = $tahsilat_detay_stmt->fetchAll();
    } else {
        $summary['collected_amount'] = 0;
        $summary['current_balance'] = 0;
        $summary['payment_details'] = [];
    }
}

// Kurye bazlı özet
$kurye_summary = [];
foreach ($orders as $order) {
    if ($order['status'] !== 'delivered' || !$order['kurye_id']) continue;
    
    $kurye_id = $order['kurye_id'];
    
    if (!isset($kurye_summary[$kurye_id])) {
        // Kurye adını her zaman veritabanından çek (güvenli olması için)
        $kurye_name_stmt = $db->query("
            SELECT u.full_name 
            FROM kuryeler k 
            JOIN users u ON k.user_id = u.id 
            WHERE k.id = ?
        ", [(int)$kurye_id]);
        $kurye_data = $kurye_name_stmt->fetch();
        $kurye_name = $kurye_data['full_name'] ?? "Kurye #{$kurye_id}";
        
        $kurye_summary[$kurye_id] = [
            'kurye_name' => $kurye_name,
            'orders' => 0,
            'total_delivery_fees' => 0,
            'our_commission' => 0,
            'courier_earnings' => 0
        ];
    }
    
    $delivery_fee = (float)$order['delivery_fee'];
    $our_commission = ($delivery_fee * $commission_rate) / 100;
    $courier_earning = $delivery_fee - $our_commission; // Kurye'nin aldığı = teslimat ücreti - komisyon
    
    $kurye_summary[$kurye_id]['orders']++;
    $kurye_summary[$kurye_id]['total_delivery_fees'] += $delivery_fee;
    $kurye_summary[$kurye_id]['our_commission'] += $our_commission;
    $kurye_summary[$kurye_id]['courier_earnings'] += $courier_earning;
}

// Gerçek ödeme bilgilerini al (kuryeler için)
foreach ($kurye_summary as $kurye_id => &$kurye_item) {
    // Bu kuryenin user_id'sini ve adını bul
    $kurye_user_stmt = $db->query("
        SELECT k.user_id, u.full_name 
        FROM kuryeler k 
        JOIN users u ON k.user_id = u.id 
        WHERE k.id = ?
    ", [(int)$kurye_id]);
    $kurye_user = $kurye_user_stmt->fetch();
    
    if ($kurye_user) {
        $user_id = $kurye_user['user_id'];
        
        // Kurye adını güncelle (eğer boşsa)
        if (empty($kurye_item['kurye_name']) || $kurye_item['kurye_name'] === 'Kurye Adı Yok' || trim($kurye_item['kurye_name']) === '') {
            $kurye_item['kurye_name'] = $kurye_user['full_name'];
        }
        
        // Dönem içinde yapılan ödemeler
        $kurye_odeme_stmt = $db->query("
            SELECT COALESCE(SUM(tutar), 0) as paid_amount
            FROM odemeler 
            WHERE user_id = ? AND user_type = 'kurye' AND odeme_tipi = 'odeme'
            AND DATE(created_at) BETWEEN ? AND ?
        ", [(int)$user_id, $start_date, $end_date]);
        
        $kurye_odeme_data = $kurye_odeme_stmt->fetch();
        $kurye_item['paid_amount'] = (float)($kurye_odeme_data['paid_amount'] ?? 0);
        
        // Mevcut bakiye durumu
        $kurye_bakiye_stmt = $db->query("
            SELECT bakiye FROM bakiye WHERE user_id = ? AND user_type = 'kurye'
        ", [(int)$user_id]);
        
        $kurye_bakiye_data = $kurye_bakiye_stmt->fetch();
        $kurye_item['current_balance'] = (float)($kurye_bakiye_data['bakiye'] ?? 0);
        
        // Son ödeme detayları (dönem içinde)
        $kurye_detay_stmt = $db->query("
            SELECT tutar, aciklama, created_at
            FROM odemeler 
            WHERE user_id = ? AND user_type = 'kurye' AND odeme_tipi = 'odeme'
            AND DATE(created_at) BETWEEN ? AND ?
            ORDER BY created_at DESC
            LIMIT 5
        ", [(int)$user_id, $start_date, $end_date]);
        
        $kurye_item['payment_details'] = $kurye_detay_stmt->fetchAll();
    } else {
        $kurye_item['paid_amount'] = 0;
        $kurye_item['current_balance'] = 0;
        $kurye_item['payment_details'] = [];
        
        // Kurye bulunamazsa varsayılan ad ver
        if (empty($kurye_item['kurye_name']) || $kurye_item['kurye_name'] === 'Kurye Adı Yok') {
            $kurye_item['kurye_name'] = "Kurye #" . $kurye_id;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detaylı Günlük Rapor - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        .summary-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            transition: transform 0.3s ease;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-bar me-2 text-primary"></i>
                        Detaylı Günlük Rapor
                    </h1>
                </div>
                
                <!-- Filtreler -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Filtre Tipi</label>
                                <select class="form-select" name="filter_type" onchange="toggleDateFields(this.value)">
                                    <option value="daily" <?= $filter_type === 'daily' ? 'selected' : '' ?>>Günlük</option>
                                    <option value="weekly" <?= $filter_type === 'weekly' ? 'selected' : '' ?>>Haftalık</option>
                                    <option value="monthly" <?= $filter_type === 'monthly' ? 'selected' : '' ?>>Aylık</option>
                                </select>
                            </div>
                            <div class="col-md-2" id="daily-field" style="<?= $filter_type !== 'daily' ? 'display:none' : '' ?>">
                                <label class="form-label">Tarih</label>
                                <input type="date" class="form-control" name="date" value="<?= sanitize($selected_date) ?>">
                            </div>
                            <div class="col-md-2" id="weekly-field" style="<?= $filter_type !== 'weekly' ? 'display:none' : '' ?>">
                                <label class="form-label">Hafta</label>
                                <input type="week" class="form-control" name="week" value="<?= sanitize($selected_week) ?>">
                            </div>
                            <div class="col-md-2" id="monthly-field" style="<?= $filter_type !== 'monthly' ? 'display:none' : '' ?>">
                                <label class="form-label">Ay</label>
                                <input type="month" class="form-control" name="month" value="<?= sanitize($selected_month) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mekan</label>
                                <select class="form-select" name="mekan_id">
                                    <option value="">Tüm Mekanlar</option>
                                    <?php foreach ($mekanlar as $mekan): ?>
                                        <option value="<?= sanitize($mekan['id']) ?>" <?= $selected_mekan == $mekan['id'] ? 'selected' : '' ?>>
                                            <?= sanitize($mekan['mekan_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kurye</label>
                                <select class="form-select" name="kurye_id">
                                    <option value="">Tüm Kuryeler</option>
                                    <?php foreach ($kuryeler as $kurye): ?>
                                        <option value="<?= sanitize($kurye['id']) ?>" <?= $selected_kurye == $kurye['id'] ? 'selected' : '' ?>>
                                            <?= sanitize($kurye['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i>
                                    Filtrele
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Özet İstatistikler -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card summary-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-shopping-bag fa-2x text-primary"></i>
                                    </div>
                                    <div class="ms-3">
                                        <div class="text-muted small">Toplam Sipariş</div>
                                        <div class="h4 mb-0"><?= $total_orders ?></div>
                                        <small class="text-success"><?= $completed_orders ?> tamamlandı</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card summary-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-coins fa-2x text-primary"></i>
                                    </div>
                                    <div class="ms-3">
                                        <div class="text-muted small">Teslimat Ücretleri</div>
                                        <div class="h4 mb-0 text-primary"><?= formatMoney($total_delivery_fees) ?></div>
                                        <small class="text-muted">Toplam teslimat geliri</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card summary-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-chart-pie fa-2x text-success"></i>
                                    </div>
                                    <div class="ms-3">
                                        <div class="text-muted small">Bizim Komisyon Gelirimiz</div>
                                        <div class="h4 mb-0 text-success"><?= formatMoney($our_commission_income) ?></div>
                                        <small class="text-muted">%<?= number_format($commission_rate, 0) ?> komisyon</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card summary-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-hand-holding-usd fa-2x text-warning"></i>
                                    </div>
                                    <div class="ms-3">
                                        <div class="text-muted small">Kuryelere Ödenecek</div>
                                        <div class="h4 mb-0 text-warning"><?= formatMoney($courier_payments) ?></div>
                                        <small class="text-muted">Toplam kurye ödemesi</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mekan Bazlı Özet -->
                <?php if (!empty($mekan_summary)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-store me-2"></i>
                            Mekan Bazlı Özet
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mekan</th>
                                        <th>Sipariş</th>
                                        <th>Sipariş Tutarı</th>
                                        <th>Teslimat Ücreti</th>
                                        <th>Bizim Komisyon</th>
                                        <th>Tahsil Edilecek</th>
                                        <th>Tahsil Edilen</th>
                                        <th>Kalan Bakiye</th>
                                        <th>Detay</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mekan_summary as $mekan_id => $summary): ?>
                                        <?php $detail_id = 'mekan-' . $mekan_id . '-' . md5(serialize($summary)); ?>
                                        <tr>
                                            <td><strong><?= sanitize($summary['mekan_name']) ?></strong></td>
                                            <td><span class="badge bg-primary"><?= $summary['orders'] ?></span></td>
                                            <td><?= formatMoney($summary['total_order_amount']) ?></td>
                                            <td class="text-primary"><?= formatMoney($summary['delivery_fees']) ?></td>
                                            <td class="text-success"><strong><?= formatMoney($summary['our_commission']) ?></strong></td>
                                            <td class="text-warning"><?= formatMoney($summary['collection_amount']) ?></td>
                                            <td class="text-info"><strong><?= formatMoney($summary['collected_amount']) ?></strong></td>
                                            <td>
                                                <?php 
                                                $balance = $summary['current_balance'];
                                                if ($balance > 0): ?>
                                                    <strong class="text-danger">₺<?= number_format($balance, 2) ?></strong>
                                                    <br><small class="text-muted">Mekan borçlu</small>
                                                <?php elseif ($balance < 0): ?>
                                                    <strong class="text-success">₺<?= number_format(abs($balance), 2) ?></strong>
                                                    <br><small class="text-muted">Bizim borcumuz</small>
                                                <?php else: ?>
                                                    <span class="text-muted">₺0.00</span>
                                                    <br><small class="text-muted">Hesap temiz</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($summary['payment_details'])): ?>
                                                    <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#detail-<?= $detail_id ?>" aria-expanded="false">
                                                        <i class="fas fa-eye"></i> Görüntüle
                                                    </button>
                                                <?php else: ?>
                                                    <small class="text-muted">Tahsilat yok</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if (!empty($summary['payment_details'])): ?>
                                            <tr class="collapse" id="detail-<?= $detail_id ?>">
                                                <td colspan="9">
                                                    <div class="card card-body bg-light">
                                                        <h6><i class="fas fa-receipt me-2"></i>Tahsilat Detayları</h6>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-borderless">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Tarih</th>
                                                                        <th>Tutar</th>
                                                                        <th>Açıklama</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($summary['payment_details'] as $detail): ?>
                                                                        <tr>
                                                                            <td><?= formatDate($detail['created_at'] ?? '') ?></td>
                                                                            <td><strong class="text-success">₺<?= number_format((float)($detail['tutar'] ?? 0), 2) ?></strong></td>
                                                                            <td><?= sanitize($detail['aciklama'] ?? '') ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Kurye Bazlı Özet -->
                <?php if (!empty($kurye_summary)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-motorcycle me-2"></i>
                            Kurye Bazlı Özet
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Kurye Adı</th>
                                        <th>Teslimat Sayısı</th>
                                        <th>Kurye Kazancı</th>
                                        <th>Ödenen Tutar</th>
                                        <th>Kalan Bakiye</th>
                                        <th>Detay</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kurye_summary as $kurye_id => $kurye_item): ?>
                                        <?php $detail_id = 'kurye-' . $kurye_id . '-' . md5(serialize($kurye_item)); ?>
                                        <tr>
                                            <td><strong><?= sanitize($kurye_item['kurye_name'] ?? 'Kurye Adı Yok') ?></strong></td>
                                            <td><span class="badge bg-success"><?= $kurye_item['orders'] ?? 0 ?></span></td>
                                            <td class="text-warning"><strong><?= formatMoney($kurye_item['courier_earnings'] ?? 0) ?></strong></td>
                                            <td class="text-info"><strong><?= formatMoney($kurye_item['paid_amount'] ?? 0) ?></strong></td>
                                            <td>
                                                <?php 
                                                $kurye_balance = $kurye_item['current_balance'] ?? 0;
                                                if ($kurye_balance > 0): ?>
                                                    <strong class="text-success">₺<?= number_format($kurye_balance, 2) ?></strong>
                                                    <br><small class="text-muted">Kurye alacaklı</small>
                                                <?php elseif ($kurye_balance < 0): ?>
                                                    <strong class="text-danger">₺<?= number_format(abs($kurye_balance), 2) ?></strong>
                                                    <br><small class="text-muted">Kurye borçlu</small>
                                                <?php else: ?>
                                                    <span class="text-muted">₺0.00</span>
                                                    <br><small class="text-muted">Hesap temiz</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($kurye_item['payment_details'])): ?>
                                                    <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#detail-<?= $detail_id ?>" aria-expanded="false">
                                                        <i class="fas fa-eye"></i> Görüntüle
                                                    </button>
                                                <?php else: ?>
                                                    <small class="text-muted">Ödeme yok</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if (!empty($kurye_item['payment_details'])): ?>
                                            <tr class="collapse" id="detail-<?= $detail_id ?>">
                                                <td colspan="6">
                                                    <div class="card card-body bg-light">
                                                        <h6><i class="fas fa-hand-holding-usd me-2"></i>Ödeme Detayları</h6>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-borderless">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Tarih</th>
                                                                        <th>Tutar</th>
                                                                        <th>Açıklama</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($kurye_item['payment_details'] as $kurye_detail): ?>
                                                                        <tr>
                                                                            <td><?= formatDate($kurye_detail['created_at'] ?? '') ?></td>
                                                                            <td><strong class="text-primary">₺<?= number_format((float)($kurye_detail['tutar'] ?? 0), 2) ?></strong></td>
                                                                            <td><?= sanitize($kurye_detail['aciklama'] ?? '') ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Detaylı Sipariş Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>
                            Detaylı Sipariş Listesi (<?= formatDate($start_date) ?><?= $start_date !== $end_date ? ' - ' . formatDate($end_date) : '' ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Seçilen kriterlerde sipariş bulunamadı.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Mekan</th>
                                            <th>Kurye</th>
                                            <th>Müşteri</th>
                                            <th>Durum</th>
                                            <th>Sipariş Tutarı</th>
                                            <th>Teslimat Ücreti</th>
                                            <th>Bizim Komisyon</th>
                                            <th>Süre</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <?php
                                            $delivery_fee = (float)$order['delivery_fee'];
                                            $our_commission = ($delivery_fee * $commission_rate) / 100;
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'accepted' => 'info',
                                                'preparing' => 'primary',
                                                'ready' => 'secondary',
                                                'picked_up' => 'success',
                                                'delivered' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            $status_texts = [
                                                'pending' => 'Bekliyor',
                                                'accepted' => 'Kabul Edildi',
                                                'preparing' => 'Hazırlanıyor',
                                                'ready' => 'Hazır',
                                                'picked_up' => 'Alındı',
                                                'delivered' => 'Teslim Edildi',
                                                'cancelled' => 'İptal Edildi'
                                            ];
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= sanitize($order['order_number'] ?? '') ?></strong>
                                                    <br><small class="text-muted"><?= formatDate($order['created_at'] ?? '') ?></small>
                                                </td>
                                                <td><?= sanitize($order['mekan_name'] ?? '') ?></td>
                                                <td><?= sanitize($order['kurye_name'] ?? 'Atanmadı') ?></td>
                                                <td>
                                                    <?= sanitize($order['customer_name'] ?? '') ?>
                                                    <br><small class="text-muted"><?= formatPhone($order['customer_phone'] ?? '') ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $status_colors[$order['status'] ?? 'unknown'] ?? 'secondary' ?>">
                                                        <?= $status_texts[$order['status'] ?? 'unknown'] ?? ($order['status'] ?? 'Bilinmiyor') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= formatMoney($order['total_amount'] ?? 0) ?></strong>
                                                </td>
                                                <td>
                                                    <strong><?= formatMoney($delivery_fee) ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($order['status'] === 'delivered'): ?>
                                                        <span class="text-success"><?= formatMoney($our_commission) ?></span>
                                                        <br><small class="text-muted">Bizim payımız</small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $delivery_minutes = (int)($order['delivery_time_minutes'] ?? 0);
                                                    if ($delivery_minutes > 0): 
                                                    ?>
                                                        <span class="badge bg-<?= $delivery_minutes <= 30 ? 'success' : ($delivery_minutes <= 60 ? 'warning' : 'danger') ?>">
                                                            <?= $delivery_minutes ?> dk
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
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
    <script>
        function toggleDateFields(filterType) {
            document.getElementById('daily-field').style.display = filterType === 'daily' ? 'block' : 'none';
            document.getElementById('weekly-field').style.display = filterType === 'weekly' ? 'block' : 'none';
            document.getElementById('monthly-field').style.display = filterType === 'monthly' ? 'block' : 'none';
        }
    </script>
</body>
</html>
