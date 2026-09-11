<?php
/**
 * Run Ticketing System Database Migration
 */
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    echo "Database connection established.\n";
    
    $sqlFile = __DIR__ . '/migrations/add_ticketing_system.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Simple query splitter
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        // Remove SQL comments for display
        $displayQuery = preg_replace('/--.*\n/', '', $query);
        $displayQuery = trim(substr($displayQuery, 0, 70));
        
        echo "Executing: $displayQuery...\n";
        
        try {
            $pdo->exec($query);
            echo "✓ Success\n";
        } catch (PDOException $e) {
            // Handle expected "duplicate" errors if run multiple times
            if (strpos($e->getMessage(), "Duplicate column name") !== false) {
                echo "⚠ Column already exists, skipped.\n";
            } elseif (strpos($e->getMessage(), "Duplicate key name") !== false) {
                echo "⚠ Key/index already exists, skipped.\n";
            } elseif (strpos($e->getMessage(), "already exists") !== false) {
                echo "⚠ Already exists, skipped.\n";
            } else {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\nMigration script completed.\n";
    
} catch (Exception $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}
