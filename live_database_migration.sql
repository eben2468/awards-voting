-- ====================================================================
-- VoteHubGH - Combined Live Database Migration Script
-- Run this script once on your live database (e.g., via phpMyAdmin SQL tab)
-- to apply all updates, including organizers, payouts, access codes,
-- transactions phone tracking, and the new ticketing system.
-- ====================================================================

-- 1. Settings Table & Defaults
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
('site_name', 'VoteHubGH'), 
('admin_email', 'admin@votehub.com'), 
('show_vote_counts', '1');

-- 2. Modify Users Table for Organizer Role
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'organizer', 'user') DEFAULT 'user';

-- 3. Modify Nominees Table for Access Code
-- (If access_code already exists, this statement can be skipped or ignored)
ALTER TABLE nominees ADD COLUMN access_code VARCHAR(50) UNIQUE AFTER shortcode;

-- 4. Remove Unique Vote Constraints (To support multiple votes per category)
ALTER TABLE votes DROP INDEX unique_vote;

-- 5. Modify Events Table for Organizers & Payouts
ALTER TABLE events ADD COLUMN organizer_id INT DEFAULT NULL;
ALTER TABLE events ADD COLUMN payout_requested BOOLEAN DEFAULT FALSE;
ALTER TABLE events ADD COLUMN payout_amount DECIMAL(10, 2) DEFAULT 0.00;
ALTER TABLE events ADD COLUMN payout_status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending';
ALTER TABLE events ADD COLUMN payout_request_date TIMESTAMP NULL;
ALTER TABLE events ADD FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL;

-- 6. Create Organizer Payouts Table
CREATE TABLE IF NOT EXISTS organizer_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT NOT NULL,
    event_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_details JSON DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_organizer (organizer_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Add Results Access Control to Categories Table
ALTER TABLE categories ADD COLUMN allow_results BOOLEAN DEFAULT FALSE;

-- 8. Add Phone Column to Transactions Table
ALTER TABLE transactions ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER category_id;
ALTER TABLE transactions ADD INDEX idx_phone (phone);

-- 9. Extract Existing Phone Numbers from Transactions Metadata
UPDATE transactions 
SET phone = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.phone'))
WHERE phone IS NULL AND JSON_EXTRACT(metadata, '$.phone') IS NOT NULL;

UPDATE transactions 
SET phone = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.msisdn'))
WHERE phone IS NULL AND JSON_EXTRACT(metadata, '$.msisdn') IS NOT NULL;

-- 10. Add Ticketing Columns to Events Table
ALTER TABLE events ADD COLUMN event_type ENUM('voting', 'ticketing', 'both') NOT NULL DEFAULT 'voting';
ALTER TABLE events ADD COLUMN event_code VARCHAR(50) DEFAULT NULL;
ALTER TABLE events ADD UNIQUE KEY unique_event_code (event_code);

-- 11. Create Ticket Categories Table
CREATE TABLE IF NOT EXISTS ticket_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Create Tickets Table
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT DEFAULT NULL,
    event_id INT NOT NULL,
    ticket_category_id INT NOT NULL,
    ticket_code VARCHAR(50) UNIQUE NOT NULL,
    quantity INT DEFAULT 1,
    phone VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_category_id) REFERENCES ticket_categories(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id),
    INDEX idx_category_id (ticket_category_id),
    INDEX idx_ticket_code (ticket_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Add Ticket Category Connection to Transactions Table
ALTER TABLE transactions ADD COLUMN ticket_category_id INT DEFAULT NULL AFTER category_id;
ALTER TABLE transactions ADD CONSTRAINT fk_transaction_ticket_category FOREIGN KEY (ticket_category_id) REFERENCES ticket_categories(id) ON DELETE SET NULL;
