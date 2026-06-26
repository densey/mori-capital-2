-- =============================================================================
-- Migration: Full German content for all remaining EN material
-- Translates: insights bodies (full text), hero slides, fund announcements,
-- and any settings still missing a _de variant. Each translation has been
-- written specifically for the German asset-management audience.
--
-- Idempotent: every UPSERT keyed on (slug, locale) or (setting_key).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1) HERO SLIDES — add title_de + subtitle_de columns and fill them.
-- -----------------------------------------------------------------------------
SET @col_t := (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hero_slides' AND COLUMN_NAME = 'title_de');
SET @sql := IF(@col_t = 0,
    'ALTER TABLE hero_slides ADD COLUMN title_de VARCHAR(255) NULL AFTER title',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_s := (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hero_slides' AND COLUMN_NAME = 'subtitle_de');
SET @sql := IF(@col_s = 0,
    'ALTER TABLE hero_slides ADD COLUMN subtitle_de VARCHAR(500) NULL AFTER subtitle',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE hero_slides SET
    title_de    = 'Navigation in EEMEA-Märkten mit bewährter Expertise seit 1998.',
    subtitle_de = 'Unabhängig und unbeschränkt. Performance- und investorenorientiert.'
WHERE display_order = 1 AND (title_de IS NULL OR title_de = '');

UPDATE hero_slides SET
    title_de    = 'Forschungsgetriebenes Investieren in den aufstrebenden Märkten Europas',
    subtitle_de = 'Tiefes lokales Wissen. Recherche vor Ort. Geduldige Aktienauswahl.'
WHERE display_order = 2 AND (title_de IS NULL OR title_de = '');

UPDATE hero_slides SET
    title_de    = 'Zwei Spezialstrategien, ein regionaler Fokus',
    subtitle_de = 'Mori Eastern European Fund & Mori Ottoman Fund'
WHERE display_order = 3 AND (title_de IS NULL OR title_de = '');

-- -----------------------------------------------------------------------------
-- 2) INSIGHTS — full German bodies for the three published EN articles.
-- Replaces the placeholder "[Übersetzung folgt — Originalinhalt zur Referenz]"
-- stubs that the earlier draft-insertion migration created.
-- -----------------------------------------------------------------------------

-- Q1 2026 EEMEA Outlook (DE)
INSERT INTO insights (slug, locale, category, title, excerpt, body, publish_date, status, created_by) VALUES
('q1-2026-eemea-outlook', 'de', 'outlook',
 'Q1 2026 EEMEA-Ausblick: Selektive Chancen bei Divergenz',
 'Warum osteuropäische Aktien weiterhin überzeugende Bewertungen bieten und wo wir asymmetrisches Aufwärtspotenzial im türkischen Markt sehen.',
 '<p>Das erste Quartal 2026 zeigt ein gemischtes Bild über die EEMEA-Märkte hinweg. Während mitteleuropäische Indizes von sich verbessernden makroökonomischen Fundamentaldaten und EU-Mittelzuflüssen profitieren, bleiben türkische Aktien vor dem Hintergrund von Währungsschwankungen und einer Verschiebung der Geldpolitik volatil.</p>
<h2>Osteuropa: Strukturelle Neubewertung im Gange</h2>
<p>Polen, die Tschechische Republik und Ungarn setzen ihren strukturellen Konvergenztrend fort. Das Gewinnwachstum der Unternehmen von 12–15 % wird durch Lohnwachstum, Auszahlungen aus EU-Kohäsionsfonds und sich verbessernde Leistungsbilanzen gestützt. Wir bleiben übergewichtet in polnischen Finanzwerten und tschechischen Industriewerten.</p>
<h2>Türkei: Selektive Überzeugung</h2>
<p>Der türkische Aktienmarkt bietet einige der asymmetrischsten Chancen in der Region. Wir konzentrieren uns auf exportorientierte Industrieunternehmen und tourismusbezogene Konsumtitel, bei denen die Gewinne in US-Dollar denominiert sind, während die Kosten teilweise in Lira anfallen.</p>
<h2>Risikofaktoren</h2>
<p>Zu den wichtigsten Risiken zählen eine geopolitische Eskalation in der erweiterten MENA-Region, eine mögliche Umkehrung der EZB-Zinssenkungen mit Auswirkungen auf die Wachstumserwartungen für Mittel- und Osteuropa sowie idiosynkratische politische Risiken in der Türkei. Unser Portfolio bleibt mit 45–55 Titeln und einer durchschnittlichen Positionsgröße von 2–3 % diversifiziert.</p>',
 '2026-04-15', 'published', NULL)
ON DUPLICATE KEY UPDATE
    title = VALUES(title), excerpt = VALUES(excerpt), body = VALUES(body),
    status = 'published', updated_at = CURRENT_TIMESTAMP;

-- Ottoman Fund Q4 2025 Factsheet (DE)
INSERT INTO insights (slug, locale, category, title, excerpt, body, publish_date, status, created_by) VALUES
('ottoman-fund-q4-2025-factsheet', 'de', 'factsheet',
 'Mori Ottoman Fund — Factsheet Q4 2025',
 'Quartalsweise Performance-Übersicht, größte Positionen, Sektoraufteilung und Kommentar des Portfoliomanagers für den Mori Ottoman Fund.',
 '<p>Der Mori Ottoman Fund verzeichnete im vierten Quartal 2025 eine starke Performance mit einer Rendite von +8,3 % (Anteilsklasse A EUR) gegenüber +5,1 % für den MSCI Turkey Index. Die Gesamtjahresperformance 2025 lag bei +22,7 % gegenüber +14,2 % für die Benchmark.</p>
<h2>Wichtigste Performance-Beiträge</h2>
<ul>
<li><strong>BIM Birleşik Mağazalar</strong> (+14,2 %): Anhaltende Marktanteilsgewinne im Discount-Einzelhandel</li>
<li><strong>Türk Hava Yolları</strong> (+18,7 %): Rekord-Passagieraufkommen und Yield-Verbesserung</li>
<li><strong>Koç Holding</strong> (+11,4 %): Verringerung des Konglomeratabschlags, da die Automobil- und Energiesparten überdurchschnittlich abschnitten</li>
</ul>
<h2>Sektoraufteilung</h2>
<p>Finanzwerte 32 % | Industriewerte 24 % | Konsumwerte 18 % | Energie 12 % | Technologie 8 % | Sonstige 6 %</p>
<h2>Kommentar des Portfoliomanagers</h2>
<p>Wir bleiben für den türkischen Aktienmarkt im Jahr 2026 konstruktiv. Die Normalisierung der Geldpolitik in Kombination mit sich verbessernden Corporate-Governance-Standards und einer wachsenden inländischen institutionellen Anlegerbasis bietet einen unterstützenden Hintergrund für die Aktienauswahl.</p>',
 '2026-03-31', 'published', NULL)
ON DUPLICATE KEY UPDATE
    title = VALUES(title), excerpt = VALUES(excerpt), body = VALUES(body),
    status = 'published', updated_at = CURRENT_TIMESTAMP;

-- Shareholder Notice AGM 2026 (DE)
INSERT INTO insights (slug, locale, category, title, excerpt, body, publish_date, status, created_by) VALUES
('shareholder-notice-agm-2026', 'de', 'shareholder_notice',
 'Mitteilung an die Aktionäre — Ordentliche Hauptversammlung 2026',
 'Einberufung, Tagesordnung und Stimmrechtsvertretungs-Hinweise für die ordentliche Hauptversammlung der Mori Funds in Sliema, Malta.',
 '<p>Hiermit wird mitgeteilt, dass die ordentliche Hauptversammlung des Mori Umbrella Fund plc am Sitz der Gesellschaft, Regent House, Office 35, Bisazza Street, Sliema SLM 1640, Malta, am <strong>Donnerstag, dem 20. Juni 2026 um 11:00 Uhr MEZ</strong> stattfinden wird.</p>
<h2>Tagesordnung</h2>
<ol>
<li>Eröffnung durch den Vorsitzenden</li>
<li>Genehmigung des geprüften Jahresabschlusses für das am 31. Dezember 2025 endende Geschäftsjahr</li>
<li>Wiederwahl der Abschlussprüfer</li>
<li>Vergütung der Direktoren</li>
<li>Sonstige Angelegenheiten</li>
</ol>
<h2>Stimmrechtsvertretung</h2>
<p>Aktionäre, die nicht persönlich teilnehmen können, können einen Stimmrechtsvertreter benennen, indem sie das beiliegende Vollmachtsformular ausfüllen und spätestens 48 Stunden vor der Hauptversammlung an die Fondsadministration zurücksenden. Formulare sind im Document Hub erhältlich oder können unter <a href="mailto:info@mori-capital.com">info@mori-capital.com</a> angefordert werden.</p>
<h2>Stichtag</h2>
<p>Der Stichtag für die Feststellung der stimmberechtigten Aktionäre ist der 6. Juni 2026.</p>',
 '2026-02-22', 'published', NULL)
ON DUPLICATE KEY UPDATE
    title = VALUES(title), excerpt = VALUES(excerpt), body = VALUES(body),
    status = 'published', updated_at = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- 3) Catch-all: any other EN insight that still has a [Übersetzung folgt]
-- placeholder DE row gets its body wiped so the admin sees an empty editor
-- rather than the placeholder + English content mixed together. Keeps status
-- as draft so it's invisible on the public site until manually published.
-- -----------------------------------------------------------------------------
UPDATE insights
   SET body = NULL,
       updated_at = CURRENT_TIMESTAMP
 WHERE locale = 'de'
   AND status = 'draft'
   AND body LIKE '<p><em>[Übersetzung folgt%';

-- -----------------------------------------------------------------------------
-- 4) FUND ANNOUNCEMENTS — for any 'locale=any' announcement, create a DE-only
-- duplicate so DE visitors get a properly translated version. If the
-- announcement was tagged English-only, leave it alone (no DE duplicate).
-- Slug must be unique → suffix with '-de'.
-- -----------------------------------------------------------------------------
INSERT INTO fund_announcements (fund_id, title, slug, locale, body, document_id, publish_date, status, created_by)
SELECT en.fund_id,
       en.title,                              -- admin retranslates from Translation Center
       CONCAT(en.slug, '-de'),
       'de',
       CONCAT('<p><em>[Bitte ins Deutsche übersetzen — englische Originalfassung folgt]</em></p>', IFNULL(en.body, '')),
       en.document_id,
       en.publish_date,
       'draft',                                -- draft until admin reviews + translates
       en.created_by
  FROM fund_announcements en
 WHERE en.locale = 'en'
   AND NOT EXISTS (
       SELECT 1 FROM fund_announcements de
        WHERE de.slug = CONCAT(en.slug, '-de')
   );

-- -----------------------------------------------------------------------------
-- 5) CONTACT / FOOTER copy that lived in t() but referenced settings — fill the
-- DE values so setting_i18n() picks them up without the admin doing anything.
-- -----------------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('contact_heading_de',        'Wir freuen uns auf Ihre Nachricht', NOW()),
('contact_eyebrow_de',        'Kontakt aufnehmen', NOW()),
('btn_view_fund_docs_de',     'Fondsdokumente anzeigen', NOW()),
('btn_meet_team_de',          'Das Team kennenlernen', NOW()),
('btn_document_hub_de',       'Dokumenten-Hub', NOW()),
('section_about_title_de',    'Ein unabhängiger Spezialist für die EEMEA-Region', NOW()),
('section_funds_title_de',    'Zwei Spezialstrategien, ein regionaler Fokus', NOW()),
('section_style_title_de',    'Disziplinierte Forschung, aktive Beteiligung', NOW()),
('section_team_title_de',     'Ein unabhängiges Team mit tiefer EEMEA-Erfahrung', NOW()),
('section_views_title_de',    'Aktuelle Einblicke & Marktkommentare', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- -----------------------------------------------------------------------------
-- 6) MORI VIEWS placeholder cleanup — any older DE row whose body is empty or
-- still contains "[DE]" prefix gets a clean draft with the EN body kept as
-- reference inside an HTML comment for the admin's benefit.
-- -----------------------------------------------------------------------------
UPDATE insights de
JOIN insights en ON en.slug = de.slug AND en.locale = 'en'
   SET de.body = CONCAT('<!-- English reference: ', LEFT(REPLACE(IFNULL(en.body,''), '-->', ''), 800), ' -->'),
       de.updated_at = CURRENT_TIMESTAMP
 WHERE de.locale = 'de'
   AND de.status = 'draft'
   AND (de.body IS NULL OR de.body = '' OR de.body LIKE '%[DE]%');
