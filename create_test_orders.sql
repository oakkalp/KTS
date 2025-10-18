-- Test siparişleri oluştur
USE kurye_system;

-- Test mekan ve kurye ID'lerini al
SET @test_mekan_id = (SELECT id FROM mekanlar WHERE user_id = (SELECT id FROM users WHERE username = 'testmekan') LIMIT 1);
SET @test_kurye_id = (SELECT id FROM kuryeler WHERE user_id = (SELECT id FROM users WHERE username = 'testkurye') LIMIT 1);

-- Eğer mekan yoksa oluştur
INSERT IGNORE INTO mekanlar (user_id, mekan_name, address, phone, status) 
SELECT u.id, 'Test Restaurant', 'Cumhuriyet Cad. No:45 Alsancak/İzmir', '+90 555 987 6543', 'active'
FROM users u WHERE u.username = 'testmekan';

SET @test_mekan_id = (SELECT id FROM mekanlar WHERE user_id = (SELECT id FROM users WHERE username = 'testmekan') LIMIT 1);

-- Aktif sipariş (kurye tarafından kabul edilmiş, hazır durumda)
INSERT INTO siparisler (
    order_number, mekan_id, kurye_id, customer_name, customer_phone, delivery_address, 
    order_details, total_amount, delivery_fee, payment_method, status, 
    created_at, accepted_at
) VALUES (
    CONCAT('ORD', LPAD(FLOOR(RAND() * 999999), 6, '0')), 
    @test_mekan_id, @test_kurye_id, 'Ahmet Yılmaz', '+90 555 123 4567', 
    'Atatürk Cad. No:123 Konak/İzmir', 
    '{"items":[{"name":"Pizza Margherita","quantity":1,"price":37.00},{"name":"Kola","quantity":1,"price":8.00}]}',
    45.00, 8.00, 'nakit', 'ready',
    DATE_SUB(NOW(), INTERVAL 10 MINUTE), DATE_SUB(NOW(), INTERVAL 8 MINUTE)
);

-- Bekleyen sipariş (henüz kurye atanmamış)
INSERT INTO siparisler (
    order_number, mekan_id, customer_name, customer_phone, delivery_address, 
    order_details, total_amount, delivery_fee, payment_method, status, 
    created_at
) VALUES (
    CONCAT('ORD', LPAD(FLOOR(RAND() * 999999), 6, '0')),
    @test_mekan_id, 'Fatma Demir', '+90 555 987 6543', 
    'İnönü Cad. No:67 Bornova/İzmir',
    '{"items":[{"name":"Burger Menu","quantity":1,"price":25.50},{"name":"Patates Kızartması","quantity":1,"price":7.00}]}',
    32.50, 7.00, 'online_kart', 'pending',
    DATE_SUB(NOW(), INTERVAL 3 MINUTE)
);

-- Bugün tamamlanmış sipariş (istatistikler için)
INSERT INTO siparisler (
    order_number, mekan_id, kurye_id, customer_name, customer_phone, delivery_address, 
    order_details, total_amount, delivery_fee, payment_method, status, 
    created_at, accepted_at, picked_up_at, delivered_at
) VALUES (
    CONCAT('ORD', LPAD(FLOOR(RAND() * 999999), 6, '0')),
    @test_mekan_id, @test_kurye_id, 'Mehmet Kaya', '+90 555 111 2233', 
    'Gazi Bulvarı No:89 Konak/İzmir',
    '{"items":[{"name":"Döner Menü","quantity":1,"price":22.50},{"name":"Ayran","quantity":1,"price":6.00}]}',
    28.50, 6.00, 'nakit', 'delivered',
    DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 110 MINUTE),
    DATE_SUB(NOW(), INTERVAL 90 MINUTE), DATE_SUB(NOW(), INTERVAL 75 MINUTE)
);

-- Kurye durumunu online yap
UPDATE kuryeler SET is_online = 1, is_available = 1, vehicle_type = 'motorcycle' WHERE id = @test_kurye_id;

SELECT 'Test siparişleri başarıyla oluşturuldu!' as message;
SELECT 'Mekan ID:' as info, @test_mekan_id as mekan_id;
SELECT 'Kurye ID:' as info, @test_kurye_id as kurye_id;
