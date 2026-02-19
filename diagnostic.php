<?php
include 'db_config.php';

echo "=== DATABASE DIAGNOSTIC ===\n\n";

// Check data
$queries = [
    'Total Orders' => 'SELECT COUNT(*) as cnt FROM orders',
    'Total Order Items' => 'SELECT COUNT(*) as cnt FROM order_items',
    'Total Products' => 'SELECT COUNT(*) as cnt FROM products',
    'Total Affiliates' => 'SELECT COUNT(*) as cnt FROM affiliates',
    'Top Products (no admin filter)' => 'SELECT p.id, p.name, COUNT(oi.id) as sales, SUM(oi.subtotal) as revenue FROM products p LEFT JOIN order_items oi ON p.id = oi.product_id GROUP BY p.id ORDER BY sales DESC LIMIT 5',
    'Top Affiliates (no admin filter)' => 'SELECT af.name, af.email, COUNT(ac.id) as clicks, SUM(ac.commission) as commissions FROM affiliate_clicks ac LEFT JOIN affiliates af ON ac.affiliate_id = af.id GROUP BY af.id ORDER BY commissions DESC LIMIT 5',
    'Recent Orders' => 'SELECT id, customer_name, customer_email, total_amount, status, order_date FROM orders ORDER BY order_date DESC LIMIT 10'
];

foreach ($queries as $label => $query) {
    echo "--- $label ---\n";
    $res = $conn->query($query);
    if ($res) {
        $count = $res->num_rows;
        echo "Rows: $count\n";
        if ($count > 0 && strpos($query, 'SELECT COUNT') === false) {
            $row = $res->fetch_assoc();
            echo "Sample: " . json_encode($row) . "\n";
        } elseif (strpos($query, 'SELECT COUNT') !== false) {
            $row = $res->fetch_assoc();
            echo "Count: " . $row['cnt'] . "\n";
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    echo "\n";
}
?>
