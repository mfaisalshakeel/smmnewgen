-- ===========================================================================
-- SMM Panel - database schema and seed data
-- Run by install/install.php. Safe to re-run on an empty database only.
-- ===========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --- key/value settings ----------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(64) NOT NULL,
  `v` TEXT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- admin users -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `last_login_at` DATETIME NULL,
  `two_factor` VARCHAR(10) NOT NULL DEFAULT 'off',
  `totp_secret` VARCHAR(64) NOT NULL DEFAULT '',
  `recovery_codes` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- providers (SMM APIs we buy from) --------------------------------------
CREATE TABLE IF NOT EXISTS `providers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `api_url` VARCHAR(255) NOT NULL,
  `api_key` VARCHAR(255) NOT NULL,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `balance_currency` VARCHAR(10) NOT NULL DEFAULT '',
  `balance_checked_at` DATETIME NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_providers_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- platforms -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `platforms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(60) NOT NULL,
  `name` VARCHAR(60) NOT NULL,
  `icon` VARCHAR(40) NOT NULL DEFAULT '',
  `color` VARCHAR(20) NOT NULL DEFAULT '',
  `meta_title` VARCHAR(190) NOT NULL DEFAULT '',
  `meta_description` VARCHAR(255) NOT NULL DEFAULT '',
  `url_prefix` VARCHAR(190) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_platforms_slug` (`slug`),
  KEY `ix_platforms_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- categories (per platform) ---------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `platform_id` INT UNSIGNED NOT NULL,
  `slug` VARCHAR(60) NOT NULL,
  `name` VARCHAR(60) NOT NULL,
  `meta_title` VARCHAR(190) NOT NULL DEFAULT '',
  `meta_description` VARCHAR(255) NOT NULL DEFAULT '',
  `service_id` INT UNSIGNED NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_platform_slug` (`platform_id`, `slug`),
  KEY `ix_categories_active` (`is_active`, `sort_order`),
  CONSTRAINT `fk_categories_platform` FOREIGN KEY (`platform_id`)
    REFERENCES `platforms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- services --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_id` INT UNSIGNED NULL,
  `provider_service_id` VARCHAR(40) NOT NULL DEFAULT '',
  `platform_id` INT UNSIGNED NULL,
  `category_id` INT UNSIGNED NULL,
  `name` VARCHAR(190) NOT NULL,
  `description` TEXT NULL,
  `badge` VARCHAR(40) NOT NULL DEFAULT '',
  `features` TEXT NULL,
  `cost_per_1000` DECIMAL(12,4) NOT NULL DEFAULT 0,
  `price_per_1000` DECIMAL(12,4) NOT NULL DEFAULT 0,
  `min_qty` INT UNSIGNED NOT NULL DEFAULT 100,
  `max_qty` INT UNSIGNED NOT NULL DEFAULT 100000,
  `delivery_time` VARCHAR(60) NOT NULL DEFAULT '',
  `supports_refill` TINYINT(1) NOT NULL DEFAULT 0,
  `supports_cancel` TINYINT(1) NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_services_provider_service` (`provider_id`, `provider_service_id`),
  KEY `ix_services_listing` (`is_active`, `platform_id`, `category_id`, `sort_order`),
  CONSTRAINT `fk_services_provider` FOREIGN KEY (`provider_id`)
    REFERENCES `providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_services_platform` FOREIGN KEY (`platform_id`)
    REFERENCES `platforms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- payment methods -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `driver` VARCHAR(40) NOT NULL DEFAULT 'manual',
  `config` TEXT NULL,
  `short_name` VARCHAR(20) NOT NULL DEFAULT '',
  `account_title` VARCHAR(120) NOT NULL DEFAULT '',
  `account_number` VARCHAR(120) NOT NULL DEFAULT '',
  `extra_label` VARCHAR(60) NOT NULL DEFAULT '',
  `extra_value` VARCHAR(190) NOT NULL DEFAULT '',
  `instructions` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `ix_payment_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- orders ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `service_id` INT UNSIGNED NULL,
  `platform_id` INT UNSIGNED NULL,
  `service_name` VARCHAR(190) NOT NULL DEFAULT '',
  `service_label` VARCHAR(190) NOT NULL DEFAULT '',
  `quantity` INT UNSIGNED NOT NULL,
  `link` VARCHAR(500) NOT NULL,
  `whatsapp` VARCHAR(40) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `sending_at` DATETIME NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `cost` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` ENUM('pending','paid','processing','completed','partial','cancelled','refunded','api_error')
      NOT NULL DEFAULT 'pending',
  `provider_id` INT UNSIGNED NULL,
  `provider_order_id` VARCHAR(60) NOT NULL DEFAULT '',
  `provider_status` VARCHAR(60) NOT NULL DEFAULT '',
  `start_count` INT UNSIGNED NULL,
  `remains` INT UNSIGNED NULL,
  `api_error` VARCHAR(500) NOT NULL DEFAULT '',
  `payment_method_id` INT UNSIGNED NULL,
  `trx_id` VARCHAR(120) NOT NULL DEFAULT '',
  `paid_amount` DECIMAL(12,2) NULL,
  `paid_at` DATETIME NULL,
  `synced_at` DATETIME NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_code` (`code`),
  KEY `ix_orders_status` (`status`, `created_at`),
  KEY `ix_orders_platform` (`platform_id`),
  KEY `ix_orders_provider` (`provider_id`, `provider_order_id`),
  CONSTRAINT `fk_orders_service` FOREIGN KEY (`service_id`)
    REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_platform` FOREIGN KEY (`platform_id`)
    REFERENCES `platforms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_payment` FOREIGN KEY (`payment_method_id`)
    REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- order activity log ----------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_packages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `bonus_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `badge` VARCHAR(40) NOT NULL DEFAULT '',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_service_packages_service` (`service_id`, `sort_order`),
  CONSTRAINT `fk_service_packages_service` FOREIGN KEY (`service_id`)
    REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `is_internal` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_order_logs_order` (`order_id`, `created_at`),
  CONSTRAINT `fk_order_logs_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- content pages ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `content` MEDIUMTEXT NULL,
  `meta_title` VARCHAR(190) NOT NULL DEFAULT '',
  `meta_description` VARCHAR(255) NOT NULL DEFAULT '',
  `show_in_footer` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- FAQs ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `platform_id` INT UNSIGNED NULL,
  `question` VARCHAR(255) NOT NULL,
  `answer` TEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `ix_faqs_active` (`is_active`, `sort_order`),
  CONSTRAINT `fk_faqs_platform` FOREIGN KEY (`platform_id`)
    REFERENCES `platforms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- contact messages ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `whatsapp` VARCHAR(40) NOT NULL DEFAULT '',
  `body` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_messages_read` (`is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- throttling ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action` VARCHAR(40) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_rate_action_ip` (`action`, `ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================================================
-- Seed data
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- Notifications, mail and two-factor.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event` VARCHAR(60) NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `body` VARCHAR(500) NOT NULL DEFAULT '',
  `link` VARCHAR(190) NOT NULL DEFAULT '',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_notifications_read` (`is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event` VARCHAR(60) NOT NULL,
  `subject` VARCHAR(190) NOT NULL,
  `body` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_templates_event` (`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event` VARCHAR(60) NOT NULL DEFAULT '',
  `recipient` VARCHAR(190) NOT NULL,
  `subject` VARCHAR(190) NOT NULL DEFAULT '',
  `status` VARCHAR(12) NOT NULL DEFAULT 'sent',
  `error` VARCHAR(500) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_email_log_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Locks, and the record of who changed what.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `locks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `owner` VARCHAR(80) NOT NULL DEFAULT '',
  `acquired_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_locks_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action` VARCHAR(60) NOT NULL,
  `actor_type` VARCHAR(12) NOT NULL DEFAULT 'system',
  `actor_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `actor_name` VARCHAR(80) NOT NULL DEFAULT '',
  `entity` VARCHAR(40) NOT NULL DEFAULT '',
  `entity_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `summary` VARCHAR(300) NOT NULL DEFAULT '',
  `severity` VARCHAR(8) NOT NULL DEFAULT 'info',
  `before_json` TEXT NULL,
  `after_json` TEXT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_audit_time` (`created_at`),
  KEY `ix_audit_entity` (`entity`, `entity_id`),
  KEY `ix_audit_severity` (`severity`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`k`, `v`) VALUES
  ('site_name',            'GrowKit'),
  ('site_tagline',         'Buy social media growth, safely'),
  ('currency_symbol',      'Rs '),
  ('default_platform',     'instagram'),
  ('theme_color',          '#6c4df6'),
  ('whatsapp_number',      ''),
  ('support_email',        ''),
  ('logo_path',            ''),
  ('favicon_path',         ''),
  ('head_code',            ''),
  ('default_markup',       '35'),
  ('auto_send_orders',     '1'),
  ('auto_sync_statuses',   '1'),
  ('require_trx_id',       '1'),
  ('allow_manual_services','1'),
  ('order_prefix',         'GK'),
  ('active_theme',         'default'),
  ('auto_packages',        '1'),
  ('default_features',     'High Quality - 100% Real
Fast Delivery
100% Safe & Secure
No Password Required'),
  ('app_version',          '')
ON DUPLICATE KEY UPDATE `k` = `k`;

INSERT INTO `platforms` (`slug`, `name`, `icon`, `color`, `url_prefix`, `sort_order`, `is_active`) VALUES
  ('instagram', 'Instagram', 'i-instagram', '#e1306c', 'instagram.com',  1, 1),
  ('tiktok',    'TikTok',    'i-tiktok',    '#111827', 'tiktok.com',     2, 1),
  ('youtube',   'YouTube',   'i-youtube',   '#ef4444', 'youtube.com',    3, 1),
  ('facebook',  'Facebook',  'i-facebook',  '#1877f2', 'facebook.com',   4, 1),
  ('x',         'X',         'i-x',         '#0f172a', 'x.com',          5, 1),
  ('telegram',  'Telegram',  'i-telegram',  '#2aabee', 't.me',           6, 1)
ON DUPLICATE KEY UPDATE `slug` = `slug`;

INSERT IGNORE INTO `categories` (`platform_id`, `slug`, `name`, `sort_order`, `is_active`)
SELECT p.`id`, c.`slug`, c.`name`, c.`sort_order`, 1
FROM `platforms` p
CROSS JOIN (
  SELECT 'followers' AS `slug`, 'Followers' AS `name`, 1 AS `sort_order`
  UNION ALL SELECT 'likes', 'Likes', 2
  UNION ALL SELECT 'views', 'Views', 3
) c;

INSERT INTO `payment_methods`
  (`name`, `short_name`, `account_title`, `account_number`, `sort_order`, `is_active`) VALUES
  ('JazzCash',      'JC', 'Your business name', '', 1, 0),
  ('EasyPaisa',     'EP', 'Your business name', '', 2, 0),
  ('Bank Transfer', 'BK', 'Your business name', '', 3, 0)
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `pages` (`slug`, `title`, `content`, `show_in_footer`, `sort_order`, `is_active`) VALUES
  ('about-us',        'About Us',        '<p>Tell your customers who you are.</p>', 1, 1, 1),
  ('contact-us',      'Contact Us',      '<p>How people can reach you.</p>',        1, 2, 1),
  ('privacy-policy',  'Privacy Policy',  '<p>Replace with your privacy policy.</p>',1, 3, 1),
  ('refund-policy',   'Refund Policy',   '<p>Replace with your refund policy.</p>', 1, 4, 1),
  ('terms-of-service','Terms of Service','<p>Replace with your terms.</p>',         1, 5, 1)
ON DUPLICATE KEY UPDATE `slug` = `slug`;

INSERT INTO `faqs` (`question`, `answer`, `sort_order`, `is_active`) VALUES
  ('Do you need my password?',
   'Never. We only need your public profile or post link. Anyone asking for your password is not us.', 1, 1),
  ('How fast does delivery start?',
   'Most orders start within 2-15 minutes of payment confirmation. Larger orders are delivered gradually so growth looks natural.', 2, 1),
  ('Is it safe for my account?',
   'Yes. Delivery is gradual and stays inside normal platform limits.', 3, 1),
  ('What if followers drop?',
   'Services with a refill guarantee are refilled free of charge. Send us your order code on WhatsApp.', 4, 1),
  ('Which payment methods do you accept?',
   'The methods listed on the payment page. After paying you submit the transaction ID and we confirm it.', 5, 1),
  ('Can I order for a private account?',
   'No. Your account must be public while the order is running.', 6, 1)
ON DUPLICATE KEY UPDATE `question` = `question`;
