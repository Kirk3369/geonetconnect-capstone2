-- GeoNet Connect / R-Link Data and Solutions
-- MySQL 5.7+ / MariaDB 10.4+ (XAMPP)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS sms_logs;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS component_incidents;
DROP TABLE IF EXISTS monthly_metrics;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS outage_subscribers;
DROP TABLE IF EXISTS outages;
DROP TABLE IF EXISTS subscription_requests;
DROP TABLE IF EXISTS coverage_areas;
DROP TABLE IF EXISTS subscribers;
DROP TABLE IF EXISTS technicians;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS network_assets;
DROP TABLE IF EXISTS barangays;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE barangays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    lat DECIMAL(10, 6) NOT NULL,
    lng DECIMAL(10, 6) NOT NULL,
    municipality VARCHAR(80) NOT NULL DEFAULT 'San Miguel',
    province VARCHAR(80) NOT NULL DEFAULT 'Iloilo',
    UNIQUE KEY uq_barangays_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE network_assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barangay_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    asset_type ENUM('landmark', 'nap', 'fiber') NOT NULL,
    lat DECIMAL(10, 6) NOT NULL,
    lng DECIMAL(10, 6) NOT NULL,
    map_pin_enabled TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_assets_type (asset_type),
    CONSTRAINT fk_assets_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coverage_areas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    coordinates_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    role ENUM('admin', 'technician', 'subscriber') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE technicians (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(20) NOT NULL,
    user_id INT UNSIGNED NULL,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    job_title VARCHAR(80) NOT NULL,
    availability ENUM('available', 'on_duty', 'en_route', 'off_duty') NOT NULL DEFAULT 'available',
    photo_initials VARCHAR(4) NOT NULL,
    UNIQUE KEY uq_technicians_public (public_id),
    UNIQUE KEY uq_technicians_user (user_id),
    CONSTRAINT fk_technicians_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(20) NOT NULL,
    user_id INT UNSIGNED NULL,
    barangay_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    plan_name VARCHAR(80) NOT NULL,
    status ENUM('online', 'offline', 'intermittent') NOT NULL DEFAULT 'online',
    lat DECIMAL(10, 6) NOT NULL,
    lng DECIMAL(10, 6) NOT NULL,
    map_pin_enabled TINYINT(1) NOT NULL DEFAULT 0,
    connected_on DATE NOT NULL,
    UNIQUE KEY uq_subscribers_public (public_id),
    UNIQUE KEY uq_subscribers_user (user_id),
    KEY idx_subscribers_status (status),
    KEY idx_subscribers_barangay (barangay_id),
    CONSTRAINT fk_subscribers_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_subscribers_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    barangay_id INT UNSIGNED NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    plan_name VARCHAR(80) NOT NULL,
    lat DECIMAL(10, 6) NOT NULL,
    lng DECIMAL(10, 6) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    subscriber_id INT UNSIGNED NULL,
    assigned_technician_id INT UNSIGNED NULL,
    dispatched_at DATETIME NULL,
    technician_response ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    technician_work_status ENUM('assigned', 'en_route', 'completed', 'not_completed') NOT NULL DEFAULT 'assigned',
    technician_work_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    KEY idx_subscription_requests_status (status),
    KEY idx_subscription_requests_email (email),
    KEY idx_subscription_requests_technician (assigned_technician_id),
    CONSTRAINT fk_subscription_requests_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscription_requests_technician FOREIGN KEY (assigned_technician_id) REFERENCES technicians (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_subscription_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(20) NOT NULL,
    barangay_id INT UNSIGNED NOT NULL,
    status ENUM('active', 'resolved') NOT NULL,
    severity ENUM('critical', 'high', 'medium', 'low') NOT NULL,
    started_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    source VARCHAR(255) NOT NULL,
    suspected VARCHAR(255) NOT NULL,
    lat DECIMAL(10, 6) NOT NULL,
    lng DECIMAL(10, 6) NOT NULL,
    UNIQUE KEY uq_outages_public (public_id),
    KEY idx_outages_status (status),
    KEY idx_outages_barangay (barangay_id),
    CONSTRAINT fk_outages_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outage_subscribers (
    outage_id INT UNSIGNED NOT NULL,
    subscriber_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (outage_id, subscriber_id),
    KEY idx_os_subscriber (subscriber_id),
    CONSTRAINT fk_os_outage FOREIGN KEY (outage_id) REFERENCES outages (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_os_subscriber FOREIGN KEY (subscriber_id) REFERENCES subscribers (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(20) NOT NULL,
    outage_id INT UNSIGNED NOT NULL,
    technician_id INT UNSIGNED NOT NULL,
    status ENUM('assigned', 'en_route', 'resolved') NOT NULL DEFAULT 'assigned',
    assigned_at DATETIME NOT NULL,
    UNIQUE KEY uq_jobs_public (public_id),
    KEY idx_jobs_tech (technician_id),
    KEY idx_jobs_outage (outage_id),
    CONSTRAINT fk_jobs_outage FOREIGN KEY (outage_id) REFERENCES outages (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_jobs_tech FOREIGN KEY (technician_id) REFERENCES technicians (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(20) NOT NULL,
    opened_at DATETIME NOT NULL,
    status ENUM('open', 'in_progress', 'scheduled', 'closed') NOT NULL,
    priority ENUM('critical', 'high', 'medium', 'low') NOT NULL,
    component VARCHAR(80) NOT NULL,
    barangay_id INT UNSIGNED NOT NULL,
    outage_id INT UNSIGNED NULL,
    technician_id INT UNSIGNED NULL,
    action_text TEXT NOT NULL,
    linked_note TEXT NULL,
    UNIQUE KEY uq_tickets_public (public_id),
    KEY idx_tickets_status (status),
    KEY idx_tickets_priority (priority),
    CONSTRAINT fk_tickets_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_outage FOREIGN KEY (outage_id) REFERENCES outages (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_tickets_tech FOREIGN KEY (technician_id) REFERENCES technicians (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE monthly_metrics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_date DATE NOT NULL,
    tickets_opened INT UNSIGNED NOT NULL DEFAULT 0,
    tickets_resolved INT UNSIGNED NOT NULL DEFAULT 0,
    uptime_pct DECIMAL(5, 2) NOT NULL,
    UNIQUE KEY uq_metrics_month (period_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE component_incidents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    component VARCHAR(80) NOT NULL,
    incident_count INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_component (component)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_settings (
    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sms_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL,
    sent_at DATETIME NOT NULL,
    recipient_name VARCHAR(120) NOT NULL,
    recipient_role ENUM('technician', 'subscriber', 'admin') NOT NULL,
    phone VARCHAR(20) NOT NULL,
    context VARCHAR(40) NOT NULL DEFAULT 'outage',
    outage_public_id VARCHAR(20) NULL,
    message TEXT NOT NULL,
    status VARCHAR(32) NOT NULL,
    provider VARCHAR(32) NOT NULL,
    note TEXT NULL,
    UNIQUE KEY uq_sms_public (public_id),
    KEY idx_sms_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NULL,
    success TINYINT(1) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_email (email),
    KEY idx_login_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
