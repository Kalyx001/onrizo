<?php
session_start();
include '../db_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');

$stmt = $conn->prepare("SELECT id, name, price, description, category, image, date_added FROM products WHERE admin_id = ? AND (deleted IS NULL OR deleted = 0) ORDER BY id DESC");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
// prepare statement to read affiliate_percent per product if column exists (safe: will fail only if column missing at DB level)
$apStmt = null;
$colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'affiliate_percent'");
if ($colCheck && $colCheck->num_rows > 0) {
    $apStmt = $conn->prepare("SELECT COALESCE(affiliate_percent, '') as ap FROM products WHERE id = ? LIMIT 1");
}
// Analytics queries (scoped to this admin)
// Total orders and revenue for products owned by this admin
$totStmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) as cnt, COALESCE(SUM(oi.subtotal),0) as total_revenue
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.admin_id = ?");
$totStmt->bind_param('i', $admin_id);
$totStmt->execute();
$totRes = $totStmt->get_result();
$totRow = $totRes ? $totRes->fetch_assoc() : ['cnt'=>0,'total_revenue'=>0];
$totalOrders = (int)$totRow['cnt'];
$totalRevenue = (float)$totRow['total_revenue'];

// Completed / Pending orders counts (distinct orders that include this admin's products)
$completedStmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) as cnt
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.admin_id = ? AND o.status = 'Completed'");
$completedStmt->bind_param('i', $admin_id); $completedStmt->execute(); $completedCntRes = $completedStmt->get_result();
$completedOrders = $completedCntRes ? (int)$completedCntRes->fetch_assoc()['cnt'] : 0;

$pendingStmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) as cnt
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.admin_id = ? AND o.status = 'Pending'");
$pendingStmt->bind_param('i', $admin_id); $pendingStmt->execute(); $pendingCntRes = $pendingStmt->get_result();
$pendingOrders = $pendingCntRes ? (int)$pendingCntRes->fetch_assoc()['cnt'] : 0;

// Balance = completed sales minus platform commission, affiliate owed, and withdrawals
// Compute total completed sales for this admin
$completedSumStmt = $conn->prepare("SELECT COALESCE(SUM(oi.subtotal),0) as total_sub
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.admin_id = ? AND o.status = 'Completed'");
$completedSumStmt->bind_param('i', $admin_id); $completedSumStmt->execute(); $completedSumRes = $completedSumStmt->get_result();
$totalCompleted = $completedSumRes ? (float)$completedSumRes->fetch_assoc()['total_sub'] : 0.0;

// platform commission (5%) applies to all completed sales
$platform_commission_percent = 5.0;
$platform_commission = round($totalCompleted * ($platform_commission_percent / 100), 2);
$completedNet = max(0, $totalCompleted - $platform_commission);

// Sum of affiliate commissions owed for this admin (confirmed clicks)
$affOwedStmt = $conn->prepare("SELECT COALESCE(SUM(ac.commission),0) as owed
    FROM affiliate_clicks ac
    JOIN products p ON ac.product_id = p.id
    WHERE p.admin_id = ? AND ac.status = 'confirmed'");
$affOwedStmt->bind_param('i', $admin_id);
$affOwedStmt->execute();
$affOwedRes = $affOwedStmt->get_result();
$affOwed = $affOwedRes ? (float)$affOwedRes->fetch_assoc()['owed'] : 0.0;
$affOwedStmt->close();

$completedSum = max(0, $completedNet - $affOwed);

// Sum of withdrawals that are PAID (deduct only paid withdrawals from available)
$createWithdrawals = "CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    status VARCHAR(32) DEFAULT 'Reserved',
    transaction_id VARCHAR(255) DEFAULT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createWithdrawals);

$withdrawalsRes = $conn->prepare("SELECT COALESCE(SUM(net_amount),0) as withdrawn_total FROM withdrawals WHERE status = 'Paid' AND admin_id = ?");
$withdrawalsRes->bind_param('i', $admin_id); $withdrawalsRes->execute(); $wr = $withdrawalsRes->get_result();
$withdrawnTotal = $wr ? (float)$wr->fetch_assoc()['withdrawn_total'] : 0.0;

// Sum of reserved/pending withdrawals (amount requested, not yet paid)
$reservedRes = $conn->prepare("SELECT COALESCE(SUM(amount),0) as reserved_total, COUNT(*) as reserved_count FROM withdrawals WHERE status IN ('Reserved','Pending','Verified') AND admin_id = ?");
$reservedRes->bind_param('i', $admin_id); $reservedRes->execute(); $resres = $reservedRes->get_result();
$reservedRow = $resres ? $resres->fetch_assoc() : ['reserved_total'=>0,'reserved_count'=>0];
$reservedTotal = (float)($reservedRow['reserved_total'] ?? 0);
$pendingWithdrawalsCount = (int)($reservedRow['reserved_count'] ?? 0);

// Available balance = completed orders - paid withdrawals - reserved withdrawals
$balance = max(0, $completedSum - $withdrawnTotal - $reservedTotal);

// Get recent withdrawal statements
$withdrawalStmts = [];
$withdrawalRes = $conn->query("SELECT id, amount, destination, status, transaction_id, requested_at, processed_at FROM withdrawals WHERE admin_id = $admin_id ORDER BY requested_at DESC LIMIT 20");
if ($withdrawalRes) {
    while ($wd = $withdrawalRes->fetch_assoc()) {
        $withdrawalStmts[] = $wd;
    }
}

// CSRF token for admin actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// Recent activity (latest 6 orders)
$recentStmt = $conn->prepare("SELECT o.id, o.customer_name, o.status, o.order_date, COALESCE(SUM(oi.subtotal),0) as admin_amount
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.admin_id = ?
    GROUP BY o.id
    ORDER BY o.order_date DESC
    LIMIT 6");
$recentStmt->bind_param('i', $admin_id); $recentStmt->execute(); $recentRes = $recentStmt->get_result();
$recentOrders = [];
if ($recentRes) {
    while ($r = $recentRes->fetch_assoc()) $recentOrders[] = $r;
}

// Orders over time (last 30 days) for chart
 $chartStmt = $conn->prepare("SELECT DATE(o.order_date) as d, COUNT(DISTINCT o.id) as cnt, COALESCE(SUM(oi.subtotal),0) as revenue
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND p.admin_id = ?
    GROUP BY DATE(o.order_date)
    ORDER BY DATE(o.order_date)");
$chartStmt->bind_param('i', $admin_id); $chartStmt->execute(); $chartRes = $chartStmt->get_result();
$chartLabels = [];
$chartCounts = [];
$chartRevenue = [];
$chartMap = [];
if ($chartRes) {
    while ($row = $chartRes->fetch_assoc()) {
        $chartMap[$row['d']] = $row;
    }
}
// ensure all last-30 days present
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('M j', strtotime($d));
    if (isset($chartMap[$d])) {
        $chartCounts[] = (int)$chartMap[$d]['cnt'];
        $chartRevenue[] = (float)$chartMap[$d]['revenue'];
    } else {
        $chartCounts[] = 0;
        $chartRevenue[] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard - Orizo Shop</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* Layout */
body {
    background: #f5f6fa;
    display: flex;
    margin: 0;
    font-family: Arial, sans-serif;
}

/* Sidebar */
.sidebar {
    width: 230px;
    background: #fff;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    border-right: 1px solid #ddd;
    padding-top: 20px;
    transition: width 0.3s;
    overflow-y: auto;
}

.sidebar.collapsed {
    width: 70px;
}

.sidebar .brand {
    font-size: 20px;
    font-weight: bold;
    padding: 0 20px 20px;
}

.sidebar ul {
    list-style: none;
    padding-left: 0;
    margin: 0;
}

.sidebar ul li a {
    display: block;
    padding: 12px 20px;
    color: #444;
    font-size: 15px;
    text-decoration: none;
    transition: background 0.2s;
}

.sidebar ul li a:hover {
    background: #f0f0f0;
}

/* Main */
.main {
    margin-left: 230px;
    padding: 20px;
    width: calc(100% - 230px);
    transition: all 0.3s;
    overflow-y: auto;
    height: 100vh;
}

.main.expanded {
    margin-left: 70px;
    width: calc(100% - 70px);
}

/* Top Bar */
.topbar {
    background: #fff;
    padding: 15px 20px;
    border-radius: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.notification-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f5f6ff;
    color: #3f51b5;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 700;
}

.notification-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ff9800;
}

/* Search Bar */
.search-box input {
    width: 260px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}

/* Analytics Cards */
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.analytics-card {
    background: #fff;
    padding: 18px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-left: 4px solid #667eea;
}

.analytics-card.revenue {
    border-left-color: #28a745;
}

.analytics-card.balance {
    border-left-color: #1e88e5;
}

.analytics-card.pending {
    border-left-color: #ff9800;
}

.analytics-card h6 {
    color: #666;
    font-weight: 700;
    margin: 0 0 8px 0;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.analytics-card .value {
    font-size: 24px;
    font-weight: 800;
    color: #333;
    margin-bottom: 6px;
}

.analytics-card .subtitle {
    color: #999;
    font-size: 13px;
}

.analytics-card .btn {
    margin-top: 10px;
}

/* Content sections */
.content-section {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.content-section h5 {
    margin: 0 0 16px 0;
    font-weight: 700;
    color: #333;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.content-section h5 .view-all {
    font-size: 13px;
    font-weight: 600;
}

/* Table */
.table-wrapper {
    overflow-x: auto;
}

.table {
    margin: 0;
    font-size: 14px;
}

.table thead th {
    background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-weight: 700;
    color: #666;
    padding: 12px;
}

.table tbody tr {
    border-bottom: 1px solid #e9ecef;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.table td {
    padding: 12px;
    vertical-align: middle;
}

.thumb {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    object-fit: cover;
}

/* Chart section */
.chart-section {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.chart-section h5 {
    margin: 0 0 16px 0;
    font-weight: 700;
}

.chart-section canvas {
    max-height: 300px;
}

/* Withdrawals section */
.withdrawals-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.withdrawals-grid .content-section {
    margin-bottom: 0;
}

@media (max-width: 1200px) {
    .withdrawals-grid {
        grid-template-columns: 1fr;
    }
}

/* Icon buttons for action column */
.icon-btn, .icon-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 6px;
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 4px;
    margin-right: 6px;
    /* raise z-index in case other elements overlay the actions */
    z-index: 5000;
    pointer-events: auto;
    position: relative;
}
.icon-btn:hover, .icon-link:hover { background: #f2f4f7; }
.icon-link { text-decoration:none; color:inherit; }
.icon-svg { width:18px; height:18px; display:block; }

/* Hamburger */
.menu-toggle {
    font-size: 22px;
    cursor: pointer;
    margin-right: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 70px;
    }
    .main {
        margin-left: 70px;
        width: calc(100% - 70px);
        padding: 15px;
    }
    .topbar {
        flex-direction: column;
        gap: 10px;
    }
    .search-box input {
        width: 100%;
    }
    .analytics-grid {
        grid-template-columns: 1fr;
    }
    .withdrawals-grid {
        grid-template-columns: 1fr;
    }
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

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="brand">🛒 Onrizo</div>
    <ul>
        <li><a href="#">📊 Dashboard</a></li>
        <li><a href="orders.php">📦 Orders</a></li>
        <li><a href="dashboard.php">🛍 Products</a></li>
        <li><a href="add_product.php">➕ Add Product</a></li>
        <li><a href="promote.php">🚀 Promote My Products</a></li>
        <li><a href="logout.php">🚪 Logout</a></li>
    </ul>
</div>

<!-- Main Content -->
<div class="main" id="main">
    
    <div class="topbar">
        <div class="d-flex align-items-center">
            <span class="menu-toggle" onclick="toggleMenu()">☰</span>
            <h4 class="mb-0">Welcome, <?= $admin_username ?> 👋</h4>
        </div>

        <div class="d-flex align-items-center" style="gap:12px;">
            <span class="notification-badge"><span class="notification-dot"></span> New Orders: <?= $pendingOrders ?></span>
            <span class="notification-badge" style="background:#fff3cd;color:#856404;"><span class="notification-dot" style="background:#ff9800;"></span> Pending Withdrawals: <?= $pendingWithdrawalsCount ?></span>
            <div class="search-box">
                <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="Search products...">
            </div>
        </div>
    </div>

    <!-- Analytics Cards -->
    <div class="analytics-grid">
        <div class="analytics-card">
            <h6>📊 Total Orders</h6>
            <div class="value"><?= $totalOrders ?></div>
            <div class="subtitle">All time</div>
        </div>

        <div class="analytics-card revenue">
            <h6>💰 Total Revenue</h6>
            <div class="value" style="color:#28a745;">KES <?= number_format($totalRevenue,0) ?></div>
            <div class="subtitle">All time</div>
        </div>

        <div class="analytics-card balance">
            <h6>💳 Available Balance</h6>
            <div class="value" style="color:#1e88e5;">KES <?= number_format($balance,0) ?></div>
            <div class="subtitle">Ready for withdrawal</div>
            <div style="margin-top:8px; font-size:12px; color:#666; line-height:1.4;">
                <div>Completed revenue: <strong>KES <?= number_format($totalCompleted,0) ?></strong></div>
                <div>Affiliate owed: <strong>KES <?= number_format($affOwed,0) ?></strong></div>
                <div>Paid withdrawals: <strong>KES <?= number_format($withdrawnTotal,0) ?></strong></div>
                <div>Reserved requests: <strong>KES <?= number_format($reservedTotal,0) ?></strong></div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openWithdrawModal()" style="margin-top:8px;">Request Withdraw</button>
        </div>

        <div class="analytics-card pending">
            <h6>⏳ Pending Orders</h6>
            <div class="value" style="color:#ff9800;"><?= $pendingOrders ?></div>
            <div class="subtitle">Need attention</div>
        </div>
    </div>

    <!-- Products & Recent Activity Grid -->
    <div class="withdrawals-grid" style="grid-template-columns: 2fr 1fr;">
        <!-- Products Table -->
        <div class="content-section">
            <h5>
                🛍️ Your Products
                <a href="add_product.php" class="btn btn-sm btn-success view-all">+ Add Product</a>
            </h5>
        <?php if ($result && $result->num_rows > 0): ?>
        <div class="table-wrapper">
        <table class="table table-hover">
            <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Price (KES)</th>
                <th>Description</th>
                <th>Category</th>
                <th>Date Added</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody id="productTable">
            <?php while ($row = $result->fetch_assoc()): 
                $id = (int)$row['id'];
                $img = htmlspecialchars($row['image'] ?? '', ENT_QUOTES);
                $name = htmlspecialchars($row['name'], ENT_QUOTES);
                $price = number_format($row['price']);
                // fetch product-level affiliate percent (if column exists)
                $affPercent = '';
                if ($apStmt) {
                    $apStmt->bind_param('i', $id);
                    $apStmt->execute();
                    $apRes = $apStmt->get_result();
                    if ($apRes && ($prow = $apRes->fetch_assoc())) $affPercent = $prow['ap'];
                }
                // prepare a safe short plain-text description (strip HTML)
                $plainDesc = trim(strip_tags($row['description'] ?? ''));
                $short = mb_substr($plainDesc, 0, 120);
                $needsMore = mb_strlen($plainDesc) > 120;
                $desc = htmlspecialchars($short, ENT_QUOTES);
                $cat = htmlspecialchars($row['category'], ENT_QUOTES);
                $date = htmlspecialchars($row['date_added'], ENT_QUOTES);
            ?>
            <tr id="prod-<?= $id ?>" data-affpercent="<?= htmlspecialchars($affPercent ?? '') ?>" data-price="<?= htmlspecialchars($row['price'] ?? 0) ?>">
                <td><?= $id ?></td>
                <td><?php if ($img): ?><img class="thumb" src="../<?= $img ?>"><?php else: ?>No image<?php endif; ?></td>
                <td><?= $name ?></td>
                <td><?= $price ?></td>
                <td>
                    <?= $desc ?>
                    <?php if (!empty($needsMore)): ?>
                        ... <a href="#" class="more-desc" data-id="<?= $id ?>">more</a>
                    <?php endif; ?>
                    <!-- hidden sanitized full HTML description for modal -->
                    <div id="full-desc-<?= $id ?>" style="display:none;">
                        <?php
                            // allow basic formatting tags when rendering full description
                            echo strip_tags($row['description'] ?? '', '<p><br><strong><em><ul><li><ol><a><b><i><u><h1><h2><h3><blockquote>');
                        ?>
                    </div>
                </td>
                <td><?= $cat ?></td>
                <td><?= $date ?></td>
                <td>
                    <a class="icon-link" href="edit_product.php?id=<?= $id ?>" title="Edit" aria-label="Edit product <?= $id ?>" tabindex="0">
                        <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                    </a>
                    <button type="button" class="icon-btn" onclick="confirmDelete(<?= $id ?>)" title="Delete" aria-label="Delete product <?= $id ?>" tabindex="0">
                        <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                    <button type="button" class="icon-btn" onclick="openAffiliateModal(<?= $id ?>)" title="Affiliate" aria-label="Affiliate for product <?= $id ?>" tabindex="0">
                        <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M10 14a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M21 21v-2a4 4 0 0 0-3-3.87"/><path d="M3 21v-2a4 4 0 0 1 3-3.87"/><path d="M8 7.5C8 9.433 9.567 11 11.5 11H12"/></svg>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
            <p class="text-center p-4">No products found. <a href="add_product.php">Add one</a></p>
        <?php endif; ?>
        </div>

        <!-- Recent Activity Sidebar -->
        <div class="content-section">
            <h5>📌 Recent Activity</h5>
            <?php if (!empty($recentOrders)): ?>
                <ul style="list-style:none; padding-left:0; margin:0;">
                <?php foreach($recentOrders as $ro): ?>
                    <li style="padding:10px 0; border-bottom:1px solid #f1f1f1; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <?php $ro_amount = isset($ro['total_amount']) ? $ro['total_amount'] : (isset($ro['admin_amount']) ? $ro['admin_amount'] : 0); ?>
                            <div style="font-weight:700;">Order #<?= htmlspecialchars($ro['id']) ?> - KES <?= number_format($ro_amount,0) ?></div>
                            <div style="font-size:12px; color:#777;"><?= htmlspecialchars($ro['customer_name']) ?> • <?= date('M d, H:i', strtotime($ro['order_date'])) ?></div>
                        </div>
                        <div style="text-align:right;">
                            <span style="padding:6px 10px; border-radius:16px; background:#f3f4f6; font-weight:700; font-size:12px;"><?= htmlspecialchars($ro['status']) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-center p-3" style="color:#999;">No recent activity.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Orders Chart -->
    <div class="chart-section">
        <h5>📈 Orders over last 30 days</h5>
        <canvas id="ordersChart" height="120" style="width:100%;"></canvas>
    </div>

    <!-- Withdrawal Statements -->
    <div class="content-section">
        <h5>
            💸 Withdrawal Statements
            <a href="withdrawals.php" class="btn btn-sm btn-primary view-all">View All</a>
        </h5>
        <?php if (!empty($withdrawalStmts)): ?>
            <div class="table-wrapper">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Amount (KES)</th>
                            <th>Destination</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($withdrawalStmts as $w): 
                            $statusColor = $w['status'] === 'Paid' ? '#28a745' : ($w['status'] === 'Cancelled' ? '#dc3545' : '#ff9800');
                            $statusLabel = $w['status'] === 'Reserved' ? '⏳ Pending' : ($w['status'] === 'Paid' ? '✅ Paid' : '❌ ' . $w['status']);
                        ?>
                            <tr>
                                <td style="font-size:13px;"><?= date('M d, Y', strtotime($w['requested_at'])) ?></td>
                                <td style="font-weight:700; color:#333;">KES <?= number_format($w['amount'], 0) ?></td>
                                <td style="font-size:13px;"><?= htmlspecialchars($w['destination']) ?></td>
                                <td><span style="padding:4px 8px; border-radius:4px; background-color:<?= $statusColor ?>20; color:<?= $statusColor ?>; font-size:12px; font-weight:700;"><?= $statusLabel ?></span></td>
                                <td style="font-size:12px; color:#666;"><?= $w['transaction_id'] ? htmlspecialchars($w['transaction_id']) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:#999; text-align:center; padding:20px 0; margin:0;">No withdrawal requests yet.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Description modal (for admin dashboard)
function openDescriptionModal(html){
    // create modal if not present
    if(!document.getElementById('descrModalBackdrop')){
        const b = document.createElement('div');
        b.id = 'descrModalBackdrop';
        b.style = 'position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:20000;display:block;';
        document.body.appendChild(b);

        const m = document.createElement('div');
        m.id = 'descrModal';
        m.style = 'position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;padding:18px;border-radius:10px;z-index:20001;max-width:760px;width:90%;max-height:80vh;overflow:auto;';
        m.innerHTML = '<div id="descrModalContent"></div><div style="text-align:right;margin-top:12px;"><button class="btn btn-secondary" onclick="closeDescriptionModal()">Close</button></div>';
        document.body.appendChild(m);
        b.addEventListener('click', closeDescriptionModal);
    }
    // limit to 10 lines initially, add show more toggle if content is long
    const contentEl = document.getElementById('descrModalContent');
    const wrapper = document.createElement('div');
    wrapper.className = 'descr-wrapper';
    wrapper.style.lineHeight = '1.4';
    wrapper.style.maxHeight = (1.4 * 10) + 'em';
    wrapper.style.overflow = 'hidden';
    wrapper.style.position = 'relative';
    wrapper.innerHTML = html;

    // measure if content exceeds 10 lines by temporarily appending
    contentEl.innerHTML = '';
    contentEl.appendChild(wrapper);

    // create toggle if content is long
    setTimeout(() => {
        const isOverflowing = wrapper.scrollHeight > wrapper.clientHeight + 4;
        if (isOverflowing) {
            const more = document.createElement('a');
            more.href = '#';
            more.textContent = 'Show more';
            more.style.display = 'inline-block';
            more.style.marginTop = '8px';
            more.style.color = '#007bff';
            more.addEventListener('click', (e) => {
                e.preventDefault();
                if (wrapper.style.maxHeight && wrapper.style.maxHeight !== 'none') {
                    wrapper.style.maxHeight = 'none';
                    more.textContent = 'Show less';
                } else {
                    wrapper.style.maxHeight = (1.4 * 10) + 'em';
                    more.textContent = 'Show more';
                }
            });
            contentEl.appendChild(more);
        }
    }, 50);
    document.getElementById('descrModalBackdrop').style.display = 'block';
    document.getElementById('descrModal').style.display = 'block';
}
function closeDescriptionModal(){
    const b = document.getElementById('descrModalBackdrop');
    const m = document.getElementById('descrModal');
    if(b) b.style.display = 'none';
    if(m) m.style.display = 'none';
}

// attach handler for more links
document.addEventListener('click', function(e){
    const t = e.target;
    if(t && t.classList && t.classList.contains('more-desc')){
        e.preventDefault();
        const id = t.getAttribute('data-id');
        const el = document.getElementById('full-desc-' + id);
        if(el){
            openDescriptionModal(el.innerHTML);
        }
    }
});
function toggleMenu() {
    document.getElementById("sidebar").classList.toggle("collapsed");
    document.getElementById("main").classList.toggle("expanded");
}

function searchTable() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let rows = document.querySelectorAll("#productTable tr");

    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(input) ? "" : "none";
    });
}

function confirmDelete(id) {
    if (!confirm("Delete product?")) return;
    fetch("delete_product.php?id=" + id, { credentials: 'same-origin' })
    .then(r => r.text())
    .then(msg => {
        alert(msg);
        const el = document.getElementById("prod-"+id);
        if (el) el.remove();
    })
    .catch(err => { console.error(err); alert('❌ Error deleting product'); });
}

/* Withdraw modal handling */
function openWithdrawModal(){
    // create modal if not exists
    if(!document.getElementById('withdrawModalBackdrop')){
        const backdrop = document.createElement('div');
        backdrop.id = 'withdrawModalBackdrop';
        backdrop.style = 'position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;display:block;';
        document.body.appendChild(backdrop);

        const modal = document.createElement('div');
        modal.id = 'withdrawModal';
        modal.style = 'position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;padding:18px;border-radius:10px;z-index:10001;max-width:420px;width:90%;';
        modal.innerHTML = `
            <h5>Request Withdraw</h5>
            <p style="color:#666;">Available balance: <strong>KES <?= number_format($balance,0) ?></strong></p>
            <div style="margin-bottom:10px;"><label>Amount (KES)</label><input id="withdrawAmount" type="number" class="form-control" min="1" max="<?= (int)$balance ?>" value="<?= (int)$balance ?>"></div>
            <div style="margin-bottom:10px;"><label>Payable To (Account/Phone)</label><input id="withdrawTo" type="text" class="form-control" placeholder="MPESA/Bank account"></div>
            <div style="display:flex; gap:8px; margin-top:12px;">
                <button class="btn btn-primary" onclick="submitWithdraw()">Submit Request</button>
                <button class="btn btn-secondary" onclick="closeWithdrawModal()">Cancel</button>
            </div>
        `;
        document.body.appendChild(modal);
        backdrop.addEventListener('click', closeWithdrawModal);
    }
}

function closeWithdrawModal(){
    const b = document.getElementById('withdrawModalBackdrop');
    const m = document.getElementById('withdrawModal');
    if(b) b.remove();
    if(m) m.remove();
}

async function submitWithdraw(){
    const amount = parseFloat(document.getElementById('withdrawAmount').value || 0);
    const to = (document.getElementById('withdrawTo').value || '').trim();
    if(!amount || amount <= 0){ alert('Enter a valid amount'); return; }
    if(!to){ alert('Enter destination account/phone'); return; }

    try{
        const res = await fetch('request_withdraw.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ amount: amount, to: to })
        });
        const data = await res.json();
        if(data.success){
            alert('Withdraw request submitted');
            closeWithdrawModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Request failed'));
        }
    } catch(err){
        console.error(err);
        alert('Network error');
    }
}

// Chart.js orders chart
// embed PHP arrays into JS
const chartLabels = <?= json_encode($chartLabels) ?>;
const chartCounts = <?= json_encode($chartCounts) ?>;
const chartRevenue = <?= json_encode($chartRevenue) ?>;
const chartAvg = <?= json_encode(array_map(function($c,$r){ return $c>0? round($r/$c,2):0; }, $chartCounts, $chartRevenue)) ?>;
const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

function renderOrdersChart(){
    const ctx = document.getElementById('ordersChart').getContext('2d');
    // load Chart.js dynamically if not present
    if (typeof Chart === 'undefined'){
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        s.onload = () => createChart(ctx);
        document.head.appendChild(s);
    } else {
        createChart(ctx);
    }
}

function createChart(ctx){
    const gradientLine = ctx.createLinearGradient(0, 0, 0, 240);
    gradientLine.addColorStop(0, 'rgba(102,126,234,0.35)');
    gradientLine.addColorStop(1, 'rgba(102,126,234,0.02)');

    const gradientBar = ctx.createLinearGradient(0, 0, 0, 240);
    gradientBar.addColorStop(0, 'rgba(40,167,69,0.5)');
    gradientBar.addColorStop(1, 'rgba(40,167,69,0.1)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                { label: 'Orders', data: chartCounts, borderColor: '#667eea', backgroundColor: gradientLine, fill: true, tension: 0.35, yAxisID: 'y', pointRadius: 3, pointBackgroundColor: '#667eea' },
                { label: 'Revenue (KES)', data: chartRevenue, borderColor: '#28a745', backgroundColor: gradientBar, tension: 0.2, yAxisID: 'y1', type:'bar', borderRadius: 6 },
                { label: 'Avg Order (KES)', data: chartAvg, borderColor: '#ff9800', backgroundColor: 'rgba(255,152,0,0.08)', tension: 0.35, borderDash:[4,4], yAxisID: 'y1', pointRadius: 2 }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' }, tooltip: { padding: 10 } },
            scales: {
                y: { type: 'linear', display: true, position: 'left', beginAtZero: true, title: { display: true, text: 'Orders' }, grid: { color: '#eef1f6' } },
                y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'KES' } }
            }
        }
    });
}

// render when DOM ready
document.addEventListener('DOMContentLoaded', function(){
    try{ renderOrdersChart(); } catch(e){ console.error(e); }
});
</script>

<!-- Fallback click-listeners & diagnostics for affiliate buttons -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    try{
        // Attach a defensive click listener to any Affiliate icon buttons in case inline onclick fails
        const affBtns = document.querySelectorAll('button[aria-label^="Affiliate"], .icon-link[aria-label^="Affiliate"]');
        affBtns.forEach(btn => {
            // avoid adding multiple listeners
            if (btn._affListenerAttached) return;
            btn.addEventListener('click', function(e){
                console.log('Affiliate button clicked (fallback listener)', {btn: btn, target: e.target});
                // find row and product id
                const tr = btn.closest('tr');
                if (!tr) return;
                const idAttr = tr.id || '';
                const m = idAttr.match(/prod-(\d+)/);
                if (m) {
                    const pid = parseInt(m[1], 10);
                    try { openAffiliateModal(pid); } catch(err) { console.error('openAffiliateModal error', err); }
                }
            });
            btn._affListenerAttached = true;
        });
        // global click logger for debugging (quick toggle) - comment out when done
        // document.addEventListener('click', e => console.log('global click', e.target));
    }catch(e){ console.error('affiliate fallback init failed', e); }
});
</script>

<!-- Affiliate Modal markup and JS -->
<script>
function openAffiliateModal(productId){
    console.log('openAffiliateModal called for productId=', productId);
    // find existing percent from row if available
    const row = document.getElementById('prod-' + productId);
    let curPercent = '';
    if(row){
        // try data attribute or embedded cell
        curPercent = row.getAttribute('data-affpercent') || '';
    }

    // build modal
    if(!document.getElementById('affiliateModalBackdrop')){
        const b = document.createElement('div');
        b.id = 'affiliateModalBackdrop';
        b.style = 'position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:12000;';
        document.body.appendChild(b);

        const m = document.createElement('div');
        m.id = 'affiliateModal';
        m.style = 'position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;padding:18px;border-radius:10px;z-index:12001;max-width:520px;width:90%;';
        m.innerHTML = `
            <h5>Set Product Affiliate Percent</h5>
            <div style="margin-top:10px;">
                <label>Default affiliate percent for this product (%)</label>
                <input id="affPercent" type="number" min="0" step="0.01" class="form-control" style="margin-bottom:8px;" />
                <div style="font-size:13px;color:#666;margin-bottom:12px;">Enter a percentage (e.g. 5 for 5%). This will be used as the default commission for this product. This percent is stored on the product and will be used to calculate affiliate payouts when a referred sale occurs.</div>
                <div style="font-size:12px;color:#555;margin-bottom:8px;border-left:3px solid #eef;padding:8px;border-radius:4px;background:#fbfcff;">Tip: set to <strong>0</strong> to disable affiliate commission for this product. Percent values are stored with two decimals (e.g. 2.50).</div>

                <div id="affPreview" style="background:#fafafa;border:1px solid #eee;padding:10px;border-radius:6px;margin-bottom:10px;display:none;">
                    <div style="font-size:13px;color:#555;">Product price: <strong id="previewPrice">KES 0</strong></div>
                    <div style="font-size:13px;color:#555;">Affiliate gets: <strong id="previewAffiliate">KES 0</strong></div>
                    <div style="font-size:13px;color:#555;">You (admin) receive: <strong id="previewAdmin">KES 0</strong></div>
                </div>

                <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAffiliateModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveAffiliate(${productId})">Save</button>
                </div>
                <div id="affResult" style="margin-top:12px;display:none;"></div>
            </div>
        `;
        document.body.appendChild(m);
        b.addEventListener('click', closeAffiliateModal);
    }

    // populate
    document.getElementById('affPercent').value = curPercent || '';
    // hide result area if present
    const resEl = document.getElementById('affResult'); if (resEl) resEl.style.display = 'none';
    document.getElementById('affiliateModalBackdrop').style.display = 'block';
    document.getElementById('affiliateModal').style.display = 'block';

    // Setup live preview based on product price
    try{
        const row = document.getElementById('prod-' + productId);
        const price = row ? parseFloat(row.dataset.price || 0) : 0;
        const previewBlock = document.getElementById('affPreview');
        const previewPrice = document.getElementById('previewPrice');
        const previewAffiliate = document.getElementById('previewAffiliate');
        const previewAdmin = document.getElementById('previewAdmin');
        const percentInput = document.getElementById('affPercent');
        if (previewBlock && percentInput) {
            previewBlock.style.display = 'block';
            previewPrice.textContent = 'KES ' + Number(price).toLocaleString();
            function updatePreview(){
                const p = parseFloat(percentInput.value || 0) || 0;
                const aff = Math.round((price * (p/100)) * 100) / 100;
                const admin = Math.round((price - aff) * 100) / 100;
                // percent displays
                const affPct = (Math.round(p * 100) / 100).toFixed(2);
                const adminPct = (100 - (parseFloat(affPct) || 0)).toFixed(2);
                previewAffiliate.textContent = 'KES ' + Number(aff).toLocaleString() + ' (' + affPct + '%)';
                previewAdmin.textContent = 'KES ' + Number(admin).toLocaleString() + ' (' + adminPct + '%)';
            }
            // attach single oninput handler (avoid duplicating listeners)
            percentInput.oninput = updatePreview;
            // initialize
            updatePreview();
        } else if (previewBlock) {
            previewBlock.style.display = 'none';
        }
    }catch(e){console.error('preview setup failed', e);} 
}

function closeAffiliateModal(){
    const b = document.getElementById('affiliateModalBackdrop');
    const m = document.getElementById('affiliateModal');
    if(b) b.style.display = 'none';
    if(m) m.style.display = 'none';
}

async function saveAffiliate(productId){
    const percent = parseFloat(document.getElementById('affPercent').value || '');
    if (isNaN(percent) || percent < 0) {
        alert('Enter a valid percent (0 or greater)');
        return;
    }

    try{
        const resp = await fetch('set_product_affiliate_percent.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'product_id=' + encodeURIComponent(productId) + '&percent=' + encodeURIComponent(percent)
        });
        let text = await resp.text();
        let j = null;
        try { j = JSON.parse(text); } catch(e) { j = null; }
        if (!resp.ok || !j || !j.success) {
            const msg = j && j.message ? j.message : text || 'Failed to update percent';
            // if DB not migrated, offer to create column automatically and retry
            if (j && j.message && j.message.toLowerCase().includes('database not migrated')) {
                if (confirm('Your database is missing the affiliate_percent column. Create it now? This will ALTER the products table.')) {
                    try{
                        const resp2 = await fetch('set_product_affiliate_percent.php', {
                            method: 'POST',
                            headers: {'Content-Type':'application/x-www-form-urlencoded'},
                            body: 'product_id=' + encodeURIComponent(productId) + '&percent=' + encodeURIComponent(percent) + '&migrate=1'
                        });
                        const t2 = await resp2.text();
                        let j2 = null; try { j2 = JSON.parse(t2); } catch(e) { j2 = null; }
                        if (!resp2.ok || !j2 || !j2.success) {
                            const m2 = j2 && j2.message ? j2.message : t2 || 'Migration failed';
                            alert('Migration failed: ' + m2);
                            return;
                        }
                        alert('Database updated and percent saved');
                        closeAffiliateModal();
                        location.reload();
                        return;
                    } catch(err){ console.error('migration error', err); alert('Migration network error'); return; }
                }
                return;
            }
            alert('Error: ' + msg);
            return;
        }

        // success — update DOM row attribute and preview, then close
        const tr = document.getElementById('prod-' + productId);
        if (tr) tr.setAttribute('data-affpercent', String(percent));
        alert('Affiliate percent saved');
        closeAffiliateModal();
        // reload to ensure UI reflects change
        location.reload();
    } catch(err){
        console.error('saveAffiliate error', err);
        alert('Network error');
        return;
    }
}
</script>

  <script src="../loader.js"></script>
</body>
</html>
