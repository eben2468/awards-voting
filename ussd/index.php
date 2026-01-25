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
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../config/nalo.php';

/**
 * Send USSD Response and Exit
 */
function sendNaloResponse($ussd_id, $msisdn, $user_data, $msg, $continue = true) {
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
        $menu .= "2. Check Results\n";
        $menu .= "3. Help";
        sendNaloResponse($ussd_id, $msisdn, $user_data, $menu, true);
    }

    $step = $_SESSION['step'] ?? 'main_menu';

    switch ($step) {
        case 'main_menu':
            if ($user_data == '1') {
                $_SESSION['step'] = 'awaiting_nominee_code';
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Enter Nominee Code:", true);
            } elseif ($user_data == '2') {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Results will be published on votehubgh.com", false);
            } elseif ($user_data == '3') {
                $help = "How to Vote:\n1. Select Vote\n2. Enter Nominee Code\n3. Enter Number of Votes\n4. Confirm & Pay";
                sendNaloResponse($ussd_id, $msisdn, $user_data, $help, false);
            } else {
                $menu = "Invalid choice.\n1. Vote\n2. Check Results\n3. Help";
                sendNaloResponse($ussd_id, $msisdn, $user_data, $menu, true);
            }
            break;

        case 'awaiting_nominee_code':
            $nomineeCode = strtoupper($user_data);
            
            $stmt = $pdo->prepare("
                SELECT n.id, n.name, e.title as event_title, e.vote_price, e.id as event_id, n.category_id
                FROM nominees n
                JOIN categories c ON n.category_id = c.id
                JOIN events e ON c.event_id = e.id
                WHERE n.shortcode = ? AND n.status = 'active' AND e.status = 'active'
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
            if ($quantity < 1) {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Invalid quantity. Please enter 1 or more:", true);
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

                // Format phone for Paystack (024xxxxxxx)
                $formattedPhone = $cleanPhone;
                if (strpos($formattedPhone, '233') === 0) {
                    $formattedPhone = '0' . substr($formattedPhone, 3);
                }

                // Create pending transaction
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (event_id, nominee_id, category_id, amount, quantity, reference, payment_method, provider, status, metadata)
                    VALUES (?, ?, ?, ?, ?, ?, 'ussd', ?, 'pending', ?)
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
                    $price,
                    $quantity,
                    $reference,
                    $provider,
                    json_encode($metadata)
                ]);

                // Trigger Paystack Charge
                // Use a standard email format to avoid validation errors
                $email = "customer-" . $formattedPhone . "@votehubgh.com";
                $charge = chargePaystackMoMo($email, $price, $formattedPhone, $provider, $reference, $metadata);

                if (isset($charge['status']) && $charge['status'] === true) {
                    $payStatus = $charge['data']['status'] ?? '';
                    
                    // Show generic success message for both direct push and pay_offline
                    $msg = "Payment prompt sent to " . $formattedPhone . ". Please approve to complete your vote for " . $_SESSION['nominee_name'] . " or go to My Approval page to complete your vote.";
                    sendNaloResponse($ussd_id, $msisdn, $user_data, $msg, false);
                } else {
                    $errorMsg = $charge['message'] ?? 'Unknown error';
                    error_log("Paystack Charge Error for $msisdn: " . $errorMsg . " | Response: " . json_encode($charge));
                    
                    // Show the specific error to the user for easier debugging
                    $displayError = "Payment Failed: " . $errorMsg;
                    if (stripos($errorMsg, 'PCI') !== false || stripos($errorMsg, 'compliance') !== false) {
                        $displayError = "Payment Error: Your Paystack account needs 'Direct Charge' enabled for Mobile Money.";
                    }
                    
                    sendNaloResponse($ussd_id, $msisdn, $user_data, $displayError, false);
                }
            } else {
                sendNaloResponse($ussd_id, $msisdn, $user_data, "Vote cancelled. Thank you.", false);
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
