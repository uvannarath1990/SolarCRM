-- ============================================================
-- Migration: Workflow Outcome Fields (v2)
-- Run once:  mysql -u USER -p DB_NAME < sql/migration_workflow_v2.sql
-- Each statement is independent — safe to re-run if partially applied.
-- ============================================================

-- ── workflow_tasks outcome columns ────────────────────────────────────────────
ALTER TABLE `workflow_tasks` ADD COLUMN IF NOT EXISTS
  `outcome` VARCHAR(60) NULL AFTER `status`;

ALTER TABLE `workflow_tasks` ADD COLUMN IF NOT EXISTS
  `outcome_site_visit_date` DATE NULL AFTER `outcome`;

ALTER TABLE `workflow_tasks` ADD COLUMN IF NOT EXISTS
  `outcome_next_visit_date` DATE NULL AFTER `outcome_site_visit_date`;

ALTER TABLE `workflow_tasks` ADD COLUMN IF NOT EXISTS
  `outcome_lost_reason` VARCHAR(200) NULL AFTER `outcome_next_visit_date`;

-- ── leads new date columns ────────────────────────────────────────────────────
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS
  `next_site_visit_date` DATE NULL AFTER `visit_scheduled_date`;

ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS
  `site_visit_completed_date` DATE NULL AFTER `next_site_visit_date`;

ALTER TABLE `leads`
  ADD INDEX IF NOT EXISTS `idx_leads_next_sv` (`next_site_visit_date`);
