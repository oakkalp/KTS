<?php
/**
 * Kurye Full System - Installation Script
 * Veritabanı kurulum scripti
 */

// Hata gösterimini aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Temel ayarlar
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kurye_system');

echo "<h1>Kurye Full System - Kurulum</h1>";

try {
    // Veritabanı bağlantısını test et (veritabanı olmadan)
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<p style='color: green;'>✓ MySQL bağlantısı başarılı</p>";
    
    // SQL dosyasını oku
    $sql = file_get_contents('database_setup.sql');
    if (!$sql) {
        throw new Exception('database_setup.sql dosyası okunamadı');
    }
    
    echo "<p style='color: blue;'>📄 SQL dosyası okundu</p>";
    
    // SQL komutlarını ayır ve çalıştır
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || substr($statement, 0, 2) === '--') {
            continue;
        }
        
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            // Bazı hatalar görmezden gelinebilir (örneğin "database already exists")
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "<p style='color: orange;'>⚠ Uyarı: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    echo "<p style='color: green;'>✓ Veritabanı tabloları oluşturuldu</p>";
    
    // Test bağlantısı kurye_system veritabanına
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Tablo sayısını kontrol et
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    
    echo "<p style='color: green;'>✓ " . count($tables) . " tablo oluşturuldu</p>";
    
    // Test kullanıcılarını kontrol et
    $stmt = $pdo->query("SELECT username, user_type FROM users");
    $users = $stmt->fetchAll();
    
    echo "<h3>Test Kullanıcıları:</h3>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li><strong>" . htmlspecialchars($user['username']) . "</strong> (" . htmlspecialchars($user['user_type']) . ")</li>";
    }
    echo "</ul>";
    
    echo "<h3>Giriş Bilgileri:</h3>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin / password</li>";
    echo "<li><strong>Test Mekan:</strong> test_mekan / password</li>";
    echo "<li><strong>Test Kurye:</strong> test_kurye / password</li>";
    echo "</ul>";
    
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>🎉 Kurulum başarıyla tamamlandı!</p>";
    echo "<p><a href='index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ana Sayfaya Git</a></p>";
    echo "<p><a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Giriş Yap</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Hata: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Lütfen aşağıdakileri kontrol edin:</p>";
    echo "<ul>";
    echo "<li>XAMPP MySQL servisi çalışıyor mu?</li>";
    echo "<li>MySQL kullanıcı adı ve şifre doğru mu? (root / boş şifre)</li>";
    echo "<li>database_setup.sql dosyası var mı?</li>";
    echo "</ul>";
}
?>
