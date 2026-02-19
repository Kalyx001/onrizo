<?php
session_start();
include '../db_config.php';

// Simple data cleanup and setup for testing withdrawals
// Shows what's being deleted and what new test data is created

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
if ($admin_id <= 0) {
    echo "Error: Not authenticated. Please log in first.";
    exit;
}

$results = [];

// Delete existing withdrawal requests for this admin (to reset)
$del = $conn->prepare("DELETE FROM withdrawals WHERE admin_id = ?");
$del->bind_param('i', $admin_id);
if ($del->execute()) {
    $results[] = "✅ Deleted old withdrawals for admin $admin_id";
} else {
    $results[] = "❌ Failed to delete withdrawals: " . $conn->error;
}
$del->close();

// Delete owner_ledger entries for this admin
$del2 = $conn->prepare("DELETE FROM owner_ledger WHERE admin_id = ?");
$del2->bind_param('i', $admin_id);
if ($del2->execute()) {
    $results[] = "✅ Deleted old owner_ledger entries for admin $admin_id";
} else {
    $results[] = "❌ Failed to delete owner_ledger: " . $conn->error;
}
$del2->close();

// Create sample Completed orders for this admin with clear numbers
// Let's create 2 products from this admin with 2 completed orders each
$sampleOrders = [
    ['customer' => 'Test Customer 1', 'amount' => 1000, 'email' => 'test1@example.com', 'phone' => '0700000001'],
    ['customer' => 'Test Customer 2', 'amount' => 1500, 'email' => 'test2@example.com', 'phone' => '0700000002'],
    ['customer' => 'Test Customer 3', 'amount' => 2000, 'email' => 'test3@example.com', 'phone' => '0700000003'],
];

$totalCreated = 0;
foreach ($sampleOrders as $orderData) {
    // Insert order
    $oStmt = $conn->prepare("INSERT INTO orders (customer_name, customer_email, customer_phone, total_amount, status) VALUES (?, ?, ?, ?, 'Completed')");
    $oStmt->bind_param('sssd', $orderData['customer'], $orderData['email'], $orderData['phone'], $orderData['amount']);
    if ($oStmt->execute()) {
        $order_id = $conn->insert_id;
        
        // Insert order item linking to a product owned by this admin
        $iStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        $product_id = 1; // Assume product 1 is owned by admin
        $productName = 'Test Product';
        $qty = 1;
        $iStmt->bind_param('iisdid', $order_id, $product_id, $productName, $orderData['amount'], $qty, $orderData['amount']);
        if ($iStmt->execute()) {
            $results[] = "✅ Created order $order_id with KES " . number_format($orderData['amount'], 2);
            $totalCreated += $orderData['amount'];
        } else {
            $results[] = "❌ Failed to insert order item: " . $iStmt->error;
        }
        $iStmt->close();
    } else {
        $results[] = "❌ Failed to insert order: " . $oStmt->error;
    }
    $oStmt->close();
}

// Verify admin has at least one product
$pCheck = $conn->prepare("SELECT COUNT(*) as c FROM products WHERE admin_id = ?");
$pCheck->bind_param('i', $admin_id);
$pCheck->execute();
$pRes = $pCheck->get_result();
$pRow = $pRes->fetch_assoc();
if ($pRow['c'] == 0) {
    // Create a sample product for this admin
    $pStmt = $conn->prepare("INSERT INTO products (admin_id, name, price, description) VALUES (?, ?, ?, ?)");
    $prodName = 'Sample Test Product';
    $prodPrice = 1000;
    $prodDesc = 'For testing withdrawals';
    $pStmt->bind_param('isds', $admin_id, $prodName, $prodPrice, $prodDesc);
    if ($pStmt->execute()) {
        $results[] = "✅ Created sample product for admin $admin_id";
    }
    $pStmt->close();
}
$pCheck->close();

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Test Data</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .result { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .result.success { background: #e8f5e9; color: #2e7d32; }
        .result.error { background: #ffebee; color: #c62828; }
        .summary { margin-top: 20px; padding: 15px; background: #e3f2fd; border-left: 4px solid #1976d2; }
        a { color: #1976d2; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✨ Test Data Reset Complete</h1>
        <?php foreach ($results as $r): ?>
            <div class="result <?= strpos($r, '✅') === 0 ? 'success' : 'error' ?>">
                <?= htmlspecialchars($r) ?>
            </div>
        <?php endforeach; ?>
        <div class="summary">
            <strong>Summary for Admin <?= $admin_id ?>:</strong><br/>
            ✅ Old withdrawals deleted<br/>
            ✅ Old ledger entries deleted<br/>
            ✅ Created <?= count($sampleOrders) ?> test orders totaling KES <?= number_format($totalCreated, 2) ?><br/>
            <br/>
            <strong>Next Steps:</strong><br/>
            1. Visit <a href="../admin/debug_balance.php">debug_balance.php</a> to check available balance<br/>
            2. Request a withdrawal from the store dashboard (try 1000 or 2000 KES)<br/>
            3. Verify → Mark Paid to test the full flow
        </div>
    </div>
</body>
</html>
<?php
?>
