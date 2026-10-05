-- 2026-10-remove-demo-nav.sql
-- 2026-05-complete-content.sql seeded 12 months of ILLUSTRATIVE sample NAVs
-- (May 2025 – Apr 2026) for Ottoman Class A EUR and Eastern European
-- Class A EUR so the performance chart could be designed before real data
-- existed. They are not real prices and must never be shown publicly now that
-- the funds publish genuine NAVs (Otto Class A EUR was still displaying them).
--
-- Deletes ONLY rows that match a sample value exactly (ISIN + date + NAV +
-- benchmark), so a genuine price for any of those dates is never touched.
-- Fresh installs: the sample insert runs first, this migration removes it.

DELETE n FROM nav_entries n
  JOIN share_classes s ON s.id = n.share_class_id
 WHERE (s.isin, n.entry_date, n.nav, n.benchmark_value) IN (
    ('IE00B0T0FN89', '2025-05-31', 120.4500, 108.2000),
    ('IE00B0T0FN89', '2025-06-30', 122.1800, 109.5000),
    ('IE00B0T0FN89', '2025-07-31', 125.3200, 111.8000),
    ('IE00B0T0FN89', '2025-08-31', 123.8900, 110.2000),
    ('IE00B0T0FN89', '2025-09-30', 128.4500, 113.6000),
    ('IE00B0T0FN89', '2025-10-31', 131.2200, 115.1000),
    ('IE00B0T0FN89', '2025-11-30', 129.7800, 114.3000),
    ('IE00B0T0FN89', '2025-12-31', 134.5600, 117.8000),
    ('IE00B0T0FN89', '2026-01-31', 136.9200, 119.2000),
    ('IE00B0T0FN89', '2026-02-28', 138.4100, 120.5000),
    ('IE00B0T0FN89', '2026-03-31', 140.8800, 121.9000),
    ('IE00B0T0FN89', '2026-04-30', 142.8600, 123.1000),
    ('IE0002787442', '2025-05-31',  98.2200,  95.4000),
    ('IE0002787442', '2025-06-30',  99.8500,  96.2000),
    ('IE0002787442', '2025-07-31', 101.4200,  97.8000),
    ('IE0002787442', '2025-08-31', 100.1800,  96.5000),
    ('IE0002787442', '2025-09-30', 103.5600,  99.1000),
    ('IE0002787442', '2025-10-31', 105.8900, 100.8000),
    ('IE0002787442', '2025-11-30', 104.2200,  99.6000),
    ('IE0002787442', '2025-12-31', 107.4500, 102.3000),
    ('IE0002787442', '2026-01-31', 109.1200, 103.8000),
    ('IE0002787442', '2026-02-28', 110.8800, 105.1000),
    ('IE0002787442', '2026-03-31', 112.4500, 106.5000),
    ('IE0002787442', '2026-04-30', 114.2200, 107.8000)
 );
