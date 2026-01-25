<?php
require_once 'config/database.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT id, title, vote_price FROM events WHERE status = 'active'");
$events = $stmt->fetchAll();
echo json_encode($events);
?>
