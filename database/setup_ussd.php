<?php
/**
 * Setup USSD Sessions Table
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "Setting up USSD sessions table...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS ussd_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100) NOT NULL UNIQUE,
        msisdn VARCHAR(20) NOT NULL,
        current_step INT DEFAULT 1,
        nominee_id INT DEFAULT NULL,
        vote_count INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_session (session_id),
        INDEX idx_msisdn (msisdn)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    echo "✅ USSD sessions table created successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
