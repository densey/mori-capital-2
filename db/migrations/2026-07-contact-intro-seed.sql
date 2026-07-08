-- 2026-07-contact-intro-seed.sql
-- The contact-page intro is now a setting (contact_intro / contact_intro_de).
-- Seed it with the office-hours-free copy so the field isn't empty — an empty
-- value falls back to the language file, which is why the old "Office hours…"
-- line kept showing. Only fills when the setting is missing or blank; a real
-- edit made in Admin -> Settings is preserved.

INSERT INTO settings (setting_key, setting_value) VALUES
('contact_intro',    'Investors, intermediaries and journalists can reach us via the form, by phone or by email.'),
('contact_intro_de', 'Anleger, Vermittler und Journalisten erreichen uns über das Formular, telefonisch oder per E-Mail.')
ON DUPLICATE KEY UPDATE
    setting_value = IF(setting_value IS NULL OR setting_value = '', VALUES(setting_value), setting_value);
