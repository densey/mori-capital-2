-- 2026-07-en-about-founding-year.sql
-- The EN About body seeded by 2026-05-complete-content.sql says "Founded in
-- 1998"; the approved wording (already live on staging via an admin edit) is
-- "Founded in 2015" — 1998 is the flagship fund's launch year, 2015 the firm's
-- incorporation. Fix the seed path so fresh installs match staging. No-op where
-- the body has already been corrected.
UPDATE pages SET body = REPLACE(body, 'Founded in 1998 and headquartered in Malta', 'Founded in 2015 and headquartered in Malta')
 WHERE slug = 'about' AND locale = 'en';
