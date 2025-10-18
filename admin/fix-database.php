<?php
/**
 * Database yapısını otomatik düzelt
 */

require_once '../config/config.php';

// Admin kontrolü
if (!isLoggedIn() || getUserType() !== 'admin') {
    die('Yetkisiz erişim');
}

$db = getDB();
$fixes = [];

try {
    echo "<h2>Database Düzeltme İşlemi</h2>";
    
    // 1. Kuryeler tablosu kontrol ve düzeltme
    echo "<h3>1. Kuryeler Tablosu</h3>";
    
    $columns = $db->query("SHOW COLUMNS FROM kuryeler")->fetchAll();
    $existingColumns = array_column($columns, 'Field');
    
    $requiredColumns = [
        'full_name' => 'VARCHAR(255) NOT NULL DEFAULT ""',
        'phone' => 'VARCHAR(20) DEFAULT NULL',
        'vehicle_type' => 'ENUM("motosiklet", "bisiklet", "araba", "yaya") DEFAULT "motosiklet"',
        'license_plate' => 'VARCHAR(20) DEFAULT NULL',
        'is_online' => 'TINYINT(1) DEFAULT 0',
        'is_available' => 'TINYINT(1) DEFAULT 1',
        'current_latitude' => 'DECIMAL(10,8) DEFAULT NULL',
        'current_longitude' => 'DECIMAL(11,8) DEFAULT NULL',
        'last_location_update' => 'TIMESTAMP NULL DEFAULT NULL'
    ];
    
    foreach ($requiredColumns as $columnName => $columnDef) {
        if (!in_array($columnName, $existingColumns)) {
            echo "❌ Eksik sütun: $columnName<br>";
            $db->query("ALTER TABLE kuryeler ADD COLUMN $columnName $columnDef");
            echo "✅ $columnName sütunu eklendi<br>";
            $fixes[] = "kuryeler.$columnName eklendi";
        } else {
            echo "✅ $columnName sütunu mevcut<br>";
        }
    }
    
    // full_name sütunu boş ise doldur
    try {
        $emptyFullNames = $db->query("
            SELECT k.id, u.first_name, u.last_name, u.username 
            FROM kuryeler k 
            JOIN users u ON k.user_id = u.id 
            WHERE k.full_name = '' OR k.full_name IS NULL
        ")->fetchAll();
    } catch (Exception $e) {
        // first_name/last_name sütunları yoksa sadece username kullan
        $emptyFullNames = $db->query("
            SELECT k.id, u.username, u.username as first_name, '' as last_name
            FROM kuryeler k 
            JOIN users u ON k.user_id = u.id 
            WHERE k.full_name = '' OR k.full_name IS NULL
        ")->fetchAll();
    }
    
    if ($emptyFullNames) {
        echo "<br><strong>full_name sütunu doldurulıyor:</strong><br>";
        foreach ($emptyFullNames as $kurye) {
            $fullName = trim($kurye['first_name'] . ' ' . $kurye['last_name']);
            if (empty($fullName)) {
                $fullName = $kurye['username'];
            }
            
            $db->query("UPDATE kuryeler SET full_name = ? WHERE id = ?", [$fullName, $kurye['id']]);
            echo "- Kurye #{$kurye['id']}: $fullName<br>";
            $fixes[] = "Kurye #{$kurye['id']} full_name güncellendi";
        }
    }
    
    // 2. Konum tabloları kontrol
    echo "<h3>2. Konum Tabloları</h3>";
    
    $tables = $db->query("SHOW TABLES LIKE 'kurye_konum%'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('kurye_konum_gecmisi', $tables)) {
        echo "❌ kurye_konum_gecmisi tablosu eksik<br>";
        $db->query("CREATE TABLE kurye_konum_gecmisi (
            id int(11) NOT NULL AUTO_INCREMENT,
            kurye_id int(11) NOT NULL,
            latitude decimal(10,8) NOT NULL,
            longitude decimal(11,8) NOT NULL,
            accuracy decimal(8,2) DEFAULT NULL,
            speed decimal(8,2) DEFAULT NULL,
            heading decimal(8,2) DEFAULT NULL,
            altitude decimal(8,2) DEFAULT NULL,
            siparis_id int(11) DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_kurye_id (kurye_id),
            KEY idx_created_at (created_at),
            KEY idx_siparis_id (siparis_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ kurye_konum_gecmisi tablosu oluşturuldu<br>";
        $fixes[] = "kurye_konum_gecmisi tablosu oluşturuldu";
    } else {
        echo "✅ kurye_konum_gecmisi tablosu mevcut<br>";
    }
    
    if (!in_array('kurye_konum', $tables)) {
        echo "❌ kurye_konum tablosu eksik<br>";
        $db->query("CREATE TABLE kurye_konum (
            id int(11) NOT NULL AUTO_INCREMENT,
            kurye_id int(11) NOT NULL,
            latitude decimal(10,8) NOT NULL,
            longitude decimal(11,8) NOT NULL,
            accuracy decimal(8,2) DEFAULT NULL,
            speed decimal(8,2) DEFAULT NULL,
            heading decimal(8,2) DEFAULT NULL,
            altitude decimal(8,2) DEFAULT NULL,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_kurye_id (kurye_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ kurye_konum tablosu oluşturuldu<br>";
        $fixes[] = "kurye_konum tablosu oluşturuldu";
    } else {
        echo "✅ kurye_konum tablosu mevcut<br>";
    }
    
    // 3. Siparisler tablosu kontrol (isteğe bağlı)
    echo "<h3>3. Siparisler Tablosu (İsteğe Bağlı)</h3>";
    
    try {
        $orderColumns = $db->query("SHOW COLUMNS FROM siparisler")->fetchAll();
        $existingOrderColumns = array_column($orderColumns, 'Field');
        
        $orderRequiredColumns = [
            'mekan_adi' => 'VARCHAR(255) DEFAULT NULL',
            'musteri_adi' => 'VARCHAR(255) DEFAULT NULL',
            'teslimat_adresi' => 'TEXT DEFAULT NULL',
            'delivery_address' => 'TEXT DEFAULT NULL',
            'customer_name' => 'VARCHAR(255) DEFAULT NULL',
            'customer_phone' => 'VARCHAR(20) DEFAULT NULL',
            'preparation_time' => 'INT DEFAULT NULL',
            'payment_method' => 'ENUM("nakit", "kapida_kart", "online_kart") DEFAULT "nakit"'
        ];
        
        foreach ($orderRequiredColumns as $columnName => $columnDef) {
            if (!in_array($columnName, $existingOrderColumns)) {
                echo "❌ Eksik sipariş sütunu: $columnName<br>";
                try {
                    $db->query("ALTER TABLE siparisler ADD COLUMN $columnName $columnDef");
                    echo "✅ $columnName sütunu eklendi<br>";
                    $fixes[] = "siparisler.$columnName eklendi";
                } catch (Exception $e) {
                    echo "⚠️ $columnName sütunu eklenemedi: " . $e->getMessage() . "<br>";
                }
            } else {
                echo "✅ $columnName sütunu mevcut<br>";
            }
        }
    } catch (Exception $e) {
        echo "⚠️ Siparisler tablosu kontrol edilemedi (normal olabilir): " . $e->getMessage() . "<br>";
    }
    
    // 4. Özet
    echo "<h3>4. Düzeltme Özeti</h3>";
    if (empty($fixes)) {
        echo "<p style='color: green;'>✅ Tüm database yapısı güncel!</p>";
    } else {
        echo "<p style='color: blue;'>📋 Yapılan düzeltmeler:</p>";
        echo "<ul>";
        foreach ($fixes as $fix) {
            echo "<li>$fix</li>";
        }
        echo "</ul>";
    }
    
    echo "<br><a href='konum-gecmisi.php' class='btn btn-primary'>Konum Geçmişi Sayfasını Test Et</a>";
    echo " <a href='dashboard.php' class='btn btn-secondary'>Dashboard'a Dön</a>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Hata: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.btn { padding: 10px 15px; margin: 5px; text-decoration: none; border-radius: 5px; display: inline-block; }
.btn-primary { background: #007bff; color: white; }
.btn-secondary { background: #6c757d; color: white; }
</style>
