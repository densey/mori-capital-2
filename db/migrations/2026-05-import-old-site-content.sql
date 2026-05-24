-- =============================================================================
-- Migration: Import real documents from old mori-capital.com backup
-- Team photos are now local (assets/images/team/), documents in uploads/.
-- =============================================================================

-- ============================================================
-- 1. Team photos — switch from remote URLs to local files
-- ============================================================
UPDATE team_members SET photo_path = 'assets/images/team/aziz-unan.jpg'                  WHERE slug = 'aziz-unan';
UPDATE team_members SET photo_path = 'assets/images/team/ozlem-tumer-eke.jpg'             WHERE slug = 'ozlem-tumer-eke';
UPDATE team_members SET photo_path = 'assets/images/team/desmond-riordan.jpg'             WHERE slug = 'desmond-riordan';
UPDATE team_members SET photo_path = 'assets/images/team/isidro-garcia-de-la-torre.jpg'   WHERE slug = 'isidro-garcia-de-la-torre';
UPDATE team_members SET photo_path = 'assets/images/team/peter-zurhorst.jpg'              WHERE slug = 'peter-zurhorst';
UPDATE team_members SET photo_path = 'assets/images/team/jean-paul-gauci.jpg'             WHERE slug = 'jean-paul-gauci';

-- ============================================================
-- 2. Fund update documents (quarterly reports)
-- ============================================================
SET @ee  = (SELECT id FROM funds WHERE slug = 'mori-eastern-european-fund');
SET @ott = (SELECT id FROM funds WHERE slug = 'mori-ottoman-fund');

INSERT INTO documents (fund_id, document_type, title, file_path, file_name, document_date, locale) VALUES
(@ee,  'other', 'Mori Eastern European Fund Update — December 2025', 'fund-updates/Mori-Eastern-European-Fund-Update-December-2025-FINAL.pdf', 'Mori-Eastern-European-Fund-Update-December-2025-FINAL.pdf', '2025-12-31', 'en'),
(@ott, 'other', 'Mori Ottoman Fund Update — December 2025', 'fund-updates/Mori-Ottoman-Fund-Update-December-2025-FINAL.pdf', 'Mori-Ottoman-Fund-Update-December-2025-FINAL.pdf', '2025-12-31', 'en'),
(@ee,  'other', 'Mori Eastern European Fund Update — September 2025', 'fund-updates/Mori-Eastern-European-Fund-Update-September-2025-FINAL.pdf', 'Mori-Eastern-European-Fund-Update-September-2025-FINAL.pdf', '2025-09-30', 'en'),
(@ott, 'other', 'Mori Ottoman Fund Update — September 2025', 'fund-updates/Mori-Ottoman-Fund-Update-September-2025-FINAL.pdf', 'Mori-Ottoman-Fund-Update-September-2025-FINAL.pdf', '2025-09-30', 'en'),
(@ee,  'other', 'Mori Eastern European Fund Update — June 2025', 'fund-updates/Mori-Eastern-European-Fund-Update-June-2025-FINAL.pdf', 'Mori-Eastern-European-Fund-Update-June-2025-FINAL.pdf', '2025-06-30', 'en'),
(@ott, 'other', 'Mori Ottoman Fund Update — June 2025', 'fund-updates/Mori-Ottoman-Fund-Update-June-2025-FINAL.pdf', 'Mori-Ottoman-Fund-Update-June-2025-FINAL.pdf', '2025-06-30', 'en'),
(@ee,  'other', 'Mori Eastern European Fund Update — December 2024', 'fund-updates/Mori-Eastern-European-Fund-Update-December-2024-FINAL.pdf', 'Mori-Eastern-European-Fund-Update-December-2024-FINAL.pdf', '2024-12-31', 'en'),
(@ott, 'other', 'Mori Ottoman Fund Update — December 2024', 'fund-updates/Mori-Ottoman-Fund-Update-December-2024-FINAL.pdf', 'Mori-Ottoman-Fund-Update-December-2024-FINAL.pdf', '2024-12-31', 'en'),
-- DE versions
(@ee,  'other', 'Mori Eastern European Fund Update — März 2025 (Deutsch)', 'fund-updates/Mori-Eastern-European-Fund-Update-March-2025-FINAL-DEUTSCH.pdf', 'Mori-Eastern-European-Fund-Update-March-2025-FINAL-DEUTSCH.pdf', '2025-03-31', 'de'),
(@ott, 'other', 'Mori Ottoman Fund Update — März 2025 (Deutsch)', 'fund-updates/Mori-Ottoman-Fund-Update-March-2025-FINAL-DEUTSCH.pdf', 'Mori-Ottoman-Fund-Update-March-2025-FINAL-DEUTSCH.pdf', '2025-03-31', 'de'),
(@ee,  'other', 'Mori Eastern European Fund Update — Dezember 2025 (Deutsch)', 'fund-updates/Mori-Eastern-European-Fund-Update-December-2025-FINAL-DEUTSCH.pdf', 'Mori-Eastern-European-Fund-Update-December-2025-FINAL-DEUTSCH.pdf', '2025-12-31', 'de'),
(@ee,  'other', 'Mori Eastern European Fund Update — Dezember 2024 (Deutsch)', 'fund-updates/Mori-Eastern-European-Fund-Update-December-2024-FINAL-DEUTSCH.pdf', 'Mori-Eastern-European-Fund-Update-December-2024-FINAL-DEUTSCH.pdf', '2024-12-31', 'de');

-- ============================================================
-- 3. Audited / Interim accounts (umbrella-level, no fund_id)
-- ============================================================
INSERT INTO documents (fund_id, document_type, title, file_path, file_name, document_date, locale) VALUES
(NULL, 'annual',      'Mori Umbrella Fund plc — Audited Financial Statements 30 Sep 2025', 'accounts/Mori-Umbrella-Fund-plc_Audited-Financial-Statements-30-09-2025_signed-1.pdf', 'Audited-Financial-Statements-30-09-2025.pdf', '2025-09-30', 'en'),
(NULL, 'annual',      'Mori Umbrella Fund plc — Geprüfter Jahresabschluss 30 Sep 2025 (Deutsch)', 'accounts/Mori-Umbrella-Fund-plc_Audited-Financial-Statements-30-09-2025_DEUTSCH.pdf', 'Audited-Financial-Statements-30-09-2025-DE.pdf', '2025-09-30', 'de'),
(NULL, 'semi_annual', 'Mori Umbrella Fund plc — Interim Financial Statements 31 Mar 2026', 'accounts/Mori-Umbrella-Fund-plc-Interim-Finanical-Statements-31-03-2026_FINAL.pdf', 'Interim-Financial-Statements-31-03-2026.pdf', '2026-03-31', 'en'),
(NULL, 'annual',      'Mori Umbrella Fund plc — Audited Financial Statements 30 Sep 2024', 'accounts/Mori-Umbrella-Fund-plc_Audited-Financials-Statements_30-09-2024.pdf', 'Audited-Financial-Statements-30-09-2024.pdf', '2024-09-30', 'en');

-- ============================================================
-- 4. Prospectus
-- ============================================================
INSERT INTO documents (fund_id, document_type, title, file_path, file_name, document_date, locale) VALUES
(NULL, 'prospectus', 'Mori Umbrella Fund plc — Prospectus (Deutsch)', 'prospectus/Mori-Umbrella-Fund-plc-Prospectus_DE.pdf', 'Mori-Prospectus-DE.pdf', '2024-01-01', 'de');

-- ============================================================
-- 5. Policy documents (umbrella-level)
-- ============================================================
INSERT INTO documents (fund_id, document_type, title, file_path, file_name, document_date, locale) VALUES
(NULL, 'other', 'ESG Policy — Responsible Investment Policy', 'policies/ESG-Policy-Responsible-Investment-Policy-31-01-25.pdf', 'ESG-Policy-31-01-25.pdf', '2025-01-31', 'any'),
(NULL, 'other', 'Conflicts of Interest and Anti-Bribery Policy', 'policies/Conflicts-of-Interest-and-Anti-Bribery-Policy-31-01-25.pdf', 'Conflicts-of-Interest-Policy-31-01-25.pdf', '2025-01-31', 'any'),
(NULL, 'other', 'Loss of Investor Rights Statement', 'policies/Loss-of-Investor-Rights-Statement-31-01-25.pdf', 'Loss-of-Investor-Rights-31-01-25.pdf', '2025-01-31', 'any'),
(NULL, 'other', 'Sanctions Policy', 'policies/Sanctions-Policy-31-01-25.pdf', 'Sanctions-Policy-31-01-25.pdf', '2025-01-31', 'any'),
(NULL, 'other', 'Whistleblowing and Anti-Retaliation Policy', 'policies/Whistleblowing-and-Anti-Retaliation-Policy-31-01-25.pdf', 'Whistleblowing-Policy-31-01-25.pdf', '2025-01-31', 'any');
