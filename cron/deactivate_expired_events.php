<?php
/**
 * Cron Job: Deactivate Expired Events
 * 
 * This script should be run periodically (every 1-5 minutes) to automatically:
 * 1. Set expired events to 'completed' status
 * 2. Deactivate all nominee codes for expired events
 * 3. Cancel any pending transactions for expired events
 * 4. Notify organizers and admins
 * 
 * Setup (Linux/cPanel):
 *   crontab -e
 *   * * * * * php /path/to/awards-voting/cron/deactivate_expired_events.php >> /path/to/awards-voting/cron/cron.log 2>&1
 * 
 * Setup (Windows Task Scheduler):
 *   Create a task that runs every minute:
 *   Action: C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\awards-voting\cron\deactivate_expired_events.php
 * 
 * You can also call this via URL for testing:
 *   https://yourdomain.com/cron/deactivate_expired_events.php?key=CRON_KEY_FROM_ENV
 */

// Security: Only allow CLI execution or requests with a valid secret key
$isCliExecution = (php_sapi_name() === 'cli');
require_once __DIR__ . '/../config/env.php';
$secretKey = (string) env('CRON_KEY', '');
$providedKey = (string) ($_GET['key'] ?? '');

if (!$isCliExecution && ($secretKey === '' || !hash_equals($secretKey, $providedKey))) {
    http_response_code(403);
    die('Access denied. Provide a valid key or run from CLI.');
}

require_once __DIR__ . '/../config/database.php';

// Output header for web access
if (!$isCliExecution) {
    header('Content-Type: application/json');
}

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "[$timestamp] Starting expired events deactivation...\n";

try {
    $results = deactivateExpiredEvents();
    
    $elapsed = round((microtime(true) - $startTime) * 1000, 2);
    
    if (isset($results['error'])) {
        echo "[$timestamp] ERROR: {$results['error']}\n";
    } else {
        echo "[$timestamp] Completed in {$elapsed}ms\n";
        echo "[$timestamp] Events deactivated: {$results['events_deactivated']}\n";
        echo "[$timestamp] Nominees deactivated: {$results['nominees_deactivated']}\n";
        
        if (!empty($results['events'])) {
            foreach ($results['events'] as $event) {
                echo "[$timestamp]   - Event #{$event['id']}: {$event['title']} (ended: {$event['end_date']}, {$event['nominees_deactivated']} nominees deactivated)\n";
            }
        }
    }
    
    // JSON output for web access
    if (!$isCliExecution) {
        echo "\n" . json_encode([
            'success' => true,
            'timestamp' => $timestamp,
            'elapsed_ms' => $elapsed,
            'results' => $results
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    echo "[$timestamp] FATAL ERROR: " . $e->getMessage() . "\n";
    error_log("Cron deactivate_expired_events FATAL: " . $e->getMessage());
    
    if (!$isCliExecution) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
