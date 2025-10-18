<?php
/**
 * Geocoding Proxy - Koordinatlardan adres bilgisi al
 */

require_once '../../config/config.php';

// Admin kontrolü
if (!isLoggedIn() || getUserType() !== 'admin') {
    http_response_code(401);
    exit('Unauthorized');
}

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $lat = $_GET['lat'] ?? '';
    $lng = $_GET['lng'] ?? '';
    
    if (empty($lat) || empty($lng)) {
        throw new Exception('Latitude ve longitude parametreleri gereklidir');
    }
    
    // Koordinat validasyonu
    if (!is_numeric($lat) || !is_numeric($lng)) {
        throw new Exception('Geçersiz koordinat formatı');
    }
    
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        throw new Exception('Koordinat aralığı dışında');
    }
    
    // Nominatim API URL'si
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
    
    // User-Agent header ekle (Nominatim gereksinimi)
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Kurye Full System/1.0 (https://example.com/contact)',
                'Accept: application/json'
            ],
            'timeout' => 10
        ]
    ]);
    
    // API çağrısı yap
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Geocoding API çağrısı başarısız');
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('API yanıtı geçersiz JSON formatında');
    }
    
    // Adres bilgisini işle
    $result = [
        'success' => true,
        'address' => null,
        'display_name' => null,
        'formatted_address' => null
    ];
    
    if ($data && isset($data['display_name'])) {
        $result['display_name'] = $data['display_name'];
        
        // Türkiye için kısa adres formatı oluştur
        if (isset($data['address'])) {
            $address_parts = [];
            
            // Sokak/cadde
            if (!empty($data['address']['road'])) {
                $address_parts[] = $data['address']['road'];
            }
            
            // Mahalle/semt
            if (!empty($data['address']['suburb'])) {
                $address_parts[] = $data['address']['suburb'];
            } elseif (!empty($data['address']['neighbourhood'])) {
                $address_parts[] = $data['address']['neighbourhood'];
            }
            
            // İlçe/şehir
            if (!empty($data['address']['city'])) {
                $address_parts[] = $data['address']['city'];
            } elseif (!empty($data['address']['town'])) {
                $address_parts[] = $data['address']['town'];
            } elseif (!empty($data['address']['county'])) {
                $address_parts[] = $data['address']['county'];
            }
            
            // İl
            if (!empty($data['address']['state'])) {
                $address_parts[] = $data['address']['state'];
            }
            
            $result['formatted_address'] = implode(', ', array_filter($address_parts));
        }
        
        $result['address'] = $data['address'] ?? null;
    }
    
    // Cache başlığı ekle (1 saat)
    header('Cache-Control: public, max-age=3600');
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

