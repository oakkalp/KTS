<?php
/**
 * GetirYemek Yönetim Sayfası
 * Admin panelinde GetirYemek entegrasyonu yönetimi
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();
$message = '';
$error = '';

// POST işlemleri
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_api_key':
                $api_key = $_POST['api_key'] ?? '';
                
                if (empty($api_key)) {
                    throw new Exception('API anahtarı boş olamaz');
                }
                
                // Config dosyasını güncelle
                $config_file = '../config/config.php';
                $config_content = file_get_contents($config_file);
                
                $new_config = preg_replace(
                    "/define\('GETIR_API_KEY', '[^']*'\);/",
                    "define('GETIR_API_KEY', '$api_key');",
                    $config_content
                );
                
                if (file_put_contents($config_file, $new_config)) {
                    $message = "API anahtarı başarıyla güncellendi";
                } else {
                    throw new Exception('Config dosyası güncellenemedi');
                }
                break;
                
            case 'add_credentials':
                $mekan_id = (int)$_POST['mekan_id'];
                $app_secret_key = $_POST['app_secret_key'] ?? '';
                $restaurant_secret_key = $_POST['restaurant_secret_key'] ?? '';
                $getir_restaurant_id = $_POST['getir_restaurant_id'] ?? '';
                
                if (empty($app_secret_key) || empty($restaurant_secret_key) || empty($getir_restaurant_id)) {
                    throw new Exception('Tüm alanlar doldurulmalıdır');
                }
                
                // Mekan bilgilerini güncelle
                $db->query("
                    UPDATE mekanlar 
                    SET getir_app_secret_key = ?,
                        getir_restaurant_secret_key = ?,
                        getir_restaurant_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ", [$app_secret_key, $restaurant_secret_key, $getir_restaurant_id, $mekan_id]);
                
                // Token'ı veritabanına kaydet
                $db->query("
                    INSERT INTO getir_tokens (
                        app_secret_key, restaurant_secret_key, token, expires_at, created_at
                    ) VALUES (?, ?, '', '1970-01-01 00:00:00', NOW())
                    ON DUPLICATE KEY UPDATE
                    app_secret_key = VALUES(app_secret_key),
                    restaurant_secret_key = VALUES(restaurant_secret_key),
                    updated_at = NOW()
                ", [$app_secret_key, $restaurant_secret_key]);
                
                $message = "GetirYemek kimlik bilgileri başarıyla eklendi";
                break;
                
            case 'test_connection':
                $mekan_id = (int)$_POST['mekan_id'];
                
                // Mekan bilgilerini al
                $stmt = $db->query("
                    SELECT * FROM mekanlar 
                    WHERE id = ? AND getir_app_secret_key IS NOT NULL 
                    AND getir_restaurant_secret_key IS NOT NULL
                ", [$mekan_id]);
                
                $mekan = $stmt->fetch();
                
                if (!$mekan) {
                    throw new Exception('Mekan bulunamadı veya kimlik bilgileri eksik');
                }
                
                // GetirYemek API'sine test isteği gönder
                $login_data = [
                    'appSecretKey' => $mekan['getir_app_secret_key'],
                    'restaurantSecretKey' => $mekan['getir_restaurant_secret_key']
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, GETIR_API_BASE_URL . '/auth/login');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($login_data));
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $result = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);
                
                if ($curl_error) {
                    throw new Exception("Bağlantı hatası: $curl_error");
                }
                
                if ($http_code !== 200) {
                    throw new Exception("API hatası: HTTP $http_code - $result");
                }
                
                $response = json_decode($result, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('API yanıtı geçersiz JSON formatında');
                }
                
                if (empty($response['token'])) {
                    throw new Exception('API\'den token alınamadı');
                }
                
                $message = "GetirYemek API bağlantısı başarılı! Token alındı.";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Mekanları getir
$mekanlar = $db->query("
    SELECT m.*, u.username,
           CASE 
               WHEN m.getir_restaurant_id IS NOT NULL THEN 'Entegre'
               ELSE 'Entegre Değil'
           END as getir_status
    FROM mekanlar m
    JOIN users u ON m.user_id = u.id
    ORDER BY m.mekan_name
")->fetchAll();

// GetirYemek token'larını getir
$tokens = $db->query("
    SELECT * FROM getir_tokens 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// GetirYemek siparişlerini getir
$getir_siparisler = $db->query("
    SELECT s.*, m.mekan_name,
           CASE s.getir_status
               WHEN 400 THEN 'Yeni Sipariş'
               WHEN 350 THEN 'Onaylandı'
               WHEN 500 THEN 'Hazırlanıyor'
               WHEN 550 THEN 'Hazır'
               WHEN 600 THEN 'Kuryeye Teslim'
               WHEN 900 THEN 'Teslim Edildi'
               WHEN 1500 THEN 'İptal Edildi'
               ELSE 'Bilinmeyen'
           END as getir_status_text
    FROM siparisler s
    JOIN mekanlar m ON s.mekan_id = m.id
    WHERE s.source = 'getir'
    ORDER BY s.created_at DESC
    LIMIT 20
")->fetchAll();

// GetirYemek loglarını getir
$logs = [];
$log_file = '../logs/getir.log';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $logs = array_slice(array_reverse($log_lines), 0, 20);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GetirYemek Yönetimi - <?= APP_NAME ?></title>
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
        .log-entry {
            font-family: monospace;
            font-size: 0.85rem;
            padding: 5px;
            border-bottom: 1px solid #eee;
        }
        .log-entry:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-store me-2"></i>GetirYemek Yönetimi</h1>
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
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- API Ayarları -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-key me-2"></i>API Ayarları</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_api_key">
                                    <div class="mb-3">
                                        <label class="form-label">GetirYemek API Anahtarı</label>
                                        <input type="text" class="form-control" name="api_key" 
                                               value="<?= GETIR_API_KEY ?>" placeholder="API anahtarınızı girin">
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Kaydet
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Webhook URL'leri</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong>Yeni Sipariş:</strong><br>
                                    <code><?= BASE_URL ?>/api/getir/webhook/newOrder</code>
                                </div>
                                <div class="mb-2">
                                    <strong>Sipariş İptal:</strong><br>
                                    <code><?= BASE_URL ?>/api/getir/webhook/cancelOrder</code>
                                </div>
                                <div class="mb-2">
                                    <strong>Kurye Bildirimi:</strong><br>
                                    <code><?= BASE_URL ?>/api/getir/webhook/courier</code>
                                </div>
                                <div class="mb-2">
                                    <strong>Restoran Durumu:</strong><br>
                                    <code><?= BASE_URL ?>/api/getir/webhook/restaurant</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mekan Entegrasyonları -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-store me-2"></i>Mekan Entegrasyonları</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Mekan Adı</th>
                                                <th>Kullanıcı</th>
                                                <th>GetirYemek Durumu</th>
                                                <th>Restoran ID</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mekanlar as $mekan): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($mekan['mekan_name']) ?></td>
                                                    <td><?= htmlspecialchars($mekan['username']) ?></td>
                                                    <td>
                                                        <span class="badge <?= $mekan['getir_status'] === 'Entegre' ? 'bg-success' : 'bg-secondary' ?>">
                                                            <?= $mekan['getir_status'] ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($mekan['getir_restaurant_id'] ?? '-') ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                                data-bs-target="#credentialsModal" 
                                                                data-mekan-id="<?= $mekan['id'] ?>"
                                                                data-mekan-name="<?= htmlspecialchars($mekan['mekan_name']) ?>">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <?php if ($mekan['getir_restaurant_id']): ?>
                                                            <button class="btn btn-sm btn-success" 
                                                                    onclick="testConnection(<?= $mekan['id'] ?>)">
                                                                <i class="fas fa-plug"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GetirYemek Siparişleri -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>GetirYemek Siparişleri</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Sipariş No</th>
                                                <th>Mekan</th>
                                                <th>Müşteri</th>
                                                <th>Tutar</th>
                                                <th>Durum</th>
                                                <th>Tarih</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($getir_siparisler as $siparis): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($siparis['order_number']) ?></td>
                                                    <td><?= htmlspecialchars($siparis['mekan_name']) ?></td>
                                                    <td><?= htmlspecialchars($siparis['customer_name']) ?></td>
                                                    <td><?= number_format($siparis['total_amount'] ?? 0, 2) ?> ₺</td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?= $siparis['getir_status_text'] ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('d.m.Y H:i', strtotime($siparis['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loglar -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>GetirYemek Logları</h5>
                            </div>
                            <div class="card-body">
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($logs as $log): ?>
                                        <?php if (!empty(trim($log))): ?>
                                            <div class="log-entry"><?= htmlspecialchars($log) ?></div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Kimlik Bilgileri Modal -->
    <div class="modal fade" id="credentialsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">GetirYemek Kimlik Bilgileri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_credentials">
                        <input type="hidden" name="mekan_id" id="modalMekanId">
                        
                        <div class="mb-3">
                            <label class="form-label">Mekan</label>
                            <input type="text" class="form-control" id="modalMekanName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">App Secret Key</label>
                            <input type="text" class="form-control" name="app_secret_key" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Restaurant Secret Key</label>
                            <input type="text" class="form-control" name="restaurant_secret_key" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">GetirYemek Restoran ID</label>
                            <input type="text" class="form-control" name="getir_restaurant_id" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal işlemleri
        document.getElementById('credentialsModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const mekanId = button.getAttribute('data-mekan-id');
            const mekanName = button.getAttribute('data-mekan-name');
            
            document.getElementById('modalMekanId').value = mekanId;
            document.getElementById('modalMekanName').value = mekanName;
        });
        
        // Bağlantı testi
        function testConnection(mekanId) {
            if (confirm('Bu mekan için GetirYemek API bağlantısını test etmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="test_connection">
                    <input type="hidden" name="mekan_id" value="${mekanId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
