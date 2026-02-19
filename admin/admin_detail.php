<?php
include '../db_config.php';
$admin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($admin_id <= 0) { die('Invalid admin'); }

$admin = null;
$stmt = $conn->prepare("SELECT id, name, email, created_at FROM admins WHERE id = ?");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res) { $admin = $res->fetch_assoc(); }
$stmt->close();
if (!$admin) { die('Admin not found'); }

// Orders and revenue
$stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) as total_orders, COALESCE(SUM(oi.subtotal),0) as revenue
    FROM orders o JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id WHERE p.admin_id = ?");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$metrics = $res ? $res->fetch_assoc() : ['total_orders'=>0,'revenue'=>0];
$stmt->close();

// Products count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE admin_id = ? AND deleted = 0");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$products_count = $res ? (int)$res->fetch_assoc()['count'] : 0;
$stmt->close();

// Pending orders
$stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) as count FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.admin_id = ? AND o.status = 'Pending'");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$pending_orders = $res ? (int)$res->fetch_assoc()['count'] : 0;
$stmt->close();

// Recent orders
$recent_orders = [];
$stmt = $conn->prepare("SELECT DISTINCT o.id, o.customer_name, o.total_amount, o.status, o.order_date
    FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id
    WHERE p.admin_id = ? ORDER BY o.order_date DESC LIMIT 10");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $recent_orders[] = $row; }
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Details</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="admin_style.css">
<style>.container{max-width:1100px;margin:24px auto;}</style>
</head>
<body>
<header>
  <h1>👨‍💼 Admin Details</h1>
  <nav>
    <a href="store_dashboard.php">← Back</a>
  </nav>
</header>

<div class="container">
  <div class="content-section">
    <h5><?php echo htmlspecialchars($admin['name'] ?? 'Admin'); ?> <small style="color:#777;">(<?php echo htmlspecialchars($admin['email']); ?>)</small></h5>
    <p>Joined: <?php echo date('d M Y', strtotime($admin['created_at'])); ?></p>
  </div>

  <div class="analytics-grid">
    <div class="analytics-card"><h6>📦 Total Orders</h6><div class="value"><?php echo (int)$metrics['total_orders']; ?></div></div>
    <div class="analytics-card revenue"><h6>💰 Revenue</h6><div class="value">KES <?php echo number_format((float)$metrics['revenue'],0); ?></div></div>
    <div class="analytics-card"><h6>🛍 Products</h6><div class="value"><?php echo $products_count; ?></div></div>
    <div class="analytics-card pending"><h6>⏳ Pending Orders</h6><div class="value"><?php echo $pending_orders; ?></div></div>
  </div>

  <div class="content-section">
    <h5>Recent Orders</h5>
    <?php if (!empty($recent_orders)): ?>
      <div class="table-wrapper">
        <table class="table">
          <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($recent_orders as $o): ?>
            <tr>
              <td>#<?php echo (int)$o['id']; ?></td>
              <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
              <td>KES <?php echo number_format($o['total_amount'],0); ?></td>
              <td><?php echo htmlspecialchars($o['status']); ?></td>
              <td><?php echo date('d M Y', strtotime($o['order_date'])); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="color:#999;">No orders yet.</p>
    <?php endif; ?>
  </div>
</div>
<script src="../loader.js"></script>
</body>
</html>
