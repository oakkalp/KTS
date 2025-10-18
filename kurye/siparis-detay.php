<?php
require_once '../config/config.php';

// Session kontrolü
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();
$user_type = getUserType();

// Sipariş bilgilerini al
try {
    if ($user_type === 'admin') {
        $stmt = $db->query("
            SELECT s.*, 
                   u.username as kurye_username,
                   k.full_name as kurye_name,
                   m.username as mekan_username,
                   m.full_name as mekan_name
            FROM siparisler s
            LEFT JOIN users u ON s.kurye_id = u.id
            LEFT JOIN kuryeler k ON s.kurye_id = k.id
            LEFT JOIN users m ON s.mekan_id = m.id
            WHERE s.id = ?
        ", [$order_id]);
    } elseif ($user_type === 'kurye') {
        $kurye_id = getKuryeId();
        $stmt = $db->query("
            SELECT s.*, 
                   m.username as mekan_username,
                   m.full_name as mekan_name
            FROM siparisler s
            LEFT JOIN users m ON s.mekan_id = m.id
            WHERE s.id = ? AND s.kurye_id = ?
        ", [$order_id, $kurye_id]);
    } elseif ($user_type === 'mekan') {
        $mekan_id = getMekanId();
        $stmt = $db->query("
            SELECT s.*, 
                   u.username as kurye_username,
                   k.full_name as kurye_name
            FROM siparisler s
            LEFT JOIN users u ON s.kurye_id = u.id
            LEFT JOIN kuryeler k ON s.kurye_id = k.id
            WHERE s.id = ? AND s.mekan_id = ?
        ", [$order_id, $mekan_id]);
    } else {
        throw new Exception('Yetkisiz erişim');
    }
    
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Sipariş bulunamadı');
    }
    
} catch (Exception $e) {
    die('Hata: ' . $e->getMessage());
}

// Durum renkleri
$status_colors = [
    'pending' => 'warning',
    'accepted' => 'info',
    'preparing' => 'primary',
    'ready' => 'success',
    'picked_up' => 'dark',
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

$payment_methods = [
    'nakit' => 'Nakit',
    'kapida_kart' => 'Kapıda Kredi Kartı',
    'online_kart' => 'Online Kredi Kartı'
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Detayı - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.9em;
            padding: 0.5em 1em;
        }
        .info-card {
            border-left: 4px solid #007bff;
        }
        .address-card {
            border-left: 4px solid #28a745;
        }
        .map-link {
            color: #007bff;
            text-decoration: none;
        }
        .map-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h2><i class="fas fa-receipt me-2"></i>Sipariş Detayı #<?= $order['id'] ?></h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Geri Dön
                        </a>
                        <?php if ($user_type === 'admin'): ?>
                        <a href="../admin/siparisler.php" class="btn btn-primary">
                            <i class="fas fa-list me-2"></i>Tüm Siparişler
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sol Kolon -->
            <div class="col-md-8">
                <!-- Sipariş Bilgileri -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Sipariş Bilgileri</h5>
                        <span class="badge bg-<?= $status_colors[$order['status']] ?> status-badge">
                            <?= $status_texts[$order['status']] ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Sipariş No:</strong></td>
                                        <td>#<?= $order['id'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Oluşturulma:</strong></td>
                                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                    </tr>
                                    <?php if ($order['accepted_at']): ?>
                                    <tr>
                                        <td><strong>Kabul Edilme:</strong></td>
                                        <td><?= date('d.m.Y H:i', strtotime($order['accepted_at'])) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($order['delivered_at']): ?>
                                    <tr>
                                        <td><strong>Teslim Edilme:</strong></td>
                                        <td><?= date('d.m.Y H:i', strtotime($order['delivered_at'])) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Tutar:</strong></td>
                                        <td><span class="h5 text-success"><?= number_format($order['total_amount'], 2) ?> ₺</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Teslimat Ücreti:</strong></td>
                                        <td><?= number_format($order['delivery_fee'], 2) ?> ₺</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Ödeme Yöntemi:</strong></td>
                                        <td>
                                            <i class="fas fa-<?= $order['payment_method'] === 'nakit' ? 'money-bill' : 'credit-card' ?> me-1"></i>
                                            <?= $payment_methods[$order['payment_method']] ?? $order['payment_method'] ?>
                                        </td>
                                    </tr>
                                    <?php if ($order['preparation_time']): ?>
                                    <tr>
                                        <td><strong>Hazırlık Süresi:</strong></td>
                                        <td>
                                            <i class="fas fa-clock me-1 text-warning"></i>
                                            <?= $order['preparation_time'] ?> dakika
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Müşteri Bilgileri -->
                <div class="card mb-4 address-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Müşteri Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Müşteri Adı:</strong></td>
                                        <td><?= htmlspecialchars($order['customer_name'] ?? 'Belirtilmemiş') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Telefon:</strong></td>
                                        <td>
                                            <?php if ($order['customer_phone']): ?>
                                                <a href="tel:<?= $order['customer_phone'] ?>" class="text-decoration-none">
                                                    <i class="fas fa-phone me-1"></i><?= htmlspecialchars($order['customer_phone']) ?>
                                                </a>
                                            <?php else: ?>
                                                Belirtilmemiş
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Teslimat Adresi:</strong></td>
                                        <td>
                                            <?php if (!empty($order['delivery_address'])): ?>
                                                <div class="mb-2">
                                                    <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                                                    <?= htmlspecialchars($order['delivery_address']) ?>
                                                </div>
                                                <a href="https://maps.google.com/maps?q=<?= urlencode($order['delivery_address']) ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-primary map-link">
                                                    <i class="fas fa-map me-1"></i>Haritada Aç
                                                </a>
                                            <?php else: ?>
                                                Belirtilmemiş
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon -->
            <div class="col-md-4">
                <!-- Kurye Bilgileri -->
                <?php if ($order['kurye_id'] && ($user_type === 'admin' || $user_type === 'mekan')): ?>
                <div class="card mb-4 info-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-motorcycle me-2"></i>Kurye Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Kurye:</strong></td>
                                <td><?= htmlspecialchars($order['kurye_name'] ?? $order['kurye_username'] ?? 'Belirtilmemiş') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Kullanıcı Adı:</strong></td>
                                <td><?= htmlspecialchars($order['kurye_username'] ?? 'Belirtilmemiş') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Mekan Bilgileri -->
                <?php if ($user_type === 'admin' || $user_type === 'kurye'): ?>
                <div class="card mb-4 info-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-store me-2"></i>Mekan Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Mekan:</strong></td>
                                <td><?= htmlspecialchars($order['mekan_name'] ?? $order['mekan_username'] ?? 'Belirtilmemiş') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Kullanıcı Adı:</strong></td>
                                <td><?= htmlspecialchars($order['mekan_username'] ?? 'Belirtilmemiş') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sipariş Notları -->
                <?php if ($order['notes']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Sipariş Notları</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Hazırlık Süresi Uyarısı -->
                <?php if ($order['preparation_time'] && $order['status'] === 'accepted' && $user_type === 'kurye'): ?>
                <div class="alert alert-warning">
                    <h6><i class="fas fa-clock me-2"></i>Hazırlık Süresi</h6>
                    <p class="mb-0">
                        Restoran <?= $order['preparation_time'] ?> dakika sonra siparişinizi hazırlayacak.
                        <br><small class="text-muted">Bu süre sonunda mekana gitmeniz önerilir.</small>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
