<?php
session_start();
include __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'POST only']); exit; }
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
$admin_id = (int)($_SESSION['admin_id'] ?? 0);

$affiliate_id = intval($_POST['affiliate_id'] ?? 0);
$order_id = intval($_POST['order_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);

if (!$affiliate_id || !$amount || $amount <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid parameters']); exit; }

// check affiliate exists
$chk = $conn->prepare('SELECT id, product_id, balance FROM affiliates WHERE id = ? LIMIT 1');
$chk->bind_param('i', $affiliate_id); $chk->execute(); $res = $chk->get_result();
if (!$res || $res->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'Affiliate not found']); exit; }
$aff = $res->fetch_assoc();

// record payment
$stmt = $conn->prepare('INSERT INTO affiliate_payments (affiliate_id, order_id, admin_id, amount) VALUES (?, ?, ?, ?)');
$stmt->bind_param('iiid', $affiliate_id, $order_id, $admin_id, $amount);
$ok = $stmt->execute();
if (!$ok) { echo json_encode(['success'=>false,'message'=>$stmt->error]); exit; }

// update affiliate balance (reduce owed balance or add negative record); here we'll increase 'balance' negatively to reflect paid out
$upd = $conn->prepare('UPDATE affiliates SET balance = COALESCE(balance,0) - ? WHERE id = ?');
$upd->bind_param('di', $amount, $affiliate_id); $upd->execute();

echo json_encode(['success'=>true,'message'=>'Paid affiliate','payment_id'=>$stmt->insert_id]);
?>
