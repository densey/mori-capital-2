-- =============================================================================
-- Migration: Add German (DE) page translations
-- Adds DE versions of all published EN pages.
-- =============================================================================

-- About page (DE)
INSERT INTO pages (slug, locale, title, meta_title, meta_description, body, status) VALUES
('about', 'de', 'Über Mori', 'Über Mori Capital Management',
 'Unabhängiger EEMEA-Spezialist für Vermögensverwaltung, gegründet 1998, mit Sitz in Malta.',
 '<p>Mori Capital Management wurde 1998 gegründet und hat seinen Sitz in Malta. Das Unternehmen ist ein engagierter Investor in den aufstrebenden europäischen, nahöstlichen und afrikanischen Aktienmärkten. Wir kombinieren eine Bottom-Up-Aktienauswahl mit rigoroser hauseigener Forschung und aktivem Dialog mit dem Management der Unternehmen.</p>
<p>Als unabhängiger und unbeschränkter Vermögensverwalter konzentrieren wir uns ausschließlich auf die EEMEA-Region. Unsere Investmentteam bringt über 80 Jahre kollektive Erfahrung in diesen Märkten mit.</p>',
 'published')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    body = VALUES(body),
    meta_title = VALUES(meta_title),
    meta_description = VALUES(meta_description),
    status = 'published',
    updated_at = CURRENT_TIMESTAMP;

-- Investment Style page (DE)
INSERT INTO pages (slug, locale, title, meta_title, meta_description, body, status) VALUES
('investment-style', 'de', 'Der Mori-Stil', 'Anlagestil — Der Mori-Stil',
 'Disziplinierte Forschung, aktive Beteiligung — unsere Anlagephilosophie.',
 '<p>Unsere Anlagephilosophie basiert auf einer Bottom-Up-Aktienauswahl mit makroökonomischem Overlay, hauseigener proprietärer Forschung und aktivem Dialog mit dem Unternehmensmanagement. Wir co-investieren neben unseren Kunden in jeden Fonds, den wir verwalten.</p>',
 'published')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    body = VALUES(body),
    status = 'published',
    updated_at = CURRENT_TIMESTAMP;

-- Contact page (DE)
INSERT INTO pages (slug, locale, title, meta_title, meta_description, body, status) VALUES
('contact', 'de', 'Kontakt', 'Kontakt — Mori Capital Management',
 'Kontaktieren Sie unser Büro in Malta.',
 '<p>Mori Capital Management Ltd. — Regent House, Office 35, Bisazza Street, Sliema SLM 1640, Malta.</p>',
 'published')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    body = VALUES(body),
    status = 'published',
    updated_at = CURRENT_TIMESTAMP;

-- Legal page (DE)
INSERT INTO pages (slug, locale, title, meta_title, meta_description, body, status) VALUES
('legal', 'de', 'Rechtliche Hinweise', 'Rechtliche Hinweise — Mori Capital Management',
 'Vollständige regulatorische Haftungsausschlüsse und Risikowarnungen.',
 '<h2>Regulatorische Informationen</h2>
<p>Mori Capital Management Ltd. ist von der Malta Financial Services Authority (MFSA) unter der Firmenreferenz C66999 autorisiert und reguliert und ist berechtigt, Wertpapierdienstleistungen gemäß der MiFID-Richtlinie zu erbringen.</p>
<h2>Risikohinweis</h2>
<p>Die frühere Wertentwicklung ist kein verlässlicher Indikator für künftige Ergebnisse. Der Wert der Anlagen und die daraus erzielten Erträge können sowohl fallen als auch steigen. Anleger sollten den Fondsprospekt, das KIID/PRIIPs-KID und die jüngsten Finanzberichte vor einer Anlage lesen.</p>
<h2>Einschränkungen</h2>
<p>Die auf dieser Website beschriebenen Fonds sind auf Nicht-US-Personen und professionelle Anleger beschränkt und sind nicht unter dem U.S. Investment Advisers Act von 1940 registriert. Diese Website stellt weder ein Angebot zum Verkauf noch eine Aufforderung zum Kauf von Wertpapieren dar.</p>',
 'published')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    body = VALUES(body),
    status = 'published',
    updated_at = CURRENT_TIMESTAMP;

-- Privacy Policy (DE)
INSERT INTO pages (slug, locale, title, meta_title, meta_description, body, status) VALUES
('privacy', 'de', 'Datenschutzrichtlinie', 'Datenschutzrichtlinie — Mori Capital',
 'Wie wir personenbezogene Daten verarbeiten.',
 '<p>Diese Datenschutzrichtlinie erläutert, wie wir personenbezogene Daten erheben, verwenden und schützen.</p>
<h2>Erhobene Daten</h2>
<p>Wir erheben personenbezogene Daten nur dann, wenn Sie uns diese freiwillig zur Verfügung stellen, beispielsweise über unser Kontaktformular oder bei der Anmeldung zu unserem Newsletter.</p>
<h2>Verwendung</h2>
<p>Ihre Daten werden ausschließlich zur Bearbeitung Ihrer Anfrage oder zur Zusendung der gewünschten Informationen verwendet.</p>
<h2>Speicherung und Sicherheit</h2>
<p>Wir speichern Ihre Daten auf gesicherten Servern und verwenden angemessene technische Maßnahmen zum Schutz vor unbefugtem Zugriff.</p>',
 'published')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    body = VALUES(body),
    status = 'published',
    updated_at = CURRENT_TIMESTAMP;

-- Cookie Policy (DE)
INSERT INTO pages (slug, locale, title, meta_title, meta_description, body, status) VALUES
('cookies', 'de', 'Cookie-Richtlinie', 'Cookie-Richtlinie — Mori Capital',
 'Wie und warum wir Cookies auf dieser Website verwenden.',
 '<p>Diese Website verwendet Cookies, um wesentliche Funktionen bereitzustellen und den Datenverkehr anonym zu analysieren.</p>
<h2>Was sind Cookies?</h2>
<p>Cookies sind kleine Textdateien, die auf Ihrem Gerät gespeichert werden, wenn Sie unsere Website besuchen.</p>
<h2>Welche Cookies verwenden wir?</h2>
<ul>
<li><strong>Notwendige Cookies:</strong> Für grundlegende Funktionen wie Sitzungsverwaltung und Spracheinstellungen.</li>
<li><strong>Analyse-Cookies:</strong> Zur anonymen Messung des Website-Verkehrs (wenn Sie dem zugestimmt haben).</li>
</ul>
<h2>Ihre Einstellungen</h2>
<p>Sie können beim ersten Besuch zwischen „Alle akzeptieren" und „Nur notwendige" wählen. Diese Entscheidung wird in Ihrem Browser gespeichert.</p>',
 'published')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    body = VALUES(body),
    status = 'published',
    updated_at = CURRENT_TIMESTAMP;

-- German fund descriptions (update existing rows)
UPDATE funds SET
    description_de = 'Ein Long-Only-Aktienfonds (Teilfonds der Mori Umbrella Fund plc), der seit 1998 in Mittel- und Osteuropa investiert. Unsere 25-jährige Erfolgsbilanz spiegelt tiefes lokales Wissen, Forschung vor Ort und geduldige Aktienauswahl wider.',
    objective_de = 'Langfristigen Kapitalzuwachs durch Investitionen hauptsächlich in Aktien von Unternehmen zu erzielen, die in Mittel- und Osteuropa ansässig sind oder dort wesentliche Geschäftstätigkeiten haben.'
WHERE slug = 'mori-eastern-european-fund';

UPDATE funds SET
    description_de = 'Eine Aktienstrategie (Teilfonds der Mori Umbrella Fund plc) mit Fokus auf die Türkei und die breitere osmanische Geographie — einschließlich ausgewählter Expositionen im Nahen Osten und Nordafrika. Preisgekrönte langfristige Erfolgsbilanz.',
    objective_de = 'Langfristigen Kapitalzuwachs durch Investitionen hauptsächlich in Aktien von Unternehmen zu erzielen, die in der Türkei und der breiteren MENA-Region ansässig sind oder dort wesentliche Geschäftstätigkeiten haben.'
WHERE slug = 'mori-ottoman-fund';

-- German team titles
UPDATE team_members SET title_de = 'Direktor, Portfoliomanager' WHERE slug IN ('aziz-unan', 'ozlem-tumer-eke');
UPDATE team_members SET title_de = 'Direktor, Chief Operations Officer' WHERE slug = 'desmond-riordan';
UPDATE team_members SET title_de = 'Compliance / MLRO' WHERE slug = 'isidro-garcia-de-la-torre';
UPDATE team_members SET title_de = 'Geschäftsentwicklung & Vertrieb' WHERE slug = 'peter-zurhorst';
UPDATE team_members SET title_de = 'Risikomanager' WHERE slug = 'jean-paul-gauci';
