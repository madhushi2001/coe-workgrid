CREATE DATABASE IF NOT EXISTS coe_workgrid
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE coe_workgrid;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_accounts_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS workspaces (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS membership_plans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    monthly_price DECIMAL(10, 2) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    features TEXT NOT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    workspace_id INT UNSIGNED NOT NULL,
    membership_plan_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    message VARCHAR(1000) NULL,
    status ENUM('pending', 'under_review', 'approved', 'awaiting_payment', 'confirmed', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bookings_status_created (status, created_at),
    KEY idx_bookings_dates_workspace (workspace_id, start_date, end_date),
    KEY idx_bookings_email (email),
    CONSTRAINT fk_bookings_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id),
    CONSTRAINT fk_bookings_plan FOREIGN KEY (membership_plan_id) REFERENCES membership_plans (id),
    CONSTRAINT chk_bookings_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB;

INSERT INTO workspaces (id, name, description, capacity) VALUES
    (1, 'Open coworking', 'Flexible coworking access.', 24),
    (2, 'Dedicated desk', 'A personal desk in a shared studio.', 12),
    (3, 'Private office', 'A private workspace for a team.', 6)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO membership_plans (id, name, monthly_price, description, features, is_featured) VALUES
    (1, 'Daylight', 4900.00, 'A welcoming place to find your rhythm.', 'Flexible coworking access\nFast Wi-Fi\nTea and coffee', 0),
    (2, 'Workweek', 8900.00, 'A consistent routine, with room to focus.', 'Dedicated desk access\nMeeting room hours\nAll Daylight benefits', 1),
    (3, 'Studio', 14900.00, 'More privacy and space to do your best work.', 'Private workspace access\nPriority meeting rooms\nAll Workweek benefits', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name);
