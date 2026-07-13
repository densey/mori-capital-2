-- 2026-07-seed-content-parity.sql
-- A fresh install (schema+seed+migrations) was still producing content that
-- staging had since corrected via admin edits: the ORIGINAL fabricated English
-- team bios (the very source the bad German translations came from), a 1998
-- founding year in the homepage blurb, 20+/25+ experience stats (approved: 30+),
-- a stale About sentence, and placeholder share-class inception dates.
-- Every statement is guarded to be a NO-OP on staging (which already carries
-- the corrected values) and only repairs the stale seed path.

-- 1) Team: replace bio_en with the approved English bios (matches staging).
UPDATE team_members SET bio_en = '<p>Aziz brings over 30 years of international experience from London, Prague and Istanbul as a financial analyst, strategist, head of research and portfolio manager in EEMEA markets. He is the founder of Mori Capital Management. Aziz has been managing the Mori Ottoman Fund since its launch in January 2006, which was awarded the best-performing regional fund in Emerging Europe (1st place over 10 years by Euro and Euro am Sonntag a number of times (2016 & 2017).  Aziz and the fund he manages received multiple industry awards from Morningstar, Sauren, Euro, Euro am Sonntag, Feri Ratings and Citywire. He holds a BA in Business Administration from the Anglo-American University.</p>' WHERE slug = 'aziz-unan';
UPDATE team_members SET bio_en = '<p>Ozlem brings more than 25+ years of experience across Turkish capital markets as a senior investment banking executive. She began her career at İŞ Investment Securities Inc., where she ran the institutional sales and trading teams and held senior roles in IPOs and SPOs at Borsa İstanbul. Between 1999 and 2001, Ozlem managed İşbank’s Luxembourg-based SICAV funds with USD 450 million in assets under management. In 2009, she joined Noego Partners Ltd., and in 2013 Ozlem joined Aziz to establish Unan Portföy Yönetimi A.Ş. She holds a BA from Bilkent University.</p>' WHERE slug = 'ozlem-tumer-eke';
UPDATE team_members SET bio_en = '<p>Desmond has over 25 years of experience in the international funds industry, with a strong background in fund accounting, operations and investment servicing. He has served as Chief Operations Officer of Mori Capital Management Limited since 2016. Desmond previously held a number of senior fund accounting positions with BNY Mellon in Ireland and PNC Global Investment Servicing in Ireland and Poland, where he established the firm’s Poland office in 2008. Prior to that, he held analyst positions at AQR Capital Management and Bear Stearns in New York City, after beginning his career at Merrill Lynch in Ireland. He holds a Bachelor of Commerce degree from University College Dublin.</p>' WHERE slug = 'desmond-riordan';
UPDATE team_members SET bio_en = '<p>Isidro has over 25 years of finance industry experience. Ten years in various roles at Bloomberg''s London Office, including being a member of the Portfolio Trading Systems department (2006–2010) — responsible for implementing Bloomberg''s total portfolio management solution AIM. He then spent 6 years at Renaissance Asset Managers in Performance and Risk Management before joining Mori Capital Management. Isidro holds a BA from University of Vigo and an MA from Anglia Ruskin University, as well as AMLRO and Compliance qualifications.</p>' WHERE slug = 'isidro-garcia-de-la-torre';
UPDATE team_members SET bio_en = '<p>Peter brings extensive international experience across asset management, institutional business development and corporate finance, with a strong focus on German-speaking Europe. He joined Mori Capital Management in April 2016 and is based in Munich. He previously led Renaissance Asset Managers’ business in German-speaking Europe from 2011, after having been responsible for Janus Capital Group’s institutional business in the same region, where he opened Janus Capital’s Munich office in 2009. Prior to that, he was Head of Northern Europe at Scottish Widows Investment Partnership and spent 12 years in corporate finance at Salomon Brothers, Lehman Brothers, CSFB and UBS. He holds a Doctorate and MBA from the University of St. Gallen (Dr. oec., lic. oec. HSG, Banking and Finance).</p>' WHERE slug = 'peter-zurhorst';
UPDATE team_members SET bio_en = '<p>Jean-Paul brings over a decade of experience in finance, risk services and fund administration. He began his finance career in 2012 as a fund administrator at a leading Maltese fund administrator before joining RiskCap International Limited in 2015, where he was promoted to Head of Risk Services in 2023. He is also a part-time lecturer at the University of Malta within the Banking and Finance Department at FEMA. Jean-Paul holds a Bachelor of Commerce from the University of Malta, a Master’s in Investment and Finance from the University of Strathclyde, and a Master’s in Risk, Crisis and Resilience Management from the University of Portsmouth.</p>' WHERE slug = 'jean-paul-gauci';

-- 2) Homepage settings: founding year + experience figures (approved values).
UPDATE settings SET setting_value = REPLACE(setting_value, 'Founded in 1998', 'Founded in 2015') WHERE setting_key = 'hp_about_text';
UPDATE settings SET setting_value = REPLACE(setting_value, 'wurde 1998 gegründet', 'wurde 2015 gegründet') WHERE setting_key = 'hp_about_text_de';
UPDATE settings SET setting_value = 'Managed by portfolio managers with 30+ years of EEMEA experience.' WHERE setting_key = 'hp_funds_footer_note' AND setting_value LIKE '%20+ years%';
UPDATE settings SET setting_value = 'Verwaltet von Portfoliomanagern mit über 30 Jahren EEMEA-Erfahrung.' WHERE setting_key = 'hp_funds_footer_note_de' AND setting_value LIKE '%20 Jahren%';
UPDATE settings SET setting_value = '30' WHERE setting_key = 'stat_years' AND setting_value = '25';

-- 3) About EN body: seeded sentence predates the approved wording.
UPDATE pages SET body = REPLACE(body, 'Over more than 25 years, we have built deep local networks,', 'Over more than 30 years, the founders have built local networks') WHERE slug = 'about' AND locale = 'en';

-- 4) Share classes: real inception months (seed had 1998-01-01 placeholders;
--    guarded so staging's admin-entered dates are never touched).
UPDATE share_classes SET inception_date = '1998-10-01' WHERE isin = 'IE0002787442' AND inception_date = '1998-01-01';
UPDATE share_classes SET inception_date = '2009-11-01' WHERE isin = 'IE00B53RTW70' AND inception_date = '1998-01-01';
UPDATE share_classes SET inception_date = '2012-03-01' WHERE isin = 'IE00B74GCZ17' AND inception_date = '1998-01-01';
UPDATE share_classes SET inception_date = '2012-03-01' WHERE isin = 'IE00B762ZY72' AND inception_date = '1998-01-01';
UPDATE share_classes SET inception_date = '2016-09-01' WHERE isin = 'IE00BD03V952' AND inception_date = '1998-01-01';
UPDATE share_classes SET inception_date = '2012-06-01' WHERE isin = 'IE00B4XYZP64' AND inception_date = '2006-01-01';
UPDATE share_classes SET inception_date = '2012-06-01' WHERE isin = 'IE00B8G12179' AND inception_date = '2006-01-01';
UPDATE share_classes SET inception_date = '2012-11-01' WHERE isin = 'IE00B87PYK12' AND inception_date = '2006-01-01';
UPDATE share_classes SET inception_date = '2013-05-01' WHERE isin = 'IE00B87G5S97' AND inception_date = '2006-01-01';
UPDATE share_classes SET inception_date = '2020-01-01' WHERE isin = 'IE00BJLC3Y24' AND inception_date = '2006-01-01';
