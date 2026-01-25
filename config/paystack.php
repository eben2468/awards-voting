<?php
/**
 * Paystack Configuration
 */

// Paystack API Keys
define('PAYSTACK_PUBLIC_KEY', 'pk_live_d932b79857281cec62d58ad21c04cd29a8558b46');
define('PAYSTACK_SECRET_KEY', 'sk_live_5b5936dd60396dbd7b74dbc80eb778e64fab7124');

// Payment Settings
define('CURRENCY', 'GHS');
define('VOTE_PRICE', 1.00); 

/**
 * Initialize Paystack Transaction
 */
function initializePaystack($email, $amount, $reference, $metadata = []) {
    $url = "https://api.paystack.co/transaction/initialize";
    
    $fields = [
        'email' => $email,
        'amount' => intval(round($amount * 100)),
        'currency' => CURRENCY,
        'reference' => $reference,
        'metadata' => $metadata,
        'callback_url' => SITE_URL . '/verify-payment.php'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

/**
 * Verify Paystack Transaction
 */
function verifyPaystack($reference) {
    $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

/**
 * Charge Mobile Money via Paystack (Direct Charge)
 * Optimized for Ghana STK Push
 */
function chargePaystackMoMo($email, $amount, $phone, $provider, $reference, $metadata = []) {
    $url = "https://api.paystack.co/charge";
    
    $amountInPesewas = intval(round($amount * 100));
    
    // Normalize phone
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 12 && strpos($phone, '233') === 0) {
        $phone = '0' . substr($phone, 3);
    }
    
    // Provider Codes
    $provider = strtolower($provider);
    $providerCode = 'mtn';
    if ($provider === 'vod' || $provider === 'telecel') $providerCode = 'vod';
    elseif ($provider === 'tgo' || $provider === 'at') $providerCode = 'tgo';

    $fields = [
        'email' => $email,
        'amount' => $amountInPesewas,
        'currency' => 'GHS',
        'reference' => $reference,
        'ip_address' => '41.215.67.45', // Ghana IP hint
        'callback_url' => SITE_URL . '/ussd/callback.php',
        'mobile_money' => [
            'phone' => $phone,
            'provider' => $providerCode
        ],
        'metadata' => $metadata
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json",
        "X-Forwarded-For: 41.215.67.45" // Additional Ghana IP hint
    ]);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);

    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['status' => false, 'message' => "Connection Error: " . $err];
    }

    $response = json_decode($result, true);
    error_log("Paystack Charge Response: Ref=$reference, Status=" . ($response['data']['status'] ?? 'failed'));
    
    return $response;
}