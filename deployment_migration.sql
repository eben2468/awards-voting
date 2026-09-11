-- 1. Create the settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Insert default settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
('site_name', 'VoteHubGH'), 
('admin_email', 'admin@votehub.com'), 
('show_vote_counts', '1');

-- 3. Add access_code column to nominees table
ALTER TABLE nominees ADD COLUMN access_code VARCHAR(50) UNIQUE AFTER shortcode;

-- Note: Existing nominees will need access codes generated. 
-- You can run the migration script (migrate_nominees.php) on the live server 
-- once to populate these codes for existing data.
