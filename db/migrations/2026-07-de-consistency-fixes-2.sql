-- 2026-07-de-consistency-fixes-2.sql
-- Second accuracy pass from the full EN<->DE screen-by-screen audit. Aligns
-- German content to the authoritative English where facts had drifted.

-- 1) Eastern European Fund: German objective stated a different strategy
--    (concentrated portfolio / bottom-up / macro overlay) than the EN objective.
UPDATE funds SET objective_de = 'Erzielung eines langfristigen Kapitalwachstums durch Anlagen überwiegend in Aktien von Unternehmen, die in Mittel- und Osteuropa ansässig sind oder dort wesentliche Geschäftstätigkeiten ausüben.'
 WHERE slug = 'mori-eastern-european-fund';

-- 2) Homepage hero slide 2: DE narrowed EEMEA to just "Europe" (dropped ME & Africa).
UPDATE hero_slides SET title_de = 'Forschungsgetriebenes Investieren in der EEMEA-Region'
 WHERE display_order = 2;

-- 3) Investment Style: the DE body was 2 stale sections; the EN body has 5 detail
--    sections. Replace DE body with a faithful translation of all five.
UPDATE pages SET body = '<h2>Unsere Philosophie</h2>
<p>Wir glauben, dass EEMEA-Märkte strukturell ineffizient sind und aktiven Managern, die fundamentale Unternehmensanalyse mit tiefem regionalem Wissen verbinden, dauerhafte Chancen bieten. Unser Vorteil entsteht dadurch, dass wir die Extrameile gehen — Fabriken besuchen, Managementteams persönlich treffen und Beziehungen aufbauen, die uns einen Informationsvorsprung gegenüber bildschirmbasierten Investoren verschaffen.</p>

<h2>Bottom-up mit Makro-Overlay</h2>
<p>Jede Anlage beginnt mit einem Unternehmen. Wir durchsuchen unser Universum von über 200 an EEMEA-Börsen notierten Wertpapieren nach fundamentaler Qualität: starke Bilanzen, nachhaltige Wettbewerbsvorteile, fähiges Management und attraktive Bewertungen. Erst nachdem ein Unternehmen unseren Bottom-up-Filter passiert hat, legen wir den makroökonomischen Kontext darüber — Geldpolitik, Währungstrends, regulatorisches Umfeld und politisches Risiko.</p>

<h2>Proprietäre Forschung</h2>
<p>Die gesamte Forschung wird hausintern durchgeführt. Wir lagern unseren Anlageprozess nicht aus. Jede Position im Portfolio wird durch unsere eigenen Bewertungsmodelle, Szenarioanalysen und Risikobewertungen gestützt. Dies gewährleistet Konsistenz, Rechenschaftspflicht und Entscheidungsgeschwindigkeit.</p>

<h2>Aktives Engagement</h2>
<p>Wir pflegen einen direkten, kontinuierlichen Dialog mit den Managementteams unserer Portfoliounternehmen. Wir nehmen an Hauptversammlungen teil, bitten bei Non-Deal-Roadshows um Gespräche und engagieren uns konstruktiv bei Themen der Unternehmensführung, Kapitalallokation und ESG.</p>

<h2>Risikomanagement</h2>
<p>Diszipliniertes Risikomanagement ist in jeden Schritt eingebettet. Die Positionsgrößenbestimmung basiert auf Überzeugung und Liquidität. Die Überwachung auf Portfolioebene umfasst Korrelationsanalysen, Sektorkonzentrationsgrenzen und Währungsengagement-Overlays. Wir setzen keine Hebelwirkung ein.</p>'
 WHERE slug = 'investment-style' AND locale = 'de';

-- 4) Media 2019 award card: DE misspelled the fund name ("Europe" -> "European").
UPDATE media_items SET title_de = 'Mori Eastern European Fund erhält Auszeichnung von €uro / €uro am Sonntag'
 WHERE title_de = 'Mori Eastern Europe Fund erhält Auszeichnung von €uro / €uro am Sonntag';

-- 5) Media 2020 ESG card: EN was a loose paraphrase; the release (both editions)
--    and the accurate DE say the fund SIGNED the UN-supported PRI. Align EN.
UPDATE media_items SET title_en = 'Mori Umbrella Fund signs UN-supported Principles for Responsible Investment'
 WHERE title_en = 'Mori Umbrella Fund increases its commitment to ESG principles';
