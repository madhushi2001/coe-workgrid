-- Apply once to an existing COE Workgrid database to enable role-based admin accounts.
ALTER TABLE admin_users
    ADD COLUMN full_name VARCHAR(120) NOT NULL DEFAULT '' AFTER id,
    ADD COLUMN role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin' AFTER password_hash,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;

UPDATE admin_users SET
    full_name = CASE WHEN full_name = '' THEN SUBSTRING_INDEX(email, '@', 1) ELSE full_name END,
    role = 'super_admin';
