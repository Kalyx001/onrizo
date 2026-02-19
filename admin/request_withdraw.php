<?php
session_start();
include '../db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// CSRF check
$headers = getallheaders();
$token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)){
    echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$amount = isset($data['amount']) ? floatval($data['amount']) : 0;
$to = isset($data['to']) ? trim($data['to']) : '';

if ($amount <= 0 || $to === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Ensure withdrawals table exists (including commission fields)
$create = "CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL,
    commission_percent DECIMAL(5,2) DEFAULT 5.00,
    commission_amount DECIMAL(12,2) DEFAULT 0,
    net_amount DECIMAL(12,2) DEFAULT 0,
    destination VARCHAR(255) NOT NULL,
    status VARCHAR(32) DEFAULT 'Reserved',
    transaction_id VARCHAR(255) DEFAULT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create);

$admin_id = (int)($_SESSION['admin_id'] ?? 0);

$commission_percent = 5.0; // platform commission percent on withdrawals

// Use transaction to lock and reserve amount
$conn->begin_transaction();
try {
    // total completed amount for this admin (sum of order_items subtotal for this admin)
    $stmtC = $conn->prepare("SELECT COALESCE(SUM(oi.subtotal),0) as bal FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE o.status = 'Completed' AND p.admin_id = ?");
    $stmtC->bind_param('i', $admin_id);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    $completed = $resC ? floatval($resC->fetch_assoc()['bal']) : 0.0;
    $stmtC->close();

    // platform commission (5%) applies to all completed sales
    $platform_commission_percent = 5.0;
    $platform_commission = round($completed * ($platform_commission_percent / 100), 2);
    $completed_net = max(0, $completed - $platform_commission);

    // sum of affiliate commissions owed for this admin (confirmed affiliate clicks)
    $stmtAff = $conn->prepare("SELECT COALESCE(SUM(ac.commission),0) as owed FROM affiliate_clicks ac JOIN products p ON ac.product_id = p.id WHERE p.admin_id = ? AND ac.status = 'confirmed'");
    $stmtAff->bind_param('i', $admin_id);
    $stmtAff->execute();
    $resAff = $stmtAff->get_result();
    $affPaid = $resAff ? floatval($resAff->fetch_assoc()['owed']) : 0.0;
    $stmtAff->close();

    // sum of PAID withdrawals (already paid) for this admin - use net_amount (actual cash disbursed)
    $stmtP = $conn->prepare("SELECT COALESCE(SUM(net_amount),0) as paid FROM withdrawals WHERE status = 'Paid' AND admin_id = ?");
    $stmtP->bind_param('i', $admin_id);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    $paid = $resP ? floatval($resP->fetch_assoc()['paid']) : 0.0;
    $stmtP->close();

    // sum of RESERVED or PENDING/Verified withdrawals that are not cancelled/paid for this admin
    $stmtR = $conn->prepare("SELECT COALESCE(SUM(amount),0) as reserved FROM withdrawals WHERE status IN ('Reserved','Pending','Verified') AND admin_id = ?");
    $stmtR->bind_param('i', $admin_id);
    $stmtR->execute();
    $resR = $stmtR->get_result();
    $reserved = $resR ? floatval($resR->fetch_assoc()['reserved']) : 0.0;
    $stmtR->close();

    // available balance = (completed revenue - platform commission) - affiliate owed - paid withdrawals - reserved requests
    $available = max(0, $completed_net - $affPaid - $paid - $reserved);

    if ($amount > $available) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Requested amount exceeds available balance']);
        exit;
    }

    // compute commission and net
    $commission_amount = round($amount * ($commission_percent/100), 2);
    $net_amount = round($amount - $commission_amount, 2);

    // insert as Reserved to prevent race conditions
    $stmt = $conn->prepare("INSERT INTO withdrawals (admin_id, amount, commission_percent, commission_amount, net_amount, destination, status) VALUES (?, ?, ?, ?, ?, ?, 'Reserved')");
    $stmt->bind_param('idddds', $admin_id, $amount, $commission_percent, $commission_amount, $net_amount, $to);
    $ok = $stmt->execute();
    if (!$ok) throw new Exception('DB insert failed');

    $conn->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log('Withdraw error: '.$e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}

?>
