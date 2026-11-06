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
    
    $categoryId = intval($data['id'] ?? 0);
    $allowResults = $data['allow_results'] ? 1 : 0;
    
    if ($categoryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE categories SET allow_results = ? WHERE id = ?");
        $stmt->execute([$allowResults, $categoryId]);
        
        echo json_encode(['success' => true, 'message' => 'Results access updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
