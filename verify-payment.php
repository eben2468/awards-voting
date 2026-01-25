<?php
/**
 * Payment Verification Script
 * Handles Paystack callback and records votes
 */

ob_start();
require_once 'config/database.php';
require_once 'config/paystack.php';
require_once 'config/nalo.php';

$pdo = getDBConnection();

// Get reference from GET (callback) or POST (webhook/AJAX)
$reference = $_GET['reference'] ?? $_POST['reference'] ?? '';

if (empty($reference)) {
    ob_clean();
    die(json_encode(['success' => false, 'message' => 'No reference provided.']));
}

try {
    // 1. Verify transaction with Paystack
    $paystackResponse = verifyPaystack($reference);
    
    if (!$paystackResponse['status'] || $paystackResponse['data']['status'] !== 'success') {
        throw new Exception('Payment verification failed or payment not successful.');
    }
    
    $data = $paystackResponse['data'];
    
    // 2. Check if transaction exists in our database
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE reference = ?");
    $stmt->execute([$reference]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        throw new Exception('Transaction record not found in database.');
    }
    
    if ($transaction['status'] === 'success') {
        // Already processed
        if (isset($_GET['reference'])) {
            setFlash('success', 'Your vote has already been recorded.');
            ob_end_clean();
            redirect('events.php');
        }
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Already processed.']);
        exit;
    }
    
    // 3. Start database transaction to record vote and update status
    $pdo->beginTransaction();
    
    // Update transaction status
    $updateTxStmt = $pdo->prepare("
        UPDATE transactions 
        SET status = 'success', 
            payment_method = ?, 
            provider = ?, 
            updated_at = NOW() 
        WHERE id = ?
    ");
    $updateTxStmt->execute([
        $data['channel'],
        $data['authorization']['brand'] ?? $data['channel'],
        $transaction['id']
    ]);
    
    // Record the vote
    $insertVoteStmt = $pdo->prepare("
        INSERT INTO votes (user_id, nominee_id, category_id, event_id, transaction_id, vote_count, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $insertVoteStmt->execute([
        $transaction['user_id'],
        $transaction['nominee_id'],
        $transaction['category_id'],
        $transaction['event_id'],
        $transaction['id'],
        $transaction['quantity'],
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    $voteId = $pdo->lastInsertId();
    
    // Update nominee vote count
    $updateNomineeStmt = $pdo->prepare("
        UPDATE nominees SET vote_count = vote_count + ? WHERE id = ?
    ");
    $updateNomineeStmt->execute([$transaction['quantity'], $transaction['nominee_id']]);
    
    // Update event total votes
    $updateEventStmt = $pdo->prepare("
        UPDATE events SET total_votes = total_votes + ? WHERE id = ?
    ");
    $updateEventStmt->execute([$transaction['quantity'], $transaction['event_id']]);
    
    // Log the action
    $logStmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, user_agent)
        VALUES (?, 'paid_vote_cast', 'votes', ?, ?, ?)
    ");
    $logStmt->execute([
        $transaction['user_id'],
        $voteId,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    $pdo->commit();
    
    // 4. Send Confirmation SMS via Nalo Solutions
    // Get nominee, event, and category details for SMS
    $detailsStmt = $pdo->prepare("
        SELECT n.name as nominee_name, e.title as event_title, c.name as category_name
        FROM nominees n
        JOIN categories c ON n.category_id = c.id
        JOIN events e ON c.event_id = e.id
        WHERE n.id = ?
    ");
    $detailsStmt->execute([$transaction['nominee_id']]);
    $details = $detailsStmt->fetch();
    
    // Extract phone number from Paystack data
    $phoneNumber = null;
    $metadata = $data['metadata'] ?? [];
    
    // Decode metadata if it's a JSON string
    if (is_string($metadata)) {
        $metadata = json_decode($metadata, true);
    }
    
    if (isset($metadata['phone'])) {
        $phoneNumber = $metadata['phone'];
    } elseif (isset($metadata['msisdn'])) {
        $phoneNumber = $metadata['msisdn'];
    }
    
    // Fallback: Get from database transaction if still empty
    if (empty($phoneNumber) && !empty($transaction['metadata'])) {
        $dbMeta = is_string($transaction['metadata']) ? json_decode($transaction['metadata'], true) : $transaction['metadata'];
        $phoneNumber = $dbMeta['msisdn'] ?? $dbMeta['phone'] ?? null;
    }
    
    // Final fallbacks from Paystack customer/authorization data
    if (empty($phoneNumber)) {
        if (isset($data['customer']['phone']) && !empty($data['customer']['phone'])) {
            $phoneNumber = $data['customer']['phone'];
        } elseif (isset($data['authorization']['mobile_money_number'])) {
            $phoneNumber = $data['authorization']['mobile_money_number'];
        }
    }
    
    // Send SMS if we have a phone number
    if ($phoneNumber && $details) {
        $amount = $transaction['amount'];
        $currency = CURRENCY; // From paystack.php
        
        sendVoteConfirmationSMS(
            $phoneNumber,
            $amount,
            $currency,
            $details['nominee_name'],
            $details['event_title'],
            $details['category_name'],
            $transaction['quantity'],
            $reference
        );
    }
    
    // If it's a redirect callback
    if (isset($_GET['reference'])) {
        setFlash('success', 'Thank you! Your vote has been recorded successfully.');
        // Redirect to event page
        $eventStmt = $pdo->prepare("SELECT slug FROM events WHERE id = ?");
        $eventStmt->execute([$transaction['event_id']]);
        $eventSlug = $eventStmt->fetchColumn();
        ob_end_clean();
        redirect('event-details.php?slug=' . $eventSlug);
    }
    
    // If it's an AJAX call
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Vote recorded successfully!']);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    if (isset($_GET['reference'])) {
        setFlash('danger', 'Error: ' . $e->getMessage());
        ob_end_clean();
        redirect('events.php');
    }
    
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
