-- Add 'partner' role to users table
ALTER TABLE `users`
  MODIFY `role` ENUM(
    'admin','manager','operation_manager','accounts',
    'sales_rep','tele_caller','installer','dealer',
    'documentation','partner'
  ) NOT NULL;

-- Track which partner referred each lead
ALTER TABLE `leads`
  ADD COLUMN `partner_id` INT UNSIGNED NULL DEFAULT NULL AFTER `tele_caller_id`,
  ADD KEY `idx_leads_partner` (`partner_id`),
  ADD CONSTRAINT `fk_leads_partner` FOREIGN KEY (`partner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
