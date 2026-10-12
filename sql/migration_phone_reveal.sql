-- Phone reveal flag: sales reps cannot see phone number until admin/manager reveals it
ALTER TABLE `leads`
  ADD COLUMN IF NOT EXISTS `phone_revealed` TINYINT(1) NOT NULL DEFAULT 0
  AFTER `phone`;
