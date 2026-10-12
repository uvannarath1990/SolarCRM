-- ============================================================================
-- Location tracking for field reps (v2)
--
-- Two parts:
--   1. Per-user work hours + an explicit opt-in tracking flag on `users`.
--   2. A `user_locations` table holding the breadcrumb trail.
--
-- Pings outside a user's work window are rejected by the server and never
-- written, so the table only ever contains working-hours positions.
-- ============================================================================

-- ── 1. Per-user work hours ──────────────────────────────────────────────────
-- track_location defaults to 0 on purpose: tracking is switched on per person,
-- never silently inherited by every newly created user.

ALTER TABLE `users`
    ADD COLUMN `work_start`     TIME        NOT NULL DEFAULT '09:00:00' AFTER `manager_id`,
    ADD COLUMN `work_end`       TIME        NOT NULL DEFAULT '19:00:00' AFTER `work_start`,
    ADD COLUMN `work_days`      VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6' AFTER `work_end`,
    ADD COLUMN `track_location` TINYINT(1)  NOT NULL DEFAULT 0 AFTER `work_days`;

-- work_days holds ISO weekday numbers (1 = Monday … 7 = Sunday).


-- ── 2. Location trail ───────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `user_locations` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED     NOT NULL,
    `lat`         DECIMAL(10,7)    NOT NULL,
    `lng`         DECIMAL(10,7)    NOT NULL,
    `accuracy_m`  SMALLINT UNSIGNED DEFAULT NULL,
    `source`      ENUM('heartbeat','checkin','checkout','task','login') NOT NULL DEFAULT 'heartbeat',
    `lead_id`     INT UNSIGNED     DEFAULT NULL,
    `captured_at` DATETIME         NOT NULL,
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_userloc_user_time` (`user_id`, `captured_at`),
    KEY `idx_userloc_time` (`captured_at`),
    CONSTRAINT `fk_userloc_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 3. Settings ─────────────────────────────────────────────────────────────
-- Retention is a setting rather than a constant so it can be changed without
-- a deploy. Purging happens opportunistically on the team map page.

INSERT INTO `settings` (`key_name`, `value`, `label`)
VALUES
    ('location_retention_days', '90', 'Location history retention (days)'),
    ('location_ping_minutes',   '60', 'Location ping interval (minutes)')
ON DUPLICATE KEY UPDATE `key_name` = `key_name`;
