<?php
session_start();
include '../db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success'=>false,'message'=>'Not authenticated']);
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
$product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;
$budget = isset($data['budget']) ? floatval($data['budget']) : 0;
$duration = isset($data['duration']) ? (int)$data['duration'] : 0;
$title = isset($data['title']) ? trim($data['title']) : '';
$message = isset($data['message']) ? trim($data['message']) : '';

if (!$product_id || $budget <= 0 || $duration <= 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid input']);
    exit;
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);

// Ensure promotions table exists
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

$stmt = $conn->prepare("INSERT INTO promotions (admin_id, product_id, title, budget, duration_days, status) VALUES (?, ?, ?, ?, ?, 'Active')");
// types: i (admin_id), i (product_id), s (title), d (budget), i (duration_days)
$stmt->bind_param('isdi', $admin_id, $product_id, $title, $budget, $duration);
$ok = $stmt->execute();

if ($ok) echo json_encode(['success'=>true]);
else echo json_encode(['success'=>false,'message'=>$conn->error]);

?>