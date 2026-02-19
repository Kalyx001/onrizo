<?php
session_start();
include __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo json_encode(['success'=>false,'message'=>'Not authorized']); exit;
}
$admin_id = (int)($_SESSION['admin_id'] ?? 0);

$product_id = intval($_POST['product_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$percent = floatval($_POST['percent'] ?? 0);

if (!$product_id || !$name || $percent <= 0) {
    echo json_encode(['success'=>false,'message'=>'Missing required fields']); exit;
}

// generate token
$token = bin2hex(random_bytes(16));

$stmt = $conn->prepare("INSERT INTO affiliates (product_id, name, contact, percent, token) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('issds', $product_id, $name, $contact, $percent, $token);
$ok = $stmt->execute();
if ($ok) {
    $id = $stmt->insert_id;
    $link = sprintf('%s/?ref=%s&product=%d', rtrim((isset($_SERVER['HTTPS'])? 'https://':'http://' ) . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])), '/'), $token, $product_id);
    echo json_encode(['success'=>true,'id'=>$id,'token'=>$token,'link'=>$link]);
} else {
    echo json_encode(['success'=>false,'message'=>$stmt->error]);
}
$stmt->close();
?>
