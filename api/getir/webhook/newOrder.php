<?php
/**
 * GetirYemek API - Yeni Sipariş Webhook
 * GetirYemek'ten gelen yeni siparişleri işler
 */

require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-api-key');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Sadece POST metodunu kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Sadece POST metodu desteklenir'
        ]
    ], 405);
}

try {
    // API Key kontrolü
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($api_key) || $api_key !== GETIR_API_KEY) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Geçersiz API anahtarı'
            ]
        ], 401);
    }
    
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Geçersiz JSON formatı');
    }
    
    // Gerekli alanları kontrol et
    if (empty($input['id']) || empty($input['client']) || empty($input['restaurant'])) {
        throw new Exception('Gerekli alanlar eksik');
    }
    
    $db = getDB();
    
    // GetirYemek siparişini işle
    $getir_order_id = $input['id'];
    $status = $input['status'] ?? 400; // 400 = yeni sipariş
    $is_scheduled = $input['isScheduled'] ?? false;
    $confirmation_id = $input['confirmationId'] ?? '';
    
    // Müşteri bilgileri
    $client = $input['client'];
    $client_name = $client['name'] ?? '';
    $client_phone = $client['contactPhoneNumber'] ?? '';
    $client_address = $client['deliveryAddress']['address'] ?? '';
    $client_city = $client['deliveryAddress']['city'] ?? '';
    $client_district = $client['deliveryAddress']['district'] ?? '';
    $client_apt_no = $client['deliveryAddress']['aptNo'] ?? '';
    $client_floor = $client['deliveryAddress']['floor'] ?? '';
    $client_door_no = $client['deliveryAddress']['doorNo'] ?? '';
    $client_description = $client['deliveryAddress']['description'] ?? '';
    $client_lat = $client['location']['lat'] ?? 0;
    $client_lon = $client['location']['lon'] ?? 0;
    
    // Restoran bilgileri
    $restaurant = $input['restaurant'];
    $restaurant_id = $restaurant['id'] ?? '';
    $restaurant_name = $restaurant['name'] ?? '';
    
    // Ürün bilgileri
    $products = $input['products'] ?? [];
    $total_price = $input['totalPrice'] ?? 0;
    $total_discounted_price = $input['totalDiscountedPrice'] ?? 0;
    
    // Ödeme bilgileri
    $payment_method = $input['paymentMethod'] ?? 0;
    $payment_method_text = $input['paymentMethodText']['tr'] ?? '';
    
    // Teslimat bilgileri
    $delivery_type = $input['deliveryType'] ?? 1; // 1 = Getir Getirsin, 2 = Restoran Getirsin
    $is_eco_friendly = $input['isEcoFriendly'] ?? false;
    $do_not_knock = $input['doNotKnock'] ?? false;
    $drop_off_at_door = $input['dropOffAtDoor'] ?? false;
    $client_note = $input['clientNote'] ?? '';
    
    // Sipariş numarası oluştur
    $order_number = 'GETIR' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Restoran ID'sini bul (GetirYemek restaurant ID'sine göre)
    $stmt = $db->query("SELECT id FROM mekanlar WHERE getir_restaurant_id = ?", [$restaurant_id]);
    $mekan = $stmt->fetch();
    
    if (!$mekan) {
        throw new Exception("Restoran bulunamadı: $restaurant_id");
    }
    
    $mekan_id = $mekan['id'];
    
    // Siparişi veritabanına kaydet
    $stmt = $db->query("
        INSERT INTO siparisler (
            order_number, mekan_id, customer_name, customer_phone, customer_address,
            customer_city, customer_district, customer_apt_no, customer_floor, customer_door_no,
            customer_description, customer_lat, customer_lon, total_price, discounted_price,
            payment_method, payment_method_text, delivery_type, is_eco_friendly,
            do_not_knock, drop_off_at_door, client_note, status, source,
            getir_order_id, getir_confirmation_id, getir_status, is_scheduled,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ", [
        $order_number, $mekan_id, $client_name, $client_phone, $client_address,
        $client_city, $client_district, $client_apt_no, $client_floor, $client_door_no,
        $client_description, $client_lat, $client_lon, $total_price, $total_discounted_price,
        $payment_method, $payment_method_text, $delivery_type, $is_eco_friendly,
        $do_not_knock, $drop_off_at_door, $client_note, 'pending', 'getir',
        $getir_order_id, $confirmation_id, $status, $is_scheduled
    ]);
    
    $siparis_id = $db->lastInsertId();
    
    // Ürünleri kaydet
    foreach ($products as $product) {
        $product_name = $product['name']['tr'] ?? $product['product'] ?? '';
        $product_count = $product['count'] ?? 1;
        $product_price = $product['totalPriceWithOption'] ?? $product['price'] ?? 0;
        $product_note = $product['note'] ?? '';
        
        $db->query("
            INSERT INTO siparis_urunleri (
                siparis_id, urun_adi, miktar, fiyat, notlar, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ", [$siparis_id, $product_name, $product_count, $product_price, $product_note]);
    }
    
    // Log kaydet
    writeLog("GetirYemek yeni sipariş alındı: $order_number (Getir ID: $getir_order_id)", 'INFO', 'getir.log');
    
    // Başarılı yanıt
    jsonResponse([
        'success' => true,
        'message' => 'Sipariş başarıyla alındı',
        'data' => [
            'order_id' => $siparis_id,
            'order_number' => $order_number,
            'getir_order_id' => $getir_order_id
        ]
    ]);
    
} catch (Exception $e) {
    writeLog("GetirYemek yeni sipariş hatası: " . $e->getMessage(), 'ERROR', 'getir.log');
    
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Sipariş işlenirken hata oluştu: ' . $e->getMessage()
        ]
    ], 500);
}
?>

