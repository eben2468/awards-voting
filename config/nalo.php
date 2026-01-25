<?php
/**
 * Nalo Solutions API Configuration
 * 
 * Configuration for Nalo Solutions USSD and SMS services
 */

// Nalo SMS Credentials (User to provide these)
define('NALO_SMS_KEY', 'ChKWJbL_FcwPWjTcNSkmtMw0kxxadrwEMoRC-tqSCAk6LYWLoy48QpopmAkfN75E'); // Your Nalo SMS API Key
define('NALO_SMS_SENDER_ID', 'VOTEHUBGH'); // Your Nalo SMS Sender ID
define('NALO_SMS_URL', 'https://api.nalosolutions.com/sms/v1/send');

// USSD Settings
define('NALO_USER_ID', 'eben1234'); // Your Nalo USSD User ID

/**
 * Send SMS via Nalo Solutions
 */
function sendNaloSMS($to, $message) {
    if (empty(NALO_SMS_KEY)) {
        error_log("Nalo SMS Error: API Key not set.");
        return ['status' => 'error', 'message' => 'API Key not set'];
    }

    $postData = [
        'key' => NALO_SMS_KEY,
        'msisdn' => $to,
        'message' => $message,
        'sender_id' => NALO_SMS_SENDER_ID
    ];

    $ch = curl_init(NALO_SMS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData)); // Use form-data
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Useful for local XAMPP environments
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        error_log("Nalo SMS CURL Error: " . $err);
        return ['status' => 'error', 'message' => $err];
    }

    $result = json_decode($response, true);
    if ($result === null) {
        $result = ['response' => $response];
    }
    $result['http_code'] = $httpCode;
    
    // Log SMS attempt for debugging
    error_log("Nalo SMS to $to - HTTP: $httpCode - Response: $response");
    
    return $result;
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

/**
 * Format phone number for Nalo
 */
function formatPhoneNumberForNalo($phone) {
    // Remove any spaces, dashes, or special characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Nalo usually accepts 233XXXXXXXXX or 0XXXXXXXXX
    // We'll standardize to 233 format if it's a Ghana number
    if (strlen($phone) == 10 && strpos($phone, '0') === 0) {
        return '233' . substr($phone, 1);
    }
    
    if (strlen($phone) == 9) {
        return '233' . $phone;
    }
    
    return $phone;
}
