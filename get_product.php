<?php
// get_product.php - public endpoint to retrieve a single product by id
header('Content-Type: application/json');
include __DIR__ . '/db_config.php';

$id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
if (!$id) {
    echo json_encode(['success'=>false,'message'=>'Missing product_id']);
    exit;
}

$stmt = $conn->prepare("SELECT p.*, a.phone AS whatsapp_number FROM products p LEFT JOIN admins a ON p.admin_id = a.id WHERE p.id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success'=>false,'message'=>'Product not found']);
    exit;
}
$product = $res->fetch_assoc();
$stmt->close();

// fetch extra images
$extra = [];
$imgQ = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
$imgQ->bind_param('i', $id);
$imgQ->execute();
$imgRes = $imgQ->get_result();
while ($r = $imgRes->fetch_assoc()) {
    $extra[] = (strpos($r['image_path'], 'http') === 0) ? $r['image_path'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../' . $r['image_path']);
}
$imgQ->close();

// normalize image absolute
if (!empty($product['image']) && !preg_match('/^https?:\/\//', $product['image'])) {
    $product['image'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../' . $product['image'];
}

$product['extra_images'] = $extra;
$product['whatsapp_number'] = isset($product['whatsapp_number']) ? preg_replace('/[^0-9]/','', $product['whatsapp_number']) : '';

$conn->close();
echo json_encode(['success'=>true,'product'=>$product]);
?>