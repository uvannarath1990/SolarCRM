-- Track who created and last modified each lead source.
ALTER TABLE `lead_sources`
  ADD COLUMN `created_by` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `created_at`,
  ADD COLUMN `updated_by` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `created_by`,
  ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL AFTER `updated_by`;
