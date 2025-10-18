<?php
/**
 * Bildirim Düzeltmesini Test Et
 */

require_once 'config/config.php';

echo "=== BİLDİRİM SİSTEMİ TEST ===\n\n";

// 1. Firebase yapılandırmasını kontrol et
echo "1. Firebase Yapılandırması:\n";
echo "   FIREBASE_PROJECT_ID: " . FIREBASE_PROJECT_ID . "\n";
echo "   FIREBASE_CLIENT_EMAIL: " . FIREBASE_CLIENT_EMAIL . "\n";
echo "   FIREBASE_PRIVATE_KEY length: " . strlen(FIREBASE_PRIVATE_KEY) . " characters\n\n";

// 2. Aktif kuryeleri kontrol et
$db = getDB();
$stmt = $db->query("
    SELECT u.id, u.username, u.device_token, k.is_online, k.is_available
    FROM users u
    JOIN kuryeler k ON u.id = k.user_id
    WHERE u.user_type = 'kurye' AND u.status = 'active'
");
$kuryeler = $stmt->fetchAll();

echo "2. Aktif Kuryeler:\n";
foreach ($kuryeler as $kurye) {
    $status = $kurye['is_online'] ? '🟢 Online' : '🔴 Offline';
    $available = $kurye['is_available'] ? '✅ Müsait' : '❌ Meşgul';
    $token = $kurye['device_token'] ? '✅ Token var' : '❌ Token yok';
    echo "   - {$kurye['username']}: $status | $available | $token\n";
}
echo "\n";

// 3. Müsait kuryeleri filtrele
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

echo "3. Bildirim Gönderilecek Kuryeler:\n";
echo "   Token sayısı: " . count($courier_tokens) . "\n\n";

if (empty($courier_tokens)) {
    echo "❌ UYARI: Bildirim gönderilecek müsait kurye bulunamadı!\n";
    echo "   Çözüm: Bir kurye hesabına giriş yapıp 'Online' duruma getirin.\n";
    exit;
}

// 4. Test bildirimi gönder
echo "4. Test Bildirimi Gönderiliyor...\n";

$test_data = [
    'order_id' => 123,
    'type' => 'new_order',
    'restaurant_name' => 'Test Restoran',
    'total_amount' => 195,  // Integer (string'e dönüştürülecek)
    'delivery_fee' => 40    // Integer (string'e dönüştürülecek)
];

echo "   Test verisi:\n";
foreach ($test_data as $key => $value) {
    echo "      $key: $value (type: " . gettype($value) . ")\n";
}
echo "\n";

$success = sendPushNotification(
    $courier_tokens,
    'Test Bildirimi 🚚',
    'Yeni sipariş testi - 195₺',
    $test_data
);

if ($success) {
    echo "✅ Test bildirimi başarıyla gönderildi!\n";
    echo "   Mobil uygulamayı kontrol edin.\n\n";
} else {
    echo "❌ Test bildirimi gönderilemedi!\n";
    echo "   logs/notifications.log dosyasını kontrol edin.\n\n";
}

// 5. Son logları göster
echo "5. Son Bildirim Logları:\n";
$log_file = LOGS_PATH . '/notifications.log';
if (file_exists($log_file)) {
    $lines = file($log_file);
    $last_lines = array_slice($lines, -10);
    foreach ($last_lines as $line) {
        echo "   " . trim($line) . "\n";
    }
} else {
    echo "   Log dosyası bulunamadı.\n";
}

echo "\n=== TEST TAMAMLANDI ===\n";
?>



