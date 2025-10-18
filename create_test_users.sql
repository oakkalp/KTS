-- Test kullanıcıları oluştur
USE kurye_system;

-- Test kullanıcılarını sil (varsa)
DELETE FROM kuryeler WHERE user_id IN (SELECT id FROM users WHERE username IN ('testkurye', 'testmekan', 'admin'));
DELETE FROM mekanlar WHERE user_id IN (SELECT id FROM users WHERE username IN ('testkurye', 'testmekan', 'admin'));
DELETE FROM users WHERE username IN ('testkurye', 'testmekan', 'admin');

-- Test kurye kullanıcısı
INSERT INTO users (username, password, user_type, full_name, email, phone, created_at) 
VALUES ('testkurye', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kurye', 'Test Kurye', 'testkurye@example.com', '+90 555 123 4567', NOW());

SET @kurye_user_id = LAST_INSERT_ID();

INSERT INTO kuryeler (user_id, license_plate, vehicle_type, is_online, is_available, created_at) 
VALUES (@kurye_user_id, '35 ABC 123', 'motorcycle', 1, 1, NOW());

-- Test mekan kullanıcısı
INSERT INTO users (username, password, user_type, full_name, email, phone, created_at) 
VALUES ('testmekan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mekan', 'Test Mekan', 'testmekan@example.com', '+90 555 987 6543', NOW());

SET @mekan_user_id = LAST_INSERT_ID();

INSERT INTO mekanlar (user_id, mekan_name, address, phone, status) 
VALUES (@mekan_user_id, 'Test Restaurant', 'Cumhuriyet Cad. No:45 Alsancak/İzmir', '+90 555 987 6543', 'active');

-- Test admin kullanıcısı
INSERT INTO users (username, password, user_type, full_name, email, phone, created_at) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Test Admin', 'admin@example.com', '+90 555 111 2233', NOW());

-- Test verileri başarıyla oluşturuldu

SELECT 'Test kullanıcıları ve siparişleri başarıyla oluşturuldu!' as message;
SELECT 'Giriş bilgileri:' as info;
SELECT 'testkurye / 123456 (Kurye)' as kurye_login;
SELECT 'testmekan / 123456 (Mekan)' as mekan_login;
SELECT 'admin / 123456 (Admin)' as admin_login;
