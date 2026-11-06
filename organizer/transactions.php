<?php
/**
 * Organizer - Transactions for their own events
 * Awards Voting System
 */

require_once '../config/database.php';

if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$txPortal = 'organizer';
$txOrganizerId = $_SESSION['user_id'];

require '../includes/transactions-page.php';
