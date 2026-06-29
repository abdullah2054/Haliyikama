-- ============================================================
-- Halı Yıkama Pazaryeri - Veritabanı Kurulum Dosyası
-- PHP 8+ / MySQL 5.7+ / MariaDB 10.3+ uyumlu
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. KULLANICILAR
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `email`      VARCHAR(200) NOT NULL UNIQUE,
  `phone`      VARCHAR(20) DEFAULT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('customer','company','consultant','admin') NOT NULL DEFAULT 'customer',
  `status`     ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. KONUMLAR
-- ============================================================
CREATE TABLE IF NOT EXISTS `locations_cities` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `locations_districts` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `city_id` INT UNSIGNED NOT NULL,
  `name`    VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_city` (`city_id`),
  FOREIGN KEY (`city_id`) REFERENCES `locations_cities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `locations_neighborhoods` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `district_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_district` (`district_id`),
  FOREIGN KEY (`district_id`) REFERENCES `locations_districts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. FİRMALAR
-- ============================================================
CREATE TABLE IF NOT EXISTS `companies` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL UNIQUE,
  `company_name`    VARCHAR(200) NOT NULL,
  `authorized_name` VARCHAR(150) NOT NULL,
  `phone`           VARCHAR(20) NOT NULL,
  `email`           VARCHAR(200) NOT NULL,
  `city_id`         INT UNSIGNED DEFAULT NULL,
  `district_id`     INT UNSIGNED DEFAULT NULL,
  `address`         TEXT DEFAULT NULL,
  `description`     TEXT DEFAULT NULL,
  `price_per_m2`    DECIMAL(10,2) DEFAULT 0.00,
  `min_order_price` DECIMAL(10,2) DEFAULT 0.00,
  `logo`            VARCHAR(255) DEFAULT NULL,
  `status`          ENUM('pending','active','rejected','suspended') NOT NULL DEFAULT 'pending',
  `balance`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user`   (`user_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`city_id`) REFERENCES `locations_cities`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`district_id`) REFERENCES `locations_districts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. FİRMA HİZMET BÖLGELERİ
-- ============================================================
CREATE TABLE IF NOT EXISTS `company_service_areas` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT UNSIGNED NOT NULL,
  `city_id`          INT UNSIGNED NOT NULL,
  `district_id`      INT UNSIGNED DEFAULT NULL,
  `neighborhood_id`  INT UNSIGNED DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_company` (`company_id`),
  INDEX `idx_location` (`city_id`, `district_id`, `neighborhood_id`),
  FOREIGN KEY (`company_id`)      REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`city_id`)         REFERENCES `locations_cities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`district_id`)     REFERENCES `locations_districts`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`neighborhood_id`) REFERENCES `locations_neighborhoods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. SİPARİŞLER
-- ============================================================
CREATE TABLE IF NOT EXISTS `orders` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tracking_code`         VARCHAR(20) NOT NULL UNIQUE,
  `customer_id`           INT UNSIGNED NOT NULL,
  `company_id`            INT UNSIGNED DEFAULT NULL,
  `city_id`               INT UNSIGNED DEFAULT NULL,
  `district_id`           INT UNSIGNED DEFAULT NULL,
  `neighborhood_id`       INT UNSIGNED DEFAULT NULL,
  `address`               TEXT NOT NULL,
  `carpet_count`          INT UNSIGNED NOT NULL DEFAULT 1,
  `total_m2`              DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `pickup_date`           DATE DEFAULT NULL,
  `estimated_delivery_date` DATE DEFAULT NULL,
  `note`                  TEXT DEFAULT NULL,
  `status`                ENUM(
                            'pending',
                            'accepted',
                            'picked_up',
                            'washing',
                            'drying',
                            'packaging',
                            'delivering',
                            'delivered',
                            'cancelled'
                          ) NOT NULL DEFAULT 'pending',
  `total_price`           DECIMAL(12,2) DEFAULT 0.00,
  `platform_fee`          DECIMAL(12,2) DEFAULT 0.00,
  `fee_status`            ENUM('pending','charged','refunded','waived') NOT NULL DEFAULT 'pending',
  `cancel_reason`         TEXT DEFAULT NULL,
  `cancelled_by`          ENUM('customer','company','admin') DEFAULT NULL,
  -- İleride harita/koordinat desteği için
  `lat`                   DECIMAL(10,7) DEFAULT NULL,
  `lng`                   DECIMAL(10,7) DEFAULT NULL,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tracking`  (`tracking_code`),
  INDEX `idx_customer`  (`customer_id`),
  INDEX `idx_company`   (`company_id`),
  INDEX `idx_status`    (`status`),
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`)  REFERENCES `companies`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`city_id`)     REFERENCES `locations_cities`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`district_id`) REFERENCES `locations_districts`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`neighborhood_id`) REFERENCES `locations_neighborhoods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. TEKLİFLER
-- ============================================================
CREATE TABLE IF NOT EXISTS `offers` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`                INT UNSIGNED NOT NULL,
  `company_id`              INT UNSIGNED NOT NULL,
  `price`                   DECIMAL(12,2) NOT NULL,
  `estimated_delivery_date` DATE DEFAULT NULL,
  `message`                 TEXT DEFAULT NULL,
  `status`                  ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_order`   (`order_id`),
  INDEX `idx_company` (`company_id`),
  FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. SİPARİŞ DURUM GEÇMİŞİ
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED NOT NULL,
  `status`     VARCHAR(50) NOT NULL,
  `note`       TEXT DEFAULT NULL,
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_order` (`order_id`),
  FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. BAKİYE HAREKETLERİ
-- ============================================================
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `order_id`    INT UNSIGNED DEFAULT NULL,
  `type`        ENUM('credit','debit','block','refund') NOT NULL,
  `amount`      DECIMAL(12,2) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_company` (`company_id`),
  INDEX `idx_order`   (`order_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. YORUMLAR
-- ============================================================
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`    INT UNSIGNED NOT NULL UNIQUE,
  `customer_id` INT UNSIGNED NOT NULL,
  `company_id`  INT UNSIGNED NOT NULL,
  `rating`      TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `comment`     TEXT DEFAULT NULL,
  `status`      ENUM('active','hidden') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_rating` CHECK (`rating` BETWEEN 1 AND 5),
  FOREIGN KEY (`order_id`)    REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`)  REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. BİLDİRİMLER
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `order_id`   INT UNSIGNED DEFAULT NULL,
  `type`       VARCHAR(50) NOT NULL DEFAULT 'email',
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT NOT NULL,
  `status`     ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user`  (`user_id`),
  INDEX `idx_order` (`order_id`),
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. CİHAZ TOKEN (Firebase Push için altyapı)
-- ============================================================
CREATE TABLE IF NOT EXISTS `device_tokens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `device_token` VARCHAR(500) NOT NULL,
  `platform`     ENUM('android_webview','web','ios_future') NOT NULL DEFAULT 'android_webview',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user` (`user_id`),
  UNIQUE KEY `uq_user_token` (`user_id`, `device_token`(255)),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. SİTE AYARLARI
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. ŞİKAYETLER
-- ============================================================
CREATE TABLE IF NOT EXISTS `complaints` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `order_id`    INT UNSIGNED DEFAULT NULL,
  `subject`     VARCHAR(255) NOT NULL,
  `message`     TEXT NOT NULL,
  `status`      ENUM('open','in_review','resolved','closed') NOT NULL DEFAULT 'open',
  `admin_note`  TEXT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. MESAJLAR (Firma-Müşteri İletişimi)
-- ============================================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `sender_id`  INT UNSIGNED NOT NULL,
  `message`    TEXT NOT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_order_company` (`order_id`, `company_id`),
  INDEX `idx_unread` (`is_read`),
  FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`)  REFERENCES `users`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VARSAYILAN AYARLAR
-- ============================================================
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('site_name',          'Halı Yıkama Pazaryeri'),
  ('site_email',         'info@siteadi.com'),
  ('platform_fee',       '25.00'),
  ('platform_fee_type',  'fixed'),
  ('smtp_host',          'smtp.example.com'),
  ('smtp_port',          '587'),
  ('smtp_user',          'mail@example.com'),
  ('smtp_pass',          ''),
  ('smtp_from_name',     'Halı Yıkama Pazaryeri'),
  ('smtp_encryption',    'tls'),
  ('sms_enabled',        '0'),
  ('push_enabled',       '0'),
  ('maintenance_mode',   '0'),
  ('terms_url',          '/terms.php'),
  ('privacy_url',        '/privacy.php');

-- ============================================================
-- ÖRNEK İLLER (Türkiye'nin büyük illeri)
-- ============================================================
INSERT IGNORE INTO `locations_cities` (`id`, `name`) VALUES
  (1,  'Adana'),   (2,  'Ankara'),   (3,  'Antalya'),  (4,  'Bursa'),
  (5,  'Diyarbakır'),(6,  'Erzurum'), (7,  'Eskişehir'),(8,  'Gaziantep'),
  (9,  'İstanbul'),(10, 'İzmir'),    (11, 'Kayseri'),  (12, 'Kocaeli'),
  (13, 'Konya'),   (14, 'Malatya'),  (15, 'Mersin'),   (16, 'Samsun'),
  (17, 'Şanlıurfa'),(18, 'Trabzon'), (19, 'Van'),      (20, 'Denizli');

-- Diyarbakır ilçeleri (örnek)
INSERT IGNORE INTO `locations_districts` (`city_id`, `name`) VALUES
  (5, 'Bağlar'), (5, 'Bismil'), (5, 'Çermik'), (5, 'Çınar'),
  (5, 'Ergani'), (5, 'Kayapınar'), (5, 'Silvan'), (5, 'Sur'), (5, 'Yenişehir');

-- Kayapınar mahalleleri (örnek)
INSERT IGNORE INTO `locations_neighborhoods` (`district_id`, `name`)
SELECT d.id, n.name FROM `locations_districts` d
CROSS JOIN (
  SELECT 'Bağıvar' AS name UNION SELECT 'Fırat' UNION SELECT 'Gevran' UNION
  SELECT 'Kayapınar' UNION SELECT 'Peyas' UNION SELECT 'Üçkuyular' UNION SELECT 'Diclekent'
) n WHERE d.name = 'Kayapınar' AND d.city_id = 5;

-- İstanbul ilçeleri (örnek)
INSERT IGNORE INTO `locations_districts` (`city_id`, `name`) VALUES
  (9, 'Kadıköy'), (9, 'Beşiktaş'), (9, 'Üsküdar'), (9, 'Maltepe'),
  (9, 'Kartal'),  (9, 'Pendik'),   (9, 'Ataşehir'),(9, 'Şişli'),
  (9, 'Beyoğlu'), (9, 'Fatih'),    (9, 'Bağcılar'),(9, 'Bahçelievler');

-- Ankara ilçeleri (örnek)
INSERT IGNORE INTO `locations_districts` (`city_id`, `name`) VALUES
  (2, 'Çankaya'), (2, 'Keçiören'), (2, 'Mamak'), (2, 'Etimesgut'),
  (2, 'Sincan'),  (2, 'Yenimahalle'), (2, 'Altındağ'), (2, 'Pursaklar');

-- İzmir ilçeleri (örnek)
INSERT IGNORE INTO `locations_districts` (`city_id`, `name`) VALUES
  (10, 'Bornova'), (10, 'Buca'), (10, 'Karşıyaka'), (10, 'Konak'),
  (10, 'Bayraklı'), (10, 'Gaziemir'), (10, 'Balçova'), (10, 'Narlıdere');

-- ============================================================
-- ADMIN KULLANICISI
-- password: admin123  (İlk girişte mutlaka değiştirin!)
-- ============================================================
INSERT IGNORE INTO `users` (`name`, `email`, `phone`, `password`, `role`, `status`) VALUES
  ('Admin', 'admin@siteadi.com', '05001234567',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'admin', 'active');

-- ============================================================
-- Admin şifresi: password (test için)
-- GERÇEK ORTAMDA HEMEN DEĞİŞTİRİN!
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;
