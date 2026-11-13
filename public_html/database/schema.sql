-- =============================================================================
-- PDFNova — MySQL / MariaDB schema
-- -----------------------------------------------------------------------------
-- Compatible with: MySQL 5.7+ / 8.x and MariaDB 10.4+ (Hostinger uses MariaDB).
-- Engine: InnoDB (foreign keys + transactions). Charset: utf8mb4.
--
-- HOW TO IMPORT (Hostinger):
--   hPanel → Databases → phpMyAdmin → select YOUR database (left sidebar)
--   → Import → choose this file → Go.
--   This file does NOT create the database itself; Hostinger creates it for you
--   (name like u123456789_pdfnova).
--
-- CONVENTIONS
--   * All DATETIME values are stored in UTC (the app sets time_zone = '+00:00').
--   * IP addresses are stored as VARBINARY(16) via PHP inet_pton() (IPv4 + IPv6).
--   * Tokens are never stored in plain text: only SHA-256 hashes (CHAR(64)).
--   * public_id columns are random 32-hex tokens used in URLs/API instead of
--     auto-increment ids, so ids cannot be guessed or enumerated.
--   * Uploaded file CONTENT is never stored in the database — only metadata.
--
-- Safe to re-run: tables use IF NOT EXISTS and seed rows use INSERT IGNORE.
-- (Re-running will NOT alter tables that already exist — use migrations for that.)
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- schema_migrations — tracks which schema version is installed
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
    version     VARCHAR(100) NOT NULL,
    applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- plans — Free / Pro / Business limits. The app reads limits from here, so
-- they can be changed from the admin panel without code changes.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plans (
    id                   SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug                 VARCHAR(30)  NOT NULL,
    name                 VARCHAR(60)  NOT NULL,
    description          VARCHAR(255) NULL,
    price_monthly_cents  INT UNSIGNED NOT NULL DEFAULT 0,
    price_yearly_cents   INT UNSIGNED NOT NULL DEFAULT 0,
    currency             CHAR(3)      NOT NULL DEFAULT 'USD',
    max_file_size_mb     INT UNSIGNED NOT NULL DEFAULT 20,
    max_files_per_job    SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    daily_jobs_limit     INT UNSIGNED NULL COMMENT 'NULL = unlimited',
    monthly_jobs_limit   INT UNSIGNED NULL COMMENT 'NULL = unlimited',
    history_days         SMALLINT UNSIGNED NOT NULL DEFAULT 7 COMMENT 'How long job history (metadata) is shown',
    batch_processing     TINYINT(1)   NOT NULL DEFAULT 0,
    priority_processing  TINYINT(1)   NOT NULL DEFAULT 0,
    api_access           TINYINT(1)   NOT NULL DEFAULT 0,
    team_seats           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    is_public            TINYINT(1)   NOT NULL DEFAULT 1,
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order           SMALLINT     NOT NULL DEFAULT 0,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_plans_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id           CHAR(32)     NOT NULL,
    name                VARCHAR(100) NOT NULL,
    email               VARCHAR(191) NOT NULL COMMENT 'Stored lower-cased',
    password_hash       VARCHAR(255) NOT NULL COMMENT 'password_hash() output — never plain text',
    role                ENUM('user','admin') NOT NULL DEFAULT 'user',
    status              ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
    email_verified_at   DATETIME     NULL,
    failed_login_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until        DATETIME     NULL COMMENT 'Temporary lock after repeated failed logins',
    last_login_at       DATETIME     NULL,
    last_login_ip       VARBINARY(16) NULL,
    password_changed_at DATETIME     NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_public_id (public_id),
    KEY idx_users_role_status (role, status),
    KEY idx_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- password_resets — single-use, expiring, hashed tokens
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64)     NOT NULL COMMENT 'SHA-256 of the token emailed to the user',
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    request_ip  VARBINARY(16) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_user (user_id),
    KEY idx_password_resets_expires (expires_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- sessions — tracks logged-in sessions per user (device list, "log out
-- everywhere", forced logout when an admin suspends a user).
-- PHP's own session data stays in PHP's session store; this table holds only
-- a hash of the session id + metadata.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id            INT UNSIGNED NOT NULL,
    session_hash       CHAR(64)     NOT NULL COMMENT 'SHA-256 of PHP session id',
    ip_address         VARBINARY(16) NULL,
    user_agent         VARCHAR(255) NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at         DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sessions_hash (session_hash),
    KEY idx_sessions_user (user_id, revoked_at),
    KEY idx_sessions_activity (last_activity_at),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- subscriptions — one row per subscription period/record. A user with no
-- active row is on the Free plan.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscriptions (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id                  INT UNSIGNED NOT NULL,
    plan_id                  SMALLINT UNSIGNED NOT NULL,
    status                   ENUM('active','trialing','past_due','canceled','expired') NOT NULL DEFAULT 'active',
    billing_cycle            ENUM('monthly','yearly','lifetime','none') NOT NULL DEFAULT 'monthly',
    gateway                  VARCHAR(30)  NOT NULL DEFAULT 'manual' COMMENT 'manual | stripe | ...',
    gateway_customer_id      VARCHAR(100) NULL,
    gateway_subscription_id  VARCHAR(100) NULL,
    amount_cents             INT UNSIGNED NOT NULL DEFAULT 0,
    currency                 CHAR(3)      NOT NULL DEFAULT 'USD',
    current_period_start     DATETIME     NULL,
    current_period_end       DATETIME     NULL,
    cancel_at_period_end     TINYINT(1)   NOT NULL DEFAULT 0,
    canceled_at              DATETIME     NULL,
    created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscriptions_gateway (gateway, gateway_subscription_id),
    KEY idx_subscriptions_user_status (user_id, status),
    KEY idx_subscriptions_plan (plan_id),
    KEY idx_subscriptions_period_end (status, current_period_end),
    CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id)
        REFERENCES plans (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- payments — filled by the payment adapter once a gateway is connected.
-- Powers the admin "Revenue" figure (shows 0 until then).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NULL,
    subscription_id     INT UNSIGNED NULL,
    gateway             VARCHAR(30)  NOT NULL DEFAULT 'manual',
    gateway_payment_id  VARCHAR(100) NULL,
    amount_cents        INT UNSIGNED NOT NULL,
    currency            CHAR(3)      NOT NULL DEFAULT 'USD',
    status              ENUM('pending','succeeded','failed','refunded') NOT NULL DEFAULT 'pending',
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_gateway (gateway, gateway_payment_id),
    KEY idx_payments_user (user_id),
    KEY idx_payments_status_created (status, created_at),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_payments_subscription FOREIGN KEY (subscription_id)
        REFERENCES subscriptions (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- files — METADATA for temporary uploads and results. The bytes live in
-- storage/uploads or storage/processed under stored_name (random, no
-- extension). original_name is display-only and never used as a path.
-- Guests: user_id NULL, owner identified by session_hash.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS files (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id      CHAR(32)     NOT NULL COMMENT 'Random token used by the API',
    user_id        INT UNSIGNED NULL,
    session_hash   CHAR(64)     NULL COMMENT 'Owner for guest uploads (SHA-256 of session id)',
    kind           ENUM('upload','result') NOT NULL,
    storage_area   ENUM('uploads','processed','temp') NOT NULL,
    stored_name    CHAR(32)     NOT NULL COMMENT 'Random filename on disk',
    original_name  VARCHAR(255) NOT NULL COMMENT 'Display only — escaped on output',
    mime_type      VARCHAR(100) NOT NULL COMMENT 'Detected server-side (finfo), not trusted from client',
    extension      VARCHAR(10)  NOT NULL,
    size_bytes     BIGINT UNSIGNED NOT NULL,
    sha256         CHAR(64)     NULL,
    page_count     INT UNSIGNED NULL,
    status         ENUM('active','deleted') NOT NULL DEFAULT 'active',
    expires_at     DATETIME     NOT NULL COMMENT 'Cleanup cron deletes the bytes after this',
    deleted_at     DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_files_public_id (public_id),
    UNIQUE KEY uq_files_stored_name (stored_name),
    KEY idx_files_user_created (user_id, created_at),
    KEY idx_files_session (session_hash),
    KEY idx_files_cleanup (status, expires_at),
    CONSTRAINT fk_files_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- processing_jobs — one row per tool run (merge, split, ...)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS processing_jobs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id       CHAR(32)     NOT NULL,
    user_id         INT UNSIGNED NULL,
    session_hash    CHAR(64)     NULL,
    tool            VARCHAR(50)  NOT NULL COMMENT 'Tool slug, e.g. merge-pdf',
    status          ENUM('queued','processing','completed','failed','expired') NOT NULL DEFAULT 'queued',
    engine          VARCHAR(50)  NULL COMMENT 'Which engine/adapter handled it',
    options_json    TEXT         NULL COMMENT 'Validated options as JSON',
    input_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    input_bytes     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    output_bytes    BIGINT UNSIGNED NULL,
    result_file_id  BIGINT UNSIGNED NULL,
    error_code      VARCHAR(50)  NULL,
    error_message   VARCHAR(500) NULL COMMENT 'Safe, user-facing message only',
    duration_ms     INT UNSIGNED NULL,
    ip_address      VARBINARY(16) NULL,
    started_at      DATETIME     NULL,
    completed_at    DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jobs_public_id (public_id),
    KEY idx_jobs_user_created (user_id, created_at),
    KEY idx_jobs_session (session_hash),
    KEY idx_jobs_status_created (status, created_at),
    KEY idx_jobs_tool_created (tool, created_at),
    KEY idx_jobs_result_file (result_file_id),
    CONSTRAINT fk_jobs_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_result_file FOREIGN KEY (result_file_id)
        REFERENCES files (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- job_files — input files of a job, in order (merge order matters)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_files (
    job_id    BIGINT UNSIGNED NOT NULL,
    file_id   BIGINT UNSIGNED NOT NULL,
    position  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (job_id, file_id),
    KEY idx_job_files_file (file_id),
    CONSTRAINT fk_job_files_job FOREIGN KEY (job_id)
        REFERENCES processing_jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_files_file FOREIGN KEY (file_id)
        REFERENCES files (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- usage_logs — append-only events for quotas, stats and the activity feed
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usage_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NULL,
    session_hash  CHAR(64)     NULL,
    tool          VARCHAR(50)  NULL,
    action        VARCHAR(30)  NOT NULL COMMENT 'upload | process | download | login | ...',
    job_id        BIGINT UNSIGNED NULL,
    bytes         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ip_address    VARBINARY(16) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_usage_user_created (user_id, created_at),
    KEY idx_usage_session_created (session_hash, created_at),
    KEY idx_usage_ip_created (ip_address, created_at),
    KEY idx_usage_action_created (action, created_at),
    KEY idx_usage_job (job_id),
    CONSTRAINT fk_usage_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_usage_job FOREIGN KEY (job_id)
        REFERENCES processing_jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- contact_messages
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(191) NOT NULL,
    subject     VARCHAR(150) NOT NULL,
    message     TEXT         NOT NULL,
    status      ENUM('new','read','replied','archived','spam') NOT NULL DEFAULT 'new',
    ip_address  VARBINARY(16) NULL,
    user_agent  VARCHAR(255) NULL,
    read_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_status_created (status, created_at),
    KEY idx_contact_ip_created (ip_address, created_at),
    CONSTRAINT fk_contact_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- rate_limits — fixed-window counters (shared hosting has no Redis)
-- rate_key = SHA-256 of "<action>|<identifier>" (identifier = IP or user id)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
    rate_key       CHAR(64)    NOT NULL,
    action         VARCHAR(50) NOT NULL,
    hits           INT UNSIGNED NOT NULL DEFAULT 0,
    window_start   DATETIME    NOT NULL,
    expires_at     DATETIME    NOT NULL,
    PRIMARY KEY (rate_key),
    KEY idx_rate_limits_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- settings — key/value site settings editable in the admin panel
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key    VARCHAR(100) NOT NULL,
    setting_value  TEXT         NULL,
    updated_by     INT UNSIGNED NULL,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- admin_audit_log — who changed what in the admin panel
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id      INT UNSIGNED NULL,
    action        VARCHAR(60)  NOT NULL,
    target_type   VARCHAR(40)  NULL,
    target_id     VARCHAR(64)  NULL,
    details_json  TEXT         NULL,
    ip_address    VARBINARY(16) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_admin_created (admin_id, created_at),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEED DATA
-- =============================================================================

-- Plans. Prices are placeholders — edit in the admin panel or here.
INSERT IGNORE INTO plans
    (slug, name, description, price_monthly_cents, price_yearly_cents, currency,
     max_file_size_mb, max_files_per_job, daily_jobs_limit, monthly_jobs_limit,
     history_days, batch_processing, priority_processing, api_access, team_seats, sort_order)
VALUES
    ('free',     'Free',     'Essential PDF tools for occasional use.',
        0,    0,     'USD',  20,  5,   20,   NULL, 7,   0, 0, 0, 1,  1),
    ('pro',      'Pro',      'Higher limits and batch processing for professionals.',
        900,  8400,  'USD', 100, 20, NULL, NULL, 30,  1, 1, 0, 1,  2),
    ('business', 'Business', 'Team features, the highest limits and API access.',
        2900, 27600, 'USD', 200, 50, NULL, NULL, 90,  1, 1, 1, 10, 3);

-- Default site settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('maintenance_mode',       '0'),
    ('registration_enabled',   '1'),
    ('guest_uploads_enabled',  '1'),
    ('guest_max_file_size_mb', '10'),
    ('guest_daily_jobs_limit', '10'),
    ('contact_email',          'support@example.com');

INSERT IGNORE INTO schema_migrations (version) VALUES ('2026_11_09_000001_initial_schema');

-- -----------------------------------------------------------------------------
-- NO DEFAULT ADMIN ACCOUNT is created (a known default password would be a
-- security hole). To create your admin:
--   1. Register normally on the website.
--   2. In phpMyAdmin → SQL tab, run (with your email):
--        UPDATE users SET role = 'admin' WHERE email = 'you@yourdomain.com';
-- -----------------------------------------------------------------------------
