-- Custom roles: admin-created role names that inherit permissions from a base role.
-- users.role always holds the base permission level (e.g. 'sales_rep').
-- users.role_custom_id (nullable) points to the custom role for display purposes.

CREATE TABLE IF NOT EXISTS `roles` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `label`      VARCHAR(100)  NOT NULL,
  `base_role`  VARCHAR(50)   NOT NULL,   -- must be one of the 9 system roles
  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `role_custom_id` INT UNSIGNED NULL DEFAULT NULL
  AFTER `role`;
