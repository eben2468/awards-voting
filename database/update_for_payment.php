<?php
/**
 * Database Update for Payment Integration
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

try {
    // 1. Remove unique constraint from votes table to allow multiple votes
    // First, create a regular index on user_id so the foreign key doesn't complain
    $pdo->exec("CREATE INDEX idx_user_id ON votes(user_id)");
    echo "Created regular index on user_id.\n";
    
    // Now drop the unique constraint
    $pdo->exec("ALTER TABLE votes DROP INDEX unique_vote");
    echo "Removed unique constraint from votes table.\n";
} catch (Exception $e) {
    echo "Note: unique_vote index might not exist or already removed: " . $e->getMessage() . "\n";
}

try {
    // 2. Add transaction_id to votes table
    $pdo->exec("ALTER TABLE votes ADD COLUMN transaction_id INT DEFAULT NULL AFTER event_id");
    $pdo->exec("ALTER TABLE votes ADD CONSTRAINT fk_vote_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL");
    echo "Added transaction_id column to votes table.\n";
} catch (Exception $e) {
    echo "Note: transaction_id column might already exist: " . $e->getMessage() . "\n";
}

try {
    // 3. Ensure transactions table has category_id
    $pdo->exec("ALTER TABLE transactions ADD COLUMN category_id INT DEFAULT NULL AFTER nominee_id");
    $pdo->exec("ALTER TABLE transactions ADD CONSTRAINT fk_transaction_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL");
    echo "Added category_id column to transactions table.\n";
} catch (Exception $e) {
    echo "Note: category_id column might already exist in transactions: " . $e->getMessage() . "\n";
}

echo "Database updates completed.\n";
