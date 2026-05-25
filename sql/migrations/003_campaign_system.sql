-- Migration 003: Campaign system tables (already in schema.sql; safe re-run guards)
ALTER TABLE `campaigns`
  ADD COLUMN IF NOT EXISTS `next_run_at` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `total_replied` INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `campaign_queue`
  ADD COLUMN IF NOT EXISTS `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `last_error` TEXT DEFAULT NULL;
