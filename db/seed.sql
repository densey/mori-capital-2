-- =============================================================================
-- Mori Capital Management — Seed Data
-- Run after schema.sql. Default admin password is set during install wizard.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Settings (defaults)
-- -----------------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_title',          'Mori Capital Management',                                  'general'),
('site_tagline',        'Specialists in EEMEA Markets',                             'general'),
('default_locale',      'en',                                                       'general'),
('available_locales',   'en,de',                                                    'general'),
('contact_email',       'info@mori-capital.com',                                    'contact'),
('contact_phone',       '+356 2033 0110',                                           'contact'),
('contact_address',     'Mori Capital Management Ltd.\nRegent House, Office 35\nBisazza Street, Sliema SLM 1640, Malta', 'contact'),
('mfsa_license',        'C66999',                                                   'compliance'),
('mfsa_authority',      'Malta Financial Services Authority',                       'compliance'),
('linkedin_url',        '#',                                                        'social'),
('google_analytics_id', '',                                                         'seo'),
('seo_default_title',   'Mori Capital Management — Specialists in EEMEA Markets',   'seo'),
('seo_default_desc',    'Mori Capital Management Ltd. - independent EEMEA specialist asset manager. Home of the Mori Eastern European Fund and Mori Ottoman Fund.', 'seo'),
('session_timeout_min', '60',                                                       'security'),
('password_min_length', '12',                                                       'security'),
('upload_max_mb',       '20',                                                       'uploads')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- -----------------------------------------------------------------------------
-- Funds (umbrella: Mori Umbrella Fund plc)
-- -----------------------------------------------------------------------------
INSERT INTO funds (slug, name_en, name_de, description_en, description_de, objective_en, launch_date, base_currency, benchmark, cover_image_path, status, display_order) VALUES
('mori-eastern-european-fund',
 'Mori Eastern European Fund',
 'Mori Eastern European Fund',
 'A long-only equity fund (sub-fund of Mori Umbrella Fund plc) investing across Central and Eastern Europe since 1998. Our 25-year track record reflects deep local knowledge, on-the-ground research, and patient stock selection across the region''s most compelling growth stories.',
 NULL,
 'To achieve long-term capital appreciation by investing primarily in equities of companies domiciled in or with significant operations across Central and Eastern Europe.',
 '1998-01-01', 'EUR', 'MSCI Emerging Europe 10/40',
 'assets/images/service/h6-service-1.webp',
 'active', 10),

('mori-ottoman-fund',
 'Mori Ottoman Fund',
 'Mori Ottoman Fund',
 'An equity strategy (sub-fund of Mori Umbrella Fund plc) focused on Türkiye and the broader Ottoman geography — including selected Middle East and North Africa exposure. Award-winning long-term track record.',
 NULL,
 'To achieve long-term capital appreciation by investing primarily in equities of companies domiciled in or with significant operations in Türkiye and the broader MENA region.',
 '2006-01-01', 'EUR', 'MSCI Türkiye',
 'assets/images/service/h6-service-2.webp',
 'active', 20)
-- Seed only creates the funds on first install; it must NOT overwrite fields
-- the client edits in the admin panel (name, benchmark, descriptions,
-- objective, …). Re-running install.php therefore leaves existing rows alone
-- and only touches the timestamp.
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- Share classes — ISINs from Desmond's FundHub spec (Jan 2026)
-- -----------------------------------------------------------------------------
INSERT INTO share_classes (fund_id, name, isin, currency, inception_date, status, display_order)
SELECT id, 'EE Class A EUR',  'IE0002787442', 'EUR', '1998-01-01', 'active', 10 FROM funds WHERE slug='mori-eastern-european-fund'
UNION ALL SELECT id, 'EE Class B EUR',  'IE00B53RTW70', 'EUR', '1998-01-01', 'active', 20 FROM funds WHERE slug='mori-eastern-european-fund'
UNION ALL SELECT id, 'EE Class AA GBP', 'IE00B74GCZ17', 'GBP', '1998-01-01', 'active', 30 FROM funds WHERE slug='mori-eastern-european-fund'
UNION ALL SELECT id, 'EE Class C GBP',  'IE00B762ZY72', 'GBP', '1998-01-01', 'active', 40 FROM funds WHERE slug='mori-eastern-european-fund'
UNION ALL SELECT id, 'EE Class M EUR',  'IE00BD03V952', 'EUR', '1998-01-01', 'active', 50 FROM funds WHERE slug='mori-eastern-european-fund'
UNION ALL SELECT id, 'Otto Class A EUR',  'IE00B0T0FN89', 'EUR', '2006-01-01', 'active', 10 FROM funds WHERE slug='mori-ottoman-fund'
UNION ALL SELECT id, 'Otto Class C USD',  'IE00B4XYZP64', 'USD', '2006-01-01', 'active', 20 FROM funds WHERE slug='mori-ottoman-fund'
UNION ALL SELECT id, 'Otto Class C EUR',  'IE00B8G12179', 'EUR', '2006-01-01', 'active', 30 FROM funds WHERE slug='mori-ottoman-fund'
UNION ALL SELECT id, 'Otto Class C GBP',  'IE00B87PYK12', 'GBP', '2006-01-01', 'active', 40 FROM funds WHERE slug='mori-ottoman-fund'
UNION ALL SELECT id, 'Otto Class AA GBP', 'IE00B87G5S97', 'GBP', '2006-01-01', 'active', 50 FROM funds WHERE slug='mori-ottoman-fund'
UNION ALL SELECT id, 'Otto Class M USD',  'IE00BJLC3Y24', 'USD', '2006-01-01', 'active', 60 FROM funds WHERE slug='mori-ottoman-fund'
-- IMPORTANT: only seed share classes on first install. Do NOT overwrite
-- name / currency / inception_date / display_order on re-run — those are
-- edited by the client in the admin panel (Funds & Share Classes), and an
-- overwrite here would silently revert their changes every time install.php
-- runs. Existing rows are left untouched; only the timestamp is bumped.
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- Team (English bios — DE can be added via admin panel)
-- -----------------------------------------------------------------------------
INSERT INTO team_members (slug, name, title_en, bio_en, photo_path, display_order, status) VALUES
('aziz-unan',                       'Aziz Unan',                'Director, Portfolio Manager',                'With more than 20 years of investment experience, Aziz has been managing the Mori Ottoman Fund since its launch in January 2006 — the best-performing regional fund in Emerging Europe (1st place over 10 years by Euro and Euro am Sonntag). Multiple industry awards: Morningstar, Sauren, Euro, Euro am Sonntag, Feri Ratings and Citywire. Equity analyst at Erste Bank in 1996. Head of Research at Wood & Company between 2002–2004. Founder of Unan Portföy Yönetimi A.Ş. BA in Business Administration from the Anglo-American University.', 'assets/images/team/aziz-unan.jpg', 10, 'active'),
('ozlem-tumer-eke',                 'Özlem Tümer Eke',          'Director, Portfolio Manager',                'Senior investment banking executive with more than 20 years of experience across Turkish capital markets. Started at İŞ Investment Securities Inc., ran institutional sales and trading teams and senior roles in IPOs and SPOs at Borsa İstanbul. 1999–2001: managed İşbank''s Luxembourg-based SICAV funds (USD 450 million AUM). 2009: joined Noego Partners Ltd. 2013: joined Aziz to establish Unan Portföy Yönetimi A.Ş. BA from Bilkent University.', 'assets/images/team/ozlem-tumer-eke.jpg', 20, 'active'),
('desmond-riordan',                 'Desmond Riordan',          'Director, Chief Operations Officer',          'Desmond has 22 years of finance industry experience. Consultant to Griffin Capital Management and Lynch Oil Shale Investments. 8 years at BNY Mellon AIS as Vice President in Valuations Department. Established and ran the Wrocław (Poland) satellite office for 3 years. Worked for AQR Capital and Bear Stearns in NYC. BComm (Banking & Finance) from University College Dublin; Certificate in Futures, Options and Financial Derivatives from Harvard.', 'https://www.mori-capital.com/uploads/team/desmond-riordan-resim-17-13-07.jpg', 30, 'active'),
('isidro-garcia-de-la-torre',       'Isidro Garcia De La Torre','Compliance / MLRO',                          'Isidro has 17 years of finance industry experience. 10 years at Bloomberg''s London Office, including a member of the Portfolio Trading Systems department (2006–2010) — responsible for implementing Bloomberg''s total portfolio management solution AIM. Then 6 years at Renaissance Asset Managers in Performance and Risk Management. BA from University of Vigo, MA from Anglia Ruskin University. AMLRO qualifications and Compliance courses in UCITS, Financial & Equity Derivatives and Valuation.', 'https://www.mori-capital.com/uploads/team/isidro-garcia-de-la-torre-resim-17-12-53.jpg', 40, 'active'),
('peter-zurhorst',                  'Dr. Peter Zurhorst',       'Business Development & Distribution',         'Joined Mori Capital Management in April 2016, based in Munich. Previously led Renasset Management''s business in German-speaking Europe since 2011. Prior to that, in charge of Janus Capital Group''s institutional business in the same region; responsible for opening Janus Capital''s Munich office in 2009. Head of Northern Europe at Scottish Widows Investment Partnership. 12 years in London corporate finance at Dresdner Kleinwort Wasserstein, UBS and Credit Suisse. Doctorate from University of St. Gallen.', 'https://www.mori-capital.com/uploads/team/dr-peter-zurhorst-resim-11-50-23.jpg', 50, 'active'),
('jean-paul-gauci',                 'Jean-Paul Gauci',          'Risk Manager',                                'Jean-Paul holds a Bachelor of Commerce from the University of Malta, a Masters in Investment and Finance (University of Strathclyde) and a Masters in Risk, Crisis and Resilience Management (University of Portsmouth). Started his finance career in 2012 as fund administrator at a leading Maltese fund administrator. In 2015 joined RiskCap International Limited; promoted to Head of Risk Services in 2023. Part-time lecturer at the University of Malta within the Banking and Finance Department at FEMA.', 'https://www.mori-capital.com/uploads/team/Jean-Paul%20Gauci_Headshot.jpg', 60, 'active')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- Pages (initial drafts, editable from admin)
-- -----------------------------------------------------------------------------
INSERT INTO pages (slug, locale, title, meta_title, meta_description, body, status) VALUES
('about',             'en', 'About Mori',         'About Mori Capital Management',                'Independent EEMEA specialist asset manager founded in 1998, headquartered in Malta.', '<p>Founded in 1998 and headquartered in Malta, Mori Capital Management is a dedicated investor in Emerging European, Middle Eastern and African equity markets.</p>', 'published'),
('investment-style',  'en', 'The Mori Style',     'Investment Style — The Mori Style',           'Disciplined research, active engagement — our investment philosophy.',                 '<p>Our investment philosophy is built on bottom-up stock picking with a macro overlay, in-house proprietary research, and active dialogue with company management.</p>', 'published'),
('contact',           'en', 'Contact',            'Contact Mori Capital',                         'Reach our Malta office.',                                                              '<p>Mori Capital Management Ltd. — Regent House, Office 35, Bisazza Street, Sliema SLM 1640, Malta.</p>', 'published'),
('legal',             'en', 'Legal & Disclaimer', 'Legal — Mori Capital Management',              'Full regulatory disclaimer and risk warnings.',                                        '<h2>Regulatory Information</h2><p>Mori Capital Management Ltd. is authorised and regulated by the Malta Financial Services Authority (MFSA) under Firm Reference C66999...</p>', 'published'),
('privacy',           'en', 'Privacy Policy',     'Privacy Policy — Mori Capital',                'How we handle personal data.',                                                         '<p>This privacy policy explains how we collect, use and protect personal information.</p>', 'published'),
('cookies',           'en', 'Cookie Policy',      'Cookie Policy — Mori Capital',                 'How and why we use cookies on this website.',                                          '<p>This website uses cookies to provide essential site functionality and to analyse traffic anonymously.</p>', 'published')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
