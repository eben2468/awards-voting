<?php
/**
 * Fix for Double Counting Votes
 * Adds unique constraint and cleans up duplicates
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

try {
    echo "Starting fix for double counting...\n";

    // 1. Identify and remove duplicates from votes table before adding unique constraint
    // We keep the one with the lowest ID for each transaction_id
    echo "Cleaning up existing duplicate votes...\n";
    $pdo->exec("
        DELETE v1 FROM votes v1
        INNER JOIN votes v2 
        WHERE v1.id > v2.id 
        AND v1.transaction_id = v2.transaction_id 
        AND v1.transaction_id IS NOT NULL
    ");
    echo "Duplicates cleaned up.\n";

    // 2. Add UNIQUE constraint to transaction_id in votes table
    echo "Adding UNIQUE constraint to transaction_id...\n";
    try {
        $pdo->exec("ALTER TABLE votes ADD UNIQUE INDEX unique_transaction_vote (transaction_id)");
        echo "UNIQUE constraint added successfully.\n";
    } catch (Exception $e) {
        echo "Note: UNIQUE constraint might already exist: " . $e->getMessage() . "\n";
    }

    echo "Fix applied successfully.\n";
} catch (Exception $e) {
    echo "Error applying fix: " . $e->getMessage() . "\n";
}
