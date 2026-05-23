-- =============================================================================
-- Mori Capital Management — Database Schema
-- MariaDB 10.5+ / MySQL 8+
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Users (admin panel)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    name            VARCHAR(120) NOT NULL,
    role            ENUM('super_admin','editor','doc_manager') NOT NULL DEFAULT 'editor',
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME NULL,
    last_login_ip   VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Pages (bilingual content)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    slug                VARCHAR(120) NOT NULL,
    locale              ENUM('en','de') NOT NULL DEFAULT 'en',
    title               VARCHAR(255) NOT NULL,
    meta_title          VARCHAR(255) NULL,
    meta_description    TEXT NULL,
    body                LONGTEXT NULL,
    status              ENUM('draft','published') NOT NULL DEFAULT 'draft',
    updated_by          INT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_slug_locale (slug, locale),
    INDEX idx_status (status),
    CONSTRAINT fk_pages_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Funds
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS funds (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    slug                VARCHAR(120) NOT NULL UNIQUE,
    name_en             VARCHAR(255) NOT NULL,
    name_de             VARCHAR(255) NULL,
    description_en      TEXT NULL,
    description_de      TEXT NULL,
    objective_en        TEXT NULL,
    objective_de        TEXT NULL,
    launch_date         DATE NULL,
    base_currency       CHAR(3) NULL,
    benchmark           VARCHAR(255) NULL,
    cover_image_path    VARCHAR(500) NULL,
    status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
    display_order       INT NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_order (status, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Share classes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS share_classes (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    fund_id             INT NOT NULL,
    name                VARCHAR(120) NOT NULL,
    isin                VARCHAR(20) NULL,
    currency            CHAR(3) NOT NULL,
    min_investment      DECIMAL(15,2) NULL,
    inception_date      DATE NULL,
    status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
    display_order       INT NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_isin (isin),
    INDEX idx_fund (fund_id),
    CONSTRAINT fk_sc_fund FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Documents
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    fund_id             INT NULL,
    document_type       ENUM('prospectus','kiid','priips','annual','semi_annual','factsheet','marketing','other') NOT NULL,
    title               VARCHAR(255) NOT NULL,
    file_path           VARCHAR(500) NOT NULL,
    file_name           VARCHAR(255) NOT NULL,
    file_size           BIGINT NULL,
    mime_type           VARCHAR(120) NULL,
    document_date       DATE NULL,
    locale              ENUM('en','de','any') NOT NULL DEFAULT 'any',
    version_notes       TEXT NULL,
    uploaded_by         INT NULL,
    download_count      INT NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fund_type (fund_id, document_type),
    INDEX idx_doc_date (document_date),
    CONSTRAINT fk_doc_fund FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE SET NULL,
    CONSTRAINT fk_doc_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Document ↔ Share class (many-to-many)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS document_share_classes (
    document_id         INT NOT NULL,
    share_class_id      INT NOT NULL,
    PRIMARY KEY (document_id, share_class_id),
    CONSTRAINT fk_dsc_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    CONSTRAINT fk_dsc_sc  FOREIGN KEY (share_class_id) REFERENCES share_classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- NAV / monthly performance entries
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nav_entries (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    share_class_id      INT NOT NULL,
    entry_date          DATE NOT NULL,
    nav                 DECIMAL(15,4) NOT NULL,
    benchmark_value     DECIMAL(15,4) NULL,
    notes               TEXT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_class_date (share_class_id, entry_date),
    INDEX idx_class_date (share_class_id, entry_date),
    CONSTRAINT fk_nav_sc FOREIGN KEY (share_class_id) REFERENCES share_classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Team members
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS team_members (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    slug                VARCHAR(120) NOT NULL UNIQUE,
    name                VARCHAR(120) NOT NULL,
    title_en            VARCHAR(255) NOT NULL,
    title_de            VARCHAR(255) NULL,
    bio_en              LONGTEXT NULL,
    bio_de              LONGTEXT NULL,
    photo_path          VARCHAR(500) NULL,
    linkedin_url        VARCHAR(500) NULL,
    email               VARCHAR(190) NULL,
    display_order       INT NOT NULL DEFAULT 0,
    status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_order (status, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Insights / Mori Views
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS insights (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    slug                VARCHAR(190) NOT NULL,
    locale              ENUM('en','de') NOT NULL DEFAULT 'en',
    category            ENUM('outlook','factsheet','shareholder_notice','article','press') NOT NULL DEFAULT 'article',
    title               VARCHAR(255) NOT NULL,
    excerpt             TEXT NULL,
    body                LONGTEXT NULL,
    cover_image_path    VARCHAR(500) NULL,
    publish_date        DATE NULL,
    status              ENUM('draft','published') NOT NULL DEFAULT 'draft',
    view_count          INT NOT NULL DEFAULT 0,
    created_by          INT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_slug_locale (slug, locale),
    INDEX idx_publish (status, publish_date),
    CONSTRAINT fk_ins_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Media library
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    file_path           VARCHAR(500) NOT NULL,
    file_name           VARCHAR(255) NOT NULL,
    file_size           BIGINT NULL,
    mime_type           VARCHAR(120) NULL,
    alt_text            VARCHAR(255) NULL,
    width               INT NULL,
    height              INT NULL,
    folder              VARCHAR(120) NULL,
    uploaded_by         INT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_folder (folder),
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Settings (key-value)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key         VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value       LONGTEXT NULL,
    setting_group       VARCHAR(60) NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Contact messages
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(120) NOT NULL,
    email               VARCHAR(190) NOT NULL,
    subject             VARCHAR(255) NULL,
    message             TEXT NOT NULL,
    ip_address          VARCHAR(45) NULL,
    user_agent          VARCHAR(500) NULL,
    status              ENUM('new','read','archived') NOT NULL DEFAULT 'new',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Newsletter subscribers
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    email               VARCHAR(190) NOT NULL UNIQUE,
    locale              ENUM('en','de') NOT NULL DEFAULT 'en',
    status              ENUM('pending','confirmed','unsubscribed') NOT NULL DEFAULT 'pending',
    confirm_token       VARCHAR(64) NULL,
    confirmed_at        DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Audit log (admin actions)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NULL,
    action              VARCHAR(100) NOT NULL,
    entity              VARCHAR(100) NULL,
    entity_id           INT NULL,
    details             TEXT NULL,
    ip_address          VARCHAR(45) NULL,
    user_agent          VARCHAR(500) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_entity (entity, entity_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Schema migrations tracker (records which .sql files have been applied)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    file        VARCHAR(190) NOT NULL UNIQUE,
    checksum    VARCHAR(64)  NOT NULL,
    applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_by  INT NULL,
    notes       TEXT NULL,
    INDEX idx_file (file)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
-- Note: the 'sessions' table is intentionally NOT created. PHP file-based
-- session storage is used. If multi-server deployment is added later, a
-- SessionHandlerInterface implementation can introduce a sessions table.
