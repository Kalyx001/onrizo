<?php
session_start();
header('Content-Type: application/json');
include '../db_config.php';

// Require admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}
$admin_id = (int)($_SESSION['admin_id'] ?? 0);

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$order_id = isset($data['order_id']) ? intval($data['order_id']) : 0;
$status = isset($data['status']) ? $data['status'] : '';

if (!$order_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Update order status
$updateQuery = "UPDATE orders SET status = ? WHERE id = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("si", $status, $order_id);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
    $conn->close();
    exit;
}

$paymentsCreated = 0;

// If marking Completed, auto-pay affiliate earnings for this admin's products in this order
if (strtolower($status) === 'completed') {
    // start transaction
    $conn->begin_transaction();
    try {
        // select affiliate earnings for this order where the related product belongs to this admin
        // Use the product_id stored on affiliate_earnings (more reliable) and join to products
        $qe = $conn->prepare("SELECT ae.id as earning_id, ae.affiliate_id, ae.amount, ae.product_id
            FROM affiliate_earnings ae
            JOIN products p ON ae.product_id = p.id
            WHERE ae.order_id = ? AND p.admin_id = ?");
        if (!$qe) {
            error_log('update_order_status: prepare affiliate earnings failed: ' . $conn->error);
        } else {
            $qe->bind_param('ii', $order_id, $admin_id);
            if (!$qe->execute()) {
                error_log('update_order_status: execute affiliate earnings failed: ' . $qe->error);
            } else {
                $res = $qe->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $affiliate_id = (int)$row['affiliate_id'];
                        $amount = floatval($row['amount']);

                        // ensure affiliate_payments table exists or prepare succeeds
                        $chk = $conn->prepare('SELECT id FROM affiliate_payments WHERE affiliate_id = ? AND order_id = ? LIMIT 1');
                        if (!$chk) {
                            error_log('update_order_status: prepare chk failed: ' . $conn->error);
                            continue;
                        }
                        $chk->bind_param('ii', $affiliate_id, $order_id);
                        if (!$chk->execute()) {
                            error_log('update_order_status: execute chk failed: ' . $chk->error);
                            $chk->close();
                            continue;
                        }
                        $rc = $chk->get_result();
                        $already = ($rc && $rc->num_rows > 0);
                        $chk->close();
                        if ($already) continue;

                        // insert payment
                        $ins = $conn->prepare('INSERT INTO affiliate_payments (affiliate_id, order_id, admin_id, amount) VALUES (?, ?, ?, ?)');
                        if (!$ins) {
                            error_log('update_order_status: prepare insert failed: ' . $conn->error);
                            continue;
                        }
                        $ins->bind_param('iiid', $affiliate_id, $order_id, $admin_id, $amount);
                        if (!$ins->execute()) {
                            error_log('update_order_status: execute insert failed: ' . $ins->error);
                            $ins->close();
                            continue;
                        }
                        $ins->close();

                        // deduct affiliate balance
                        $upd = $conn->prepare('UPDATE affiliates SET balance = COALESCE(balance,0) - ? WHERE id = ?');
                        if (!$upd) {
                            error_log('update_order_status: prepare update affiliates failed: ' . $conn->error);
                        } else {
                            $upd->bind_param('di', $amount, $affiliate_id);
                            if (!$upd->execute()) {
                                error_log('update_order_status: execute update affiliates failed: ' . $upd->error);
                            }
                            $upd->close();
                        }

                        $paymentsCreated++;
                    }
                }
            }
            $qe->close();
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log('update_order_status exception: ' . $e->getMessage());
        // log error but don't fail the status update
    }
}

echo json_encode(['success' => true, 'message' => 'Order status updated', 'payments_created' => $paymentsCreated]);

$conn->close();
?>
