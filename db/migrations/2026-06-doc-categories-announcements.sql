-- Migration 2026-06: Document categories + Fund announcements
-- Idempotent: uses information_schema checks before ALTER.

-- 1) Add category column to documents (if missing)
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'category');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE documents ADD COLUMN category VARCHAR(40) NOT NULL DEFAULT ''share_class'' AFTER document_type',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Add description column
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'description');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE documents ADD COLUMN description TEXT NULL AFTER title',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Add display_year column
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'display_year');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE documents ADD COLUMN display_year INT NULL AFTER document_date',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Index on category + year
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_doc_category');
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE documents ADD INDEX idx_doc_category (category, display_year)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Fund announcements table
CREATE TABLE IF NOT EXISTS fund_announcements (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    fund_id       INT NULL,
    title         VARCHAR(255) NOT NULL,
    slug          VARCHAR(255) NOT NULL UNIQUE,
    locale        ENUM('en','de','any') NOT NULL DEFAULT 'any',
    body          LONGTEXT NULL,
    document_id   INT NULL,
    publish_date  DATE NOT NULL DEFAULT (CURRENT_DATE),
    status        ENUM('draft','published') NOT NULL DEFAULT 'published',
    created_by    INT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ann_date (publish_date),
    INDEX idx_ann_status_locale (status, locale),
    CONSTRAINT fk_ann_fund FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE SET NULL,
    CONSTRAINT fk_ann_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    CONSTRAINT fk_ann_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
