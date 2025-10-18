-- Kurye Konum Geçmişi Tablosu
CREATE TABLE IF NOT EXISTS `kurye_konum_gecmisi` (
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
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`kurye_id`) REFERENCES `kuryeler` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kurye konum güncelleme tablosu (gerçek zamanlı konum için)
CREATE TABLE IF NOT EXISTS `kurye_konum` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kurye_id` int(11) NOT NULL UNIQUE,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `accuracy` decimal(8,2) DEFAULT NULL,
  `speed` decimal(8,2) DEFAULT NULL,
  `heading` decimal(8,2) DEFAULT NULL,
  `altitude` decimal(8,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_kurye_id` (`kurye_id`),
  FOREIGN KEY (`kurye_id`) REFERENCES `kuryeler` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

