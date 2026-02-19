<?php
// edit_product.php and update_product.php combined clean version
include 'db_config.php';
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
header("Location: login.php");
exit;
}


// --- FETCH PRODUCT ---
if (!isset($_GET['id'])) {
die("❌ No product ID provided. Delete the product and upload Afresh!!");
}


$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if (!$product) die("❌ Product not found.");


// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$name = mysqli_real_escape_string($conn, $_POST['name']);
$price = floatval($_POST['price']);
$description = mysqli_real_escape_string($conn, $_POST['description']);
$category = mysqli_real_escape_string($conn, $_POST['category']);


// MAIN IMAGE UPDATE
$imageSql = '';
if (!empty($_FILES['image']['name'])) {
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
$fileName = time() . '_' . basename($_FILES['image']['name']);
move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName);
$imageSql = ", image = '$fileName'";
}


$sql = "UPDATE products SET name='$name', price='$price', description='$description', category='$category' $imageSql WHERE id=$id";
$conn->query($sql);


// ADDITIONAL IMAGES UPDATE
if (!empty($_FILES['images']['name'][0])) {
foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
if (!is_uploaded_file($tmp)) continue;
$ext = strtolower(pathinfo($_FILES['images']['name'][$k], PATHINFO_EXTENSION));
$unique = uniqid('IMG_', true) . '.' . $ext;
move_uploaded_file($tmp, $uploadDir . $unique);
$relPath = 'uploads/' . $unique;
$ins = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
$ins->bind_param("is", $id, $relPath);
$ins->execute();
$ins->close();
}
}


header("Location: edit_product.php?id=$id&updated=1");
exit;
}
?>