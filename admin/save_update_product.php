<?php
// save_update_product.php - safer handler for edit_product.php uploads and updates
include __DIR__ . '/../db_config.php';
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) die('Invalid product ID');

// fetch current product
$stmt = $conn->prepare('SELECT admin_id, image FROM products WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) die('Product not found');

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
if ($row['admin_id'] != 0 && $row['admin_id'] != $admin_id) {
    die('Not authorized');
}

$name = trim($_POST['name'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$description = trim($_POST['description'] ?? '');
$category = trim($_POST['category'] ?? '');

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$allowed = ['jpg','jpeg','png','gif'];

$newImagePath = null;
if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, $allowed)) {
        $unique = uniqid('IMG_', true) . '.' . $ext;
        $target = $uploadDir . $unique;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $newImagePath = 'uploads/' . $unique;
            // cleanup old image? keep for safety
        }
    }
}

// update product
if ($newImagePath) {
    $u = $conn->prepare('UPDATE products SET name=?, price=?, description=?, category=?, image=? WHERE id=?');
    $u->bind_param('sdsssi', $name, $price, $description, $category, $newImagePath, $id);
} else {
    $u = $conn->prepare('UPDATE products SET name=?, price=?, description=?, category=? WHERE id=?');
    $u->bind_param('sdssi', $name, $price, $description, $category, $id);
}
$u->execute();
$u->close();

// handle deletion of selected additional images
if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
    foreach ($_POST['delete_images'] as $delId) {
        $delId = intval($delId);
        if ($delId <= 0) continue;
        $q = $conn->prepare('SELECT image_path FROM product_images WHERE id = ? AND product_id = ? LIMIT 1');
        $q->bind_param('ii', $delId, $id);
        $q->execute();
        $r = $q->get_result();
        if ($r && $rowImg = $r->fetch_assoc()) {
            $imgPath = __DIR__ . '/../' . $rowImg['image_path'];
            if (is_file($imgPath)) @unlink($imgPath);
            $del = $conn->prepare('DELETE FROM product_images WHERE id = ? AND product_id = ?');
            $del->bind_param('ii', $delId, $id);
            $del->execute();
            $del->close();
        }
        $q->close();
    }
}

// additional images
if (!empty($_FILES['images']['name'][0])) {
    foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
        if (!is_uploaded_file($tmp)) continue;
        $ext = strtolower(pathinfo($_FILES['images']['name'][$k], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;
        $unique = uniqid('IMG_', true) . '.' . $ext;
        $target = $uploadDir . $unique;
        if (move_uploaded_file($tmp, $target)) {
            $rel = 'uploads/' . $unique;
            $ins = $conn->prepare('INSERT INTO product_images (product_id, image_path) VALUES (?, ?)');
            $ins->bind_param('is', $id, $rel);
            $ins->execute();
            $ins->close();
        }
    }
}

header('Location: edit_product.php?id=' . $id . '&updated=1');
exit;
?>
