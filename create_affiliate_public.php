<?php
// Public affiliate creation endpoint for visitors to generate a referal link to sell a product
header('Content-Type: application/json');
include __DIR__ . '/db_config.php';

// Accept POST or GET
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : (isset($_GET['product_id'])? intval($_GET['product_id']): 0);
$name = isset($_POST['name']) ? trim($_POST['name']) : (isset($_GET['name'])? trim($_GET['name']): '');
$contact = isset($_POST['contact']) ? trim($_POST['contact']) : (isset($_GET['contact'])? trim($_GET['contact']): '');
$percent = isset($_POST['percent']) ? floatval($_POST['percent']) : (isset($_GET['percent'])? floatval($_GET['percent']): null);

if (!$product_id) {
    echo json_encode(['success'=>false,'message'=>'Missing product_id']); exit;
}

// check product exists
$stmt = $conn->prepare('SELECT id, price, affiliate_percent FROM products WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $product_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success'=>false,'message'=>'Product not found']); exit;
}
$prod = $res->fetch_assoc();
$stmt->close();

// decide percent: prefer provided percent (if valid), otherwise product default, otherwise 0
$usePercent = 0.0;
if ($percent !== null && is_numeric($percent) && $percent >= 0) {
    $usePercent = floatval($percent);
} elseif (isset($prod['affiliate_percent']) && $prod['affiliate_percent'] !== null && $prod['affiliate_percent'] !== '') {
    $usePercent = floatval($prod['affiliate_percent']);
}

// generate token
try{
    $token = bin2hex(random_bytes(12));
} catch(Exception $e) {
    $token = substr(md5(uniqid((string)microtime(true), true)), 0, 24);
}

// insert into affiliates table
$ins = $conn->prepare('INSERT INTO affiliates (product_id, name, contact, percent, token, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$status = 'Active';
$ins->bind_param('issdss', $product_id, $name, $contact, $usePercent, $token, $status);
$ok = $ins->execute();
if (!$ok) {
    echo json_encode(['success'=>false,'message'=>'DB insert failed: '.$ins->error]); exit;
}
$ins->close();

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/');
$link = $base . '/?ref=' . $token . '&product=' . $product_id;

echo json_encode(['success'=>true,'token'=>$token,'link'=>$link,'percent'=>$usePercent]);
$conn->close();
?>