-- Hero slides management
CREATE TABLE IF NOT EXISTS hero_slides (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(255) NULL,
    subtitle      VARCHAR(500) NULL,
    media_type    ENUM('image','video') NOT NULL DEFAULT 'image',
    media_path    VARCHAR(500) NOT NULL,
    overlay_opacity DECIMAL(3,2) NOT NULL DEFAULT 0.70,
    cta_text      VARCHAR(100) NULL,
    cta_url       VARCHAR(500) NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed with existing hero images/videos
INSERT INTO hero_slides (title, subtitle, media_type, media_path, display_order, is_active) VALUES
('Navigating EEMEA markets with proven expertise since 1998.', 'Independent and unconstrained. Performance and investor focused.', 'image', 'assets/images/hero/hero-istanbul.jpg', 1, 1),
('Research-led investing across Emerging Europe', 'Deep local knowledge. On-the-ground research. Patient stock selection.', 'image', 'assets/images/hero/hero-corporate-1.jpg', 2, 1),
('Two specialist strategies, one regional focus', 'Mori Eastern European Fund & Mori Ottoman Fund', 'image', 'assets/images/hero/hero-corporate-2.jpg', 3, 1);
