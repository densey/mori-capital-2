-- 2026-07-doc-show-on-fund.sql
-- Fund detail pages (fund-eastern-european.php / fund-ottoman.php) used to
-- list a fund's latest 12 uploads regardless of language. Two problems:
--   * language was ignored (DE docs showed on the EN site and vice-versa)
--   * there was no way to choose WHICH documents appear in that teaser.
--
-- The language filter is handled in code. This migration adds an explicit
-- opt-in flag so the client controls exactly what shows on the fund page,
-- and seeds a sensible starting set.

-- Add the flag (MariaDB 10.2+ supports IF NOT EXISTS on ADD COLUMN).
ALTER TABLE documents ADD COLUMN IF NOT EXISTS show_on_fund_page TINYINT(1) NOT NULL DEFAULT 0;

-- Sensible starting point: show the fund-specific documents that belong to a
-- fund (Prospectus, KIID, PRIIPs, Annual / Semi-Annual, Factsheet). Company
-- Policies, Other Documents and Suspension Updates live on their own pages,
-- so they stay hidden from the fund teaser until the client opts them in.
UPDATE documents
   SET show_on_fund_page = 1
 WHERE fund_id IS NOT NULL
   AND (category = 'share_class' OR category IS NULL)
   AND document_type IN ('prospectus','kiid','priips','annual','semi_annual','factsheet');
