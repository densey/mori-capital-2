-- 2026-07-document-titles-de.sql
-- German titles for the document-list pages (Company Policies, Other
-- Documents, Suspension Updates). The documents table stores a single `title`
-- shown to every locale; this adds an optional `title_de` that the German
-- site prefers when set. The English site is UNAFFECTED (it always uses
-- `title`), so locale='any' regulatory PDFs keep their English name on /en.
-- Titles matched verbatim against the live values; underlying PDFs unchanged.

ALTER TABLE documents ADD COLUMN IF NOT EXISTS title_de VARCHAR(255) NULL AFTER title;

-- Policies, statements & financial reports
UPDATE documents SET title_de = 'Best-Execution-Grundsätze (31.01.2026)' WHERE title = 'Best Execution Policy (31.01.2026)';
UPDATE documents SET title_de = 'Richtlinie zu Interessenkonflikten und Korruptionsbekämpfung (31.01.2026)' WHERE title = 'Conflicts of Interest and Anti-Bribery Policy (31.01.2026)';
UPDATE documents SET title_de = 'Engagement- und Stewardship-Richtlinie (31.01.2026)' WHERE title = 'Engagement and Stewardship Policy (31.01.2026)';
UPDATE documents SET title_de = 'Engagement- und Stewardship-Bericht 2025 (31.01.2026)' WHERE title = 'Engagement and Stewardship Report 2025 (31.01.2026)';
UPDATE documents SET title_de = 'ESG-Richtlinie – Richtlinie für verantwortungsbewusstes Investieren (31.01.2026)' WHERE title = 'ESG Policy - Responsible Investment Policy (31.01.2026)';
UPDATE documents SET title_de = 'Erklärung zum Verlust von Anlegerrechten (31.01.2026)' WHERE title = 'Loss of Investor Rights Statement (31.01.2026)';
UPDATE documents SET title_de = 'Auslagerungsrichtlinie (31.01.2026)' WHERE title = 'Outsourcing Policy (31.01.2026)';
UPDATE documents SET title_de = 'PAI-Erklärung (31.01.2026)' WHERE title = 'PAIs Statement (31.01.2026)';
UPDATE documents SET title_de = 'Richtlinie zu Datenschutz, Datenverarbeitung und Datenaufbewahrung (31.01.2026)' WHERE title = 'Privacy - Data Protection - Data Retention Policy (31.01.2026)';
UPDATE documents SET title_de = 'Vergütungsrichtlinie (31.01.2026)' WHERE title = 'Remuneration Policy (31.01.2026)';
UPDATE documents SET title_de = 'Research-Richtlinie (31.01.2026)' WHERE title = 'Research Policy (31.01.2026)';
UPDATE documents SET title_de = 'Erklärung zur Risikobereitschaft – Mori Capital Management Limited (31.01.2026)' WHERE title = 'Risk Appetite Statement - Mori Capital Management Limited (31.01.2026)';
UPDATE documents SET title_de = 'Sanktionsrichtlinie (31.01.2026)' WHERE title = 'Sanctions Policy (31.01.2026';
UPDATE documents SET title_de = 'Whistleblowing- und Antivergeltungsrichtlinie (31.01.2026)' WHERE title = 'Whistleblowing and Anti-Retaliation Policy (31.01.2026)';
UPDATE documents SET title_de = 'Mori Umbrella Fund plc – Zwischenbericht 31. März 2026' WHERE title = 'Mori Umbrella Fund plc - Interim Financials 31 March 2026';
UPDATE documents SET title_de = 'Mori Umbrella Fund plc – Britische Steuerberichterstattung für das Geschäftsjahr zum 30. September 2025' WHERE title = 'Mori Umbrella Fund plc - UK Tax reporting for the year ended September 30th 2025';
UPDATE documents SET title_de = 'Mori Umbrella Fund plc – Ex-ante-Kostenoffenlegung 2025' WHERE title = 'Mori Umbrella Fund plc - Ex-Ante Costs Disclosure 2025';
UPDATE documents SET title_de = 'Sanktionsrichtlinie (31.01.25)' WHERE title = 'Sanctions Policy (31.01.25)';
UPDATE documents SET title_de = 'Best-Execution-Grundsätze (31.01.25)' WHERE title = 'Best Execution Policy (31.01.25)';
UPDATE documents SET title_de = 'Richtlinie zu Interessenkonflikten und Korruptionsbekämpfung (31.01.25)' WHERE title = 'Conflicts of interest and Anti-Bribery Policy (31.01.25)';
UPDATE documents SET title_de = 'Engagement- und Stewardship-Bericht 2024 (31.01.25)' WHERE title = 'Engagement and Stewardship Report 2024 (31.01.25)';
UPDATE documents SET title_de = 'Engagement- und Stewardship-Richtlinie (31.01.25)' WHERE title = 'Engagement and Stewardship Policy (31.01.25)';
UPDATE documents SET title_de = 'ESG-Richtlinie – Richtlinie für verantwortungsbewusstes Investieren (31.01.25)' WHERE title = 'ESG Policy – Responsible Investment Policy (31.01.25)';
UPDATE documents SET title_de = 'Erklärung zum Verlust von Anlegerrechten (31.01.25)' WHERE title = 'Loss of Investor Rights Statement (31.01.25)';
UPDATE documents SET title_de = 'Auslagerungsrichtlinie (31.01.25)' WHERE title = 'Outsourcing Policy (31.01.25)';
UPDATE documents SET title_de = 'PAI-Erklärung (31.01.25)' WHERE title = 'PAIs Statement (31.01.25)';
UPDATE documents SET title_de = 'Vergütungsrichtlinie (31.01.25)' WHERE title = 'Remuneration Policy (31.01.25)';
UPDATE documents SET title_de = 'Research-Richtlinie (31.01.25)' WHERE title = 'Research Policy (31.01.25)';
UPDATE documents SET title_de = 'Erklärung zur Risikobereitschaft (31.01.25)' WHERE title = 'Risk Appetite Statement (31.01.25)';
UPDATE documents SET title_de = 'Whistleblowing- und Antivergeltungsrichtlinie (31.01.25)' WHERE title = 'Whistleblowing and Anti-Retaliation Policy (31.01.25)';
UPDATE documents SET title_de = 'Mori Umbrella Fund plc – Geprüfter Jahresabschluss 30. September 2025' WHERE title = 'MMori Umbrella Fund plc – Audited Financial Statements 30 September 2025';
UPDATE documents SET title_de = 'Mori Umbrella Fund plc – Zwischenbericht 31. März 2025' WHERE title = 'Mori Umbrella Fund plc - Interim Financials 31 March 2025';
UPDATE documents SET title_de = 'Mori Umbrella Fund plc – Britische Steuerberichterstattung für das Geschäftsjahr zum 30. September 2024' WHERE title = 'Mori Umbrella Fund plc - UK Tax reporting for the year ended September 30th 2024';
UPDATE documents SET title_de = 'Ex-ante-Kostenoffenlegung 2024' WHERE title = 'Ex-Ante Costs Disclosure 2024';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update Juni 2024' WHERE title = 'Mori Ottoman Juni 2024';

-- Fund NAV updates (suspension archive)
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update März 2026' WHERE title = 'Mori Eastern European Fund Update Marsch 2026';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update März 2026' WHERE title = 'Mori Ottoman Fund Update Marsch 2026';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update Dezember 2025' WHERE title = 'Mori Eastern European Fund Update December 2025';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update Dezember 2025' WHERE title = 'Mori Ottoman Fund Update December 2025';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update September 2025' WHERE title = 'Mori Eastern European Fund Update September 2025';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update September 2025' WHERE title = 'Mori Ottoman Fund Update September 2025';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update Juni 2025' WHERE title = 'Mori Eastern European Fund Update June 2025';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update Juni 2025' WHERE title = 'Mori Ottoman Fund Update June 2025';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update März 2025' WHERE title = 'Mori Eastern European Fund Update Marsch 2025';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update März 2025' WHERE title = 'Mori Ottoman Fund Update Marsch 2025';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update Dezember 2024' WHERE title = 'Mori Eastern European Fund Update December 2024';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update Dezember 2024' WHERE title = 'Mori Ottoman Fund Update December 2024';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update September 2024' WHERE title = 'Mori Eastern European Fund Update September 2024';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update September 2024' WHERE title = 'Mori Ottoman Fund Update September 2024';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update Juni 2024' WHERE title = 'Mori Eastern European Fund Update Juni 2024';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update März 2024' WHERE title = 'Mori Eastern European Fund Update Marsch DEUTSCH 2024';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update März 2024' WHERE title = 'Mori Ottoman Fund Update Marsch 2024';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update Dezember 2023' WHERE title = 'Mori Eastern European Fund Update Dezember 2023';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update Dezember 2023' WHERE title = 'Mori Ottoman Fund Update Dezember 2023';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update September 2023' WHERE title = 'Mori Eastern European Fund Update September 2023';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update September 2023' WHERE title = 'Mori Ottoman Fund Update September 2023';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update Juni 2023' WHERE title = 'Mori Eastern European Fund Update Juni 2023';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update Juni 2023' WHERE title = 'Mori Ottoman Fund Update Juni 2023';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Update März 2023' WHERE title = 'Mori Eastern European Fund Update Marsch 2023';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Update März 2023' WHERE title = 'Mori Ottoman Fund Update Marsch 2023';

-- Shareholder notices
UPDATE documents SET title_de = 'Aktionärsmitteilung – Mori Umbrella Fund, 14. September 2022' WHERE title = 'Shareholder Notice - Mori Umbrella Fund 14 September 2022 (Deutsch)';
UPDATE documents SET title_de = 'Mori Eastern European Fund – Aktionärsmitteilung, 1. März 2022' WHERE title = 'Mori Eastern European Fund - Shareholder Notice - 1 March 2022 (Deutsch)';
UPDATE documents SET title_de = 'Mori Ottoman Fund – Aktionärsmitteilung, 1. März 2022' WHERE title = 'Mori Ottoman Fund - Shareholder Notice - 1 March 2022 (Deutsch)';
