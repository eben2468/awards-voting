<?php
/**
 * Newsletter Subscription Handler
 */

require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Verify CSRF Token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    echo json_encode(['status' => 'error', 'message' => 'Security token mismatch. Please refresh the page.']);
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Check if already subscribed
    $stmt = $pdo->prepare("SELECT id, status FROM subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $subscriber = $stmt->fetch();
    
    if ($subscriber) {
        if ($subscriber['status'] === 'active') {
            echo json_encode(['status' => 'info', 'message' => 'You are already subscribed to our newsletter!']);
        } else {
            // Re-activate subscription
            $stmt = $pdo->prepare("UPDATE subscribers SET status = 'active' WHERE id = ?");
            $stmt->execute([$subscriber['id']]);
            echo json_encode(['status' => 'success', 'message' => 'Welcome back! Your subscription has been reactivated.']);
        }
    } else {
        // New subscription
        $stmt = $pdo->prepare("INSERT INTO subscribers (email) VALUES (?)");
        $stmt->execute([$email]);
        
        // Create notification for admin (optional but good)
        createNotification(1, "New Newsletter Subscriber", "A new user ($email) has subscribed to the newsletter.");
        
        echo json_encode(['status' => 'success', 'message' => 'Thank you for subscribing to our newsletter!']);
    }
} catch (Exception $e) {
    error_log("Subscription Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred. Please try again later.']);
}
