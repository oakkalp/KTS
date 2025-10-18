<?php
/**
 * Kurye Full System - Mekan Profil
 */

require_once '../config/config.php';
requireUserType('mekan');

$message = '';
$error = '';

// Mekan bilgilerini al
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT u.*, m.mekan_name, m.address, m.category, m.latitude, m.longitude, m.status as mekan_status,
               m.working_hours, m.phone as mekan_phone, m.description, m.getir_restaurant_id,
               m.getir_app_secret_key, m.getir_restaurant_secret_key, m.getir_status
        FROM users u
        JOIN mekanlar m ON u.id = m.user_id
        WHERE u.id = ?
    ", [getUserId()]);
    
    $user_data = $stmt->fetch();
    
    if (!$user_data) {
        throw new Exception('Kullanıcı bilgileri bulunamadı');
    }
    
} catch (Exception $e) {
    $error = 'Profil bilgileri yüklenemedi: ' . $e->getMessage();
    $user_data = [];
}

// Profil güncelleme
if ($_POST) {
    try {
        $db = getDB();
        
        // Kullanıcı bilgilerini güncelle
        $full_name = clean($_POST['full_name']);
        $email = clean($_POST['email']);
        $phone = clean($_POST['phone']);
        $password = clean($_POST['password']);
        
        // Mekan bilgilerini güncelle
        $mekan_name = clean($_POST['mekan_name']);
        $address = clean($_POST['address']);
        $category = clean($_POST['category']);
        $description = clean($_POST['description']);
        $working_hours = clean($_POST['working_hours']);
        $mekan_phone = clean($_POST['mekan_phone']);
        
        // GetirYemek API bilgilerini güncelle
        $getir_restaurant_id = clean($_POST['getir_restaurant_id'] ?? '');
        $getir_app_secret_key = clean($_POST['getir_app_secret_key'] ?? '');
        $getir_restaurant_secret_key = clean($_POST['getir_restaurant_secret_key'] ?? '');
        
        // Email benzersizlik kontrolü
        $check_stmt = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, getUserId()]);
        if ($check_stmt->fetch()) {
            throw new Exception('Bu email adresi zaten kullanılıyor');
        }
        
        // Kullanıcı bilgilerini güncelle
        if (!empty($password)) {
            $hashed_password = hashPassword($password);
            $db->query(
                "UPDATE users SET full_name = ?, email = ?, phone = ?, password = ? WHERE id = ?",
                [$full_name, $email, $phone, $hashed_password, getUserId()]
            );
        } else {
            $db->query(
                "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?",
                [$full_name, $email, $phone, getUserId()]
            );
        }
        
        // Mekan bilgilerini güncelle
        $db->query(
            "UPDATE mekanlar SET mekan_name = ?, address = ?, category = ?, description = ?, 
             working_hours = ?, phone = ?, getir_restaurant_id = ?, getir_app_secret_key = ?, 
             getir_restaurant_secret_key = ? WHERE user_id = ?",
            [$mekan_name, $address, $category, $description, $working_hours, $mekan_phone, 
             $getir_restaurant_id, $getir_app_secret_key, $getir_restaurant_secret_key, getUserId()]
        );
        
        // Session'daki bilgileri güncelle
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        
        $message = 'Profil başarıyla güncellendi';
        
        // Güncellenmiş verileri tekrar çek
        $stmt = $db->query("
            SELECT u.*, m.mekan_name, m.address, m.category, m.latitude, m.longitude, m.status as mekan_status,
                   m.working_hours, m.phone as mekan_phone, m.description
            FROM users u
            JOIN mekanlar m ON u.id = m.user_id
            WHERE u.id = ?
        ", [getUserId()]);
        
        $user_data = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?= APP_NAME ?></title>
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
                        <i class="fas fa-user me-2 text-primary"></i>
                        Profil Ayarları
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
                
                <?php if (!empty($user_data)): ?>
                    <form method="POST">
                        <div class="row">
                            <!-- Kişisel Bilgiler -->
                            <div class="col-lg-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-user me-2"></i>
                                            Kişisel Bilgiler
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Kullanıcı Adı</label>
                                            <input type="text" class="form-control" value="<?= sanitize($user_data['username']) ?>" readonly>
                                            <div class="form-text">Kullanıcı adı değiştirilemez</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Ad Soyad</label>
                                            <input type="text" class="form-control" name="full_name" 
                                                   value="<?= sanitize($user_data['full_name']) ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?= sanitize($user_data['email']) ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Kişisel Telefon</label>
                                            <input type="text" class="form-control" name="phone" 
                                                   value="<?= sanitize($user_data['phone']) ?>" placeholder="05551234567">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Yeni Şifre</label>
                                            <input type="password" class="form-control" name="password" 
                                                   placeholder="Boş bırakılırsa değişmez">
                                            <div class="form-text">Şifreyi değiştirmek istemiyorsanız boş bırakın</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Hesap Durumu</label>
                                            <div>
                                                <span class="badge bg-<?= 
                                                    $user_data['status'] === 'active' ? 'success' : 
                                                    ($user_data['status'] === 'pending' ? 'warning' : 'danger') 
                                                ?> fs-6">
                                                    <?= ucfirst($user_data['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mekan Bilgileri -->
                            <div class="col-lg-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-store me-2"></i>
                                            Mekan Bilgileri
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Mekan Adı</label>
                                            <input type="text" class="form-control" name="mekan_name" 
                                                   value="<?= sanitize($user_data['mekan_name']) ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Kategori</label>
                                            <select class="form-select" name="category">
                                                <option value="Restoran" <?= $user_data['category'] === 'Restoran' ? 'selected' : '' ?>>Restoran</option>
                                                <option value="Market" <?= $user_data['category'] === 'Market' ? 'selected' : '' ?>>Market</option>
                                                <option value="Eczane" <?= $user_data['category'] === 'Eczane' ? 'selected' : '' ?>>Eczane</option>
                                                <option value="Kafe" <?= $user_data['category'] === 'Kafe' ? 'selected' : '' ?>>Kafe</option>
                                                <option value="Fast Food" <?= $user_data['category'] === 'Fast Food' ? 'selected' : '' ?>>Fast Food</option>
                                                <option value="Diğer" <?= $user_data['category'] === 'Diğer' ? 'selected' : '' ?>>Diğer</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Adres</label>
                                            <textarea class="form-control" name="address" rows="3" required><?= sanitize($user_data['address']) ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Mekan Telefonu</label>
                                            <input type="text" class="form-control" name="mekan_phone" 
                                                   value="<?= sanitize($user_data['mekan_phone']) ?>" placeholder="02121234567">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Çalışma Saatleri</label>
                                            <input type="text" class="form-control" name="working_hours" 
                                                   value="<?= sanitize($user_data['working_hours']) ?>" 
                                                   placeholder="09:00 - 22:00">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Açıklama</label>
                                            <textarea class="form-control" name="description" rows="3" 
                                                      placeholder="Mekanınız hakkında kısa bilgi"><?= sanitize($user_data['description']) ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Mekan Durumu</label>
                                            <div>
                                                <span class="badge bg-<?= 
                                                    $user_data['mekan_status'] === 'active' ? 'success' : 
                                                    ($user_data['mekan_status'] === 'pending' ? 'warning' : 'danger') 
                                                ?> fs-6">
                                                    <?= ucfirst($user_data['mekan_status']) ?>
                                                </span>
                                                <?php if ($user_data['mekan_status'] === 'pending'): ?>
                                                    <div class="form-text text-warning">
                                                        <i class="fas fa-clock me-1"></i>
                                                        Mekanınız admin onayı bekliyor
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- GetirYemek API Ayarları -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-store me-2"></i>
                                            GetirYemek API Ayarları
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            GetirYemek entegrasyonu için gerekli API bilgilerini girin. Bu bilgileri GetirYemek'ten alabilirsiniz.
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">GetirYemek Restoran ID</label>
                                                    <input type="text" class="form-control" name="getir_restaurant_id" 
                                                           value="<?= sanitize($user_data['getir_restaurant_id'] ?? '') ?>" 
                                                           placeholder="GetirYemek'ten aldığınız restoran ID">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">App Secret Key</label>
                                                    <input type="password" class="form-control" name="getir_app_secret_key" 
                                                           value="<?= sanitize($user_data['getir_app_secret_key'] ?? '') ?>" 
                                                           placeholder="GetirYemek App Secret Key">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Restaurant Secret Key</label>
                                                    <input type="password" class="form-control" name="getir_restaurant_secret_key" 
                                                           value="<?= sanitize($user_data['getir_restaurant_secret_key'] ?? '') ?>" 
                                                           placeholder="GetirYemek Restaurant Secret Key">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Entegrasyon Durumu</label>
                                                    <div>
                                                        <?php if (!empty($user_data['getir_restaurant_id']) && !empty($user_data['getir_app_secret_key']) && !empty($user_data['getir_restaurant_secret_key'])): ?>
                                                            <span class="badge bg-success fs-6">
                                                                <i class="fas fa-check me-1"></i>Entegre
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning fs-6">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>Entegre Değil
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($user_data['getir_restaurant_id'])): ?>
                                            <div class="alert alert-success">
                                                <h6><i class="fas fa-link me-2"></i>Webhook URL'leri</h6>
                                                <p class="mb-1"><strong>Yeni Sipariş:</strong> <code><?= BASE_URL ?>/api/getir/webhook/newOrder</code></p>
                                                <p class="mb-1"><strong>Sipariş İptal:</strong> <code><?= BASE_URL ?>/api/getir/webhook/cancelOrder</code></p>
                                                <p class="mb-1"><strong>Kurye Bildirimi:</strong> <code><?= BASE_URL ?>/api/getir/webhook/courier</code></p>
                                                <p class="mb-0"><strong>Restoran Durumu:</strong> <code><?= BASE_URL ?>/api/getir/webhook/restaurant</code></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- İstatistikler -->
                            <div class="col-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-chart-bar me-2"></i>
                                            Hesap İstatistikleri
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-3">
                                                <div class="h4 text-primary">
                                                    <?php
                                                    try {
                                                        $stats_stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE mekan_id = (SELECT id FROM mekanlar WHERE user_id = ?)", [getUserId()]);
                                                        echo number_format($stats_stmt->fetch()['count']);
                                                    } catch (Exception $e) {
                                                        echo '0';
                                                    }
                                                    ?>
                                                </div>
                                                <div class="text-muted">Toplam Sipariş</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="h4 text-success">
                                                    <?php
                                                    try {
                                                        $stats_stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE mekan_id = (SELECT id FROM mekanlar WHERE user_id = ?) AND status = 'delivered'", [getUserId()]);
                                                        echo number_format($stats_stmt->fetch()['count']);
                                                    } catch (Exception $e) {
                                                        echo '0';
                                                    }
                                                    ?>
                                                </div>
                                                <div class="text-muted">Tamamlanan</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="h4 text-info">
                                                    <?php
                                                    try {
                                                        $stats_stmt = $db->query("SELECT SUM(total_amount) as total FROM siparisler WHERE mekan_id = (SELECT id FROM mekanlar WHERE user_id = ?) AND status = 'delivered'", [getUserId()]);
                                                        $total = $stats_stmt->fetch()['total'] ?? 0;
                                                        echo formatMoney($total);
                                                    } catch (Exception $e) {
                                                        echo formatMoney(0);
                                                    }
                                                    ?>
                                                </div>
                                                <div class="text-muted">Toplam Gelir</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="h4 text-warning">
                                                    <?= formatDate($user_data['created_at']) ?>
                                                </div>
                                                <div class="text-muted">Üyelik Tarihi</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>
                                Profili Güncelle
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form submit confirmation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('Profil bilgilerini güncellemek istediğinizden emin misiniz?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
