<?php
/**
 * Kuryeler tablosu düzeltme scripti
 */

require_once 'config/config.php';

try {
    $db = getDB();
    
    echo "Kuryeler tablosu kontrol ediliyor...\n";
    
    // Mevcut tablo yapısını kontrol et
    $columns = $db->query("SHOW COLUMNS FROM kuryeler")->fetchAll();
    
    echo "Mevcut sütunlar:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // full_name sütunu var mı kontrol et
    $hasFullName = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'full_name') {
            $hasFullName = true;
            break;
        }
    }
    
    if (!$hasFullName) {
        echo "\n❌ full_name sütunu bulunamadı. Ekleniyor...\n";
        
        $db->query("ALTER TABLE kuryeler ADD COLUMN full_name VARCHAR(255) NOT NULL DEFAULT ''");
        echo "✅ full_name sütunu eklendi\n";
        
        // Mevcut kayıtları güncelle (username'den full_name oluştur)
        $kuryeler = $db->query("
            SELECT k.id, u.username, u.first_name, u.last_name 
            FROM kuryeler k 
            JOIN users u ON k.user_id = u.id 
            WHERE k.full_name = '' OR k.full_name IS NULL
        ")->fetchAll();
        
        foreach ($kuryeler as $kurye) {
            $fullName = trim($kurye['first_name'] . ' ' . $kurye['last_name']);
            if (empty($fullName)) {
                $fullName = $kurye['username'];
            }
            
            $db->query("UPDATE kuryeler SET full_name = ? WHERE id = ?", [$fullName, $kurye['id']]);
            echo "- Kurye #{$kurye['id']}: $fullName\n";
        }
        
    } else {
        echo "\n✅ full_name sütunu zaten mevcut\n";
    }
    
    // Diğer eksik sütunları kontrol et
    $requiredColumns = [
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
        $hasColumn = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $columnName) {
                $hasColumn = true;
                break;
            }
        }
        
        if (!$hasColumn) {
            echo "\n❌ $columnName sütunu bulunamadı. Ekleniyor...\n";
            $db->query("ALTER TABLE kuryeler ADD COLUMN $columnName $columnDef");
            echo "✅ $columnName sütunu eklendi\n";
        }
    }
    
    echo "\n🎉 Kuryeler tablosu düzeltildi!\n";
    echo "\nGüncel tablo yapısı:\n";
    
    $newColumns = $db->query("SHOW COLUMNS FROM kuryeler")->fetchAll();
    foreach ($newColumns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>


