<?php
include '../db_config.php';
// ensure deleted column exists and exclude archived products
@ $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS deleted TINYINT(1) DEFAULT 0");
$result = $conn->query("SELECT * FROM products WHERE (deleted IS NULL OR deleted = 0) ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Products</title>
    <style>
        table { width: 90%; margin: 20px auto; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        img { width: 80px; height: auto; }
    </style>
</head>
<body>
<h2 style="text-align:center;">All Products</h2>
<table>
    <tr><th>ID</th><th>Name</th><th>Price</th><th>Image</th><th>Created</th></tr>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= number_format($row['price']) ?></td>
        <td><img src="../uploads/<?= htmlspecialchars($row['image']) ?>" alt=""></td>
        <td><?= $row['created_at'] ?></td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
