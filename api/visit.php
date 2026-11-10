<?php
/**
 * Adds the screen size and time zone a browser reports to its own page view.
 * The signature proves the request came from the page that was tracked.
 */

require_once '../config/database.php';
require_once '../includes/visit-tracker.php';

header('Content-Type: application/json');

$id = (int) ($_POST['id'] ?? 0);
$sig = (string) ($_POST['sig'] ?? '');

if ($id <= 0 || !hash_equals(visitSignature($id), $sig)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$screen = (string) ($_POST['screen'] ?? '');
$screen = preg_match('/^\d{2,5}x\d{2,5}$/', $screen) ? $screen : null;
$timezone = (string) ($_POST['tz'] ?? '');
$timezone = preg_match('/^[A-Za-z_]+(?:\/[A-Za-z0-9_+\-]+){0,2}$/', $timezone) ? substr($timezone, 0, 50) : null;

try {
    visitQuery("UPDATE site_visits SET screen = ?, timezone = ? WHERE id = ? AND screen IS NULL", [$screen, $timezone, $id]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    error_log('Visit beacon error: ' . $e->getMessage());
    echo json_encode(['ok' => false]);
}
