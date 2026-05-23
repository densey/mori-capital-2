-- =============================================================================
-- Migration tracker — applied migrations are recorded here.
-- This file itself is part of the base schema (created on install).
-- =============================================================================
CREATE TABLE IF NOT EXISTS schema_migrations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    file        VARCHAR(190) NOT NULL UNIQUE,
    checksum    VARCHAR(64)  NOT NULL,
    applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_by  INT NULL,
    notes       TEXT NULL,
    INDEX idx_file (file)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
