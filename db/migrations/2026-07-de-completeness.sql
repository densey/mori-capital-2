-- 2026-07-de-completeness.sql
-- End-to-end DE completeness pass.
--
-- 1) The July fund-description update (2026-07-fund-descriptions.sql) changed the
--    English wording but left the German columns holding the OLD text, so the DE
--    fund pages were showing stale descriptions/objectives. Re-translate them to
--    match Desmond's current EN wording (UCITS sub-fund framing, Emerging Europe /
--    MENA / CIS, Aziz Unan since Jan 2006, "one of the longest lasting track
--    records").
-- 2) Two of Mori's OWN press releases had no German title, so the DE Media page
--    fell back to the English headline. External/third-party article headlines are
--    intentionally left in their original published language (they are real cited
--    headlines) — only Mori-authored items are translated here.
--
-- All statements are safe to re-run: they set explicit values by stable key.

-- ---------------------------------------------------------------------------
-- FUNDS — refresh description_de / objective_de to the current EN wording
-- ---------------------------------------------------------------------------

-- Mori Eastern European Fund: only the description changed in EN (objective kept).
UPDATE funds
   SET description_de = 'Ein Long-Only-UCITS-Aktienfonds (Teilfonds der Mori Umbrella Fund plc), der seit 1998 in Mittel- und Osteuropa investiert. Als eine der am längsten bestehenden Erfolgsbilanzen der Region spiegelt er tiefes lokales Wissen, Recherche vor Ort und geduldige Aktienauswahl in den überzeugendsten Titeln der Region wider.'
 WHERE slug = 'mori-eastern-european-fund';

-- Mori Ottoman Fund: both description and objective changed in EN.
UPDATE funds
   SET description_de = 'Eine UCITS-Aktienstrategie (Teilfonds der Mori Umbrella Fund plc) mit Fokus auf Emerging Europe, die MENA-Region und die GUS-Staaten. Der Mori Ottoman Fund wird seit seiner Auflegung im Januar 2006 durchgehend vom selben Portfoliomanager, Aziz Unan, verwaltet.',
       objective_de   = 'Erzielung eines langfristigen Kapitalwachstums durch Anlagen überwiegend in Aktien von Unternehmen, die in Emerging Europe und der weiteren MENA-Region ansässig sind oder dort wesentliche Geschäftstätigkeiten ausüben.'
 WHERE slug = 'mori-ottoman-fund';

-- ---------------------------------------------------------------------------
-- MEDIA — add German titles for Mori's own press releases that were EN-only
-- ---------------------------------------------------------------------------

UPDATE media_items
   SET title_de = 'COVID-19-Pandemiemaßnahmen und Geschäftskontinuität'
 WHERE source = 'Mori Capital Management'
   AND title_en = 'COVID-19 Pandemic Measures and Business Continuity'
   AND (title_de IS NULL OR title_de = '');

UPDATE media_items
   SET title_de = 'Preisgekrönte Emerging-Europe-Fonds in Mori Umbrella Fund plc umbenannt'
 WHERE source = 'Mori Capital Management'
   AND title_en = 'Award-winning emerging Europe funds renamed Mori Umbrella Fund Plc.'
   AND (title_de IS NULL OR title_de = '');
