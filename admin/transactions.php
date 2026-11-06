<?php
/**
 * Admin - Transactions
 * Awards Voting System
 */

require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$txPortal = 'admin';
$txOrganizerId = null;

require '../includes/transactions-page.php';
