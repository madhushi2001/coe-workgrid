-- Apply once to an existing COE Workgrid database to enable admin-created customer accounts.
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
