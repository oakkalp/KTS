<?php
/**
 * Kurye Full System - Eksik Tabloları Oluştur
 * odemeler ve bakiye tablolarını oluşturur
 */

require_once '../config/config.php';

try {
    $db = getDB();
    
    echo "<h2>Eksik Tabloları Oluşturma</h2>";
    
    // 1. odemeler tablosunu kontrol et ve oluştur
    $tables = $db->query("SHOW TABLES LIKE 'odemeler'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p>❌ odemeler tablosu eksik - oluşturuluyor...</p>";
        $db->query("
            CREATE TABLE odemeler (
                id int(11) NOT NULL AUTO_INCREMENT,
                user_id int(11) NOT NULL,
                user_type enum('kurye','mekan') NOT NULL,
                odeme_tutari decimal(10,2) DEFAULT 0.00,
                tahsilat_tutari decimal(10,2) DEFAULT 0.00,
                aciklama text,
                tarih date DEFAULT NULL,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_id (user_id),
                KEY idx_user_type (user_type),
                KEY idx_tarih (tarih),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p>✅ odemeler tablosu oluşturuldu</p>";
    } else {
        echo "<p>✅ odemeler tablosu mevcut</p>";
    }
    
    // 2. bakiye tablosunu kontrol et ve oluştur
    $tables = $db->query("SHOW TABLES LIKE 'bakiye'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p>❌ bakiye tablosu eksik - oluşturuluyor...</p>";
        $db->query("
            CREATE TABLE bakiye (
                id int(11) NOT NULL AUTO_INCREMENT,
                user_id int(11) NOT NULL,
                user_type enum('kurye','mekan') NOT NULL,
                borc decimal(10,2) DEFAULT 0.00,
                alacak decimal(10,2) DEFAULT 0.00,
                son_guncelleme timestamp DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_user (user_id, user_type),
                KEY idx_user_type (user_type),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p>✅ bakiye tablosu oluşturuldu</p>";
    } else {
        echo "<p>✅ bakiye tablosu mevcut</p>";
    }
    
    // 3. siparisler tablosuna eksik sütunları ekle
    $columns = $db->query("SHOW COLUMNS FROM siparisler LIKE 'payment_method'")->fetchAll();
    if (empty($columns)) {
        echo "<p>❌ siparisler.payment_method sütunu eksik - ekleniyor...</p>";
        $db->query("ALTER TABLE siparisler ADD COLUMN payment_method enum('nakit','kapida_kart','online_kart') DEFAULT 'nakit' AFTER commission_amount");
        echo "<p>✅ payment_method sütunu eklendi</p>";
    }
    
    $columns = $db->query("SHOW COLUMNS FROM siparisler LIKE 'odeme_durumu'")->fetchAll();
    if (empty($columns)) {
        echo "<p>❌ siparisler.odeme_durumu sütunu eksik - ekleniyor...</p>";
        $db->query("ALTER TABLE siparisler ADD COLUMN odeme_durumu enum('bekliyor','odendi','iptal') DEFAULT 'bekliyor' AFTER payment_method");
        $db->query("ALTER TABLE siparisler ADD COLUMN odeme_tarihi timestamp NULL AFTER odeme_durumu");
        echo "<p>✅ odeme_durumu ve odeme_tarihi sütunları eklendi</p>";
    }
    
    // 4. Bakiye tablosundaki sütun isimlerini kontrol et
    $columns = $db->query("SHOW COLUMNS FROM bakiye LIKE 'son_guncelleme'")->fetchAll();
    if (empty($columns)) {
        echo "<p>❌ bakiye.son_guncelleme sütunu eksik - ekleniyor...</p>";
        $db->query("ALTER TABLE bakiye ADD COLUMN son_guncelleme timestamp DEFAULT CURRENT_TIMESTAMP");
        echo "<p>✅ son_guncelleme sütunu eklendi</p>";
    }
    
    // 5. Tablo yapılarını göster
    echo "<h3>📋 Tablo Yapıları</h3>";
    
    echo "<h4>odemeler tablosu:</h4>";
    $columns = $db->query("SHOW COLUMNS FROM odemeler")->fetchAll();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Sütun</th><th>Tip</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";
    
    echo "<h4>bakiye tablosu:</h4>";
    $columns = $db->query("SHOW COLUMNS FROM bakiye")->fetchAll();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Sütun</th><th>Tip</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green; font-weight: bold;'>🎉 Tüm eksik tablolar ve sütunlar başarıyla oluşturuldu!</p>";
    echo "<p><a href='kuryeler.php'>← Kuryeler sayfasına dön</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Hata: " . $e->getMessage() . "</p>";
}
?>
