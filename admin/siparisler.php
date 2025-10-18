<?php
/**
 * Kurye Full System - Admin Sipariş Yönetimi
 */

require_once '../config/config.php';
requireUserType('admin');

$db = getDB();

// İşlemler
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'assign_courier':
                $order_id = (int)$_POST['order_id'];
                $courier_id = (int)$_POST['courier_id'];
                
                $db->query("UPDATE siparisler SET kurye_id = ?, status = 'accepted' WHERE id = ?", 
                          [$courier_id, $order_id]);
                
                // Kuryeye bildirim gönder
                $stmt = $db->query("
                    SELECT u.device_token, s.order_number, m.mekan_name, s.customer_name, s.customer_address, 
                           s.preparation_time, s.created_at
                    FROM users u
                    JOIN kuryeler k ON u.id = k.user_id
                    JOIN siparisler s ON s.id = ?
                    JOIN mekanlar m ON s.mekan_id = m.id
                    WHERE k.id = ? AND u.device_token IS NOT NULL
                ", [$order_id, $courier_id]);
                
                $courier_info = $stmt->fetch();
                
                if ($courier_info && $courier_info['device_token']) {
                    $title = "Sipariş Yönlendirildi";
                    
                    // Hazırlık süresi hesapla
                    $ready_time = '';
                    if ($courier_info['preparation_time']) {
                        $ready_time = " {$courier_info['preparation_time']} dakika sonra hazır olacak.";
                    }
                    
                    $message = "ORD{$courier_info['order_number']} numaralı sipariş - {$courier_info['mekan_name']} size yönlendirildi.{$ready_time} Mekanda olun.";
                    
                    $notification_data = [
                        'type' => 'order_assigned',
                        'order_id' => (string)$order_id,
                        'order_number' => $courier_info['order_number'],
                        'restaurant_name' => $courier_info['mekan_name'],
                        'customer_name' => $courier_info['customer_name'],
                        'customer_address' => $courier_info['customer_address']
                    ];
                    
                    // Debug log
                    writeLog("Sending notification to courier ID: $courier_id, Token: " . substr($courier_info['device_token'], 0, 20) . "...", 'INFO', 'notifications.log');
                    writeLog("Notification data: " . json_encode($notification_data), 'INFO', 'notifications.log');
                    
                    $notification_sent = sendPushNotification(
                        $courier_info['device_token'],
                        $title,
                        $message,
                        $notification_data
                    );
                    
                    if ($notification_sent) {
                        writeLog("✅ Courier assignment notification sent successfully to courier ID: $courier_id for order ID: $order_id", 'INFO', 'notifications.log');
                    } else {
                        writeLog("❌ Failed to send courier assignment notification to courier ID: $courier_id for order ID: $order_id", 'ERROR', 'notifications.log');
                    }
                } else {
                    if (!$courier_info) {
                        writeLog("❌ Courier info not found for courier ID: $courier_id", 'ERROR', 'notifications.log');
                    } else {
                        writeLog("❌ No device token found for courier ID: $courier_id", 'ERROR', 'notifications.log');
                    }
                }
                
                $message = "Kurye başarıyla atandı ve sipariş kabul edildi.";
                break;
                
            case 'cancel_order':
                $order_id = (int)$_POST['order_id'];
                
                $db->query("UPDATE siparisler SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", 
                          [$order_id]);
                
                $message = "Sipariş başarıyla iptal edildi.";
                break;
                
            case 'delete_order':
                $order_id = (int)$_POST['order_id'];
                
                // İlgili tüm verileri sil
                $db->beginTransaction();
                
                // Konum geçmişini sil
                $db->query("DELETE FROM kurye_konum_gecmisi WHERE siparis_id = ?", [$order_id]);
                
                // Siparişi sil
                $db->query("DELETE FROM siparisler WHERE id = ?", [$order_id]);
                
                $db->commit();
                
                $message = "Sipariş ve tüm ilgili veriler başarıyla silindi.";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        if ($db->inTransaction()) {
            $db->rollback();
        }
    }
}

// Filtreleme
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'today';
$mekan_filter = $_GET['mekan'] ?? 'all';

// WHERE koşulları
$where_conditions = [];
$params = [];

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

// Mekan filtresi
if ($mekan_filter !== 'all') {
    $where_conditions[] = "s.mekan_id = ?";
    $params[] = (int)$mekan_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Siparişleri getir
$orders = $db->query("
    SELECT s.*, 
           m.mekan_name,
           k.user_id as kurye_user_id, 
           u_kurye.full_name as kurye_name,
           u_kurye.phone as kurye_phone,
           k.license_plate,
           k.vehicle_type,
           k.is_online
    FROM siparisler s 
    LEFT JOIN mekanlar m ON s.mekan_id = m.id 
    LEFT JOIN kuryeler k ON s.kurye_id = k.id 
    LEFT JOIN users u_kurye ON k.user_id = u_kurye.id 
    {$where_clause}
    ORDER BY s.created_at DESC
    LIMIT 50
", $params)->fetchAll();

// İstatistikler
$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status IN ('pending', 'accepted', 'preparing', 'ready', 'picked_up', 'delivering') THEN 1 END) as active,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
        SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END) as total_revenue
    FROM siparisler s 
    {$where_clause}
", $params)->fetch();

// Mekan ve kurye listeleri
$mekanlar = $db->query("SELECT id, mekan_name FROM mekanlar ORDER BY mekan_name")->fetchAll();
$kuryeler = $db->query("
    SELECT k.id, u.full_name, k.is_online, k.vehicle_type 
    FROM kuryeler k 
    JOIN users u ON k.user_id = u.id 
    ORDER BY u.full_name
")->fetchAll();

// Durum renkleri
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
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Yönetimi - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 1rem;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }
        .order-header {
            border-radius: 15px 15px 0 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
        }
        .order-body {
            padding: 15px;
        }
        .order-actions {
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
            padding: 10px 15px;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .stat-card {
            border-radius: 15px;
            border: none;
            overflow: hidden;
        }
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 10px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-shopping-bag me-2"></i>Sipariş Yönetimi</h1>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- İstatistik Kartları -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                <h4><?= $stats['total'] ?></h4>
                                <small>Toplam Sipariş</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h4><?= $stats['active'] ?></h4>
                                <small>Aktif Sipariş</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h4><?= $stats['completed'] ?></h4>
                                <small>Tamamlanan</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-times-circle fa-2x mb-2"></i>
                                <h4><?= $stats['cancelled'] ?></h4>
                                <small>İptal Edilen</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Durum</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Tümü</option>
                                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Bekliyor</option>
                                    <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Teslim Edildi</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>İptal</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tarih</label>
                                <select name="date" class="form-select form-select-sm">
                                    <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Bugün</option>
                                    <option value="yesterday" <?= $date_filter === 'yesterday' ? 'selected' : '' ?>>Dün</option>
                                    <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>Son 7 Gün</option>
                                    <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>Son 30 Gün</option>
                                    <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>Tümü</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mekan</label>
                                <select name="mekan" class="form-select form-select-sm">
                                    <option value="all">Tüm Mekanlar</option>
                                    <?php foreach ($mekanlar as $mekan): ?>
                                        <option value="<?= $mekan['id'] ?>" <?= $mekan_filter == $mekan['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($mekan['mekan_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-filter me-1"></i>Filtrele
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Siparişler -->
                <div class="row">
                    <?php if (empty($orders)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle fa-3x mb-3"></i>
                                <h5>Henüz sipariş bulunmuyor</h5>
                                <p>Seçilen filtrelere göre sipariş bulunamadı.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="col-xl-4 col-lg-6 col-md-12 mb-3">
                                <div class="order-card card h-100">
                                    <!-- Header -->
                                    <div class="order-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= $order['order_number'] ?></h6>
                                                <small><?= formatDate($order['created_at']) ?></small>
                                            </div>
                                            <span class="status-badge badge bg-<?= $status_colors[$order['status']] ?>">
                                                <?= $status_names[$order['status']] ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Body -->
                                    <div class="order-body flex-grow-1">
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <small class="text-muted">Mekan:</small>
                                                <div class="fw-bold"><?= htmlspecialchars($order['mekan_name']) ?></div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Müşteri:</small>
                                                <div class="fw-bold"><?= htmlspecialchars($order['customer_name']) ?></div>
                                            </div>
                                        </div>

                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <small class="text-muted">Tutar:</small>
                                                <div class="fw-bold text-success"><?= formatMoney($order['total_amount']) ?></div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Teslimat:</small>
                                                <div class="fw-bold text-primary"><?= formatMoney($order['delivery_fee']) ?></div>
                                            </div>
                                        </div>

                                        <?php if ($order['kurye_name']): ?>
                                            <div class="alert alert-info py-2 mb-2">
                                                <i class="fas fa-motorcycle me-2"></i>
                                                <strong><?= htmlspecialchars($order['kurye_name']) ?></strong>
                                                <br>
                                                <small>
                                                    <?= ucfirst($order['vehicle_type']) ?> - 
                                                    <span class="badge bg-<?= $order['is_online'] ? 'success' : 'secondary' ?>">
                                                        <?= $order['is_online'] ? 'Online' : 'Offline' ?>
                                                    </span>
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning py-2 mb-2">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                <strong>Kurye atanmamış</strong>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mb-2">
                                            <small class="text-muted">Adres:</small>
                                            <div class="small"><?= htmlspecialchars($order['customer_address']) ?></div>
                                        </div>

                                        <?php if ($order['notes']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted">Not:</small>
                                                <div class="small"><?= htmlspecialchars($order['notes']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Actions -->
                                    <div class="order-actions">
                                        <div class="btn-group btn-group-sm w-100" role="group">
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <button class="btn btn-success" onclick="showAssignModal(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number']) ?>')">
                                                    <i class="fas fa-user-plus"></i> Kurye Ata
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($order['status'], ['pending', 'accepted', 'preparing'])): ?>
                                                <button class="btn btn-warning" onclick="cancelOrder(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number']) ?>')">
                                                    <i class="fas fa-times"></i> İptal
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-danger" onclick="deleteOrder(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number']) ?>')">
                                                <i class="fas fa-trash"></i> Sil
                                            </button>
                                            
                                            <button class="btn btn-info" onclick="showOrderDetails(<?= $order['id'] ?>)">
                                                <i class="fas fa-eye"></i> Detay
                                            </button>
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

    <!-- Kurye Atama Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kurye Ata</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_courier">
                        <input type="hidden" name="order_id" id="assignOrderId">
                        
                        <div class="mb-3">
                            <label class="form-label">Sipariş</label>
                            <input type="text" class="form-control" id="assignOrderNumber" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kurye Seç</label>
                            <select name="courier_id" class="form-select" required>
                                <option value="">Kurye seçin...</option>
                                <?php foreach ($kuryeler as $kurye): ?>
                                    <option value="<?= $kurye['id'] ?>">
                                        <?= htmlspecialchars($kurye['full_name']) ?> 
                                        (<?= ucfirst($kurye['vehicle_type']) ?>) 
                                        - <?= $kurye['is_online'] ? '🟢 Online' : '🔴 Offline' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">Kurye Ata</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showAssignModal(orderId, orderNumber) {
            document.getElementById('assignOrderId').value = orderId;
            document.getElementById('assignOrderNumber').value = orderNumber;
            new bootstrap.Modal(document.getElementById('assignModal')).show();
        }

        function cancelOrder(orderId, orderNumber) {
            if (confirm(`"${orderNumber}" numaralı siparişi iptal etmek istediğinizden emin misiniz?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel_order">
                    <input type="hidden" name="order_id" value="${orderId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteOrder(orderId, orderNumber) {
            if (confirm(`"${orderNumber}" numaralı siparişi ve TÜM İLGİLİ VERİLERİ kalıcı olarak silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz!`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_order">
                    <input type="hidden" name="order_id" value="${orderId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showOrderDetails(orderId) {
            // Sipariş detayı için ayrı sayfa açılabilir
            window.open(`siparis-detay.php?id=${orderId}`, '_blank');
        }
    </script>
</body>
</html>