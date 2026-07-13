-- 2026-07-hide-cinematic.sql
-- The homepage "cinematic" showcase (Disciplined investing / powered by data)
-- contains an ILLUSTRATIVE performance chart and sample figures (NAV, YTD,
-- 10Y) — it is not fed from live NAV data. Desmond asked to remove it since
-- it can be mistaken for real fund performance. The section is now gated by a
-- setting (hp_cine_enabled) with a show/hide toggle in Admin -> Homepage
-- Content. Default it to hidden. It can be switched back on any time once it
-- is wired to real data.

INSERT INTO settings (setting_key, setting_value, setting_group)
VALUES ('hp_cine_enabled', '0', 'homepage')
ON DUPLICATE KEY UPDATE setting_value = '0';
