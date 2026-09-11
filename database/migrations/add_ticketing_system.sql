-- Migration: Add Ticketing System Tables and Columns
-- Date: 2026-06-03

-- 1. Update events table to add event_type and event_code
ALTER TABLE events ADD COLUMN event_type ENUM('voting', 'ticketing', 'both') NOT NULL DEFAULT 'voting';
ALTER TABLE events ADD COLUMN event_code VARCHAR(50) DEFAULT NULL;
ALTER TABLE events ADD UNIQUE KEY unique_event_code (event_code);

-- 2. Create ticket_categories table
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

-- 3. Create tickets table
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

-- 4. Update transactions table to add ticket_category_id
ALTER TABLE transactions ADD COLUMN ticket_category_id INT DEFAULT NULL AFTER category_id;
ALTER TABLE transactions ADD CONSTRAINT fk_transaction_ticket_category FOREIGN KEY (ticket_category_id) REFERENCES ticket_categories(id) ON DELETE SET NULL;
