<?php
require_once 'config/config.php';

$db = getDB();

echo "=== SİPARİSLER TABLOSU YAPISI ===\n";
$stmt = $db->query("DESCRIBE siparisler");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    echo "- {$column['Field']}: {$column['Type']}\n";
}

echo "\n=== MEKANLAR TABLOSU YAPISI ===\n";
$stmt = $db->query("DESCRIBE mekanlar");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    echo "- {$column['Field']}: {$column['Type']}\n";
}

echo "\n=== CUSTOMERS TABLOSU VAR MI? ===\n";
try {
    $stmt = $db->query("DESCRIBE customers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Customers tablosu var:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']}\n";
    }
} catch (Exception $e) {
    echo "❌ Customers tablosu yok: " . $e->getMessage() . "\n";
}
?>
