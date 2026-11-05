<?php
/**
 * Nalo Solutions API Configuration
 * 
 * Configuration for Nalo Solutions USSD and SMS services
 */

require_once __DIR__ . '/env.php';

// Nalo SMS credentials (from .env)
define('NALO_SMS_KEY', env('NALO_SMS_KEY', ''));
define('NALO_SMS_SENDER_ID', env('NALO_SMS_SENDER_ID', 'VOTEHUBGH'));
define('NALO_SMS_URL', 'https://sms.nalosolutions.com/smsbackend/Resl_Nalo/send-message/');

// USSD Settings
define('NALO_USER_ID', env('NALO_USER_ID', ''));

// Nalo Pay credentials (from .env)
define('NALO_PAY_MERCHANT_ID', env('NALO_PAY_MERCHANT_ID', ''));
define('NALO_PAY_SECRET_KEY', env('NALO_PAY_SECRET_KEY', ''));
define('NALO_PAY_BASIC_AUTH', env('NALO_PAY_BASIC_AUTH', ''));
define('NALO_PAY_BASE_URL', 'https://api.nalopay.com');
define('NALO_PAY_TOKEN_URL', NALO_PAY_BASE_URL . '/clientapi/generate-payment-token/');
define('NALO_PAY_COLLECTION_URL', NALO_PAY_BASE_URL . '/clientapi/collection/');
define('NALO_PAY_CHECKOUT_URL', NALO_PAY_BASE_URL . '/checkout/session/');

/**
 * Generate Nalo Payment Token (JWT)
 */
function generateNaloPaymentToken() {
    $url = NALO_PAY_TOKEN_URL;
    
    $fields = [
        'merchant_id' => NALO_PAY_MERCHANT_ID
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: " . NALO_PAY_BASIC_AUTH,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set to false if having SSL issues on local server
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $result = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        error_log("Nalo Token CURL Error: " . $err);
        return null;
    }

    $response = json_decode($result, true);
    
    // Handle NaloPay's 'sucess' typo
    $isSuccess = $response['success'] ?? $response['sucess'] ?? false;
    
    if ($httpCode !== 200 || !$isSuccess || !isset($response['data']['token'])) {
        error_log("Nalo Token API Error: HTTP $httpCode - Response: " . $result);
        return null;
    }

    return $response['data']['token'];
}

/**
 * Create Nalo Hosted Checkout Session
 */
function createNaloCheckoutSession($orderId, $customerName, $amount, $reference, $products = []) {
    $token = generateNaloPaymentToken();
    if (!$token) {
        return ['success' => false, 'message' => 'Failed to generate payment token.'];
    }

    $url = NALO_PAY_CHECKOUT_URL;
    
    // Generate Hash: merchant_id + order_id + total_price + reference
    $hashString = NALO_PAY_MERCHANT_ID . $orderId . number_format($amount, 2, '.', '') . $reference;
    $transHash = hash_hmac('sha256', $hashString, NALO_PAY_SECRET_KEY);

    $fields = [
        'merchant' => [
            'merchant_id' => NALO_PAY_MERCHANT_ID,
            'order_id' => $orderId,
            'customer_name' => $customerName,
            'referral_url' => SITE_URL,
            'callback_url' => SITE_URL . '/verify-payment.php',
            'trans_hash' => $transHash,
            'reference' => $reference,
            'mode' => 'ANY'
        ],
        'summary' => [
            'products' => $products,
            'item_count' => count($products),
            'total_price' => number_format($amount, 2, '.', '')
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "token: " . $token,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'message' => "Connection Error: " . $err];
    }

    return json_decode($result, true);
}

/**
 * Charge Mobile Money via NaloPay (Direct Collection / STK Push)
 */
function chargeNaloMoMo($phoneNumber, $amount, $network, $reference, $metadata = []) {
    $token = generateNaloPaymentToken();
    if (!$token) {
        return ['success' => false, 'message' => 'Failed to generate payment token.'];
    }

    $url = NALO_PAY_COLLECTION_URL;
    
    // Normalize network to Nalo format: MTN, AT, TELECEL
    $network = strtoupper($network);
    if ($network === 'VOD' || $network === 'VODAFONE') $network = 'TELECEL';
    if ($network === 'TGO' || $network === 'AIRTELTIGO') $network = 'AT';
    
    // Normalize phone: Standardize to 233 format for NaloPay API
    $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
    if (strlen($phone) == 10 && strpos($phone, '0') === 0) {
        $phone = '233' . substr($phone, 1);
    } elseif (strlen($phone) == 9) {
        $phone = '233' . $phone;
    }
    
    // Generate Hash: merchant_id + account_number + amount + reference
    $formattedAmount = number_format($amount, 2, '.', '');
    $hashString = NALO_PAY_MERCHANT_ID . $phone . $formattedAmount . $reference;
    $transHash = hash_hmac('sha256', $hashString, NALO_PAY_SECRET_KEY);

    $fields = [
        'merchant_id' => NALO_PAY_MERCHANT_ID,
        'service_name' => 'MOMO_TRANSACTION', 
        'account_number' => $phone,
        'amount' => (float)$formattedAmount, // Send as float
        'network' => $network,
        'reference' => $reference,
        'callback' => SITE_URL . '/verify-payment.php',
        'trans_hash' => $transHash,
        'description' => 'Award Voting',
        'account_name' => 'Voter',
        'extra_data' => $metadata
    ];

    // Log request for debugging (remove in production)
    error_log("NaloPay Collection Request: " . json_encode($fields));
    error_log("NaloPay Hash String: " . $hashString);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "token: " . $token,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("NaloPay Collection CURL Error: " . $err);
        return ['success' => false, 'message' => "Connection Error: " . $err];
    }

    error_log("NaloPay Collection Raw Response: " . $result);
    $response = json_decode($result, true);
    
    if ($response === null) {
        return ['success' => false, 'message' => "Invalid response from payment gateway.", 'raw' => $result];
    }

    return $response;
}

/**
 * Verify Nalo Transaction Status
 * Can be verified using Nalo's order_id or our reference
 */
function verifyNaloTransaction($id) {
    $token = generateNaloPaymentToken();
    if (!$token) {
        return ['success' => false, 'message' => 'Failed to generate payment token for verification.'];
    }

    $url = NALO_PAY_BASE_URL . "/clientapi/collection-status/";
    
    $fields = [
        'merchant_id' => NALO_PAY_MERCHANT_ID,
        'order_id' => $id // Nalo accepts their order_id or our reference here usually
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "token: " . $token,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

/**
 * Process a successful payment: record votes OR generate tickets, send SMS, and notify
 */
function processSuccessfulPayment($pdo, $transaction, $naloData) {
    if ($transaction['status'] === 'success') {
        return ['success' => true, 'message' => 'Already processed.'];
    }

    $isTicketPurchase = !empty($transaction['ticket_category_id']);

    // Real-time check: Verify event hasn't expired before recording vote (skip for tickets)
    if (!$isTicketPurchase && isEventExpired($transaction['event_id'])) {
        $failStmt = $pdo->prepare("UPDATE transactions SET status = 'failed', updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $failStmt->execute([$transaction['id']]);
        error_log("processSuccessfulPayment: Vote blocked for expired event #{$transaction['event_id']}, transaction #{$transaction['id']}");
        return ['success' => false, 'message' => 'This event has ended. Voting is no longer available.'];
    }

    try {
        $pdo->beginTransaction();

        // Idempotency check
        if ($isTicketPurchase) {
            $checkStmt = $pdo->prepare("SELECT id FROM tickets WHERE transaction_id = ?");
            $checkStmt->execute([$transaction['id']]);
            if ($checkStmt->fetch()) {
                $pdo->rollBack();
                return ['success' => true, 'message' => 'Ticket already generated for this transaction.'];
            }
        } else {
            $checkVoteStmt = $pdo->prepare("SELECT id FROM votes WHERE transaction_id = ?");
            $checkVoteStmt->execute([$transaction['id']]);
            if ($checkVoteStmt->fetch()) {
                $pdo->rollBack();
                return ['success' => true, 'message' => 'Vote already recorded for this transaction.'];
            }
        }

        // Update transaction status - ONLY if it's still pending
        $updateTxStmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'success', 
                payment_method = ?, 
                provider = ?, 
                external_id = ?,
                updated_at = NOW() 
            WHERE id = ? AND status = 'pending'
        ");

        $method = (isset($naloData['payment_type']) && stripos($naloData['payment_type'], 'card') !== false) ? 'card' : 'momo';
        $externalId = $naloData['order_id'] ?? $transaction['external_id'] ?? null;

        $updateTxStmt->execute([
            $method,
            $naloData['network'] ?? 'NaloPay',
            $externalId,
            $transaction['id']
        ]);

        if ($updateTxStmt->rowCount() === 0) {
            $stmt = $pdo->prepare("SELECT status FROM transactions WHERE id = ?");
            $stmt->execute([$transaction['id']]);
            $currentStatus = $stmt->fetchColumn();
            
            if ($currentStatus === 'success') {
                $pdo->rollBack();
                return ['success' => true, 'message' => 'Transaction already processed.'];
            }
            throw new Exception("Transaction cannot be processed. Current status: $currentStatus");
        }

        $metadata = is_string($transaction['metadata']) ? json_decode($transaction['metadata'], true) : $transaction['metadata'];
        $phoneNumber = $metadata['phone'] ?? $metadata['msisdn'] ?? null;

        if ($isTicketPurchase) {
            // --- TICKET PURCHASE FLOW ---
            $ticketCode = 'TKT-' . strtoupper(bin2hex(random_bytes(5)));

            $insertTicketStmt = $pdo->prepare("
                INSERT INTO tickets (transaction_id, event_id, ticket_category_id, ticket_code, quantity, phone)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertTicketStmt->execute([
                $transaction['id'],
                $transaction['event_id'],
                $transaction['ticket_category_id'],
                $ticketCode,
                $transaction['quantity'],
                $phoneNumber
            ]);

            $ticketId = $pdo->lastInsertId();

            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, user_agent)
                VALUES (?, 'ticket_purchase', 'tickets', ?, ?, ?)
            ");
            $logStmt->execute([
                $transaction['user_id'],
                $ticketId,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);

            $pdo->commit();

            // Send ticket confirmation SMS
            try {
                $detailsStmt = $pdo->prepare("
                    SELECT e.title as event_title, e.start_date as event_date, 
                           tc.name as category_name, u.fullname as buyer_name
                    FROM events e
                    JOIN ticket_categories tc ON tc.event_id = e.id
                    LEFT JOIN users u ON u.id = ?
                    WHERE e.id = ? AND tc.id = ?
                ");
                $detailsStmt->execute([$transaction['user_id'], $transaction['event_id'], $transaction['ticket_category_id']]);
                $details = $detailsStmt->fetch();

                if ($phoneNumber && $details) {
                    sendTicketConfirmationSMS(
                        $phoneNumber,
                        $transaction['amount'],
                        'GHS',
                        $details['event_title'],
                        $details['category_name'],
                        $ticketCode,
                        $transaction['quantity'],
                        $transaction['reference'],
                        $details['buyer_name'] ?? '',
                        $details['event_date'] ?? ''
                    );
                }
            } catch (Exception $e) {
                error_log("SMS Error in processSuccessfulPayment (ticket): " . $e->getMessage());
            }

            // Notifications for ticket sale
            try {
                $eventStmt = $pdo->prepare("SELECT organizer_id, title FROM events WHERE id = ?");
                $eventStmt->execute([$transaction['event_id']]);
                $event = $eventStmt->fetch();

                if ($event) {
                    $catName = $details['category_name'] ?? 'Ticket';
                    if ($event['organizer_id']) {
                        createNotification(
                            $event['organizer_id'],
                            "New Ticket Sale",
                            $transaction['quantity'] . "x $catName ticket(s) sold for: " . $event['title'],
                            "success",
                            "organizer/events/"
                        );
                    }
                    createNotification(1, "New Ticket Sale", $transaction['quantity'] . "x $catName ticket(s) sold for " . $event['title'], "info", "admin/events/");
                }
            } catch (Exception $e) {
                error_log("Notification Error in processSuccessfulPayment (ticket): " . $e->getMessage());
            }

        } else {
            // --- VOTING FLOW (original logic) ---
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
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);

            $voteId = $pdo->lastInsertId();

            // Update nominee vote count
            $updateNomineeStmt = $pdo->prepare("UPDATE nominees SET vote_count = vote_count + ? WHERE id = ?");
            $updateNomineeStmt->execute([$transaction['quantity'], $transaction['nominee_id']]);

            // Update event total votes
            $updateEventStmt = $pdo->prepare("UPDATE events SET total_votes = total_votes + ? WHERE id = ?");
            $updateEventStmt->execute([$transaction['quantity'], $transaction['event_id']]);

            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, user_agent)
                VALUES (?, 'paid_vote_cast', 'votes', ?, ?, ?)
            ");
            $logStmt->execute([
                $transaction['user_id'],
                $voteId,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);

            $pdo->commit();

            // Send Vote Confirmation SMS
            try {
                $detailsStmt = $pdo->prepare("
                    SELECT n.name as nominee_name, e.title as event_title, c.name as category_name
                    FROM nominees n
                    JOIN categories c ON n.category_id = c.id
                    JOIN events e ON c.event_id = e.id
                    WHERE n.id = ?
                ");
                $detailsStmt->execute([$transaction['nominee_id']]);
                $details = $detailsStmt->fetch();

                if ($phoneNumber && $details) {
                    sendVoteConfirmationSMS(
                        $phoneNumber,
                        $transaction['amount'],
                        'GHS',
                        $details['nominee_name'],
                        $details['event_title'],
                        $details['category_name'],
                        $transaction['quantity'],
                        $transaction['reference']
                    );
                }
            } catch (Exception $e) {
                error_log("SMS Error in processSuccessfulPayment: " . $e->getMessage());
            }

            // Create Notifications for votes
            try {
                $eventStmt = $pdo->prepare("SELECT created_by, title FROM events WHERE id = ?");
                $eventStmt->execute([$transaction['event_id']]);
                $event = $eventStmt->fetch();

                if ($event) {
                    if ($event['created_by']) {
                        createNotification(
                            $event['created_by'],
                            "New Vote Recorded",
                            "A new vote has been cast for " . ($details['nominee_name'] ?? 'Nominee') . " in your event: " . $event['title'],
                            "success",
                            "organizer/event-details.php?id=" . $transaction['event_id']
                        );
                    }
                    createNotification(1, "New Vote Recorded", "A new vote has been cast for " . ($details['nominee_name'] ?? 'Nominee') . " in " . $event['title'], "info", "admin/votes.php");
                }
            } catch (Exception $e) {
                error_log("Notification Error in processSuccessfulPayment: " . $e->getMessage());
            }
        }

        return ['success' => true, 'message' => 'Payment processed successfully.'];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Process Payment Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send SMS via Nalo Solutions
 * 
 * Uses Nalo SMS API v1 with proper authentication
 * Reference: https://documenter.getpostman.com/view/7705958/Uyr7Hydn
 */
function sendNaloSMS($to, $message) {
    if (empty(NALO_SMS_KEY)) {
        error_log("Nalo SMS Error: API Key not set.");
        return ['status' => 'error', 'message' => 'API Key not set'];
    }

    // Log warning on localhost if needed, but still execute the API call
    $is_localhost = in_array($_SERVER['REMOTE_ADDR'] ?? '::1', ['127.0.0.1', 'localhost', '::1']);
    if ($is_localhost) {
        error_log("Nalo SMS Notice: Running on localhost. Initiating API call for testing.");
    }

    // Format phone number for Nalo API
    $formattedPhone = formatPhoneNumberForNalo($to);

    // Prepare form-encoded request (most compatible with Nalo API)
    $postData = http_build_query([
        'key' => NALO_SMS_KEY,
        'msisdn' => $formattedPhone,
        'message' => $message,
        'sender_id' => NALO_SMS_SENDER_ID
    ]);

    $ch = curl_init(NALO_SMS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);
    
    if ($err) {
        error_log("Nalo SMS CURL Error: " . $err);
        return ['status' => 'error', 'message' => $err];
    }

    // Log the full response for debugging
    error_log("Nalo SMS Request: key=" . substr(NALO_SMS_KEY, 0, 10) . "... to=$formattedPhone message_len=" . strlen($message));
    error_log("Nalo SMS Response (HTTP $httpCode): " . (strlen($response) > 500 ? substr($response, 0, 500) . '...' : $response));

    // Try to parse as JSON
    $result = json_decode($response, true);
    
    // Determine success based on HTTP code and response content
    if ($httpCode === 200 || $httpCode === 201 || $httpCode === 202) {
        // Success HTTP codes - typically means the request was accepted
        if ($result === null) {
            // Non-JSON success response
            $result = ['status' => 'success', 'response' => $response];
        } else {
            // JSON response with success code
            $result['status'] = 'success';
        }
        return $result + ['http_code' => $httpCode];
    } 
    
    // Check for HTTP errors
    if ($httpCode >= 400) {
        $errorMsg = "HTTP $httpCode: ";
        if ($result) {
            $errorMsg .= $result['message'] ?? $result['error'] ?? json_encode($result);
        } else {
            $errorMsg .= $response;
        }
        error_log("Nalo SMS API HTTP Error: " . $errorMsg);
        return ['status' => 'error', 'message' => $errorMsg, 'http_code' => $httpCode];
    }
    
    // Handle non-JSON responses (might be plain text codes)
    if ($result === null) {
        $trimmedResponse = trim($response);
        
        // Nalo API might return success codes as simple strings/numbers
        if (in_array($trimmedResponse, ['100', '1', 'success', 'SUCCESS', 'Ok', 'OK', ''])) {
            return ['status' => 'success', 'response' => $trimmedResponse, 'http_code' => $httpCode];
        }
        
        // If it's a number, it might be an error code
        if (is_numeric($trimmedResponse)) {
            return ['status' => 'error', 'message' => 'API returned code: ' . $trimmedResponse, 'http_code' => $httpCode];
        }
        
        error_log("Nalo SMS Unexpected non-JSON Response: " . $response);
        return ['status' => 'error', 'message' => 'Unexpected response: ' . $trimmedResponse, 'http_code' => $httpCode];
    }
    
    // JSON response - check various possible success fields
    $successStatus = $result['status'] ?? $result['success'] ?? $result['code'] ?? $result['state'] ?? null;
    
    // Nalo might use different field names for success
    if ($successStatus == '1' || $successStatus == '100' || $successStatus === 'success' || $successStatus === 'SUCCESS' || $successStatus === true || $successStatus === 'Ok') {
        $result['status'] = 'success';
        return $result + ['http_code' => $httpCode];
    }
    
    // Not a success status
    $result['status'] = 'error';
    return $result + ['http_code' => $httpCode];
}

/**
 * Send Vote Confirmation SMS via Nalo
 */
function sendVoteConfirmationSMS($phoneNumber, $amount, $currency, $nomineeName, $eventTitle, $categoryName = '', $voteCount = 1, $reference = '') {
    // Format the phone number
    $formattedPhone = formatPhoneNumberForNalo($phoneNumber);
    
    // Build the confirmation message
    $message = "VoteHub Payment Confirmed!\n";
    $message .= "Amount: $currency " . number_format($amount, 2) . "\n";
    $message .= "Votes: $voteCount for $nomineeName\n";
    $message .= "Event: $eventTitle\n";
    if ($categoryName) {
        $message .= "Category: $categoryName\n";
    }
    $message .= "Thank you for voting!\n";
    $message .= "Visit: votehubgh.com";
    
    return sendNaloSMS($formattedPhone, $message);
}

function sendTicketConfirmationSMS($phoneNumber, $amount, $currency, $eventTitle, $categoryName, $ticketCode, $quantity = 1, $reference = '', $buyerName = '', $eventDate = '') {
    $formattedPhone = formatPhoneNumberForNalo($phoneNumber);
    
    $eventDateFormatted = !empty($eventDate) ? date('M d, Y', strtotime($eventDate)) : 'Scheduled Date';
    
    $message = "VoteHub Ticket Confirmed!\n\n";
    $message .= "Event: $eventTitle\n";
    $message .= "Date: $eventDateFormatted\n";
    $message .= "Category: $categoryName\n";
    $message .= "Ticket Code: $ticketCode\n";
    $message .= "Qty: $quantity\n";
    $message .= "Amount: $currency " . number_format($amount, 2) . "\n";
    if ($reference) {
        $message .= "Ref: $reference\n";
    }
    $message .= "\nInstructions: Present this SMS or ticket code at the event entrance for verification. Do not share it.\n\n";
    $message .= "Thank you for using VoteHubGH!";
    
    return sendNaloSMS($formattedPhone, $message);
}

/**
 * Format phone number for Nalo
 */
function formatPhoneNumberForNalo($phone) {
    // Remove any spaces, dashes, or special characters
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Remove leading + if present
    $phone = ltrim($phone, '+');
    
    // Ghana numbers
    if (strlen($phone) == 10 && strpos($phone, '0') === 0) {
        // 0XXXXXXXXX format -> 233XXXXXXXXX
        return '233' . substr($phone, 1);
    }
    
    if (strlen($phone) == 9) {
        // XXXXXXXXX format -> 233XXXXXXXXX
        return '233' . $phone;
    }
    
    if (strlen($phone) == 12 && strpos($phone, '233') === 0) {
        // Already in 233XXXXXXXXX format
        return $phone;
    }
    
    // Return as-is if it doesn't match expected patterns
    // Nalo API might accept various formats
    return $phone;
}
