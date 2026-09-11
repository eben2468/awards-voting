<?php
/**
 * Ticket Purchase Processing Script
 * Awards Voting System
 * 
 * Handles ticket purchase requests (similar to vote.php for voting).
 */

ob_start();
require_once 'config/database.php';
require_once 'config/nalo.php';

$pdo = getDBConnection();

// Handle ticket purchase (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Get data
        $eventId = intval($_POST['event_id'] ?? 0);
        $ticketCategoryId = intval($_POST['ticket_category_id'] ?? 0);
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $csrfToken = $_POST['csrf_token'] ?? '';
        $voterPhone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
        $voterNetwork = $_POST['network'] ?? '';
        
        // Verify CSRF token
        if (!verifyCSRFToken($csrfToken)) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }
        
        // Validate input
        if ($eventId <= 0 || $ticketCategoryId <= 0) {
            throw new Exception('Invalid ticket data.');
        }
        
        // Verify event is active and has ticketing enabled
        $eventStmt = $pdo->prepare("
            SELECT * FROM events 
            WHERE id = ? AND status = 'active' AND event_type IN ('ticketing', 'both')
              AND start_date <= NOW() AND end_date >= NOW()
        ");
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch();
        
        if (!$event) {
            throw new Exception('This event is not currently selling tickets.');
        }
        
        // Verify ticket category exists and belongs to the event
        $catStmt = $pdo->prepare("
            SELECT * FROM ticket_categories 
            WHERE id = ? AND event_id = ?
        ");
        $catStmt->execute([$ticketCategoryId, $eventId]);
        $category = $catStmt->fetch();
        
        if (!$category) {
            throw new Exception('Invalid ticket category.');
        }
        
        // Calculate total
        $unitPrice = floatval($category['price']);
        $amount = $unitPrice * $quantity;
        $reference = 'TKT-' . strtoupper(bin2hex(random_bytes(8)));
        
        // Format phone number
        $formattedPhone = '';
        if (!empty($voterPhone)) {
            $voterPhone = ltrim($voterPhone, '0');
            $formattedPhone = '+233' . $voterPhone;
        }
        
        $metadata = json_encode([
            'ticket_category_id' => $ticketCategoryId,
            'event_id' => $eventId,
            'quantity' => $quantity,
            'phone' => $formattedPhone,
            'category_name' => $category['name'],
            'event_title' => $event['title']
        ]);
        
        // Check if user is logged in
        $userId = getCurrentUserId();
        
        // Create pending transaction with ticket_category_id
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, event_id, ticket_category_id, phone, amount, quantity, reference, status, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ");
        
        $stmt->execute([
            $userId,
            $eventId,
            $ticketCategoryId,
            $formattedPhone ?: null,
            $amount,
            $quantity,
            $reference,
            $metadata
        ]);
        
        // If network is CARD, process payment directly using simulated success
        if ($voterNetwork === 'CARD') {
            $cardholderName = trim($_POST['cardholder_name'] ?? '');
            $cardNumber = str_replace(' ', '', $_POST['card_number'] ?? '');
            $expiryMonth = $_POST['expiry_month'] ?? '';
            $expiryYear = $_POST['expiry_year'] ?? '';
            $cvv = $_POST['cvv'] ?? '';
            
            // Validate card fields
            if (empty($cardholderName)) {
                throw new Exception('Cardholder Name is required.');
            }
            if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19 || !ctype_digit($cardNumber)) {
                throw new Exception('Invalid Card Number.');
            }
            if (empty($expiryMonth) || empty($expiryYear)) {
                throw new Exception('Card expiry date is required.');
            }
            if (strlen($cvv) < 3 || strlen($cvv) > 4 || !ctype_digit($cvv)) {
                throw new Exception('Invalid CVV.');
            }
            
            // Retrieve the newly inserted transaction
            $txId = $pdo->lastInsertId();
            $txStmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
            $txStmt->execute([$txId]);
            $transaction = $txStmt->fetch();
            
            if (!$transaction) {
                throw new Exception('Failed to record transaction.');
            }
            
            // Simulate NaloPay details
            $naloData = [
                'status' => 'COMPLETED',
                'order_id' => $reference,
                'payment_type' => 'card',
                'network' => 'CARD'
            ];
            
            // Process successful payment
            $result = processSuccessfulPayment($pdo, $transaction, $naloData);
            
            if ($result['success']) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'payment_required' => false,
                    'payment_data' => [
                        'reference' => $reference
                    ],
                    'message' => 'Card payment processed successfully! Your ticket purchase has been confirmed.'
                ]);
                exit;
            } else {
                throw new Exception($result['message']);
            }
        }
        
        // Initialize payment
        $products = [
            [
                'name' => $event['title'] . ' - ' . $category['name'] . ' x' . $quantity,
                'count' => 1,
                'price' => number_format($amount, 2, '.', '')
            ]
        ];
        
        $customerName = $_SESSION['user_name'] ?? 'Guest';
        
        // If phone and network provided AND not CARD, try direct STK Push
        if (!empty($voterPhone) && !empty($voterNetwork) && $voterNetwork !== 'CARD') {
            $naloResponse = chargeNaloMoMo(
                $voterPhone,
                $amount,
                $voterNetwork,
                $reference,
                json_decode($metadata, true)
            );
            
            $isSuccess = $naloResponse['success'] ?? $naloResponse['sucess'] ?? false;
            
            if ($naloResponse && $isSuccess) {
                // Store NaloPay's order_id as external_id
                $externalId = $naloResponse['data']['order_id'] ?? null;
                if ($externalId) {
                    $updateStmt = $pdo->prepare("UPDATE transactions SET external_id = ? WHERE reference = ?");
                    $updateStmt->execute([$externalId, $reference]);
                }

                $response['success'] = true;
                $response['payment_required'] = true;
                $response['payment_data'] = [
                    'direct_push' => true,
                    'reference' => $reference,
                    'message' => 'An STK Push has been sent to your phone. Please approve to complete your ticket purchase.'
                ];
                $response['message'] = 'STK Push initiated...';
                
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
            // If direct push fails, fall back to hosted checkout
            error_log("NaloPay Direct Push failed for ticket $reference: " . ($naloResponse['message'] ?? 'Unknown error'));
        }

        // Fallback to Hosted Checkout
        $naloResponse = createNaloCheckoutSession(
            $reference,
            $customerName,
            $amount,
            $reference,
            $products
        );
        
        $isSuccess = $naloResponse['success'] ?? $naloResponse['sucess'] ?? false;
        
        if ($naloResponse && $isSuccess) {
            $externalId = $naloResponse['data']['session_id'] ?? $naloResponse['data']['order_id'] ?? null;
            if ($externalId) {
                $updateStmt = $pdo->prepare("UPDATE transactions SET external_id = ? WHERE reference = ?");
                $updateStmt->execute([$externalId, $reference]);
            }

            $response['success'] = true;
            $response['payment_required'] = true;
            $response['payment_data'] = [
                'checkout_url' => $naloResponse['data']['checkout_url'],
                'reference' => $reference
            ];
            $response['message'] = 'Redirecting to payment...';
        } else {
            $errorMsg = $naloResponse['message'] ?? 'Unknown error';
            if (isset($naloResponse['errors'])) {
                $errorMsg .= ': ' . json_encode($naloResponse['errors']);
            }
            throw new Exception('Failed to initialize payment: ' . $errorMsg);
        }
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
        
    } catch (Throwable $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

ob_end_clean();
redirect('ticketing.php');
