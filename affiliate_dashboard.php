<?php
session_start();
include 'db_config.php';

// Check if affiliate is logged in
if (!isset($_SESSION['affiliate_logged_in']) || $_SESSION['affiliate_logged_in'] !== true) {
    header("Location: affiliate_login.php");
    exit;
}

$affiliate_id = (int)($_SESSION['affiliate_id'] ?? 0);
$affiliate_name = htmlspecialchars($_SESSION['affiliate_name'] ?? 'Affiliate', ENT_QUOTES, 'UTF-8');
$affiliate_email = htmlspecialchars($_SESSION['affiliate_email'] ?? '', ENT_QUOTES, 'UTF-8');

// Get affiliate information
$stmt = $conn->prepare("SELECT id, name, email, phone, referral_code, balance, total_earnings, active_referrals, withdrawn, status FROM affiliates WHERE id = ?");
$stmt->bind_param('i', $affiliate_id);
$stmt->execute();
$result = $stmt->get_result();
$affiliate_data = $result->fetch_assoc();
$stmt->close();

// Get affiliate referral link
$affiliate_link = rtrim((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/?ref=' . $affiliate_data['referral_code'];

// Get referral statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_clicks,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_sales,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_sales,
        SUM(CASE WHEN status = 'confirmed' THEN commission ELSE 0 END) as earned_commission
    FROM affiliate_clicks 
    WHERE affiliate_id = ?
");
$stats_stmt->bind_param('i', $affiliate_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc() ?? ['total_clicks' => 0, 'pending_sales' => 0, 'confirmed_sales' => 0, 'earned_commission' => 0];
$stats_stmt->close();

// Get approved payments for this affiliate (only count payments that are approved)
$approved_payments_stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_approved 
    FROM affiliate_payments 
    WHERE affiliate_id = ? AND status = 'approved'
");
$approved_payments_stmt->bind_param('i', $affiliate_id);
$approved_payments_stmt->execute();
$approved_result = $approved_payments_stmt->get_result();
$approved_payments = (float)($approved_result->fetch_assoc()['total_approved'] ?? 0);
$approved_payments_stmt->close();

// Get available balance (earned commission that hasn't been paid yet)
$available_balance = $stats['earned_commission'] - $approved_payments;
$available_balance = max(0, $available_balance);

// Get recent referrals
$referrals_stmt = $conn->prepare("
    SELECT id, product_name, order_code, commission, status, created_at 
    FROM affiliate_clicks 
    WHERE affiliate_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$referrals_stmt->bind_param('i', $affiliate_id);
$referrals_stmt->execute();
$referrals_result = $referrals_stmt->get_result();
$referrals = [];
while ($row = $referrals_result->fetch_assoc()) {
    $referrals[] = $row;
}
$referrals_stmt->close();

// Get payment history
$payments_stmt = $conn->prepare("
    SELECT id, amount, method, status, transaction_id, created_at 
    FROM affiliate_payments 
    WHERE affiliate_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$payments_stmt->bind_param('i', $affiliate_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
$payments = [];
while ($row = $payments_result->fetch_assoc()) {
    $payments[] = $row;
}
$payments_stmt->close();

// Get withdrawal history (from affiliate_payments table, not withdrawals)
$withdrawals_stmt = $conn->prepare("
    SELECT id, amount, method, status, transaction_id, created_at, NULL as processed_at
    FROM affiliate_payments
    WHERE affiliate_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$withdrawals_stmt->bind_param('i', $affiliate_id);
$withdrawals_stmt->execute();
$withdrawals_result = $withdrawals_stmt->get_result();
$withdrawals = [];
while ($row = $withdrawals_result->fetch_assoc()) {
    $withdrawals[] = $row;
}
$withdrawals_stmt->close();

// Monthly earnings chart data
$monthly_stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(commission) as amount
    FROM affiliate_clicks
    WHERE affiliate_id = ? AND status = 'confirmed'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$monthly_stmt->bind_param('i', $affiliate_id);
$monthly_stmt->execute();
$monthly_result = $monthly_stmt->get_result();
$monthly_data = [];
while ($row = $monthly_result->fetch_assoc()) {
    $monthly_data[] = $row;
}
$monthly_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliate Dashboard - Onrizo Shop</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #764ba2;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
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
            line-height: 1.6;
        }

        /* HEADER */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-user span {
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* MAIN CONTAINER */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* TABS */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--border);
            flex-wrap: wrap;
        }

        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: var(--text-light);
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            bottom: -2px;
        }

        .tab-button.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* GRID LAYOUT */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .stat-card {
            text-align: center;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-subtext {
            font-size: 12px;
            color: var(--text-light);
        }

        /* REFERRAL LINK SECTION */
        .referral-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 2px solid var(--primary);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .referral-section h3 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 18px;
        }

        .referral-link-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .referral-link-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: monospace;
            font-size: 13px;
            background: var(--white);
            color: var(--text);
        }

        .copy-btn {
            padding: 12px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            background: var(--primary-dark);
        }

        .referral-code {
            display: inline-block;
            padding: 6px 12px;
            background: var(--primary);
            color: white;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
        }

        /* TABLE */
        .table-container {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
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
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-light);
            font-size: 14px;
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
            padding: 15px;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-confirmed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-paid {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-pending-payment {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        /* CHART */
        .chart-container {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            position: relative;
            height: 400px;
        }

        .chart-container h3 {
            margin-bottom: 20px;
            color: var(--text);
            font-size: 18px;
        }

        /* NO DATA MESSAGE */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .empty-state-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 10px;
        }

        /* BUTTONS */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--border);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-block {
            width: 100%;
            margin-top: 15px;
        }

        /* PROFILE SECTION */
        .profile-form {
            background: var(--white);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input:disabled {
            background: var(--bg);
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .container {
                padding: 20px 15px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .referral-link-container {
                flex-direction: column;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px;
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
    <!-- HEADER -->
    <div class="header">
        <div class="header-content">
            <h1>💰 Affiliate Dashboard</h1>
            <div class="header-user">
                <span>Welcome, <?php echo $affiliate_name; ?></span>
                <a href="affiliate_logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="container">
        <!-- TABS -->
        <div class="tabs">
            <button class="tab-button active" onclick="switchTab('overview')">📊 Overview</button>
            <button class="tab-button" onclick="switchTab('referrals')">🔗 Referrals</button>
            <button class="tab-button" onclick="switchTab('payments')">💵 Payments</button>
            <button class="tab-button" onclick="switchTab('withdrawals')">💸 Withdrawals</button>
            <button class="tab-button" onclick="switchTab('profile')">👤 Profile</button>
        </div>

        <!-- OVERVIEW TAB -->
        <div id="overview" class="tab-content active">
            <!-- REFERRAL LINK SECTION -->
            <div class="referral-section">
                <h3>🌐 Your Referral Link</h3>
                <p style="margin-bottom: 15px; color: var(--text-light);">Share this link to earn commissions on every sale</p>
                <div class="referral-link-container">
                    <input type="text" class="referral-link-input" id="referralLink" value="<?php echo htmlspecialchars($affiliate_link); ?>" readonly>
                    <button class="copy-btn" onclick="copyToClipboard()">📋 Copy</button>
                </div>
                <div style="color: var(--text-light); font-size: 13px;">
                    Your Referral Code: <span class="referral-code"><?php echo htmlspecialchars($affiliate_data['referral_code']); ?></span>
                </div>
            </div>

            <!-- KEY METRICS (Top Row) -->
            <h3 style="margin-bottom: 15px;">📊 Key Performance Metrics</h3>
            <div class="grid">
                <div class="card stat-card">
                    <div class="stat-label">Total Clicks</div>
                    <div class="stat-value"><?php echo number_format($stats['total_clicks']); ?></div>
                    <div class="stat-subtext">Referral visits</div>
                </div>

                <div class="card stat-card">
                    <div class="stat-label">Confirmed Sales</div>
                    <div class="stat-value" style="color: var(--success);"><?php echo number_format($stats['confirmed_sales']); ?></div>
                    <div class="stat-subtext">Verified sales</div>
                </div>

                <div class="card stat-card">
                    <div class="stat-label">Pending Sales</div>
                    <div class="stat-value" style="color: var(--warning);"><?php echo number_format($stats['pending_sales']); ?></div>
                    <div class="stat-subtext">Awaiting confirmation</div>
                </div>

                <div class="card stat-card">
                    <div class="stat-label">Total Earned</div>
                    <div class="stat-value" style="color: var(--success);">KES <?php echo number_format($stats['earned_commission'], 2); ?></div>
                    <div class="stat-subtext">All-time earnings</div>
                </div>
            </div>

            <!-- FINANCIAL SUMMARY (Middle Row) -->
            <h3 style="margin-bottom: 15px; margin-top: 30px;">💰 Financial Summary</h3>
            <div class="grid">
                <div class="card stat-card" style="border-left: 4px solid var(--success);">
                    <div class="stat-label">Available Balance</div>
                    <div class="stat-value" style="color: var(--success);">KES <?php echo number_format($affiliate_data['balance'], 2); ?></div>
                    <div class="stat-subtext">Ready for withdrawal</div>
                </div>

                <div class="card stat-card" style="border-left: 4px solid var(--primary);">
                    <div class="stat-label">Approved Payments</div>
                    <div class="stat-value" style="color: var(--primary);">KES <?php echo number_format($approved_payments, 2); ?></div>
                    <div class="stat-subtext">Already approved</div>
                </div>

                <div class="card stat-card" style="border-left: 4px solid var(--warning);">
                    <div class="stat-label">Pending Approval</div>
                    <div class="stat-value" style="color: var(--warning);">KES <?php echo number_format($available_balance, 2); ?></div>
                    <div class="stat-subtext">Awaiting admin review</div>
                </div>

                <div class="card stat-card" style="border-left: 4px solid var(--primary);">
                    <div class="stat-label">Total Withdrawn</div>
                    <div class="stat-value">KES <?php echo number_format($affiliate_data['withdrawn'], 2); ?></div>
                    <div class="stat-subtext">Paid out to date</div>
                </div>
            </div>

            <!-- EARNINGS CHART -->
            <div class="chart-container" style="margin-top: 30px;">
                <h3>📈 Monthly Earnings Trend</h3>
                <canvas id="earningsChart"></canvas>
            </div>
        </div>

        <!-- REFERRALS TAB -->
        <div id="referrals" class="tab-content">
            <div class="table-container">
                <h3 style="margin-bottom: 20px;">Recent Referrals</h3>
                <?php if (empty($referrals)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🔗</div>
                        <div class="empty-state-title">No referrals yet</div>
                        <p>Share your referral link to start earning commissions!</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Order ID</th>
                                <th>Commission</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($referrals as $referral): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($referral['product_name']); ?></td>
                                    <td><code style="background: var(--bg); padding: 4px 8px; border-radius: 4px; font-size: 12px;"><?php echo htmlspecialchars($referral['order_code']); ?></code></td>
                                    <td><strong>KES <?php echo number_format($referral['commission'], 2); ?></strong></td>
                                    <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $referral['status'])); ?>"><?php echo ucfirst($referral['status']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($referral['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- PAYMENTS TAB -->
        <div id="payments" class="tab-content">
            <div style="margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="requestWithdrawal()">💸 Request Payment</button>
            </div>

            <div class="table-container">
                <h3 style="margin-bottom: 20px;">Payment Request History</h3>
                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">💵</div>
                        <div class="empty-state-title">No payment requests yet</div>
                        <p>Your payment requests will appear here once you request a payment.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Transaction ID</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): 
                                // Map payment status to approval status display
                                $approval_status = ucfirst($payment['status']);
                                $approval_color = match($payment['status']) {
                                    'pending' => 'var(--warning)',
                                    'approved' => 'var(--success)',
                                    'paid' => 'var(--primary)',
                                    default => 'var(--text-light)'
                                };
                            ?>
                                <tr>
                                    <td><strong>KES <?php echo number_format($payment['amount'], 2); ?></strong></td>
                                    <td><?php echo ucfirst($payment['method']); ?></td>
                                    <td><span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; background: <?php echo $approval_color; ?>;"><?php echo $approval_status; ?></span></td>
                                    <td><code style="background: var(--bg); padding: 4px 8px; border-radius: 4px; font-size: 12px;"><?php echo htmlspecialchars($payment['transaction_id']); ?></code></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- WITHDRAWALS TAB -->
        <div id="withdrawals" class="tab-content">
            <div style="margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="requestWithdrawal()">💸 Request Withdrawal</button>
            </div>

            <div class="table-container">
                <h3 style="margin-bottom: 20px;">💸 Withdrawal/Payment Requests</h3>
                <?php if (empty($withdrawals)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">💸</div>
                        <div class="empty-state-title">No withdrawal requests yet</div>
                        <p>Request a withdrawal to move your earnings to your M-Pesa or bank account.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Request Date</th>
                                <th>Transaction ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdrawals as $wd):
                                $status_color = match($wd['status']) {
                                    'pending' => 'var(--warning)',
                                    'approved' => 'var(--success)',
                                    'paid' => 'var(--primary)',
                                    'rejected' => 'var(--danger)',
                                    default => 'var(--text-light)'
                                };
                                $status_icon = match($wd['status']) {
                                    'pending' => '⏳',
                                    'approved' => '✅',
                                    'paid' => '🎉',
                                    'rejected' => '❌',
                                    default => '◯'
                                };
                            ?>
                                <tr>
                                    <td><strong>KES <?php echo number_format($wd['amount'], 2); ?></strong></td>
                                    <td><?php echo ucfirst($wd['method']); ?></td>
                                    <td>
                                        <span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; background: <?php echo $status_color; ?>;">
                                            <?php echo $status_icon; ?> <?php echo ucfirst($wd['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y H:i', strtotime($wd['created_at'])); ?></td>
                                    <td><code style="background: var(--bg); padding: 4px 8px; border-radius: 4px; font-size: 11px;"><?php echo htmlspecialchars($wd['transaction_id']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- PROFILE TAB -->
        <div id="profile" class="tab-content">
            <div class="profile-form">
                <h3 style="margin-bottom: 25px;">Account Information</h3>
                <form method="POST" action="affiliate_update_profile.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($affiliate_data['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($affiliate_data['email']); ?>" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($affiliate_data['phone']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="status">Account Status</label>
                            <input type="text" id="status" name="status" value="<?php echo ucfirst($affiliate_data['status']); ?>" disabled>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bank_details">Bank/M-Pesa Details</label>
                        <textarea id="bank_details" name="bank_details" rows="4" placeholder="Add your M-Pesa number or bank account details for payments"><?php echo isset($affiliate_data['bank_details']) ? htmlspecialchars($affiliate_data['bank_details']) : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <!-- WITHDRAWAL MODAL -->
    <div id="withdrawalModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%;">
            <h3 style="margin-bottom: 20px;">Request Withdrawal</h3>
            <form method="POST" action="affiliate_request_withdrawal.php">
                <div class="form-group">
                    <label for="amount">Amount (KES)</label>
                    <input type="number" id="amount" name="amount" min="500" step="100" placeholder="Minimum: 500 KES" required>
                    <div style="font-size: 12px; color: var(--text-light); margin-top: 5px;">Available: KES <?php echo number_format($affiliate_data['balance'], 2); ?></div>
                </div>

                <div class="form-group">
                    <label for="method">Payment Method</label>
                    <select name="method" id="method" style="width: 100%; padding: 12px 15px; border: 1px solid var(--border); border-radius: 8px;">
                        <option value="mpesa">M-Pesa</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="document.getElementById('withdrawalModal').style.display = 'none';">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Request Withdrawal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function copyToClipboard() {
            const link = document.getElementById('referralLink');
            link.select();
            document.execCommand('copy');
            alert('Referral link copied to clipboard!');
        }

        function requestWithdrawal() {
            document.getElementById('withdrawalModal').style.display = 'flex';
        }

        // Initialize chart
        const monthlyData = <?php echo json_encode(array_reverse($monthly_data)); ?>;
        if (monthlyData.length > 0) {
            const ctx = document.getElementById('earningsChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: monthlyData.map(d => new Date(d.month).toLocaleDateString('en-US', { year: 'numeric', month: 'short' })),
                    datasets: [{
                        label: 'Commission Earned',
                        data: monthlyData.map(d => d.amount),
                        borderColor: 'var(--primary)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: 'var(--primary)',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                            }
                        }
                    }
                }
            });
        }
    </script>
  <script src="loader.js"></script>
</body>
</html>
