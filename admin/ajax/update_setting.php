<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!verifyCSRFToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $key = $data['key'] ?? '';
    $value = $data['value'] ?? '';
    
    // Only switches flipped straight from the page. Everything else goes through
    // the Settings forms, which validate what is saved.
    if (!in_array($key, ['show_vote_counts'], true)) {
        echo json_encode(['success' => false, 'message' => 'Missing key']);
        exit;
    }

    try {
        // saveSetting() writes and then reads the value back, so a silent
        // failure is reported as a failure instead of a success.
        if (saveSetting($key, $value)) {
            echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'The setting could not be saved. Check Diagnostics.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
