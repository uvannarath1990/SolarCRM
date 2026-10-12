-- Run once to add push notification support

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `endpoint`   TEXT NOT NULL,
    `p256dh`     VARCHAR(512) NOT NULL,
    `auth`       VARCHAR(128) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_endpoint` (`user_id`, `endpoint`(255)),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Track when a reminder push was last sent (prevents duplicate alerts)
ALTER TABLE `reminders` ADD COLUMN IF NOT EXISTS `notified_at` DATETIME NULL DEFAULT NULL;

-- VAPID keys (generated via /push/setup — run once as admin)
INSERT IGNORE INTO `settings` (`key_name`, `value`) VALUES ('vapid_public_key',  '');
INSERT IGNORE INTO `settings` (`key_name`, `value`) VALUES ('vapid_private_key', '');
INSERT IGNORE INTO `settings` (`key_name`, `value`) VALUES ('vapid_subject',     'mailto:admin@kresolar.in');
