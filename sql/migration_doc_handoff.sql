-- ============================================================================
-- Sales → Documentation handoff
--
-- Adds the columns needed to send a lead back to sales and to pause the
-- documentation SLA clock while it sits with them.
--
-- Deliberately NO new documentation_status value: "with sales" reuses the
-- existing 'on_hold', distinguished by doc_hold_reason. A fifth ENUM member
-- would need edits in saveStatus()'s whitelist, viewCounts(), the queue's
-- ORDER BY FIELD(...) and the status pill map — and v1 at /employee reads this
-- column blind via SELECT *.
--
-- Additive only, and no UPDATE of existing rows, so leads.updated_at does not
-- move and the "won this month" KPI is untouched.
-- ============================================================================

ALTER TABLE `leads`
    ADD COLUMN `doc_hold_reason`  VARCHAR(500) NULL DEFAULT NULL AFTER `documentation_note`,
    ADD COLUMN `doc_hold_at`      DATETIME     NULL DEFAULT NULL AFTER `doc_hold_reason`,
    ADD COLUMN `doc_hold_task_id` INT UNSIGNED NULL DEFAULT NULL AFTER `doc_hold_at`,
    ADD COLUMN `doc_hold_seconds` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `doc_hold_task_id`,
    ADD COLUMN `doc_escalated_at` DATETIME     NULL DEFAULT NULL AFTER `doc_hold_seconds`;

-- doc_hold_task_id is a pointer, not a relationship: no FK, because the leads
-- CASCADE would then have to reason about workflow_tasks too.

ALTER TABLE `leads`
    ADD INDEX `idx_leads_doc_hold` (`documentation_status`, `doc_hold_at`);
