<?php
/**
 * Payment Verification Script
 * Handles NaloPay callback and records votes
 */

ob_start();
require_once 'config/database.php';
require_once 'config/nalo.php';

$pdo = getDBConnection();

// Get order_id from GET (redirect) or POST (webhook)
// Nalo uses order_id, which we mapped to our reference
$reference = $_GET['order_id'] ?? $_GET['reference'] ?? $_GET['id'] ?? $_GET['session_id'] ?? '';

// Log incoming request
error_log("Payment Callback Received: " . $_SERVER['REQUEST_METHOD'] . " - GET: " . json_encode($_GET) . " - POST: " . file_get_contents('php://input'));

$isWebhook = false;
$webhookStatus = '';
$webhookData = [];

// Check for POST data (Webhook)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isWebhook = true;
    $input = file_get_contents('php://input');
    $payload = json_decode($input, true);
    
    if (!empty($payload)) {
        $reference = $payload['order_id'] ?? $payload['reference'] ?? $payload['id'] ?? $payload['session_id'] ?? $reference;
        $webhookStatus = $payload['status'] ?? $payload['success'] ?? $payload['sucess'] ?? '';
        $webhookData = $payload;
    } else {
        $reference = $_POST['order_id'] ?? $_POST['reference'] ?? $_POST['id'] ?? $_POST['session_id'] ?? $reference;
        $webhookStatus = $_POST['status'] ?? $_POST['success'] ?? $_POST['sucess'] ?? '';
        $webhookData = $_POST;
    }
}

if (empty($reference)) {
    ob_clean();
    die(json_encode(['success' => false, 'message' => 'No reference/order_id provided.']));
}

try {
    // 1. Check if transaction exists in our database
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE reference = ? OR external_id = ?");
    $stmt->execute([$reference, $reference]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        throw new Exception('Transaction record not found in database.');
    }

    // Handle Redirect (GET)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $isTicket = !empty($transaction['ticket_category_id']);

        // If it's a redirect and transaction is already successful (from webhook), show success immediately
        if ($transaction['status'] === 'success') {
            if ($isTicket) {
                setFlash('success', 'Thank you! Your ticket purchase was successful. Your ticket code has been sent via SMS.');
                ob_end_clean();
                redirect('ticketing.php');
            } else {
                setFlash('success', 'Thank you! Your vote has been recorded successfully.');
                $eventStmt = $pdo->prepare("SELECT slug FROM events WHERE id = ?");
                $eventStmt->execute([$transaction['event_id']]);
                $eventSlug = $eventStmt->fetchColumn();
                ob_end_clean();
                redirect('event-details.php?slug=' . $eventSlug);
            }
        }
        
        // If not successful yet, we can try to verify with Nalo API 
        // Note: works for MoMo STK push but may fail for Nalo hosted checkout sessions
        try {
            $naloResponse = verifyNaloTransaction($reference);
            $isSuccess = $naloResponse['success'] ?? $naloResponse['sucess'] ?? false;
            if ($isSuccess) {
                $paymentStatus = $naloResponse['data']['status'] ?? '';
                if ($paymentStatus === 'COMPLETED' || $paymentStatus === 'SUCCESS') {
                    // It actually worked! Let's process it
                    $result = processSuccessfulPayment($pdo, $transaction, $naloResponse['data']);
                    if ($result['success']) {
                        if ($isTicket) {
                            setFlash('success', 'Thank you! Your ticket purchase was successful. Your ticket code has been sent via SMS.');
                            ob_end_clean();
                            redirect('ticketing.php');
                        } else {
                            setFlash('success', 'Thank you! Your vote has been recorded successfully.');
                            $eventStmt = $pdo->prepare("SELECT slug FROM events WHERE id = ?");
                            $eventStmt->execute([$transaction['event_id']]);
                            $eventSlug = $eventStmt->fetchColumn();
                            ob_end_clean();
                            redirect('event-details.php?slug=' . $eventSlug);
                        }
                    }
                }
            }
        } catch (Throwable $t) {
            // Ignore API error on GET since it might be a checkout session
        }
        
        // Still pending?
        if ($isTicket) {
            setFlash('warning', 'Your payment is still being processed. Please check your SMS or ticket analytics in a moment.');
            ob_end_clean();
            redirect('ticketing.php');
        } else {
            $eventStmt = $pdo->prepare("SELECT slug FROM events WHERE id = ?");
            $eventStmt->execute([$transaction['event_id']]);
            $eventSlug = $eventStmt->fetchColumn();
            
            setFlash('warning', 'Your payment is still being processed. Please refresh in a moment to see your vote.');
            ob_end_clean();
            redirect('event-details.php?slug=' . $eventSlug);
        }
    }

    // Handle Webhook (POST)
    $isSuccess = false;
    $paymentStatus = '';
    $data = [];

    // Nalo webhook status values can vary
    $isWebhookStatusSuccess = ($webhookStatus === 'SUCCESS' || $webhookStatus === 'COMPLETED' || $webhookStatus === '100' || $webhookStatus === 'success' || $webhookStatus === true || strtolower((string)$webhookStatus) === 'true');

    if ($isWebhook && $isWebhookStatusSuccess) {
        $isSuccess = true;
        $paymentStatus = 'SUCCESS';
        $data = $webhookData;
    } else {
        // Fallback to verifying with NaloPay directly if webhook isn't definitive
        $naloResponse = verifyNaloTransaction($reference);
        $isSuccess = $naloResponse['success'] ?? $naloResponse['sucess'] ?? false;
        
        if (!$isSuccess) {
            throw new Exception('Payment verification failed or connection error.');
        }
        
        $data = $naloResponse['data'] ?? [];
        $paymentStatus = $data['status'] ?? '';
    }
    
    // Check if status is one of the definitive success strings
    if ($paymentStatus !== 'COMPLETED' && $paymentStatus !== 'SUCCESS') {
        throw new Exception('Payment not successful. Status: ' . $paymentStatus);
    }
    
    // Real-time check: Block vote recording if event has expired (skip for tickets)
    $isTicket = !empty($transaction['ticket_category_id']);
    if (!$isTicket && isEventExpired($transaction['event_id'])) {
        // Mark transaction as failed
        $failStmt = $pdo->prepare("UPDATE transactions SET status = 'failed', updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $failStmt->execute([$transaction['id']]);
        throw new Exception('Event has ended. Voting is closed.');
    }
    
    // Process the successful payment
    $result = processSuccessfulPayment($pdo, $transaction, $data);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    // Respond to Webhook accurately
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Vote recorded successfully!']);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Payment Verification Error: " . $e->getMessage());
    
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
