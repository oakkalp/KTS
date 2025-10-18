<?php
/**
 * Kurye Full System - Kurye Yeni Siparişler
 */

require_once '../config/config.php';
requireUserType('kurye');

// Kurye ID'sini al
$kurye_id = getKuryeId();

// Yeni siparişleri getir (kurye atanmamış)
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT s.*, m.mekan_name, m.address as mekan_address, m.phone as mekan_phone,
               u.full_name as mekan_contact, u.phone as mekan_contact_phone
        FROM siparisler s 
        JOIN mekanlar m ON s.mekan_id = m.id 
        JOIN users u ON m.user_id = u.id
        WHERE s.status = 'pending' AND s.kurye_id IS NULL
        ORDER BY s.priority DESC, s.created_at ASC
    ");
    
    $new_orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Siparişler yüklenemedi: ' . $e->getMessage();
    $new_orders = [];
}

// Sipariş kabul etme
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'accept_order') {
    try {
        $db = getDB();
        $order_id = (int)$_POST['order_id'];
        
        // Siparişin hala müsait olduğunu kontrol et
        $stmt = $db->query("SELECT id FROM siparisler WHERE id = ? AND status = 'pending' AND kurye_id IS NULL", [$order_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Bu sipariş artık müsait değil');
        }
        
        // Kuryenin online olduğunu kontrol et
        $stmt = $db->query("SELECT is_online, is_available FROM kuryeler WHERE id = ?", [$kurye_id]);
        $kurye_status = $stmt->fetch();
        
        if (!$kurye_status || !$kurye_status['is_online'] || !$kurye_status['is_available']) {
            throw new Exception('Sipariş kabul etmek için online ve müsait olmalısınız');
        }
        
        $db->beginTransaction();
        
        // Siparişi kurye'ye ata
        $db->query("UPDATE siparisler SET kurye_id = ?, status = 'accepted', accepted_at = NOW() WHERE id = ?", [$kurye_id, $order_id]);
        
        // Kurye'yi meşgul yap
        $db->query("UPDATE kuryeler SET is_available = 0 WHERE id = ?", [$kurye_id]);
        
        $db->commit();
        
        // Başarı mesajı ile yönlendir
        header("Location: siparislerim.php?success=" . urlencode('Sipariş başarıyla kabul edildi!'));
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Siparişler - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-card {
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
            position: relative;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .priority-urgent {
            border-left-color: #dc3545;
            background: linear-gradient(45deg, rgba(220,53,69,0.1), transparent);
        }
        .priority-express {
            border-left-color: #ffc107;
            background: linear-gradient(45deg, rgba(255,193,7,0.1), transparent);
        }
        .new-order-pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
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
                        <i class="fas fa-bell me-2 text-success"></i>
                        Yeni Siparişler
                        <?php if (!empty($new_orders)): ?>
                            <span class="badge bg-success"><?= count($new_orders) ?></span>
                        <?php endif; ?>
                    </h1>
                    <button class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>
                        Yenile
                    </button>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= sanitize($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row g-3">
                    <?php if (empty($new_orders)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                    <h5>Yeni sipariş bulunmuyor</h5>
                                    <p class="text-muted">Yeni siparişler geldiğinde burada görünecek.</p>
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="siparislerim.php" class="btn btn-primary">
                                            <i class="fas fa-tasks me-2"></i>
                                            Aktif Siparişlerim
                                        </a>
                                        <button class="btn btn-outline-secondary" onclick="location.reload()">
                                            <i class="fas fa-sync-alt me-2"></i>
                                            Yenile
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($new_orders as $order): ?>
                            <div class="col-lg-6">
                                <div class="card order-card new-order-pulse priority-<?= $order['priority'] ?>">
                                    <?php if ($order['priority'] !== 'normal'): ?>
                                        <div class="position-absolute top-0 end-0 p-2">
                                            <span class="badge bg-<?= $order['priority'] === 'urgent' ? 'danger' : 'warning' ?> fs-6">
                                                <?= $order['priority'] === 'urgent' ? 'ACİL' : 'EKSPRES' ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="card-title mb-1">
                                                    <strong><?= sanitize($order['order_number']) ?></strong>
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= formatDate($order['created_at']) ?>
                                                    <?php
                                                    $minutes_ago = round((time() - strtotime($order['created_at'])) / 60);
                                                    if ($minutes_ago < 60) {
                                                        echo " ({$minutes_ago} dk önce)";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="h5 text-success mb-0"><?= formatMoney($order['total_amount']) ?></div>
                                                <small class="text-muted">Teslimat: <?= formatMoney($order['delivery_fee']) ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Mekan:</strong><br>
                                                <?= sanitize($order['mekan_name']) ?><br>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?= sanitize($order['mekan_address']) ?>
                                                </small>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Müşteri:</strong><br>
                                                <?= sanitize($order['customer_name']) ?><br>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <a href="tel:<?= $order['customer_phone'] ?>"><?= formatPhone($order['customer_phone']) ?></a>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <strong>Teslimat Adresi:</strong><br>
                                            <small><?= sanitize($order['customer_address']) ?></small>
                                            <?php if ($order['customer_latitude'] && $order['customer_longitude']): ?>
                                                <br><small class="text-success">
                                                    <i class="fas fa-map-pin me-1"></i>
                                                    Konum mevcut
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($order['notes']): ?>
                                            <div class="mb-3">
                                                <strong>Notlar:</strong><br>
                                                <small class="text-muted"><?= sanitize($order['notes']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-warning px-3 py-2">
                                                    <i class="fas fa-hourglass-half me-1"></i>
                                                    Bekliyor
                                                </span>
                                                <?php if ($order['estimated_delivery_time']): ?>
                                                    <br><small class="text-muted">
                                                        Tahmini: <?= $order['estimated_delivery_time'] ?> dk
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <button class="btn btn-success btn-lg" onclick="acceptOrder(<?= $order['id'] ?>)">
                                                    <i class="fas fa-check me-2"></i>
                                                    Kabul Et
                                                </button>
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
        function acceptOrder(orderId) {
            if (confirm('Bu siparişi kabul etmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="accept_order">
                    <input type="hidden" name="order_id" value="${orderId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto refresh every 15 seconds for new orders
        setTimeout(() => {
            location.reload();
        }, 15000);
        
        // Play sound when new orders arrive (optional)
        let lastOrderCount = <?= count($new_orders) ?>;
        
        setInterval(() => {
            fetch('api/check-new-orders.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > lastOrderCount) {
                        // New order arrived, refresh page
                        location.reload();
                    }
                })
                .catch(error => console.log('Check failed:', error));
        }, 5000);
    </script>
</body>
</html>
