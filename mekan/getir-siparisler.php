<?php
/**
 * GetirYemek Siparişleri Sayfası
 * Mekan panelinde GetirYemek siparişlerini yönetme
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Mekan kontrolü
requireUserType('mekan');

$db = getDB();
$user_id = getUserId();

// Mekan bilgilerini al
$stmt = $db->query("SELECT * FROM mekanlar WHERE user_id = ?", [$user_id]);
$mekan = $stmt->fetch();

if (!$mekan) {
    throw new Exception("Mekan bilgileri bulunamadı");
}

$mekan_id = $mekan['id'];
$message = '';
$error = '';

// POST işlemleri
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'verify_order':
                $siparis_id = (int)$_POST['siparis_id'];
                
                // Siparişi onayla
                $response = file_get_contents(BASE_URL . '/api/getir/orders/verify.php', false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode(['order_id' => $siparis_id])
                    ]
                ]));
                
                $result = json_decode($response, true);
                
                if ($result && isset($result['success']) && $result['success']) {
                    $message = "Sipariş başarıyla onaylandı";
                } else {
                    throw new Exception($result['error']['message'] ?? 'Sipariş onaylanamadı');
                }
                break;
                
            case 'prepare_order':
                $siparis_id = (int)$_POST['siparis_id'];
                
                // Siparişi hazırla
                $response = file_get_contents(BASE_URL . '/api/getir/orders/prepare.php', false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode(['order_id' => $siparis_id])
                    ]
                ]));
                
                $result = json_decode($response, true);
                
                if ($result && isset($result['success']) && $result['success']) {
                    $message = "Sipariş hazır olarak işaretlendi";
                } else {
                    throw new Exception($result['error']['message'] ?? 'Sipariş hazırlanamadı');
                }
                break;
                
            case 'handover_order':
                $siparis_id = (int)$_POST['siparis_id'];
                
                // Siparişi kuryeye teslim et
                $response = file_get_contents(BASE_URL . '/api/getir/orders/handover.php', false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode(['order_id' => $siparis_id])
                    ]
                ]));
                
                $result = json_decode($response, true);
                
                if ($result && isset($result['success']) && $result['success']) {
                    $message = "Sipariş kuryeye teslim edildi";
                } else {
                    throw new Exception($result['error']['message'] ?? 'Sipariş teslim edilemedi');
                }
                break;
                
            case 'deliver_order':
                $siparis_id = (int)$_POST['siparis_id'];
                
                // Siparişi teslim et
                $response = file_get_contents(BASE_URL . '/api/getir/orders/deliver.php', false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode(['order_id' => $siparis_id])
                    ]
                ]));
                
                $result = json_decode($response, true);
                
                if ($result && isset($result['success']) && $result['success']) {
                    $message = "Sipariş teslim edildi";
                } else {
                    throw new Exception($result['error']['message'] ?? 'Sipariş teslim edilemedi');
                }
                break;
                
            case 'restaurant_status':
                $status_action = $_POST['status_action'] ?? '';
                
                // Restoran durumunu değiştir
                $response = file_get_contents(BASE_URL . '/api/getir/restaurants/status.php', false, stream_context_create([
                    'http' => [
                        'method' => 'PUT',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode(['action' => $status_action])
                    ]
                ]));
                
                $result = json_decode($response, true);
                
                if ($result && isset($result['success']) && $result['success']) {
                    $message = "Restoran durumu başarıyla güncellendi";
                } else {
                    throw new Exception($result['error']['message'] ?? 'Restoran durumu güncellenemedi');
                }
                break;
                
            case 'courier_status':
                $courier_action = $_POST['courier_action'] ?? '';
                
                // Kurye durumunu değiştir
                $response = file_get_contents(BASE_URL . '/api/getir/restaurants/courier.php', false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode(['action' => $courier_action])
                    ]
                ]));
                
                $result = json_decode($response, true);
                
                if ($result && isset($result['success']) && $result['success']) {
                    $message = "Kurye durumu başarıyla güncellendi";
                } else {
                    throw new Exception($result['error']['message'] ?? 'Kurye durumu güncellenemedi');
                }
                break;
                
            case 'busyness_status':
                $is_busy = $_POST['is_busy'] === 'true';
                $duration = (int)$_POST['duration'];
                
                // Yoğunluk durumunu değiştir
                $response = file_get_contents(BASE_URL . '/api/getir/restaurants/busyness.php', false, stream_context_create([
                    'http' => [
                        'method' => 'PUT',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode([
                            'isBusy' => $is_busy,
                            'busynessDifferenceDuration' => $duration
                        ])
                    ]
                ]));
                
                $result = json_decode($response, true);
                
                if ($result && isset($result['success']) && $result['success']) {
                    $message = "Yoğunluk durumu başarıyla güncellendi";
                } else {
                    throw new Exception($result['error']['message'] ?? 'Yoğunluk durumu güncellenemedi');
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// GetirYemek entegrasyonu kontrolü
if (empty($mekan['getir_restaurant_id']) || empty($mekan['getir_app_secret_key']) || empty($mekan['getir_restaurant_secret_key'])) {
    $error = "GetirYemek entegrasyonu yapılmamış. Lütfen profil sayfasından GetirYemek API bilgilerinizi girin.";
}

// GetirYemek siparişlerini getir
$getir_siparisler = $db->query("
    SELECT s.*, 
           CASE s.getir_status
               WHEN 400 THEN 'Yeni Sipariş'
               WHEN 350 THEN 'Onaylandı'
               WHEN 500 THEN 'Hazırlanıyor'
               WHEN 550 THEN 'Hazır'
               WHEN 600 THEN 'Kuryeye Teslim'
               WHEN 900 THEN 'Teslim Edildi'
               WHEN 1500 THEN 'İptal Edildi'
               ELSE 'Bilinmeyen'
           END as getir_status_text,
           CASE s.delivery_type
               WHEN 1 THEN 'Getir Getirsin'
               WHEN 2 THEN 'Restoran Getirsin'
               ELSE 'Bilinmeyen'
           END as delivery_type_text
    FROM siparisler s
    WHERE s.mekan_id = ? AND s.source = 'getir'
    ORDER BY s.created_at DESC
", [$mekan_id])->fetchAll();

// Sipariş istatistikleri
$stats = $db->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN s.getir_status = 400 THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN s.getir_status = 350 THEN 1 ELSE 0 END) as accepted_orders,
        SUM(CASE WHEN s.getir_status = 550 THEN 1 ELSE 0 END) as ready_orders,
        SUM(CASE WHEN s.getir_status = 900 THEN 1 ELSE 0 END) as delivered_orders,
        SUM(s.total_amount) as total_revenue
    FROM siparisler s
    WHERE s.mekan_id = ? AND s.source = 'getir' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
", [$mekan_id])->fetch();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GetirYemek Siparişleri - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .card-header {
            border-radius: 15px 15px 0 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .order-card {
            border-left: 4px solid #667eea;
        }
        .order-card.pending { border-left-color: #ffc107; }
        .order-card.accepted { border-left-color: #17a2b8; }
        .order-card.ready { border-left-color: #28a745; }
        .order-card.delivered { border-left-color: #6c757d; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-store me-2"></i>GetirYemek Siparişleri</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                        <?php if (strpos($error, 'GetirYemek entegrasyonu') !== false): ?>
                            <div class="mt-2">
                                <a href="profil.php" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-cog me-1"></i>Profil Ayarlarına Git
                                </a>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- GetirYemek entegrasyonu yoksa uyarı göster -->
                <?php if (empty($mekan['getir_restaurant_id']) || empty($mekan['getir_app_secret_key']) || empty($mekan['getir_restaurant_secret_key'])): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>GetirYemek Entegrasyonu Gerekli</h5>
                                </div>
                                <div class="card-body">
                                    <p class="mb-3">GetirYemek siparişlerini yönetmek için önce API bilgilerinizi girmeniz gerekiyor.</p>
                                    <div class="d-flex gap-2">
                                        <a href="profil.php" class="btn btn-warning">
                                            <i class="fas fa-cog me-2"></i>Profil Ayarlarına Git
                                        </a>
                                        <a href="https://getir.com" target="_blank" class="btn btn-outline-warning">
                                            <i class="fas fa-external-link-alt me-2"></i>GetirYemek'e Git
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                <!-- Restoran Kontrolleri -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-power-off me-2"></i>Restoran Durumu</h5>
                            </div>
                            <div class="card-body text-center">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="restaurant_status">
                                    <input type="hidden" name="status_action" value="open">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-play me-2"></i>Aç
                                    </button>
                                </form>
                                <form method="POST" class="d-inline ms-2">
                                    <input type="hidden" name="action" value="restaurant_status">
                                    <input type="hidden" name="status_action" value="close">
                                    <button type="submit" class="btn btn-danger btn-lg">
                                        <i class="fas fa-stop me-2"></i>Kapat
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-motorcycle me-2"></i>Kurye Servisi</h5>
                            </div>
                            <div class="card-body text-center">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="courier_status">
                                    <input type="hidden" name="courier_action" value="enable">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-check me-2"></i>Aktif Et
                                    </button>
                                </form>
                                <form method="POST" class="d-inline ms-2">
                                    <input type="hidden" name="action" value="courier_status">
                                    <input type="hidden" name="courier_action" value="disable">
                                    <button type="submit" class="btn btn-warning btn-lg">
                                        <i class="fas fa-times me-2"></i>Pasif Et
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Yoğunluk Durumu</h5>
                            </div>
                            <div class="card-body text-center">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="busyness_status">
                                    <input type="hidden" name="is_busy" value="true">
                                    <input type="hidden" name="duration" value="15">
                                    <button type="submit" class="btn btn-warning btn-lg">
                                        <i class="fas fa-exclamation me-2"></i>Yoğun (+15dk)
                                    </button>
                                </form>
                                <form method="POST" class="d-inline ms-2">
                                    <input type="hidden" name="action" value="busyness_status">
                                    <input type="hidden" name="is_busy" value="false">
                                    <input type="hidden" name="duration" value="0">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-check me-2"></i>Normal
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?= $stats['total_orders'] ?></h3>
                                <small>Toplam Sipariş</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?= $stats['pending_orders'] ?></h3>
                                <small>Bekleyen</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?= $stats['accepted_orders'] ?></h3>
                                <small>Onaylanan</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?= $stats['ready_orders'] ?></h3>
                                <small>Hazır</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-secondary"><?= $stats['delivered_orders'] ?></h3>
                                <small>Teslim Edilen</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?= number_format($stats['total_revenue'] ?? 0, 0) ?> ₺</h3>
                                <small>Toplam Gelir</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Siparişler -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>GetirYemek Siparişleri</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($getir_siparisler)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">Henüz GetirYemek siparişi yok</h5>
                                        <p class="text-muted">GetirYemek'ten gelen siparişler burada görünecek</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($getir_siparisler as $siparis): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card order-card <?= strtolower($siparis['getir_status_text']) ?>">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="card-title"><?= htmlspecialchars($siparis['order_number']) ?></h6>
                                                            <span class="badge bg-info"><?= $siparis['getir_status_text'] ?></span>
                                                        </div>
                                                        
                                                        <div class="mb-2">
                                                            <strong>Müşteri:</strong> <?= htmlspecialchars($siparis['customer_name']) ?><br>
                                                            <strong>Telefon:</strong> <?= htmlspecialchars($siparis['customer_phone']) ?><br>
                                                            <strong>Adres:</strong> <?= htmlspecialchars($siparis['customer_address']) ?><br>
                                                            <strong>Tutar:</strong> <?= number_format($siparis['total_amount'] ?? 0, 2) ?> ₺<br>
                                                            <strong>Teslimat:</strong> <?= $siparis['delivery_type_text'] ?><br>
                                                            <strong>Tarih:</strong> <?= date('d.m.Y H:i', strtotime($siparis['created_at'])) ?>
                                                        </div>
                                                        
                                                        <div class="d-flex gap-2">
                                                            <?php if ($siparis['getir_status'] == 400): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="action" value="verify_order">
                                                                    <input type="hidden" name="siparis_id" value="<?= $siparis['id'] ?>">
                                                                    <button type="submit" class="btn btn-success btn-sm">
                                                                        <i class="fas fa-check me-1"></i>Onayla
                                                                    </button>
                                                                </form>
                                                            <?php elseif ($siparis['getir_status'] == 350): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="action" value="prepare_order">
                                                                    <input type="hidden" name="siparis_id" value="<?= $siparis['id'] ?>">
                                                                    <button type="submit" class="btn btn-warning btn-sm">
                                                                        <i class="fas fa-clock me-1"></i>Hazırla
                                                                    </button>
                                                                </form>
                                                            <?php elseif ($siparis['getir_status'] == 550): ?>
                                                                <?php if ($siparis['delivery_type'] == 1): ?>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="action" value="handover_order">
                                                                        <input type="hidden" name="siparis_id" value="<?= $siparis['id'] ?>">
                                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                                            <i class="fas fa-hand-holding me-1"></i>Kuryeye Teslim Et
                                                                        </button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="action" value="deliver_order">
                                                                        <input type="hidden" name="siparis_id" value="<?= $siparis['id'] ?>">
                                                                        <button type="submit" class="btn btn-success btn-sm">
                                                                            <i class="fas fa-truck me-1"></i>Teslim Et
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
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
</body>
</html>
