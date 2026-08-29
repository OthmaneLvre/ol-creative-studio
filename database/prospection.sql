-- ============================================================
-- OL Creative Studio
-- Module Prospection
-- ============================================================
-- Ce fichier contient la structure SQL nécessaire au moteur de
-- prospection automatisée :
--
-- - prospects ;
-- - analyses ;
-- - historique des événements ;
-- - messages et relances ;
-- - réponses reçues ;
-- - désinscriptions.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- ============================================================
-- 1. PROSPECTS
-- ============================================================

CREATE TABLE IF NOT EXISTS prospects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_name VARCHAR(190) NOT NULL,
    activity VARCHAR(190) DEFAULT NULL,

    city VARCHAR(190) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,

    website_url VARCHAR(500) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,

    source VARCHAR(100) DEFAULT NULL,
    source_url VARCHAR(500) DEFAULT NULL,
    source_external_id VARCHAR(190) DEFAULT NULL,

    status ENUM(
        'discovered',
        'analyzing',
        'analyzed',
        'pending_validation',
        'approved',
        'scheduled',
        'contacted',
        'follow_up',
        'replied',
        'qualified',
        'converted',
        'rejected',
        'unsubscribed',
        'invalid'
    ) NOT NULL DEFAULT 'discovered',

    score SMALLINT UNSIGNED DEFAULT NULL,

    discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    analyzed_at DATETIME DEFAULT NULL,
    validated_at DATETIME DEFAULT NULL,
    scheduled_at DATETIME DEFAULT NULL,
    contacted_at DATETIME DEFAULT NULL,
    replied_at DATETIME DEFAULT NULL,
    converted_at DATETIME DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_prospects_status (status),
    INDEX idx_prospects_score (score),
    INDEX idx_prospects_city (city),
    INDEX idx_prospects_email (email),
    INDEX idx_prospects_scheduled_at (scheduled_at),
    INDEX idx_prospects_contacted_at (contacted_at),
    INDEX idx_prospects_source_external (
        source,
        source_external_id
    ),

    UNIQUE KEY uq_prospect_email (email),

    UNIQUE KEY uq_prospect_source_external (
        source,
        source_external_id
    )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2. ANALYSES DES PROSPECTS
-- ============================================================

CREATE TABLE IF NOT EXISTS prospect_analyses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    prospect_id INT UNSIGNED NOT NULL,

    website_reachable TINYINT(1) DEFAULT NULL,
    has_ssl TINYINT(1) DEFAULT NULL,
    is_mobile_friendly TINYINT(1) DEFAULT NULL,
    has_contact_page TINYINT(1) DEFAULT NULL,
    has_social_links TINYINT(1) DEFAULT NULL,
    has_visible_email TINYINT(1) DEFAULT NULL,

    detected_email VARCHAR(190) DEFAULT NULL,

    performance_score SMALLINT UNSIGNED DEFAULT NULL,
    seo_score SMALLINT UNSIGNED DEFAULT NULL,
    design_score SMALLINT UNSIGNED DEFAULT NULL,
    mobile_score SMALLINT UNSIGNED DEFAULT NULL,
    technical_score SMALLINT UNSIGNED DEFAULT NULL,

    overall_score SMALLINT UNSIGNED DEFAULT NULL,

    weaknesses JSON DEFAULT NULL,
    strengths JSON DEFAULT NULL,
    opportunities JSON DEFAULT NULL,

    raw_analysis JSON DEFAULT NULL,

    audit_text TEXT DEFAULT NULL,

    analyzed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_prospect_analyses_prospect
        FOREIGN KEY (prospect_id)
        REFERENCES prospects(id)
        ON DELETE CASCADE,

    INDEX idx_prospect_analyses_prospect (prospect_id),
    INDEX idx_prospect_analyses_score (overall_score)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. HISTORIQUE DES ÉVÉNEMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS prospect_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    prospect_id INT UNSIGNED NOT NULL,

    event_type VARCHAR(100) NOT NULL,

    previous_status VARCHAR(50) DEFAULT NULL,
    new_status VARCHAR(50) DEFAULT NULL,

    description VARCHAR(500) DEFAULT NULL,

    metadata JSON DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_prospect_events_prospect
        FOREIGN KEY (prospect_id)
        REFERENCES prospects(id)
        ON DELETE CASCADE,

    INDEX idx_prospect_events_prospect (prospect_id),
    INDEX idx_prospect_events_type (event_type),
    INDEX idx_prospect_events_created_at (created_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 4. EMAILS ET RELANCES
-- ============================================================

CREATE TABLE IF NOT EXISTS prospect_emails (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    prospect_id INT UNSIGNED NOT NULL,

    email_type ENUM(
        'initial',
        'follow_up_1',
        'follow_up_2',
        'manual'
    ) NOT NULL DEFAULT 'initial',

    recipient_email VARCHAR(190) NOT NULL,

    subject VARCHAR(255) NOT NULL,

    body_html MEDIUMTEXT DEFAULT NULL,
    body_text MEDIUMTEXT DEFAULT NULL,

    status ENUM(
        'draft',
        'pending_validation',
        'approved',
        'scheduled',
        'sending',
        'sent',
        'failed',
        'cancelled'
    ) NOT NULL DEFAULT 'draft',

    approved_at DATETIME DEFAULT NULL,
    scheduled_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,

    message_id VARCHAR(255) DEFAULT NULL,

    send_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    error_message TEXT DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_prospect_emails_prospect
        FOREIGN KEY (prospect_id)
        REFERENCES prospects(id)
        ON DELETE CASCADE,

    INDEX idx_prospect_emails_prospect (prospect_id),
    INDEX idx_prospect_emails_status (status),
    INDEX idx_prospect_emails_scheduled_at (scheduled_at),
    INDEX idx_prospect_emails_sent_at (sent_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 5. RÉPONSES REÇUES
-- ============================================================

CREATE TABLE IF NOT EXISTS prospect_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    prospect_id INT UNSIGNED NOT NULL,
    prospect_email_id BIGINT UNSIGNED DEFAULT NULL,

    sender_email VARCHAR(190) NOT NULL,

    subject VARCHAR(255) DEFAULT NULL,

    body_text MEDIUMTEXT DEFAULT NULL,
    body_html MEDIUMTEXT DEFAULT NULL,

    message_id VARCHAR(255) DEFAULT NULL,
    in_reply_to VARCHAR(255) DEFAULT NULL,

    classification ENUM(
        'positive',
        'negative',
        'question',
        'unsubscribed',
        'automatic',
        'unknown'
    ) NOT NULL DEFAULT 'unknown',

    is_read TINYINT(1) NOT NULL DEFAULT 0,

    received_at DATETIME NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_prospect_replies_prospect
        FOREIGN KEY (prospect_id)
        REFERENCES prospects(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_prospect_replies_email
        FOREIGN KEY (prospect_email_id)
        REFERENCES prospect_emails(id)
        ON DELETE SET NULL,

    INDEX idx_prospect_replies_prospect (prospect_id),
    INDEX idx_prospect_replies_classification (classification),
    INDEX idx_prospect_replies_is_read (is_read),
    INDEX idx_prospect_replies_received_at (received_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 6. DÉSINSCRIPTIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS prospect_unsubscribes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    prospect_id INT UNSIGNED DEFAULT NULL,

    email VARCHAR(190) NOT NULL,

    reason VARCHAR(255) DEFAULT NULL,

    source ENUM(
        'email_link',
        'reply',
        'manual',
        'admin'
    ) NOT NULL DEFAULT 'manual',

    unsubscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_prospect_unsubscribes_prospect
        FOREIGN KEY (prospect_id)
        REFERENCES prospects(id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_prospect_unsubscribe_email (email),

    INDEX idx_prospect_unsubscribes_prospect (prospect_id),
    INDEX idx_prospect_unsubscribes_date (unsubscribed_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- PROSPECTING AREAS
-- Zones géographiques parcourues par le moteur de découverte
-- ============================================================

CREATE TABLE IF NOT EXISTS prospecting_areas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    city VARCHAR(120) NOT NULL,
    postal_code VARCHAR(10) DEFAULT NULL,
    department VARCHAR(120) DEFAULT NULL,

    latitude DECIMAL(10, 7) DEFAULT NULL,
    longitude DECIMAL(10, 7) DEFAULT NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    last_scanned_at DATETIME DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_prospecting_area_city_postal (
        city,
        postal_code
    ),

    INDEX idx_prospecting_areas_active_scan (
        is_active,
        last_scanned_at
    )
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;