-- Add KSEB registration process fields to leads
ALTER TABLE `leads`
    ADD COLUMN `kseb_process`        ENUM('','Pending','Complete','Blocked') NOT NULL DEFAULT '' AFTER `bank_process`,
    ADD COLUMN `kseb_completed_date` DATE NULL AFTER `kseb_process`;
