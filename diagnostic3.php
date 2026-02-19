<?php
include 'db_config.php';

echo "=== AFFILIATE SALES TRACKING ANALYSIS ===\n\n";

// Check affiliate_clicks table
echo "--- Affiliate Clicks Table ---\n";
$res = $conn->query("DESCRIBE affiliate_clicks");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

echo "\n--- Sample Affiliate Click Data ---\n";
$res = $conn->query("SELECT * FROM affiliate_clicks LIMIT 3");
if ($res) {
    echo "Count: " . $res->num_rows . "\n";
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}

echo "\n--- Affiliate Payments Table ---\n";
$res = $conn->query("DESCRIBE affiliate_payments");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

echo "\n--- Sample Affiliate Payments Data ---\n";
$res = $conn->query("SELECT * FROM affiliate_payments LIMIT 3");
if ($res) {
    echo "Count: " . $res->num_rows . "\n";
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}

echo "\n--- Sales by Affiliate ---\n";
$res = $conn->query("
SELECT 
    af.id, 
    af.name, 
    af.email,
    COUNT(ac.id) as total_clicks,
    SUM(CASE WHEN ac.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_sales,
    SUM(CASE WHEN ac.status = 'confirmed' THEN ac.commission ELSE 0 END) as earned_commission,
    af.balance,
    af.withdrawn
FROM affiliates af
LEFT JOIN affiliate_clicks ac ON af.id = ac.affiliate_id
GROUP BY af.id
ORDER BY earned_commission DESC
");
if ($res) {
    echo "Count: " . $res->num_rows . "\n";
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}

echo "\n--- Pending Affiliate Payments ---\n";
$res = $conn->query("
SELECT ap.id, af.name, af.email, ap.amount, ap.method, ap.status, ap.created_at
FROM affiliate_payments ap
JOIN affiliates af ON ap.affiliate_id = af.id
WHERE ap.status = 'pending'
");
if ($res) {
    echo "Count: " . $res->num_rows . "\n";
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}
?>
