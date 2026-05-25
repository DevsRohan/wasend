-- Migration 005: Webhook log table (already in schema.sql; this is a safe upgrade path)
CREATE TABLE IF NOT EXISTS `webhook_log` (
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
