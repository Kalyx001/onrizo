<?php
session_start();
include 'db_config.php';
header('Content-Type: application/json');

// Check if affiliate is logged in
if (!isset($_SESSION['affiliate_logged_in']) || $_SESSION['affiliate_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only']);
    exit;
}

$affiliate_id = (int)($_SESSION['affiliate_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$method = trim($_POST['method'] ?? '');

$commission_percent = 5.0; // platform commission percent on affiliate withdrawals

if ($amount <= 0 || !in_array($method, ['mpesa', 'bank'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount or method']);
    exit;
}

// Check balance
$stmt = $conn->prepare("SELECT balance FROM affiliates WHERE id = ?");
$stmt->bind_param('i', $affiliate_id);
$stmt->execute();
$result = $stmt->get_result();
$affiliate = $result->fetch_assoc();
$stmt->close();

if (!$affiliate || $affiliate['balance'] < $amount) {
    echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
    exit;
}

// Create withdrawal request
$transaction_id = 'WD-' . strtoupper(substr(md5(time() . $affiliate_id), 0, 10));

// compute commission and net amounts
$commission_amount = round($amount * ($commission_percent/100), 2);
$net_amount = round($amount - $commission_amount, 2);

$insert_stmt = $conn->prepare("INSERT INTO affiliate_payments (affiliate_id, amount, commission_percent, commission_amount, net_amount, method, status, transaction_id) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
$insert_stmt->bind_param('iddddss', $affiliate_id, $amount, $commission_percent, $commission_amount, $net_amount, $method, $transaction_id);
$insert_ok = $insert_stmt->execute();
$insert_stmt->close();

if (!$insert_ok) {
    echo json_encode(['success' => false, 'message' => 'Withdrawal request failed']);
    exit;
}

// Deduct from affiliate balance (deduct requested/gross amount)
$update_stmt = $conn->prepare("UPDATE affiliates SET balance = balance - ? WHERE id = ?");
$update_stmt->bind_param('di', $amount, $affiliate_id);
$update_stmt->execute();
$update_stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Withdrawal request submitted',
    'transaction_id' => $transaction_id
]);
?>
