<?php
/**
 * Keeps a session alive while someone is working on one page without reloading.
 * config/database.php records the activity, because this is a POST request.
 */

require_once '../config/database.php';

header('Content-Type: application/json');
echo json_encode(['active' => isLoggedIn()]);
