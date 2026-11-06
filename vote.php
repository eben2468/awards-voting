<?php
/**
 * Vote Processing Script
 * Awards Voting System
 */

ob_start(); // Buffer output to prevent accidental whitespace/errors from breaking JSON
require_once 'config/database.php';
require_once 'config/nalo.php';

$pdo = getDBConnection();

// Handle shortcode search (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['shortcode'])) {
    $shortcode = sanitize($_GET['shortcode']);
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if (!empty($shortcode)) {
        $stmt = $pdo->prepare("
            SELECT n.*, c.name as category_name, c.id as category_id, e.title as event_title, e.slug as event_slug, e.id as event_id, e.voting_type, e.vote_price
            FROM nominees n
            JOIN categories c ON n.category_id = c.id
            JOIN events e ON c.event_id = e.id
            WHERE n.shortcode = ? AND n.status = 'active' AND e.status = 'active'
              AND e.start_date <= NOW() AND e.end_date >= NOW()
        ");
        $stmt->execute([$shortcode]);
        $nominee = $stmt->fetch();
        
        if ($nominee) {
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'nominee' => [
                        'id' => $nominee['id'],
                        'name' => $nominee['name'],
                        'photo' => $nominee['photo'] ? 'uploads/nominees/' . $nominee['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($nominee['name']) . '&background=0F2C7A&color=fff&size=150',
                        'category_id' => $nominee['category_id'],
                        'event_id' => $nominee['event_id'],
                        'voting_type' => $nominee['voting_type'],
                        'vote_price' => $nominee['vote_price']
                    ]
                ]);
                exit;
            }
            ob_end_clean();
            redirect('event-details.php?slug=' . $nominee['event_slug'] . '#category-' . $nominee['category_id']);
        } else {
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No nominee found with shortcode: ' . $shortcode]);
                exit;
            }
            setFlash('warning', 'No nominee found with shortcode: ' . $shortcode);
            ob_end_clean();
            redirect('events.php');
        }
    }
    if ($isAjax) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Shortcode is required.']);
        exit;
    }
    ob_end_clean();
    redirect('events.php');
}

// Handle vote submission (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Get data
        $nomineeId = intval($_POST['nominee_id'] ?? 0);
        $categoryId = intval($_POST['category_id'] ?? 0);
        $eventId = intval($_POST['event_id'] ?? 0);
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $csrfToken = $_POST['csrf_token'] ?? '';
        $voterPhone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? ''); // Clean phone number
        $voterNetwork = $_POST['network'] ?? ''; // Get network for direct push
        
        // Verify CSRF token
        if (!verifyCSRFToken($csrfToken)) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }
        
        // Validate input
        if ($nomineeId <= 0 || $categoryId <= 0 || $eventId <= 0) {
            throw new Exception('Invalid vote data.');
        }
        
        // Verify event is active and get voting type/price
        $eventStmt = $pdo->prepare("
            SELECT * FROM events 
            WHERE id = ? AND status = 'active' AND start_date <= NOW() AND end_date >= NOW()
        ");
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch();
        
        if (!$event) {
            throw new Exception('This event is not currently accepting votes.');
        }
        
        // Additional real-time expiry check (defense in depth)
        if (isEventExpired($eventId)) {
            throw new Exception('This event has ended. Voting is no longer available.');
        }
        
        // Verify nominee exists and belongs to category
        $nomineeStmt = $pdo->prepare("
            SELECT n.*, c.event_id 
            FROM nominees n
            JOIN categories c ON n.category_id = c.id
            WHERE n.id = ? AND n.category_id = ? AND c.event_id = ? AND n.status = 'active'
        ");
        $nomineeStmt->execute([$nomineeId, $categoryId, $eventId]);
        $nominee = $nomineeStmt->fetch();
        
        if (!$nominee) {
            throw new Exception('Invalid nominee selection.');
        }

        // Check if user is logged in
        $userId = getCurrentUserId();
        $email = $_SESSION['user_email'] ?? 'guest@gmail.com';
        
        // If event is paid, initialize payment
        if ($event['voting_type'] === 'paid') {
            $unitPrice = $event['vote_price'] > 0 ? $event['vote_price'] : (defined('VOTE_PRICE') ? VOTE_PRICE : 1.00);
            $amount = $unitPrice * $quantity;
            $reference = 'VOTE-' . strtoupper(bin2hex(random_bytes(8)));
            
            // Format phone number for metadata (add country code if needed)
            $formattedPhone = '';
            if (!empty($voterPhone)) {
                $voterPhone = normalizeGhanaPhone($voterPhone);
                if ($voterPhone === null) {
                    throw new Exception('Enter a valid phone number, such as 024 123 4567.');
                }
                $formattedPhone = '+233' . $voterPhone;
            }
            
            $metadata = json_encode([
                'nominee_id' => $nomineeId,
                'category_id' => $categoryId,
                'event_id' => $eventId,
                'nominee_name' => $nominee['name'],
                'quantity' => $quantity,
                'phone' => $formattedPhone // Phone for SMS confirmation
            ]);

            // Create pending transaction with phone number
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, event_id, nominee_id, category_id, phone, amount, quantity, reference, status, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            
            $stmt->execute([
                $userId,
                $eventId,
                $nomineeId,
                $categoryId,
                $formattedPhone ?: null,
                $amount,
                $quantity,
                $reference,
                $metadata
            ]);
            
            // Initialize NaloPay Hosted Checkout
            require_once 'config/nalo.php';
            
            $products = [
                [
                    'name' => $nominee['name'] . ' - ' . $quantity . ' Votes',
                    'count' => 1,
                    'price' => number_format($amount, 2, '.', '')
                ]
            ];
            
            // If phone and network are provided AND network is not CARD, try direct STK Push
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
                        'approval_code' => naloApprovalCode($naloResponse),
                        'message' => 'Check your phone and approve the payment to complete your vote.'
                    ];
                    $response['message'] = 'STK Push initiated...';
                    
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
                // If direct push fails, we can either throw an error or fall back to hosted checkout
                // Let's log it and try hosted checkout as a fallback
                error_log("NaloPay Direct Push failed for $reference: " . naloErrorMessage($naloResponse));
            }

            // Card payments, and any MoMo push that could not start, use NaloPay's own
            // secure checkout. Card details never touch this site.
            $customerName = $_SESSION['user_name'] ?? 'Voter';
            $naloResponse = createNaloCheckoutSession(
                $reference, // Using reference as orderId
                $customerName,
                $amount,
                $reference,
                $products
            );
            
            $isSuccess = $naloResponse['success'] ?? $naloResponse['sucess'] ?? false;
            
            if ($naloResponse && $isSuccess) {
                // Store NaloPay's session ID or order ID as external_id
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
                // Nothing was charged, so this must not sit in the pending list
                $pdo->prepare("UPDATE transactions SET status = 'failed' WHERE reference = ?")->execute([$reference]);
                throw new Exception('Your payment could not start: ' . naloErrorMessage($naloResponse));
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        // Start transaction for free voting
        $pdo->beginTransaction();
        
        // Record the vote
        $insertVoteStmt = $pdo->prepare("
            INSERT INTO votes (user_id, nominee_id, category_id, event_id, vote_count, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $insertVoteStmt->execute([
            $userId,
            $nomineeId,
            $categoryId,
            $eventId,
            $quantity,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        // Update nominee vote count
        $updateNomineeStmt = $pdo->prepare("
            UPDATE nominees SET vote_count = vote_count + ? WHERE id = ?
        ");
        $updateNomineeStmt->execute([$quantity, $nomineeId]);
        
        // Update event total votes
        $updateEventStmt = $pdo->prepare("
            UPDATE events SET total_votes = total_votes + ? WHERE id = ?
        ");
        $updateEventStmt->execute([$quantity, $eventId]);
        
        // Log the action
        $logStmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, user_agent)
            VALUES (?, 'vote_cast', 'votes', ?, ?, ?)
        ");
        $logStmt->execute([
            $userId,
            $pdo->lastInsertId(),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = 'Your vote has been recorded successfully!';
        
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

ob_end_clean();
redirect('events.php');
