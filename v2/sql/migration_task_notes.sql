-- Migration: add notes column to workflow_tasks (used by task reopen + bulk review)
ALTER TABLE `workflow_tasks`
  ADD COLUMN `notes` TEXT NULL AFTER `outcome_lost_reason`;
