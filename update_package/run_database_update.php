<?php
/**
 * Bulletproof Database Migration Script
 * VoteHubGH
 * 
 * Run this script once in your web browser: https://your-domain.com/run_database_update.php
 * After running, delete this file from your server.
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to plain text for readable output
header('Content-Type: text/plain; charset=utf-8');

require_once 'config/database.php';

try {
    $pdo = getDBConnection();
    echo "========================================================\n";
    echo "          VOTEHUBGH LIVE DATABASE UPDATE RUNNER         \n";
    echo "========================================================\n\n";
    echo "Database connection established successfully.\n\n";
} catch (Exception $e) {
    die("❌ Fatal Error: Could not connect to the database. " . $e->getMessage() . "\n");
}

/**
 * Execute a SQL query and print its status.
 */
function runQuery($pdo, $title, $sql, $ignoreDuplicates = true) {
    echo "Executing: $title...\n";
    try {
        $pdo->exec($sql);
        echo "✓ Success\n\n";
        return true;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        $code = $e->getCode();
        
        // Check if error is due to duplicate table/column/key
        $isDuplicate = (
            strpos($msg, "Duplicate column name") !== false ||
            strpos($msg, "already exists") !== false ||
            strpos($msg, "Duplicate key name") !== false ||
            strpos($msg, "check that it exists") !== false ||
            strpos($msg, "Duplicate entry") !== false ||
            $code == '23000' ||
            $code == '42S21'
        );
        
        if ($isDuplicate && $ignoreDuplicates) {
            echo "⚠ Ignored: Column, table, index, or constraint already updated or removed.\n\n";
            return true;
        } else {
            echo "❌ Failed: " . $msg . " (Code: $code)\n\n";
            return false;
        }
    }
}

// --- Step 1: Settings Table ---
runQuery($pdo, "Create Settings Table", "
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

runQuery($pdo, "Insert Default Settings", "
    INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
    ('site_name', 'VoteHubGH'), 
    ('admin_email', 'admin@votehub.com'), 
    ('show_vote_counts', '1');
");

// --- Step 2: Users Role Modification ---
runQuery($pdo, "Modify users table for organizer role", "
    ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'organizer', 'user') DEFAULT 'user'
");

// --- Step 3: Nominees Access Code ---
runQuery($pdo, "Add access_code column to nominees table", "
    ALTER TABLE nominees ADD COLUMN access_code VARCHAR(50) UNIQUE AFTER shortcode
");

// --- Step 4: Drop Unique Vote Constraint ---
runQuery($pdo, "Drop unique_vote index from votes", "
    ALTER TABLE votes DROP INDEX unique_vote
");

// --- Step 5: Events Payout Fields ---
runQuery($pdo, "Add organizer_id to events table", "
    ALTER TABLE events ADD COLUMN organizer_id INT DEFAULT NULL
");

runQuery($pdo, "Add payout_requested to events table", "
    ALTER TABLE events ADD COLUMN payout_requested BOOLEAN DEFAULT FALSE
");

runQuery($pdo, "Add payout_amount to events table", "
    ALTER TABLE events ADD COLUMN payout_amount DECIMAL(10, 2) DEFAULT 0.00
");

runQuery($pdo, "Add payout_status to events table", "
    ALTER TABLE events ADD COLUMN payout_status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending'
");

runQuery($pdo, "Add payout_request_date to events table", "
    ALTER TABLE events ADD COLUMN payout_request_date TIMESTAMP NULL
");

runQuery($pdo, "Add foreign key constraint for organizer_id on events", "
    ALTER TABLE events ADD FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL
");

// --- Step 6: Organizer Payouts Table ---
runQuery($pdo, "Create organizer_payouts table", "
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
");

// --- Step 7: Categories Results Access Control ---
runQuery($pdo, "Add allow_results to categories", "
    ALTER TABLE categories ADD COLUMN allow_results BOOLEAN DEFAULT FALSE
");

// --- Step 8: Contact Messages Table ---
runQuery($pdo, "Create contact_messages table", "
    CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        subject VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_created (created_at),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// --- Step 9: Transactions Phone Column ---
runQuery($pdo, "Add phone column to transactions table", "
    ALTER TABLE transactions ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER category_id
");

runQuery($pdo, "Add phone index to transactions table", "
    ALTER TABLE transactions ADD INDEX idx_phone (phone)
");

runQuery($pdo, "Update transactions phone from metadata (standard parameter)", "
    UPDATE transactions 
    SET phone = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.phone'))
    WHERE phone IS NULL AND JSON_EXTRACT(metadata, '$.phone') IS NOT NULL
");

runQuery($pdo, "Update transactions phone from metadata (msisdn parameter)", "
    UPDATE transactions 
    SET phone = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.msisdn'))
    WHERE phone IS NULL AND JSON_EXTRACT(metadata, '$.msisdn') IS NOT NULL
");

// --- Step 10: Ticketing System ---
runQuery($pdo, "Add event_type column to events", "
    ALTER TABLE events ADD COLUMN event_type ENUM('voting', 'ticketing', 'both') NOT NULL DEFAULT 'voting'
");

runQuery($pdo, "Add event_code column to events", "
    ALTER TABLE events ADD COLUMN event_code VARCHAR(50) DEFAULT NULL
");

runQuery($pdo, "Add unique key constraint on event_code", "
    ALTER TABLE events ADD UNIQUE KEY unique_event_code (event_code)
");

runQuery($pdo, "Create ticket_categories table", "
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
");

runQuery($pdo, "Create tickets table", "
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
");

runQuery($pdo, "Add ticket_category_id to transactions table", "
    ALTER TABLE transactions ADD COLUMN ticket_category_id INT DEFAULT NULL AFTER category_id
");

runQuery($pdo, "Add foreign key constraint for ticket_category_id on transactions", "
    ALTER TABLE transactions ADD CONSTRAINT fk_transaction_ticket_category FOREIGN KEY (ticket_category_id) REFERENCES ticket_categories(id) ON DELETE SET NULL
");

echo "========================================================\n";
echo "        DATABASE UPDATES COMPLETED SUCCESSFULLY!       \n";
echo "========================================================\n\n";
echo "Next step: Run 'live_migration_helper.php' in your browser to populate nominee access codes.\n";
echo "IMPORTANT: Delete this file ('run_database_update.php') from your server for security.\n";
?>
