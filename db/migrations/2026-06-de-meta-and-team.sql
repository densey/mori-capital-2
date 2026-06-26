-- 2026-06-de-meta-and-team.sql
-- Fixes the last batch of EN-leakage found by the bilingual audit:
--   1. DE locale overrides for SEO meta tags (title/description) on every page
--      that uses setting('seo_default_title')/setting('seo_default_desc').
--   2. German bio for Jean-Paul Gauci (was still mostly English on the public team page).
-- Idempotent: every UPDATE/INSERT is guarded by a WHERE/ON DUPLICATE KEY clause.
-- Settings table columns are `setting_key` / `setting_value` (NOT `key` / `value`).

-- ---------------------------------------------------------------------------
-- 1. SEO defaults — German overrides for index.php meta
-- ---------------------------------------------------------------------------

INSERT INTO settings (`setting_key`, `setting_value`) VALUES
('seo_default_title_de', 'Mori Capital Management — Spezialist für die EEMEA-Region'),
('seo_default_desc_de',  'Unabhängiger EEMEA-fokussierter Vermögensverwalter. Mori Eastern European Fund und Mori Ottoman Fund — research-getriebenes Investieren in Emerging Europe, dem Nahen Osten und Afrika seit 1998.')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);


-- ---------------------------------------------------------------------------
-- 2. Team — Jean-Paul Gauci German bio
-- ---------------------------------------------------------------------------

UPDATE team_members
SET bio_de = 'Jean-Paul besitzt einen Bachelor of Commerce der University of Malta, einen Masters in Investment and Finance (University of Strathclyde) sowie einen Masters in Risk, Crisis and Resilience Management (University of Portsmouth). 2012 startete er seine Finanzkarriere als Fondsadministrator bei einem führenden maltesischen Fondsadministrator. 2015 wechselte er zu RiskCap International Limited; 2023 wurde er zum Head of Risk Services befördert. Nebenberuflich Dozent an der University of Malta im Bereich Banking and Finance des FEMA.'
WHERE slug = 'jean-paul-gauci';
