<?php
session_start();
include '../db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success'=>false,'message'=>'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
action:
$action = $data['action'] ?? '';

if(!$id || !$action){ echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

// CSRF check
$headers = getallheaders();
$token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)){
    echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']);
    exit;
}

if($action === 'set_txn'){
    $txn = trim($data['txn'] ?? '');
    if($txn === ''){ echo json_encode(['success'=>false,'message'=>'Empty txn']); exit; }
    $stmt = $conn->prepare("UPDATE withdrawals SET transaction_id = ?, processed_at = NOW(), status = 'Paid' WHERE id = ?");
    $stmt->bind_param('si', $txn, $id);
    $ok = $stmt->execute();

    if ($ok) {
        // Record owner commission in ledger (idempotent)
        $chk = $conn->prepare("SELECT id, admin_id, commission_amount FROM withdrawals WHERE id = ? LIMIT 1");
        $chk->bind_param('i', $id);
        $chk->execute();
        $res = $chk->get_result();
        if ($row = $res->fetch_assoc()) {
            $commission = (float)$row['commission_amount'];
            $admin_id = (int)$row['admin_id'];
            if ($commission > 0) {
                $exists = $conn->prepare("SELECT id FROM owner_ledger WHERE withdrawal_id = ? AND type = 'commission' LIMIT 1");
                $exists->bind_param('i', $id);
                $exists->execute();
                $er = $exists->get_result();
                if (!$er || $er->num_rows === 0) {
                    $desc = "Commission for withdrawal #" . $id;
                    $ins = $conn->prepare("INSERT INTO owner_ledger (withdrawal_id, admin_id, amount, type, description) VALUES (?, ?, ?, 'commission', ?)");
                    $ins->bind_param('iids', $id, $admin_id, $commission, $desc);
                    $ins->execute();
                }
            }
        }
        if (isset($chk)) $chk->close();
    }

    echo json_encode(['success'=>$ok]);
    exit;
}

if(in_array($action, ['Paid','Cancelled'])){
    if ($action === 'Paid'){
        $stmt = $conn->prepare("UPDATE withdrawals SET status = 'Paid', processed_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $id);
    } else {
        $stmt = $conn->prepare("UPDATE withdrawals SET status = 'Cancelled' WHERE id = ?");
        $stmt->bind_param('i', $id);
    }
    $ok = $stmt->execute();

    if ($ok && $action === 'Paid'){
        // After marking Paid, ensure commission is recorded for owner (idempotent)
        $chk = $conn->prepare("SELECT id, admin_id, commission_amount FROM withdrawals WHERE id = ? LIMIT 1");
        $chk->bind_param('i', $id);
        $chk->execute();
        $res = $chk->get_result();
        if ($row = $res->fetch_assoc()) {
            $commission = (float)$row['commission_amount'];
            $admin_id = (int)$row['admin_id'];
            if ($commission > 0) {
                $exists = $conn->prepare("SELECT id FROM owner_ledger WHERE withdrawal_id = ? AND type = 'commission' LIMIT 1");
                $exists->bind_param('i', $id);
                $exists->execute();
                $er = $exists->get_result();
                if (!$er || $er->num_rows === 0) {
                    $desc = "Commission for withdrawal #" . $id;
                    $ins = $conn->prepare("INSERT INTO owner_ledger (withdrawal_id, admin_id, amount, type, description) VALUES (?, ?, ?, 'commission', ?)");
                    $ins->bind_param('iids', $id, $admin_id, $commission, $desc);
                    $ins->execute();
                }
            }
        }
        if (isset($chk)) $chk->close();
    }

    echo json_encode(['success'=>$ok]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);
?>
