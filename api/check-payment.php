<?php
/**
 * API to check payment status
 * Used for polling from the frontend
 */

require_once '../config/database.php';
require_once '../config/nalo.php';

header('Content-Type: application/json');

$reference = $_GET['reference'] ?? '';

if (empty($reference)) {
    echo json_encode(['success' => false, 'message' => 'Reference is required.']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // 1. Check if already success in our DB (by reference or external_id)
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE reference = ? OR external_id = ?");
    $stmt->execute([$reference, $reference]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found.']);
        exit;
    }

    if ($transaction['status'] === 'success') {
        echo json_encode(['success' => true, 'status' => 'COMPLETED', 'message' => 'Payment already verified and recorded.']);
        exit;
    }
    
    // 2. If not success, ask NaloPay
    $confirmation = confirmNaloPayment($transaction);

    if ($confirmation['paid']) {
        $result = processSuccessfulPayment($pdo, $transaction, $confirmation['data']);

        if ($result['success']) {
            echo json_encode(['success' => true, 'status' => 'COMPLETED', 'message' => 'Payment verified and processed.']);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    } else {
        echo json_encode(['success' => true, 'status' => $confirmation['status'], 'message' => 'Payment is ' . $confirmation['status']]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
