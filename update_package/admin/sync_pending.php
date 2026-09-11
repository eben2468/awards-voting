<?php
/**
 * Sync Pending Transactions
 */
require_once '../config/database.php';
require_once '../config/nalo.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$pdo = getDBConnection();

// Fetch pending transactions (limit to 50 to avoid timeout on large datasets)
$stmt = $pdo->query("SELECT * FROM transactions WHERE status = 'pending' ORDER BY created_at DESC LIMIT 50");
$pending = $stmt->fetchAll();

$updated = 0;
$failed = 0;
$skipped = 0;

foreach ($pending as $transaction) {
    // Determine lookup ID (Nalo usually responds better to their generated external_id, or original reference)
    $lookupId = $transaction['external_id'] ?? $transaction['reference'];
    if (!$lookupId) continue;

    try {
        $naloResponse = verifyNaloTransaction($lookupId);
        $isSuccess = $naloResponse['success'] ?? $naloResponse['sucess'] ?? false;

        if ($isSuccess && isset($naloResponse['data'])) {
            $data = $naloResponse['data'];
            $paymentStatus = $data['status'] ?? '';

            if ($paymentStatus === 'COMPLETED' || $paymentStatus === 'SUCCESS') {
                $result = processSuccessfulPayment($pdo, $transaction, $data);
                if ($result['success']) {
                    $updated++;
                } else {
                    $failed++;
                    error_log("Sync Pending Error ({$lookupId}): " . $result['message']);
                }
            } else {
                $skipped++;
            }
        } else {
            $skipped++;
        }
    } catch (Exception $e) {
        $failed++;
        error_log("Sync Pending Exception ({$lookupId}): " . $e->getMessage());
    }
}

if ($updated > 0) {
    setFlash('success', "$updated pending transaction(s) were successfully synced and updated to success!");
} else if ($failed > 0) {
    setFlash('warning', "No transactions were synced to success, but $failed encountered processing errors. Check error logs.");
} else {
    setFlash('info', "Checked " . count($pending) . " transactions. No pending transactions were found to be successful on the payment gateway yet.");
}

// Redirect back to transactions
redirect('transactions.php');
