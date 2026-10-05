-- 2026-10-nav-import-log.sql
-- Every price upload published from Admin → Performance (NAV) is recorded here:
-- who published it, the original file (kept so each published price can be
-- traced back to its source), and the previous values of every price that was
-- overwritten or deleted, so an accidental overwrite can be undone.
-- Publishing still works if this table is missing (the log is then skipped).

CREATE TABLE IF NOT EXISTS nav_import_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id         INT NULL,
    file_name       VARCHAR(255) NOT NULL,
    file_sha256     CHAR(64) NOT NULL,
    file_base64     LONGTEXT NULL,
    mode            VARCHAR(10) NOT NULL,
    summary         VARCHAR(500) NULL,
    changes_json    LONGTEXT NULL,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
