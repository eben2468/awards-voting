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

// Redirect with an appropriate message
session_start();

// ?cancel=1 is used to abandon a half-finished two-step verification
if (isset($_GET['cancel'])) {
    setFlash('info', 'Sign-in cancelled. You can log in with a different account.');
    redirect('login.php');
}

// Sent by the browser when the auto logout timer runs out. This goes in the URL
// because the session cookie was deleted above, which would lose a flash message.
if (isset($_GET['timeout'])) {
    redirect('login.php?timeout=1');
}

setFlash('success', 'You have been successfully logged out.');
redirect('');
