<?php
/**
 * Kurye Full System - Admin Kullanıcı Yönetimi
 */

require_once '../config/config.php';
requireUserType('admin');

// Kullanıcı işlemleri
$message = '';
$error = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDB();
        
        if ($action === 'add_user') {
            $username = clean($_POST['username']);
            $email = clean($_POST['email']);
            $password = $_POST['password'];
            $full_name = clean($_POST['full_name']);
            $phone = clean($_POST['phone']);
            $user_type = clean($_POST['user_type']);
            
            // Validation
            if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($user_type)) {
                throw new Exception('Tüm alanlar zorunludur');
            }
            
            // Kullanıcı adı kontrolü
            $stmt = $db->query("SELECT id FROM users WHERE username = ?", [$username]);
            if ($stmt->fetch()) {
                throw new Exception('Bu kullanıcı adı zaten kullanılıyor');
            }
            
            // Email kontrolü
            $stmt = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
            if ($stmt->fetch()) {
                throw new Exception('Bu email adresi zaten kullanılıyor');
            }
            
            // Kullanıcı ekle
            $hashed_password = hashPassword($password);
            $stmt = $db->query(
                "INSERT INTO users (username, email, password, full_name, phone, user_type, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
                [$username, $email, $hashed_password, $full_name, $phone, $user_type]
            );
            
            $user_id = $db->lastInsertId();
            
            // User type'a göre ek tablolara kayıt
            if ($user_type === 'mekan') {
                $mekan_address = clean($_POST['mekan_address'] ?? 'Adres girilmedi');
                $category = clean($_POST['category'] ?? 'Restoran');
                
                $db->query(
                    "INSERT INTO mekanlar (user_id, mekan_name, address, category, status) VALUES (?, ?, ?, ?, 'pending')",
                    [$user_id, $full_name, $mekan_address, $category]
                );
            } elseif ($user_type === 'kurye') {
                $license_plate = clean($_POST['license_plate'] ?? '');
                $vehicle_type = clean($_POST['vehicle_type'] ?? 'motosiklet');
                
                $db->query(
                    "INSERT INTO kuryeler (user_id, license_plate, vehicle_type, is_online, is_available) VALUES (?, ?, ?, 0, 1)",
                    [$user_id, $license_plate, $vehicle_type]
                );
            }
            
            $message = 'Kullanıcı başarıyla eklendi';
            
        } elseif ($action === 'edit_user') {
            $user_id = (int)$_POST['user_id'];
            $username = clean($_POST['username']);
            $full_name = clean($_POST['full_name']);
            $email = clean($_POST['email']);
            $phone = clean($_POST['phone']);
            $password = clean($_POST['password']);
            $status = clean($_POST['status']);
            
            // Email ve username benzersizlik kontrolü (mevcut kullanıcı hariç)
            $check_stmt = $db->query(
                "SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?",
                [$email, $username, $user_id]
            );
            
            if ($check_stmt->fetch()) {
                throw new Exception('Bu email veya kullanıcı adı zaten kullanılıyor');
            }
            
            // Şifre güncelleme
            if (!empty($password)) {
                $hashed_password = hashPassword($password);
                $db->query(
                    "UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, phone = ?, status = ? WHERE id = ?",
                    [$username, $email, $hashed_password, $full_name, $phone, $status, $user_id]
                );
            } else {
                $db->query(
                    "UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, status = ? WHERE id = ?",
                    [$username, $email, $full_name, $phone, $status, $user_id]
                );
            }
            
            $message = 'Kullanıcı başarıyla güncellendi';
            
        } elseif ($action === 'update_status') {
            $user_id = (int)$_POST['user_id'];
            $status = clean($_POST['status']);
            
            $db->query("UPDATE users SET status = ? WHERE id = ?", [$status, $user_id]);
            $message = 'Kullanıcı durumu güncellendi';
            
        } elseif ($action === 'delete_user') {
            $user_id = (int)$_POST['user_id'];
            
            // Kullanıcıyı sil (CASCADE ile ilgili kayıtlar da silinir)
            $db->query("DELETE FROM users WHERE id = ?", [$user_id]);
            $message = 'Kullanıcı silindi';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Kullanıcıları listele
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT u.*, 
               CASE 
                   WHEN u.user_type = 'mekan' THEN m.mekan_name
                   WHEN u.user_type = 'kurye' THEN k.license_plate
                   ELSE NULL 
               END as additional_info,
               CASE 
                   WHEN u.user_type = 'kurye' THEN k.is_online
                   ELSE NULL 
               END as is_online
        FROM users u 
        LEFT JOIN mekanlar m ON u.id = m.user_id 
        LEFT JOIN kuryeler k ON u.id = k.user_id 
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Kullanıcılar yüklenemedi: ' . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-users me-2 text-primary"></i>
                        Kullanıcı Yönetimi
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-2"></i>
                        Yeni Kullanıcı
                    </button>
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
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Kullanıcı Adı</th>
                                        <th>Ad Soyad</th>
                                        <th>Email</th>
                                        <th>Telefon</th>
                                        <th>Tip</th>
                                        <th>Durum</th>
                                        <th>Ek Bilgi</th>
                                        <th>Kayıt Tarihi</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= $user['id'] ?></td>
                                            <td>
                                                <strong><?= sanitize($user['username']) ?></strong>
                                                <?php if ($user['user_type'] === 'kurye' && $user['is_online']): ?>
                                                    <span class="badge bg-success ms-1">Online</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= sanitize($user['full_name']) ?></td>
                                            <td><?= sanitize($user['email']) ?></td>
                                            <td><?= formatPhone($user['phone']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $user['user_type'] === 'admin' ? 'primary' : 
                                                    ($user['user_type'] === 'mekan' ? 'success' : 'warning') 
                                                ?>">
                                                    <?= ucfirst($user['user_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $user['status'] === 'active' ? 'success' : 
                                                    ($user['status'] === 'inactive' ? 'secondary' : 'danger') 
                                                ?>">
                                                    <?= ucfirst($user['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= sanitize($user['additional_info']) ?></td>
                                            <td><?= formatDate($user['created_at']) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($user['status'] === 'active'): ?>
                                                        <button class="btn btn-outline-warning" onclick="changeStatus(<?= $user['id'] ?>, 'inactive')">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-success" onclick="changeStatus(<?= $user['id'] ?>, 'active')">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($user['username'] !== 'admin'): ?>
                                                        <button class="btn btn-outline-danger" onclick="deleteUser(<?= $user['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni Kullanıcı Ekle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Kullanıcı Adı</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Şifre</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="text" class="form-control" name="phone" placeholder="05551234567">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kullanıcı Tipi</label>
                            <select class="form-select" name="user_type" required onchange="toggleUserTypeFields(this.value)">
                                <option value="">Seçiniz</option>
                                <option value="admin">Admin</option>
                                <option value="mekan">Mekan</option>
                                <option value="kurye">Kurye</option>
                            </select>
                        </div>
                        
                        <!-- Kurye için ek alanlar -->
                        <div id="kuryeFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Araç Plakası</label>
                                <input type="text" class="form-control" name="license_plate" placeholder="34 ABC 123">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Araç Tipi</label>
                                <select class="form-select" name="vehicle_type">
                                    <option value="motosiklet">Motosiklet</option>
                                    <option value="bisiklet">Bisiklet</option>
                                    <option value="araba">Araba</option>
                                    <option value="yürüyerek">Yürüyerek</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Mekan için ek alanlar -->
                        <div id="mekanFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Mekan Adresi</label>
                                <textarea class="form-control" name="mekan_address" rows="2" placeholder="Mekan adresini girin"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kategori</label>
                                <select class="form-select" name="category">
                                    <option value="Restoran">Restoran</option>
                                    <option value="Market">Market</option>
                                    <option value="Eczane">Eczane</option>
                                    <option value="Kafe">Kafe</option>
                                    <option value="Diğer">Diğer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modals -->
    <?php foreach ($users as $user): ?>
    <div class="modal fade" id="editUserModal<?= $user['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Kullanıcı Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Kullanıcı Adı</label>
                            <input type="text" class="form-control" name="username" value="<?= sanitize($user['username']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" name="full_name" value="<?= sanitize($user['full_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= sanitize($user['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="text" class="form-control" name="phone" value="<?= sanitize($user['phone']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Yeni Şifre (Boş bırakılırsa değişmez)</label>
                            <input type="password" class="form-control" name="password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status">
                                <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleUserTypeFields(userType) {
            document.getElementById('kuryeFields').style.display = 'none';
            document.getElementById('mekanFields').style.display = 'none';
            
            if (userType === 'kurye') {
                document.getElementById('kuryeFields').style.display = 'block';
            } else if (userType === 'mekan') {
                document.getElementById('mekanFields').style.display = 'block';
            }
        }
        function changeStatus(userId, status) {
            if (confirm('Kullanıcı durumunu değiştirmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteUser(userId) {
            if (confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
