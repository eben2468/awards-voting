-- =====================================================================
-- Auth & Security Upgrade
-- Awards Voting System (VoteHubGH)
--
-- Adds:
--   1. Two-step (2FA) login verification codes sent by email
--   2. Organizer account approval workflow
--   3. Verified email-address changes from the profile page
--   4. Email-based password reset (link + code)
--   5. SMTP (Gmail) mail settings
--
-- SAFE TO RUN MORE THAN ONCE.
-- Every change is checked first, so re-running this file will not produce
-- "#1060 Duplicate column name" or "#1061 Duplicate key name" errors.
--
-- Import it in phpMyAdmin (Import tab), or paste into the SQL tab.
-- A guided alternative is: /database/run_auth_migration.php
-- =====================================================================

-- Helper: adds a column only when it is not already there.
DROP PROCEDURE IF EXISTS vh_add_column;
DELIMITER $$
CREATE PROCEDURE vh_add_column(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- Helper: adds an index only when it is not already there.
DROP PROCEDURE IF EXISTS vh_add_index;
DELIMITER $$
CREATE PROCEDURE vh_add_index(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_columns VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_columns, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 1. USERS: approval workflow + 2FA preference
-- ---------------------------------------------------------------------
CALL vh_add_column('users', 'approval_status',
    "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER status");
CALL vh_add_column('users', 'approved_at',      'TIMESTAMP NULL DEFAULT NULL AFTER approval_status');
CALL vh_add_column('users', 'approved_by',      'INT DEFAULT NULL AFTER approved_at');
CALL vh_add_column('users', 'approval_note',    'TEXT DEFAULT NULL AFTER approved_by');
CALL vh_add_column('users', 'two_factor_enabled', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER approval_note');
CALL vh_add_index('users', 'idx_approval_status', '`approval_status`');

-- Everyone who already had an account stays approved and can keep logging in
UPDATE users
SET approval_status = 'approved',
    approved_at = COALESCE(approved_at, created_at)
WHERE approval_status = 'approved' AND approved_at IS NULL;

-- ---------------------------------------------------------------------
-- 2. LOGIN VERIFICATION CODES (two-step verification)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    purpose VARCHAR(30) NOT NULL DEFAULT 'login',
    attempts INT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_purpose (user_id, purpose),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. EMAIL CHANGE REQUESTS (verified email edits from the profile page)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    old_email VARCHAR(100) NOT NULL,
    new_email VARCHAR(100) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_new_email (new_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. PASSWORD RESETS: one-time codes, single use
-- ---------------------------------------------------------------------
-- IMPORTANT: expires_at was the table's first TIMESTAMP column, so MySQL
-- silently gave it "ON UPDATE CURRENT_TIMESTAMP". Every UPDATE on the row
-- (such as counting a wrong code attempt) reset the expiry to NOW(), which
-- expired the reset request instantly. DATETIME has no such behaviour.
ALTER TABLE password_resets MODIFY COLUMN expires_at DATETIME NOT NULL;

CALL vh_add_column('password_resets', 'code_hash',  'VARCHAR(255) DEFAULT NULL AFTER token');
CALL vh_add_column('password_resets', 'attempts',   'INT NOT NULL DEFAULT 0 AFTER code_hash');
CALL vh_add_column('password_resets', 'used_at',    'DATETIME NULL DEFAULT NULL AFTER expires_at');
CALL vh_add_column('password_resets', 'ip_address', 'VARCHAR(45) DEFAULT NULL AFTER used_at');

-- ---------------------------------------------------------------------
-- 5. SETTINGS TABLE INTEGRITY
-- ---------------------------------------------------------------------
-- Without a unique key on setting_key, saving a setting can append a second
-- row instead of replacing the first, so changes appear not to save.
-- Remove any duplicates (keeping the newest), then enforce uniqueness.
DELETE s1 FROM settings s1
INNER JOIN settings s2
    ON s1.setting_key = s2.setting_key
   AND s1.id < s2.id;

DROP PROCEDURE IF EXISTS vh_unique_setting_key;
DELIMITER $$
CREATE PROCEDURE vh_unique_setting_key()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'settings'
          AND COLUMN_NAME = 'setting_key'
          AND NON_UNIQUE = 0
    ) THEN
        ALTER TABLE settings ADD UNIQUE KEY uniq_setting_key (setting_key);
    END IF;
END$$
DELIMITER ;
CALL vh_unique_setting_key();

-- ---------------------------------------------------------------------
-- 6. MAIL + SECURITY SETTINGS (defaults only; never overwrites your values)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('smtp_enabled', '0'),
    ('smtp_host', 'smtp.gmail.com'),
    ('smtp_port', '587'),
    ('smtp_secure', 'tls'),
    ('smtp_username', ''),
    ('smtp_password', ''),
    ('smtp_from_email', ''),
    ('smtp_from_name', 'VoteHubGH'),
    ('approval_notify_email', ''),
    ('two_factor_required', '1'),
    ('two_factor_fallback', '1'),
    ('organizer_approval_required', '1');

-- ---------------------------------------------------------------------
-- Clean up the helper procedures
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS vh_add_column;
DROP PROCEDURE IF EXISTS vh_add_index;
DROP PROCEDURE IF EXISTS vh_unique_setting_key;
