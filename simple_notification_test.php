<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "=== BASİT BİLDİRİM TEST ===\n";

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
    $message = "Bu bir test bildirimidir. Sistem çalışıyor!";
    $data = [
        'type' => 'test_notification',
        'timestamp' => date('c')
    ];
    
    echo "\nBildirim gönderiliyor...\n";
    echo "FCM Server Key: " . substr(FCM_SERVER_KEY, 0, 20) . "...\n";
    
    $success = sendPushNotification($device_tokens, $title, $message, $data);
    
    if ($success) {
        echo "✅ Test bildirimi gönderildi!\n";
    } else {
        echo "❌ Test bildirimi gönderilemedi!\n";
        
        // Manuel curl test
        echo "\n=== MANUEL CURL TEST ===\n";
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
            'registration_ids' => $device_tokens,
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

        echo "HTTP Code: $http_code\n";
        echo "Curl Error: " . ($curl_error ?: 'Yok') . "\n";
        echo "Response: $result\n";
    }
} else {
    echo "❌ Device token bulunamadı!\n";
}
?>
