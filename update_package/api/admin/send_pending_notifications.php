<?php
/**
 * API: Send notifications to users with pending transactions
 */

require_once '../../config/database.php';
require_once '../../config/nalo.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Start output buffering to catch any unexpected output
ob_start();

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || empty($data['transaction_ids']) || empty($data['message'])) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
        exit;
    }

    $transactionIds = $data['transaction_ids'];
    $message = $data['message'];

    $pdo = getDBConnection();
    $successCount = 0;
    $errorCount = 0;

    // Fetch transaction details for the selected IDs
    $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
    $stmt = $pdo->prepare("
        SELECT t.*, u.fullname, u.email, u.id as user_id,
               e.title as event_title, n.name as nominee_name
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN events e ON t.event_id = e.id
        LEFT JOIN nominees n ON t.nominee_id = n.id
        WHERE t.id IN ($placeholders) AND t.status = 'pending'
    ");
    $stmt->execute($transactionIds);
    $transactions = $stmt->fetchAll();

    foreach ($transactions as $tx) {
        $notified = false;
        
        // Personalize message
        $personalizedMessage = str_replace(
            ['{event}', '{nominee}'],
            [$tx['event_title'] ?? 'the event', $tx['nominee_name'] ?? 'your nominee'],
            $message
        );

        // Get phone number - primary source is t.phone, fallback to metadata
        $phoneNumber = $tx['phone'];
        
        // If phone is empty, try to extract from metadata
        if (empty($phoneNumber)) {
            $metadata = is_string($tx['metadata']) ? json_decode($tx['metadata'], true) : $tx['metadata'];
            $phoneNumber = $metadata['phone'] ?? $metadata['msisdn'] ?? null;
            
            if ($phoneNumber) {
                error_log("SMS: Using phone from metadata for transaction " . $tx['id'] . ": " . $phoneNumber);
            }
        }

        // Send SMS if phone exists
        if (!empty($phoneNumber)) {
            $smsResult = sendNaloSMS($phoneNumber, $personalizedMessage);
            
            // Check for success in Nalo response
            if (isset($smsResult['status']) && $smsResult['status'] === 'success') {
                $notified = true;
            } else {
                error_log("SMS Failed for " . $phoneNumber . " (TX " . $tx['id'] . "): " . json_encode($smsResult));
            }
        } else {
            error_log("SMS Skipped: No phone number found for transaction " . $tx['id']);
        }

        if ($notified) {
            $successCount++;
            logAudit(getCurrentUserId(), 'sent_pending_sms', 'transactions', $tx['id'], null, ['message' => $personalizedMessage]);
        } else {
            $errorCount++;
        }
    }

    // Clear any accidental output (notices, warnings)
    $unexpectedOutput = ob_get_clean();
    if (!empty($unexpectedOutput)) {
        error_log("Unexpected output in send_pending_notifications: " . $unexpectedOutput);
    }

    echo json_encode([
        'status' => 'success',
        'message' => "SMS sending process completed. Sent: $successCount, Failed: $errorCount.",
        'success_count' => $successCount,
        'error_count' => $errorCount
    ]);

} catch (Exception $e) {
    ob_clean();
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
