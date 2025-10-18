<?php
/**
 * Mevcut siparişlere koordinat ekleme aracı
 */

require_once '../config/config.php';
requireUserType('admin');

$db = getDB();

if ($_POST) {
    try {
        $order_id = (int)$_POST['order_id'];
        $latitude = (float)$_POST['latitude'];
        $longitude = (float)$_POST['longitude'];
        
        $db->query("
            UPDATE siparisler 
            SET customer_latitude = ?, customer_longitude = ? 
            WHERE id = ?
        ", [$latitude, $longitude, $order_id]);
        
        $success = "Sipariş #$order_id koordinatları güncellendi!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Koordinatı olmayan siparişleri listele
$orders = $db->query("
    SELECT id, order_number, customer_name, customer_address, 
           customer_latitude, customer_longitude, created_at
    FROM siparisler 
    WHERE customer_latitude IS NULL OR customer_longitude IS NULL
    ORDER BY created_at DESC
")->fetchAll();

// Tüm siparişleri güncelle butonu için
if (isset($_POST['update_all'])) {
    $updated = 0;
    
    foreach ($orders as $order) {
        // Adres bazlı koordinat bul
        $address = $order['customer_address'];
        $coordinates = findCoordinatesFromAddress($address);
        
        if ($coordinates) {
            $db->query("
                UPDATE siparisler 
                SET customer_latitude = ?, customer_longitude = ? 
                WHERE id = ?
            ", [$coordinates['lat'], $coordinates['lng'], $order['id']]);
            
            $updated++;
        }
    }
    
    $success = "$updated sipariş koordinatları adresten bulunarak güncellendi!";
    
    // Listeyi yenile
    $orders = $db->query("
        SELECT id, order_number, customer_name, customer_address, 
               customer_latitude, customer_longitude, created_at
        FROM siparisler 
        WHERE customer_latitude IS NULL OR customer_longitude IS NULL
        ORDER BY created_at DESC
    ")->fetchAll();
}

// Test adres butonu için
if (isset($_POST['test_address'])) {
    $testAddress = 'Süleyman Demirel Mahallesi Şöförler Sitesi 1/B Blok';
    $success = "Test adresi: $testAddress<br><br>";
    
    $result = findCoordinatesFromAddress($testAddress);
    
    if ($result) {
        $success .= "✅ <strong>Koordinat bulundu!</strong><br>";
        $success .= "Lat: {$result['lat']}<br>";
        $success .= "Lng: {$result['lng']}<br>";
        $success .= '<a href="https://maps.google.com/maps?q=' . $result['lat'] . ',' . $result['lng'] . '" target="_blank" class="btn btn-sm btn-primary mt-2">
                        <i class="fas fa-external-link-alt me-1"></i>Google Maps\'te Aç
                     </a>';
    } else {
        $error = "❌ Test adresi bulunamadı: $testAddress<br><br>";
        $error .= "Denenen stratejiler:<br>";
        $error .= "1. Orijinal adres<br>";
        $error .= "2. Adres + ', Türkiye'<br>";
        $error .= "3. Sadece mahalle adı<br>";
        $error .= "4. Şehir/ilçe çıkarımı<br><br>";
        $error .= "Bu durumda manuel koordinat girişi gerekiyor.";
    }
}

// Gelişmiş geocoding fonksiyonu - çoklu strateji
function findCoordinatesFromAddress($address) {
    $strategies = [
        $address,                                    // Orijinal adres
        $address . ', Türkiye',                    // Türkiye eklendi
        extractNeighborhood($address) . ', Türkiye', // Sadece mahalle
        extractCityFromAddress($address)            // İl/ilçe
    ];
    
    foreach ($strategies as $searchAddress) {
        if (empty($searchAddress)) continue;
        
        // Nominatim dene
        $result = tryNominatimGeocoding($searchAddress);
        if ($result) {
            error_log("Adres bulundu: $searchAddress -> {$result['lat']}, {$result['lng']}");
            return $result;
        }
        
        // Photon API dene
        $result = tryPhotonGeocoding($searchAddress);
        if ($result) {
            error_log("Adres bulundu (Photon): $searchAddress -> {$result['lat']}, {$result['lng']}");
            return $result;
        }
        
        // Kısa bir bekleme (rate limiting)
        usleep(200000); // 0.2 saniye
    }
    
    error_log("Adres bulunamadı: $address");
    return null;
}

// Nominatim geocoding
function tryNominatimGeocoding($address) {
    try {
        $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode($address) . '&limit=1&countrycodes=tr';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Kurye System/1.0'
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            
            if (!empty($data) && isset($data[0]['lat'], $data[0]['lon'])) {
                return [
                    'lat' => (float)$data[0]['lat'],
                    'lng' => (float)$data[0]['lon']
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Nominatim hatası: " . $e->getMessage());
    }
    
    return null;
}

// Photon geocoding
function tryPhotonGeocoding($address) {
    try {
        $url = 'https://photon.komoot.io/api/?q=' . urlencode($address) . '&limit=1&lang=tr';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Kurye System/1.0'
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            
            if (!empty($data['features']) && isset($data['features'][0]['geometry']['coordinates'])) {
                $coords = $data['features'][0]['geometry']['coordinates'];
                return [
                    'lat' => (float)$coords[1],
                    'lng' => (float)$coords[0]
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Photon hatası: " . $e->getMessage());
    }
    
    return null;
}

// Mahalle çıkarma
function extractNeighborhood($address) {
    if (preg_match('/([^,]+)\s*mahalle/i', $address, $matches)) {
        return trim($matches[1]) . ' mahallesi';
    }
    return null;
}

// Şehir/ilçe çıkarma
function extractCityFromAddress($address) {
    $cities = ['istanbul', 'ankara', 'izmir', 'bursa', 'antalya', 'adana', 'konya', 'gaziantep', 'mersin', 'diyarbakır', 'kayseri', 'eskişehir'];
    $addressLower = mb_strtolower($address, 'UTF-8');
    
    foreach ($cities as $city) {
        if (strpos($addressLower, $city) !== false) {
            return $city . ', türkiye';
        }
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koordinat Güncelleme - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-map-marker-alt me-2"></i>Sipariş Koordinatları Güncelleme</h2>
                    <a href="siparisler.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Geri Dön
                    </a>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?= $success ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>Koordinatı Olmayan Siparişler (<?= count($orders) ?>)</h5>
                            <?php if (!empty($orders)): ?>
                                <form method="POST" style="display: inline;">
                                    <button type="submit" name="update_all" class="btn btn-warning btn-sm" 
                                            onclick="return confirm('Tüm siparişlere adresten koordinat bulansın mı?')">
                                        <i class="fas fa-search-location me-1"></i>Hepsini Güncelle
                                    </button>
                                    <button type="submit" name="test_address" class="btn btn-info btn-sm ms-2">
                                        <i class="fas fa-vial me-1"></i>Test Adres
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="alert alert-success text-center">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <h5>Tüm siparişlerin koordinatları mevcut!</h5>
                                <p>Artık tüm siparişlerde harita görünecek.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Sipariş</th>
                                            <th>Müşteri</th>
                                            <th>Adres</th>
                                            <th>Tarih</th>
                                            <th>Koordinat Güncelle</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= $order['order_number'] ?></strong>
                                                    <br><small>ID: <?= $order['id'] ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                                <td class="small"><?= htmlspecialchars($order['customer_address']) ?></td>
                                                <td><?= formatDate($order['created_at']) ?></td>
                                                <td>
                                                    <form method="POST" class="d-flex gap-2">
                                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                        <input type="number" step="0.000001" name="latitude" 
                                                               placeholder="Enlem" class="form-control form-control-sm" 
                                                               value="<?= $order['customer_latitude'] ?>" required>
                                                        <input type="number" step="0.000001" name="longitude" 
                                                               placeholder="Boylam" class="form-control form-control-sm" 
                                                               value="<?= $order['customer_longitude'] ?>" required>
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-save"></i>
                                                        </button>
                                                    </form>
                                                    <small class="text-muted">
                                                        Örnek: 41.0082, 28.9784 (İstanbul)
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Bilgi</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Koordinat alma yöntemleri:</strong></p>
                        <ul>
                            <li><strong>Google Maps:</strong> Haritada sağ tık → "Bu konumun koordinatları" → Kopyala</li>
                            <li><strong>Hızlı güncelleme:</strong> "Hepsini Güncelle" ile tüm siparişlere rastgele İstanbul koordinatları atanır</li>
                            <li><strong>Manual:</strong> Her sipariş için ayrı ayrı gerçek koordinat girebilirsiniz</li>
                        </ul>
                        <div class="alert alert-info">
                            <strong>Not:</strong> Yeni siparişlerde koordinat otomatik alınır. Bu araç sadece eski siparişler için.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
