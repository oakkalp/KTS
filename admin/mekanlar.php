<?php
/**
 * Kurye Full System - Admin Mekan Yönetimi
 */

require_once '../config/config.php';
requireUserType('admin');

// Mekan işlemleri
$message = '';
$error = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDB();
        
        if ($action === 'update_status') {
            $mekan_id = (int)$_POST['mekan_id'];
            $status = clean($_POST['status']);
            
            $db->query("UPDATE mekanlar SET status = ? WHERE id = ?", [$status, $mekan_id]);
            $message = 'Mekan durumu güncellendi';
            
        } elseif ($action === 'delete_mekan') {
            $mekan_id = (int)$_POST['mekan_id'];
            
            // Mekanı sil (CASCADE ile user da silinir)
            $stmt = $db->query("SELECT user_id FROM mekanlar WHERE id = ?", [$mekan_id]);
            $mekan = $stmt->fetch();
            
            if ($mekan) {
                $db->query("DELETE FROM users WHERE id = ?", [$mekan['user_id']]);
                $message = 'Mekan silindi';
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Teslimat ücretini sistem ayarlarından al (HTML'de de kullanılacak)
$delivery_fee = (float)getSetting('delivery_fee', 5.00);

// Mekanları listele
try {
    $db = getDB();
    
    $stmt = $db->query("
        SELECT m.*, u.username, u.email, u.phone, u.created_at, u.last_login,
               COUNT(CASE WHEN s.status IN ('pending', 'accepted', 'preparing', 'ready', 'picked_up', 'delivering') THEN 1 END) as active_orders,
               COUNT(CASE WHEN s.status = 'delivered' AND (s.tahsilat_durumu IS NULL OR s.tahsilat_durumu = 'bekliyor') THEN 1 END) as pending_collection_orders,
               COALESCE(b.bakiye, 0) as current_balance
        FROM mekanlar m 
        JOIN users u ON m.user_id = u.id 
        LEFT JOIN siparisler s ON m.id = s.mekan_id 
        LEFT JOIN bakiye b ON m.user_id = b.user_id AND b.user_type = 'mekan'
        GROUP BY m.id
        ORDER BY m.created_at DESC
    ", []);
    $mekanlar = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Mekanlar yüklenemedi: ' . $e->getMessage();
    $mekanlar = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mekan Yönetimi - <?= APP_NAME ?></title>
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
                        <i class="fas fa-store me-2 text-success"></i>
                        Mekan Yönetimi
                    </h1>
                    <a href="users.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        Yeni Mekan Ekle
                    </a>
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
                                        <th>Mekan Adı</th>
                                        <th>Kategori</th>
                                        <th>Adres</th>
                                        <th>Telefon</th>
                                        <th>Email</th>
                                        <th>Durum</th>
                                        <th>Siparişler</th>
                                        <th>Gelir</th>
                                        <th>Kayıt Tarihi</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($mekanlar)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center py-4">
                                                <i class="fas fa-store fa-3x text-muted mb-3"></i>
                                                <p>Henüz mekan bulunmuyor.</p>
                                                <a href="users.php" class="btn btn-primary">
                                                    <i class="fas fa-plus me-2"></i>
                                                    İlk Mekanı Ekle
                                                </a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($mekanlar as $mekan): ?>
                                            <tr>
                                                <td><?= $mekan['id'] ?></td>
                                                <td>
                                                    <strong><?= sanitize($mekan['mekan_name']) ?></strong>
                                                    <br><small class="text-muted">@<?= sanitize($mekan['username']) ?></small>
                                                </td>
                                                <td><?= sanitize($mekan['category']) ?></td>
                                                <td>
                                                    <small><?= sanitize($mekan['address']) ?></small>
                                                    <?php if ($mekan['latitude'] && $mekan['longitude']): ?>
                                                        <br><small class="text-muted">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                            <?= number_format($mekan['latitude'], 4) ?>, <?= number_format($mekan['longitude'], 4) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= formatPhone($mekan['phone']) ?></td>
                                                <td><?= sanitize($mekan['email']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $mekan['status'] === 'active' ? 'success' : 
                                                        ($mekan['status'] === 'pending' ? 'warning' : 'secondary') 
                                                    ?>">
                                                        <?= ucfirst($mekan['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= $mekan['pending_collection_orders'] ?></strong> / <?= $mekan['active_orders'] ?>
                                                    <br><small class="text-muted">Tahsil Edilmemiş / Aktif</small>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $remaining_balance = $mekan['current_balance'];
                                                    $pending_orders = (int)$mekan['pending_collection_orders']; // Tahsil edilmemiş paketler
                                                    
                                                    if ($remaining_balance > 0): ?>
                                                        <strong class="text-danger">₺<?= number_format($remaining_balance, 2) ?></strong>
                                                        <br><small class="text-muted"><?= $pending_orders ?> × ₺<?= number_format($delivery_fee, 2) ?></small>
                                                    <?php elseif ($remaining_balance < 0): ?>
                                                        <strong class="text-success">₺<?= number_format(abs($remaining_balance), 2) ?></strong>
                                                        <br><small class="text-muted"><?= $pending_orders ?> × ₺<?= number_format($delivery_fee, 2) ?></small>
                                                    <?php else: ?>
                                                        <strong class="text-muted">₺0.00</strong>
                                                        <br><small class="text-muted"><?= $pending_orders ?> × ₺<?= number_format($delivery_fee, 2) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= formatDate($mekan['created_at']) ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($mekan['status'] === 'pending'): ?>
                                                            <button class="btn btn-outline-success" onclick="changeStatus(<?= $mekan['id'] ?>, 'active')" title="Onayla">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($mekan['status'] === 'active'): ?>
                                                            <button class="btn btn-outline-warning" onclick="changeStatus(<?= $mekan['id'] ?>, 'inactive')" title="Pasif Yap">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-success" onclick="changeStatus(<?= $mekan['id'] ?>, 'active')" title="Aktif Yap">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button class="btn btn-outline-danger" onclick="deleteMekan(<?= $mekan['id'] ?>)" title="Sil">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        
                                                        <button class="btn btn-primary" onclick="showTahsilatModal(<?= $mekan['id'] ?>, '<?= sanitize($mekan['mekan_name']) ?>')" title="Tahsilat Yap">
                                                            <i class="fas fa-money-bill"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Tahsilat Modal -->
    <div class="modal fade" id="tahsilatModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill me-2"></i>
                        Tahsilat Yap
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="tahsilatModalBody">
                    <!-- İçerik AJAX ile yüklenecek -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeStatus(mekanId, status) {
            const statusTexts = {
                'active': 'aktif',
                'inactive': 'pasif',
                'pending': 'beklemede'
            };
            
            if (confirm(`Mekan durumunu ${statusTexts[status]} yapmak istediğinizden emin misiniz?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="mekan_id" value="${mekanId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteMekan(mekanId) {
            if (confirm('Bu mekanı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve tüm siparişler de silinecektir!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_mekan">
                    <input type="hidden" name="mekan_id" value="${mekanId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showTahsilatModal(mekanId, mekanName) {
            // Modal içeriğini AJAX ile yükle
            fetch('ajax/get_tahsilat_info.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    mekan_id: mekanId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('tahsilatModalBody').innerHTML = data.html;
                    new bootstrap.Modal(document.getElementById('tahsilatModal')).show();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Tahsilat bilgisi alınırken hata:', error);
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            });
        }
        
        // Tahsilat işlevi - Global fonksiyon
        window.processTahsilat = function() {
            const formData = new FormData(document.getElementById("tahsilatForm"));
            
            fetch("ajax/process_tahsilat.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Tahsilat başarıyla kaydedildi!");
                    location.reload();
                } else {
                    alert("Hata: " + data.message);
                }
            })
            .catch(error => {
                console.error("Tahsilat işlemi hatası:", error);
                alert("Bir hata oluştu. Lütfen tekrar deneyin.");
            });
        }
        
        // Bakiye hesaplama - Global fonksiyon
        window.calculateBalance = function() {
            const toplam = parseFloat(document.getElementById("toplamTahsilat").value.replace(",", ""));
            const tahsil = parseFloat(document.getElementById("tahsilTutari").value) || 0;
            const fark = tahsil - toplam;
            const balanceInfo = document.getElementById("balanceInfo");
            const balanceText = document.getElementById("balanceText");
            
            if (Math.abs(fark) > 0.01) {
                balanceInfo.style.display = "block";
                if (fark > 0) {
                    balanceText.innerHTML = "<i class=\"fas fa-arrow-up text-success me-2\"></i>Fazla ödeme: <strong>₺" + fark.toFixed(2) + "</strong> (sonraki tahsilattan düşülecek)";
                } else {
                    balanceText.innerHTML = "<i class=\"fas fa-arrow-down text-danger me-2\"></i>Eksik ödeme: <strong>₺" + Math.abs(fark).toFixed(2) + "</strong> (sonraki tahsilata eklenecek)";
                }
            } else {
                balanceInfo.style.display = "none";
            }
        }
    </script>
</body>
</html>
