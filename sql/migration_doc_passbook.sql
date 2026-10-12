-- Fourth document in the checklist: bank pass book, plus its account number.
ALTER TABLE `leads`
  ADD COLUMN `doc_bank_passbook`   TINYINT(1)  NOT NULL DEFAULT 0    AFTER `doc_kseb_bill`,
  ADD COLUMN `doc_bank_account_no` VARCHAR(40) NULL    DEFAULT NULL AFTER `doc_kseb_consumer_no`;
