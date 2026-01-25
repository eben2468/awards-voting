<?php
/**
 * Logout Script
 * Awards Voting System
 */

require_once 'config/database.php';

// Log the logout action
if (isLoggedIn()) {
    try {
        $pdo = getDBConnection();
        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'logout', ?, ?)");
        $logStmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
    } catch (PDOException $e) {
        error_log("Logout log error: " . $e->getMessage());
    }
}

// Destroy session
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to home with message
session_start();
setFlash('success', 'You have been successfully logged out.');
redirect('');
