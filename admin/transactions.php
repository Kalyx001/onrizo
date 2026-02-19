<?php
session_start();
include '../db_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Ensure orders has transaction_id column (MySQL 8+ supports IF NOT EXISTS)
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(255) DEFAULT NULL");

// Fetch recent order transactions
$ordersRes = $conn->query("SELECT id, customer_name, total_amount, order_date, transaction_id, status FROM orders ORDER BY order_date DESC LIMIT 100");
$orders = [];
if ($ordersRes) while($r = $ordersRes->fetch_assoc()) $orders[] = $r;

// Fetch withdrawal transactions
$withdrawRes = $conn->query("SELECT id, admin_id, amount, destination, transaction_id, status, requested_at, processed_at FROM withdrawals ORDER BY requested_at DESC LIMIT 100");
$withdrawals = [];
if ($withdrawRes) while($r = $withdrawRes->fetch_assoc()) $withdrawals[] = $r;

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Transactions - Onrizo Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="admin_style.css">
<style>.container{max-width:1100px; margin:24px auto} .card{border-radius:8px}</style>
</head>
<body>
  <div id="pageLoader" class="active">
    <div>
      <div class="spinner"></div>
      <div class="loader-text">Loading...</div>
    </div>
  </div>

<header>
    <h1>💳 Transactions</h1>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="withdrawals.php">Withdrawals</a>
    </nav>
</header>

<div class="container">
    <p>Combined recent payments and withdrawal transaction IDs.</p>

    <div class="row">
        <div class="col-md-6">
            <div class="card p-3 mb-3">
                <h5>Order Transactions</h5>
                <?php if (!empty($orders)): ?>
                <table class="table table-sm">
                    <thead><tr><th>#</th><th>Customer</th><th>Amount</th><th>Txn ID</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach($orders as $o): ?>
                        <tr>
                            <td><?= htmlspecialchars($o['id']) ?></td>
                            <td><?= htmlspecialchars($o['customer_name']) ?></td>
                            <td>KES <?= number_format($o['total_amount'],0) ?></td>
                            <td><?= htmlspecialchars($o['transaction_id'] ?? '') ?></td>
                            <td><?= htmlspecialchars($o['status']) ?></td>
                            <td><?= htmlspecialchars($o['order_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>No orders found.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-3 mb-3">
                <h5>Withdrawal Transactions</h5>
                <?php if (!empty($withdrawals)): ?>
                <table class="table table-sm">
                    <thead><tr><th>#</th><th>Admin</th><th>Amount</th><th>Txn ID</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach($withdrawals as $w): ?>
                        <tr>
                            <td><?= htmlspecialchars($w['id']) ?></td>
                            <td><?= htmlspecialchars($w['admin_id']) ?></td>
                            <td>KES <?= number_format($w['amount'],0) ?></td>
                            <td><?= htmlspecialchars($w['transaction_id'] ?? '') ?></td>
                            <td><?= htmlspecialchars($w['status']) ?></td>
                            <td><?= htmlspecialchars($w['processed_at'] ?? $w['requested_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>No withdrawals found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <p><a href="dashboard.php">Back to dashboard</a></p>
</div>

<script src="../loader.js"></script>
</body>
</html>
