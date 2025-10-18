<?php
/**
 * Eksik tabloları oluştur
 */

require_once 'config/config.php';

try {
    $db = getDB();
    
    echo "Konum tabloları oluşturuluyor...\n";
    
    // 1. Kurye Konum Geçmişi Tablosu
    $sql1 = "CREATE TABLE IF NOT EXISTS `kurye_konum_gecmisi` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `kurye_id` int(11) NOT NULL,
      `latitude` decimal(10,8) NOT NULL,
      `longitude` decimal(11,8) NOT NULL,
      `accuracy` decimal(8,2) DEFAULT NULL,
      `speed` decimal(8,2) DEFAULT NULL,
      `heading` decimal(8,2) DEFAULT NULL,
      `altitude` decimal(8,2) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_kurye_id` (`kurye_id`),
      KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql1);
    echo "✅ kurye_konum_gecmisi tablosu oluşturuldu\n";
    
    // 2. Kurye Gerçek Zamanlı Konum Tablosu
    $sql2 = "CREATE TABLE IF NOT EXISTS `kurye_konum` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `kurye_id` int(11) NOT NULL,
      `latitude` decimal(10,8) NOT NULL,
      `longitude` decimal(11,8) NOT NULL,
      `accuracy` decimal(8,2) DEFAULT NULL,
      `speed` decimal(8,2) DEFAULT NULL,
      `heading` decimal(8,2) DEFAULT NULL,
      `altitude` decimal(8,2) DEFAULT NULL,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_kurye_id` (`kurye_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql2);
    echo "✅ kurye_konum tablosu oluşturuldu\n";
    
    // 3. Mevcut tabloları kontrol et
    $tables = $db->query("SHOW TABLES LIKE 'kurye%'")->fetchAll(PDO::FETCH_COLUMN);
    echo "\n📋 Kurye tabloları:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
    echo "\n🎉 Tüm tablolar başarıyla oluşturuldu!\n";
    echo "\nArtık konum sistemi çalışacak. Kurye dashboard'unda 'Konum Al' butonunu test edin.\n";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>

