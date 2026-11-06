<?php
/**
 * Admin - Bulk Upload Nominees
 * Awards Voting System
 */

require_once '../../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$bulkType = 'nominees';
$bulkPortal = 'admin';
$bulkOrganizerId = null;

require '../../includes/bulk-import-page.php';
