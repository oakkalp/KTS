-- User Devices Table for Mobile App Management
-- Mobil cihaz yönetimi için kullanıcı cihazları tablosu

CREATE TABLE IF NOT EXISTS `user_devices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `device_id` varchar(255) NOT NULL,
    `device_name` varchar(255) DEFAULT NULL,
    `platform` enum('android','ios','web') NOT NULL,
    `app_version` varchar(50) DEFAULT NULL,
    `fcm_token` text DEFAULT NULL,
    `refresh_token` varchar(255) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `last_used_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_device` (`user_id`, `device_id`),
    KEY `idx_user_devices_user_id` (`user_id`),
    KEY `idx_user_devices_device_id` (`device_id`),
    KEY `idx_user_devices_fcm_token` (`fcm_token`(255)),
    CONSTRAINT `fk_user_devices_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX `idx_user_devices_active` ON `user_devices` (`is_active`);
CREATE INDEX `idx_user_devices_platform` ON `user_devices` (`platform`);
CREATE INDEX `idx_user_devices_last_used` ON `user_devices` (`last_used_at`);

-- Sample data for testing
INSERT INTO `user_devices` (`user_id`, `device_id`, `device_name`, `platform`, `app_version`, `fcm_token`, `is_active`) VALUES
(3, 'test_device_kurye_1', 'Samsung Galaxy S21', 'android', '1.0.0', 'fcm_token_test_kurye_1', 1),
(4, 'test_device_kurye_2', 'iPhone 13', 'ios', '1.0.0', 'fcm_token_test_kurye_2', 1);

-- Add comments
ALTER TABLE `user_devices` 
COMMENT = 'Mobil uygulama cihaz yönetimi tablosu';

-- Column comments
ALTER TABLE `user_devices` 
MODIFY COLUMN `device_id` varchar(255) NOT NULL COMMENT 'Benzersiz cihaz kimliği',
MODIFY COLUMN `device_name` varchar(255) DEFAULT NULL COMMENT 'Cihaz adı (örn: iPhone 13)',
MODIFY COLUMN `platform` enum('android','ios','web') NOT NULL COMMENT 'Platform türü',
MODIFY COLUMN `app_version` varchar(50) DEFAULT NULL COMMENT 'Uygulama versiyonu',
MODIFY COLUMN `fcm_token` text DEFAULT NULL COMMENT 'Firebase Cloud Messaging token',
MODIFY COLUMN `refresh_token` varchar(255) DEFAULT NULL COMMENT 'JWT refresh token',
MODIFY COLUMN `is_active` tinyint(1) DEFAULT 1 COMMENT 'Cihaz aktif mi?',
MODIFY COLUMN `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Son kullanım zamanı';
