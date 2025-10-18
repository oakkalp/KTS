<?php
require_once '../config/config.php';

// Admin kontrolü
if (!isLoggedIn() || getUserType() !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();

// Filtreleme parametreleri
$kurye_id = $_GET['kurye_id'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$limit = $_GET['limit'] ?? 100;

// Kurye listesi - full_name sütunu yoksa users tablosundan al
try {
    $kuryeler = $db->query("
        SELECT k.id, k.full_name, u.username 
        FROM kuryeler k 
        JOIN users u ON k.user_id = u.id 
        ORDER BY k.full_name
    ")->fetchAll();
} catch (Exception $e) {
    // full_name sütunu yoksa alternatif sorgu
    try {
        $kuryeler = $db->query("
            SELECT k.id, CONCAT(u.first_name, ' ', u.last_name) as full_name, u.username 
            FROM kuryeler k 
            JOIN users u ON k.user_id = u.id 
            ORDER BY u.first_name, u.last_name
        ")->fetchAll();
    } catch (Exception $e2) {
        // first_name/last_name da yoksa sadece username
        $kuryeler = $db->query("
            SELECT k.id, u.username as full_name, u.username 
            FROM kuryeler k 
            JOIN users u ON k.user_id = u.id 
            ORDER BY u.username
        ")->fetchAll();
    }
}

// Konum geçmişi sorgusu
$whereClause = "WHERE DATE(kg.created_at) BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if ($kurye_id) {
    $whereClause .= " AND kg.kurye_id = ?";
    $params[] = $kurye_id;
}

// Konum geçmişi sorgusu - full_name ve sipariş sütunu kontrolü ile
try {
    $konum_gecmisi = $db->query("
        SELECT 
            kg.*,
            k.full_name as kurye_adi,
            u.username,
            s.id as siparis_no,
            s.mekan_adi,
            s.musteri_adi,
            s.teslimat_adresi
        FROM kurye_konum_gecmisi kg
        JOIN kuryeler k ON kg.kurye_id = k.id
        JOIN users u ON k.user_id = u.id
        LEFT JOIN siparisler s ON kg.siparis_id = s.id
        {$whereClause}
        ORDER BY kg.created_at DESC
        LIMIT ?
    ", array_merge($params, [(int)$limit]))->fetchAll();
} catch (Exception $e) {
    // Sipariş sütunları veya full_name yoksa basit sorgu
    try {
        // Sipariş bilgileri olmadan, sadece konum ve kurye bilgisi
        $konum_gecmisi = $db->query("
            SELECT 
                kg.*,
                k.full_name as kurye_adi,
                u.username,
                NULL as siparis_no,
                NULL as mekan_adi,
                NULL as musteri_adi,
                NULL as teslimat_adresi
            FROM kurye_konum_gecmisi kg
            JOIN kuryeler k ON kg.kurye_id = k.id
            JOIN users u ON k.user_id = u.id
            {$whereClause}
            ORDER BY kg.created_at DESC
            LIMIT ?
        ", array_merge($params, [(int)$limit]))->fetchAll();
    } catch (Exception $e2) {
        // full_name da yoksa username kullan
        try {
            $konum_gecmisi = $db->query("
                SELECT 
                    kg.*,
                    CONCAT(u.first_name, ' ', u.last_name) as kurye_adi,
                    u.username,
                    NULL as siparis_no,
                    NULL as mekan_adi,
                    NULL as musteri_adi,
                    NULL as teslimat_adresi
                FROM kurye_konum_gecmisi kg
                JOIN kuryeler k ON kg.kurye_id = k.id
                JOIN users u ON k.user_id = u.id
                {$whereClause}
                ORDER BY kg.created_at DESC
                LIMIT ?
            ", array_merge($params, [(int)$limit]))->fetchAll();
        } catch (Exception $e3) {
            // En basit sorgu - sadece username
            $konum_gecmisi = $db->query("
                SELECT 
                    kg.*,
                    u.username as kurye_adi,
                    u.username,
                    NULL as siparis_no,
                    NULL as mekan_adi,
                    NULL as musteri_adi,
                    NULL as teslimat_adresi
                FROM kurye_konum_gecmisi kg
                JOIN kuryeler k ON kg.kurye_id = k.id
                JOIN users u ON k.user_id = u.id
                {$whereClause}
                ORDER BY kg.created_at DESC
                LIMIT ?
            ", array_merge($params, [(int)$limit]))->fetchAll();
        }
    }
}

// İstatistikler
$stats = $db->query("
    SELECT 
        COUNT(*) as toplam_kayit,
        COUNT(DISTINCT kg.kurye_id) as aktif_kurye,
        COUNT(DISTINCT DATE(kg.created_at)) as gun_sayisi
    FROM kurye_konum_gecmisi kg
    {$whereClause}
", $params)->fetch();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konum Geçmişi - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" rel="stylesheet">
    <style>
        .location-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
        }
        .location-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .accuracy-badge {
            font-size: 0.75rem;
        }
        .time-badge {
            font-size: 0.8rem;
        }
        #map {
            height: 400px;
            border-radius: 8px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Admin Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-motorcycle me-2"></i><?= SITE_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
                <a class="nav-link" href="map-tracking.php"><i class="fas fa-map me-1"></i>Canlı Takip</a>
                <a class="nav-link active" href="konum-gecmisi.php"><i class="fas fa-map-marked-alt me-1"></i>Konum Geçmişi</a>
                <a class="nav-link" href="kuryeler.php"><i class="fas fa-motorcycle me-1"></i>Kuryeler</a>
                <a class="nav-link" href="raporlar.php"><i class="fas fa-chart-bar me-1"></i>Raporlar</a>
                <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-1"></i>Çıkış</a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-map-marked-alt text-primary me-2"></i>
                        Konum Geçmişi
                    </h1>
                    <button class="btn btn-primary" onclick="showMapView()">
                        <i class="fas fa-map me-2"></i>
                        Harita Görünümü
                    </button>
                </div>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-map-pin fa-2x mb-2"></i>
                        <h4><?= number_format($stats['toplam_kayit']) ?></h4>
                        <p class="mb-0">Toplam Konum Kaydı</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4><?= $stats['aktif_kurye'] ?></h4>
                        <p class="mb-0">Aktif Kurye</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar fa-2x mb-2"></i>
                        <h4><?= $stats['gun_sayisi'] ?></h4>
                        <p class="mb-0">Gün Sayısı</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtreler -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>
                    Filtreler
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Kurye</label>
                        <select name="kurye_id" class="form-select">
                            <option value="">Tüm Kuryeler</option>
                            <?php foreach ($kuryeler as $kurye): ?>
                                <option value="<?= $kurye['id'] ?>" <?= $kurye_id == $kurye['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kurye['full_name']) ?> (@<?= $kurye['username'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Limit</label>
                        <select name="limit" class="form-select">
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                            <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                            <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Konum Geçmişi -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Konum Kayıtları (<?= count($konum_gecmisi) ?> kayıt)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($konum_gecmisi)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-map-marked-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Konum kaydı bulunamadı</h5>
                        <p class="text-muted">Seçilen kriterlere uygun konum geçmişi bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-user me-1"></i>Kurye</th>
                                    <th><i class="fas fa-clock me-1"></i>Tarih/Saat</th>
                                    <th><i class="fas fa-map-marker-alt me-1"></i>Konum</th>
                                    <th><i class="fas fa-road me-1"></i>Adres</th>
                                    <th><i class="fas fa-crosshairs me-1"></i>Doğruluk</th>
                                    <th><i class="fas fa-shopping-bag me-1"></i>Sipariş</th>
                                    <th><i class="fas fa-cog me-1"></i>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($konum_gecmisi as $konum): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($konum['kurye_adi']) ?></strong>
                                                <br><small class="text-muted">@<?= $konum['username'] ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= date('d.m.Y', strtotime($konum['created_at'])) ?></strong>
                                                <br><small><?= date('H:i:s', strtotime($konum['created_at'])) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <small>
                                                <strong>Enlem:</strong> <?= number_format($konum['latitude'], 6) ?><br>
                                                <strong>Boylam:</strong> <?= number_format($konum['longitude'], 6) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div id="address_<?= $konum['id'] ?>" class="text-muted">
                                                <small>
                                                    <i class="fas fa-spinner fa-spin me-1"></i>
                                                    Adres alınıyor...
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($konum['accuracy']): ?>
                                                <span class="badge bg-info">
                                                    <?= round($konum['accuracy']) ?>m
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($konum['siparis_no']): ?>
                                                <div>
                                                    <strong>#<?= $konum['siparis_no'] ?></strong>
                                                    <?php if ($konum['mekan_adi']): ?>
                                                        <br><small><i class="fas fa-store me-1"></i><?= htmlspecialchars($konum['mekan_adi']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="showLocationOnMap(<?= $konum['latitude'] ?>, <?= $konum['longitude'] ?>, '<?= addslashes($konum['kurye_adi']) ?>')">
                                                <i class="fas fa-map me-1"></i>
                                                Harita
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Harita Modal -->
    <div class="modal fade" id="mapModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-map me-2"></i>
                        Konum Haritası
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="map"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        let map;
        let mapModal;

        document.addEventListener('DOMContentLoaded', function() {
            mapModal = new bootstrap.Modal(document.getElementById('mapModal'));
        });

        function showMapView() {
            mapModal.show();
            
            setTimeout(() => {
                if (!map) {
                    initMap();
                }
                loadAllLocations();
            }, 300);
        }

        function initMap() {
            map = L.map('map').setView([38.2522, 27.9970], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
        }

        function showLocationOnMap(lat, lng, kuryeAdi) {
            mapModal.show();
            
            setTimeout(() => {
                if (!map) {
                    initMap();
                }
                
                map.setView([lat, lng], 16);
                
                // Önceki markerları temizle
                map.eachLayer(function (layer) {
                    if (layer instanceof L.Marker) {
                        map.removeLayer(layer);
                    }
                });
                
                // Yeni marker ekle
                L.marker([lat, lng])
                    .addTo(map)
                    .bindPopup(`
                        <strong>${kuryeAdi}</strong><br>
                        Enlem: ${lat.toFixed(6)}<br>
                        Boylam: ${lng.toFixed(6)}
                    `)
                    .openPopup();
            }, 300);
        }

        function loadAllLocations() {
            // Tüm konumları haritaya ekle
            <?php if (!empty($konum_gecmisi)): ?>
                const locations = [
                    <?php foreach ($konum_gecmisi as $konum): ?>
                        {
                            lat: <?= $konum['latitude'] ?>,
                            lng: <?= $konum['longitude'] ?>,
                            kurye: '<?= addslashes($konum['kurye_adi']) ?>',
                            time: '<?= date('H:i:s', strtotime($konum['created_at'])) ?>',
                            siparis: '<?= $konum['siparis_no'] ? '#' . $konum['siparis_no'] : 'Sipariş yok' ?>'
                        },
                    <?php endforeach; ?>
                ];

                locations.forEach(function(location, index) {
                    const color = index < 5 ? 'red' : (index < 10 ? 'orange' : 'blue');
                    
                    L.circleMarker([location.lat, location.lng], {
                        radius: 6,
                        fillColor: color,
                        color: color,
                        weight: 2,
                        opacity: 0.8,
                        fillOpacity: 0.6
                    })
                    .addTo(map)
                    .bindPopup(`
                        <strong>${location.kurye}</strong><br>
                        Saat: ${location.time}<br>
                        ${location.siparis}<br>
                        <small>Enlem: ${location.lat.toFixed(6)}<br>
                        Boylam: ${location.lng.toFixed(6)}</small>
                    `);
                });
            <?php endif; ?>
        }
        
        // Koordinatlardan adres bilgisi al (PHP Proxy üzerinden)
        function getAddressFromCoordinates(lat, lng, elementId) {
            // Kendi PHP proxy'mizi kullan
            const url = `ajax/get-address.php?lat=${lat}&lng=${lng}`;
            
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const element = document.getElementById(elementId);
                    if (data.success && data.formatted_address) {
                        element.innerHTML = `<small><i class="fas fa-map-marker-alt text-success me-1"></i>${data.formatted_address}</small>`;
                    } else if (data.success && data.display_name) {
                        // Formatted address yoksa display_name kullan
                        element.innerHTML = `<small><i class="fas fa-map-marker-alt text-warning me-1"></i>${data.display_name}</small>`;
                    } else {
                        element.innerHTML = '<small class="text-muted"><i class="fas fa-exclamation-circle me-1"></i>Adres bulunamadı</small>';
                    }
                })
                .catch(error => {
                    console.error('Adres alma hatası:', error);
                    document.getElementById(elementId).innerHTML = '<small class="text-muted"><i class="fas fa-times-circle me-1"></i>Adres alınamadı</small>';
                });
        }
        
        // Sayfa yüklendiğinde tüm koordinatlar için adres al
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($konum_gecmisi as $konum): ?>
                setTimeout(() => {
                    getAddressFromCoordinates(<?= $konum['latitude'] ?>, <?= $konum['longitude'] ?>, 'address_<?= $konum['id'] ?>');
                }, <?= array_search($konum, $konum_gecmisi) * 100 ?>); // 100ms aralıklarla API çağrısı
            <?php endforeach; ?>
        });
    </script>
</body>
</html>
