<?php
include '../db_config.php';
$affiliate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($affiliate_id <= 0) { die('Invalid affiliate'); }

$affiliate = null;
$stmt = $conn->prepare("SELECT id, name, email, phone, referral_code, balance, status, created_at FROM affiliates WHERE id = ?");
$stmt->bind_param('i', $affiliate_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res) { $affiliate = $res->fetch_assoc(); }
$stmt->close();
if (!$affiliate) { die('Affiliate not found'); }

// Metrics
$stmt = $conn->prepare("SELECT COUNT(*) as clicks, COALESCE(SUM(commission),0) as commissions FROM affiliate_clicks WHERE affiliate_id = ?");
$stmt->bind_param('i', $affiliate_id);
$stmt->execute();
$res = $stmt->get_result();
$metrics = $res ? $res->fetch_assoc() : ['clicks'=>0,'commissions'=>0];
$stmt->close();

// Recent clicks
$recent_clicks = [];
$stmt = $conn->prepare("SELECT ac.id, ac.product_id, ac.commission, ac.status, ac.created_at, p.name as product_name
    FROM affiliate_clicks ac LEFT JOIN products p ON ac.product_id = p.id
    WHERE ac.affiliate_id = ? ORDER BY ac.created_at DESC LIMIT 10");
$stmt->bind_param('i', $affiliate_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $recent_clicks[] = $row; }
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Affiliate Details</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="admin_style.css">
<style>.container{max-width:1100px;margin:24px auto;}</style>
</head>
<body>
<header>
  <h1>🤝 Affiliate Details</h1>
  <nav>
    <a href="store_dashboard.php">← Back</a>
  </nav>
</header>

<div class="container">
  <div class="content-section">
    <h5><?php echo htmlspecialchars($affiliate['name']); ?> <small style="color:#777;">(<?php echo htmlspecialchars($affiliate['email']); ?>)</small></h5>
    <p>Phone: <?php echo htmlspecialchars($affiliate['phone'] ?? 'N/A'); ?> • Code: <code><?php echo htmlspecialchars($affiliate['referral_code']); ?></code></p>
    <p>Status: <?php echo htmlspecialchars($affiliate['status']); ?> • Balance: KES <?php echo number_format((float)$affiliate['balance'],0); ?></p>
  </div>

  <div class="analytics-grid">
    <div class="analytics-card"><h6>🖱 Clicks</h6><div class="value"><?php echo (int)$metrics['clicks']; ?></div></div>
    <div class="analytics-card revenue"><h6>💰 Commissions</h6><div class="value">KES <?php echo number_format((float)$metrics['commissions'],0); ?></div></div>
  </div>

  <div class="content-section">
    <h5>Recent Clicks</h5>
    <?php if (!empty($recent_clicks)): ?>
      <div class="table-wrapper">
        <table class="table">
          <thead><tr><th>ID</th><th>Product</th><th>Commission</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($recent_clicks as $c): ?>
            <tr>
              <td>#<?php echo (int)$c['id']; ?></td>
              <td><?php echo htmlspecialchars($c['product_name'] ?? ''); ?></td>
              <td>KES <?php echo number_format((float)$c['commission'],0); ?></td>
              <td><?php echo htmlspecialchars($c['status']); ?></td>
              <td><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="color:#999;">No clicks yet.</p>
    <?php endif; ?>
  </div>
</div>
<script src="../loader.js"></script>
</body>
</html>
