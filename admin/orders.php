<?php
session_start();
include '../db_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);

// Get orders that include products owned by this admin (show admin's portion per order)
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$orders = [];
if ($statusFilter) {
  $stmt = $conn->prepare("SELECT o.*, COALESCE(SUM(oi.subtotal),0) as admin_amount
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.admin_id = ? AND o.status = ?
    GROUP BY o.id
    ORDER BY o.order_date DESC
    LIMIT 100");
  $stmt->bind_param('is', $admin_id, $statusFilter);
} else {
  $stmt = $conn->prepare("SELECT o.*, COALESCE(SUM(oi.subtotal),0) as admin_amount
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.admin_id = ?
    GROUP BY o.id
    ORDER BY o.order_date DESC
    LIMIT 100");
  $stmt->bind_param('i', $admin_id);
}
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
  }
}
$stmt->close();

// For each order, load any affiliate earnings that belong to this admin's products
if (!empty($orders)) {
  foreach ($orders as &$o) {
    $o['affiliates'] = [];
    // Get affiliate clicks for products owned by this admin with status confirmed
    $stmt2 = $conn->prepare("SELECT ac.id as earning_id, ac.affiliate_id, ac.commission as amount, af.name, af.email, af.phone, af.referral_code
      FROM affiliate_clicks ac
      JOIN affiliates af ON ac.affiliate_id = af.id
      JOIN products p ON ac.product_id = p.id
      WHERE ac.status = 'confirmed' AND p.admin_id = ?
      ORDER BY ac.created_at DESC
      LIMIT 100");
    if ($stmt2) {
      $stmt2->bind_param('i', $admin_id);
      $stmt2->execute();
      $ar = $stmt2->get_result();
      if ($ar) {
        while ($a = $ar->fetch_assoc()) {
          $o['affiliates'][] = $a;
        }
      }
      $stmt2->close();
    }
    $o['affiliate_total'] = array_sum(array_column($o['affiliates'], 'amount'));
  }
  unset($o);
}

$totalOrders = count($orders);
$totalRevenue = array_sum(array_column($orders, 'admin_amount'));
$pendingOrders = count(array_filter($orders, function($o) { return $o['status'] === 'Pending'; }));
$completedOrders = count(array_filter($orders, function($o) { return $o['status'] === 'Completed'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Orders - Onrizo Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.sidebar {
    width: 250px;
    background: #fff;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    border-right: 1px solid #e0e0e0;
    padding-top: 20px;
    transition: width 0.3s;
    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
    overflow-y: auto;
}

.sidebar.collapsed { width: 70px; }

.sidebar .brand { 
    font-size: 18px; 
    font-weight: bold; 
    padding: 0 20px 30px;
    color: #667eea;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar.collapsed .brand span {
    display: none;
}

.sidebar ul { 
    list-style: none; 
    padding-left: 0; 
}

.sidebar ul li a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: #444;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}

.sidebar.collapsed ul li a span {
    display: none;
}

.sidebar ul li a:hover { 
    background: #f5f5f5;
    border-left-color: #667eea;
    color: #667eea;
}

.sidebar ul li a.active {
    background: #667eea;
    color: white;
    border-left-color: #667eea;
}

.main {
    margin-left: 250px;
    padding: 30px;
    width: 100%;
    transition: margin-left 0.3s;
    min-height: 100vh;
}

.main.expanded { margin-left: 70px; }

.topbar {
    background: #fff;
    padding: 20px 25px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.topbar h4 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.menu-toggle { 
    font-size: 24px; 
    cursor: pointer; 
    color: #667eea;
    transition: transform 0.3s;
}

.menu-toggle:hover {
    transform: scale(1.1);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stat-card h6 {
    color: #999;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.stat-card .value {
    font-size: 28px;
    font-weight: bold;
    color: #667eea;
}

.stat-card.orders .value { color: #667eea; }
.stat-card.revenue .value { color: #28a745; }
.stat-card.pending .value { color: #ff9800; }
.stat-card.completed .value { color: #4caf50; }

.table-wrapper {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.table-wrapper h5 {
    margin-bottom: 20px;
    color: #333;
    font-weight: 600;
}

.filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filters a, .filters button {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    color: #666;
    transition: all 0.3s;
}

.filters a.active, .filters button.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.filters a:hover, .filters button:hover {
    border-color: #667eea;
    color: #667eea;
}

.table {
    font-size: 14px;
}

.table thead {
    background: #f8f9fa;
    border-top: 2px solid #e0e0e0;
    border-bottom: 2px solid #e0e0e0;
}

.table thead th {
    color: #667eea;
    font-weight: 600;
    padding: 15px;
    border: none;
}

.table tbody tr {
    border-bottom: 1px solid #e0e0e0;
    transition: background 0.2s;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.table tbody td {
    padding: 15px;
    vertical-align: middle;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.actions-cell {
    display: flex;
    gap: 8px;
}

.btn-small {
    padding: 6px 12px;
    font-size: 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-view {
    background: #667eea;
    color: white;
}

.btn-view:hover {
    background: #5568d3;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-complete {
    background: #28a745;
    color: white;
}

.btn-complete:hover {
    background: #218838;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.btn-delete {
    background: #dc3545;
    color: white;
}

.btn-delete:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: #999;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

/* Modal */
.modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-backdrop.show {
    display: block;
}

.order-modal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    z-index: 1001;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}

.order-modal.show {
    display: block;
}

.order-modal h3 {
    color: #667eea;
    margin-bottom: 20px;
    border-bottom: 2px solid #667eea;
    padding-bottom: 15px;
}

.order-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.info-item h6 {
    color: #999;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.info-item p {
    color: #333;
    font-weight: 500;
    margin: 0;
}

.items-list {
    border-top: 1px solid #e0e0e0;
    padding-top: 20px;
    margin-bottom: 20px;
}

.items-list h6 {
    color: #667eea;
    font-weight: 600;
    margin-bottom: 15px;
}

.item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.item-name {
    flex: 1;
    color: #333;
}

.item-qty {
    color: #999;
    font-size: 12px;
}

.item-total {
    color: #667eea;
    font-weight: 600;
}

.modal-footer {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    border-top: 1px solid #e0e0e0;
    padding-top: 20px;
}

.modal-footer button {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.modal-close {
    background: #f0f0f0;
    color: #333;
}

.modal-close:hover {
    background: #e0e0e0;
}

@media (max-width: 768px) {
  /* Hide sidebar completely on small screens to maximize space */
  .sidebar { display: none !important; }

  .main { margin-left: 0; padding: 18px; }

  .stats-grid { grid-template-columns: 1fr; }

  .order-modal { width: 95%; max-width: none; }

  /* Convert table rows into readable card blocks */
  .table { width: 100%; }
  .table thead { display: none; }
  .table tbody tr {
    display: block;
    margin-bottom: 12px;
    border: 1px solid #e9e9e9;
    border-radius: 8px;
    padding: 10px;
    background: #fff;
  }
  .table tbody td {
    display: flex;
    justify-content: space-between;
    padding: 8px 6px;
    border: none;
    align-items: center;
  }
  .table tbody td::before {
    content: attr(data-label) ": ";
    font-weight: 600;
    color: #666;
    margin-right: 8px;
    white-space: nowrap;
  }
  .actions-cell { flex-direction: row; gap: 8px; }
  .actions-cell .btn-small { padding: 8px 10px; font-size: 13px; }

  /* Mobile menu toggle: hide desktop toggle and show mobile-specific toggle */
  .menu-toggle { display: none; }
  .mobile-menu-toggle { display: inline-block; color: #667eea; }

  /* Mobile nav overlay */
  .mobile-nav { display: none; position: fixed; top: 70px; left: 10px; right: 10px; background: #fff; z-index: 1002; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); }
  .mobile-nav.show { display: block; }
  .mobile-nav ul { list-style: none; margin: 0; padding: 12px; }
  .mobile-nav ul li { padding: 10px 0; border-bottom: 1px solid #f1f1f1; }
  .mobile-nav ul li a { color: #333; text-decoration: none; font-weight: 600; display: block; padding: 6px 8px; }
}
</style>
</head>
<body>
  <div id="pageLoader" class="active">
    <div>
      <div class="spinner"></div>
      <div class="loader-text">Loading...</div>
    </div>
  </div>

<div class="sidebar" id="sidebar">
  <div class="brand">
    <span style="font-size: 24px;">📦</span>
    <span>Onrizo</span>
  </div>
  <ul>
    <li><a href="dashboard.php"><span style="font-size: 18px;">📊</span> <span>Dashboard</span></a></li>
    <li><a href="orders.php" class="active"><span style="font-size: 18px;">📦</span> <span>Orders</span></a></li>
    <li><a href="view_products.php"><span style="font-size: 18px;">🛍</span> <span>Products</span></a></li>
    <li><a href="add_product.php"><span style="font-size: 18px;">➕</span> <span>Add Product</span></a></li>
    <li><a href="logout.php"><span style="font-size: 18px;">🚪</span> <span>Logout</span></a></li>
  </ul>
</div>

<div class="main" id="main">
    <div class="topbar">
      <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
        <div style="display:flex; align-items:center;">
          <span class="menu-toggle" onclick="toggleMenu()">☰</span>
          <h4 style="display: inline-block; margin-left: 15px;">Orders Management</h4>
        </div>
        <!-- Mobile menu toggle (shown only on small screens) -->
        <span id="mobileMenuToggle" class="mobile-menu-toggle" onclick="toggleMobileNav()" style="display:none; font-size:22px; cursor:pointer;">☰</span>
      </div>
    </div>

    <!-- Mobile navigation overlay (hidden by default) -->
    <div id="mobileNav" class="mobile-nav" aria-hidden="true">
      <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="orders.php">Orders</a></li>
        <li><a href="view_products.php">Products</a></li>
        <li><a href="add_product.php">Add Product</a></li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
    </div>

  <!-- Stats Cards -->
  <div class="stats-grid">
    <div class="stat-card orders">
      <h6>Total Orders</h6>
      <div class="value"><?= $totalOrders ?></div>
    </div>
    <div class="stat-card revenue">
      <h6>Total Revenue</h6>
      <div class="value">KES <?= number_format($totalRevenue, 0) ?></div>
    </div>
    <div class="stat-card pending">
      <h6>Pending Orders</h6>
      <div class="value"><?= $pendingOrders ?></div>
    </div>
    <div class="stat-card completed">
      <h6>Completed Orders</h6>
      <div class="value"><?= $completedOrders ?></div>
    </div>
  </div>

  <!-- Table -->
  <div class="table-wrapper">
      <h5>📋 All Orders</h5>
      
      <div class="filters">
        <a href="orders.php" <?= !$statusFilter ? 'class="active"' : '' ?>>All Orders</a>
        <a href="?status=Pending" <?= $statusFilter === 'Pending' ? 'class="active"' : '' ?>>Pending</a>
        <a href="?status=Completed" <?= $statusFilter === 'Completed' ? 'class="active"' : '' ?>>Completed</a>
        <a href="?status=Cancelled" <?= $statusFilter === 'Cancelled' ? 'class="active"' : '' ?>>Cancelled</a>
      </div>

      <?php if (!empty($orders)): ?>
      <div style="overflow-x: auto;">
      <table class="table">
      <thead>
      <tr>
              <th>#</th>
              <th>Customer</th>
              <th>Contact</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
          </tr>
          </thead>
          <tbody>
      <?php $i = 1; foreach ($orders as $order): ?>
      <tr data-order-id="<?= (int)$order['id'] ?>" data-affiliates='<?= htmlspecialchars(json_encode($order['affiliates'] ?? []), ENT_QUOTES) ?>'>
              <td data-label="#"><strong><?= $i++ ?></strong></td>
              <td data-label="Customer"><?= htmlspecialchars($order['customer_name']) ?></td>
              <td data-label="Contact">
                <div style="font-size: 12px; color: #666;">
                  <div>📧 <?= htmlspecialchars($order['customer_email']) ?></div>
                  <div>📱 <?= htmlspecialchars($order['customer_phone']) ?></div>
                </div>
              </td>
              <?php
                $admin_amount = floatval($order['admin_amount'] ?? 0);
                $affiliate_total = floatval($order['affiliate_total'] ?? 0);
                // When order is completed show admin net after affiliate split, otherwise show gross admin amount
                if ($order['status'] === 'Completed') {
                    $displayAmount = max(0, $admin_amount - $affiliate_total);
                } else {
                    $displayAmount = $admin_amount;
                }
              ?>
              <td data-label="Amount">
                <strong style="color: #28a745;">KES <?= number_format($displayAmount, 0) ?></strong>
                <?php if ($affiliate_total > 0): ?>
                  <div style="font-size:12px;color:#666;">(Affiliate: KES <?= number_format($affiliate_total,0) ?>)</div>
                <?php endif; ?>
              </td>
              <td data-label="Status">
                <?php
                $status = $order['status'];
                $statusClass = 'status-pending';
                if ($status === 'Completed') $statusClass = 'status-completed';
                if ($status === 'Cancelled') $statusClass = 'status-cancelled';
                ?>
                <span class="status-badge <?= $statusClass ?>"><?= $status ?></span>
              </td>
              <td><small><?= date('M d, Y H:i', strtotime($order['order_date'])) ?></small></td>
              <td>
                  <div class="actions-cell">
                  <button class="btn-small btn-view" onclick="viewOrder(<?= $order['id'] ?>)">View</button>
                  <?php if ($order['status'] === 'Pending'): ?>
                  <button class="btn-small btn-complete" onclick="completeOrder(<?= $order['id'] ?>)">Complete</button>
                  <?php endif; ?>
                  <?php if (!empty($order['affiliates'])): ?>
                    <?php if (count($order['affiliates']) === 1):
                        $a = $order['affiliates'][0];
                        $label = htmlspecialchars(($a['name'] ?? 'Affiliate') . ' - KES ' . number_format($a['amount'] ?? 0,0));
                    else:
                        $label = 'Affiliates (' . count($order['affiliates']) . ')';
                    endif; ?>
                    <button class="btn-small" style="background:#6f42c1;color:#fff;" onclick="openAffiliateModal(<?= $order['id'] ?>)"><?= $label ?></button>
                  <?php endif; ?>
                </div>
              </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
      </table>
      </div>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-state-icon">📭</div>
          <p>No orders found</p>
        </div>
      <?php endif; ?>
  </div>
</div>

<!-- Modal for viewing order -->
<div class="modal-backdrop" id="orderModalBackdrop"></div>
<div class="order-modal" id="orderModal">
  <h3>Order Details</h3>
  <div id="orderContent"></div>
  <div class="modal-footer">
    <button class="modal-close" onclick="closeOrderModal()">Close</button>
  </div>
</div>

  <!-- Affiliate Modal -->
  <div class="modal-backdrop" id="affiliateBackdrop"></div>
  <div class="order-modal" id="affiliateModal">
    <h3>Affiliate / Referral</h3>
    <div id="affiliateContent">
      <p><strong>Order:</strong> <span id="affOrderId">-</span></p>
      <p><strong>Admin portion:</strong> KES <span id="affAdminAmount">0</span></p>
      <div>
        <h6>Affiliate(s) on this order</h6>
        <div id="affiliateList" style="margin-top:8px;">
          <!-- Populated by JS: list of affiliate name, contact, percent, amount, token -->
        </div>
      </div>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:10px;">
        <button class="btn-small" onclick="closeAffiliateModal()">Close</button>
      </div>
    </div>
    <div class="modal-footer"></div>
   </div>

<script>
function toggleMenu(){
  document.getElementById("sidebar").classList.toggle("collapsed");
  document.getElementById("main").classList.toggle("expanded");
}

async function viewOrder(orderId) {
  try {
    const response = await fetch('get_order_details.php?id=' + orderId);
    const data = await response.json();
    
    if (data.success) {
  const order = data.order;
  // admin_amount may be returned separately
  order.admin_amount = data.admin_amount || 0;
  const items = data.items;
      
      let html = `
        <div class="order-info">
          <div class="info-item">
            <h6>Customer Name</h6>
            <p>${order.customer_name}</p>
          </div>
          <div class="info-item">
            <h6>Email</h6>
            <p>${order.customer_email}</p>
          </div>
          <div class="info-item">
            <h6>Phone</h6>
            <p>${order.customer_phone}</p>
          </div>
          <div class="info-item">
            <h6>Location</h6>
            <p>${order.location || 'N/A'}</p>
          </div>
          <div class="info-item">
            <h6>Order Date</h6>
            <p>${new Date(order.order_date).toLocaleString()}</p>
          </div>
          <div class="info-item">
            <h6>Status</h6>
            <p><span class="status-badge status-${order.status.toLowerCase()}">${order.status}</span></p>
          </div>
        </div>
        
        <div class="items-list">
          <h6>Order Items</h6>
      `;
      
      items.forEach(item => {
        html += `
          <div class="item">
            <div class="item-name"><strong>${item.product_name}</strong></div>
            <div class="item-qty">${item.quantity} x KES ${parseInt(item.price).toLocaleString()}</div>
            <div class="item-total">KES ${parseInt(item.subtotal).toLocaleString()}</div>
          </div>
        `;
      });
      
      html += `
        </div>
        <div style="text-align: right; padding: 15px 0; border-top: 2px solid #e0e0e0;">
          <h5>Your total: <span style="color: #28a745;">KES ${parseInt(order.admin_amount || 0).toLocaleString()}</span></h5>
        </div>
      `;
      
      document.getElementById('orderContent').innerHTML = html;
      document.getElementById('orderModalBackdrop').classList.add('show');
      document.getElementById('orderModal').classList.add('show');
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Failed to load order details');
  }
}

function openAffiliateModal(orderId, orderId2) {
  // orderId and orderId2 are same but kept to allow future params
  document.getElementById('affiliateBackdrop').classList.add('show');
  document.getElementById('affiliateModal').classList.add('show');
  document.getElementById('affOrderId').textContent = orderId;
  // fetch order details to show admin_amount
  fetch('get_order_details.php?id=' + orderId)
    .then(r=>r.json()).then(j=>{
      if (j.success) {
        const admin_amount = j.admin_amount || 0;
        document.getElementById('affAdminAmount').textContent = parseInt(admin_amount).toLocaleString();
        document.getElementById('payAmount').value = Math.max(0, Math.round(admin_amount * 0.10)); // default 10%
      }
    });

  // Attempt to read affiliate earnings embedded on the order row via data-affiliates
  const listEl = document.getElementById('affiliateList'); listEl.innerHTML = '';
  let affiliates = [];

  // Find the table row that corresponds to this order id (we add data-order-id on each row)
  let rowEl = document.querySelector('tr[data-order-id="' + orderId + '"]');
  if (rowEl) {
    const attr = rowEl.getAttribute('data-affiliates');
    try { affiliates = attr ? JSON.parse(attr) : []; } catch(e){ affiliates = []; }
  }

  if (affiliates && affiliates.length) {
    affiliates.forEach(a => {
      const div = document.createElement('div');
      div.style.padding = '8px';
      div.style.borderBottom = '1px solid #f0f0f0';
      const name = a.name || ('Affiliate #' + (a.affiliate_id || a.id || ''));
      const email = a.email ? (' • ' + a.email) : '';
      const phone = a.phone ? (' • ' + a.phone) : '';
      const referral = a.referral_code ? (' • Code: ' + a.referral_code) : '';
      div.innerHTML = '<strong>' + escapeHtml(name) + '</strong>' + email + phone + referral + '<div style="color:#28a745; font-weight:600; margin-top:6px;">KES ' + Number(a.amount || 0).toLocaleString() + '</div>';
      listEl.appendChild(div);
    });
  } else {
    listEl.innerHTML = '<div style="color:#666">No affiliates found for this order</div>';
  }
}

function closeAffiliateModal(){
  document.getElementById('affiliateBackdrop').classList.remove('show');
  document.getElementById('affiliateModal').classList.remove('show');
}

// small helper to escape HTML
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>\"]/g, function (s) {
    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]);
  });
}

function payAffiliate(){
  // deprecated: manual pay removed. This function kept intentionally empty.
  return;
}

function closeOrderModal() {
  document.getElementById('orderModalBackdrop').classList.remove('show');
  document.getElementById('orderModal').classList.remove('show');
}

async function completeOrder(orderId) {
  if (confirm('Mark this order as completed?')) {
    try {
      const response = await fetch('update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, status: 'Completed' })
      });
      
      const data = await response.json();
      if (data.success) {
        alert('Order updated successfully!');
        location.reload();
      } else {
        alert('Error updating order');
      }
    } catch (error) {
      console.error('Error:', error);
    }
  }
}

// Close modal when clicking backdrop
document.getElementById('orderModalBackdrop')?.addEventListener('click', closeOrderModal);

// Mobile nav toggle for small screens
function toggleMobileNav(){
  var n = document.getElementById('mobileNav');
  if(!n) return;
  if(n.classList.contains('show')){
    n.classList.remove('show');
    n.setAttribute('aria-hidden','true');
  } else {
    n.classList.add('show');
    n.setAttribute('aria-hidden','false');
  }
}

// Close mobile nav when a link inside it is clicked
document.addEventListener('click', function(e){
  var mobileNav = document.getElementById('mobileNav');
  if(!mobileNav) return;
  if(mobileNav.classList.contains('show') && e.target.closest('.mobile-nav a')){
    mobileNav.classList.remove('show');
    mobileNav.setAttribute('aria-hidden','true');
  }
});
</script>

  <script src="../loader.js"></script>
</body>
</html>
