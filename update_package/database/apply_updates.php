<?php
/**
 * Apply Database Updates
 * Awards Voting System
 */

require_once '../config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "Applying database updates...\n\n";
    
    // 1. Add organizer role to users table
    echo "1. Adding organizer role to users table...\n";
    $stmt = $pdo->prepare("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'organizer', 'user') DEFAULT 'user'");
    $stmt->execute();
    echo "✓ Added organizer role\n";
    
    // 2. Remove the unique constraint that prevents multiple votes per category
    echo "2. Removing unique vote constraint...\n";
    try {
        $stmt = $pdo->prepare("ALTER TABLE votes DROP INDEX unique_vote");
        $stmt->execute();
        echo "✓ Removed unique vote constraint\n";
    } catch (PDOException $e) {
        echo "⚠ Constraint already removed or doesn't exist\n";
    }
    
    // 3. Add organizer-specific fields to events table
    echo "3. Adding organizer fields to events table...\n";
    try {
        $stmt = $pdo->prepare("ALTER TABLE events ADD COLUMN organizer_id INT DEFAULT NULL");
        $stmt->execute();
        echo "✓ Added organizer_id column\n";
    } catch (PDOException $e) {
        echo "⚠ organizer_id column already exists\n";
    }
    
    try {
        $stmt = $pdo->prepare("ALTER TABLE events ADD COLUMN payout_requested BOOLEAN DEFAULT FALSE");
        $stmt->execute();
        echo "✓ Added payout_requested column\n";
    } catch (PDOException $e) {
        echo "⚠ payout_requested column already exists\n";
    }
    
    try {
        $stmt = $pdo->prepare("ALTER TABLE events ADD COLUMN payout_amount DECIMAL(10, 2) DEFAULT 0.00");
        $stmt->execute();
        echo "✓ Added payout_amount column\n";
    } catch (PDOException $e) {
        echo "⚠ payout_amount column already exists\n";
    }
    
    try {
        $stmt = $pdo->prepare("ALTER TABLE events ADD COLUMN payout_status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending'");
        $stmt->execute();
        echo "✓ Added payout_status column\n";
    } catch (PDOException $e) {
        echo "⚠ payout_status column already exists\n";
    }
    
    try {
        $stmt = $pdo->prepare("ALTER TABLE events ADD COLUMN payout_request_date TIMESTAMP NULL");
        $stmt->execute();
        echo "✓ Added payout_request_date column\n";
    } catch (PDOException $e) {
        echo "⚠ payout_request_date column already exists\n";
    }
    
    // Add foreign key constraint
    try {
        $stmt = $pdo->prepare("ALTER TABLE events ADD FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL");
        $stmt->execute();
        echo "✓ Added foreign key constraint for organizer_id\n";
    } catch (PDOException $e) {
        echo "⚠ Foreign key constraint already exists\n";
    }
    
    // 4. Create organizer_payouts table
    echo "4. Creating organizer_payouts table...\n";
    $createTableSQL = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $stmt = $pdo->prepare($createTableSQL);
    $stmt->execute();
    echo "✓ Created organizer_payouts table\n";
    
// 5. Insert sample organizer user
    echo "5. Creating sample organizer user...\n";
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users (fullname, email, phone, password, role, status) 
            VALUES ('Sample Organizer', 'organizer@example.com', '+233123456789', ?, 'organizer', 'active')"
        );
        // Using the same password hash as admin: Admin@123
        $passwordHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $stmt->execute([$passwordHash]);
        echo "✓ Created sample organizer user (email: organizer@example.com, password: Admin@123)\n";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            echo "⚠ Sample organizer user already exists\n";
        } else {
            echo "⚠ Error creating sample organizer: " . $e->getMessage() . "\n";
        }
    }
    
    // 6. Create contact_messages table
    echo "6. Creating contact_messages table...\n";
    try {
        $createContactTableSQL = 
            "CREATE TABLE IF NOT EXISTS contact_messages (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $stmt = $pdo->prepare($createContactTableSQL);
        $stmt->execute();
        echo "✓ Created contact_messages table\n";
    } catch (PDOException $e) {
        echo "⚠ Error creating contact_messages table: " . $e->getMessage() . "\n";
    }

    // 7. Add allow_results column to categories table
    echo "7. Adding allow_results column to categories table...\n";
    try {
        $stmt = $pdo->prepare("ALTER TABLE categories ADD COLUMN allow_results BOOLEAN DEFAULT FALSE");
        $stmt->execute();
        echo "✓ Added allow_results column to categories table\n";
    } catch (PDOException $e) {
        echo "⚠ Column allow_results already exists or error: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ All database updates applied successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Update authentication logic to redirect organizers\n";
    echo "2. Remove voting restrictions\n";
    echo "3. Create organizer portal\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
