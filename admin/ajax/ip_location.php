<?php
/**
 * Approximate locations for the addresses shown on the audit log page.
 */

require_once '../../config/database.php';
require_once '../../includes/device-info.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['locations' => []]);
    exit;
}

$ips = array_slice(array_filter(explode(',', (string) ($_GET['ips'] ?? ''))), 0, 15);
echo json_encode(['locations' => lookupIpLocations($ips)]);
