<?php
// admin/delete_product.php - soft-delete product to preserve analytics
include __DIR__ . '/../db_config.php';
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo "❌ Not authorized";
    exit;
}

if (!isset($_GET['id'])) {
    echo "❌ No product ID provided.";
    exit;
}

$id = intval($_GET['id']);
if ($id <= 0) { echo "❌ Invalid product ID."; exit; }

// Ensure soft-delete columns exist in a compatible way
$colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'deleted'");
if ($colCheck && $colCheck->num_rows == 0) {
    // attempt to add columns one by one for compatibility
    $conn->query("ALTER TABLE products ADD COLUMN deleted TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE products ADD COLUMN deleted_at DATETIME NULL");
    $conn->query("ALTER TABLE products ADD COLUMN deleted_by INT DEFAULT 0");
}

$admin_id = intval($_SESSION['admin_id'] ?? 0);

// soft-delete: mark product as deleted; do not remove rows that analytics rely on
$u = $conn->prepare('UPDATE products SET deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?');
if ($u === false) {
    echo "❌ Error preparing archive statement: " . $conn->error;
    $conn->close();
    exit;
}
$u->bind_param('ii', $admin_id, $id);
if ($u->execute()) {
    echo "✅ Product archived (soft-deleted). Analytics and orders remain intact.";
} else {
    echo "❌ Error archiving product: " . $u->error;
}
$u->close();
$conn->close();
?>
