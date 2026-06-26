-- 2026-06-en-base-settings-backfill.sql
--
-- Earlier migrations populated only the *_de setting variants (Investment
-- Principles, homepage cinematic copy, hp_about_* etc.).  The English base
-- keys (without the _de suffix) were never inserted, so:
--   * admin/homepage.php shows the EN inputs empty
--   * the public EN site falls back to the PHP defaults instead of editable
--     content
--   * setting_i18n('hp_cine_title') on the EN locale returns NULL → default
--
-- This migration inserts the EN base value for every key that has a _de
-- companion.  It is conservative:
--   * INSERT … ON DUPLICATE KEY UPDATE setting_value = IF(empty, VALUES, keep)
--   * If a row already exists with a non-empty value (because someone
--     edited it through the admin), the existing value is preserved.
--   * If the row exists but is empty, the value is filled.
--   * If the row does not exist, it is created.

-- ---------------------------------------------------------------------------
-- 1. Investment Principles — EN titles, descriptions and FA icon classes
-- ---------------------------------------------------------------------------

INSERT INTO settings (setting_key, setting_value) VALUES
('principle_1_icon',  'fa-magnifying-glass-chart'),
('principle_2_icon',  'fa-route'),
('principle_3_icon',  'fa-flask'),
('principle_4_icon',  'fa-shield-halved'),
('principle_5_icon',  'fa-handshake'),

('principle_1_title', 'Bottom-up stock picking with macro overlay'),
('principle_1_desc',  'We start with fundamental company analysis, then test our conviction against the macro backdrop of each EEMEA market.'),

('principle_2_title', 'Walking the extra mile'),
('principle_2_desc',  'Active coverage of 200+ securities across the region — visiting management teams, factories and competitors on the ground.'),

('principle_3_title', 'In-house proprietary research'),
('principle_3_desc',  'No outsourced models. Every position is supported by our own valuation work, risk analysis and scenario testing.'),

('principle_4_title', 'Disciplined risk management'),
('principle_4_desc',  'Position sizing, liquidity monitoring and correlation overlays — rigorously applied at portfolio level.'),

('principle_5_title', 'Active dialogue with stakeholders'),
('principle_5_desc',  'Direct, ongoing engagement with company management, regulators, sell-side analysts and other shareholders.')
ON DUPLICATE KEY UPDATE
  setting_value = IF(setting_value IS NULL OR setting_value = '', VALUES(setting_value), setting_value);


-- ---------------------------------------------------------------------------
-- 2. Homepage — About / Funds / Style / Cinematic EN base copy
-- ---------------------------------------------------------------------------

INSERT INTO settings (setting_key, setting_value) VALUES
-- About
('hp_about_eyebrow',     'About Mori Capital'),
('hp_about_text',        'Founded in 1998 and headquartered in Malta, Mori Capital Management is a dedicated investor in Emerging European, Middle Eastern and African equity markets. We combine bottom-up stock picking with rigorous in-house research and active dialogue with company management.'),
('hp_about_quote',       "In the EEMEA region, knowledge isn't found in screens — it's earned by walking the extra mile."),

-- Funds
('hp_funds_desc',        'Our flagship vehicles offer differentiated access to distinct EEMEA market opportunities — both managed with the same disciplined, research-led approach.'),
('hp_funds_footer_note', 'Managed by portfolio managers with 20+ years of EEMEA experience.'),

-- Style
('hp_style_desc',        'Our investment philosophy is built on bottom-up stock picking with a macro overlay, in-house proprietary research, and active dialogue with company management.'),
('hp_style_bullet_1',    'Bottom-up stock picking with a top-down macro overlay across EEMEA markets'),
('hp_style_bullet_2',    'Active dialogue with company management and on-the-ground research visits'),
('hp_style_feature_1',   'In-house proprietary research'),
('hp_style_feature_2',   'Walking the extra mile — 200+ securities'),
('hp_style_feature_3',   'Disciplined risk management'),
('hp_style_quote',       "We don't just analyse companies — we visit them, walk their factories, and meet their stakeholders."),

-- Cinematic
('hp_cine_eyebrow',      'EEMEA in motion'),
('hp_cine_title',        'Disciplined investing,<br>powered by data.'),
('hp_cine_desc',         'Real-time portfolio analytics, on-the-ground research and active risk management — all converging into a single conviction-led process across Emerging European, Middle Eastern and African markets.')
ON DUPLICATE KEY UPDATE
  setting_value = IF(setting_value IS NULL OR setting_value = '', VALUES(setting_value), setting_value);


-- ---------------------------------------------------------------------------
-- 3. SEO defaults — EN base (in case admin/seo.php form was never saved)
-- ---------------------------------------------------------------------------

INSERT INTO settings (setting_key, setting_value) VALUES
('seo_default_title', 'Mori Capital Management — Specialists in EEMEA Markets'),
('seo_default_desc',  'Independent EEMEA-focused asset manager. Mori Eastern European Fund and Mori Ottoman Fund — research-led investing across Emerging Europe, the Middle East and Africa since 1998.')
ON DUPLICATE KEY UPDATE
  setting_value = IF(setting_value IS NULL OR setting_value = '', VALUES(setting_value), setting_value);
