-- Migration: add document checklist columns for Won leads
ALTER TABLE `leads`
  ADD COLUMN `doc_aadhar`            TINYINT(1) NOT NULL DEFAULT 0 AFTER `kseb_completed_date`,
  ADD COLUMN `doc_registered_mobile` TINYINT(1) NOT NULL DEFAULT 0 AFTER `doc_aadhar`,
  ADD COLUMN `doc_kseb_bill`         TINYINT(1) NOT NULL DEFAULT 0 AFTER `doc_registered_mobile`;
