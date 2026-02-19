<?php
date_default_timezone_set('Africa/Nairobi');

$raw = file_get_contents('php://input');
file_put_contents(__DIR__ . '/callback_log.txt', $raw . PHP_EOL, FILE_APPEND); // log for debugging

$data = json_decode($raw, true);
if (!$data || !isset($data['Body']['stkCallback'])) {
    http_response_code(400);
    exit("Invalid callback data");
}

$callback = $data['Body']['stkCallback'];
$resultCode = $callback['ResultCode'] ?? -1;
$checkoutRequestID = $callback['CheckoutRequestID'] ?? null;

// Extract amount and phone if available
$meta = $callback['CallbackMetadata']['Item'] ?? [];
$amount = null;
$phone = null;
foreach ($meta as $item) {
    if (!empty($item['Name']) && $item['Name'] === 'Amount') $amount = $item['Value'];
    if (!empty($item['Name']) && $item['Name'] === 'PhoneNumber') $phone = $item['Value'];
}

// Save to orders.json for historic record
$ordersFile = __DIR__ . '/orders.json';
$orders = file_exists($ordersFile) ? json_decode(file_get_contents($ordersFile), true) : [];

if ($resultCode == 0) {
    // Payment successful
    $orders[] = [
        'phone' => $phone,
        'amount' => $amount,
        'status' => 'Paid',
        'date' => date('Y-m-d H:i:s'),
        'checkoutRequestID' => $checkoutRequestID
    ];

    // Try to activate any pending promotion that matches this CheckoutRequestID
    include __DIR__ . '/db_config.php';
    if ($checkoutRequestID && isset($conn)) {
        // ensure promotions table exists
        @$conn->query("CREATE TABLE IF NOT EXISTS promotions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT DEFAULT 0,
            product_id INT NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            budget DECIMAL(12,2) DEFAULT 0,
            duration_days INT DEFAULT 7,
            status VARCHAR(32) DEFAULT 'Active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            checkout_request_id VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $conn->prepare("SELECT id FROM promotions WHERE checkout_request_id = ? LIMIT 1");
        $stmt->bind_param('s', $checkoutRequestID);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $promoId = (int)$row['id'];
            $u = $conn->prepare("UPDATE promotions SET status = 'Active' WHERE id = ?");
            $u->bind_param('i', $promoId);
            $u->execute();
            $u->close();
        }
        $stmt->close();
    }

} else {
    // Failed or cancelled
    $orders[] = [
        'phone' => $phone ?? 'N/A',
        'amount' => $amount ?? 0,
        'status' => 'Failed (' . ($callback['ResultDesc'] ?? 'Unknown') . ')',
        'date' => date('Y-m-d H:i:s'),
        'checkoutRequestID' => $checkoutRequestID
    ];
}

file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT));

http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
