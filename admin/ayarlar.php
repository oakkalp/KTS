<?php
/**
 * Kurye Full System - Admin Sistem Ayarları
 */

require_once '../config/config.php';
requireUserType('admin');

$message = '';
$error = '';

// Ayar güncelleme
if ($_POST) {
    try {
        $db = getDB();
        
        $settings = [
            'app_name' => clean($_POST['app_name'] ?? ''),
            'delivery_fee' => (float)($_POST['delivery_fee'] ?? 0),
            'commission_rate' => (float)($_POST['commission_rate'] ?? 0),
                    'location_update_interval' => (int)($_POST['location_update_interval'] ?? 30),
                    'default_pickup_time' => (int)($_POST['default_pickup_time'] ?? 15),
                    'max_delivery_time' => (int)($_POST['max_delivery_time'] ?? 25),
                    'max_orders_per_courier' => (int)($_POST['max_orders_per_courier'] ?? 5),
            'google_maps_api_key' => clean($_POST['google_maps_api_key'] ?? ''),
            'firebase_server_key' => clean($_POST['firebase_server_key'] ?? ''),
            'sms_api_key' => clean($_POST['sms_api_key'] ?? ''),
            'email_smtp_host' => clean($_POST['email_smtp_host'] ?? ''),
            'email_smtp_username' => clean($_POST['email_smtp_username'] ?? ''),
            'email_smtp_password' => clean($_POST['email_smtp_password'] ?? ''),
        ];
        
        foreach ($settings as $key => $value) {
            $type = is_numeric($value) ? 'number' : 'string';
            
            // Ayarı güncelle veya ekle
            $stmt = $db->query(
                "INSERT INTO sistem_ayarlari (setting_key, setting_value, setting_type) 
                 VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?",
                [$key, $value, $type, $value, $type]
            );
        }
        
        $message = 'Ayarlar başarıyla güncellendi';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Mevcut ayarları al
try {
    $db = getDB();
    $stmt = $db->query("SELECT setting_key, setting_value FROM sistem_ayarlari");
    $current_settings = [];
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error = 'Ayarlar yüklenemedi: ' . $e->getMessage();
    $current_settings = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Ayarları - <?= APP_NAME ?></title>
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
                        <i class="fas fa-cog me-2 text-primary"></i>
                        Sistem Ayarları
                    </h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= sanitize($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= sanitize($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row">
                        <!-- Genel Ayarlar -->
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-sliders-h me-2"></i>
                                        Genel Ayarlar
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Uygulama Adı</label>
                                        <input type="text" class="form-control" name="app_name" 
                                               value="<?= sanitize($current_settings['app_name'] ?? 'Kurye Full System') ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Varsayılan Teslimat Ücreti (₺)</label>
                                        <input type="number" step="0.01" class="form-control" name="delivery_fee" 
                                               value="<?= $current_settings['delivery_fee'] ?? '5.00' ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Komisyon Oranı (%)</label>
                                        <input type="number" step="0.01" class="form-control" name="commission_rate" 
                                               value="<?= $current_settings['commission_rate'] ?? '15.00' ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Konum Güncelleme Aralığı (saniye)</label>
                                        <input type="number" class="form-control" name="location_update_interval" 
                                               value="<?= $current_settings['location_update_interval'] ?? '30' ?>">
                                        <div class="form-text">Kuryeler bu sıklıkla konum bilgisi gönderir</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Varsayılan Hazırlık Süresi (dakika)</label>
                                        <input type="number" class="form-control" name="default_pickup_time" 
                                               value="<?= $current_settings['default_pickup_time'] ?? '15' ?>">
                                        <div class="form-text">Mekanların siparişleri hazırlama süresi</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Maksimum Teslimat Süresi (dakika)</label>
                                        <input type="number" class="form-control" name="max_delivery_time" 
                                               value="<?= $current_settings['max_delivery_time'] ?? '25' ?>">
                                        <div class="form-text">Siparişin alınıp teslim edilmesi gereken maksimum süre</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Kurye Başına Maksimum Sipariş Sayısı</label>
                                        <input type="number" class="form-control" name="max_orders_per_courier" 
                                               value="<?= $current_settings['max_orders_per_courier'] ?? '5' ?>" min="1" max="10">
                                        <div class="form-text">Bir kurye aynı anda kaç sipariş alabilir</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- API Ayarları -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-key me-2"></i>
                                        API Ayarları
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Google Maps API Key</label>
                                        <input type="text" class="form-control" name="google_maps_api_key" 
                                               value="<?= sanitize($current_settings['google_maps_api_key'] ?? '') ?>"
                                               placeholder="AIzaSy...">
                                        <div class="form-text">
                                            <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">
                                                API Key nasıl alınır?
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Firebase Server Key</label>
                                        <input type="text" class="form-control" name="firebase_server_key" 
                                               value="<?= sanitize($current_settings['firebase_server_key'] ?? '') ?>"
                                               placeholder="AAAA...">
                                        <div class="form-text">Push notification için gerekli</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">SMS API Key</label>
                                        <input type="text" class="form-control" name="sms_api_key" 
                                               value="<?= sanitize($current_settings['sms_api_key'] ?? '') ?>"
                                               placeholder="SMS API anahtarı">
                                        <div class="form-text">SMS bildirimleri için</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Email Ayarları -->
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-envelope me-2"></i>
                                        Email Ayarları
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" name="email_smtp_host" 
                                               value="<?= sanitize($current_settings['email_smtp_host'] ?? '') ?>"
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Kullanıcı Adı</label>
                                        <input type="email" class="form-control" name="email_smtp_username" 
                                               value="<?= sanitize($current_settings['email_smtp_username'] ?? '') ?>"
                                               placeholder="your-email@gmail.com">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Şifre</label>
                                        <input type="password" class="form-control" name="email_smtp_password" 
                                               value="<?= sanitize($current_settings['email_smtp_password'] ?? '') ?>"
                                               placeholder="Uygulama şifresi">
                                        <div class="form-text">Gmail için uygulama şifresi kullanın</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sistem Bilgileri -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Sistem Bilgileri
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>PHP Version:</strong><br>
                                            <span class="text-muted"><?= phpversion() ?></span>
                                        </div>
                                        <div class="col-6">
                                            <strong>MySQL Version:</strong><br>
                                            <span class="text-muted">
                                                <?php
                                                try {
                                                    $stmt = $db->query("SELECT VERSION() as version");
                                                    echo $stmt->fetch()['version'];
                                                } catch (Exception $e) {
                                                    echo 'Bilinmiyor';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>Toplam Kullanıcı:</strong><br>
                                            <span class="text-muted">
                                                <?php
                                                try {
                                                    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
                                                    echo number_format($stmt->fetch()['count']);
                                                } catch (Exception $e) {
                                                    echo '0';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="col-6">
                                            <strong>Toplam Sipariş:</strong><br>
                                            <span class="text-muted">
                                                <?php
                                                try {
                                                    $stmt = $db->query("SELECT COUNT(*) as count FROM siparisler");
                                                    echo number_format($stmt->fetch()['count']);
                                                } catch (Exception $e) {
                                                    echo '0';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-12">
                                            <strong>Disk Kullanımı:</strong><br>
                                            <span class="text-muted">
                                                <?php
                                                $bytes = disk_free_space('.');
                                                $si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB');
                                                $base = 1024;
                                                $class = min((int)log($bytes , $base) , count($si_prefix) - 1);
                                                echo sprintf('%1.2f' , $bytes / pow($base,$class)) . ' ' . $si_prefix[$class] . ' boş alan';
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>
                            Ayarları Kaydet
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form submit confirmation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('Ayarları kaydetmek istediğinizden emin misiniz?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
