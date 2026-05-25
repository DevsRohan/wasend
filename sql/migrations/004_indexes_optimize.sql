-- Migration 004: Additional indexes for query optimization
ALTER TABLE `leads`
  ADD INDEX IF NOT EXISTS `idx_leads_state` (`state`),
  ADD INDEX IF NOT EXISTS `idx_leads_website` (`website_status`),
  ADD INDEX IF NOT EXISTS `idx_leads_unread` (`unread_count`);

ALTER TABLE `messages`
  ADD INDEX IF NOT EXISTS `idx_messages_status` (`status`),
  ADD INDEX IF NOT EXISTS `idx_messages_first_outreach` (`is_first_outreach`);
