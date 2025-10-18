<?php
/**
 * Basit Kurulum Scripti
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "MySQL bağlantısı başarılı\n";
    
    // Veritabanını oluştur
    $pdo->exec("CREATE DATABASE IF NOT EXISTS kurye_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Veritabanı oluşturuldu\n";
    
    // Veritabanını seç
    $pdo->exec("USE kurye_system");
    echo "Veritabanı seçildi\n";
    
    // Tabloları oluştur
    $tables = [
        "users" => "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            user_type ENUM('admin', 'mekan', 'kurye') NOT NULL,
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            profile_image VARCHAR(255),
            device_token VARCHAR(255),
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "mekanlar" => "CREATE TABLE mekanlar (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            mekan_name VARCHAR(100) NOT NULL,
            address TEXT NOT NULL,
            latitude DECIMAL(10, 8),
            longitude DECIMAL(11, 8),
            phone VARCHAR(20),
            email VARCHAR(100),
            category VARCHAR(50),
            status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
            rating DECIMAL(3,2) DEFAULT 0.00,
            total_orders INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "kuryeler" => "CREATE TABLE kuryeler (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            license_plate VARCHAR(20),
            vehicle_type ENUM('motosiklet', 'bisiklet', 'araba', 'yürüyerek') DEFAULT 'motosiklet',
            current_latitude DECIMAL(10, 8),
            current_longitude DECIMAL(11, 8),
            is_online BOOLEAN DEFAULT FALSE,
            is_available BOOLEAN DEFAULT TRUE,
            rating DECIMAL(3,2) DEFAULT 0.00,
            total_deliveries INT DEFAULT 0,
            total_earnings DECIMAL(10,2) DEFAULT 0.00,
            last_location_update TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "siparisler" => "CREATE TABLE siparisler (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(20) UNIQUE NOT NULL,
            mekan_id INT NOT NULL,
            kurye_id INT NULL,
            customer_name VARCHAR(100) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            customer_address TEXT NOT NULL,
            customer_latitude DECIMAL(10, 8),
            customer_longitude DECIMAL(11, 8),
            order_details JSON NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            delivery_fee DECIMAL(8,2) DEFAULT 0.00,
            commission_amount DECIMAL(8,2) DEFAULT 0.00,
            status ENUM('pending', 'accepted', 'preparing', 'ready', 'picked_up', 'delivering', 'delivered', 'cancelled') DEFAULT 'pending',
            priority ENUM('normal', 'urgent', 'express') DEFAULT 'normal',
            estimated_delivery_time INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            accepted_at TIMESTAMP NULL,
            picked_up_at TIMESTAMP NULL,
            delivered_at TIMESTAMP NULL,
            cancelled_at TIMESTAMP NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (mekan_id) REFERENCES mekanlar(id) ON DELETE CASCADE,
            FOREIGN KEY (kurye_id) REFERENCES kuryeler(id) ON DELETE SET NULL
        )",
        
        "sistem_ayarlari" => "CREATE TABLE sistem_ayarlari (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
            description TEXT,
            is_public BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "api_logs" => "CREATE TABLE api_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            endpoint VARCHAR(200) NOT NULL,
            method ENUM('GET', 'POST', 'PUT', 'DELETE', 'PATCH') NOT NULL,
            request_data TEXT,
            response_data TEXT,
            status_code INT,
            response_time FLOAT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    ];
    
    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        echo "Tablo oluşturuldu: $name\n";
    }
    
    // Test verilerini ekle
    $pdo->exec("INSERT INTO users (username, email, password, full_name, user_type, status) VALUES 
        ('admin', 'admin@kuryesystem.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'active'),
        ('test_mekan', 'mekan@test.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test Restoranı', 'mekan', 'active'),
        ('test_kurye', 'kurye@test.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test Kurye', 'kurye', 'active')
    ");
    
    $pdo->exec("INSERT INTO mekanlar (user_id, mekan_name, address, latitude, longitude, phone, category, status) VALUES 
        (2, 'Test Restoranı', 'Test Mahallesi, Test Sokak No:1', 41.0082, 28.9784, '05551234567', 'Restoran', 'active')
    ");
    
    $pdo->exec("INSERT INTO kuryeler (user_id, license_plate, vehicle_type, is_online, is_available) VALUES 
        (3, '34 ABC 123', 'motosiklet', true, true)
    ");
    
    $pdo->exec("INSERT INTO sistem_ayarlari (setting_key, setting_value, setting_type, description, is_public) VALUES 
        ('app_name', 'Kurye Full System', 'string', 'Uygulama adı', true),
        ('app_version', '1.0.0', 'string', 'Uygulama versiyonu', true),
        ('delivery_fee', '5.00', 'number', 'Varsayılan teslimat ücreti', true),
        ('commission_rate', '15.00', 'number', 'Varsayılan komisyon oranı (%)', false),
        ('location_update_interval', '30', 'number', 'Konum güncelleme aralığı (saniye)', false)
    ");
    
    echo "\nKurulum tamamlandı!\n";
    echo "Admin: admin / password\n";
    echo "Test Mekan: test_mekan / password\n";
    echo "Test Kurye: test_kurye / password\n";
    
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}
?>
