<?php
/**
 * Test Nalo SMS API - Comprehensive Test
 * Run this on your live server to diagnose SMS issues
 */

require_once 'config/database.php';
require_once 'config/nalo.php';

echo "=== NALO SMS API DIAGNOSTIC TEST ===\n\n";

// Test 1: Check if we're on a production IP (not localhost)
echo "TEST 1: Server IP Detection\n";
echo "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'UNDEFINED') . "\n";
echo "HTTP_CF_CONNECTING_IP: " . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'NOT SET') . "\n";
echo "HTTP_X_FORWARDED_FOR: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'NOT SET') . "\n";

$is_localhost = in_array($_SERVER['REMOTE_ADDR'] ?? '::1', ['127.0.0.1', 'localhost', '::1']);
echo "Is Localhost: " . ($is_localhost ? 'YES' : 'NO') . "\n\n";

// Test 2: Check API credentials
echo "TEST 2: API Credentials\n";
echo "NALO_SMS_KEY set: " . (empty(NALO_SMS_KEY) ? 'NO' : 'YES') . "\n";
echo "NALO_SMS_SENDER_ID: " . NALO_SMS_SENDER_ID . "\n";
echo "NALO_SMS_URL: " . NALO_SMS_URL . "\n\n";

// Test 3: Test phone number formatting
echo "TEST 3: Phone Number Formatting\n";
$testPhones = [
    '+233548811774',
    '0548811774',
    '548811774',
    '233548811774',
];

foreach ($testPhones as $phone) {
    $formatted = formatPhoneNumberForNalo($phone);
    echo "$phone => $formatted\n";
}
echo "\n";

// Test 4: Test actual SMS send
echo "TEST 4: Send Test SMS\n";
$testPhone = '+233548811774';
$testMessage = 'Test SMS from VoteHubGH at ' . date('Y-m-d H:i:s');

echo "Phone: $testPhone\n";
echo "Message: $testMessage\n";
echo "Sending...\n";

$result = sendNaloSMS($testPhone, $testMessage);

echo "Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

if ($result['status'] === 'success') {
    echo "✓ SMS SENT SUCCESSFULLY\n";
} else {
    echo "✗ SMS FAILED\n";
    echo "Error: " . ($result['message'] ?? 'Unknown error') . "\n";
}

echo "\n=== END TEST ===\n";
