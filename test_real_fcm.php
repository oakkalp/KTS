<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "=== GERÇEK FCM TOKEN TEST ===\n";

// Gerçek bir FCM token formatı (Firebase Console'dan alınmalı)
// Bu token Firebase Console > Project Settings > Cloud Messaging > Server Key altında bulunur
$real_fcm_token = "dGVzdF9mY21fdG9rZW5fZm9yX2ZpcmViYXNlX2Nsb3VkX21lc3NhZ2luZ19hcGlfdGVzdGluZ19wdXJwb3Nlc19vbmx5X2RvX25vdF91c2VfaW5fcHJvZHVjdGlvbg";

echo "Test FCM token: " . substr($real_fcm_token, 0, 50) . "...\n";
echo "Token uzunluğu: " . strlen($real_fcm_token) . " karakter\n";

// Test kullanıcısının token'ını güncelle
$db = getDB();
$stmt = $db->query("UPDATE users SET device_token = ? WHERE username = 'testkurye'", [$real_fcm_token]);

echo "✅ Test FCM token güncellendi!\n";

// Test bildirimi gönder
$device_tokens = [$real_fcm_token];
$title = "Test Bildirimi 🔔";
$message = "Firebase Cloud Messaging API v1 test!";
$data = [
    'type' => 'test_notification',
    'timestamp' => date('c')
];

echo "\nTest bildirimi gönderiliyor...\n";
$success = sendPushNotification($device_tokens, $title, $message, $data);

if ($success) {
    echo "✅ Test bildirimi gönderildi!\n";
} else {
    echo "❌ Test bildirimi gönderilemedi!\n";
}

echo "\n=== ÖNEMLİ NOT ===\n";
echo "Bu test token'ı gerçek değil!\n";
echo "Gerçek FCM token'ı Flutter uygulamasından almanız gerekiyor:\n";
echo "1. Flutter uygulamasını çalıştırın\n";
echo "2. Login yapın\n";
echo "3. Debug console'da 'FCM Token:' satırını bulun\n";
echo "4. O token'ı database'e kaydedin\n";
?>
