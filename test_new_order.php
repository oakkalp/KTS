<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "=== BİLDİRİM SİSTEMİ TEST ===\n";

// Test kullanıcısının device token'ını kontrol et
$db = getDB();
$stmt = $db->query("SELECT u.device_token, u.username FROM users u WHERE u.user_type = 'kurye'");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Kurye kullanıcıları:\n";
foreach ($users as $user) {
    echo "- {$user['username']}: " . ($user['device_token'] ? 'Token var' : 'Token yok') . "\n";
}

echo "\n=== YENİ SİPARİŞ BİLDİRİMİ TEST ===\n";

// Yeni sipariş oluştur
$mekan_id = 1; // Test mekan
$order_number = generateOrderNumber();
$total_amount = 25.50;
$delivery_fee = 5.00;
$customer_name = "Test Müşteri";
$customer_phone = "05551234567";
$customer_address = "Test Adres, İstanbul";

try {
    $db->beginTransaction();
    
    $stmt = $db->query("
        INSERT INTO siparisler (order_number, mekan_id, customer_name, customer_phone, customer_address, order_details, total_amount, delivery_fee, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ", [$order_number, $mekan_id, $customer_name, $customer_phone, $customer_address, 'Test sipariş detayları', $total_amount, $delivery_fee]);
    $order_id = $db->lastInsertId();
    
    // Müsait kuryelere bildirim gönder
    $stmt = $db->query("
        SELECT u.device_token, u.username
        FROM users u 
        JOIN kuryeler k ON u.id = k.user_id 
        WHERE u.user_type = 'kurye' 
        AND u.device_token IS NOT NULL 
        AND k.is_online = 1 
        AND k.is_available = 1
        AND u.status = 'active'
    ");
    $courier_tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Müsait kuryeler: " . count($courier_tokens) . "\n";
    
    if (!empty($courier_tokens)) {
        $tokens = array_column($courier_tokens, 'device_token');
        $tokens = array_filter($tokens); // Boş token'ları filtrele
        
        if (!empty($tokens)) {
            $title = 'Yeni Sipariş Geldi! 🚚';
            $message = "Test Mekan - {$order_number} - {$total_amount}₺";
            $data = [
                'order_id' => $order_id,
                'type' => 'new_order',
                'restaurant_name' => 'Test Mekan',
                'total_amount' => $total_amount,
                'delivery_fee' => $delivery_fee
            ];
            
            echo "Bildirim gönderiliyor...\n";
            echo "Token sayısı: " . count($tokens) . "\n";
            
            $success = sendPushNotification($tokens, $title, $message, $data);
            
            if ($success) {
                echo "✅ Bildirim gönderildi!\n";
            } else {
                echo "❌ Bildirim gönderilemedi!\n";
            }
        } else {
            echo "❌ Geçerli token bulunamadı!\n";
        }
    } else {
        echo "❌ Müsait kurye bulunamadı!\n";
    }
    
    $db->commit();
    echo "✅ Sipariş oluşturuldu: {$order_number}\n";
    
} catch (Exception $e) {
    $db->rollback();
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>
