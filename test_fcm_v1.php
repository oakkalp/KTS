<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "=== FIREBASE CLOUD MESSAGING API V1 TEST ===\n";

// Test kullanıcısının device token'ını al
$db = getDB();
$stmt = $db->query("SELECT u.device_token, u.username FROM users u WHERE u.user_type = 'kurye' AND u.device_token IS NOT NULL");
$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Device token'lar:\n";
foreach ($tokens as $token) {
    echo "- {$token['username']}: " . substr($token['device_token'], 0, 30) . "...\n";
}

if (!empty($tokens)) {
    $device_tokens = array_column($tokens, 'device_token');
    $title = "Test Bildirimi 🔔";
    $message = "Firebase Cloud Messaging API v1 ile test bildirimi!";
    $data = [
        'type' => 'test_notification',
        'timestamp' => date('c')
    ];
    
    echo "\nFirebase API v1 ile bildirim gönderiliyor...\n";
    echo "Project ID: " . FIREBASE_PROJECT_ID . "\n";
    echo "Client Email: " . FIREBASE_CLIENT_EMAIL . "\n";
    
    $success = sendPushNotification($device_tokens, $title, $message, $data);
    
    if ($success) {
        echo "✅ Test bildirimi gönderildi!\n";
    } else {
        echo "❌ Test bildirimi gönderilemedi!\n";
    }
} else {
    echo "❌ Device token bulunamadı!\n";
}

echo "\n=== ACCESS TOKEN TEST ===\n";
$access_token = getFirebaseAccessToken();
if ($access_token) {
    echo "✅ Access token alındı: " . substr($access_token, 0, 20) . "...\n";
} else {
    echo "❌ Access token alınamadı!\n";
}
?>
