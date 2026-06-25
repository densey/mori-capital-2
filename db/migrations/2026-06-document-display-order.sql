-- Migration: add display_order to documents so the admin can manually
-- reorder Other Documents / Company Policies / Updates During Suspension lists.
-- Idempotent: information_schema check before ALTER.

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'display_order');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE documents ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER display_year',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_doc_order');
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE documents ADD INDEX idx_doc_order (category, display_order)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed initial ordering so the lists don't reshuffle the moment the column appears.
-- We simply use the row id as a proxy for "creation order" — older rows get
-- lower display_order (appear first), newer ones go to the end. The admin can
-- then drag to reorder. We avoid @rownum-based UPDATEs because they fail on
-- some MariaDB/MySQL strict-mode setups.
UPDATE documents SET display_order = id WHERE display_order = 0;
