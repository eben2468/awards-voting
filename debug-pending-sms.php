<?php
/**
 * Debug Pending SMS Issues
 * Check what's actually happening with pending transactions
 */

require_once 'config/database.php';
require_once 'config/nalo.php';

$pdo = getDBConnection();

echo "=== DEBUG: Pending Transactions ===\n\n";

// Get pending transactions
$stmt = $pdo->prepare("
    SELECT t.id, t.phone, t.user_id, t.amount, t.metadata,
           u.fullname, u.email,
           e.title as event_title, n.name as nominee_name
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN events e ON t.event_id = e.id
    LEFT JOIN nominees n ON t.nominee_id = n.id
    WHERE t.status = 'pending'
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute();
$transactions = $stmt->fetchAll();

echo "Total pending transactions found: " . count($transactions) . "\n\n";

foreach ($transactions as $i => $tx) {
    echo "Transaction #" . ($i + 1) . "\n";
    echo "  ID: " . $tx['id'] . "\n";
    echo "  User: " . ($tx['fullname'] ?: 'Guest') . " (" . $tx['email'] . ")\n";
    echo "  Phone in DB: " . ($tx['phone'] ? $tx['phone'] : '[NULL/EMPTY]') . "\n";
    
    // Check metadata for phone
    $metadata = is_string($tx['metadata']) ? json_decode($tx['metadata'], true) : $tx['metadata'];
    $metaPhone = $metadata['phone'] ?? $metadata['msisdn'] ?? null;
    echo "  Phone in metadata: " . ($metaPhone ? $metaPhone : '[NONE]') . "\n";
    
    // Use whichever phone we have
    $phoneToUse = $tx['phone'] ?: $metaPhone;
    echo "  Phone to send to: " . ($phoneToUse ? $phoneToUse : '[CANNOT SEND - NO PHONE]') . "\n";
    
    // Test formatting
    if ($phoneToUse) {
        $formatted = formatPhoneNumberForNalo($phoneToUse);
        echo "  Formatted phone: " . $formatted . "\n";
        
        // Test a dummy SMS send
        echo "  Testing SMS send...\n";
        $testResult = sendNaloSMS($phoneToUse, "Test SMS from debug script");
        echo "  Result: " . json_encode($testResult) . "\n";
    }
    
    echo "\n";
}

echo "\n=== END DEBUG ===\n";
