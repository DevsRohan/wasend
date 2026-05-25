-- =====================================================================
-- Wasend CRM - Seed Data
-- Default admin user + default settings
-- =====================================================================
-- Default admin login:
--   username: admin
--   password: ChangeMe@123    (CHANGE IMMEDIATELY after first login)
-- The hash below is bcrypt for 'ChangeMe@123' (cost 10).
-- =====================================================================

INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `is_active`)
VALUES ('admin', 'admin@wasend.local',
        '$2y$10$Q1bN5oYxw7g5oA1fvJ5d1.6JzQ0XxZJ6FJ7ZKQqkGqPj0rWpZbk2C',
        'admin', 1)
ON DUPLICATE KEY UPDATE `username` = `username`;

-- ---------------------------------------------------------------------
-- Default settings (categories: connection, ai, campaign, ui, security, branding)
-- ---------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`, `value_type`, `category`, `is_secret`, `description`) VALUES
-- Connection / infra
('node_api_url',          'https://your-space.hf.space',          'string', 'connection', 0, 'Hugging Face Node backend public URL'),
('node_api_key',          '',                                     'secret', 'connection', 1, 'Bearer key shared with Node backend'),
('webhook_secret',        '',                                     'secret', 'connection', 1, 'HMAC secret for webhook verification'),
('socket_url',            'https://your-space.hf.space',          'string', 'connection', 0, 'Socket.io endpoint (usually same as Node URL)'),
('public_app_url',        'https://yourdomain.com',               'string', 'connection', 0, 'Public PHP dashboard URL'),

-- AI
('groq_api_key',          '',                                     'secret', 'ai',         1, 'Groq API key (gsk_...)'),
('groq_model',            'llama-3.3-70b-versatile',              'string', 'ai',         0, 'Groq primary model'),
('groq_fallback_model',   'llama-3.1-8b-instant',                 'string', 'ai',         0, 'Groq fallback model'),
('groq_max_tokens',       '700',                                  'int',    'ai',         0, 'Max tokens per AI generation'),
('groq_temperature',      '0.75',                                 'float',  'ai',         0, 'Temperature for variation'),

-- Campaign
('daily_send_limit',      '80',                                   'int',    'campaign',   0, 'Max first-outreach messages per day'),
('min_delay_seconds',     '120',                                  'int',    'campaign',   0, 'Min delay between sends (seconds)'),
('max_delay_seconds',     '300',                                  'int',    'campaign',   0, 'Max delay between sends (seconds)'),
('working_hours_start',   '10',                                   'int',    'campaign',   0, 'Start hour (0-23) for sending'),
('working_hours_end',     '20',                                   'int',    'campaign',   0, 'End hour (0-23) for sending'),
('campaign_enabled',      '1',                                    'bool',   'campaign',   0, 'Master toggle for automation'),
('retry_max_attempts',    '3',                                    'int',    'campaign',   0, 'Retry attempts for failed sends'),
('skip_replied_leads',    '1',                                    'bool',   'campaign',   0, 'Never re-send to replied leads'),

-- UI
('app_theme',             'light',                                'string', 'ui',         0, 'Theme: light (default white+green)'),
('notification_sound',    '1',                                    'bool',   'ui',         0, 'Play sound on new message'),
('default_language',      'hinglish',                             'string', 'ui',         0, 'Default AI message language'),
('items_per_page',        '50',                                   'int',    'ui',         0, 'Lead list pagination'),

-- Security
('webhook_ip_whitelist',  '',                                     'string', 'security',   0, 'Comma-separated IPs (optional)'),
('session_timeout_min',   '480',                                  'int',    'security',   0, 'Session timeout in minutes'),
('csrf_protection',       '1',                                    'bool',   'security',   0, 'Enable CSRF tokens'),
('logging_enabled',       '1',                                    'bool',   'security',   0, 'Enable activity logging'),

-- Branding
('brand_name',            'Wasend',                               'string', 'branding',   0, 'Brand name shown in UI'),
('brand_tagline',         'WhatsApp CRM + Cold Outreach OS',      'string', 'branding',   0, 'Tagline'),
('owner_name',            'Your Agency',                          'string', 'branding',   0, 'Sender / owner name in AI prompts'),
('owner_services',        'Landing Pages,Business Websites,eCommerce Websites,Custom Web Apps,AI Agents,Automation Systems,Android Apps,Chrome Extensions,Digital Marketing', 'string', 'branding', 0, 'Comma-separated services list'),

-- Feature flags
('feature_csv_upload',    '1',                                    'bool',   'features',   0, 'Enable CSV upload UI'),
('feature_ai_preview',    '1',                                    'bool',   'features',   0, 'Allow AI message preview'),
('feature_manual_send',   '1',                                    'bool',   'features',   0, 'Allow manual chat sending'),
('feature_export',        '1',                                    'bool',   'features',   0, 'Allow lead export')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- ---------------------------------------------------------------------
-- Default campaign row
-- ---------------------------------------------------------------------
INSERT INTO `campaigns` (`name`, `description`, `status`, `daily_limit`, `min_delay_seconds`, `max_delay_seconds`)
VALUES ('Default Outreach', 'Auto-created default campaign for first-outreach automation', 'paused', 80, 120, 300)
ON DUPLICATE KEY UPDATE `name` = `name`;
