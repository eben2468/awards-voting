<?php
require_once 'config/database.php';
$pdo = getDBConnection();
try {
    $pdo->exec("ALTER TABLE categories ADD COLUMN access_code VARCHAR(50) UNIQUE AFTER description");
    echo "Column 'access_code' added to categories table successfully.\n";
    
    // Check if access_code exists in nominees and migrate if needed, then drop
    $stmt = $pdo->query("SHOW COLUMNS FROM nominees LIKE 'access_code'");
    if ($stmt->rowCount() > 0) {
        // Try to migrate some codes to categories just in case
        $pdo->exec("UPDATE categories c SET c.access_code = (SELECT n.access_code FROM nominees n WHERE n.category_id = c.id LIMIT 1) WHERE c.access_code IS NULL OR c.access_code = ''");
        
        $pdo->exec("ALTER TABLE nominees DROP COLUMN access_code");
        echo "Column 'access_code' moved from nominees to categories.\n";
    }

    // Generate codes for categories that don't have one
    $stmt = $pdo->query("SELECT id FROM categories WHERE access_code IS NULL OR access_code = ''");
    $categories = $stmt->fetchAll();
    if ($categories) {
        $updateStmt = $pdo->prepare("UPDATE categories SET access_code = ? WHERE id = ?");
        foreach ($categories as $cat) {
            $code = 'CAT-' . strtoupper(bin2hex(random_bytes(4)));
            $updateStmt->execute([$code, $cat['id']]);
        }
        echo "Generated codes for " . count($categories) . " categories.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
