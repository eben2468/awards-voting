<?php
/**
 * Organizer - Bulk Upload Nominees
 * Awards Voting System
 */

require_once '../../config/database.php';

if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$bulkType = 'nominees';
$bulkPortal = 'organizer';
$bulkOrganizerId = $_SESSION['user_id'];

require '../../includes/bulk-import-page.php';
