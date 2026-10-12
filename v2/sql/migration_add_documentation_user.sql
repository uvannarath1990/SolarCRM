ALTER TABLE `leads`
  MODIFY COLUMN `documentation_user_id` INT UNSIGNED NULL;

ALTER TABLE `leads`
  ADD INDEX IF NOT EXISTS `idx_leads_doc_user` (`documentation_user_id`);

ALTER TABLE `leads`
  ADD CONSTRAINT `fk_leads_doc_user`
  FOREIGN KEY (`documentation_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
