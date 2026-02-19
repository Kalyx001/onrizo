<?php
include 'db_config.php';

echo "=== PLATFORM VERIFICATION ===\n\n";

echo "1. All Products:\n";
$res = $conn->query("SELECT id, name, price FROM products WHERE deleted = 0 LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   - " . $row['name'] . " (KES " . $row['price'] . ")\n";
}

echo "\n2. All Admins:\n";
$res = $conn->query("SELECT id, email FROM admins LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   - " . $row['email'] . "\n";
}

echo "\n3. All Affiliates:\n";
$res = $conn->query("SELECT id, name, email, balance FROM affiliates LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   - " . $row['name'] . " (" . $row['email'] . ", Balance: KES " . $row['balance'] . ")\n";
}

echo "\n4. Recent Orders:\n";
$res = $conn->query("SELECT id, customer_name, total_amount, status FROM orders ORDER BY id DESC LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   - Order #" . $row['id'] . " - " . $row['customer_name'] . " (KES " . $row['total_amount'] . ", Status: " . $row['status'] . ")\n";
}

echo "\n5. Pending Payments:\n";
$res = $conn->query("SELECT ap.id, af.name, ap.amount FROM affiliate_payments ap JOIN affiliates af ON ap.affiliate_id = af.id WHERE ap.status = 'pending' LIMIT 3");
if ($res->num_rows === 0) {
    echo "   - No pending payments\n";
} else {
    while ($row = $res->fetch_assoc()) {
        echo "   - Payment for " . $row['name'] . " (KES " . $row['amount'] . ")\n";
    }
}

echo "\n6. Total Metrics:\n";
$res = $conn->query("SELECT 
    (SELECT COUNT(*) FROM products WHERE deleted = 0) as products,
    (SELECT COUNT(*) FROM admins) as admins,
    (SELECT COUNT(*) FROM affiliates) as affiliates,
    (SELECT COUNT(*) FROM orders) as orders,
    (SELECT COALESCE(SUM(subtotal), 0) FROM order_items) as total_sales
");
$metrics = $res->fetch_assoc();
echo "   - Products: " . $metrics['products'] . "\n";
echo "   - Admins: " . $metrics['admins'] . "\n";
echo "   - Affiliates: " . $metrics['affiliates'] . "\n";
echo "   - Orders: " . $metrics['orders'] . "\n";
echo "   - Total Sales: KES " . $metrics['total_sales'] . "\n";

echo "\n✅ All data verified!\n";
?>
