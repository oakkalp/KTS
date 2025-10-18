<?php
/**
 * Kurye Full System - Admin Kurye Takip Haritası
 */

require_once '../config/config.php';
requireUserType('admin');

// Aktif kuryeler ve konumları
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT k.*, u.full_name, u.phone, u.last_login,
               COUNT(s.id) as active_orders
        FROM kuryeler k 
        JOIN users u ON k.user_id = u.id 
        LEFT JOIN siparisler s ON k.id = s.kurye_id AND s.status IN ('accepted', 'preparing', 'ready', 'picked_up', 'delivering')
        WHERE u.status = 'active'
        GROUP BY k.id
        ORDER BY k.is_online DESC, k.last_location_update DESC
    ");
    $couriers = $stmt->fetchAll();
    
    // Online kurye sayısı
    $online_count = array_sum(array_column($couriers, 'is_online'));
    
} catch (Exception $e) {
    writeLog("Map tracking error: " . $e->getMessage(), 'ERROR');
    $couriers = [];
    $online_count = 0;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurye Takip Haritası - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        #map {
            height: 70vh;
            width: 100%;
            border-radius: 10px;
        }
        .courier-card {
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .courier-card.online {
            border-left-color: #28a745;
        }
        .courier-card.offline {
            border-left-color: #dc3545;
            opacity: 0.7;
        }
        .courier-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-indicator {
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
                        <i class="fas fa-map-marked-alt me-2 text-primary"></i>
                        Kurye Takip Haritası
                    </h1>
                    <div class="btn-toolbar">
                        <div class="btn-group me-2">
                            <button class="btn btn-outline-success btn-sm">
                                <i class="fas fa-circle me-1"></i>
                                Online: <?= $online_count ?>
                            </button>
                            <button class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-circle me-1"></i>
                                Offline: <?= count($couriers) - $online_count ?>
                            </button>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="refreshMap()">
                            <i class="fas fa-sync-alt me-1"></i>
                            Yenile
                        </button>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Harita -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-map me-2"></i>
                                    Kurye Konumları
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="map"></div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Harita her 30 saniyede otomatik güncellenir. Kurye simgelerine tıklayarak detay bilgileri görebilirsiniz.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kurye Listesi -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-users me-2"></i>
                                    Kuryeler (<?= count($couriers) ?>)
                                </h5>
                            </div>
                            <div class="card-body" style="max-height: 70vh; overflow-y: auto;">
                                <?php if (empty($couriers)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-motorcycle fa-3x mb-3"></i>
                                        <p>Henüz kurye bulunmuyor</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($couriers as $courier): ?>
                                        <div class="card courier-card <?= $courier['is_online'] ? 'online' : 'offline' ?> mb-3" 
                                             onclick="focusCourier(<?= $courier['id'] ?>)">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="status-indicator <?= $courier['is_online'] ? 'status-online' : 'status-offline' ?>"></span>
                                                    <strong><?= sanitize($courier['full_name']) ?></strong>
                                                    <?php if ($courier['active_orders'] > 0): ?>
                                                        <span class="badge bg-warning ms-auto"><?= $courier['active_orders'] ?> Aktif</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="small text-muted">
                                                    <div><i class="fas fa-phone me-1"></i><?= formatPhone($courier['phone']) ?></div>
                                                    <?php if ($courier['license_plate']): ?>
                                                        <div><i class="fas fa-car me-1"></i><?= sanitize($courier['license_plate']) ?></div>
                                                    <?php endif; ?>
                                                    <div><i class="fas fa-motorcycle me-1"></i><?= ucfirst($courier['vehicle_type']) ?></div>
                                                    
                                                    <?php if ($courier['is_online']): ?>
                                                        <?php if ($courier['current_latitude'] && $courier['current_longitude']): ?>
                                                            <div class="text-success">
                                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                                Konum: <?= number_format($courier['current_latitude'], 4) ?>, <?= number_format($courier['current_longitude'], 4) ?>
                                                            </div>
                                                            <?php if ($courier['last_location_update']): ?>
                                                                <div><i class="fas fa-clock me-1"></i><?= formatDate($courier['last_location_update']) ?></div>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <div class="text-warning">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                                Konum bilgisi yok
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="text-danger">
                                                            <i class="fas fa-power-off me-1"></i>
                                                            Çevrimdışı
                                                        </div>
                                                        <?php if ($courier['last_login']): ?>
                                                            <div><i class="fas fa-history me-1"></i>Son: <?= formatDate($courier['last_login']) ?></div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let map;
        let markers = {};
        let courierData = <?= json_encode($couriers) ?>;
        
        // Harita başlat
        function initMap() {
            // İstanbul merkez koordinatları
            const center = { lat: 41.0082, lng: 28.9784 };
            
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 12,
                center: center,
                mapTypeControl: true,
                streetViewControl: false,
                fullscreenControl: true,
                styles: [
                    {
                        featureType: "poi",
                        elementType: "labels",
                        stylers: [{ visibility: "off" }]
                    }
                ]
            });
            
            // Kurye marker'larını ekle
            updateCourierMarkers();
        }
        
        function updateCourierMarkers() {
            // Mevcut marker'ları temizle
            Object.values(markers).forEach(marker => marker.setMap(null));
            markers = {};
            
            courierData.forEach(courier => {
                if (courier.current_latitude && courier.current_longitude) {
                    const position = {
                        lat: parseFloat(courier.current_latitude),
                        lng: parseFloat(courier.current_longitude)
                    };
                    
                    // Araç tipine ve durumuna göre ikon seç
                    function getVehicleIcon(vehicleType, isOnline) {
                        const color = isOnline == 1 ? '#28a745' : '#dc3545'; // Yeşil/Kırmızı
                        let vehicleIcon = '';
                        
                        switch(vehicleType) {
                            case 'motosiklet':
                                vehicleIcon = `<path d="M5,11c-2.21,0-4,1.79-4,4s1.79,4,4,4s4-1.79,4-4S7.21,11,5,11z M5,17c-1.1,0-2-0.9-2-2s0.9-2,2-2s2,0.9,2,2 S6.1,17,5,17z M19,11c-2.21,0-4,1.79-4,4s1.79,4,4,4s4-1.79,4-4S21.21,11,19,11z M19,17c-1.1,0-2-0.9-2-2s0.9-2,2-2s2,0.9,2,2 S20.1,17,19,17z M8.5,12h4.5c0.83,0,1.5-0.67,1.5-1.5v-1c0-0.83-0.67-1.5-1.5-1.5H9L7,6h4c0.55,0,1,0.45,1,1v1h2V7 c0-1.65-1.35-3-3-3H7C6.45,4,6,4.45,6,5s0.45,1,1,1h1.5l1,2H8.5C7.67,8,7,8.67,7,9.5S7.67,11,8.5,11z"/>`;
                                break;
                            case 'araba':
                                vehicleIcon = `<path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.22.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>`;
                                break;
                            case 'bisiklet':
                                vehicleIcon = `<path d="M15.5 5.5c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM5 12c-2.8 0-5 2.2-5 5s2.2 5 5 5 5-2.2 5-5-2.2-5-5-5zm0 8.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5zm14.8-9.1c-.4-.7-1.1-1.1-1.9-1.1H16l-1.1-2.1c-.4-.8-1.3-1.3-2.2-1.3H9.5c-.8 0-1.5.7-1.5 1.5S8.7 9 9.5 9h2.4l.8 1.5L9.5 15c-.8 0-1.5.7-1.5 1.5s.7 1.5 1.5 1.5c.4 0 .8-.2 1.1-.5l3.1-3.1c.4-.4.4-1 0-1.4L12 11.2l1.4-2.6h3.4l1.3 2.4c.3.6 1 .9 1.6.6.6-.3.9-1 .6-1.6zm-4.3 4.6c-2.8 0-5 2.2-5 5s2.2 5 5 5 5-2.2 5-5-2.2-5-5-5zm0 8.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/>`;
                                break;
                            default:
                                vehicleIcon = `<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>`;
                        }
                        
                        const svgIcon = `
                            <svg width="35" height="35" viewBox="0 0 35 35" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="17.5" cy="17.5" r="17.5" fill="${color}"/>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="white" x="7.5" y="7.5">
                                    ${vehicleIcon}
                                </svg>
                            </svg>
                        `;
                        
                        return 'data:image/svg+xml;base64,' + btoa(svgIcon);
                    }
                    
                    const icon = {
                        url: getVehicleIcon(courier.vehicle_type, courier.is_online),
                        scaledSize: new google.maps.Size(35, 35),
                        anchor: new google.maps.Point(17.5, 17.5)
                    };
                    
                    const marker = new google.maps.Marker({
                        position: position,
                        map: map,
                        title: courier.full_name,
                        icon: icon,
                        animation: courier.is_online == 1 ? google.maps.Animation.BOUNCE : null
                    });
                    
                    // Info window
                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div style="padding: 10px;">
                                <h6><i class="fas fa-motorcycle"></i> ${courier.full_name}</h6>
                                <p class="mb-1"><i class="fas fa-phone"></i> ${courier.phone || 'Telefon yok'}</p>
                                <p class="mb-1"><i class="fas fa-car"></i> ${courier.license_plate || 'Plaka yok'}</p>
                                <p class="mb-1"><i class="fas fa-motorcycle"></i> ${courier.vehicle_type}</p>
                                <p class="mb-0">
                                    <span class="badge bg-${courier.is_online == 1 ? 'success' : 'danger'}">
                                        ${courier.is_online == 1 ? 'Online' : 'Offline'}
                                    </span>
                                    ${courier.active_orders > 0 ? `<span class="badge bg-warning ms-1">${courier.active_orders} Aktif Sipariş</span>` : ''}
                                </p>
                            </div>
                        `
                    });
                    
                    marker.addListener('click', () => {
                        infoWindow.open(map, marker);
                    });
                    
                    markers[courier.id] = marker;
                    
                    // Bounce animasyonunu 3 saniye sonra durdur
                    if (courier.is_online == 1) {
                        setTimeout(() => {
                            marker.setAnimation(null);
                        }, 3000);
                    }
                }
            });
        }
        
        function focusCourier(courierId) {
            const courier = courierData.find(c => c.id == courierId);
            if (courier && courier.current_latitude && courier.current_longitude) {
                const position = {
                    lat: parseFloat(courier.current_latitude),
                    lng: parseFloat(courier.current_longitude)
                };
                
                map.setCenter(position);
                map.setZoom(16);
                
                // Marker'ı bounce yap
                const marker = markers[courierId];
                if (marker) {
                    marker.setAnimation(google.maps.Animation.BOUNCE);
                    setTimeout(() => marker.setAnimation(null), 2000);
                }
            }
        }
        
        function refreshMap() {
            // AJAX ile kurye verilerini güncelle
            fetch('ajax/get_courier_locations.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        courierData = data.couriers;
                        updateCourierMarkers();
                        
                        // Sayaçları güncelle
                        const onlineCount = courierData.filter(c => c.is_online == 1).length;
                        const offlineCount = courierData.length - onlineCount;
                        
                        document.querySelector('.btn-outline-success').innerHTML = 
                            `<i class="fas fa-circle me-1"></i>Online: ${onlineCount}`;
                        document.querySelector('.btn-outline-danger').innerHTML = 
                            `<i class="fas fa-circle me-1"></i>Offline: ${offlineCount}`;
                    }
                })
                .catch(error => {
                    console.error('Kurye verileri güncellenemedi:', error);
                });
        }
        
        // Otomatik güncelleme (30 saniyede bir)
        setInterval(refreshMap, 30000);
    </script>
    
    <!-- Google Maps API -->
    <script async defer 
        src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&callback=initMap">
    </script>
    
    <!-- Geçici olarak Google Maps olmadan çalışması için -->
    <script>
        // Google Maps yüklenemezse alternatif göster
        window.addEventListener('load', function() {
            setTimeout(function() {
                if (typeof google === 'undefined') {
                    document.getElementById('map').innerHTML = `
                        <div class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                            <div class="text-center">
                                <i class="fas fa-map fa-3x text-muted mb-3"></i>
                                <h5>Google Maps API Key Gerekli</h5>
                                <p class="text-muted">Harita görüntülemek için Google Maps API key'i yapılandırın</p>
                                <small class="text-muted">
                                    Sistem Ayarları > Google Maps API Key
                                </small>
                            </div>
                        </div>
                    `;
                }
            }, 2000);
        });
    </script>
</body>
</html>
