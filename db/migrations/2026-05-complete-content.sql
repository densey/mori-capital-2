-- =============================================================================
-- Migration: Complete content — fill every page so no screen looks empty
-- Run after schema + seed + german-pages migrations.
-- =============================================================================

-- ============================================================
-- 1. INSIGHTS — 3 EN + 3 DE published (homepage shows latest 3)
-- ============================================================
INSERT INTO insights (slug, locale, category, title, excerpt, body, publish_date, status, created_by) VALUES
('q1-2026-eemea-outlook', 'en', 'outlook',
 'Q1 2026 EEMEA Outlook: Selective Opportunities Amid Divergence',
 'Why Eastern European equities continue to offer compelling valuations and where we see asymmetric upside in the Turkish market.',
 '<p>The first quarter of 2026 has presented a mixed picture across EEMEA markets. While Central European indices have benefited from improving macro fundamentals and EU fund inflows, Turkish equities remain volatile amid currency fluctuations and shifting monetary policy.</p>
<h2>Eastern Europe: Structural Re-rating Underway</h2>
<p>Poland, Czech Republic and Hungary continue their structural convergence trade. Corporate earnings growth of 12-15% is supported by wage growth, EU cohesion fund disbursements, and improving current accounts. We remain overweight Polish financials and Czech industrials.</p>
<h2>Türkiye: Selective Conviction</h2>
<p>The Turkish equity market offers some of the most asymmetric opportunities in the region. We are focused on export-oriented industrials and tourism-linked consumer names where earnings are dollar-denominated but costs remain partially in lira.</p>
<h2>Risk Factors</h2>
<p>Key risks include geopolitical escalation in the broader MENA region, a potential reversal of ECB rate cuts impacting CEE growth expectations, and idiosyncratic policy risk in Türkiye. Our portfolio remains diversified across 45-55 names with an average position size of 2-3%.</p>',
 '2026-04-15', 'published', NULL),

('ottoman-fund-q4-2025-factsheet', 'en', 'factsheet',
 'Mori Ottoman Fund — Q4 2025 Factsheet',
 'Quarterly performance review, top holdings, sector allocation and portfolio manager commentary for the Mori Ottoman Fund.',
 '<p>The Mori Ottoman Fund delivered a strong Q4 2025, returning +8.3% (Class A EUR) against +5.1% for the MSCI Turkey index. Full-year 2025 performance was +22.7% versus +14.2% for the benchmark.</p>
<h2>Top Contributors</h2>
<ul>
<li><strong>BIM Birleşik Mağazalar</strong> (+14.2%): Continued market share gains in discount retail</li>
<li><strong>Türk Hava Yolları</strong> (+18.7%): Record passenger volumes and yield improvement</li>
<li><strong>Koç Holding</strong> (+11.4%): Conglomerate discount narrowing as automotive and energy divisions outperformed</li>
</ul>
<h2>Sector Allocation</h2>
<p>Financials 32% | Industrials 24% | Consumer 18% | Energy 12% | Technology 8% | Other 6%</p>
<h2>Portfolio Manager Commentary</h2>
<p>We remain constructive on the Turkish equity market for 2026. The normalisation of monetary policy, combined with improving corporate governance standards and a growing domestic institutional investor base, provides a supportive backdrop for stock selection.</p>',
 '2026-03-31', 'published', NULL),

('shareholder-notice-agm-2026', 'en', 'shareholder_notice',
 'Notice to Shareholders — Annual General Meeting 2026',
 'Convocation, agenda and proxy voting instructions for the Mori Funds Annual General Meeting to be held in Sliema, Malta.',
 '<p>Notice is hereby given that the Annual General Meeting of Mori Umbrella Fund plc will be held at the registered office, Regent House, Office 35, Bisazza Street, Sliema SLM 1640, Malta, on <strong>Thursday, 20 June 2026 at 11:00 CET</strong>.</p>
<h2>Agenda</h2>
<ol>
<li>Chairman''s opening remarks</li>
<li>Approval of the audited financial statements for the year ended 31 December 2025</li>
<li>Re-appointment of auditors</li>
<li>Directors'' remuneration</li>
<li>Any other business</li>
</ol>
<h2>Proxy Voting</h2>
<p>Shareholders who are unable to attend may appoint a proxy by completing and returning the enclosed proxy form to the Fund Administrator no later than 48 hours before the meeting. Forms are available from the Document Hub or by contacting <a href="mailto:info@mori-capital.com">info@mori-capital.com</a>.</p>
<h2>Record Date</h2>
<p>The record date for determining shareholders entitled to vote is 6 June 2026.</p>',
 '2026-02-22', 'published', NULL)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    excerpt = VALUES(excerpt),
    body = VALUES(body),
    status = 'published',
    updated_at = CURRENT_TIMESTAMP;

-- DE insights
INSERT INTO insights (slug, locale, category, title, excerpt, body, publish_date, status, created_by) VALUES
('q1-2026-eemea-outlook', 'de', 'outlook',
 'Q1 2026 EEMEA-Ausblick: Selektive Chancen bei Divergenz',
 'Warum osteuropäische Aktien weiterhin attraktive Bewertungen bieten und wo wir asymmetrisches Aufwärtspotenzial im türkischen Markt sehen.',
 '<p>Das erste Quartal 2026 zeigte ein gemischtes Bild über die EEMEA-Märkte. Während mitteleuropäische Indizes von verbesserten Makro-Fundamentaldaten und EU-Fondszuflüssen profitiert haben, bleiben türkische Aktien angesichts von Währungsschwankungen und sich ändernder Geldpolitik volatil.</p>
<h2>Osteuropa: Strukturelle Neubewertung</h2>
<p>Polen, Tschechien und Ungarn setzen ihren strukturellen Konvergenzhandel fort. Das Wachstum der Unternehmensgewinne von 12-15% wird durch Lohnwachstum, EU-Kohäsionsfonds und verbesserte Leistungsbilanzen unterstützt.</p>',
 '2026-04-15', 'published', NULL),

('ottoman-fund-q4-2025-factsheet', 'de', 'factsheet',
 'Mori Ottoman Fund — Q4 2025 Factsheet',
 'Quartalsperformance, Top-Positionen, Sektorallokation und Kommentar des Portfoliomanagers.',
 '<p>Der Mori Ottoman Fund lieferte ein starkes Q4 2025 mit +8,3% (Klasse A EUR) gegenüber +5,1% für den MSCI Turkey Index.</p>',
 '2026-03-31', 'published', NULL),

('shareholder-notice-agm-2026', 'de', 'shareholder_notice',
 'Mitteilung an die Aktionäre — Hauptversammlung 2026',
 'Einberufung, Tagesordnung und Vollmachtserteilung für die Hauptversammlung der Mori Fonds in Sliema, Malta.',
 '<p>Hiermit wird mitgeteilt, dass die Hauptversammlung der Mori Umbrella Fund plc am <strong>Donnerstag, 20. Juni 2026 um 11:00 MEZ</strong> im eingetragenen Sitz stattfindet.</p>',
 '2026-02-22', 'published', NULL)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    excerpt = VALUES(excerpt),
    body = VALUES(body),
    status = 'published',
    updated_at = CURRENT_TIMESTAMP;

-- ============================================================
-- 2. EXPANDED ABOUT PAGE BODY (EN) — replace the stub
-- ============================================================
UPDATE pages SET body = '<p>Founded in 1998 and headquartered in Malta, Mori Capital Management is a dedicated investor in Emerging European, Middle Eastern and African equity markets. We combine bottom-up stock picking with rigorous in-house research and active dialogue with company management.</p>

<h2>Our History</h2>
<p>Mori Capital Management Ltd. was established to provide institutional and private investors with specialist access to EEMEA equity markets — a region that, at the time, was largely underserved by independent asset managers. Over more than 25 years, we have built deep local networks, proprietary research capabilities and a long-term performance track record across two flagship funds.</p>

<h2>Our Mission</h2>
<p>To generate superior long-term investment returns for our clients by identifying undervalued companies in Emerging European, Middle Eastern and African markets through disciplined fundamental research and active engagement with company management.</p>

<h2>Independence & Alignment</h2>
<p>Mori Capital Management is independently owned by its founding partners. This ownership structure ensures that our interests are fully aligned with our investors — we co-invest alongside our clients in every fund we manage.</p>

<h2>Regulation</h2>
<p>Mori Capital Management Ltd. is authorised and regulated by the Malta Financial Services Authority (MFSA) under Firm Reference C66999, and is authorised to provide investment services under the MiFID directive.</p>'
WHERE slug = 'about' AND locale = 'en';

-- ============================================================
-- 3. EXPANDED INVESTMENT STYLE PAGE BODY (EN)
-- ============================================================
UPDATE pages SET body = '<h2>Our Philosophy</h2>
<p>We believe that EEMEA markets are structurally inefficient, offering persistent opportunities for active managers who combine fundamental company analysis with deep regional knowledge. Our edge comes from walking the extra mile — visiting factories, meeting management teams in person, and building relationships that give us an informational advantage over screen-based investors.</p>

<h2>Bottom-Up with Macro Overlay</h2>
<p>Every investment starts with a company. We screen our universe of 200+ EEMEA-listed securities for fundamental quality: strong balance sheets, sustainable competitive advantages, capable management and attractive valuations. Only after a company passes our bottom-up filter do we layer on the macro context — monetary policy, currency trends, regulatory environment and political risk.</p>

<h2>Proprietary Research</h2>
<p>All research is conducted in-house. We do not outsource our investment process. Each position in the portfolio is supported by our own valuation models, scenario analysis and risk assessment. This ensures consistency, accountability and speed of decision-making.</p>

<h2>Active Engagement</h2>
<p>We maintain direct, ongoing dialogue with the management teams of our portfolio companies. We attend shareholder meetings, request meetings during non-deal roadshows, and engage constructively on governance, capital allocation and ESG topics.</p>

<h2>Risk Management</h2>
<p>Disciplined risk management is embedded in every step. Position sizing is based on conviction and liquidity. Portfolio-level monitoring includes correlation analysis, sector concentration limits and currency exposure overlays. We do not use leverage.</p>'
WHERE slug = 'investment-style' AND locale = 'en';

-- ============================================================
-- 4. EXPANDED LEGAL DISCLAIMER (EN) — comprehensive
-- ============================================================
UPDATE pages SET body = '<h2>Regulatory Information</h2>
<p>Mori Capital Management Ltd. is a company incorporated in Malta and is authorised and regulated by the Malta Financial Services Authority (MFSA) under Firm Reference <strong>C66999</strong>. The company is authorised to provide investment services under the Markets in Financial Instruments Directive (MiFID).</p>

<h2>Important Risk Warnings</h2>
<p>The value of investments and the income from them can fall as well as rise and investors may not get back the amount originally invested. Past performance is not a reliable indicator of future results. Changes in exchange rates may also cause the value of investments to go up or down.</p>
<p>The funds described on this website invest primarily in equities of companies in emerging markets, which may involve a higher degree of risk than investments in more developed markets. Emerging markets may be subject to less regulation, greater political instability, currency volatility and lower liquidity.</p>

<h2>Non-U.S. Persons Only</h2>
<p>The funds described on this website are not registered under the United States Investment Advisers Act of 1940, as amended, and are not available to U.S. persons as defined in Regulation S under the Securities Act of 1933. Access to the information on this website may be restricted in certain jurisdictions. It is the responsibility of any person accessing this website to comply with applicable laws and regulations of their own jurisdiction.</p>

<h2>No Offer or Solicitation</h2>
<p>Nothing on this website constitutes an offer to sell, or a solicitation of an offer to purchase, any security or financial product. Any investment in the funds should be made on the basis of the current Prospectus and the relevant Key Investor Information Document (KIID) or PRIIPs Key Information Document (KID), copies of which are available in the Document Hub or on request from the Investment Manager.</p>

<h2>Intellectual Property</h2>
<p>All content on this website, including text, graphics, logos, images, data compilations and software, is the property of Mori Capital Management Ltd. or its licensors and is protected by copyright and intellectual property laws.</p>

<h2>Contact the Regulator</h2>
<p>Malta Financial Services Authority (MFSA)<br>
Triq l-Imdina, Zone 1<br>
Central Business District, Birkirkara, CBD 1010, Malta<br>
Website: <a href="https://www.mfsa.mt" target="_blank" rel="noopener">www.mfsa.mt</a></p>'
WHERE slug = 'legal' AND locale = 'en';

-- ============================================================
-- 5. SAMPLE NAV DATA — 12 months for Ottoman Class A EUR
-- ============================================================
INSERT INTO nav_entries (share_class_id, entry_date, nav, benchmark_value) VALUES
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2025-05-31', 120.4500, 108.2000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2025-06-30', 122.1800, 109.5000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2025-07-31', 125.3200, 111.8000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2025-08-31', 123.8900, 110.2000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2025-09-30', 128.4500, 113.6000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2025-10-31', 131.2200, 115.1000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2025-11-30', 129.7800, 114.3000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2025-12-31', 134.5600, 117.8000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2026-01-31', 136.9200, 119.2000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2026-02-28', 138.4100, 120.5000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2026-03-31', 140.8800, 121.9000),
((SELECT id FROM share_classes WHERE isin = 'IE00B0T0FN89'), '2026-04-30', 142.8600, 123.1000)
ON DUPLICATE KEY UPDATE nav = VALUES(nav), benchmark_value = VALUES(benchmark_value);

-- Eastern European Fund Class A EUR — 12 months
INSERT INTO nav_entries (share_class_id, entry_date, nav, benchmark_value) VALUES
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2025-05-31', 98.2200, 95.4000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2025-06-30', 99.8500, 96.2000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2025-07-31', 101.4200, 97.8000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2025-08-31', 100.1800, 96.5000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2025-09-30', 103.5600, 99.1000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2025-10-31', 105.8900, 100.8000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2025-11-30', 104.2200, 99.6000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2025-12-31', 107.4500, 102.3000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2026-01-31', 109.1200, 103.8000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2026-02-28', 110.8800, 105.1000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2026-03-31', 112.4500, 106.5000),
((SELECT id FROM share_classes WHERE isin = 'IE0002787442'), '2026-04-30', 114.2200, 107.8000)
ON DUPLICATE KEY UPDATE nav = VALUES(nav), benchmark_value = VALUES(benchmark_value);

-- ============================================================
-- 6. DEFAULT SETTINGS — ensure OG image + custom code keys exist
-- ============================================================
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('og_default_image', 'assets/images/mori-capital-logo-dark.fw.png', 'seo'),
('custom_head_code', '', 'seo'),
('custom_footer_code', '', 'seo')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
