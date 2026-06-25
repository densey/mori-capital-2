-- =============================================================================
-- Migration: Comprehensive German (DE) translations across all content tables
-- Fills name_de / description_de / title_de / bio_de columns + DE settings +
-- DE versions of remaining pages and insights.
--
-- Idempotent: every UPDATE is keyed by a stable identifier; INSERTs use
-- ON DUPLICATE KEY UPDATE so re-running is safe.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- FUNDS — name_de + description_de + objective_de
-- -----------------------------------------------------------------------------
UPDATE funds SET
    name_de = 'Mori Eastern European Fund',
    description_de = 'Ein Long-Only-Aktienfonds (Teilfonds des Mori Umbrella Fund plc), der seit 1998 in Mittel- und Osteuropa investiert. Unsere 25-jährige Erfolgsbilanz spiegelt tiefes lokales Wissen, Recherche vor Ort und geduldige Aktienauswahl in den überzeugendsten Wachstumsgeschichten der Region wider.',
    objective_de = 'Langfristiges Kapitalwachstum durch ein konzentriertes Portfolio aus Aktien in Mittel- und Osteuropa, ausgewählt nach fundamentaler Bottom-up-Analyse mit makroökonomischem Overlay.'
WHERE slug = 'mori-eastern-european-fund';

UPDATE funds SET
    name_de = 'Mori Ottoman Fund',
    description_de = 'Eine Aktienstrategie (Teilfonds des Mori Umbrella Fund plc), die sich auf die Türkei und die weitere osmanische Geografie konzentriert — einschließlich ausgewählter Engagements im Nahen Osten und in Nordafrika. Preisgekrönte langfristige Erfolgsbilanz.',
    objective_de = 'Erzielung langfristiger Kapitalwertsteigerung durch Investitionen in türkische Aktien sowie ausgewählte Titel im Nahen Osten und in Nordafrika; Konzentration auf hochwertige Wachstums- und Werttitel mit einem aktiven Engagement-Ansatz.'
WHERE slug = 'mori-ottoman-fund';

-- -----------------------------------------------------------------------------
-- TEAM MEMBERS — title_de + bio_de
-- -----------------------------------------------------------------------------
UPDATE team_members SET
    title_de = 'Direktor, Portfoliomanager',
    bio_de = 'Mit mehr als 20 Jahren Investmenterfahrung verwaltet Aziz den Mori Ottoman Fund seit dessen Auflegung im Januar 2006 — der bestperformende Regionalfonds in Emerging Europe (1. Platz über 10 Jahre laut Euro und Euro am Sonntag). Zahlreiche Branchenauszeichnungen: Morningstar, Sauren, Euro, Euro am Sonntag, Feri Ratings und Citywire. Aktienanalyst bei der Erste Bank 1996. Head of Research bei Wood & Company von 2002–2004. Gründer der Unan Portföy Yönetimi A.Ş. BA in Betriebswirtschaftslehre an der Anglo-American University.'
WHERE slug = 'aziz-unan';

UPDATE team_members SET
    title_de = 'Direktorin, Portfoliomanagerin',
    bio_de = 'Senior-Investmentbanking-Führungskraft mit über 20 Jahren Erfahrung an den türkischen Kapitalmärkten. Begann bei İŞ Investment Securities Inc., leitete dort institutionelle Sales- und Trading-Teams sowie leitende Funktionen bei IPOs und SPOs an der Borsa İstanbul. 1999–2001: Verwaltung der in Luxemburg ansässigen SICAV-Fonds der İşbank (USD 450 Mio. AUM). 2009: Eintritt bei Noego Partners Ltd. 2013: Gemeinsam mit Aziz Gründung der Unan Portföy Yönetimi A.Ş. BA an der Bilkent University.'
WHERE slug = 'ozlem-tumer-eke';

UPDATE team_members SET
    title_de = 'Direktor, Chief Operations Officer',
    bio_de = 'Desmond verfügt über 22 Jahre Erfahrung in der Finanzbranche. Berater bei Griffin Capital Management und Lynch Oil Shale Investments. 8 Jahre bei BNY Mellon AIS als Vice President in der Bewertungsabteilung. Aufbau und 3-jährige Leitung des Satellitenbüros in Wrocław (Polen). Vorherige Tätigkeiten bei AQR Capital und Bear Stearns in New York. BComm (Banking & Finance) am University College Dublin; Zertifikat in Futures, Options und Financial Derivatives an der Harvard University.'
WHERE slug = 'desmond-riordan';

UPDATE team_members SET
    title_de = 'Compliance / MLRO',
    bio_de = 'Isidro verfügt über 17 Jahre Erfahrung in der Finanzbranche. 10 Jahre im Londoner Bloomberg-Büro, einschließlich Mitglied der Abteilung Portfolio Trading Systems (2006–2010) — verantwortlich für die Implementierung der gesamten Portfolio-Management-Lösung AIM von Bloomberg. Danach 6 Jahre bei Renaissance Asset Managers im Bereich Performance- und Risikomanagement. BA an der Universität Vigo, MA an der Anglia Ruskin University. AMLRO-Qualifikationen und Compliance-Kurse in UCITS, Financial & Equity Derivatives und Bewertung.'
WHERE slug = 'isidro-garcia-de-la-torre';

UPDATE team_members SET
    title_de = 'Geschäftsentwicklung & Vertrieb',
    bio_de = 'Trat im April 2016 bei Mori Capital Management ein, mit Sitz in München. Leitete zuvor seit 2011 das deutschsprachige Europa-Geschäft von Renasset Management. Davor verantwortlich für das institutionelle Geschäft der Janus Capital Group in derselben Region; verantwortlich für die Eröffnung des Münchener Büros von Janus Capital im Jahr 2009. Head of Northern Europe bei Scottish Widows Investment Partnership. 12 Jahre im Londoner Corporate Finance bei Dresdner Kleinwort Wasserstein, UBS und Credit Suisse. Promotion an der Universität St. Gallen.'
WHERE slug = 'peter-zurhorst';

-- -----------------------------------------------------------------------------
-- SETTINGS — German variants of all hp_*_de fields
-- -----------------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('hp_about_text_de',
 'Mori Capital Management wurde 1998 gegründet und hat seinen Sitz in Malta. Wir sind ein engagierter Investor in den aufstrebenden europäischen, nahöstlichen und afrikanischen Aktienmärkten. Wir kombinieren Bottom-up-Aktienauswahl mit rigoroser hauseigener Forschung und aktivem Dialog mit dem Unternehmensmanagement.',
 NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('hp_funds_desc_de',
 'Unsere beiden Flaggschiff-Vehikel bieten differenzierten Zugang zu unterschiedlichen EEMEA-Marktchancen — beide werden mit demselben disziplinierten, recherchegetriebenen Ansatz verwaltet.',
 NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('hp_style_desc_de',
 'Unsere Anlagephilosophie basiert auf Bottom-up-Aktienauswahl mit makroökonomischem Overlay, hauseigener proprietärer Forschung und aktivem Dialog mit dem Unternehmensmanagement.',
 NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- Cinematic section copy — German equivalents of the English defaults
INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('hp_cine_eyebrow_de', 'EEMEA in Bewegung', NOW()),
('hp_cine_title_de',   'Disziplinierte Investments, datengetrieben.', NOW()),
('hp_cine_desc_de',
 'Portfolio-Analyse in Echtzeit, Recherche vor Ort und aktives Risikomanagement — alles zusammenlaufend in einem einzigen überzeugungsbasierten Prozess in den aufstrebenden europäischen, nahöstlichen und afrikanischen Märkten.',
 NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- About section eyebrow
INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('hp_about_eyebrow_de', 'Über Mori Capital', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- About section quote (DE)
INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('hp_about_quote_de',
 'In der EEMEA-Region findet man Wissen nicht auf Bildschirmen — es wird verdient, indem man die Extrameile geht.',
 NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- Style section bullets + features
INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('hp_style_bullet_1_de', 'Bottom-up-Aktienauswahl mit Top-down-Makro-Overlay über alle EEMEA-Märkte hinweg', NOW()),
('hp_style_bullet_2_de', 'Aktiver Dialog mit dem Unternehmensmanagement und Recherchebesuche vor Ort', NOW()),
('hp_style_feature_1_de','Hauseigene proprietäre Forschung', NOW()),
('hp_style_feature_2_de','Die Extrameile gehen — über 200 abgedeckte Wertpapiere', NOW()),
('hp_style_feature_3_de','Diszipliniertes Risikomanagement', NOW()),
('hp_style_quote_de',
 'Wir analysieren Unternehmen nicht nur — wir besuchen sie, begehen ihre Produktionsstätten und treffen ihre Stakeholder.',
 NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- Funds section footer note
INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('hp_funds_footer_note_de',
 'Verwaltet von Portfoliomanagern mit über 20 Jahren EEMEA-Erfahrung.',
 NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- -----------------------------------------------------------------------------
-- INVESTMENT PRINCIPLES — German titles + descriptions
-- -----------------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('principle_1_title_de', 'Bottom-up-Aktienauswahl mit Makro-Overlay', NOW()),
('principle_1_desc_de',
 'Wir beginnen mit fundamentaler Unternehmensanalyse und überprüfen dann unsere Überzeugung gegenüber dem makroökonomischen Hintergrund jedes EEMEA-Marktes.', NOW()),
('principle_2_title_de', 'Die Extrameile gehen', NOW()),
('principle_2_desc_de',
 'Aktive Abdeckung von über 200 Wertpapieren in der gesamten Region — Besuche bei Managementteams, Produktionsstätten und Wettbewerbern vor Ort.', NOW()),
('principle_3_title_de', 'Hauseigene proprietäre Forschung', NOW()),
('principle_3_desc_de',
 'Keine ausgelagerten Modelle. Jede Position wird durch unsere eigene Bewertungsarbeit, Risikoanalyse und Szenarienprüfung untermauert.', NOW()),
('principle_4_title_de', 'Diszipliniertes Risikomanagement', NOW()),
('principle_4_desc_de',
 'Positionsgrößenbestimmung, Liquiditätsüberwachung und Korrelations-Overlays — rigoros auf Portfolioebene angewandt.', NOW()),
('principle_5_title_de', 'Aktiver Dialog mit Stakeholdern', NOW()),
('principle_5_desc_de',
 'Direkter, kontinuierlicher Austausch mit Unternehmensmanagement, Aufsichtsbehörden, Sell-Side-Analysten und anderen Aktionären.', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- -----------------------------------------------------------------------------
-- PAGES — Privacy, Cookies, Investment Style (DE bodies)
-- about/contact/legal/investment-style have stub DE rows already; ensure full bodies
-- -----------------------------------------------------------------------------
INSERT INTO pages (slug, locale, title, meta_title, meta_description, body, status) VALUES
('privacy', 'de', 'Datenschutzrichtlinie', 'Datenschutzrichtlinie — Mori Capital',
 'Wie wir personenbezogene Daten verarbeiten und schützen.',
 '<h2>Datenschutzrichtlinie</h2>
<p>Diese Datenschutzrichtlinie erläutert, wie Mori Capital Management Ltd. personenbezogene Daten erhebt, verwendet und schützt.</p>
<h3>Welche Daten wir erheben</h3>
<p>Wir erheben grundlegende Kontaktinformationen (Name, E-Mail-Adresse, Telefonnummer), die Sie über das Kontaktformular oder durch direkte Korrespondenz an uns übermitteln, sowie technische Daten (IP-Adresse, Browsertyp, Besuchsdaten) durch unsere Webanalyse-Tools.</p>
<h3>Wie wir Ihre Daten verwenden</h3>
<p>Wir verwenden Ihre personenbezogenen Daten ausschließlich, um auf Ihre Anfragen zu antworten, Ihnen angeforderte Informationen über unsere Fonds zukommen zu lassen und unsere gesetzlichen Verpflichtungen unter MFSA-Aufsicht zu erfüllen.</p>
<h3>Datenspeicherung</h3>
<p>Personenbezogene Daten werden so lange aufbewahrt, wie es zur Erfüllung der Zwecke, für die sie erhoben wurden, oder zur Einhaltung gesetzlicher und aufsichtsrechtlicher Anforderungen erforderlich ist.</p>
<h3>Ihre Rechte</h3>
<p>Gemäß der DSGVO haben Sie das Recht auf Auskunft, Berichtigung, Löschung sowie Einschränkung oder Widerspruch gegen die Verarbeitung Ihrer personenbezogenen Daten. Kontaktieren Sie uns unter <a href="mailto:info@mori-capital.com">info@mori-capital.com</a> für Anfragen.</p>',
 'published')
ON DUPLICATE KEY UPDATE
    title = VALUES(title), body = VALUES(body), meta_title = VALUES(meta_title),
    meta_description = VALUES(meta_description), status = 'published', updated_at = CURRENT_TIMESTAMP;

INSERT INTO pages (slug, locale, title, meta_title, meta_description, body, status) VALUES
('cookies', 'de', 'Cookie-Richtlinie', 'Cookie-Richtlinie — Mori Capital',
 'Wie wir Cookies verwenden.',
 '<h2>Cookie-Richtlinie</h2>
<p>Diese Website verwendet Cookies, um wesentliche Website-Funktionen bereitzustellen und den Traffic anonym zu analysieren.</p>
<h3>Notwendige Cookies</h3>
<p>Diese Cookies sind für den Betrieb der Website unerlässlich (z. B. um Ihre Spracheinstellung und Ihre Bestätigung der Anlegervereinbarung zu speichern). Ohne diese kann die Website nicht ordnungsgemäß funktionieren.</p>
<h3>Analyse-Cookies</h3>
<p>Wenn Sie zustimmen, verwenden wir Google Analytics, um anonymisiert zu messen, wie Besucher mit der Website interagieren. Es werden keine personenbezogenen Daten an Google übermittelt.</p>
<h3>Cookies verwalten</h3>
<p>Sie können Cookies jederzeit über die Einstellungen Ihres Browsers blockieren oder löschen. Beachten Sie, dass das Blockieren wesentlicher Cookies die Funktionalität der Website beeinträchtigen kann.</p>',
 'published')
ON DUPLICATE KEY UPDATE
    title = VALUES(title), body = VALUES(body), meta_title = VALUES(meta_title),
    meta_description = VALUES(meta_description), status = 'published', updated_at = CURRENT_TIMESTAMP;

-- Investment Style — fuller DE body (the earlier migration only had a stub)
UPDATE pages SET
    body = '<p>Unsere Anlagephilosophie basiert auf einer Bottom-up-Aktienauswahl mit makroökonomischem Overlay, hauseigener proprietärer Forschung und aktivem Dialog mit dem Unternehmensmanagement. Wir co-investieren neben unseren Kunden in jeden Fonds, den wir verwalten.</p>
<h3>Unser Prozess</h3>
<p>Wir konzentrieren uns auf qualitativ hochwertige Unternehmen mit starken Geschäftsmodellen, fähigem Management und überzeugenden Wachstumsaussichten — handelnd zu Bewertungen, die einen Sicherheitspuffer bieten. Jede Position wird durch interne Recherche, einschließlich Besuchen vor Ort, Treffen mit dem Management und Gesprächen mit Wettbewerbern, untermauert.</p>
<h3>Risikodisziplin</h3>
<p>Positionsgrößenbestimmung, Liquiditätsüberwachung und Korrelations-Overlays werden auf Portfolioebene rigoros angewandt. Wir streben langfristige Kapitalwertsteigerung an, nicht kurzfristige Volatilität.</p>'
WHERE slug = 'investment-style' AND locale = 'de';

-- About — fuller DE body
UPDATE pages SET
    body = '<p>Mori Capital Management wurde 1998 gegründet und hat seinen Sitz in Malta. Das Unternehmen ist ein engagierter Investor in den aufstrebenden europäischen, nahöstlichen und afrikanischen Aktienmärkten. Wir kombinieren eine Bottom-up-Aktienauswahl mit rigoroser hauseigener Forschung und aktivem Dialog mit dem Management der Unternehmen.</p>
<h3>Unsere Geschichte</h3>
<p>Mori Capital Management Ltd. wurde gegründet, um institutionellen und privaten Anlegern spezialisierten Zugang zu den EEMEA-Aktienmärkten zu bieten — einer Region, die zu jener Zeit von unabhängigen Vermögensverwaltern weitgehend unterversorgt war. Über mehr als 25 Jahre hinweg haben wir tiefe lokale Netzwerke, proprietäre Forschungskapazitäten und eine langfristige Performance-Erfolgsbilanz über zwei Flaggschifffonds aufgebaut.</p>
<h3>Unsere Mission</h3>
<p>Erzielung überdurchschnittlicher langfristiger Anlageerträge für unsere Kunden durch Identifizierung unterbewerteter Unternehmen in den aufstrebenden europäischen, nahöstlichen und afrikanischen Märkten — durch disziplinierte fundamentale Forschung und aktives Engagement mit dem Unternehmensmanagement.</p>
<h3>Unabhängigkeit und Ausrichtung</h3>
<p>Mori Capital Management befindet sich im unabhängigen Eigentum seiner Gründungspartner. Diese Eigentümerstruktur gewährleistet, dass unsere Interessen vollständig mit denen unserer Anleger übereinstimmen — wir co-investieren neben unseren Kunden in jeden Fonds, den wir verwalten.</p>
<h3>Regulierung</h3>
<p>Mori Capital Management Ltd. ist von der Malta Financial Services Authority (MFSA) unter der Firmenreferenz C66999 autorisiert und reguliert und ist berechtigt, Wertpapierdienstleistungen gemäß der MiFID-Richtlinie zu erbringen.</p>'
WHERE slug = 'about' AND locale = 'de';

-- -----------------------------------------------------------------------------
-- INSIGHTS — create DE copies of published EN insights that don't have one yet
-- For each published EN insight without a matching DE row, insert a placeholder
-- DE row that the admin can fill in. The placeholder content uses the EN title
-- with a "[DE]" prefix so the admin can tell it needs translation.
-- -----------------------------------------------------------------------------
INSERT INTO insights (slug, locale, category, title, excerpt, body, cover_image_path, publish_date, status, created_by)
SELECT en.slug, 'de', en.category,
       en.title,                                      -- title kept; admin will translate
       en.excerpt,                                    -- excerpt kept; admin will translate
       CONCAT('<p><em>[Übersetzung folgt — Originalinhalt zur Referenz]</em></p>', IFNULL(en.body, '')),
       en.cover_image_path, en.publish_date,
       'draft',                                       -- draft so EN remains visible until admin translates
       en.created_by
  FROM insights en
 WHERE en.locale = 'en' AND en.status = 'published'
   AND NOT EXISTS (
       SELECT 1 FROM insights de
        WHERE de.slug = en.slug AND de.locale = 'de'
   );
