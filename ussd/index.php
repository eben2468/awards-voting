<?php
/**
 * VoteHubGH USSD Integration - Nalo Solutions
 * 
 * This script handles USSD requests from Nalo Solutions USSD Gateway.
 * It uses PHP sessions to maintain state between requests.
 */

// --- 1. Error Handling & Performance ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/ussd_error.log');
set_time_limit(10); 

// --- 2. Input Handling ---
$request = file_get_contents("php://input");
$data = json_decode($request, true);

if (!$data) {
    header('Content-type: application/json');
    echo json_encode(["MSG" => "Invalid Request", "MSGTYPE" => false]);
    exit;
}

$ussd_id = $data['USERID'] ?? '';
$msisdn = $data['MSISDN'] ?? '';
$user_data = trim($data['USERDATA'] ?? '');
$msgtype = $data['MSGTYPE'] ?? true; // true = initial dial

// --- 3. Session Management ---
// Use MSISDN to identify the session - MUST BE BEFORE session_start and any output
session_id(md5($msisdn));
session_start();

// Debug log
error_log("USSD Request: MSISDN=$msisdn, USERDATA=$user_data, MSGTYPE=" . ($msgtype ? 'true' : 'false') . ", CurrentStep=" . ($_SESSION['step'] ?? 'none'));

// --- 4. Database & Config ---
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/nalo.php';

/**
 * Send USSD Response and Exit
 */
function sendNaloResponse($ussd_id, $msisdn, $user_data, $msg, $continue = true) {
    $msg = plainText($msg);
    header('Content-type: application/json');
    $resp = [
        "USERID" => $ussd_id,
        "MSISDN" => $msisdn,
        "USERDATA" => $user_data,
        "MSG" => $msg,
        "MSGTYPE" => $continue
    ];
    echo json_encode($resp);
    if (!$continue) {
        session_destroy();
    } else {
        session_write_close(); // Ensure session is saved before exit
    }
    exit;
}

// --- 5. Database Connection ---
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    error_log("USSD DB Connection Error: " . $e->getMessage());
    sendNaloResponse($ussd_id, $msisdn, $user_data, "Service temporarily unavailable. Please try again later.", false);
}

// --- 6. USSD Logic Flow ---

try {
    // Initial dial or session reset
    if ($msgtype == true) {
        $_SESSION['step'] = 'main_menu';
        $menu = "Welcome to VoteHubGH\n";
        $menu .= "1. Vote\n";
        $menu .= "2. Buy ticket\n";
        $menu .= "3. Check Results\n";
        $menu .= "4. Help";
        sendNaloResponse($ussd_id, $msisdn, $user_data, $menu, true);
    }

    $step = $_SESSION['step'] ?? 'main_menu';

    switch ($step) {
        case 'main_menu':
            if ($user_data == '1') {
                $_SESSION['step'] = 'awaiting_nominee_code';
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Enter Nominee Code:", true);
            } elseif ($user_data == '2') {
                $_SESSION['step'] = 'awaiting_event_code';
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Enter Event Code:", true);
            } elseif ($user_data == '3') {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Results will be published on votehubgh.com", false);
            } elseif ($user_data == '4') {
                $help = "How to Vote:\n1. Select Vote\n2. Enter Nominee Code\n3. Enter Number of Votes\n4. Confirm & Pay\n\nBuy Tickets:\n1. Select Buy ticket\n2. Enter Event Code\n3. Choose Category\n4. Confirm & Pay";
                sendNaloResponse($ussd_id, $msisdn, $user_data, $help, false);
            } else {
                $menu = "Invalid choice.\n1. Vote\n2. Buy ticket\n3. Check Results\n4. Help";
                sendNaloResponse($ussd_id, $msisdn, $user_data, $menu, true);
            }
            break;

        // ========== VOTING FLOW ==========
        case 'awaiting_nominee_code':
            $nomineeCode = strtoupper($user_data);
            
            $stmt = $pdo->prepare("
                SELECT n.id, n.name, e.title as event_title, e.vote_price, e.id as event_id, n.category_id, e.end_date
                FROM nominees n
                JOIN categories c ON n.category_id = c.id
                JOIN events e ON c.event_id = e.id
                WHERE n.shortcode = ? AND n.status = 'active' AND e.status = 'active'
                  AND e.start_date <= NOW() AND e.end_date >= NOW()
                LIMIT 1
            ");
            $stmt->execute([$nomineeCode]);
            $nominee = $stmt->fetch();

            if (!$nominee) {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Invalid Nominee Code. Please try again.", false);
            }

            $_SESSION['nominee_id'] = $nominee['id'];
            $_SESSION['nominee_name'] = $nominee['name'];
            $_SESSION['event_id'] = $nominee['event_id'];
            $_SESSION['category_id'] = $nominee['category_id'];
            $_SESSION['vote_price'] = $nominee['vote_price'] > 0 ? $nominee['vote_price'] : VOTE_PRICE;
            $_SESSION['step'] = 'awaiting_vote_count';

            sendNaloResponse($ussd_id, $msisdn, $user_data, "Enter number of votes for " . $nominee['name'] . ":", true);
            break;

        case 'awaiting_vote_count':
            $quantity = intval(trim($user_data));
            if ($quantity < 1 || $quantity > 1000) {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Invalid quantity (1-1000). Please enter number of votes:", true);
            }

            $_SESSION['quantity'] = $quantity;
            $totalPrice = $_SESSION['vote_price'] * $quantity;
            $_SESSION['total_price'] = $totalPrice;
            $_SESSION['step'] = 'awaiting_confirmation';

            $response = "Vote for " . $_SESSION['nominee_name'] . "\n";
            $response .= "Quantity: " . $quantity . "\n";
            $response .= "Total Price: GHS " . number_format($totalPrice, 2) . "\n";
            $response .= "1. Confirm & Pay\n";
            $response .= "2. Cancel";
            sendNaloResponse($ussd_id, $msisdn, $user_data, $response, true);
            break;

        case 'awaiting_confirmation':
            if ($user_data == '1') {
                // Real-time check: Verify event hasn't expired since user started USSD session
                if (isEventExpired($_SESSION['event_id'])) {
                    sendNaloResponse($ussd_id, $msisdn, $user_data, "Sorry, voting for this event has ended. Thank you for your interest.", false);
                }

                $price = $_SESSION['total_price'];
                $quantity = $_SESSION['quantity'];
                $reference = 'USSD-' . time() . '-' . rand(1000, 9999);
                
                $provider = 'mtn'; // Default
                // Improved prefix check for Ghana
                $cleanPhone = preg_replace('/[^0-9]/', '', $msisdn);
                // If it starts with 233, get the next two digits
                if (strpos($cleanPhone, '233') === 0) {
                    $netPrefix = substr($cleanPhone, 3, 2);
                } else {
                    // If it starts with 0, get the next two digits
                    $netPrefix = substr($cleanPhone, 1, 2);
                }

                if (in_array($netPrefix, ['20', '50'])) $provider = 'vod';
                elseif (in_array($netPrefix, ['26', '56', '27', '57'])) $provider = 'tgo';

                // Format phone for storage
                $formattedMsisdn = preg_replace('/[^0-9]/', '', $msisdn);
                if (strlen($formattedMsisdn) == 9) {
                    $formattedMsisdn = '+233' . $formattedMsisdn;
                } elseif (strpos($formattedMsisdn, '233') === 0) {
                    $formattedMsisdn = '+' . $formattedMsisdn;
                } elseif (strpos($formattedMsisdn, '0') === 0) {
                    $formattedMsisdn = '+233' . substr($formattedMsisdn, 1);
                }

                // Create pending transaction with phone number
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (event_id, nominee_id, category_id, phone, amount, quantity, reference, payment_method, provider, status, metadata)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'ussd', ?, 'pending', ?)
                ");
                
                $metadata = [
                    'msisdn' => $msisdn,
                    'nominee_id' => $_SESSION['nominee_id'],
                    'event_id' => $_SESSION['event_id'],
                    'category_id' => $_SESSION['category_id'],
                    'quantity' => $quantity
                ];

                $stmt->execute([
                    $_SESSION['event_id'],
                    $_SESSION['nominee_id'],
                    $_SESSION['category_id'],
                    $formattedMsisdn,
                    $price,
                    $quantity,
                    $reference,
                    $provider,
                    json_encode($metadata)
                ]);

                // Trigger NaloPay Charge - Use raw msisdn
                $charge = chargeNaloMoMo($msisdn, $price, $provider, $reference, $metadata);
                $isChargeSuccess = $charge['success'] ?? $charge['sucess'] ?? false;

                if ($isChargeSuccess === true) {
                    // Store NaloPay's order_id as external_id
                    $externalId = $charge['data']['order_id'] ?? null;
                    if ($externalId) {
                        $updateStmt = $pdo->prepare("UPDATE transactions SET external_id = ? WHERE reference = ?");
                        $updateStmt->execute([$externalId, $reference]);
                    }

                    $otpCode = $charge['data']['otp_code'] ?? '';
                    
                    $msg = "Payment prompt sent to " . $msisdn . ". Please approve to complete your vote.";
                    if (!empty($otpCode)) {
                        $msg .= "\nIf no prompt appears, dial " . $otpCode . " to approve.";
                    }
                    sendNaloResponse($ussd_id, $msisdn, $user_data, $msg, false);
                } else {
                    $errorMsg = $charge['message'] ?? 'Unknown error';
                    if (isset($charge['errors'])) {
                        $errorMsg .= ': ' . json_encode($charge['errors']);
                    }
                    error_log("NaloPay Charge Error for $msisdn: " . $errorMsg . " | Response: " . json_encode($charge));
                    
                    sendNaloResponse($ussd_id, $msisdn, $user_data, "Payment Failed: " . $errorMsg, false);
                }
            } else {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Vote cancelled. Thank you.", false);
            }
            break;

        // ========== TICKET PURCHASE FLOW ==========
        case 'awaiting_event_code':
            $eventCode = strtoupper(trim($user_data));
            
            $stmt = $pdo->prepare("
                SELECT id, title, event_code
                FROM events
                WHERE event_code = ? AND status = 'active' AND event_type IN ('ticketing', 'both')
                  AND start_date <= NOW() AND end_date >= NOW()
                LIMIT 1
            ");
            $stmt->execute([$eventCode]);
            $ticketEvent = $stmt->fetch();

            if (!$ticketEvent) {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Invalid Event Code. No active ticketed event found.", false);
            }

            // Fetch ticket categories
            $catStmt = $pdo->prepare("SELECT id, name, price FROM ticket_categories WHERE event_id = ? ORDER BY price ASC");
            $catStmt->execute([$ticketEvent['id']]);
            $ticketCats = $catStmt->fetchAll();

            if (empty($ticketCats)) {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "No ticket categories available for this event.", false);
            }

            $_SESSION['ticket_event_id'] = $ticketEvent['id'];
            $_SESSION['ticket_event_title'] = $ticketEvent['title'];
            $_SESSION['ticket_categories'] = $ticketCats;
            $_SESSION['step'] = 'awaiting_ticket_category';

            $menu = "Select Ticket Category:\n";
            foreach ($ticketCats as $i => $cat) {
                $menu .= ($i + 1) . ". " . $cat['name'] . " (GHS " . number_format($cat['price'], 2) . ")\n";
            }
            sendNaloResponse($ussd_id, $msisdn, $user_data, rtrim($menu), true);
            break;

        case 'awaiting_ticket_category':
            $choice = intval($user_data);
            $ticketCats = $_SESSION['ticket_categories'] ?? [];

            if ($choice < 1 || $choice > count($ticketCats)) {
                $menu = "Invalid choice. Select Ticket Category:\n";
                foreach ($ticketCats as $i => $cat) {
                    $menu .= ($i + 1) . ". " . $cat['name'] . " (GHS " . number_format($cat['price'], 2) . ")\n";
                }
                sendNaloResponse($ussd_id, $msisdn, $user_data, rtrim($menu), true);
            }

            $selectedCat = $ticketCats[$choice - 1];
            $_SESSION['ticket_category_id'] = $selectedCat['id'];
            $_SESSION['ticket_category_name'] = $selectedCat['name'];
            $_SESSION['ticket_price'] = floatval($selectedCat['price']);
            $_SESSION['step'] = 'awaiting_ticket_quantity';

            sendNaloResponse($ussd_id, $msisdn, $user_data, "Enter number of tickets:", true);
            break;

        case 'awaiting_ticket_quantity':
            $qty = intval(trim($user_data));
            if ($qty < 1 || $qty > 50) {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Invalid quantity (1-50). Enter number of tickets:", true);
            }

            $_SESSION['ticket_quantity'] = $qty;
            $total = $_SESSION['ticket_price'] * $qty;
            $_SESSION['ticket_total'] = $total;
            $_SESSION['step'] = 'awaiting_ticket_confirmation';

            $confirm = "Ticket for " . $_SESSION['ticket_event_title'] . "\n";
            $confirm .= "Category: " . $_SESSION['ticket_category_name'] . "\n";
            $confirm .= "Qty: " . $qty . "\n";
            $confirm .= "Total: GHS " . number_format($total, 2) . "\n";
            $confirm .= "1. Confirm & Pay\n";
            $confirm .= "2. Cancel";
            sendNaloResponse($ussd_id, $msisdn, $user_data, $confirm, true);
            break;

        case 'awaiting_ticket_confirmation':
            if ($user_data == '1') {
                $price = $_SESSION['ticket_total'];
                $qty = $_SESSION['ticket_quantity'];
                $reference = 'TKT-' . time() . '-' . rand(1000, 9999);

                // Detect network from MSISDN
                $provider = 'mtn';
                $cleanPhone = preg_replace('/[^0-9]/', '', $msisdn);
                if (strpos($cleanPhone, '233') === 0) {
                    $netPrefix = substr($cleanPhone, 3, 2);
                } else {
                    $netPrefix = substr($cleanPhone, 1, 2);
                }
                if (in_array($netPrefix, ['20', '50'])) $provider = 'vod';
                elseif (in_array($netPrefix, ['26', '56', '27', '57'])) $provider = 'tgo';

                // Format phone
                $formattedMsisdn = preg_replace('/[^0-9]/', '', $msisdn);
                if (strlen($formattedMsisdn) == 9) {
                    $formattedMsisdn = '+233' . $formattedMsisdn;
                } elseif (strpos($formattedMsisdn, '233') === 0) {
                    $formattedMsisdn = '+' . $formattedMsisdn;
                } elseif (strpos($formattedMsisdn, '0') === 0) {
                    $formattedMsisdn = '+233' . substr($formattedMsisdn, 1);
                }

                // Create pending transaction for ticket (nominee_id and category_id are NULL)
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (event_id, ticket_category_id, phone, amount, quantity, reference, payment_method, provider, status, metadata)
                    VALUES (?, ?, ?, ?, ?, ?, 'ussd', ?, 'pending', ?)
                ");

                $metadata = [
                    'msisdn' => $msisdn,
                    'ticket_category_id' => $_SESSION['ticket_category_id'],
                    'event_id' => $_SESSION['ticket_event_id'],
                    'quantity' => $qty,
                    'phone' => $formattedMsisdn
                ];

                $stmt->execute([
                    $_SESSION['ticket_event_id'],
                    $_SESSION['ticket_category_id'],
                    $formattedMsisdn,
                    $price,
                    $qty,
                    $reference,
                    $provider,
                    json_encode($metadata)
                ]);

                // Trigger NaloPay Charge
                $charge = chargeNaloMoMo($msisdn, $price, $provider, $reference, $metadata);
                $isChargeSuccess = $charge['success'] ?? $charge['sucess'] ?? false;

                if ($isChargeSuccess === true) {
                    $externalId = $charge['data']['order_id'] ?? null;
                    if ($externalId) {
                        $updateStmt = $pdo->prepare("UPDATE transactions SET external_id = ? WHERE reference = ?");
                        $updateStmt->execute([$externalId, $reference]);
                    }

                    $otpCode = $charge['data']['otp_code'] ?? '';
                    $msg = "Payment prompt sent to " . $msisdn . ". Approve to purchase your ticket.";
                    if (!empty($otpCode)) {
                        $msg .= "\nDial " . $otpCode . " to approve.";
                    }
                    sendNaloResponse($ussd_id, $msisdn, $user_data, $msg, false);
                } else {
                    $errorMsg = $charge['message'] ?? 'Unknown error';
                    if (isset($charge['errors'])) {
                        $errorMsg .= ': ' . json_encode($charge['errors']);
                    }
                    error_log("NaloPay Ticket Charge Error for $msisdn: " . $errorMsg);
                    sendNaloResponse($ussd_id, $msisdn, $user_data, "Payment Failed: " . $errorMsg, false);
                }
            } else {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Ticket purchase cancelled. Thank you.", false);
            }
            break;

        default:
            sendNaloResponse($ussd_id, $msisdn, $user_data, "Invalid session. Please try again.", false);
            break;
    }

} catch (Exception $e) {
    error_log("USSD Processing Error: " . $e->getMessage());
    sendNaloResponse($ussd_id, $msisdn, $user_data, "Something went wrong. Please try again.", false);
}

