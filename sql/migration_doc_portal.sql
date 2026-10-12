-- ============================================================================
-- Documentation portal (v2)
--
-- Two parts:
--   1. Explicit documentation state on `leads`. Until now "in progress" was
--      inferred from (site visit done AND not Lost AND not all four checkboxes),
--      which cannot distinguish "nobody has started" from "we are working on it",
--      cannot express "on hold", and has no timestamps to age a queue by.
--   2. `lead_documents` — the free-form file list. Deliberately independent of
--      the four legacy doc_* checkboxes.
--
-- Deliberately NOT backfilled: `leads.updated_at` is ON UPDATE CURRENT_TIMESTAMP
-- and the "won this month" KPI reads it, so a bulk UPDATE would corrupt that
-- figure. 'not_started' is honest for historical rows, and the portal queue also
-- matches the old implicit rule so nothing disappears on day one.
--
-- Additive only — /employee (v1) shares this database and uses SELECT * on leads.
-- ============================================================================

ALTER TABLE `leads`
    ADD COLUMN `documentation_status`       ENUM('not_started','in_progress','on_hold','completed')
                                            NOT NULL DEFAULT 'not_started' AFTER `documentation_user_id`,
    ADD COLUMN `documentation_started_at`   DATETIME     NULL DEFAULT NULL AFTER `documentation_status`,
    ADD COLUMN `documentation_completed_at` DATETIME     NULL DEFAULT NULL AFTER `documentation_started_at`,
    ADD COLUMN `documentation_note`         VARCHAR(500) NULL DEFAULT NULL AFTER `documentation_completed_at`;

ALTER TABLE `leads`
    ADD INDEX `idx_leads_doc_status` (`documentation_status`, `site_visit_completed_date`);


-- ── Uploaded documents ──────────────────────────────────────────────────────
-- `stored_name` is a random basename only, never a path: the directory is
-- derived server-side from lead_id, so nothing user-supplied reaches the
-- filesystem. mime_type/extension/size describe the file AS STORED, which can
-- differ from what was uploaded once compression runs (HEIC arrives, JPEG is
-- kept). original_size_bytes preserves what it weighed on arrival.

CREATE TABLE IF NOT EXISTS `lead_documents` (
    `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `lead_id`             INT UNSIGNED    NOT NULL,
    `label`               VARCHAR(120)    NOT NULL,
    `stored_name`         VARCHAR(80)     NOT NULL,
    `original_name`       VARCHAR(255)    NOT NULL,
    `mime_type`           VARCHAR(100)    NOT NULL,
    `extension`           VARCHAR(10)     NOT NULL,
    `size_bytes`          BIGINT UNSIGNED NOT NULL,
    `original_size_bytes` BIGINT UNSIGNED NULL DEFAULT NULL,
    `compressed`          TINYINT(1)      NOT NULL DEFAULT 0,
    `sha256`              CHAR(64)        NULL DEFAULT NULL,
    `uploaded_by`         INT UNSIGNED    NULL DEFAULT NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`          DATETIME        NULL DEFAULT NULL,
    `deleted_by`          INT UNSIGNED    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lead_documents_stored` (`stored_name`),
    KEY `idx_lead_documents_lead` (`lead_id`, `deleted_at`),
    CONSTRAINT `fk_lead_documents_lead` FOREIGN KEY (`lead_id`)
        REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lead_documents_user` FOREIGN KEY (`uploaded_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Settings ────────────────────────────────────────────────────────────────

INSERT INTO `settings` (`key_name`, `value`, `label`)
VALUES
    ('doc_portal_roles',      'admin|manager|partner|documentation_head|documentation_user',
                              'Roles allowed to use the Documentation login'),
    ('doc_upload_quota_mb',   '5000', 'Total document storage cap (MB)')
ON DUPLICATE KEY UPDATE `key_name` = `key_name`;
