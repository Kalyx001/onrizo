<?php
session_start();
include 'db_config.php';

header('Content-Type: application/json');

// Check if affiliate is logged in
if (!isset($_SESSION['affiliate_logged_in']) || $_SESSION['affiliate_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$affiliate_id = (int)($_SESSION['affiliate_id'] ?? 0);

// Get earned commission (from confirmed sales)
$earned_stmt = $conn->prepare("
    SELECT COALESCE(SUM(commission), 0) as total 
    FROM affiliate_clicks 
    WHERE affiliate_id = ? AND status = 'confirmed'
");
$earned_stmt->bind_param('i', $affiliate_id);
$earned_stmt->execute();
$earned_result = $earned_stmt->get_result();
$earned_commission = (float)($earned_result->fetch_assoc()['total'] ?? 0);
$earned_stmt->close();

// Get approved payments (approved but not necessarily paid yet)
$approved_stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM affiliate_payments 
    WHERE affiliate_id = ? AND status IN ('approved', 'paid')
");
$approved_stmt->bind_param('i', $affiliate_id);
$approved_stmt->execute();
$approved_result = $approved_stmt->get_result();
$approved_payments = (float)($approved_result->fetch_assoc()['total'] ?? 0);
$approved_stmt->close();

// Get pending payments (submitted but not approved)
$pending_stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM affiliate_payments 
    WHERE affiliate_id = ? AND status = 'pending'
");
$pending_stmt->bind_param('i', $affiliate_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_payments = (float)($pending_result->fetch_assoc()['total'] ?? 0);
$pending_stmt->close();

// Calculate available balance (earned - approved)
$available_balance = max(0, $earned_commission - $approved_payments);

echo json_encode([
    'success' => true,
    'earned_commission' => $earned_commission,
    'approved_payments' => $approved_payments,
    'pending_payments' => $pending_payments,
    'available_balance' => $available_balance,
    'status_message' => $pending_payments > 0 
        ? "You have KES " . number_format($pending_payments, 0) . " pending approval from the admin"
        : "All your payments are up to date!"
]);

$conn->close();
?>
