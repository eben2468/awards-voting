<?php
/**
 * Web-based Database Migration Script
 * Awards Voting System
 */

require_once 'config/database.php';

echo "<pre>";

try {
    $pdo = getDBConnection();
    echo "Connected to database successfully.\n";
    
    // Read the migration file
    $sql = file_get_contents('database/migrations/add_phone_to_transactions.sql');
    
    if (!$sql) {
        throw new Exception("Could not read migration file.");
    }
    
    echo "Executing migration...\n";
    
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            echo "Running: " . htmlspecialchars(substr($query, 0, 50)) . "...\n";
            try {
                $pdo->exec($query);
                echo "Success.\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "Duplicate column name") !== false) {
                    echo "Column already exists, skipping.\n";
                } elseif (strpos($e->getMessage(), "Duplicate key name") !== false) {
                    echo "Index already exists, skipping.\n";
                } else {
                    echo "Error: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "Migration completed successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
