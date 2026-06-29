-- 2026-06-media-items.sql
-- "Mori Views" is being replaced by a "Media" section that mirrors the old
-- live site's media.html: press releases (own PDFs), media coverage
-- (external articles) and videos/podcasts.
--
-- This migration only creates the table. The rows are seeded — and the
-- press-release PDFs downloaded into the local media library — by
-- import-media.php, which needs PHP (outbound fetch) and so can't live in a
-- pure-SQL migration.

CREATE TABLE IF NOT EXISTS media_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    item_date     DATE NULL,
    source        VARCHAR(200) NULL,                -- "Citywire — Chris Soley" / "Mori Capital Management"
    title_en      VARCHAR(400) NOT NULL,
    title_de      VARCHAR(400) NULL,                -- blank → reuse EN
    url_en        VARCHAR(700) NULL,                -- external link or local PDF path
    url_de        VARCHAR(700) NULL,                -- DE-specific PDF/link; blank → reuse url_en
    item_type     ENUM('press_release','article','video','podcast') NOT NULL DEFAULT 'article',
    is_external   TINYINT(1) NOT NULL DEFAULT 1,    -- 1 = open in new tab; 0 = local PDF (download)
    display_order INT NOT NULL DEFAULT 0,
    status        ENUM('published','draft') NOT NULL DEFAULT 'published',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_date (status, item_date),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
