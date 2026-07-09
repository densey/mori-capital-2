-- 2026-07-linkedin-url.sql
-- Point the LinkedIn icon (topbar + footer) at Mori Capital's company page.
-- The default was '#'. Editable afterwards in Admin -> Settings -> Social;
-- seed.sql no longer overwrites it on re-run.

INSERT INTO settings (setting_key, setting_value, setting_group)
VALUES ('linkedin_url', 'https://www.linkedin.com/company/mori-capital-management-ltd-/', 'social')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
