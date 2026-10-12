-- Task performance tracking columns
-- was_on_time: 1 = completed on/before due_date, 0 = completed after due_date, NULL = no due_date
-- reschedule_count: incremented each time setDueDate() is called on a pending task

ALTER TABLE `workflow_tasks`
  ADD COLUMN `was_on_time`      TINYINT(1)       NULL         AFTER `completed_at`,
  ADD COLUMN `reschedule_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `was_on_time`;
