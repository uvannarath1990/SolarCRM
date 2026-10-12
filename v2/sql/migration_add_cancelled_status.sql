-- Add 'cancelled' to workflow_tasks.status ENUM
-- Run this once on your database.

ALTER TABLE `workflow_tasks`
  MODIFY COLUMN `status`
    ENUM('pending','done','skipped','cancelled')
    NOT NULL DEFAULT 'pending';
