<?php
/**
 * Kurye Full System - Admin Kurye Yönetimi
 */

require_once '../config/config.php';
requireUserType('admin');

// Kurye işlemleri
$message = '';
$error = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDB();
        
        if ($action === 'toggle_status') {
            $kurye_id = (int)$_POST['kurye_id'];
            $is_online = (int)$_POST['is_online'];
            
            $db->query("UPDATE kuryeler SET is_online = ? WHERE id = ?", [$is_online, $kurye_id]);
            $message = 'Kurye durumu güncellendi';
            
        } elseif ($action === 'delete_kurye') {
            $kurye_id = (int)$_POST['kurye_id'];
            
            // Kuryeyi sil (CASCADE ile user da silinir)
            $stmt = $db->query("SELECT user_id FROM kuryeler WHERE id = ?", [$kurye_id]);
            $kurye = $stmt->fetch();
            
            if ($kurye) {
                $db->query("DELETE FROM users WHERE id = ?", [$kurye['user_id']]);
                $message = 'Kurye silindi';
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Teslimat ücretini ve komisyon oranını al
$delivery_fee = (float)getSetting('delivery_fee', 40.00);
$commission_rate = (float)getSetting('commission_rate', 15.00);

// Kuryeler listele
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT k.*, u.username, u.email, u.phone, u.full_name, u.created_at, u.last_login,
               COUNT(CASE WHEN s.status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering') THEN 1 END) as active_orders,
               COUNT(CASE WHEN s.status = 'delivered' AND (s.odeme_durumu IS NULL OR s.odeme_durumu = 'bekliyor') THEN 1 END) as pending_payment_orders,
               COALESCE(b.bakiye, 0) as current_balance,
               AVG(CASE WHEN s.status = 'delivered' AND s.delivered_at IS NOT NULL 
                   THEN TIMESTAMPDIFF(MINUTE, s.created_at, s.delivered_at) END) as avg_delivery_time
        FROM kuryeler k 
        JOIN users u ON k.user_id = u.id 
        LEFT JOIN siparisler s ON k.id = s.kurye_id 
        LEFT JOIN bakiye b ON k.user_id = b.user_id AND b.user_type = 'kurye'
        GROUP BY k.id
        ORDER BY k.is_online DESC, k.last_location_update DESC
    ");
    $kuryeler = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Kuryeler yüklenemedi: ' . $e->getMessage();
    $kuryeler = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurye Yönetimi - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .online-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-online {
            background-color: #28a745;
            animation: pulse 2s infinite;
        }
        .status-offline {
            background-color: #dc3545;
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
                        <i class="fas fa-motorcycle me-2 text-warning"></i>
                        Kurye Yönetimi
                    </h1>
                    <div class="btn-toolbar">
                        <div class="btn-group me-2">
                            <a href="users.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>
                                Yeni Kurye Ekle
                            </a>
                            <a href="map-tracking.php" class="btn btn-outline-primary">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                Haritada Görüntüle
                            </a>
                        </div>
                    </div>
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
                                        <th>Kurye</th>
                                        <th>İletişim</th>
                                        <th>Araç</th>
                                        <th>Durum</th>
                                        <th>Konum</th>
                                        <th>Performans</th>
                                        <th>Kazanç</th>
                                        <th>Son Giriş</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($kuryeler)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
                                                <i class="fas fa-motorcycle fa-3x text-muted mb-3"></i>
                                                <p>Henüz kurye bulunmuyor.</p>
                                                <a href="users.php" class="btn btn-primary">
                                                    <i class="fas fa-plus me-2"></i>
                                                    İlk Kuryeyi Ekle
                                                </a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($kuryeler as $kurye): ?>
                                            <tr>
                                                <td><?= $kurye['id'] ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="online-indicator <?= $kurye['is_online'] ? 'status-online' : 'status-offline' ?>"></span>
                                                        <div>
                                                            <strong><?= sanitize($kurye['full_name']) ?></strong>
                                                            <br><small class="text-muted">@<?= sanitize($kurye['username']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div><?= formatPhone($kurye['phone']) ?></div>
                                                    <small class="text-muted"><?= sanitize($kurye['email']) ?></small>
                                                </td>
                                                <td>
                                                    <div><i class="fas fa-motorcycle me-1"></i><?= ucfirst($kurye['vehicle_type']) ?></div>
                                                    <?php if ($kurye['license_plate']): ?>
                                                        <small class="text-muted"><?= sanitize($kurye['license_plate']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($kurye['is_online']): ?>
                                                        <span class="badge bg-success">Çevrimiçi</span>
                                                        <?php if ($kurye['is_available']): ?>
                                                            <br><span class="badge bg-info mt-1">Müsait</span>
                                                        <?php else: ?>
                                                            <br><span class="badge bg-warning mt-1">Meşgul</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Çevrimdışı</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($kurye['current_latitude'] && $kurye['current_longitude']): ?>
                                                        <div class="small">
                                                            <i class="fas fa-map-marker-alt text-success"></i>
                                                            <?= number_format($kurye['current_latitude'], 4) ?>
                                                            <br><?= number_format($kurye['current_longitude'], 4) ?>
                                                        </div>
                                                        <?php if ($kurye['last_location_update']): ?>
                                                            <small class="text-muted"><?= formatDate($kurye['last_location_update']) ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <small class="text-muted">Konum yok</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= $kurye['pending_payment_orders'] ?></strong> / <?= $kurye['active_orders'] ?>
                                                    <br><small class="text-muted">Ödeme Bekleyen / Aktif</small>
                                                    <?php if ($kurye['avg_delivery_time']): ?>
                                                        <br><small class="text-info">Ort: <?= round($kurye['avg_delivery_time']) ?> dk</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $remaining_balance = $kurye['current_balance'];
                                                    $pending_orders = (int)$kurye['pending_payment_orders']; // Ödeme bekleyen paketler
                                                    $net_per_delivery = $delivery_fee * (1 - $commission_rate / 100); // Komisyon sonrası kurye payı
                                                    
                                                    if ($remaining_balance > 0): ?>
                                                        <strong class="text-success">₺<?= number_format($remaining_balance, 2) ?></strong>
                                                        <br><small class="text-muted"><?= $pending_orders ?> × ₺<?= number_format($net_per_delivery, 2) ?></small>
                                                    <?php elseif ($remaining_balance < 0): ?>
                                                        <strong class="text-danger">₺<?= number_format(abs($remaining_balance), 2) ?></strong>
                                                        <br><small class="text-muted">Kurye borçlu</small>
                                                    <?php else: ?>
                                                        <strong class="text-muted">₺0.00</strong>
                                                        <br><small class="text-muted"><?= $pending_orders ?> × ₺<?= number_format($net_per_delivery, 2) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= formatDate($kurye['last_login']) ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($kurye['is_online']): ?>
                                                            <button class="btn btn-outline-warning" onclick="toggleStatus(<?= $kurye['id'] ?>, 0)" title="Offline Yap">
                                                                <i class="fas fa-power-off"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-success" onclick="toggleStatus(<?= $kurye['id'] ?>, 1)" title="Online Yap">
                                                                <i class="fas fa-power-off"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button class="btn btn-outline-danger" onclick="deleteKurye(<?= $kurye['id'] ?>)" title="Sil">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        
                                                        <button class="btn btn-success" onclick="showOdemeModal(<?= $kurye['id'] ?>, '<?= sanitize($kurye['full_name']) ?>')" title="Ödeme Yap">
                                                            <i class="fas fa-hand-holding-usd"></i>
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

    <!-- Ödeme Modal -->
    <div class="modal fade" id="odemeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-hand-holding-usd me-2"></i>
                        Kurye Ödemesi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="odemeModalBody">
                    <!-- İçerik AJAX ile yüklenecek -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleStatus(kuryeId, isOnline) {
            const statusText = isOnline ? 'online' : 'offline';
            
            if (confirm(`Kuryeyi ${statusText} yapmak istediğinizden emin misiniz?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="kurye_id" value="${kuryeId}">
                    <input type="hidden" name="is_online" value="${isOnline}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteKurye(kuryeId) {
            if (confirm('Bu kuryeyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve tüm siparişleri de etkileyecektir!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_kurye">
                    <input type="hidden" name="kurye_id" value="${kuryeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showOdemeModal(kuryeId, kuryeName) {
            // Modal içeriğini AJAX ile yükle
            fetch('ajax/get_odeme_info.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    kurye_id: kuryeId
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        document.getElementById('odemeModalBody').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('odemeModal')).show();
                    } else {
                        alert('Hata: ' + data.message);
                    }
                } catch (e) {
                    console.error('JSON parse hatası:', e);
                    console.error('Gelen response:', text);
                    alert('Sunucudan geçersiz yanıt geldi. Konsolu kontrol edin.');
                }
            })
            .catch(error => {
                console.error('Ödeme bilgisi alınırken hata:', error);
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            });
        }
        
        // Ödeme işlevi - Global fonksiyon
        window.processOdeme = function() {
            const formData = new FormData(document.getElementById("odemeForm"));
            
            fetch("ajax/process_odeme.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Ödeme başarıyla kaydedildi!");
                    location.reload();
                } else {
                    alert("Hata: " + data.message);
                }
            })
            .catch(error => {
                console.error("Ödeme işlemi hatası:", error);
                alert("Bir hata oluştu. Lütfen tekrar deneyin.");
            });
        }
        
        // Bakiye hesaplama - Global fonksiyon
        window.calculateKuryeBalance = function() {
            const toplam = parseFloat(document.getElementById("toplamOdeme").value.replace(",", ""));
            const odeme = parseFloat(document.getElementById("odemeTutari").value) || 0;
            const fark = odeme - toplam;
            const balanceInfo = document.getElementById("balanceInfo");
            const balanceText = document.getElementById("balanceText");
            
            if (Math.abs(fark) > 0.01) {
                balanceInfo.style.display = "block";
                if (fark > 0) {
                    balanceText.innerHTML = "<i class=\"fas fa-arrow-up text-success me-2\"></i>Fazla ödeme: <strong>₺" + fark.toFixed(2) + "</strong> (kuryenin borcu olacak)";
                } else {
                    balanceText.innerHTML = "<i class=\"fas fa-arrow-down text-danger me-2\"></i>Eksik ödeme: <strong>₺" + Math.abs(fark).toFixed(2) + "</strong> (kuryenin alacağı kalacak)";
                }
            } else {
                balanceInfo.style.display = "none";
            }
        }
        
        // Auto refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
