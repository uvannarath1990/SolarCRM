-- Add bank_process status field to leads
ALTER TABLE `leads`
    ADD COLUMN `bank_process` ENUM('','Pending','Complete','Blocked') NOT NULL DEFAULT '' AFTER `won_value`;
