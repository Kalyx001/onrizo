<?php
session_start();
include 'db_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin/login.php");
    exit;
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);

// Get affiliate statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_affiliates,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_affiliates,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_affiliates,
        COALESCE(SUM(total_earnings), 0) as total_commissions
    FROM affiliates
");
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Get pending payments
$pending_stmt = $conn->prepare("
    SELECT COUNT(*) as pending_count, COALESCE(SUM(amount), 0) as pending_amount
    FROM affiliate_payments
    WHERE status = 'pending'
");
$pending_stmt->execute();
$pending = $pending_stmt->get_result()->fetch_assoc();
$pending_stmt->close();

// Get top affiliates
$top_stmt = $conn->prepare("
    SELECT a.id, a.name, a.email, a.total_earnings, COUNT(ac.id) as sales_count
    FROM affiliates a
    LEFT JOIN affiliate_clicks ac ON a.id = ac.affiliate_id AND ac.status = 'confirmed'
    GROUP BY a.id
    ORDER BY a.total_earnings DESC
    LIMIT 10
");
$top_stmt->execute();
$top_affiliates = $top_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$top_stmt->close();

// Get recent activity
$activity_stmt = $conn->prepare("
    SELECT 
        'referral' as type,
        a.name as affiliate_name,
        ac.product_name as description,
        ac.commission as amount,
        ac.created_at
    FROM affiliate_clicks ac
    JOIN affiliates a ON ac.affiliate_id = a.id
    UNION ALL
    SELECT 
        'payment' as type,
        a.name as affiliate_name,
        CONCAT('Withdrawal Request - ', ap.method) as description,
        ap.amount,
        ap.created_at
    FROM affiliate_payments ap
    JOIN affiliates a ON ap.affiliate_id = a.id
    ORDER BY created_at DESC
    LIMIT 15
");
$activity_stmt->execute();
$activities = $activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$activity_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliate Management - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --text: #333;
            --text-light: #666;
            --bg: #f9fafb;
            --border: #e5e7eb;
            --white: #ffffff;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        /* LAYOUT */
        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: var(--white);
            border-right: 1px solid var(--border);
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 30px;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .header h1 {
            font-size: 28px;
            color: var(--text);
        }

        /* GRID */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-light);
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-subtext {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }

        /* CARD */
        .card {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .card h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--text);
        }

        /* TABLE */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--bg);
            border-bottom: 2px solid var(--border);
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: var(--bg);
        }

        td {
            padding: 12px;
            font-size: 14px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        /* BUTTONS */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-small {
            padding: 4px 8px;
            font-size: 11px;
        }

        /* SIDEBAR NAV */
        .nav {
            list-style: none;
        }

        .nav li {
            margin-bottom: 8px;
        }

        .nav a {
            display: block;
            padding: 10px 15px;
            color: var(--text);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .nav a:hover {
            background: var(--bg);
            color: var(--primary);
        }

        .nav a.active {
            background: var(--primary);
            color: white;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <h3 style="margin-bottom: 20px;">Admin Menu</h3>
            <ul class="nav">
                <li><a href="admin/dashboard.php">📊 Dashboard</a></li>
                <li><a href="admin/view_products.php">📦 Products</a></li>
                <li><a href="admin/orders.php">📋 Orders</a></li>
                <li><a href="admin_affiliates.php" class="active">🤝 Affiliates</a></li>
                <li><a href="admin/transactions.php">💰 Transactions</a></li>
                <li><a href="admin/logout.php">🚪 Logout</a></li>
            </ul>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="header">
                <h1>🤝 Affiliate Management</h1>
            </div>

            <!-- STATISTICS -->
            <div class="grid">
                <div class="stat-card">
                    <div class="stat-label">Total Affiliates</div>
                    <div class="stat-value"><?php echo $stats['total_affiliates']; ?></div>
                    <div class="stat-subtext"><?php echo $stats['active_affiliates']; ?> active</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">New This Month</div>
                    <div class="stat-value"><?php echo $stats['new_affiliates']; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Total Commissions Paid</div>
                    <div class="stat-value">KES <?php echo number_format($stats['total_commissions'], 0); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Pending Payments</div>
                    <div class="stat-value" style="color: var(--warning);"><?php echo $pending['pending_count']; ?></div>
                    <div class="stat-subtext">KES <?php echo number_format($pending['pending_amount'], 0); ?></div>
                </div>
            </div>

            <!-- TOP AFFILIATES -->
            <div class="card">
                <h2>🏆 Top Affiliates</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Sales</th>
                                <th>Total Earned</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_affiliates as $affiliate): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($affiliate['name']); ?></td>
                                    <td><?php echo htmlspecialchars($affiliate['email']); ?></td>
                                    <td><span class="badge badge-success"><?php echo $affiliate['sales_count']; ?></span></td>
                                    <td><strong>KES <?php echo number_format($affiliate['total_earnings'], 2); ?></strong></td>
                                    <td>
                                        <button class="btn btn-primary btn-small" onclick="viewAffiliateDetails(<?php echo $affiliate['id']; ?>)">Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RECENT ACTIVITY -->
            <div class="card">
                <h2>📊 Recent Activity</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Affiliate</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td>
                                        <?php if ($activity['type'] === 'referral'): ?>
                                            <span class="badge badge-success">Referral</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Payment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['affiliate_name']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                    <td><strong>KES <?php echo number_format($activity['amount'], 2); ?></strong></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        function viewAffiliateDetails(affiliateId) {
            // TODO: Implement modal or navigate to detail page
            alert('View details for affiliate ' + affiliateId);
        }
    </script>
</body>
</html>
