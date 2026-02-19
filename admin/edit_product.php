<?php
include '../db_config.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("❌ Invalid request.");
}

$id = intval($_GET['id']);
$query = $conn->prepare("SELECT * FROM products WHERE id = ?");
$query->bind_param("i", $id);
$query->execute();
$result = $query->get_result();

if ($result->num_rows == 0) {
    die("❌ Product not found.");
}

$product = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - Orizo Admin</title>
    <link rel="stylesheet" href="admin_style.css">

    <style>
        .file-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .file-label {
            padding: 8px 12px;
            background: #007bff;
            color: #fff;
            cursor: pointer;
            border-radius: 5px;
        }
        .file-name {
            color: #555;
            font-size: 14px;
        }
        .preview-img {
            width: 120px;
            margin-top: 10px;
            border-radius: 8px;
        }
        /* Improved form layout */
        .product-form { max-width: 900px; margin: 20px auto; padding: 20px; background:#fff;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.08);} 
        .product-form h2 { margin-top:0; }
        .form-row { display:flex; gap:16px; align-items:flex-start; }
        .form-col { flex:1; }
        label { display:block; margin:10px 0 6px; font-weight:600; }
        input[type="text"], input[type="number"], textarea, select { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; }
        .thumb-grid { display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }
        .thumb-item { position:relative; width:120px; }
        .thumb-item img { width:100%; height:80px; object-fit:cover; border-radius:6px; border:1px solid #eee; }
        .thumb-item .remove-checkbox { position:absolute; top:6px; right:6px; background:rgba(255,255,255,0.85); padding:2px 4px; border-radius:4px; }
    </style>
</head>
<body>
  <div id="pageLoader" class="active">
    <div>
      <div class="spinner"></div>
      <div class="loader-text">Loading...</div>
    </div>
  </div>

<header>
    <h1>✏️ Edit Product</h1>
    <nav>
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="view_products.php">📦 View Products</a>
    </nav>
</header>

<main class="form-container">

    <form action="save_update_product.php" method="POST" enctype="multipart/form-data" class="product-form">
        <h2>Update Product Details</h2>

        <input type="hidden" name="id" value="<?= $product['id'] ?>">

        <label>Product Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required maxlength="45">

        <label>Price (KES)</label>
        <input type="number" name="price" step="0.01" value="<?= $product['price'] ?>" required onwheel="this.blur()">

        <label>Description</label>
        <textarea name="description"><?= htmlspecialchars($product['description']) ?></textarea>

        <label>Current Main Image</label>
        <?php if (!empty($product['image'])): ?>
            <img src="../<?= $product['image'] ?>" class="preview-img">
        <?php else: ?>
            <p>No image uploaded</p>
        <?php endif; ?>

        <?php
        // fetch additional images
        $imgs = [];
        $iq = $conn->prepare('SELECT id, image_path FROM product_images WHERE product_id = ?');
        $iq->bind_param('i', $product['id']);
        $iq->execute();
        $ir = $iq->get_result();
        while ($im = $ir->fetch_assoc()) { $imgs[] = $im; }
        $iq->close();
        ?>

        <label>Additional Images</label>
        <?php if (count($imgs) > 0): ?>
            <div class="thumb-grid">
                <?php foreach ($imgs as $im): ?>
                    <div class="thumb-item">
                        <img src="../<?= htmlspecialchars($im['image_path']) ?>" alt="">
                        <label class="remove-checkbox"><input type="checkbox" name="delete_images[]" value="<?= $im['id'] ?>"> remove</label>
                    </div>
                <?php endforeach; ?>
            </div>
            <p style="font-size:13px;color:#666;margin-top:8px;">Check images to remove, then upload replacements below if needed.</p>
        <?php else: ?>
            <p>No additional images</p>
        <?php endif; ?>

        <label>Replace Main Image (Optional)</label>
        <div class="file-group">
            <input type="file" id="newImage" name="image" accept="image/*" hidden>
            <label for="newImage" class="file-label">📁 Choose Main Image</label>
            <span id="newFileName" class="file-name">No file chosen</span>
        </div>

        <label>Upload Additional Images (optional)</label>
        <div class="file-group">
            <input type="file" id="addImages" name="images[]" accept="image/*" multiple hidden>
            <label for="addImages" class="file-label">📁 Add Images</label>
            <span id="addFilesName" class="file-name">No files chosen</span>
        </div>

        <button type="submit" class="btn primary">💾 Save Changes</button>
    </form>

</main>

<script>
// Show selected filename
document.getElementById('newImage').addEventListener('change', function () {
    let fileName = this.files.length ? this.files[0].name : "No file chosen";
    document.getElementById('newFileName').textContent = fileName;
});
document.getElementById('addImages').addEventListener('change', function () {
    let fileCount = this.files.length;
    document.getElementById('addFilesName').textContent = fileCount ? `${fileCount} file(s) selected` : 'No files chosen';
});
</script>

  <script src="../loader.js"></script>
</body>
</html>
