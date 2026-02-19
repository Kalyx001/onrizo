<?php
session_start();
include 'db_config.php';
header('Content-Type: application/json');

// Get email from request (can be from session or POST/GET)
$email = isset($_POST['email']) ? trim($_POST['email']) : (isset($_GET['email']) ? trim($_GET['email']) : '');
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Product ID required']);
    exit;
}

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

// Get affiliate by email
$stmt = $conn->prepare("SELECT id, referral_code, name, status FROM affiliates WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$affiliate = $result->fetch_assoc();
$stmt->close();

if (!$affiliate) {
    echo json_encode(['success' => false, 'message' => 'Affiliate not found', 'type' => 'not_registered']);
    exit;
}

if ($affiliate['status'] !== 'active') {
    echo json_encode(['success' => false, 'message' => 'Affiliate account is inactive']);
    exit;
}

$affiliate_id = (int)$affiliate['id'];

// Get product details
$prod_stmt = $conn->prepare("SELECT id, name, price FROM products WHERE id = ? LIMIT 1");
$prod_stmt->bind_param('i', $product_id);
$prod_stmt->execute();
$prod_result = $prod_stmt->get_result();
$product = $prod_result->fetch_assoc();
$prod_stmt->close();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

// Generate affiliate link
$base_url = rtrim((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/');
$affiliate_link = $base_url . '/?ref=' . $affiliate['referral_code'] . '&product=' . $product_id;

// Check if link already exists for this affiliate+product combination
$check_stmt = $conn->prepare("SELECT id FROM affiliate_clicks WHERE affiliate_id = ? AND product_id = ? AND status = 'link_generated' LIMIT 1");
$check_stmt->bind_param('ii', $affiliate_id, $product_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$existing_link = $check_result->fetch_assoc();
$check_stmt->close();

if (!$existing_link) {
    // Create new link record only if one doesn't exist
    $log_stmt = $conn->prepare("INSERT INTO affiliate_clicks (affiliate_id, product_id, product_name, status) VALUES (?, ?, ?, 'link_generated')");
    $log_stmt->bind_param('iis', $affiliate_id, $product_id, $product['name']);
    $log_stmt->execute();
    $log_stmt->close();
}

echo json_encode([
    'success' => true,
    'affiliate_link' => $affiliate_link,
    'product_name' => $product['name'],
    'product_price' => $product['price'],
    'affiliate_name' => $affiliate['name'],
    'referral_code' => $affiliate['referral_code']
]);
?>
