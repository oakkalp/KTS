<?php
/**
 * Kurye Full System - Mekan Yeni Sipariş Oluşturma
 */

require_once '../config/config.php';
requireUserType('mekan');

$message = '';
$error = '';

// Mekan bilgilerini al
try {
    $db = getDB();
    $user_id = getUserId();
    
    $stmt = $db->query("SELECT * FROM mekanlar WHERE user_id = ?", [$user_id]);
    $mekan = $stmt->fetch();
    
    if (!$mekan) {
        throw new Exception("Mekan bilgileri bulunamadı");
    }
    
    $mekan_id = $mekan['id'];
    
    // Aktif kuryeler
    $stmt = $db->query("
        SELECT k.*, u.full_name, u.phone 
        FROM kuryeler k 
        JOIN users u ON k.user_id = u.id 
        WHERE k.is_online = 1 AND k.is_available = 1 AND u.status = 'active'
        ORDER BY u.full_name
    ");
    $available_couriers = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Sistem hatası: ' . $e->getMessage();
    $mekan = null;
    $available_couriers = [];
}

// Form gönderildiğinde
if ($_POST && $mekan) {
    try {
        // Form verilerini al
        $customer_name = clean($_POST['customer_name']);
        $customer_phone = clean($_POST['customer_phone']);
        $customer_address = clean($_POST['customer_address']);
        $customer_latitude = !empty($_POST['customer_latitude']) ? (float)$_POST['customer_latitude'] : null;
        $customer_longitude = !empty($_POST['customer_longitude']) ? (float)$_POST['customer_longitude'] : null;
        
        $order_items = $_POST['order_items'] ?? [];
        $total_amount = (float)$_POST['total_amount'];
        $delivery_fee = (float)($_POST['delivery_fee'] ?? getSetting('delivery_fee', 5.00));
        $preparation_time = (int)($_POST['preparation_time'] ?? 15);
        $priority = clean($_POST['priority'] ?? 'normal');
        $notes = clean($_POST['notes'] ?? '');
        $payment_method = clean($_POST['payment_method'] ?? 'nakit');
        
        $kurye_id = !empty($_POST['kurye_id']) ? (int)$_POST['kurye_id'] : null;
        
        // Validation
        if (empty($customer_name) || empty($customer_phone) || empty($customer_address)) {
            throw new Exception('Müşteri bilgileri zorunludur');
        }
        
        if (empty($order_items)) {
            throw new Exception('En az bir ürün eklemelisiniz');
        }
        
        if ($total_amount <= 0) {
            throw new Exception('Geçerli bir tutar giriniz');
        }
        
        // Ödeme yöntemi kontrolü
        $valid_payment_methods = ['nakit', 'kapida_kart', 'online_kart'];
        if (!in_array($payment_method, $valid_payment_methods)) {
            throw new Exception('Geçersiz ödeme yöntemi');
        }
        
        // Telefon formatını kontrol et
        if (!isValidPhone($customer_phone)) {
            throw new Exception('Geçerli bir telefon numarası giriniz');
        }
        
        // Sipariş numarası oluştur
        $order_number = generateOrderNumber();
        
        // Komisyon hesapla
        $commission_rate = (float)getSetting('commission_rate', 15.00);
        $commission_amount = ($total_amount * $commission_rate) / 100;
        
        // Teslimat süresi tahmini
        $estimated_time = 30; // Varsayılan 30 dakika
        if ($kurye_id && $customer_latitude && $customer_longitude && $mekan['latitude'] && $mekan['longitude']) {
            // Kurye konumunu al
            $stmt = $db->query("SELECT current_latitude, current_longitude, vehicle_type FROM kuryeler WHERE id = ?", [$kurye_id]);
            $kurye_location = $stmt->fetch();
            
            if ($kurye_location && $kurye_location['current_latitude'] && $kurye_location['current_longitude']) {
                // Kurye -> Mekan mesafesi
                $distance_to_restaurant = calculateDistance(
                    $kurye_location['current_latitude'],
                    $kurye_location['current_longitude'],
                    $mekan['latitude'],
                    $mekan['longitude']
                );
                
                // Mekan -> Müşteri mesafesi
                $distance_to_customer = calculateDistance(
                    $mekan['latitude'],
                    $mekan['longitude'],
                    $customer_latitude,
                    $customer_longitude
                );
                
                $total_distance = $distance_to_restaurant + $distance_to_customer;
                $estimated_time = estimateDeliveryTime($total_distance, $kurye_location['vehicle_type']);
            }
        }
        
        $db->beginTransaction();
        
        // Beklenen alım zamanını hesapla
        $expected_pickup_time = date('Y-m-d H:i:s', strtotime("+{$preparation_time} minutes"));
        
        // Sipariş kaydet
        $stmt = $db->query("
            INSERT INTO siparisler (
                order_number, mekan_id, kurye_id, customer_name, customer_phone, customer_address,
                delivery_address, customer_latitude, customer_longitude, order_details, total_amount, delivery_fee,
                commission_amount, status, priority, estimated_delivery_time, notes, preparation_time, expected_pickup_time, payment_method
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $order_number, $mekan_id, $kurye_id, $customer_name, $customer_phone, $customer_address,
            $customer_address, $customer_latitude, $customer_longitude, json_encode($order_items), $total_amount,
            $delivery_fee, $commission_amount, $kurye_id ? 'accepted' : 'pending', $priority,
            $estimated_time, $notes, $preparation_time, $expected_pickup_time, $payment_method
        ]);
        
        $order_id = $db->lastInsertId();
        
        // Durum geçmişi kaydet (eğer tablo varsa)
        try {
            $db->query("
                INSERT INTO siparis_durum_gecmisi (siparis_id, old_status, new_status, changed_by, notes) 
                VALUES (?, NULL, ?, ?, ?)
            ", [$order_id, $kurye_id ? 'accepted' : 'pending', $user_id, 'Sipariş oluşturuldu']);
            
            if ($kurye_id) {
                // Kurye atandıysa durum geçmişi ekle
                $db->query("
                    INSERT INTO siparis_durum_gecmisi (siparis_id, old_status, new_status, changed_by, notes) 
                    VALUES (?, 'pending', 'accepted', ?, ?)
                ", [$order_id, $user_id, 'Kurye atandı']);
            }
        } catch (Exception $e) {
            // Sipariş durum geçmişi tablosu yoksa log'a yaz ve devam et
            writeLog("Sipariş durum geçmişi kaydedilemedi: " . $e->getMessage(), 'WARNING');
        }
        
        $db->commit();
        
        // Bildirim gönder
        try {
            if ($kurye_id) {
                // Kuryeye bildirim
                $stmt = $db->query("SELECT device_token FROM users WHERE id = (SELECT user_id FROM kuryeler WHERE id = ?)", [$kurye_id]);
                $kurye_token = $stmt->fetch()['device_token'] ?? null;
                
                if ($kurye_token) {
                    sendPushNotification(
                        [$kurye_token],
                        'Yeni Sipariş Atandı',
                        "Sipariş No: {$order_number} - {$customer_name}",
                        ['order_id' => $order_id, 'type' => 'new_order']
                    );
                }
            } else {
                // Otomatik atama - tüm müsait kuryelere bildirim gönder
                $stmt = $db->query("
                    SELECT u.device_token 
                    FROM users u 
                    JOIN kuryeler k ON u.id = k.user_id 
                    WHERE k.is_online = 1 AND k.is_available = 1 AND u.device_token IS NOT NULL AND u.status = 'active'
                ");
                $courier_tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($courier_tokens)) {
                    sendPushNotification(
                        $courier_tokens,
                        'Yeni Sipariş Mevcut',
                        "Sipariş No: {$order_number} - {$customer_name} - {$mekan['mekan_name']}",
                        ['order_id' => $order_id, 'type' => 'new_order_available']
                    );
                }
            }
            
            // Admin'lere bildirim
            // Admin'e bildirim gönder
            $stmt = $db->query("SELECT device_token FROM users WHERE user_type = 'admin' AND device_token IS NOT NULL");
            $admin_tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($admin_tokens)) {
                sendPushNotification(
                    $admin_tokens,
                    'Yeni Sipariş Oluşturuldu',
                    "{$mekan['mekan_name']} - Sipariş No: {$order_number}",
                    ['order_id' => $order_id, 'type' => 'order_created']
                );
            }
            
            // Müsait kuryelere bildirim gönder
            $stmt = $db->query("
                SELECT u.device_token 
                FROM users u 
                JOIN kuryeler k ON u.id = k.user_id 
                WHERE u.user_type = 'kurye' 
                AND u.device_token IS NOT NULL 
                AND k.is_online = 1 
                AND k.is_available = 1
                AND u.status = 'active'
            ");
            $courier_tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($courier_tokens)) {
                sendPushNotification(
                    $courier_tokens,
                    'Yeni Sipariş Geldi! 🚚',
                    "{$mekan['mekan_name']} - {$order_number} - {$total_amount}₺",
                    [
                        'order_id' => $order_id, 
                        'type' => 'new_order',
                        'restaurant_name' => $mekan['mekan_name'],
                        'total_amount' => $total_amount,
                        'delivery_fee' => $delivery_fee
                    ]
                );
            }
            
        } catch (Exception $e) {
            writeLog("Notification error: " . $e->getMessage(), 'WARNING');
        }
        
        $message = "Sipariş başarıyla oluşturuldu! Sipariş No: {$order_number}";
        
        // Form verilerini temizle
        $_POST = [];
        
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
    <title>Yeni Sipariş - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .total-display {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
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
                        <i class="fas fa-plus me-2 text-success"></i>
                        Yeni Sipariş Oluştur
                    </h1>
                    <a href="siparisler.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Siparişlere Dön
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
                
                <form method="POST" id="orderForm">
                    <div class="row">
                        <!-- Müşteri Bilgileri -->
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-user me-2"></i>
                                        Müşteri Bilgileri
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Müşteri Adı *</label>
                                        <input type="text" class="form-control" name="customer_name" 
                                               value="<?= sanitize($_POST['customer_name'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Telefon *</label>
                                        <input type="tel" class="form-control" name="customer_phone" 
                                               value="<?= sanitize($_POST['customer_phone'] ?? '') ?>" 
                                               placeholder="05551234567" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Teslimat Adresi *</label>
                                        <textarea class="form-control" name="customer_address" rows="3" required><?= sanitize($_POST['customer_address'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Enlem (Latitude)</label>
                                            <input type="number" step="0.000001" class="form-control" name="customer_latitude" 
                                                   value="<?= sanitize($_POST['customer_latitude'] ?? '') ?>" placeholder="41.0082">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Boylam (Longitude)</label>
                                            <input type="number" step="0.000001" class="form-control" name="customer_longitude" 
                                                   value="<?= sanitize($_POST['customer_longitude'] ?? '') ?>" placeholder="28.9784">
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="getCurrentLocation()">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            Mevcut Konumu Al
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sipariş Detayları -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-clipboard-list me-2"></i>
                                        Sipariş Detayları
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Öncelik</label>
                                        <select class="form-select" name="priority">
                                            <option value="normal" <?= ($_POST['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
                                            <option value="urgent" <?= ($_POST['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Acil</option>
                                            <option value="express" <?= ($_POST['priority'] ?? '') === 'express' ? 'selected' : '' ?>>Ekspres</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Kurye Seç</label>
                                        <select class="form-select" name="kurye_id">
                                            <option value="">Otomatik Atama</option>
                                            <?php foreach ($available_couriers as $courier): ?>
                                                <option value="<?= $courier['id'] ?>" 
                                                        <?= ($_POST['kurye_id'] ?? '') == $courier['id'] ? 'selected' : '' ?>>
                                                    <?= sanitize($courier['full_name']) ?> 
                                                    (<?= ucfirst($courier['vehicle_type']) ?>)
                                                    <?php if ($courier['license_plate']): ?>
                                                        - <?= sanitize($courier['license_plate']) ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">
                                            Boş bırakırsanız sistem otomatik olarak en uygun kuryeyi atayacaktır.
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Ödeme Yöntemi *</label>
                                        <select class="form-select" name="payment_method" required>
                                            <option value="nakit" <?= ($_POST['payment_method'] ?? 'nakit') === 'nakit' ? 'selected' : '' ?>>
                                                <i class="fas fa-money-bill-wave me-2"></i>Nakit
                                            </option>
                                            <option value="kapida_kart" <?= ($_POST['payment_method'] ?? '') === 'kapida_kart' ? 'selected' : '' ?>>
                                                <i class="fas fa-credit-card me-2"></i>Kapıda Kredi Kartı
                                            </option>
                                            <option value="online_kart" <?= ($_POST['payment_method'] ?? '') === 'online_kart' ? 'selected' : '' ?>>
                                                <i class="fas fa-globe me-2"></i>Online Kredi Kartı
                                            </option>
                                        </select>
                                        <div class="form-text">
                                            <small>
                                                <i class="fas fa-info-circle me-1"></i>
                                                <strong>Nakit:</strong> Kurye nakit tahsil eder |
                                                <strong>Kapıda Kart:</strong> Kurye POS cihazı ile tahsil eder |
                                                <strong>Online Kart:</strong> Ödeme önceden alınmıştır
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Notlar</label>
                                        <textarea class="form-control" name="notes" rows="3" 
                                                  placeholder="Özel talimatlar, allerji uyarıları vb."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ürünler ve Fiyat -->
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-shopping-cart me-2"></i>
                                        Ürünler
                                    </h5>
                                    <button type="button" class="btn btn-sm btn-success" onclick="addOrderItem()">
                                        <i class="fas fa-plus me-1"></i>
                                        Ürün Ekle
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="orderItems">
                                        <!-- Ürünler buraya eklenecek -->
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="addOrderItem()">
                                            <i class="fas fa-plus me-1"></i>
                                            İlk Ürünü Ekle
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Fiyat Özeti -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-calculator me-2"></i>
                                        Fiyat Özeti
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Hazırlık Süresi (dakika)</label>
                                            <input type="number" class="form-control" name="preparation_time" 
                                                   value="<?= $_POST['preparation_time'] ?? 15 ?>" min="5" max="120">
                                            <div class="form-text">Siparişin hazırlanma süresi</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Teslimat Ücreti</label>
                                            <input type="number" step="0.01" class="form-control" name="delivery_fee" 
                                                   value="<?= $_POST['delivery_fee'] ?? getSetting('delivery_fee', 5.00) ?>" 
                                                   onchange="calculateTotal()">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Toplam Tutar</label>
                                            <input type="number" step="0.01" class="form-control" name="total_amount" 
                                                   value="<?= $_POST['total_amount'] ?? 0 ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="total-display" id="totalDisplay">
                                        <h4 class="mb-0">Toplam: ₺0.00</h4>
                                        <small>Teslimat dahil</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sipariş Ver -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-check me-2"></i>
                                    Sipariş Oluştur
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let itemCounter = 0;
        
        function addOrderItem() {
            itemCounter++;
            const itemHtml = `
                <div class="order-item" id="item-${itemCounter}">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Ürün Adı</label>
                            <input type="text" class="form-control" name="order_items[${itemCounter}][name]" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Adet</label>
                            <input type="number" class="form-control" name="order_items[${itemCounter}][quantity]" 
                                   value="1" min="1" onchange="calculateTotal()" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Birim Fiyat</label>
                            <input type="number" step="0.01" class="form-control" name="order_items[${itemCounter}][price]" 
                                   onchange="calculateTotal()" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-sm d-block" onclick="removeOrderItem(${itemCounter})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <label class="form-label">Açıklama</label>
                            <input type="text" class="form-control" name="order_items[${itemCounter}][description]" 
                                   placeholder="Ürün açıklaması, seçenekler vb.">
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('orderItems').insertAdjacentHTML('beforeend', itemHtml);
            calculateTotal();
        }
        
        function removeOrderItem(itemId) {
            document.getElementById(`item-${itemId}`).remove();
            calculateTotal();
        }
        
        function calculateTotal() {
            let subtotal = 0;
            
            // Tüm ürünlerin fiyatını topla
            document.querySelectorAll('.order-item').forEach(item => {
                const quantity = parseFloat(item.querySelector('input[name*="[quantity]"]').value) || 0;
                const price = parseFloat(item.querySelector('input[name*="[price]"]').value) || 0;
                subtotal += quantity * price;
            });
            
            const deliveryFee = parseFloat(document.querySelector('input[name="delivery_fee"]').value) || 0;
            const total = subtotal + deliveryFee;
            
            document.querySelector('input[name="total_amount"]').value = total.toFixed(2);
            document.getElementById('totalDisplay').innerHTML = `
                <h4 class="mb-0">Toplam: ₺${total.toFixed(2)}</h4>
                <small>Ürünler: ₺${subtotal.toFixed(2)} + Teslimat: ₺${deliveryFee.toFixed(2)}</small>
            `;
        }
        
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        document.querySelector('input[name="customer_latitude"]').value = position.coords.latitude;
                        document.querySelector('input[name="customer_longitude"]').value = position.coords.longitude;
                        alert('Konum bilgisi alındı!');
                    },
                    function(error) {
                        alert('Konum alınamadı: ' + error.message);
                    }
                );
            } else {
                alert('Tarayıcınız konum özelliğini desteklemiyor.');
            }
        }
        
        // Form submit kontrolü
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            const items = document.querySelectorAll('.order-item');
            if (items.length === 0) {
                e.preventDefault();
                alert('En az bir ürün eklemelisiniz!');
                return false;
            }
            
            const total = parseFloat(document.querySelector('input[name="total_amount"]').value);
            if (total <= 0) {
                e.preventDefault();
                alert('Geçerli bir toplam tutar giriniz!');
                return false;
            }
        });
        
        // Sayfa yüklendiğinde ilk ürünü ekle
        document.addEventListener('DOMContentLoaded', function() {
            addOrderItem();
        });
    </script>
</body>
</html>
