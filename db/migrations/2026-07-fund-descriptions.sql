-- 2026-07-fund-descriptions.sql
-- Fund description / objective wording supplied by Desmond (Jul 2026).
-- These are the EN base texts. Editable afterwards in Admin → Funds & Share
-- Classes (seed.sql no longer overwrites them). German versions are managed
-- via the Translation Center / fund editor.

UPDATE funds
   SET description_en = 'A long-only UCITS equity fund (sub-fund of Mori Umbrella Fund plc) investing across Central and Eastern Europe since 1998. As one of the longest lasting track records in the region, it reflects deep local knowledge, on-the-ground research, and patient stock selection across the region''s most compelling stories.'
 WHERE slug = 'mori-eastern-european-fund';

UPDATE funds
   SET description_en = 'An UCITS equity strategy (sub-fund of Mori Umbrella Fund plc) focused on Emerging Europe, MENA and CIS countries. Mori Ottoman Fund has been managed by the same portfolio manager, Aziz Unan, since its inception in January 2006.',
       objective_en   = 'To achieve long-term capital appreciation by investing primarily in equities of companies domiciled in or with significant operations in Emerging Europe and the broader MENA region.'
 WHERE slug = 'mori-ottoman-fund';
