<?php
require_once 'config/database.php';
$pdo = getDBConnection();

$totalNominees = $pdo->query("SELECT COUNT(*) FROM nominees")->fetchColumn();
echo "Total nominees: " . $totalNominees . "\n";

$statusCounts = $pdo->query("SELECT status, COUNT(*) as cnt FROM nominees GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
echo "Nominee status counts:\n";
print_r($statusCounts);

$sampleNominees = $pdo->query("SELECT id, name, category_id, status, vote_count FROM nominees LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "Sample nominees:\n";
print_r($sampleNominees);

$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
echo "Total categories: " . $totalCategories . "\n";

$catStatusCounts = $pdo->query("SELECT status, COUNT(*) as cnt FROM categories GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
echo "Category status counts:\n";
print_r($catStatusCounts);
?>
