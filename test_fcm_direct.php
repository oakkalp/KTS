<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "=== GERÇEK TEST BİLDİRİMİ ===\n";

// Test için gerçek bir device token kullan
$test_token = "test_fcm_token_123456789"; // Bu gerçek token olmalı

$title = "Test Bildirimi 🔔";
$message = "Bu bir test bildirimidir. Sistem çalışıyor!";
$data = [
    'type' => 'test_notification',
    'timestamp' => date('c')
];

echo "FCM Server Key: " . substr(FCM_SERVER_KEY, 0, 20) . "...\n";
echo "Device Token: " . substr($test_token, 0, 30) . "...\n";

$success = sendPushNotification([$test_token], $title, $message, $data);

if ($success) {
    echo "✅ Test bildirimi gönderildi!\n";
} else {
    echo "❌ Test bildirimi gönderilemedi!\n";
}

// Curl hata kontrolü
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: key=' . FCM_SERVER_KEY,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'registration_ids' => [$test_token],
    'notification' => [
        'title' => $title,
        'body' => $message,
        'sound' => 'default'
    ],
    'data' => $data,
    'priority' => 'high'
]));

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "\n=== CURL DETAYLARI ===\n";
echo "HTTP Code: $http_code\n";
echo "Curl Error: " . ($curl_error ?: 'Yok') . "\n";
echo "Response: $result\n";
?>
