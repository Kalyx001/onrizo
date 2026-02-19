<?php
session_start();
include __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo json_encode(['success'=>false,'message'=>'Not authorized']); exit;
}
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id) {
    $stmt = $conn->prepare('SELECT id, name, contact, percent, token, balance, status, created_at FROM affiliates WHERE product_id = ?');
    $stmt->bind_param('i', $product_id);
} else {
    // return affiliates for products owned by this admin
    $stmt = $conn->prepare('SELECT a.id, a.name, a.contact, a.percent, a.token, a.balance, a.status, a.created_at FROM affiliates a JOIN products p ON a.product_id = p.id WHERE p.admin_id = ?');
    $stmt->bind_param('i', $admin_id);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

echo json_encode(['success'=>true, 'affiliates'=>$rows]);
?>
