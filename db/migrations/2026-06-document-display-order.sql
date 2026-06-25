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
-- Order within each (category, display_year, locale) bucket follows current visible
-- ordering: newest date first, then created_at desc.
SET @rownum := 0;
UPDATE documents d
JOIN (
    SELECT id,
           (@rownum := @rownum + 1) AS rn
      FROM documents
     ORDER BY category,
              COALESCE(display_year, YEAR(document_date), YEAR(created_at)) DESC,
              COALESCE(document_date, created_at) DESC,
              id ASC
) ranked ON ranked.id = d.id
SET d.display_order = ranked.rn
WHERE d.display_order = 0;
