-- Add installation_date field to leads
ALTER TABLE `leads`
    ADD COLUMN `installation_date` DATE NULL AFTER `kseb_completed_date`;
