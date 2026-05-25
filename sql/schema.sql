-- =====================================================================
-- Wasend CRM - Complete MySQL Schema
-- Production-grade WhatsApp CRM + Cold Outreach OS
-- Target: MySQL 5.7+ / MariaDB 10.4+ (Hostinger compatible)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- USERS (single-tenant, single admin by default; expandable)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','operator') NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `last_login_ip` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- LEADS - core CRM entity
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_name` VARCHAR(255) NOT NULL,
  `address` TEXT DEFAULT NULL,
  `locality` VARCHAR(150) DEFAULT NULL,
  `city` VARCHAR(120) DEFAULT NULL,
  `state` VARCHAR(120) DEFAULT NULL,
  `phone_number` VARCHAR(32) NOT NULL,                         -- E.164 normalized
  `phone_raw` VARCHAR(64) DEFAULT NULL,                        -- original from CSV
  `website_url` VARCHAR(500) DEFAULT NULL,
  `website_status` ENUM('has_website','no_website','unknown') NOT NULL DEFAULT 'unknown',
  `rating` DECIMAL(3,2) DEFAULT NULL,
  `review_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `whatsapp_status` ENUM('pending','valid','invalid','not_on_whatsapp','failed') NOT NULL DEFAULT 'pending',
  `whatsapp_jid` VARCHAR(64) DEFAULT NULL,                     -- e.g. 919876543210@c.us
  `outreach_status` ENUM('pending','queued','sent','delivered','read','replied','failed','skipped','blocked') NOT NULL DEFAULT 'pending',
  `pitch_type` ENUM('A','B','unknown') NOT NULL DEFAULT 'unknown',
  `language_preference` ENUM('hinglish','gujarati_mix','marathi_mix','en_in','auto') NOT NULL DEFAULT 'auto',
  `tags` VARCHAR(500) DEFAULT NULL,                            -- comma separated
  `notes` TEXT DEFAULT NULL,
  `last_contacted_at` DATETIME DEFAULT NULL,
  `last_reply_at` DATETIME DEFAULT NULL,
  `unread_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `source` VARCHAR(64) DEFAULT 'csv_import',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leads_phone` (`phone_number`),
  KEY `idx_leads_outreach` (`whatsapp_status`,`outreach_status`),
  KEY `idx_leads_city_state` (`city`,`state`),
  KEY `idx_leads_pitch` (`pitch_type`),
  KEY `idx_leads_last_contacted` (`last_contacted_at`),
  KEY `idx_leads_last_reply` (`last_reply_at`),
  KEY `idx_leads_pinned` (`is_pinned`),
  FULLTEXT KEY `ft_leads_search` (`business_name`,`address`,`locality`,`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MESSAGES - full conversation timeline
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` INT UNSIGNED NOT NULL,
  `wa_message_id` VARCHAR(128) DEFAULT NULL,                   -- whatsapp-web.js msg id
  `direction` ENUM('outbound','inbound') NOT NULL,
  `sender` ENUM('system','ai','user','lead') NOT NULL,
  `message_text` MEDIUMTEXT NOT NULL,
  `message_type` ENUM('text','image','document','audio','video','other') NOT NULL DEFAULT 'text',
  `status` ENUM('queued','sent','delivered','read','failed') NOT NULL DEFAULT 'queued',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `is_first_outreach` TINYINT(1) NOT NULL DEFAULT 0,
  `error_message` TEXT DEFAULT NULL,
  `meta_json` TEXT DEFAULT NULL,                               -- JSON for extras
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_messages_wa_id` (`wa_message_id`),
  KEY `idx_messages_lead_time` (`lead_id`,`timestamp`),
  KEY `idx_messages_direction` (`direction`),
  KEY `idx_messages_unread` (`lead_id`,`is_read`),
  CONSTRAINT `fk_messages_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CAMPAIGNS - outreach control
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `campaigns`;
CREATE TABLE `campaigns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('draft','running','paused','stopped','completed') NOT NULL DEFAULT 'draft',
  `daily_limit` INT UNSIGNED NOT NULL DEFAULT 80,
  `min_delay_seconds` INT UNSIGNED NOT NULL DEFAULT 120,
  `max_delay_seconds` INT UNSIGNED NOT NULL DEFAULT 300,
  `sent_today` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_sent` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_failed` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_replied` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_sent_at` DATETIME DEFAULT NULL,
  `next_run_at` DATETIME DEFAULT NULL,
  `filters_json` TEXT DEFAULT NULL,                            -- JSON: city, pitch_type, etc.
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaigns_status` (`status`),
  KEY `idx_campaigns_next_run` (`next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CAMPAIGN_QUEUE - per-lead queue items
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `campaign_queue`;
CREATE TABLE `campaign_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT UNSIGNED NOT NULL,
  `lead_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','sent','failed','skipped','blocked') NOT NULL DEFAULT 'pending',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` TEXT DEFAULT NULL,
  `scheduled_at` DATETIME DEFAULT NULL,
  `processed_at` DATETIME DEFAULT NULL,
  `message_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_queue_campaign_lead` (`campaign_id`,`lead_id`),
  KEY `idx_queue_status_sched` (`status`,`scheduled_at`),
  KEY `idx_queue_lead` (`lead_id`),
  CONSTRAINT `fk_queue_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_queue_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SETTINGS - key/value store (encrypted secrets supported via app layer)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `value_type` ENUM('string','int','float','bool','json','secret') NOT NULL DEFAULT 'string',
  `category` VARCHAR(50) NOT NULL DEFAULT 'general',
  `is_secret` TINYINT(1) NOT NULL DEFAULT 0,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`),
  KEY `idx_settings_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- WEBHOOK_LOG - dedup + retry + audit
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `webhook_log`;
CREATE TABLE `webhook_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` VARCHAR(128) DEFAULT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `payload` MEDIUMTEXT DEFAULT NULL,
  `signature_ok` TINYINT(1) NOT NULL DEFAULT 0,
  `processed` TINYINT(1) NOT NULL DEFAULT 0,
  `error_message` TEXT DEFAULT NULL,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_webhook_event_id` (`event_id`),
  KEY `idx_webhook_type` (`event_type`),
  KEY `idx_webhook_received` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ACTIVITY_LOG - audit + system trail
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level` ENUM('debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
  `category` VARCHAR(50) NOT NULL DEFAULT 'system',
  `actor` VARCHAR(100) DEFAULT NULL,
  `action` VARCHAR(150) NOT NULL,
  `target_type` VARCHAR(50) DEFAULT NULL,
  `target_id` VARCHAR(64) DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `meta_json` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_level` (`level`),
  KEY `idx_activity_category` (`category`),
  KEY `idx_activity_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SOCKET_TOKENS - short lived tokens for browser → HF socket auth
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `socket_tokens`;
CREATE TABLE `socket_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token` VARCHAR(128) NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_socket_token` (`token`),
  KEY `idx_socket_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
