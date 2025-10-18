<?php
/**
 * Kurye Full System - Kurye Profil
 */

require_once '../config/config.php';
requireUserType('kurye');

$message = '';
$error = '';

// Kurye bilgilerini al
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT u.*, k.license_plate, k.vehicle_type, k.is_online, k.is_available,
               k.current_latitude, k.current_longitude, k.last_location_update
        FROM users u
        JOIN kuryeler k ON u.id = k.user_id
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
        
        // Kurye bilgilerini güncelle
        $license_plate = clean($_POST['license_plate']);
        $vehicle_type = clean($_POST['vehicle_type']);
        
        // Email benzersizlik kontrolü
        $check_stmt = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, getUserId()]);
        if ($check_stmt->fetch()) {
            throw new Exception('Bu email adresi zaten kullanılıyor');
        }
        
        $db->beginTransaction();
        
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
        
        // Kurye bilgilerini güncelle
        $db->query(
            "UPDATE kuryeler SET license_plate = ?, vehicle_type = ? WHERE user_id = ?",
            [$license_plate, $vehicle_type, getUserId()]
        );
        
        $db->commit();
        
        // Session'daki bilgileri güncelle
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        
        $message = 'Profil başarıyla güncellendi';
        
        // Güncellenmiş verileri tekrar çek
        $stmt = $db->query("
            SELECT u.*, k.license_plate, k.vehicle_type, k.is_online, k.is_available,
                   k.current_latitude, k.current_longitude, k.last_location_update
            FROM users u
            JOIN kuryeler k ON u.id = k.user_id
            WHERE u.id = ?
        ", [getUserId()]);
        
        $user_data = $stmt->fetch();
        
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
                                            <label class="form-label">Telefon</label>
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
                            
                            <!-- Kurye Bilgileri -->
                            <div class="col-lg-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-motorcycle me-2"></i>
                                            Kurye Bilgileri
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Araç Tipi</label>
                                            <select class="form-select" name="vehicle_type">
                                                <option value="motosiklet" <?= $user_data['vehicle_type'] === 'motosiklet' ? 'selected' : '' ?>>Motosiklet</option>
                                                <option value="bisiklet" <?= $user_data['vehicle_type'] === 'bisiklet' ? 'selected' : '' ?>>Bisiklet</option>
                                                <option value="araba" <?= $user_data['vehicle_type'] === 'araba' ? 'selected' : '' ?>>Araba</option>
                                                <option value="yürüyerek" <?= $user_data['vehicle_type'] === 'yürüyerek' ? 'selected' : '' ?>>Yürüyerek</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Araç Plakası</label>
                                            <input type="text" class="form-control" name="license_plate" 
                                                   value="<?= sanitize($user_data['license_plate']) ?>" 
                                                   placeholder="34 ABC 123">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Online Durumu</label>
                                            <div>
                                                <span class="badge bg-<?= $user_data['is_online'] ? 'success' : 'danger' ?> fs-6 me-2">
                                                    <i class="fas fa-circle me-1"></i>
                                                    <?= $user_data['is_online'] ? 'Online' : 'Offline' ?>
                                                </span>
                                                <?php if ($user_data['is_online']): ?>
                                                    <span class="badge bg-<?= $user_data['is_available'] ? 'info' : 'warning' ?> fs-6">
                                                        <?= $user_data['is_available'] ? 'Müsait' : 'Meşgul' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Son Konum</label>
                                            <div>
                                                <?php if ($user_data['current_latitude'] && $user_data['current_longitude']): ?>
                                                    <div class="text-success">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?= number_format($user_data['current_latitude'], 6) ?>, <?= number_format($user_data['current_longitude'], 6) ?>
                                                    </div>
                                                    <?php if ($user_data['last_location_update']): ?>
                                                        <small class="text-muted">
                                                            Son güncelleme: <?= formatDate($user_data['last_location_update']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Konum bilgisi yok</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
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
                                            Kurye İstatistikleri
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-3">
                                                <div class="h4 text-primary">
                                                    <?php
                                                    try {
                                                        $stats_stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE kurye_id = (SELECT id FROM kuryeler WHERE user_id = ?)", [getUserId()]);
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
                                                        $stats_stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE kurye_id = (SELECT id FROM kuryeler WHERE user_id = ?) AND status = 'delivered'", [getUserId()]);
                                                        echo number_format($stats_stmt->fetch()['count']);
                                                    } catch (Exception $e) {
                                                        echo '0';
                                                    }
                                                    ?>
                                                </div>
                                                <div class="text-muted">Tamamlanan</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="h4 text-warning">
                                                    <?php
                                                    try {
                                                        $stats_stmt = $db->query("SELECT SUM(delivery_fee) as total FROM siparisler WHERE kurye_id = (SELECT id FROM kuryeler WHERE user_id = ?) AND status = 'delivered'", [getUserId()]);
                                                        $total = $stats_stmt->fetch()['total'] ?? 0;
                                                        echo formatMoney($total);
                                                    } catch (Exception $e) {
                                                        echo formatMoney(0);
                                                    }
                                                    ?>
                                                </div>
                                                <div class="text-muted">Toplam Kazanç</div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="h4 text-info">
                                                    <?= formatDate($user_data['created_at']) ?>
                                                </div>
                                                <div class="text-muted">Başlangıç Tarihi</div>
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
