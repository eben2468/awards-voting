<?php
/**
 * Payment Callback for Paystack (USSD Mobile Money Payments)
 * This webhook handles payment confirmations from Paystack's charge API
 */

// Handle test request (GET request to verify endpoint) - BEFORE loading any configs
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success', 
        'message' => 'Paystack callback endpoint is working',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Only load configs for POST requests (actual webhooks)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/nalo.php';
require_once __DIR__ . '/../config/paystack.php';

// Handle POST request (actual webhook)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Verify Paystack signature to ensure request is from Paystack
$paystackSignature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$input = file_get_contents('php://input');

if ($paystackSignature === '' || !hash_equals(hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY), $paystackSignature)) {
    http_response_code(401);
    exit('Invalid signature');
}

$data = json_decode($input, true);

if (!$data || !isset($data['event'])) {
    http_response_code(400);
    exit('Invalid Request - No valid JSON data received');
}

// Only process charge.success events
$event = $data['event'] ?? '';
$chargeData = $data['data'] ?? [];

if ($event !== 'charge.success') {
    // Acknowledge non-success events but don't process
    http_response_code(200);
    echo json_encode(['status' => 'acknowledged', 'message' => 'Event type not processed: ' . $event]);
    exit;
}

$status = $chargeData['status'] ?? '';
$reference = $chargeData['reference'] ?? '';
$amount = ($chargeData['amount'] ?? 0) / 100; // Paystack returns amount in pesewas
$metadata = $chargeData['metadata'] ?? [];

// Decode metadata if it's a JSON string
if (is_string($metadata)) {
    $metadata = json_decode($metadata, true);
}

$msisdn = $metadata['msisdn'] ?? '';

$db = getDBConnection();

// Find the transaction
$stmt = $db->prepare("SELECT * FROM transactions WHERE reference = ? AND status = 'pending'");
$stmt->execute([$reference]);
$transaction = $stmt->fetch();

if (!$transaction) {
    http_response_code(404);
    exit('Transaction not found or already processed');
}

// Fallback: Get MSISDN from database metadata if not in webhook metadata
if (empty($msisdn) && !empty($transaction['metadata'])) {
    $dbMeta = is_string($transaction['metadata']) ? json_decode($transaction['metadata'], true) : $transaction['metadata'];
    $msisdn = $dbMeta['msisdn'] ?? '';
}

if ($status === 'success') {
    // Real-time check: Verify event hasn't expired before recording vote
    if (isEventExpired($transaction['event_id'])) {
        // Event has ended - mark transaction as failed, don't record vote
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'failed', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'");
        $stmt->execute([$transaction['id']]);
        
        error_log("USSD Callback: Vote blocked for expired event #{$transaction['event_id']}, transaction #{$transaction['id']}");
        http_response_code(200);
        echo json_encode(['status' => 'failed', 'message' => 'Event has ended. Voting is closed.']);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Update Transaction
        $stmt = $db->prepare("UPDATE transactions SET status = 'success', provider = 'paystack', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'");

        $stmt->execute([$transaction['id']]);

        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Already processed']);
            exit;
        }


        $quantity = $transaction['quantity'] ?? 1;

        // 2. Record Vote
        $stmt = $db->prepare("INSERT INTO votes (nominee_id, category_id, event_id, transaction_id, vote_count, ip_address, user_agent) 
                             SELECT n.id, n.category_id, c.event_id, ?, ?, 'USSD', 'Paystack Mobile Money'
                             FROM nominees n
                             JOIN categories c ON n.category_id = c.id
                             WHERE n.id = ?");
        $stmt->execute([$transaction['id'], $quantity, $transaction['nominee_id']]);

        // 3. Update Nominee Vote Count
        $stmt = $db->prepare("UPDATE nominees SET vote_count = vote_count + ? WHERE id = ?");
        $stmt->execute([$quantity, $transaction['nominee_id']]);

        // 4. Update Event Total Votes
        $stmt = $db->prepare("UPDATE events SET total_votes = total_votes + ? WHERE id = ?");
        $stmt->execute([$quantity, $transaction['event_id']]);

        $db->commit();

        // 5. Send Confirmation SMS via Nalo Solutions
        // Get full details for comprehensive SMS
        $detailsStmt = $db->prepare("
            SELECT n.name as nominee_name, e.title as event_title, c.name as category_name
            FROM nominees n
            JOIN categories c ON n.category_id = c.id
            JOIN events e ON c.event_id = e.id
            WHERE n.id = ?
        ");
        $detailsStmt->execute([$transaction['nominee_id']]);
        $details = $detailsStmt->fetch();
        
        if ($msisdn && $details) {
            sendVoteConfirmationSMS(
                $msisdn,
                $amount, // Amount from Paystack data
                CURRENCY,
                $details['nominee_name'],
                $details['event_title'],
                $details['category_name'],
                $quantity,
                $reference
            );
        }

        // 6. Create Notifications
        $eventStmt = $db->prepare("SELECT organizer_id, created_by, title FROM events WHERE id = ?");
        $eventStmt->execute([$transaction['event_id']]);
        $event = $eventStmt->fetch();
        
        if ($event) {
            notifyEventActivity(
                $event['organizer_id'] ?: $event['created_by'],
                'New Vote Recorded',
                'A new vote has been cast for ' . $details['nominee_name'] . ' in ' . $event['title'],
                'votes/?event=' . $transaction['event_id']
            );
        }

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Vote recorded']);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        exit('Error processing vote: ' . $e->getMessage());
    }
} else {
    // Update transaction as failed
    $stmt = $db->prepare("UPDATE transactions SET status = 'failed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$transaction['id']]);
    
    http_response_code(200);
    echo json_encode(['status' => 'failed', 'message' => 'Payment failed']);
}