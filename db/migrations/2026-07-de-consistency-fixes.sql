-- 2026-07-de-consistency-fixes.sql
-- Accuracy pass: align German content with the authoritative English where the
-- two had drifted apart (wrong facts, not just wording). Surgical REPLACEs so
-- nothing else in the body is touched; each is a no-op if the string is absent.

-- About page: firm was founded in 2015 (per the English page and Desmond's own
-- correction), and the 30-year record belongs to the founders, not the 2015 firm.
UPDATE pages SET body = REPLACE(body, 'wurde 1998 gegründet', 'wurde 2015 gegründet')
 WHERE slug = 'about' AND locale = 'de';
UPDATE pages SET body = REPLACE(body, 'mehr als 25 Jahre', 'mehr als 30 Jahre')
 WHERE slug = 'about' AND locale = 'de';
UPDATE pages SET body = REPLACE(body, 'Jahre hinweg haben wir tiefe lokale Netzwerke', 'Jahre hinweg haben die Gründer tiefe lokale Netzwerke')
 WHERE slug = 'about' AND locale = 'de';
