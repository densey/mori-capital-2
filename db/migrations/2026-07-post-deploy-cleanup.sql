-- 2026-07-post-deploy-cleanup.sql
-- Two cleanups spotted in the post-deploy live verification:
--
-- 1) Team bios: write Peter's 12 years and Isidro's 6 years as digits in the
--    German bios so they mirror the English exactly ("12 years" / "6 years").
UPDATE team_members SET bio_de = REPLACE(bio_de, 'war zwölf Jahre im Corporate Finance', 'war 12 Jahre im Corporate Finance')
 WHERE slug = 'peter-zurhorst';
UPDATE team_members SET bio_de = REPLACE(bio_de, 'Anschließend war er sechs Jahre', 'Anschließend war er 6 Jahre')
 WHERE slug = 'isidro-garcia-de-la-torre';

-- 2) Document descriptions: the old-site import filled `description` with a
--    copy of the raw file label ("… DEUTSCH", "Marsch" typos, "MMori…",
--    "… - English"). The titles are curated (title/title_de) but the grey
--    description line re-exposed the artifact on BOTH locales. These
--    descriptions duplicate the title and carry no information — clear them.
--    Restricted to the three imported archive categories; share-class fund
--    documents are untouched, and admins can add real descriptions any time.
UPDATE documents SET description = NULL
 WHERE category IN ('company_policy','other','suspension_update')
   AND description IS NOT NULL
   AND (
        description = title
     OR REPLACE(REPLACE(description, ' ', ''), '-', '') = REPLACE(REPLACE(title, ' ', ''), '-', '')
     OR description LIKE '%DEUTSCH%'
     OR description LIKE '%Marsch%'
     OR description LIKE 'MMori%'
     OR description LIKE '% - English'
     OR description LIKE '% - Deutsch'
   );
