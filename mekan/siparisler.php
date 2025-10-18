<?php
/**
 * Kurye Full System - Mekan Siparişleri
 */

require_once '../config/config.php';
requireUserType('mekan');

// Mekan bilgilerini al
try {
    $db = getDB();
    $user_id = getUserId();
    
    $stmt = $db->query("SELECT * FROM mekanlar WHERE user_id = ?", [$user_id]);
    $mekan = $stmt->fetch();
    
    if (!$mekan) {
        throw new Exception("Mekan bilgileri bulunamadı");
    }
    
    $mekan_id = $mekan['id'];
    
    // Filtreleme
    $status_filter = $_GET['status'] ?? 'all';
    $date_filter = $_GET['date'] ?? 'today';
    
    // WHERE koşulları
    $where_conditions = ["s.mekan_id = ?"];
    $params = [$mekan_id];
    
    // Status filtresi
    if ($status_filter !== 'all') {
        if ($status_filter === 'active') {
            $where_conditions[] = "s.status IN ('pending', 'accepted', 'preparing', 'ready', 'picked_up', 'delivering')";
        } else {
            $where_conditions[] = "s.status = ?";
            $params[] = $status_filter;
        }
    }
    
    // Tarih filtresi
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(s.created_at) = CURDATE()";
            break;
        case 'yesterday':
            $where_conditions[] = "DATE(s.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $where_conditions[] = "s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Siparişleri getir
    $stmt = $db->query("
        SELECT s.*, 
               k.user_id as kurye_user_id, 
               u_kurye.full_name as kurye_name,
               u_kurye.phone as kurye_phone,
               k.license_plate,
               k.vehicle_type,
               k.current_latitude,
               k.current_longitude,
               k.is_online
        FROM siparisler s 
        LEFT JOIN kuryeler k ON s.kurye_id = k.id 
        LEFT JOIN users u_kurye ON k.user_id = u_kurye.id 
        {$where_clause}
        ORDER BY s.created_at DESC
    ", $params);
    
    $orders = $stmt->fetchAll();
    
    // İstatistikler
    $stats = [];
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status IN ('pending', 'accepted', 'preparing', 'ready', 'picked_up', 'delivering') THEN 1 END) as active,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
            SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END) as total_revenue
        FROM siparisler 
        WHERE mekan_id = ? AND DATE(created_at) = CURDATE()
    ", [$mekan_id]);
    
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    $error = 'Veriler yüklenemedi: ' . $e->getMessage();
    $orders = [];
    $stats = ['total' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0, 'total_revenue' => 0];
}

// Sipariş durumu güncelleme
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $order_id = (int)$_POST['order_id'];
        $new_status = clean($_POST['new_status']);
        
        // İzin verilen durumlar (mekan sadece bunları değiştirebilir)
        $allowed_statuses = ['accepted', 'preparing', 'ready', 'cancelled'];
        
        if (!in_array($new_status, $allowed_statuses)) {
            throw new Exception('Bu durumu değiştirme yetkiniz yok');
        }
        
        // Siparişin bu mekana ait olduğunu kontrol et
        $stmt = $db->query("SELECT id, status FROM siparisler WHERE id = ? AND mekan_id = ?", [$order_id, $mekan_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception('Sipariş bulunamadı');
        }
        
        $old_status = $order['status'];
        
        // Durum güncelle
        $db->query("UPDATE siparisler SET status = ?, updated_at = NOW() WHERE id = ?", [$new_status, $order_id]);
        
        // Durum geçmişi kaydet
        $db->query("
            INSERT INTO siparis_durum_gecmisi (siparis_id, old_status, new_status, changed_by, notes) 
            VALUES (?, ?, ?, ?, ?)
        ", [$order_id, $old_status, $new_status, $user_id, "Mekan tarafından güncellendi"]);
        
        $success_message = 'Sipariş durumu güncellendi';
        
        // Sayfayı yenile
        header("Location: siparisler.php?status=$status_filter&date=$date_filter&updated=1");
        exit;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siparişler - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-card {
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-pending { border-left-color: #ffc107; }
        .status-accepted { border-left-color: #17a2b8; }
        .status-preparing { border-left-color: #6f42c1; }
        .status-ready { border-left-color: #6c757d; }
        .status-picked_up { border-left-color: #fd7e14; }
        .status-delivering { border-left-color: #20c997; }
        .status-delivered { border-left-color: #28a745; }
        .status-cancelled { border-left-color: #dc3545; }
        
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-shopping-bag me-2 text-success"></i>
                        Siparişler
                    </h1>
                    <div class="btn-toolbar">
                        <div class="btn-group me-2">
                            <a href="yeni-siparis.php" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>
                                Yeni Sipariş
                            </a>
                            <button class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-1"></i>
                                Yenile
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        Sipariş durumu başarıyla güncellendi!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= sanitize($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- İstatistikler -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card text-center">
                            <div class="card-body">
                                <div class="h2 text-primary"><?= number_format($stats['total']) ?></div>
                                <div class="text-muted">Bugünkü Toplam</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card text-center">
                            <div class="card-body">
                                <div class="h2 text-warning"><?= number_format($stats['active']) ?></div>
                                <div class="text-muted">Aktif Siparişler</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card text-center">
                            <div class="card-body">
                                <div class="h2 text-success"><?= number_format($stats['completed']) ?></div>
                                <div class="text-muted">Tamamlanan</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card text-center">
                            <div class="card-body">
                                <div class="h2 text-success"><?= formatMoney($stats['total_revenue']) ?></div>
                                <div class="text-muted">Bugünkü Gelir</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtreler -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Durum Filtresi</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Tüm Siparişler</option>
                                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktif Siparişler</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Bekleyenler</option>
                                    <option value="preparing" <?= $status_filter === 'preparing' ? 'selected' : '' ?>>Hazırlanıyor</option>
                                    <option value="ready" <?= $status_filter === 'ready' ? 'selected' : '' ?>>Hazır</option>
                                    <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Teslim Edildi</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>İptal Edildi</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tarih Filtresi</label>
                                <select name="date" class="form-select" onchange="this.form.submit()">
                                    <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Bugün</option>
                                    <option value="yesterday" <?= $date_filter === 'yesterday' ? 'selected' : '' ?>>Dün</option>
                                    <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>Bu Hafta</option>
                                    <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>Bu Ay</option>
                                    <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>Tüm Zamanlar</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i>
                                        Filtrele
                                    </button>
                                    <a href="siparisler.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        Temizle
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Siparişler -->
                <div class="row g-3">
                    <?php if (empty($orders)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5>Sipariş bulunamadı</h5>
                                    <p class="text-muted">Seçilen kriterlere uygun sipariş bulunmuyor.</p>
                                    <a href="yeni-siparis.php" class="btn btn-success">
                                        <i class="fas fa-plus me-2"></i>
                                        İlk Siparişi Oluştur
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="col-lg-6">
                                <div class="card order-card status-<?= $order['status'] ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="card-title mb-1">
                                                    <strong><?= sanitize($order['order_number']) ?></strong>
                                                    <?php if ($order['priority'] !== 'normal'): ?>
                                                        <span class="badge bg-<?= $order['priority'] === 'urgent' ? 'danger' : 'warning' ?> ms-2">
                                                            <?= $order['priority'] === 'urgent' ? 'ACİL' : 'EKSPRES' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?= formatDate($order['created_at']) ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="h5 text-success mb-0"><?= formatMoney($order['total_amount']) ?></div>
                                                <?php if ($order['delivery_fee'] > 0): ?>
                                                    <small class="text-muted">+<?= formatMoney($order['delivery_fee']) ?> teslimat</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Müşteri:</strong><br>
                                                <?= sanitize($order['customer_name']) ?><br>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <a href="tel:<?= $order['customer_phone'] ?>"><?= formatPhone($order['customer_phone']) ?></a>
                                                </small>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Kurye:</strong><br>
                                                <?php if ($order['kurye_name']): ?>
                                                    <?= sanitize($order['kurye_name']) ?>
                                                    <?php if ($order['is_online']): ?>
                                                        <span class="badge bg-success badge-sm ms-1">Online</span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-phone me-1"></i>
                                                        <a href="tel:<?= $order['kurye_phone'] ?>"><?= formatPhone($order['kurye_phone']) ?></a>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">Kurye atanmadı</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-8">
                                                <strong>Adres:</strong><br>
                                                <small><?= sanitize($order['customer_address']) ?></small>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Ödeme:</strong><br>
                                                <?php
                                                $payment_method = $order['payment_method'] ?? 'nakit';
                                                $payment_icons = [
                                                    'nakit' => 'fas fa-money-bill-wave text-success',
                                                    'kapida_kart' => 'fas fa-credit-card text-primary', 
                                                    'online_kart' => 'fas fa-globe text-info'
                                                ];
                                                $payment_labels = [
                                                    'nakit' => 'Nakit',
                                                    'kapida_kart' => 'Kapıda Kart',
                                                    'online_kart' => 'Online Kart'
                                                ];
                                                ?>
                                                <small>
                                                    <i class="<?= $payment_icons[$payment_method] ?? $payment_icons['nakit'] ?> me-1"></i>
                                                    <?= $payment_labels[$payment_method] ?? 'Nakit' ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <?php if ($order['notes']): ?>
                                            <div class="mb-3">
                                                <strong>Notlar:</strong><br>
                                                <small class="text-muted"><?= sanitize($order['notes']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
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
                                                <span class="badge bg-<?= $status_colors[$order['status']] ?? 'secondary' ?> px-3 py-2">
                                                    <?= $status_texts[$order['status']] ?? $order['status'] ?>
                                                </span>
                                            </div>
                                            <div>
                                                <?php if (in_array($order['status'], ['pending', 'accepted', 'preparing'])): ?>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($order['status'] === 'pending'): ?>
                                                            <button class="btn btn-success" onclick="updateOrderStatus(<?= $order['id'] ?>, 'accepted')">
                                                                <i class="fas fa-check me-1"></i>Kabul Et
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($order['status'] === 'accepted'): ?>
                                                            <button class="btn btn-primary" onclick="updateOrderStatus(<?= $order['id'] ?>, 'preparing')">
                                                                <i class="fas fa-fire me-1"></i>Hazırlanıyor
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($order['status'] === 'preparing'): ?>
                                                            <button class="btn btn-secondary" onclick="updateOrderStatus(<?= $order['id'] ?>, 'ready')">
                                                                <i class="fas fa-bell me-1"></i>Hazır
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button class="btn btn-outline-danger" onclick="updateOrderStatus(<?= $order['id'] ?>, 'cancelled')">
                                                            <i class="fas fa-times me-1"></i>İptal
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateOrderStatus(orderId, newStatus) {
            const statusTexts = {
                'accepted': 'kabul etmek',
                'preparing': 'hazırlanıyor olarak işaretlemek',
                'ready': 'hazır olarak işaretlemek',
                'cancelled': 'iptal etmek'
            };
            
            const confirmText = statusTexts[newStatus] || 'durumunu değiştirmek';
            
            if (confirm(`Bu siparişi ${confirmText} istediğinizden emin misiniz?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="${orderId}">
                    <input type="hidden" name="new_status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto refresh every 30 seconds for active orders
        <?php if ($status_filter === 'active' || $status_filter === 'all'): ?>
        setTimeout(() => {
            location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>
