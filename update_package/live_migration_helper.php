<?php
/**
 * Deployment Migration Helper
 * Run this ONCE on your live server to generate access codes for existing nominees.
 */
require_once 'config/database.php';
$pdo = getDBConnection();

try {
    // Generate random access codes for existing nominees who don't have one
    $stmt = $pdo->query("SELECT id, name FROM nominees WHERE access_code IS NULL OR access_code = ''");
    $nominees = $stmt->fetchAll();
    
    if ($nominees) {
        $updateStmt = $pdo->prepare("UPDATE nominees SET access_code = ? WHERE id = ?");
        $count = 0;
        foreach ($nominees as $nominee) {
            $code = 'NOM-' . strtoupper(bin2hex(random_bytes(4)));
            $updateStmt->execute([$code, $nominee['id']]);
            $count++;
        }
        echo "Successfully generated access codes for $count nominees.";
    } else {
        echo "All nominees already have access codes.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
