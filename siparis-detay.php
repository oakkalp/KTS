<?php
/**
 * Kurye Full System - Sipariş Detay Sayfası
 * Admin ve Mekan kullanıcıları için ortak sayfa
 */

require_once 'config/config.php';

// Giriş kontrolü
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_type = getUserType();
$user_id = getUserId();

// Sadece admin ve mekan kullanıcıları erişebilir
if (!in_array($user_type, ['admin', 'mekan'])) {
    header('Location: index.php');
    exit;
}

// Sipariş ID kontrolü
$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    header('Location: ' . ($user_type === 'admin' ? 'admin/siparisler.php' : 'mekan/siparisler.php'));
    exit;
}

$db = getDB();


// Sipariş bilgilerini getir
try {
    $query = "
        SELECT s.*, 
               m.mekan_name, m.address as mekan_address, m.phone as mekan_phone,
               u_mekan.username as mekan_username,
               k.user_id as kurye_user_id, 
               u_kurye.full_name as kurye_name,
               u_kurye.phone as kurye_phone,
               k.license_plate, k.vehicle_type, k.is_online,
               k.current_latitude, k.current_longitude
        FROM siparisler s 
        LEFT JOIN mekanlar m ON s.mekan_id = m.id 
        LEFT JOIN users u_mekan ON m.user_id = u_mekan.id
        LEFT JOIN kuryeler k ON s.kurye_id = k.id 
        LEFT JOIN users u_kurye ON k.user_id = u_kurye.id 
        WHERE s.id = ?
    ";
    
    $params = [$order_id];
    
    // Mekan kullanıcısı sadece kendi siparişlerini görebilir
    if ($user_type === 'mekan') {
        $query .= " AND m.user_id = ?";
        $params[] = $user_id;
    }
    
    $order = $db->query($query, $params)->fetch();
    
    if (!$order) {
        throw new Exception('Sipariş bulunamadı veya erişim yetkiniz yok.');
    }
    
    // Sipariş detaylarını parse et
    $order_details = json_decode($order['order_details'], true) ?? [];
    
    // Konum geçmişini getir (sadece admin görebilir)
    $location_history = [];
    if ($user_type === 'admin' && $order['kurye_id']) {
        $location_history = $db->query("
            SELECT latitude, longitude, accuracy, speed, created_at
            FROM kurye_konum_gecmisi 
            WHERE kurye_id = ? AND siparis_id = ?
            ORDER BY created_at DESC
            LIMIT 20
        ", [$order['kurye_id'], $order_id])->fetchAll();
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $order = null;
}

// Durum isimleri ve renkleri
$status_names = [
    'pending' => 'Bekliyor',
    'accepted' => 'Kabul Edildi',
    'preparing' => 'Hazırlanıyor',
    'ready' => 'Hazır',
    'picked_up' => 'Alındı',
    'delivering' => 'Teslimatta',
    'delivered' => 'Teslim Edildi',
    'cancelled' => 'İptal Edildi'
];

$status_colors = [
    'pending' => 'warning',
    'accepted' => 'info', 
    'preparing' => 'primary',
    'ready' => 'success',
    'picked_up' => 'dark',
    'delivering' => 'secondary',
    'delivered' => 'success',
    'cancelled' => 'danger'
];

$payment_icons = [
    'nakit' => 'fa-money-bill',
    'kapida_kart' => 'fa-credit-card', 
    'online_kart' => 'fa-globe'
];

$payment_names = [
    'nakit' => 'Nakit',
    'kapida_kart' => 'Kapıda Kart',
    'online_kart' => 'Online Kart'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Detay #<?= $order ? $order['order_number'] : 'Bulunamadı' ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .detail-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 1.5rem;
        }
        .detail-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
        }
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -0.5rem;
            top: 0.3rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: white;
            border: 3px solid #6c757d;
        }
        .timeline-item.completed::before {
            border-color: #198754;
            background: #198754;
        }
        .timeline-item.current::before {
            border-color: #0d6efd;
            background: #0d6efd;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(13, 110, 253, 0); }
            100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
        }
        .info-row {
            border-bottom: 1px solid #f8f9fa;
            padding: 0.75rem 0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .back-btn {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
        }
        .map-container {
            height: 300px;
            border-radius: 10px;
            overflow: hidden;
        }
        .map-container.large {
            height: 400px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-receipt me-2"></i>Sipariş Detayı</h2>
                    <a href="<?= $user_type === 'admin' ? 'admin/siparisler.php' : 'mekan/siparisler.php' ?>" 
                       class="btn btn-outline-primary back-btn">
                        <i class="fas fa-arrow-left me-2"></i>Geri Dön
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    </div>
                <?php else: ?>

                    <div class="row">
                        <!-- Sol Kolon -->
                        <div class="col-md-8">
                            
                            <!-- Sipariş Bilgileri -->
                            <div class="detail-card card">
                                <div class="detail-header">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h4 class="mb-1"><?= $order['order_number'] ?></h4>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-<?= $status_colors[$order['status']] ?> me-2">
                                                    <?= $status_names[$order['status']] ?>
                                                </span>
                                                <small><?= formatDate($order['created_at']) ?></small>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="text-end">
                                                <h3 class="mb-0"><?= formatMoney($order['total_amount']) ?></h3>
                                                <small>Toplam Tutar</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-user me-2"></i>Müşteri Bilgileri</h6>
                                            <div class="info-row">
                                                <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                                            </div>
                                            <div class="info-row">
                                                <i class="fas fa-phone me-2"></i><?= formatPhone($order['customer_phone']) ?>
                                            </div>
                                            <div class="info-row">
                                                <i class="fas fa-map-marker-alt me-2"></i>
                                                <?= htmlspecialchars($order['customer_address']) ?>
                                                <?php if ($order['customer_latitude'] && $order['customer_longitude']): ?>
                                                    <br>
                                                    <a href="https://maps.google.com/?q=<?= $order['customer_latitude'] ?>,<?= $order['customer_longitude'] ?>" 
                                                       target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                                        <i class="fas fa-external-link-alt me-1"></i>Haritada Aç
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-store me-2"></i>Mekan Bilgileri</h6>
                                            <div class="info-row">
                                                <strong><?= htmlspecialchars($order['mekan_name']) ?></strong>
                                            </div>
                                            <?php if ($user_type === 'admin'): ?>
                                                <div class="info-row">
                                                    <i class="fas fa-user me-2"></i>@<?= htmlspecialchars($order['mekan_username']) ?>
                                                </div>
                                                <div class="info-row">
                                                    <i class="fas fa-phone me-2"></i><?= formatPhone($order['mekan_phone']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="info-row">
                                                <i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($order['mekan_address']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>

                            <!-- Sipariş İçeriği -->
                            <?php if (!empty($order_details['items'])): ?>
                                <div class="detail-card card">
                                    <div class="card-header bg-light">
                                        <h6><i class="fas fa-shopping-bag me-2"></i>Sipariş İçeriği</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Ürün</th>
                                                        <th>Adet</th>
                                                        <th>Birim Fiyat</th>
                                                        <th>Toplam</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($order_details['items'] as $item): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                                                            <td><?= (int)($item['quantity'] ?? 0) ?></td>
                                                            <td><?= formatMoney($item['price'] ?? 0) ?></td>
                                                            <td><?= formatMoney(($item['quantity'] ?? 0) * ($item['price'] ?? 0)) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="table-light">
                                                    <tr>
                                                        <th colspan="3">Ara Toplam</th>
                                                        <th><?= formatMoney($order_details['total'] ?? $order['total_amount']) ?></th>
                                                    </tr>
                                                    <tr>
                                                        <th colspan="3">Teslimat Ücreti</th>
                                                        <th><?= formatMoney($order['delivery_fee']) ?></th>
                                                    </tr>
                                                    <tr class="table-primary">
                                                        <th colspan="3">Genel Toplam</th>
                                                        <th><?= formatMoney($order['total_amount'] + $order['delivery_fee']) ?></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Kurye Bilgileri -->
                            <?php if ($order['kurye_name']): ?>
                                <div class="detail-card card">
                                    <div class="card-header bg-light">
                                        <h6><i class="fas fa-motorcycle me-2"></i>Kurye Bilgileri</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <h6 class="mb-1"><?= htmlspecialchars($order['kurye_name']) ?></h6>
                                                <div class="text-muted">
                                                    <i class="fas fa-car me-1"></i><?= ucfirst($order['vehicle_type']) ?>
                                                    <?php if ($order['license_plate']): ?>
                                                        - <?= htmlspecialchars($order['license_plate']) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($user_type === 'admin' && $order['kurye_phone']): ?>
                                                    <div class="text-muted">
                                                        <i class="fas fa-phone me-1"></i><?= formatPhone($order['kurye_phone']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-auto">
                                                <span class="badge bg-<?= $order['is_online'] ? 'success' : 'secondary' ?> fs-6">
                                                    <?= $order['is_online'] ? 'Online' : 'Offline' ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Canlı Konum (sadece admin ve teslim olmamış siparişler) -->
                                        <?php if ($user_type === 'admin' && $order['current_latitude'] && $order['current_longitude'] && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                            <div class="mt-3">
                                                <small class="text-muted">Son Bilinen Konum:</small>
                                                <div class="map-container">
                                                    <iframe 
                                                        src="https://maps.google.com/maps?q=<?= $order['current_latitude'] ?>,<?= $order['current_longitude'] ?>&output=embed" 
                                                        width="100%" height="100%" frameborder="0" style="border:0">
                                                    </iframe>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Sağ Kolon -->
                        <div class="col-md-4">
                            
                            <!-- Sipariş Durumu Timeline -->
                            <div class="detail-card card">
                                <div class="card-header bg-light">
                                    <h6><i class="fas fa-route me-2"></i>Sipariş Durumu</h6>
                                </div>
                                <div class="card-body">
                                    <div class="timeline">
                                        <?php
                                        $statuses = ['pending', 'accepted', 'preparing', 'ready', 'picked_up', 'delivering', 'delivered'];
                                        $current_status = $order['status'];
                                        $current_index = array_search($current_status, $statuses);
                                        
                                        foreach ($statuses as $index => $status):
                                            $is_completed = $index < $current_index || $current_status === 'delivered';
                                            $is_current = $status === $current_status && $current_status !== 'delivered';
                                            $class = $is_completed ? 'completed' : ($is_current ? 'current' : '');
                                        ?>
                                            <div class="timeline-item <?= $class ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold"><?= $status_names[$status] ?></span>
                                                    <?php if ($is_completed || $is_current): ?>
                                                        <i class="fas fa-check text-success"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($status === $current_status): ?>
                                                    <small class="text-muted">Mevcut durum</small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if ($current_status === 'cancelled'): ?>
                                            <div class="timeline-item current">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold text-danger">İptal Edildi</span>
                                                    <i class="fas fa-times text-danger"></i>
                                                </div>
                                                <small class="text-muted">
                                                    <?= $order['cancelled_at'] ? formatDate($order['cancelled_at']) : '' ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Ödeme Bilgileri -->
                            <div class="detail-card card">
                                <div class="card-header bg-light">
                                    <h6><i class="fas fa-credit-card me-2"></i>Ödeme Bilgileri</h6>
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <strong>Ödeme Yöntemi</strong>
                                        <div class="mt-1">
                                            <i class="fas <?= $payment_icons[$order['payment_method']] ?? 'fa-question' ?> me-2"></i>
                                            <?= $payment_names[$order['payment_method']] ?? 'Belirtilmemiş' ?>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <strong>Sipariş Tutarı</strong>
                                        <div class="text-end"><?= formatMoney($order['total_amount']) ?></div>
                                    </div>
                                    <div class="info-row">
                                        <strong>Teslimat Ücreti</strong>
                                        <div class="text-end"><?= formatMoney($order['delivery_fee']) ?></div>
                                    </div>
                                    <div class="info-row border-top pt-2">
                                        <strong>Toplam</strong>
                                        <div class="text-end">
                                            <h5 class="text-primary mb-0">
                                                <?= formatMoney($order['total_amount'] + $order['delivery_fee']) ?>
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Zaman Bilgileri -->
                            <div class="detail-card card">
                                <div class="card-header bg-light">
                                    <h6><i class="fas fa-clock me-2"></i>Zaman Bilgileri</h6>
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <strong>Sipariş Verildi</strong>
                                        <div><?= formatDate($order['created_at']) ?></div>
                                    </div>
                                    <?php if ($order['accepted_at']): ?>
                                        <div class="info-row">
                                            <strong>Kabul Edildi</strong>
                                            <div><?= formatDate($order['accepted_at']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($order['picked_up_at']): ?>
                                        <div class="info-row">
                                            <strong>Alındı</strong>
                                            <div><?= formatDate($order['picked_up_at']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($order['delivered_at']): ?>
                                        <div class="info-row">
                                            <strong>Teslim Edildi</strong>
                                            <div><?= formatDate($order['delivered_at']) ?></div>
                                        </div>
                                        <div class="info-row">
                                            <strong>Teslimat Süresi</strong>
                                            <div>
                                                <?php
                                                $start = new DateTime($order['created_at']);
                                                $end = new DateTime($order['delivered_at']);
                                                $diff = $start->diff($end);
                                                echo $diff->format('%h saat %i dakika');
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Notlar -->
                            <?php if ($order['notes']): ?>
                                <div class="detail-card card">
                                    <div class="card-header bg-light">
                                        <h6><i class="fas fa-sticky-note me-2"></i>Notlar</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sayfa her 30 saniyede bir yenilensin (canlı takip için)
        setTimeout(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
