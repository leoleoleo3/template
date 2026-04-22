-- =============================================================================
-- Migration: UI Refresh Settings
-- Date:      2026-04-22
-- Adds six new `web`-group keys to `set_settings` for the design-system refresh.
--   sidenav_color / topbar_color   -> separate chrome color pickers
--   login_hero_enabled / *_color_* -> login page gradient control
--   dark_mode_enabled              -> tenant default for dark mode
--
-- Idempotent: safe to run multiple times. Existing rows are left unchanged.
-- =============================================================================

INSERT INTO `set_settings` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `setting_label`, `description`) VALUES
  ('sidenav_color',          '#212529', 'string', 'web', 'Sidebar Background',    'Hex color for the left sidenav background.'),
  ('topbar_color',           '#212529', 'string', 'web', 'Topbar Background',     'Hex color for the top navigation bar background.'),
  ('login_hero_enabled',     '1',       'bool',   'web', 'Login Hero Background', 'Show the gradient hero behind the login card. Disable for a flat background.'),
  ('login_hero_color_start', '',        'string', 'web', 'Login Gradient Start',  'Optional override for the login gradient start color. Falls back to the primary color.'),
  ('login_hero_color_end',   '',        'string', 'web', 'Login Gradient End',    'Optional override for the login gradient end color. Falls back to a darker primary color.'),
  ('dark_mode_enabled',      '0',       'bool',   'web', 'Dark Mode (Default)',   'Tenant default theme. Users can override per-device via the topbar toggle.')
ON DUPLICATE KEY UPDATE
  `setting_value` = `set_settings`.`setting_value`;
