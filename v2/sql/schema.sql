-- ============================================================
-- Solar CRM Phase 1 Schema
-- MySQL 8 / MariaDB  |  utf8mb4_unicode_ci
-- Import via phpMyAdmin or: mysql -u USER -p DB_NAME < schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────
-- 1. USERS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(120)     NOT NULL,
    `email`           VARCHAR(180)     NOT NULL,
    `phone`           VARCHAR(20)      DEFAULT NULL,
    `password_hash`   VARCHAR(255)     NOT NULL,
    `role`            ENUM('admin','manager','operation_manager','accounts','sales_rep','tele_caller','installer','dealer','documentation') NOT NULL,
    `is_active`       TINYINT(1)       NOT NULL DEFAULT 1,
    `manager_id`      INT UNSIGNED     DEFAULT NULL,
    `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`    DATETIME         DEFAULT NULL,
    `last_login`      DATETIME         DEFAULT NULL,
    `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_manager` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 2. LEAD SOURCES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lead_sources` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(80)  NOT NULL,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order` TINYINT      NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lead_sources_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 3. LEADS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `leads` (
    `id`                   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `source_id`            INT UNSIGNED    DEFAULT NULL,
    `name`                 VARCHAR(150)    NOT NULL,
    `phone`                VARCHAR(20)     NOT NULL,
    `email`                VARCHAR(180)    DEFAULT NULL,
    `location`             VARCHAR(255)    DEFAULT NULL,
    `bill_amount`          DECIMAL(10,2)   DEFAULT NULL COMMENT 'Monthly electricity bill INR',
    `potential_kw`         DECIMAL(6,2)    DEFAULT NULL COMMENT 'Estimated system size kW',
    `stage`                ENUM(
                               'New',
                               'Contacted',
                               'Visit Scheduled',
                               'Site Visit in Progress',
                               'Site Visit Completed',
                               'Quoted',
                               'Won',
                               'Lost',
                               'On Hold'
                           ) NOT NULL DEFAULT 'New',
    `assigned_rep_id`      INT UNSIGNED    DEFAULT NULL,
    `tele_caller_id`       INT UNSIGNED    DEFAULT NULL,
    `created_by`           INT UNSIGNED    NOT NULL,
    `next_followup_date`   DATE            DEFAULT NULL,
    `visit_scheduled_date` DATE            DEFAULT NULL,
    `status_reason_code`   VARCHAR(80)     DEFAULT NULL COMMENT 'Dropdown selection',
    `status_reason_notes`  TEXT            DEFAULT NULL COMMENT 'Free-text supplement',
    `quote_amount`         DECIMAL(12,2)   DEFAULT NULL,
    `won_value`            DECIMAL(12,2)   DEFAULT NULL,
    `lost_reason`          VARCHAR(200)    DEFAULT NULL,
    `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`           DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_leads_stage`         (`stage`),
    KEY `idx_leads_rep`           (`assigned_rep_id`),
    KEY `idx_leads_followup`      (`next_followup_date`),
    KEY `idx_leads_visit_sched`   (`visit_scheduled_date`),
    KEY `idx_leads_created_by`    (`created_by`),
    UNIQUE KEY `uq_leads_phone`        (`phone`),
    KEY `idx_leads_deleted`            (`deleted_at`),
    KEY `idx_leads_tele_caller`        (`tele_caller_id`),
    CONSTRAINT `fk_leads_source`       FOREIGN KEY (`source_id`)       REFERENCES `lead_sources` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_leads_rep`          FOREIGN KEY (`assigned_rep_id`) REFERENCES `users` (`id`)        ON DELETE SET NULL,
    CONSTRAINT `fk_leads_tele_caller`  FOREIGN KEY (`tele_caller_id`)  REFERENCES `users` (`id`)        ON DELETE SET NULL,
    CONSTRAINT `fk_leads_creator`      FOREIGN KEY (`created_by`)      REFERENCES `users` (`id`)        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 4. ACTIVITIES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `activities` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`     INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `type`        ENUM('call','email','meeting','note','stage_change','quote','other') NOT NULL DEFAULT 'note',
    `notes`       TEXT         DEFAULT NULL,
    `occurred_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activities_lead` (`lead_id`, `occurred_at`),
    CONSTRAINT `fk_activities_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_activities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 5. REMINDERS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `reminders` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `remind_at`  DATETIME     NOT NULL,
    `message`    VARCHAR(255) NOT NULL,
    `is_done`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_reminders_due` (`remind_at`, `is_done`),
    KEY `idx_reminders_user` (`user_id`, `is_done`),
    CONSTRAINT `fk_reminders_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reminders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 6. SITE VISITS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `site_visits` (
    `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `lead_id`             INT UNSIGNED    NOT NULL,
    `rep_id`              INT UNSIGNED    NOT NULL,
    `checkin_at`          DATETIME        DEFAULT NULL,
    `checkout_at`         DATETIME        DEFAULT NULL,
    `duration_secs`       INT UNSIGNED    DEFAULT NULL,
    `checkin_lat`         DECIMAL(10,7)   DEFAULT NULL,
    `checkin_lng`         DECIMAL(10,7)   DEFAULT NULL,
    `checkin_accuracy_m`  DECIMAL(8,2)    DEFAULT NULL,
    `checkout_lat`        DECIMAL(10,7)   DEFAULT NULL,
    `checkout_lng`        DECIMAL(10,7)   DEFAULT NULL,
    `checkout_accuracy_m` DECIMAL(8,2)    DEFAULT NULL,
    `location_provided`   TINYINT(1)      NOT NULL DEFAULT 1,
    `voice_notes`         TEXT            DEFAULT NULL,
    `roof_type`           VARCHAR(60)     DEFAULT NULL,
    `roof_area_sqft`      DECIMAL(8,2)    DEFAULT NULL,
    `shading_notes`       TEXT            DEFAULT NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_site_visits_lead` (`lead_id`),
    KEY `idx_site_visits_rep`  (`rep_id`, `checkin_at`),
    CONSTRAINT `fk_sv_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sv_rep`  FOREIGN KEY (`rep_id`)  REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 7. INSTALLATIONS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `installations` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`        INT UNSIGNED NOT NULL,
    `installer_id`   INT UNSIGNED DEFAULT NULL,
    `scheduled_date` DATE         DEFAULT NULL,
    `completed_date` DATE         DEFAULT NULL,
    `system_kw`      DECIMAL(6,2) DEFAULT NULL,
    `panel_count`    SMALLINT     DEFAULT NULL,
    `inverter_model` VARCHAR(100) DEFAULT NULL,
    `notes`          TEXT         DEFAULT NULL,
    `status`         ENUM('pending','scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_installations_lead` (`lead_id`),
    KEY `idx_installations_installer` (`installer_id`),
    CONSTRAINT `fk_inst_lead`      FOREIGN KEY (`lead_id`)      REFERENCES `leads` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_inst_installer` FOREIGN KEY (`installer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 8. AUDIT LOG  (app DB user: SELECT + INSERT only — no UPDATE/DELETE)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`    INT UNSIGNED    DEFAULT NULL,
    `user_id`    INT UNSIGNED    DEFAULT NULL,
    `action`     VARCHAR(40)     NOT NULL,
    `entity`     VARCHAR(40)     DEFAULT NULL,
    `field_name` VARCHAR(80)     DEFAULT NULL,
    `old_value`  TEXT            DEFAULT NULL,
    `new_value`  TEXT            DEFAULT NULL,
    `ip_address` VARCHAR(45)     DEFAULT NULL,
    `user_agent` VARCHAR(255)    DEFAULT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_al_lead`    (`lead_id`, `created_at`),
    KEY `idx_al_user`    (`user_id`, `created_at`),
    KEY `idx_al_created` (`created_at`)
    -- NOTE: Grant only SELECT, INSERT to the app DB user on this table.
    -- Never grant UPDATE or DELETE. Enforced at DB user-privilege level.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 9. SETTINGS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key_name`   VARCHAR(80)  NOT NULL,
    `value`      TEXT         DEFAULT NULL,
    `label`      VARCHAR(120) DEFAULT NULL,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 10. TEAMS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `teams` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `leader_id`  INT UNSIGNED NOT NULL,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_teams_leader` FOREIGN KEY (`leader_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 11. TEAM MEMBERS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `team_members` (
    `team_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`team_id`, `user_id`),
    CONSTRAINT `fk_tm_team` FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tm_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 12. OPERATIONS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `operations` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`         INT UNSIGNED NOT NULL,
    `team_id`         INT UNSIGNED DEFAULT NULL,
    `operation_type`  VARCHAR(60)  NOT NULL DEFAULT 'Cleaning',
    `scheduled_at`    DATE         NOT NULL,
    `done_at`         DATE         DEFAULT NULL COMMENT 'NULL = pending/overdue',
    `interval_days`   SMALLINT     NOT NULL DEFAULT 120,
    `notes`           TEXT         DEFAULT NULL,
    `performed_by_id` INT UNSIGNED DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ops_lead`      (`lead_id`),
    KEY `idx_ops_scheduled` (`scheduled_at`),
    CONSTRAINT `fk_ops_lead` FOREIGN KEY (`lead_id`)         REFERENCES `leads`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ops_user` FOREIGN KEY (`performed_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ops_team` FOREIGN KEY (`team_id`)         REFERENCES `teams`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 13. LEAD FINANCIALS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lead_financials` (
    `lead_id`            INT UNSIGNED  NOT NULL,
    `total_amount`       DECIMAL(12,2) NOT NULL DEFAULT 0,
    `discount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
    `payment_status`     VARCHAR(100)  DEFAULT NULL,
    `feasibility_status` VARCHAR(100)  DEFAULT NULL,
    `structure_type`     VARCHAR(100)  DEFAULT NULL,
    `equipment_brand`    VARCHAR(100)  DEFAULT NULL,
    `notes`              TEXT          DEFAULT NULL,
    `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`lead_id`),
    CONSTRAINT `fk_fin_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 14. LEAD PAYMENT INSTALLMENTS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lead_payment_installments` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `lead_id`          INT UNSIGNED  NOT NULL,
    `installment_type` ENUM('advance','installation','commissioning','other') NOT NULL DEFAULT 'advance',
    `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
    `received_date`    DATE          NOT NULL,
    `cheque_status`    ENUM('none','received','cleared','bounced') NOT NULL DEFAULT 'none',
    `cheque_amount`    DECIMAL(12,2) DEFAULT NULL,
    `notes`            VARCHAR(255)  DEFAULT NULL,
    `created_by`       INT UNSIGNED  DEFAULT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pi_lead` (`lead_id`),
    CONSTRAINT `fk_pi_lead` FOREIGN KEY (`lead_id`)    REFERENCES `leads`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pi_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 15. WORKFLOW PIPELINE TASKS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `workflow_tasks` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `lead_id`       INT UNSIGNED  NOT NULL,
    `stage`         VARCHAR(50)   NOT NULL,
    `title`         VARCHAR(200)  NOT NULL,
    `assigned_to`   INT UNSIGNED  DEFAULT NULL,
    `assigned_role` VARCHAR(30)   DEFAULT NULL,
    `due_date`      DATE          DEFAULT NULL,
    `status`        ENUM('pending','done','skipped') NOT NULL DEFAULT 'pending',
    `completed_by`  INT UNSIGNED  DEFAULT NULL,
    `completed_at`  DATETIME      DEFAULT NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wt_lead_stage` (`lead_id`, `stage`),
    KEY `idx_wt_assigned`   (`assigned_to`, `status`),
    CONSTRAINT `fk_wt_lead`    FOREIGN KEY (`lead_id`)     REFERENCES `leads`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wt_user`    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_wt_done_by` FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════
-- SEED DATA
-- ═══════════════════════════════════════════════════════════════

-- Default settings
INSERT INTO `settings` (`key_name`, `value`, `label`) VALUES
    ('company_name',       'Solar Co.',           'Company Name'),
    ('company_phone',      '',                    'Company Phone'),
    ('company_email',      '',                    'Company Email'),
    ('company_address',    '',                    'Company Address'),
    ('company_logo',       '',                    'Company Logo URL'),
    ('bill_to_kw_divisor', '1200',                'Monthly Bill to kW Divisor'),
    ('currency_symbol',    '₹',                   'Currency Symbol'),
    ('status_reasons',     'Price too high|Competitor chosen|New home — not ready|Subsidy pending|No response|Other', 'Status Reasons (pipe-separated)'),
    ('lost_reasons',       'Price too high|Competitor chosen|Not interested|Technical constraint|No response after follow-ups|Other', 'Lost Reasons (pipe-separated)'),
    ('gps_radius_meters',  '500',                 'GPS Visit Radius (metres)')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- Lead sources
INSERT INTO `lead_sources` (`name`, `sort_order`) VALUES
    ('MOACAP21',                1),
    ('Company Old Lead',        2),
    ('Company Old Lead (Ref)',   3),
    ('Referral',                4),
    ('Online Ad',               5),
    ('Social Media',            6),
    ('Walk-in',                 7),
    ('Exhibition',              8),
    ('Cold Call',               9),
    ('Other',                  10)
ON DUPLICATE KEY UPDATE `sort_order` = VALUES(`sort_order`);

-- Default admin account
-- Password: Admin@1234  (CHANGE IMMEDIATELY after first login)
-- Hash generated with: password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12])
INSERT INTO `users` (`name`, `email`, `role`, `password_hash`, `is_active`) VALUES
    ('Admin', 'admin@solarcrm.local', 'admin',
     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)
ON DUPLICATE KEY UPDATE `id` = `id`;
-- Note: the hash above is bcrypt of 'password' for dev only.
-- Replace with a proper hash before production deployment.
