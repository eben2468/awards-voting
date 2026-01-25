-- Awards Voting System Database Updates
-- Add organizer role and modify voting constraints

USE awards_voting;

-- 1. Add organizer role to users table
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'organizer', 'user') DEFAULT 'user';

-- 2. Remove the unique constraint that prevents multiple votes per category
ALTER TABLE votes DROP INDEX unique_vote;

-- 3. Add organizer-specific fields to events table
ALTER TABLE events ADD COLUMN organizer_id INT DEFAULT NULL;
ALTER TABLE events ADD COLUMN payout_requested BOOLEAN DEFAULT FALSE;
ALTER TABLE events ADD COLUMN payout_amount DECIMAL(10, 2) DEFAULT 0.00;
ALTER TABLE events ADD COLUMN payout_status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending';
ALTER TABLE events ADD COLUMN payout_request_date TIMESTAMP NULL;
ALTER TABLE events ADD FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL;

-- 4. Create organizer_payouts table for tracking withdrawal requests
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

-- 5. Insert sample organizer user
INSERT INTO users (fullname, email, phone, password, role, status) VALUES
('Sample Organizer', 'organizer@example.com', '+233123456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'organizer', 'active');
