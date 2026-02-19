<?php
session_start();
include __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo json_encode(['success'=>false,'message'=>'Not authorized']); exit;
}
$admin_id = (int)($_SESSION['admin_id'] ?? 0);

$product_id = intval($_POST['product_id'] ?? 0);
$percent = is_numeric($_POST['percent'] ?? null) ? floatval($_POST['percent']) : null;

if (!$product_id || $percent === null || $percent < 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid input']); exit;
}

// ensure affiliate_percent column exists before attempting update
$colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'affiliate_percent'");
if (!$colCheck || $colCheck->num_rows === 0) {
    // If caller requested migration, attempt to add the column safely
    if (isset($_POST['migrate']) && ($_POST['migrate'] === '1' || $_POST['migrate'] === 1)) {
        $alterSql = "ALTER TABLE products ADD COLUMN affiliate_percent DECIMAL(5,2) DEFAULT NULL";
        if ($conn->query($alterSql) === TRUE) {
            // re-check
            $colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'affiliate_percent'");
            if (!$colCheck || $colCheck->num_rows === 0) {
                echo json_encode(['success'=>false,'message'=>'Failed to add affiliate_percent column (unknown error)']); exit;
            }
            // continue to update below
        } else {
            echo json_encode(['success'=>false,'message'=>'Failed to add affiliate_percent column: ' . $conn->error]); exit;
        }
    } else {
        echo json_encode(['success'=>false,'message'=>'Database not migrated: affiliate_percent column missing. Send migrate=1 to create it automatically.']); exit;
    }
}

// ensure product belongs to this admin
$stmt = $conn->prepare('SELECT id FROM products WHERE id = ? AND admin_id = ? LIMIT 1');
$stmt->bind_param('ii', $product_id, $admin_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success'=>false,'message'=>'Product not found or not owned by you']); exit;
}

$u = $conn->prepare('UPDATE products SET affiliate_percent = ? WHERE id = ?');
$u->bind_param('di', $percent, $product_id);
$ok = $u->execute();
if ($ok) {
    echo json_encode(['success'=>true,'message'=>'Affiliate percent updated']);
} else {
    echo json_encode(['success'=>false,'message'=>$conn->error]);
}
$u->close();
$stmt->close();
?>
