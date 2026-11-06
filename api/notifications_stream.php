<?php
/**
 * Server-Sent Events (SSE) for Real-time Notifications
 */

require_once __DIR__ . '/../config/database.php';

// Prevent session locking
session_write_close();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering for Nginx

if (!isLoggedIn()) {
    echo "data: " . json_encode(['error' => 'Unauthorized']) . "\n\n";
    exit;
}

$user_id = getCurrentUserId();
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

// Set time limit to 0 for long running script
set_time_limit(0);

while (true) {
    try {
        $db = getDBConnection();
        
        // Check for new notifications
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND id > ? 
            ORDER BY id ASC
        ");
        $stmt->execute([$user_id, $last_id]);
        $notifications = $stmt->fetchAll();

        if (!empty($notifications)) {
            foreach ($notifications as $notification) {
                $notification['link'] = notificationUrl($notification['link']);
                echo "data: " . json_encode($notification) . "\n\n";
                $last_id = $notification['id'];
            }
            // Flush the output buffer
            if (ob_get_level() > 0) ob_flush();
            flush();
        }

        // Also send a heartbeat to keep connection alive
        echo ": heartbeat\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();

    } catch (Exception $e) {
        // Log error and exit if DB fails
        error_log("SSE Error: " . $e->getMessage());
        break;
    }

    // Wait for 3 seconds before checking again
    sleep(3);
}
