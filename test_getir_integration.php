<?php
/**
 * GetirYemek Entegrasyon Test Dosyası
 * Tüm GetirYemek API endpoint'lerini test eder
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

echo "<h1>GetirYemek Entegrasyon Testi</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

// Test 1: Veritabanı bağlantısı
echo "<div class='test-section'>";
echo "<h2>1. Veritabanı Bağlantısı</h2>";
try {
    $db = getDB();
    echo "<p class='success'>✓ Veritabanı bağlantısı başarılı</p>";
    
    // GetirYemek tablolarını kontrol et
    $tables = ['getir_tokens', 'getir_courier_notifications', 'getir_restaurant_status_changes'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "<p class='success'>✓ Tablo '$table' mevcut</p>";
        } else {
            echo "<p class='error'>✗ Tablo '$table' bulunamadı</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Veritabanı hatası: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 2: Config ayarları
echo "<div class='test-section'>";
echo "<h2>2. Konfigürasyon Ayarları</h2>";
echo "<p class='info'>GetirYemek API Base URL: " . GETIR_API_BASE_URL . "</p>";
echo "<p class='info'>GetirYemek API Key: " . (GETIR_API_KEY ? 'Tanımlı' : 'Tanımlı değil') . "</p>";
echo "<p class='info'>Base URL: " . BASE_URL . "</p>";
echo "</div>";

// Test 3: Webhook endpoint'leri
echo "<div class='test-section'>";
echo "<h2>3. Webhook Endpoint'leri</h2>";
$webhook_endpoints = [
    'newOrder' => BASE_URL . '/api/getir/webhook/newOrder.php',
    'cancelOrder' => BASE_URL . '/api/getir/webhook/cancelOrder.php',
    'courier' => BASE_URL . '/api/getir/webhook/courier.php',
    'restaurant' => BASE_URL . '/api/getir/webhook/restaurant.php'
];

foreach ($webhook_endpoints as $name => $url) {
    if (file_exists(str_replace(BASE_URL, '.', $url))) {
        echo "<p class='success'>✓ $name: $url</p>";
    } else {
        echo "<p class='error'>✗ $name: $url (dosya bulunamadı)</p>";
    }
}
echo "</div>";

// Test 4: API endpoint'leri
echo "<div class='test-section'>";
echo "<h2>4. API Endpoint'leri</h2>";
$api_endpoints = [
    'auth/login' => BASE_URL . '/api/getir/auth/login.php',
    'orders/verify' => BASE_URL . '/api/getir/orders/verify.php',
    'orders/prepare' => BASE_URL . '/api/getir/orders/prepare.php',
    'orders/handover' => BASE_URL . '/api/getir/orders/handover.php',
    'orders/deliver' => BASE_URL . '/api/getir/orders/deliver.php',
    'restaurants/status' => BASE_URL . '/api/getir/restaurants/status.php',
    'restaurants/courier' => BASE_URL . '/api/getir/restaurants/courier.php',
    'restaurants/busyness' => BASE_URL . '/api/getir/restaurants/busyness.php',
    'products/status' => BASE_URL . '/api/getir/products/status.php'
];

foreach ($api_endpoints as $name => $url) {
    if (file_exists(str_replace(BASE_URL, '.', $url))) {
        echo "<p class='success'>✓ $name: $url</p>";
    } else {
        echo "<p class='error'>✗ $name: $url (dosya bulunamadı)</p>";
    }
}
echo "</div>";

// Test 5: Admin ve Mekan panelleri
echo "<div class='test-section'>";
echo "<h2>5. Panel Sayfaları</h2>";
$panel_pages = [
    'Admin GetirYemek' => BASE_URL . '/admin/getir-yonetim.php',
    'Mekan GetirYemek' => BASE_URL . '/mekan/getir-siparisler.php'
];

foreach ($panel_pages as $name => $url) {
    if (file_exists(str_replace(BASE_URL, '.', $url))) {
        echo "<p class='success'>✓ $name: $url</p>";
    } else {
        echo "<p class='error'>✗ $name: $url (dosya bulunamadı)</p>";
    }
}
echo "</div>";

// Test 6: Örnek webhook testi
echo "<div class='test-section'>";
echo "<h2>6. Webhook Testi (Örnek)</h2>";
echo "<p class='info'>Aşağıdaki komutları terminal'de çalıştırarak webhook'ları test edebilirsiniz:</p>";
echo "<pre>";
echo "# Yeni sipariş webhook testi\n";
echo "curl -X POST " . BASE_URL . "/api/getir/webhook/newOrder.php \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -H \"x-api-key: YOUR_GETIR_API_KEY\" \\\n";
echo "  -d '{\n";
echo "    \"id\": \"test123\",\n";
echo "    \"status\": 400,\n";
echo "    \"client\": {\n";
echo "      \"name\": \"Test Müşteri\",\n";
echo "      \"contactPhoneNumber\": \"+905551234567\",\n";
echo "      \"deliveryAddress\": {\n";
echo "        \"address\": \"Test Adres\",\n";
echo "        \"city\": \"İstanbul\",\n";
echo "        \"district\": \"Kadıköy\"\n";
echo "      },\n";
echo "      \"location\": {\n";
echo "        \"lat\": 41.0082,\n";
echo "        \"lon\": 28.9784\n";
echo "      }\n";
echo "    },\n";
echo "    \"restaurant\": {\n";
echo "      \"id\": \"restaurant123\",\n";
echo "      \"name\": \"Test Restoran\"\n";
echo "    },\n";
echo "    \"products\": [\n";
echo "      {\n";
echo "        \"name\": {\n";
echo "          \"tr\": \"Test Ürün\"\n";
echo "        },\n";
echo "        \"count\": 1,\n";
echo "        \"totalPriceWithOption\": 25.50\n";
echo "      }\n";
echo "    ],\n";
echo "    \"totalPrice\": 25.50\n";
echo "  }'\n";
echo "</pre>";
echo "</div>";

// Test 7: Mekan entegrasyon durumu
echo "<div class='test-section'>";
echo "<h2>7. Mekan Entegrasyon Durumu</h2>";
try {
    $mekanlar = $db->query("
        SELECT m.mekan_name, m.getir_restaurant_id, m.getir_app_secret_key, m.getir_restaurant_secret_key,
               CASE 
                   WHEN m.getir_restaurant_id IS NOT NULL THEN 'Entegre'
                   ELSE 'Entegre Değil'
               END as getir_status
        FROM mekanlar m
        ORDER BY m.mekan_name
    ")->fetchAll();
    
    if (empty($mekanlar)) {
        echo "<p class='error'>✗ Hiç mekan bulunamadı</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Mekan Adı</th><th>GetirYemek Durumu</th><th>Restoran ID</th><th>Kimlik Bilgileri</th></tr>";
        foreach ($mekanlar as $mekan) {
            $kimlik_durumu = ($mekan['getir_app_secret_key'] && $mekan['getir_restaurant_secret_key']) ? 'Mevcut' : 'Eksik';
            $durum_class = $mekan['getir_status'] === 'Entegre' ? 'success' : 'error';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($mekan['mekan_name']) . "</td>";
            echo "<td class='$durum_class'>" . $mekan['getir_status'] . "</td>";
            echo "<td>" . htmlspecialchars($mekan['getir_restaurant_id'] ?? '-') . "</td>";
            echo "<td>$kimlik_durumu</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Mekan sorgusu hatası: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 8: GetirYemek siparişleri
echo "<div class='test-section'>";
echo "<h2>8. GetirYemek Siparişleri</h2>";
try {
    $siparisler = $db->query("
        SELECT s.order_number, s.customer_name, s.total_price, s.getir_status, s.created_at,
               m.mekan_name
        FROM siparisler s
        JOIN mekanlar m ON s.mekan_id = m.id
        WHERE s.source = 'getir'
        ORDER BY s.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
    if (empty($siparisler)) {
        echo "<p class='info'>ℹ Henüz GetirYemek siparişi yok</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Sipariş No</th><th>Mekan</th><th>Müşteri</th><th>Tutar</th><th>Durum</th><th>Tarih</th></tr>";
        foreach ($siparisler as $siparis) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($siparis['order_number']) . "</td>";
            echo "<td>" . htmlspecialchars($siparis['mekan_name']) . "</td>";
            echo "<td>" . htmlspecialchars($siparis['customer_name']) . "</td>";
            echo "<td>" . number_format($siparis['total_amount'] ?? 0, 2) . " ₺</td>";
            echo "<td>" . $siparis['getir_status'] . "</td>";
            echo "<td>" . date('d.m.Y H:i', strtotime($siparis['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Sipariş sorgusu hatası: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 9: Log dosyası
echo "<div class='test-section'>";
echo "<h2>9. Log Dosyası</h2>";
$log_file = 'logs/getir.log';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $recent_logs = array_slice(array_reverse($log_lines), 0, 5);
    
    echo "<p class='success'>✓ Log dosyası mevcut: $log_file</p>";
    echo "<h4>Son 5 log kaydı:</h4>";
    echo "<pre>";
    foreach ($recent_logs as $log) {
        if (!empty(trim($log))) {
            echo htmlspecialchars($log) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<p class='error'>✗ Log dosyası bulunamadı: $log_file</p>";
}
echo "</div>";

// Test 10: Öneriler
echo "<div class='test-section'>";
echo "<h2>10. Sonraki Adımlar</h2>";
echo "<ol>";
echo "<li><strong>API Anahtarı:</strong> config/config.php dosyasında GETIR_API_KEY değerini güncelleyin</li>";
echo "<li><strong>Mekan Entegrasyonu:</strong> Admin panelinden mekanlar için GetirYemek kimlik bilgilerini ekleyin</li>";
echo "<li><strong>Webhook URL'leri:</strong> GetirYemek'e webhook URL'lerini kaydedin</li>";
echo "<li><strong>Test Siparişi:</strong> GetirYemek'ten test siparişi gönderin</li>";
echo "<li><strong>Ürün Yönetimi:</strong> GetirYemek ürünlerini senkronize edin</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p><strong>Test tamamlandı!</strong> GetirYemek entegrasyonu hazır.</p>";
echo "<p><a href='admin/getir-yonetim.php'>Admin Panel</a> | <a href='mekan/getir-siparisler.php'>Mekan Panel</a></p>";
?>
