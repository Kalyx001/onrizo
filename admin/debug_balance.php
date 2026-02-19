<?php
session_start();
include '../db_config.php';

$admin_id = (int)($_SESSION['admin_id'] ?? 0);

if ($admin_id <= 0) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');

// completed revenue for this admin
$stmtC = $conn->prepare("SELECT COALESCE(SUM(oi.subtotal),0) as bal FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE o.status = 'Completed' AND p.admin_id = ?");
$stmtC->bind_param('i', $admin_id);
$stmtC->execute();
$resC = $stmtC->get_result();
$completed = $resC ? floatval($resC->fetch_assoc()['bal']) : 0.0;
$stmtC->close();

// affiliate commissions owed
$stmtAff = $conn->prepare("SELECT COALESCE(SUM(ac.commission),0) as owed FROM affiliate_clicks ac JOIN products p ON ac.product_id = p.id WHERE p.admin_id = ? AND ac.status = 'confirmed'");
$stmtAff->bind_param('i', $admin_id);
$stmtAff->execute();
$resAff = $stmtAff->get_result();
$affOwed = $resAff ? floatval($resAff->fetch_assoc()['owed']) : 0.0;
$stmtAff->close();

// paid withdrawals (net amount actually disbursed)
$stmtP = $conn->prepare("SELECT COALESCE(SUM(net_amount),0) as paid FROM withdrawals WHERE status = 'Paid' AND admin_id = ?");
$stmtP->bind_param('i', $admin_id);
$stmtP->execute();
$resP = $stmtP->get_result();
$paid = $resP ? floatval($resP->fetch_assoc()['paid']) : 0.0;
$stmtP->close();

// reserved withdrawals
$stmtR = $conn->prepare("SELECT COALESCE(SUM(amount),0) as reserved FROM withdrawals WHERE status IN ('Reserved','Pending','Verified') AND admin_id = ?");
$stmtR->bind_param('i', $admin_id);
$stmtR->execute();
$resR = $stmtR->get_result();
$reserved = $resR ? floatval($resR->fetch_assoc()['reserved']) : 0.0;
$stmtR->close();

$available = max(0, $completed - $affOwed - $paid - $reserved);

echo json_encode([
    'admin_id' => $admin_id,
    'completed_revenue' => $completed,
    'affiliate_commissions_owed' => $affOwed,
    'paid_withdrawals' => $paid,
    'reserved_withdrawals' => $reserved,
    'available_balance' => $available,
    'formula' => "$completed - $affOwed - $paid - $reserved = $available"
]);
?>
