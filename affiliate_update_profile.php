<?php
session_start();
include 'db_config.php';
header('Content-Type: application/json');

// Check if affiliate is logged in
if (!isset($_SESSION['affiliate_logged_in']) || $_SESSION['affiliate_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only']);
    exit;
}

$affiliate_id = (int)($_SESSION['affiliate_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$bank_details = trim($_POST['bank_details'] ?? '');

if (empty($name) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Name and phone are required']);
    exit;
}

// Update profile
$stmt = $conn->prepare("UPDATE affiliates SET name = ?, phone = ?, bank_details = ? WHERE id = ?");
$stmt->bind_param('sssi', $name, $phone, $bank_details, $affiliate_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    $_SESSION['affiliate_name'] = $name;
    echo json_encode(['success' => true, 'message' => 'Profile updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
?>
