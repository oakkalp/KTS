<?php
require_once 'config/config.php';

// Test login
$username = 'testkurye';
$password = '123456';

echo "=== LOGIN API TEST ===\n\n";

// Kullanıcıyı bul
$user_query = "
    SELECT u.*, k.id as kurye_id, k.vehicle_type, k.is_online, k.is_available,
           m.id as mekan_id, m.mekan_name
    FROM users u
    LEFT JOIN kuryeler k ON u.id = k.user_id
    LEFT JOIN mekanlar m ON u.id = m.user_id
    WHERE u.username = ?
";

$user_stmt = $db->query($user_query, [$username]);
$user = $user_stmt->fetch();

if (!$user) {
    echo "❌ Kullanıcı bulunamadı: $username\n";
    exit;
}

echo "✅ Kullanıcı bulundu:\n";
echo "   ID: {$user['id']}\n";
echo "   Username: {$user['username']}\n";
echo "   User Type: {$user['user_type']}\n";
echo "   Full Name: {$user['full_name']}\n";
echo "   Kurye ID: {$user['kurye_id']}\n";
echo "   Password Hash: " . substr($user['password'], 0, 20) . "...\n\n";

// Şifre kontrolü
$password_check = password_verify($password, $user['password']);
echo "🔐 Şifre Kontrolü:\n";
echo "   Girilen şifre: $password\n";
echo "   Hash: " . substr($user['password'], 0, 30) . "...\n";
echo "   Verification: " . ($password_check ? "✅ BAŞARILI" : "❌ BAŞARISIZ") . "\n\n";

if (!$password_check) {
    // Yeni hash oluştur ve karşılaştır
    $new_hash = password_hash($password, PASSWORD_DEFAULT);
    echo "🔧 Debug:\n";
    echo "   Yeni hash: " . substr($new_hash, 0, 30) . "...\n";
    echo "   Yeni hash verification: " . (password_verify($password, $new_hash) ? "✅" : "❌") . "\n\n";
    
    // Hash'i güncelle
    $update_result = $db->query("UPDATE users SET password = ? WHERE username = ?", [$new_hash, $username]);
    echo "   Hash güncellendi: " . ($update_result ? "✅" : "❌") . "\n\n";
    
    // Tekrar test et
    $user_stmt = $db->query($user_query, [$username]);
    $user = $user_stmt->fetch();
    $password_check = password_verify($password, $user['password']);
    echo "   Yeni verification: " . ($password_check ? "✅ BAŞARILI" : "❌ BAŞARISIZ") . "\n\n";
}

if ($password_check) {
    echo "🎉 LOGIN BAŞARILI!\n\n";
    
    // JWT oluştur
    if (defined('JWT_SECRET')) {
        echo "🔑 JWT Token oluşturuluyor...\n";
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => (int)$user['id'],
            'username' => $user['username'],
            'user_type' => $user['user_type'],
            'kurye_id' => $user['kurye_id'] ? (int)$user['kurye_id'] : null,
            'iat' => time(),
            'exp' => time() + (30 * 24 * 60 * 60)
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        $jwt = $base64Header . "." . $base64Payload . "." . $base64Signature;
        
        echo "   Token: " . substr($jwt, 0, 50) . "...\n";
        echo "   ✅ JWT oluşturuldu!\n\n";
    } else {
        echo "   ⚠️ JWT_SECRET tanımlı değil\n\n";
    }
    
    echo "📱 API Response:\n";
    $response = [
        'success' => true,
        'message' => 'Giriş başarılı',
        'data' => [
            'token' => $jwt ?? 'JWT_SECRET_NOT_DEFINED',
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'user_type' => $user['user_type'],
                'full_name' => $user['full_name'],
                'kurye_id' => $user['kurye_id'] ? (int)$user['kurye_id'] : null
            ]
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
} else {
    echo "❌ LOGIN BAŞARISIZ!\n";
}
?>
