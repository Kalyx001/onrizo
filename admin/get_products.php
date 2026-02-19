<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// admin/get_products.php
include '../db_config.php';
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
// Accept filters
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? floatval($_GET['max_price']) : 0;

// ensure soft-delete column exists
@ $conn->query("ALTER TABLE products 
    ADD COLUMN IF NOT EXISTS deleted TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL");

// Ensure promotions table exists (safe-guard for older installs)
$conn->query("CREATE TABLE IF NOT EXISTS promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT 0,
    product_id INT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    budget DECIMAL(12,2) DEFAULT 0,
    duration_days INT DEFAULT 7,
    status VARCHAR(32) DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Build base query using prepared statement with dynamic WHERE clauses
$sql = "SELECT p.*, a.phone AS whatsapp_number, promo.id as promotion_id, promo.created_at as promo_created_at
    FROM products p
    LEFT JOIN admins a ON p.admin_id = a.id
    LEFT JOIN promotions promo ON promo.product_id = p.id AND promo.status = 'Active' AND DATE_ADD(promo.created_at, INTERVAL promo.duration_days DAY) >= NOW()";

$conds = [];
$params = [];
$types = "";

// category filter (case-insensitive, partial match)
if ($category !== '') {
    $conds[] = "LOWER(p.category) LIKE CONCAT('%', LOWER(?), '%')";
    $types .= "s";
    $params[] = $category;
}

// exclude soft-deleted products by default
$conds[] = "(p.deleted IS NULL OR p.deleted = 0)";



// location filter (admins' county/subcounty)
$county = isset($_GET['county']) ? trim($_GET['county']) : '';
$subcounty = isset($_GET['subcounty']) ? trim($_GET['subcounty']) : '';

if ($county !== '') {
    $conds[] = "a.county = ?";
    $types .= "s";
    $params[] = $county;
}

if ($subcounty !== '') {
    $conds[] = "a.subcounty = ?";
    $types .= "s";
    $params[] = $subcounty;
}

// max price filter
if ($max_price > 0) {
    $conds[] = "p.price <= ?";
    $types .= "d";
    $params[] = $max_price;
}

if (count($conds) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conds);
}

// promoted products first, then recent promotions, then newest products
$sql .= " ORDER BY (promo.id IS NOT NULL) DESC, promo_created_at DESC, p.id DESC";

// prepare statement
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["error" => "DB prepare failed", "db_error" => $conn->error]);
    exit;
}

// bind params dynamically if any
if (count($params) > 0) {
    $bind_names = [];
    $bind_names[] = $types;
    for ($i=0; $i<count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    if (!call_user_func_array([$stmt, 'bind_param'], $bind_names)) {
        http_response_code(500);
        echo json_encode(["error" => "bind_param failed", "db_error" => $stmt->error]);
        exit;
    }
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "execute failed", "db_error" => $stmt->error]);
    exit;
}

$result = $stmt->get_result();
if ($result === false) {
    http_response_code(500);
    echo json_encode(["error" => "get_result failed", "db_error" => $stmt->error]);
    exit;
}

$products = [];
while ($row = $result->fetch_assoc()) {
    $id = $row['id'];

    // fetch extra images
    $extraImages = [];
    $imgQ = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $imgQ->bind_param("i", $id);
    $imgQ->execute();
    $imgRes = $imgQ->get_result();
    while ($img = $imgRes->fetch_assoc()) {
        // return full path for convenience (adjust base url if different)
        $extraImages[] = "http://".$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI'])."/../".$img['image_path'];
    }
    $imgQ->close();

    // make main image absolute if needed
    if (!empty($row['image']) && !preg_match('/^https?:\/\//', $row['image'])) {
        $row['image'] = "http://".$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI'])."/../".$row['image'];
    }

    // clean phone to digits-only
    $row['whatsapp_number'] = isset($row['whatsapp_number']) && $row['whatsapp_number'] !== null
        ? preg_replace('/[^0-9]/', '', $row['whatsapp_number']) : '';

    $row['extra_images'] = $extraImages;
    $products[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($products);
