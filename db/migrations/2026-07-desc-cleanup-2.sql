-- 2026-07-desc-cleanup-2.sql
-- One import-artifact description survived 2026-07-post-deploy-cleanup.sql:
-- its "– English" suffix uses an EN-DASH, while the cleanup matched the ASCII
-- hyphen variant only. Catch the dash variants and make the title/description
-- redundancy comparison dash-agnostic. Same safety rails: archive categories
-- only, share-class fund documents untouched, idempotent.
UPDATE documents SET description = NULL
 WHERE category IN ('company_policy','other','suspension_update')
   AND description IS NOT NULL
   AND (
        description LIKE '% – English'
     OR description LIKE '% – Deutsch'
     OR REPLACE(REPLACE(REPLACE(description, ' ', ''), '-', ''), '–', '') =
        REPLACE(REPLACE(REPLACE(title, ' ', ''), '-', ''), '–', '')
   );
