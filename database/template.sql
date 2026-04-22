-- =============================================================================
--  Template — Database Seed File
--  Generated: 2026-04-04
-- =============================================================================
--
--  HOW TO USE
--  ----------
--  1. Create a database in MySQL/MariaDB, e.g.:
--       CREATE DATABASE your_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--  2. Update Template/.env with your DB credentials.
--  3. Import this file:
--       mysql -u root -p your_db_name < template.sql
--
--  DEFAULT LOGIN CREDENTIALS
--  -------------------------
--  Role          Email                    Password
--  ----------    ---------------------    --------------
--  Super Admin   admin@example.com        Admin@1234
--  Manager       manager@example.com      Manager@1234
--  Staff         staff@example.com        Staff@1234
--
--  IMPORTANT: Change all passwords immediately after first login.
-- =============================================================================
CREATE DATABASE template;
use template;
SET SQL_MODE   = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone  = '+08:00';
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
--  DROP (dependency order: children first)
-- =============================================================================

DROP TABLE IF EXISTS `role_page_permissions`;
DROP TABLE IF EXISTS `audit_trail`;
DROP TABLE IF EXISTS `mailer_log`;
DROP TABLE IF EXISTS `mailer_queue`;
DROP TABLE IF EXISTS `mailer_account`;
DROP TABLE IF EXISTS `set_settings`;
DROP TABLE IF EXISTS `permission_types`;
DROP TABLE IF EXISTS `pages`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- =============================================================================
--  ROLES
-- =============================================================================

CREATE TABLE `roles` (
  `id`           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(50)       NOT NULL COMMENT 'Lowercase identifier, e.g. superadmin',
  `display_name` VARCHAR(100)      NOT NULL,
  `description`  TEXT              DEFAULT NULL,
  `is_superadmin` TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 = bypasses all permission checks',
  `hidden`       TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'Soft-delete flag',
  `created_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `is_superadmin`) VALUES
  (1, 'superadmin', 'Super Administrator', 'Full system access — bypasses all permission checks.',     1),
  (2, 'manager',    'Manager',             'Can view, create, and edit all pages. Cannot delete.',     0),
  (3, 'staff',      'Staff',               'Read-only access to dashboard, accounts, and audit trail.',0);

-- =============================================================================
--  USERS
-- =============================================================================

CREATE TABLE `users` (
  `id`                    INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `first_name`            VARCHAR(100)   NOT NULL,
  `middle_name`           VARCHAR(100)   DEFAULT NULL,
  `last_name`             VARCHAR(100)   NOT NULL,
  `display_name`          VARCHAR(200)   DEFAULT NULL,
  `email`                 VARCHAR(191)   NOT NULL,
  `phone`                 VARCHAR(30)    DEFAULT NULL,
  `password`              VARCHAR(255)   NOT NULL,
  `role_id`               INT UNSIGNED   NOT NULL DEFAULT 3,
  `status`                ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'active',
  `is_active`             TINYINT(1)     NOT NULL DEFAULT 1,
  `email_verified`        TINYINT(1)     NOT NULL DEFAULT 0,
  `failed_login_attempts` TINYINT        NOT NULL DEFAULT 0,
  `locked_until`          DATETIME       DEFAULT NULL,
  `last_login_at`         DATETIME       DEFAULT NULL,
  `hidden`                TINYINT(1)     NOT NULL DEFAULT 0,
  `deleted_at`            DATETIME       DEFAULT NULL,
  `created_at`            DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role`   (`role_id`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_hidden` (`hidden`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Passwords (bcrypt cost 12):
--   admin@example.com   → Admin@1234
--   manager@example.com → Manager@1234
--   staff@example.com   → Staff@1234
INSERT INTO `users`
  (`id`, `first_name`, `last_name`, `display_name`, `email`, `password`, `role_id`, `status`, `is_active`, `email_verified`)
VALUES
  (1, 'System', 'Administrator', 'System Administrator', 'admin@example.com',
      '$2y$12$4R08dslAsVKBFiTCF1Rnze3QZnT6SbZNaNjZLfswKNF9GCQQwTBBS', 1, 'active', 1, 1),
  (2, 'John', 'Manager', 'John Manager', 'manager@example.com',
      '$2y$12$tIs57JWwPEKm1hCfKlcFzejQsJLJVCL1cY7gU0W4QbMdF/nob6.qC', 2, 'active', 1, 1),
  (3, 'Jane', 'Staff', 'Jane Staff', 'staff@example.com',
      '$2y$12$ptpedUfXq9bRz52pEP7BxON69QXL28ow0ndpzeZz31XnGKSNnI4DS', 3, 'active', 1, 1);

-- =============================================================================
--  PERMISSION TYPES
-- =============================================================================

CREATE TABLE `permission_types` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(50)   NOT NULL,
  `display_name` VARCHAR(100)  NOT NULL,
  `sort_order`   INT           NOT NULL DEFAULT 0,
  `hidden`       TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permission_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permission_types` (`id`, `name`, `display_name`, `sort_order`) VALUES
  (1, 'view',   'View',   1),
  (2, 'create', 'Create', 2),
  (3, 'edit',   'Edit',   3),
  (4, 'delete', 'Delete', 4);

-- =============================================================================
--  PAGES  (hierarchical via parent_id)
-- =============================================================================

CREATE TABLE `pages` (
  `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `parent_id`    INT UNSIGNED   DEFAULT NULL COMMENT 'NULL = top-level',
  `name`         VARCHAR(100)   NOT NULL COMMENT 'Lowercase identifier used in permission checks',
  `display_name` VARCHAR(150)   NOT NULL,
  `description`  TEXT           DEFAULT NULL,
  `route`        VARCHAR(255)   DEFAULT NULL COMMENT 'PHP filename, e.g. account_management.php',
  `icon`         VARCHAR(100)   NOT NULL DEFAULT 'fas fa-file',
  `sort_order`   INT            NOT NULL DEFAULT 0,
  `is_menu_item` TINYINT(1)     NOT NULL DEFAULT 1 COMMENT '0 = grouping label only',
  `hidden`       TINYINT(1)     NOT NULL DEFAULT 0,
  `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_name` (`name`),
  KEY `idx_pages_parent` (`parent_id`),
  CONSTRAINT `fk_pages_parent` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pages`
  (`id`, `parent_id`, `name`, `display_name`, `description`, `route`, `icon`, `sort_order`, `is_menu_item`)
VALUES
  -- Top-level
  (1,  NULL, 'dashboard',             'Dashboard',             'Main system overview.',                              'index.php',                'fas fa-tachometer-alt',  10, 1),
  -- System group (parent, no route)
  (2,  NULL, 'system',                'System',                'User and access management.',                        NULL,                       'fas fa-cogs',            20, 0),
  (3,  2,    'account_management',    'Account Management',    'Manage user accounts.',                              'account_management.php',   'fas fa-users',           21, 1),
  (4,  2,    'role_management',       'Role Management',       'Manage roles.',                                      'role_management.php',      'fas fa-user-shield',     22, 1),
  (5,  2,    'permission_management', 'Permission Management', 'Assign permissions to roles.',                       'permission_management.php','fas fa-key',             23, 1),
  (6,  2,    'page_management',       'Page Management',       'Manage navigable pages and routes.',                 'page_management.php',      'fas fa-file-alt',        24, 1),
  -- Settings group (parent, no route)
  (7,  NULL, 'settings',              'Settings',              'System configuration.',                              NULL,                       'fas fa-sliders-h',       30, 0),
  (8,  7,    'settings_web',          'Website',               'Website branding and display settings.',             'settings_web.php',         'fas fa-globe',           31, 1),
  (9,  7,    'mailer_management',     'Mailer',                'SMTP accounts, mail queue, and delivery logs.',      'mailer_management.php',    'fas fa-envelope',        32, 1),
  -- Standalone
  (10, NULL, 'audit_trail',           'Audit Trail',           'Immutable log of all system actions.',               'audit_trail.php',          'fas fa-history',         40, 1);

-- =============================================================================
--  ROLE PAGE PERMISSIONS
--  Superadmin (role 1) bypasses this table — no rows needed.
--  Manager (role 2): view + create + edit on all pages.
--  Staff (role 3): view-only on dashboard, accounts, audit trail, mailer.
-- =============================================================================

CREATE TABLE `role_page_permissions` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id`            INT UNSIGNED NOT NULL,
  `page_id`            INT UNSIGNED NOT NULL,
  `permission_type_id` INT UNSIGNED NOT NULL,
  `granted`            TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rpp` (`role_id`, `page_id`, `permission_type_id`),
  CONSTRAINT `fk_rpp_role` FOREIGN KEY (`role_id`)            REFERENCES `roles`            (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rpp_page` FOREIGN KEY (`page_id`)            REFERENCES `pages`            (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rpp_perm` FOREIGN KEY (`permission_type_id`) REFERENCES `permission_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Manager: view (1) + create (2) + edit (3) on all 10 pages ─────────────────
INSERT INTO `role_page_permissions` (`role_id`, `page_id`, `permission_type_id`, `granted`)
SELECT 2, p.id, pt.id, 1
FROM `pages` p
CROSS JOIN `permission_types` pt
WHERE pt.name IN ('view', 'create', 'edit');

-- ── Staff: view-only on dashboard, account_management, audit_trail, mailer ────
INSERT INTO `role_page_permissions` (`role_id`, `page_id`, `permission_type_id`, `granted`)
SELECT 3, p.id, 1, 1   -- permission_type_id 1 = view
FROM `pages` p
WHERE p.name IN ('dashboard', 'account_management', 'audit_trail', 'mailer_management');

-- =============================================================================
--  SITE SETTINGS
-- =============================================================================

CREATE TABLE `set_settings` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100)  NOT NULL,
  `setting_value` LONGTEXT      DEFAULT NULL,
  `setting_type`  VARCHAR(20)   NOT NULL DEFAULT 'string' COMMENT 'string|int|bool|json|float',
  `setting_group` VARCHAR(50)   NOT NULL DEFAULT 'general',
  `setting_label` VARCHAR(150)  NOT NULL DEFAULT '',
  `description`   TEXT          DEFAULT NULL,
  `hidden`        TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `set_settings` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `setting_label`, `description`) VALUES
  ('site_name',    'My Application',         'string', 'web', 'Site Name',    'Application name shown in the browser title and header.'),
  ('site_tagline', 'Powered by Template',    'string', 'web', 'Site Tagline', 'Short tagline shown below the site name on the login page.'),
  ('site_logo',    '',                       'string', 'web', 'Site Logo',    'Path to logo image (relative to public/).'),
  ('site_favicon', '',                       'string', 'web', 'Site Favicon', 'Path to favicon (relative to public/).'),
  ('site_logo_dark', '',                     'string', 'web', 'Dark Logo',    'Alternative logo for dark backgrounds.'),
  ('primary_color','#0d6efd',               'string', 'web', 'Primary Color','Bootstrap primary accent colour (hex).'),
  ('footer_text',  '© 2025 My Application', 'string', 'web', 'Footer Text',  'Text displayed in the page footer.');

-- =============================================================================
--  AUDIT TRAIL  (immutable INSERT-only log)
-- =============================================================================

CREATE TABLE `audit_trail` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED   DEFAULT NULL,
  `user_name`      VARCHAR(200)   DEFAULT NULL,
  `user_email`     VARCHAR(191)   DEFAULT NULL,
  `role_id`        INT UNSIGNED   DEFAULT NULL,
  `action`         VARCHAR(50)    NOT NULL COMMENT 'create|edit|delete|login|logout|view|export',
  `entity_type`    VARCHAR(100)   NOT NULL,
  `entity_id`      VARCHAR(191)   DEFAULT NULL,
  `old_value`      JSON           DEFAULT NULL,
  `new_value`      JSON           DEFAULT NULL,
  `description`    TEXT           DEFAULT NULL,
  `ip_address`     VARCHAR(45)    DEFAULT NULL,
  `user_agent`     TEXT           DEFAULT NULL,
  `request_uri`    VARCHAR(500)   DEFAULT NULL,
  `request_method` VARCHAR(10)    DEFAULT NULL,
  `row_hash`       VARCHAR(64)    DEFAULT NULL COMMENT 'SHA-256 tamper-detection hash',
  `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user`   (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_entity` (`entity_type`),
  KEY `idx_audit_date`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  MAILER ACCOUNTS
-- =============================================================================

CREATE TABLE `mailer_account` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(100)  NOT NULL,
  `smtp_host`       VARCHAR(255)  NOT NULL,
  `smtp_port`       SMALLINT      NOT NULL DEFAULT 587,
  `smtp_encryption` ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
  `smtp_username`   VARCHAR(255)  NOT NULL,
  `smtp_password`   VARCHAR(500)  NOT NULL COMMENT 'Stored encrypted or as plaintext — encrypt in production',
  `from_email`      VARCHAR(191)  NOT NULL,
  `from_name`       VARCHAR(100)  NOT NULL,
  `reply_to_email`  VARCHAR(191)  DEFAULT NULL,
  `reply_to_name`   VARCHAR(100)  DEFAULT NULL,
  `daily_limit`     INT           NOT NULL DEFAULT 500,
  `hourly_limit`    INT           NOT NULL DEFAULT 50,
  `throttle_ms`     INT           NOT NULL DEFAULT 1000 COMMENT 'Milliseconds between sends',
  `priority`        TINYINT       NOT NULL DEFAULT 10 COMMENT 'Lower = higher priority',
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `sent_today`      INT           NOT NULL DEFAULT 0,
  `sent_this_hour`  INT           NOT NULL DEFAULT 0,
  `hour_reset_at`   DATETIME      DEFAULT NULL,
  `day_reset_at`    DATETIME      DEFAULT NULL,
  `hidden`          TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  MAIL QUEUE
-- =============================================================================

CREATE TABLE `mailer_queue` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `to_email`          VARCHAR(191)  NOT NULL,
  `to_name`           VARCHAR(200)  DEFAULT NULL,
  `subject`           VARCHAR(500)  NOT NULL,
  `body_html`         LONGTEXT      NOT NULL,
  `body_text`         LONGTEXT      DEFAULT NULL,
  `cc`                JSON          DEFAULT NULL,
  `bcc`               JSON          DEFAULT NULL,
  `reply_to_email`    VARCHAR(191)  DEFAULT NULL,
  `reply_to_name`     VARCHAR(100)  DEFAULT NULL,
  `attachments`       JSON          DEFAULT NULL,
  `priority`          TINYINT       NOT NULL DEFAULT 3 COMMENT '1 = highest',
  `status`            ENUM('queued','processing','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
  `attempts`          TINYINT       NOT NULL DEFAULT 0,
  `max_attempts`      TINYINT       NOT NULL DEFAULT 3,
  `mailer_account_id` INT UNSIGNED  DEFAULT NULL,
  `next_attempt_at`   DATETIME      DEFAULT NULL,
  `locked_at`         DATETIME      DEFAULT NULL,
  `locked_by`         VARCHAR(100)  DEFAULT NULL,
  `last_error`        TEXT          DEFAULT NULL,
  `sent_at`           DATETIME      DEFAULT NULL,
  `context`           JSON          DEFAULT NULL,
  `created_by`        INT UNSIGNED  DEFAULT NULL,
  `hidden`            TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mq_status`    (`status`),
  KEY `idx_mq_priority`  (`priority`),
  KEY `idx_mq_next`      (`next_attempt_at`),
  CONSTRAINT `fk_mq_account` FOREIGN KEY (`mailer_account_id`) REFERENCES `mailer_account` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  MAILER LOG  (immutable delivery log)
-- =============================================================================

CREATE TABLE `mailer_log` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `queue_id`          INT UNSIGNED  DEFAULT NULL,
  `mailer_account_id` INT UNSIGNED  DEFAULT NULL,
  `to_email`          VARCHAR(191)  NOT NULL,
  `subject`           VARCHAR(500)  NOT NULL,
  `status`            VARCHAR(20)   NOT NULL COMMENT 'sent|failed',
  `smtp_response`     TEXT          DEFAULT NULL,
  `error_message`     TEXT          DEFAULT NULL,
  `duration_ms`       INT           DEFAULT NULL,
  `sent_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ml_status`  (`status`),
  KEY `idx_ml_sent_at` (`sent_at`),
  CONSTRAINT `fk_ml_queue`   FOREIGN KEY (`queue_id`)          REFERENCES `mailer_queue`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ml_account` FOREIGN KEY (`mailer_account_id`) REFERENCES `mailer_account` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
--  DEFAULT CREDENTIALS (remove this block before deploying to production)
-- =============================================================================
--
--  admin@example.com    →  Admin@1234     (Super Administrator)
--  manager@example.com  →  Manager@1234   (Manager)
--  staff@example.com    →  Staff@1234     (Staff)
--
-- =============================================================================
