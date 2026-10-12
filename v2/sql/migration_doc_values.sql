-- Store the actual document values alongside the received/not-received flags.
-- The doc_* TINYINT columns say whether a document arrived; these say what it is.
ALTER TABLE `leads`
  ADD COLUMN `doc_aadhar_number`   VARCHAR(20) NULL DEFAULT NULL AFTER `doc_kseb_bill`,
  ADD COLUMN `doc_mobile_number`   VARCHAR(20) NULL DEFAULT NULL AFTER `doc_aadhar_number`,
  ADD COLUMN `doc_kseb_consumer_no` VARCHAR(40) NULL DEFAULT NULL AFTER `doc_mobile_number`;
