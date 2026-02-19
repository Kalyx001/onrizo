<?php
session_start();
include '../db_config.php';

$admin_id = (int)($_SESSION['admin_id'] ?? 0);

// Allow reset only if authenticated
if ($admin_id <= 0) {
    echo "Error: Not authenticated. Please log in first.";
    exit;
}

$results = [];

// Backup: Show current state before deletion
$orderCount = 0;
$res = $conn->query("SELECT COUNT(*) as c FROM orders");
if ($res && $row = $res->fetch_assoc()) $orderCount = $row['c'];

$withdrawalCount = 0;
$res = $conn->query("SELECT COUNT(*) as c FROM withdrawals");
if ($res && $row = $res->fetch_assoc()) $withdrawalCount = $row['c'];

$results[] = "📊 <strong>Before Reset:</strong> $orderCount orders, $withdrawalCount withdrawals";

// DELETE ALL TRANSACTION DATA (keep master records)
$tables_to_clear = [
    'orders' => 'All orders',
    'order_items' => 'All order items',
    'withdrawals' => 'All withdrawal requests',
    'owner_ledger' => 'All owner ledger entries',
    'affiliate_payments' => 'All affiliate payments',
    'affiliate_clicks' => 'All affiliate clicks',
    'affiliate_earnings' => 'All affiliate earnings',
];

foreach ($tables_to_clear as $table => $label) {
    $del = $conn->query("DELETE FROM $table");
    if ($del) {
        $affected = $conn->affected_rows;
        $results[] = "✅ Cleared $label ($affected rows deleted)";
    } else {
        $results[] = "❌ Failed to clear $label: " . $conn->error;
    }
}

// Reset affiliate balances to 0
$res = $conn->query("UPDATE affiliates SET balance = 0");
if ($res) {
    $affected = $conn->affected_rows;
    $results[] = "✅ Reset affiliate balances to 0 ($affected updated)";
}

$results[] = "═══════════════════════════════════════";
$results[] = "✨ <strong>COMPLETE RESET FINISHED</strong>";
$results[] = "All orders, withdrawals, and payments cleared.";
$results[] = "Ready to test fresh workflow!";

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Complete System Reset</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; margin-bottom: 20px; }
        .result { padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #999; }
        .result strong { font-weight: 600; }
        .separator { background: #eee; border: none; height: 2px; margin: 15px 0; }
        .success { background: #e8f5e9; color: #1b5e20; border-left-color: #4caf50; }
        .error { background: #ffebee; color: #b71c1c; border-left-color: #f44336; }
        .info { background: #e3f2fd; color: #0d47a1; border-left-color: #2196f3; }
        .next-steps { margin-top: 30px; padding: 20px; background: #fff3e0; border-left: 4px solid #ff9800; border-radius: 4px; }
        .next-steps h2 { color: #e65100; margin-top: 0; }
        .next-steps ol { margin: 10px 0; }
        .next-steps li { margin: 8px 0; }
        a { color: #1976d2; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Complete System Reset</h1>
        
        <?php foreach ($results as $r): 
            if (strpos($r, '✅') === 0) {
                $class = 'success';
            } elseif (strpos($r, '❌') === 0) {
                $class = 'error';
            } elseif (strpos($r, '═') === 0 || strpos($r, '✨') === 0) {
                $class = 'info';
            } else {
                $class = 'result';
            }
        ?>
            <div class="result <?= $class ?>">
                <?= $r ?>
            </div>
        <?php endforeach; ?>

        <div class="next-steps">
            <h2>✅ What's Next?</h2>
            <ol>
                <li><strong>Create test orders:</strong> Go to the store front and place orders (or manually via <a href="reset_test_data.php">reset_test_data.php</a>)</li>
                <li><strong>Check balance:</strong> Open <a href="debug_balance.php">debug_balance.php</a> to verify available balance</li>
                <li><strong>Request withdrawal:</strong> Try withdrawing from store dashboard</li>
                <li><strong>Verify & Mark Paid:</strong> Complete the withdrawal flow</li>
            </ol>
            <p style="margin-top: 20px;">
                <a href="../admin/store_dashboard.php">← Back to Store Dashboard</a>
            </p>
        </div>
    </div>
</body>
</html>
<?php
?>
