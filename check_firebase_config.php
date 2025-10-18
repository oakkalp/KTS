<?php
require_once 'config/config.php';

echo "=== FIREBASE KONFIGÜRASYON KONTROLÜ ===\n";
echo "Project ID: " . FIREBASE_PROJECT_ID . "\n";
echo "Client Email: " . FIREBASE_CLIENT_EMAIL . "\n";
echo "Sender ID: " . FCM_SENDER_ID . "\n";
echo "Server Key: " . (FCM_SERVER_KEY === 'YOUR_LEGACY_SERVER_KEY_HERE' ? '❌ Henüz eklenmemiş' : '✅ Eklenmiş') . "\n";

if (FCM_SERVER_KEY !== 'YOUR_LEGACY_SERVER_KEY_HERE') {
    echo "Server Key uzunluğu: " . strlen(FCM_SERVER_KEY) . " karakter\n";
    echo "Server Key başlangıcı: " . substr(FCM_SERVER_KEY, 0, 10) . "...\n";
}
?>
