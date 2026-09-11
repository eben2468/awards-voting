<?php
require_once 'config/database.php';
require_once 'config/nalo.php';

$test_phone = '0548811774';

echo "=== System SMS Integration Test ===\n";
echo "Sending test ticket confirmation SMS to: $test_phone\n";

$result = sendTicketConfirmationSMS(
    $test_phone,
    150.00,
    'GHS',
    'VoteHub Test Event',
    'VIP Category',
    'TKT-TEST12345',
    1,
    'TKT-TX-REF-999',
    'Ebenezer Owusu',
    '2026-06-15'
);

echo "\nResult:\n";
print_r($result);
echo "\nDone.\n";
