<?php
require_once 'config/config.php';

try {
    $db = getDB();
    
    echo "<h2>Sistem Ayarları Kurulumu</h2>";
    
    // sistem_ayarlari tablosunu kontrol et
    $tables = $db->query("SHOW TABLES LIKE 'sistem_ayarlari'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p>❌ sistem_ayarlari tablosu eksik - oluşturuluyor...</p>";
        $db->query("
            CREATE TABLE sistem_ayarlari (
                id int(11) NOT NULL AUTO_INCREMENT,
                setting_key varchar(100) NOT NULL,
                setting_value text,
                description varchar(255) DEFAULT NULL,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p>✅ sistem_ayarlari tablosu oluşturuldu</p>";
    } else {
        echo "<p>✅ sistem_ayarlari tablosu mevcut</p>";
    }
    
    // Varsayılan ayarları ekle
    $default_settings = [
        'commission_rate' => '15.00',
        'max_orders_per_courier' => '5',
        'max_delivery_time' => '25',
        'delivery_fee' => '10.00'
    ];
    
    foreach ($default_settings as $key => $value) {
        try {
            $stmt = $db->query("SELECT id FROM sistem_ayarlari WHERE setting_key = ?", [$key]);
            if (!$stmt->fetch()) {
                $db->query("INSERT INTO sistem_ayarlari (setting_key, setting_value, description) VALUES (?, ?, ?)", 
                    [$key, $value, "Varsayılan $key ayarı"]);
                echo "<p>✅ $key ayarı eklendi: $value</p>";
            } else {
                echo "<p>ℹ️ $key ayarı zaten mevcut</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ $key ayarı eklenemedi: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<p><strong>Sistem ayarları kurulumu tamamlandı!</strong></p>";
    echo "<p><a href='kuryeler.php' class='btn btn-primary'>Kuryeler Sayfasına Dön</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Hata: " . $e->getMessage() . "</p>";
}
?>

