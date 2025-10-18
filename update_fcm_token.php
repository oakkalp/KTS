<?php
require_once 'config/config.php';

$db = getDB();

echo "=== FCM TOKEN GÜNCELLEME ===\n";

// Test için gerçek bir FCM token formatı kullan
// Bu token Firebase Console'dan veya Flutter uygulamasından alınmalı
$real_fcm_token = "eyJhbGciOiJSUzI1NiIsImtpZCI6IjE2NzM4OTk2NzI5NzQ2NzQ4NzMiLCJ0eXAiOiJKV1QifQ.eyJpc3MiOiJodHRwczovL3NlY3VyZXRva2VuLmdvb2dsZS5jb20vZnV0dXJlLWNhc3RsZS0xMjM0NTYiLCJhdWQiOiJmdXR1cmUtY2FzdGxlLTEyMzQ1NiIsImF1dGhfdGltZSI6MTY3Mzg5OTY3MywidXNlcl9pZCI6InRlc3RfdXNlcl8xMjM0NTYiLCJzdWIiOiJ0ZXN0X3VzZXJfMTIzNDU2IiwiaWF0IjoxNjczODk5NjczLCJleHAiOjE3MDU0MzU2NzMsImVtYWlsIjoidGVzdEBleGFtcGxlLmNvbSIsImVtYWlsX3ZlcmlmaWVkIjpmYWxzZSwiZmlyZWJhc2UiOnsiaWRlbnRpdGllcyI6eyJlbWFpbCI6WyJ0ZXN0QGV4YW1wbGUuY29tIl19LCJzaWduX2luX3Byb3ZpZGVyIjoicGFzc3dvcmQifX0.test_signature_here";

echo "Gerçek FCM token formatı:\n";
echo "Uzunluk: " . strlen($real_fcm_token) . " karakter\n";
echo "Başlangıç: " . substr($real_fcm_token, 0, 50) . "...\n";

// Test kullanıcısının token'ını güncelle
$stmt = $db->query("UPDATE users SET device_token = ? WHERE username = 'testkurye'", [$real_fcm_token]);

if ($stmt) {
    echo "✅ FCM token güncellendi!\n";
} else {
    echo "❌ FCM token güncellenemedi!\n";
}

echo "\n=== GÜNCEL TOKEN KONTROLÜ ===\n";
$stmt = $db->query("SELECT username, device_token FROM users WHERE username = 'testkurye'");
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "Kullanıcı: {$user['username']}\n";
    echo "Token: " . substr($user['device_token'], 0, 50) . "...\n";
    echo "Token uzunluğu: " . strlen($user['device_token']) . " karakter\n";
} else {
    echo "❌ Kullanıcı bulunamadı!\n";
}
?>
