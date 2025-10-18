<?php
require_once 'config/config.php';

try {
    // Test kurye kullanıcısı oluştur
    $password_hash = password_hash('123456', PASSWORD_DEFAULT);
    
    // Users tablosuna ekle
    $user_stmt = $db->prepare("
        INSERT INTO users (username, password, user_type, full_name, email, phone, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE password = VALUES(password)
    ");
    
    $user_stmt->execute([
        'testkurye',
        $password_hash,
        'kurye',
        'Test Kurye',
        'testkurye@example.com',
        '+90 555 123 4567'
    ]);
    
    $user_id = $db->lastInsertId() ?: $db->query("SELECT id FROM users WHERE username = 'testkurye'")->fetchColumn();
    
    // Kuryeler tablosuna ekle
    $kurye_stmt = $db->prepare("
        INSERT INTO kuryeler (user_id, license_plate, vehicle_type, is_online, is_available, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            license_plate = VALUES(license_plate),
            vehicle_type = VALUES(vehicle_type)
    ");
    
    $kurye_stmt->execute([
        $user_id,
        '35 ABC 123',
        'motorcycle',
        false,
        false
    ]);
    
    echo "✅ Test kurye kullanıcısı oluşturuldu:\n";
    echo "   Username: testkurye\n";
    echo "   Password: 123456\n";
    echo "   User ID: $user_id\n\n";
    
    // Test mekan kullanıcısı oluştur
    $user_stmt->execute([
        'testmekan',
        $password_hash,
        'mekan',
        'Test Mekan',
        'testmekan@example.com',
        '+90 555 987 6543'
    ]);
    
    $mekan_user_id = $db->lastInsertId() ?: $db->query("SELECT id FROM users WHERE username = 'testmekan'")->fetchColumn();
    
    // Mekanlar tablosuna ekle
    $mekan_stmt = $db->prepare("
        INSERT INTO mekanlar (user_id, mekan_name, address, phone, cuisine_type, is_open, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            mekan_name = VALUES(mekan_name),
            address = VALUES(address)
    ");
    
    $mekan_stmt->execute([
        $mekan_user_id,
        'Test Restaurant',
        'Cumhuriyet Cad. No:45 Alsancak/İzmir',
        '+90 555 987 6543',
        'turkish',
        true
    ]);
    
    echo "✅ Test mekan kullanıcısı oluşturuldu:\n";
    echo "   Username: testmekan\n";
    echo "   Password: 123456\n";
    echo "   User ID: $mekan_user_id\n\n";
    
    // Test admin kullanıcısı oluştur
    $user_stmt->execute([
        'admin',
        $password_hash,
        'admin',
        'Test Admin',
        'admin@example.com',
        '+90 555 111 2233'
    ]);
    
    $admin_user_id = $db->lastInsertId() ?: $db->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
    
    echo "✅ Test admin kullanıcısı oluşturuldu:\n";
    echo "   Username: admin\n";
    echo "   Password: 123456\n";
    echo "   User ID: $admin_user_id\n\n";
    
    echo "🎉 Tüm test kullanıcıları başarıyla oluşturuldu!\n\n";
    echo "Mobil uygulama test etmek için:\n";
    echo "- Kurye: testkurye / 123456\n";
    echo "- Mekan: testmekan / 123456\n";
    echo "- Admin: admin / 123456\n";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>
