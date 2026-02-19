<?php
include 'db_config.php';

echo "=== WITHDRAWAL DIAGNOSIS ===\n\n";

// Check withdrawals table structure
echo "--- Withdrawals Table Structure ---\n";
$res = $conn->query("DESCRIBE withdrawals");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n--- Current Withdrawals Data ---\n";
$res = $conn->query("SELECT * FROM withdrawals LIMIT 5");
if ($res) {
    echo "Count: " . $res->num_rows . "\n";
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}

echo "\n--- Affiliate Payments Data ---\n";
$res = $conn->query("SELECT id, affiliate_id, amount, method, status, created_at FROM affiliate_payments ORDER BY id DESC LIMIT 5");
if ($res) {
    echo "Count: " . $res->num_rows . "\n";
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}

echo "\n--- Test Query (admin_id=1) ---\n";
$res = $conn->query("SELECT * FROM withdrawals WHERE admin_id = 1");
if ($res) {
    echo "Results: " . $res->num_rows . " rows\n";
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}
?>
