-- 2026-07-de-consistency-fixes-3.sql
-- Legal pages fully synced EN<->DE, and the confirmed co-investment fact added
-- to the English About page (it was stated in German only; Desmond confirmed it
-- is accurate).

-- 1) LEGAL (DE was abridged): translate the full English legal page — regulator
--    address, emerging-markets risk paragraph, capital-loss/FX warnings,
--    Regulation S / Securities Act 1933, Intellectual Property, 'incorporated in Malta'.
UPDATE pages SET body = '<h2>Aufsichtsrechtliche Informationen</h2>
<p>Mori Capital Management Ltd. ist eine in Malta eingetragene Gesellschaft und ist von der Malta Financial Services Authority (MFSA) unter der Firmenreferenz C66999 autorisiert und reguliert. Das Unternehmen ist berechtigt, Wertpapierdienstleistungen gemäß der Richtlinie über Märkte für Finanzinstrumente (MiFID) zu erbringen.</p>
<h2>Wichtige Risikohinweise</h2>
<p>Der Wert von Anlagen und die daraus erzielten Erträge können sowohl fallen als auch steigen, und Anleger erhalten den ursprünglich investierten Betrag möglicherweise nicht zurück. Die frühere Wertentwicklung ist kein verlässlicher Indikator für künftige Ergebnisse. Änderungen der Wechselkurse können ebenfalls dazu führen, dass der Wert der Anlagen steigt oder fällt.</p>
<p>Die auf dieser Website beschriebenen Fonds investieren überwiegend in Aktien von Unternehmen in Schwellenländern, was mit einem höheren Risiko verbunden sein kann als Anlagen in weiter entwickelten Märkten. Schwellenländer können einer geringeren Regulierung, größerer politischer Instabilität, Währungsvolatilität und geringerer Liquidität unterliegen.</p>
<h2>Nur Nicht-US-Personen</h2>
<p>Die auf dieser Website beschriebenen Fonds sind nicht gemäß dem United States Investment Advisers Act von 1940 in seiner geänderten Fassung registriert und stehen US-Personen im Sinne von Regulation S des U.S. Securities Act von 1933 nicht zur Verfügung. Der Zugang zu den Informationen auf dieser Website kann in bestimmten Rechtsordnungen eingeschränkt sein. Es liegt in der Verantwortung jeder Person, die auf diese Website zugreift, die geltenden Gesetze und Vorschriften ihrer eigenen Rechtsordnung einzuhalten.</p>
<h2>Kein Angebot und keine Aufforderung</h2>
<p>Nichts auf dieser Website stellt ein Angebot zum Verkauf oder eine Aufforderung zur Abgabe eines Angebots zum Kauf von Wertpapieren oder Finanzprodukten dar. Jede Anlage in die Fonds sollte auf der Grundlage des aktuellen Prospekts und des entsprechenden Basisinformationsblatts (KIID) oder PRIIPs-Basisinformationsblatts (KID) erfolgen, die im Dokumenten-Hub oder auf Anfrage beim Investmentmanager erhältlich sind.</p>
<h2>Geistiges Eigentum</h2>
<p>Alle Inhalte dieser Website, einschließlich Texte, Grafiken, Logos, Bilder, Datensammlungen und Software, sind Eigentum von Mori Capital Management Ltd. oder ihren Lizenzgebern und durch Urheberrechts- und Immaterialgüterrechtsgesetze geschützt.</p>
<h2>Aufsichtsbehörde kontaktieren</h2>
<p>Malta Financial Services Authority (MFSA)<br>Triq l-Imdina, Zone 1<br>Central Business District, Birkirkara, CBD 1010, Malta<br>Website: www.mfsa.mt</p>' WHERE slug = 'legal' AND locale = 'de';

-- 2) PRIVACY (EN was a one-line stub): mirror the full German GDPR policy into English.
UPDATE pages SET body = '<h2>Privacy Policy</h2>
<p>This privacy policy explains how Mori Capital Management Ltd. collects, uses and protects personal information.</p>
<h3>What data we collect</h3>
<p>We collect basic contact information (name, email address, telephone number) that you submit to us via the contact form or through direct correspondence, as well as technical data (IP address, browser type, visit data) through our web-analytics tools.</p>
<h3>How we use your data</h3>
<p>We use your personal data solely to respond to your enquiries, to provide you with requested information about our funds, and to meet our legal obligations under MFSA supervision.</p>
<h3>Data retention</h3>
<p>Personal data is retained for as long as necessary to fulfil the purposes for which it was collected, or to comply with legal and regulatory requirements.</p>
<h3>Your rights</h3>
<p>Under the GDPR, you have the right to access, rectify and erase your personal data, and to restrict or object to its processing. Contact us at info@mori-capital.com for any requests.</p>' WHERE slug = 'privacy' AND locale = 'en';

-- 3) COOKIES (EN was a one-line stub): mirror the full German cookie policy into English.
UPDATE pages SET body = '<h2>Cookie Policy</h2>
<p>This website uses cookies to provide essential site functionality and to analyse traffic anonymously.</p>
<h3>Necessary cookies</h3>
<p>These cookies are essential for the operation of the website (for example, to store your language preference and your acceptance of the investor agreement). Without them, the website cannot function properly.</p>
<h3>Analytics cookies</h3>
<p>If you consent, we use Google Analytics to measure anonymously how visitors interact with the website. No personal data is transmitted to Google.</p>
<h3>Managing cookies</h3>
<p>You can block or delete cookies at any time via your browser settings. Please note that blocking essential cookies may impair the functionality of the website.</p>' WHERE slug = 'cookies' AND locale = 'en';

-- 4) About (EN): add the co-investment alignment fact (already stated in German).
UPDATE pages SET body = REPLACE(body, 'fully aligned with our investors.', 'fully aligned with our investors. We co-invest alongside our clients in every fund we manage.')
 WHERE slug = 'about' AND locale = 'en';

-- 5) Refresh 'last updated' on the synced legal pages so EN and DE show the same date.
UPDATE pages SET updated_at = NOW() WHERE slug IN ('legal','privacy','cookies');
