<?php
/**
 * SMS API Test Script
 * Debug the Nalo SMS API integration
 */

// Test credentials
$api_key = 'ChKWJbL_FcwPWjTcNSkmtMw0kxxadrwEMoRC-tqSCAk6LYWLoy48QpopmAkfN75E';
$sender_id = 'VOTEHUBGH';
$test_phone = '+233548811774';
$test_message = 'Test SMS from Awards Voting System';
$api_url = 'https://api.nalosolutions.com/sms/v1/send';

echo "=== Nalo SMS API Test ===\n\n";

// Test 1: Form-encoded (Original format)
echo "Test 1: Form-encoded format (key in body)\n";
echo "URL: $api_url\n";
$form_data = http_build_query([
    'key' => $api_key,
    'msisdn' => $test_phone,
    'message' => $test_message,
    'sender_id' => $sender_id
]);

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $form_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response1 = curl_exec($ch);
$http_code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err1 = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code1\n";
echo "Error: " . ($err1 ?: 'None') . "\n";
echo "Response: " . substr($response1, 0, 500) . "\n\n";

// Test 2: JSON format with Bearer token
echo "Test 2: JSON format with Bearer token\n";
echo "URL: $api_url\n";
$json_data = json_encode([
    'recipient' => $test_phone,
    'message' => $test_message,
    'sender_id' => $sender_id
]);

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response2 = curl_exec($ch);
$http_code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err2 = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code2\n";
echo "Error: " . ($err2 ?: 'None') . "\n";
echo "Response: " . substr($response2, 0, 500) . "\n\n";

// Test 3: Bearer token in URL parameter
echo "Test 3: Bearer token as URL parameter\n";
$url_with_key = $api_url . '?key=' . urlencode($api_key);
echo "URL: $url_with_key\n";

$ch = curl_init($url_with_key);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response3 = curl_exec($ch);
$http_code3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err3 = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code3\n";
echo "Error: " . ($err3 ?: 'None') . "\n";
echo "Response: " . substr($response3, 0, 500) . "\n\n";

// Summary
echo "=== Summary ===\n";
echo "Test 1 (Form-encoded): HTTP $http_code1\n";
echo "Test 2 (JSON + Bearer): HTTP $http_code2\n";
echo "Test 3 (JSON + URL param): HTTP $http_code3\n";
echo "\nCheck logs for full responses.\n";
?>
