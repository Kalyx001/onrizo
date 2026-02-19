<?php
header('Content-Type: application/json');
session_start();
include '../db_config.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$admin_id = (int)($_SESSION['admin_id'] ?? 0);

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Get order details (full order)
$orderQuery = "SELECT * FROM orders WHERE id = ?";
$stmt = $conn->prepare($orderQuery);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$orderResult = $stmt->get_result();

if ($orderResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$order = $orderResult->fetch_assoc();

// Get only the items that belong to this admin (so sellers see only their items)
$itemsQuery = "SELECT oi.*, p.admin_id, p.name as product_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ? AND p.admin_id = ?";
$stmt = $conn->prepare($itemsQuery);
$stmt->bind_param("ii", $order_id, $admin_id);
$stmt->execute();
$itemsResult = $stmt->get_result();

$items = [];
$admin_amount = 0;
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
    $admin_amount += (float)$item['subtotal'];
}

echo json_encode([
    'success' => true,
    'order' => $order,
    'items' => $items,
    'admin_amount' => $admin_amount
]);

$conn->close();
?>
