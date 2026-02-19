<?php
session_start();
include '../db_config.php';

// ✅ NO LOGIN REQUIRED - Main store dashboard is public
$admin_id = (int)($_SESSION['admin_id'] ?? 0);

// Track visitor (log to session, not DB for simplicity)
if (!isset($_SESSION['visit_time'])) {
    $_SESSION['visit_time'] = time();
}
$visit_time = $_SESSION['visit_time'];

// Handle product deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    if ($product_id > 0) {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param('i', $product_id);
        if ($stmt->execute()) {
            $msg = "✅ Product deleted successfully";
        }
        $stmt->close();
    }
}

// Handle user deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id > 0) {
        $stmt = $conn->prepare("DELETE FROM affiliates WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            $msg = "✅ User deleted successfully";
        }
        $stmt->close();
    }
}

// Handle payment approval
if (isset($_POST['action']) && $_POST['action'] === 'approve_payment') {
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    if ($payment_id > 0) {
        $stmt = $conn->prepare("SELECT affiliate_id, amount FROM affiliate_payments WHERE id = ?");
        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            $affiliate_id = $row['affiliate_id'];
            $amount = $row['amount'];
            
            // Update payment status to approved
            $stmt2 = $conn->prepare("UPDATE affiliate_payments SET status = 'approved', processed_at = NOW() WHERE id = ?");
            $stmt2->bind_param('i', $payment_id);
            $stmt2->execute();
            $stmt2->close();
            
            // Deduct from affiliate balance
            $stmt3 = $conn->prepare("UPDATE affiliates SET balance = balance - ? WHERE id = ?");
            $stmt3->bind_param('di', $amount, $affiliate_id);
            $stmt3->execute();
            $stmt3->close();
            
            $msg = "✅ Payment approved! KES " . number_format($amount, 0) . " deducted from affiliate account";
        }
        $stmt->close();
    }
}

// Handle withdrawal verification
if (isset($_POST['action']) && $_POST['action'] === 'verify_withdrawal') {
    $withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
    if ($withdrawal_id > 0) {
        $stmt = $conn->prepare("SELECT id, amount, commission_amount, net_amount FROM withdrawals WHERE id = ?");
        $stmt->bind_param('i', $withdrawal_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            // Update withdrawal status to verified
            $stmt2 = $conn->prepare("UPDATE withdrawals SET status = 'Verified', processed_at = NOW() WHERE id = ?");
            $stmt2->bind_param('i', $withdrawal_id);
            $stmt2->execute();
            $stmt2->close();
            
            $msg = "✅ Withdrawal verified! Requested: KES " . number_format($row['amount'], 0) . ", Net: KES " . number_format($row['net_amount'], 0) . ", Commission: KES " . number_format($row['commission_amount'], 0);
        }
        $stmt->close();
    }
}

// Handle withdrawal rejection
if (isset($_POST['action']) && $_POST['action'] === 'reject_withdrawal') {
    $withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
    if ($withdrawal_id > 0) {
        $stmt = $conn->prepare("SELECT admin_id, amount FROM withdrawals WHERE id = ?");
        $stmt->bind_param('i', $withdrawal_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            $affiliate_id = $row['admin_id'];
            $amount = $row['amount'];
            
            // Update withdrawal status to rejected
            $stmt2 = $conn->prepare("UPDATE withdrawals SET status = 'Rejected', processed_at = NOW() WHERE id = ?");
            $stmt2->bind_param('i', $withdrawal_id);
            $stmt2->execute();
            $stmt2->close();
            
            // Restore balance to affiliate
            $stmt3 = $conn->prepare("UPDATE affiliates SET balance = balance + ? WHERE id = ?");
            $stmt3->bind_param('di', $amount, $affiliate_id);
            $stmt3->execute();
            $stmt3->close();
            
            $msg = "❌ Withdrawal rejected! KES " . number_format($amount, 0) . " restored to affiliate balance";
        }
        $stmt->close();
    }
}

// Handle withdrawal mark as paid
if (isset($_POST['action']) && $_POST['action'] === 'mark_withdrawal_paid') {
    $withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
    if ($withdrawal_id > 0) {
        $stmt = $conn->prepare("SELECT id, amount, net_amount, commission_amount FROM withdrawals WHERE id = ?");
        $stmt->bind_param('i', $withdrawal_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            // Update withdrawal status to paid
            $stmt2 = $conn->prepare("UPDATE withdrawals SET status = 'Paid', processed_at = NOW() WHERE id = ?");
            $stmt2->bind_param('i', $withdrawal_id);
            $stmt2->execute();
            $stmt2->close();
            
            // Insert commission to owner ledger (idempotent)
            $commission = (float)$row['commission_amount'];
            if ($commission > 0) {
                $chk = $conn->prepare("SELECT id FROM owner_ledger WHERE withdrawal_id = ? AND type = 'commission' LIMIT 1");
                $chk->bind_param('i', $withdrawal_id);
                $chk->execute();
                $eres = $chk->get_result();
                if (!$eres || $eres->num_rows === 0) {
                    $desc = "Commission for withdrawal #" . $withdrawal_id;
                    $ins = $conn->prepare("INSERT INTO owner_ledger (withdrawal_id, admin_id, amount, type, description) VALUES (?, ?, ?, 'commission', ?)");
                    $ins->bind_param('iids', $withdrawal_id, $admin_id, $commission, $desc);
                    $ins->execute();
                }
            }
            
            $msg = "✅ Withdrawal marked as Paid! Net: KES " . number_format($row['net_amount'], 0) . ", Commission: KES " . number_format($commission, 0);
        }
        $stmt->close();
    }
}

// ============ STORE OVERVIEW DATA ============
$metrics = ['total_orders' => 0, 'total_revenue' => 0, 'total_products' => 0, 'active_affiliates' => 0, 'pending_orders' => 0, 'completed_orders' => 0, 'affiliate_commissions' => 0];

$stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) as count, COALESCE(SUM(oi.subtotal), 0) as revenue FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.admin_id = ?");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $metrics['total_orders'] = (int)$row['count'];
    $metrics['total_revenue'] = (float)$row['revenue'];
}
$stmt->close();

$stmt = $conn->prepare("SELECT o.status, COUNT(DISTINCT o.id) as count FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.admin_id = ? GROUP BY o.status");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if ($row['status'] === 'Pending') $metrics['pending_orders'] = (int)$row['count'];
    elseif ($row['status'] === 'Completed') $metrics['completed_orders'] = (int)$row['count'];
}
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE admin_id = ? AND deleted = 0");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $metrics['total_products'] = (int)$row['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(DISTINCT ac.affiliate_id) as count FROM affiliate_clicks ac JOIN products p ON ac.product_id = p.id WHERE p.admin_id = ?");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $metrics['active_affiliates'] = (int)$row['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(ac.commission), 0) as total FROM affiliate_clicks ac JOIN products p ON ac.product_id = p.id WHERE ac.status = 'confirmed' AND p.admin_id = ?");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $metrics['affiliate_commissions'] = (float)$row['total'];
$stmt->close();

// Commission revenue from withdrawals (owner commission)
$owner_commission = 0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM owner_ledger WHERE type = 'commission'");
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $owner_commission = (float)$row['total'];
$stmt->close();

// Withdrawal history with commission details
$withdrawal_history = [];
$stmt = $conn->prepare("SELECT id, amount, commission_amount, net_amount, status, requested_at FROM withdrawals WHERE status IN ('Verified', 'Paid') ORDER BY requested_at DESC LIMIT 10");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $withdrawal_history[] = $row;
$stmt->close();

// ===== Withdrawable balance (admin) =====
// completed revenue for this admin (gross)
$withdrawable_available = 0.0;
$stmtW = $conn->prepare("SELECT COALESCE(SUM(oi.subtotal),0) as bal FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE o.status = 'Completed' AND p.admin_id = ?");
$stmtW->bind_param('i', $admin_id);
$stmtW->execute();
$resW = $stmtW->get_result();
$completed_admin = $resW ? floatval($resW->fetch_assoc()['bal']) : 0.0;
$stmtW->close();

// platform commission (5%) applies to all completed sales
$platform_commission_percent = 5.0;
$platform_commission = round($completed_admin * ($platform_commission_percent / 100), 2);
$completed_net = max(0, $completed_admin - $platform_commission);

// affiliate commissions owed for this admin (confirmed clicks/earnings)
$stmtAffO = $conn->prepare("SELECT COALESCE(SUM(ac.commission),0) as owed FROM affiliate_clicks ac JOIN products p ON ac.product_id = p.id WHERE p.admin_id = ? AND ac.status = 'confirmed'");
$stmtAffO->bind_param('i', $admin_id);
$stmtAffO->execute();
$resAffO = $stmtAffO->get_result();
$affiliate_owed = $resAffO ? floatval($resAffO->fetch_assoc()['owed']) : 0.0;
$stmtAffO->close();

// sum of PAID withdrawals (use net_amount as actual cash disbursed)
$stmtPaid = $conn->prepare("SELECT COALESCE(SUM(net_amount),0) as paid FROM withdrawals WHERE status = 'Paid' AND admin_id = ?");
$stmtPaid->bind_param('i', $admin_id);
$stmtPaid->execute();
$resPaid = $stmtPaid->get_result();
$paid_withdrawals = $resPaid ? floatval($resPaid->fetch_assoc()['paid']) : 0.0;
$stmtPaid->close();

// reserved withdrawals (gross amounts still pending)
$stmtRes = $conn->prepare("SELECT COALESCE(SUM(amount),0) as reserved FROM withdrawals WHERE status IN ('Reserved','Pending','Verified') AND admin_id = ?");
$stmtRes->bind_param('i', $admin_id);
$stmtRes->execute();
$resRes = $stmtRes->get_result();
$reserved_withdrawals = $resRes ? floatval($resRes->fetch_assoc()['reserved']) : 0.0;
$stmtRes->close();

$withdrawable_available = max(0, $completed_net - $affiliate_owed - $paid_withdrawals - $reserved_withdrawals);
// =========================================

// Recent orders
$recent_orders = [];
$stmt = $conn->prepare("SELECT DISTINCT o.id, o.customer_name, o.customer_email, o.total_amount, o.status, o.order_date FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.admin_id = ? GROUP BY o.id ORDER BY o.order_date DESC LIMIT 5");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $recent_orders[] = $row;
$stmt->close();

// Top products (platform-wide)
$top_products = [];
$stmt = $conn->prepare("SELECT p.id, p.name, COUNT(oi.id) as sales, SUM(oi.subtotal) as revenue FROM products p LEFT JOIN order_items oi ON p.id = oi.product_id GROUP BY p.id ORDER BY sales DESC LIMIT 5");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $top_products[] = $row;
$stmt->close();

// Top affiliates (platform-wide)
$top_affiliates = [];
$stmt = $conn->prepare("SELECT af.id, af.name, af.email, COUNT(ac.id) as clicks, COALESCE(SUM(ac.commission), 0) as commissions FROM affiliates af LEFT JOIN affiliate_clicks ac ON af.id = ac.affiliate_id GROUP BY af.id ORDER BY commissions DESC LIMIT 5");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $top_affiliates[] = $row;
$stmt->close();

// Monthly revenue
$monthly_data = [];
$stmt = $conn->prepare("SELECT DATE_FORMAT(o.order_date, '%Y-%m') as month, COALESCE(SUM(oi.subtotal), 0) as revenue FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.admin_id = ? AND o.order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(o.order_date, '%Y-%m') ORDER BY month ASC");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $monthly_data[] = $row;
$stmt->close();

// ============ PLATFORM-WIDE DATA ============
// All products
$all_products = [];
$query = "SELECT p.id, p.name, p.price, p.admin_id, a.email as admin_email, p.date_added FROM products p LEFT JOIN admins a ON p.admin_id = a.id WHERE p.deleted = 0 ORDER BY p.date_added DESC LIMIT 100";
$res = $conn->query($query);
if ($res) while ($row = $res->fetch_assoc()) $all_products[] = $row;

// All admins
$all_admins = [];
$res = $conn->query("SELECT id, email, name, created_at FROM admins ORDER BY created_at DESC LIMIT 100");
if ($res) while ($row = $res->fetch_assoc()) $all_admins[] = $row;

// All affiliates
$all_affiliates = [];
$res = $conn->query("SELECT id, name, email, phone, referral_code, balance, status, created_at FROM affiliates ORDER BY created_at DESC LIMIT 100");
if ($res) while ($row = $res->fetch_assoc()) $all_affiliates[] = $row;

// Total platform sales
$total_sales = 0;
$res = $conn->query("SELECT COALESCE(SUM(oi.subtotal), 0) as total FROM order_items oi");
if ($res && $row = $res->fetch_assoc()) $total_sales = (float)$row['total'];

// Pending payments
$pending_payments = [];
$res = $conn->query("SELECT ap.id, ap.affiliate_id, af.name, af.email, ap.amount, ap.method, ap.status, ap.created_at FROM affiliate_payments ap JOIN affiliates af ON ap.affiliate_id = af.id WHERE ap.status = 'pending' ORDER BY ap.created_at DESC LIMIT 50");
if ($res) while ($row = $res->fetch_assoc()) $pending_payments[] = $row;

// Pending withdrawals (Reserved and Verified)
$pending_withdrawals = [];
$res = $conn->query("SELECT w.id, w.admin_id, 
  COALESCE(af.name, ad.email) as name,
  COALESCE(af.email, ad.email) as email,
  w.amount, w.commission_amount, w.net_amount, w.destination, w.status, w.requested_at 
FROM withdrawals w 
LEFT JOIN affiliates af ON w.admin_id = af.id
LEFT JOIN admins ad ON w.admin_id = ad.id
WHERE w.status IN ('Reserved', 'Verified') ORDER BY w.requested_at DESC LIMIT 50");
if ($res) while ($row = $res->fetch_assoc()) $pending_withdrawals[] = $row;

// Total commissions
$total_commissions = 0;
$res = $conn->query("SELECT COALESCE(SUM(commission), 0) as total FROM affiliate_clicks WHERE status = 'confirmed'");
if ($res && $row = $res->fetch_assoc()) $total_commissions = (float)$row['total'];

// Live visitors (count unique visitors in past 24 hours)
$live_visitors = 0;
$res = $conn->query("SELECT COUNT(DISTINCT customer_email) as visitors FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
if ($res && $row = $res->fetch_assoc()) $live_visitors = (int)$row['visitors'];

// All orders (for new All Orders tab)
$all_orders = [];
$res = $conn->query("SELECT o.id, o.customer_name, o.customer_email, o.customer_phone, o.total_amount, o.status, o.order_date FROM orders o ORDER BY o.order_date DESC LIMIT 500");
if ($res) while ($row = $res->fetch_assoc()) $all_orders[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Dashboard - Onrizo</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 {
            color: #333;
            font-size: 28px;
        }

        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-links a {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: background 0.3s;
        }

        .nav-links a:hover {
            background: #764ba2;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 10px 20px;
            background: #f0f0f0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: #333;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: #667eea;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            margin-top: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }

        .metric-card.success { border-left-color: #28a745; }
        .metric-card.warning { border-left-color: #ffc107; }
        .metric-card.danger { border-left-color: #dc3545; }

        .metric-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .metric-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .metric-subtitle {
            color: #999;
            font-size: 12px;
            margin-top: 8px;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .table-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: #f8f9fa;
        }

        table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 13px;
            border-bottom: 2px solid #e9ecef;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            color: #666;
            font-size: 14px;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-active {
            background: #d4edda;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .item-name {
            color: #333;
            font-weight: 500;
        }

        .item-value {
            color: #667eea;
            font-weight: 700;
            font-size: 16px;
        }

        .chart-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        @media (max-width: 768px) {
            header {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                width: 100%;
                justify-content: center;
            }

            .tabs {
                flex-direction: column;
            }

            .tab-btn {
                width: 100%;
            }

            .content-grid {
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
    <div class="container">
        <header>
            <div>
                <h1>🏪 Store Dashboard</h1>
                <p style="color: #999; font-size: 14px; margin-top: 5px;">Complete store management & analytics</p>
            </div>
            <div class="nav-links">
                
                <a href="../index.html">Home</a>
            </div>
        </header>

        <?php if (isset($msg)): ?>
            <div class="success-msg" id="flashMsg"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('overview')">📊 Overview</button>
            <button class="tab-btn" onclick="showTab('visitors')">👥 Live Visitors</button>
            <button class="tab-btn" onclick="showTab('orders')">📦 All Orders</button>
            <button class="tab-btn" onclick="showTab('products')">📦 Products</button>
            <button class="tab-btn" onclick="showTab('admins')">👨‍💼 Admins</button>
            <button class="tab-btn" onclick="showTab('affiliates')">🤝 Affiliates</button>
            <button class="tab-btn" onclick="showTab('payments')">💳 Payments</button>
            <button class="tab-btn" onclick="showTab('withdrawals')">💸 Withdrawals</button>
        </div>

        <!-- TAB 1: OVERVIEW -->
        <div id="overview" class="tab-content active">
            <div class="section-title">📊 Key Metrics</div>
            <div class="metrics-grid">
                <div class="metric-card success">
                    <div class="metric-label">💰 Total Revenue</div>
                    <div class="metric-value">KES <?php echo number_format($total_sales, 0); ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">📦 Total Products</div>
                    <div class="metric-value"><?php echo count($all_products); ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">👥 Total Admins</div>
                    <div class="metric-value"><?php echo count($all_admins); ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">🤝 Total Affiliates</div>
                    <div class="metric-value"><?php echo count($all_affiliates); ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">💳 Total Commissions</div>
                    <div class="metric-value">KES <?php echo number_format($total_commissions, 0); ?></div>
                </div>
                <div class="metric-card success">
                    <div class="metric-label">💵 Platform Commission Revenue</div>
                    <div class="metric-value">KES <?php echo number_format($owner_commission, 0); ?></div>
                </div>
                <div class="metric-card warning">
                    <div class="metric-label">💸 Withdrawable Balance</div>
                    <div class="metric-value">KES <?php echo number_format($withdrawable_available, 0); ?></div>
                    <div class="metric-subtitle">(After 5% platform + affiliate commissions)</div>
                    <div class="metric-subtitle" style="margin-top:6px; color:#666;">
                        Gross completed: KES <?php echo number_format($completed_admin, 0); ?> ·
                        Platform (5%): KES <?php echo number_format($platform_commission, 0); ?> ·
                        Affiliate owed: KES <?php echo number_format($affiliate_owed, 0); ?>
                    </div>
                </div>
                <div class="metric-card danger">
                    <div class="metric-label">⏳ Pending Payments</div>
                    <div class="metric-value"><?php echo count($pending_payments); ?></div>
                </div>
            </div>

            <div class="section-title">💸 Withdrawal History & Commission</div>
            <div class="table-container">
                <?php if (!empty($withdrawal_history)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Amount Requested</th>
                                <th>Commission (5%)</th>
                                <th>Net Payout</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdrawal_history as $wd): ?>
                                <tr>
                                    <td><strong>#<?php echo $wd['id']; ?></strong></td>
                                    <td>KES <?php echo number_format($wd['amount'], 0); ?></td>
                                    <td style="color: #27ae60; font-weight: 600;">KES <?php echo number_format($wd['commission_amount'], 0); ?></td>
                                    <td><strong>KES <?php echo number_format($wd['net_amount'], 0); ?></strong></td>
                                    <td><span class="<?php echo $wd['status'] === 'Paid' ? 'status-completed' : ''; ?> status-badge"><?php echo ucfirst($wd['status']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($wd['requested_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #999; padding: 30px; text-align: center;">No withdrawal history yet</p>
                <?php endif; ?>
            </div>

            <div class="section-title">🚀 Top Performers</div>
            <div class="content-grid">
                <div class="table-container">
                    <div class="chart-title">🛍️ Top Products</div>
                    <?php if (!empty($top_products)): ?>
                        <?php foreach ($top_products as $prod): ?>
                            <div class="list-item">
                                <div>
                                    <div class="item-name"><?php echo htmlspecialchars(substr($prod['name'], 0, 25)); ?></div>
                                    <div style="color: #999; font-size: 12px;"><?php echo $prod['sales']; ?> sales</div>
                                </div>
                                <div class="item-value">KES <?php echo number_format($prod['revenue'], 0); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #999; padding: 20px; text-align: center;">No sales yet</p>
                    <?php endif; ?>
                </div>

                <div class="table-container">
                    <div class="chart-title">🔝 Top Affiliates</div>
                    <?php if (!empty($top_affiliates)): ?>
                        <?php foreach ($top_affiliates as $aff): ?>
                            <div class="list-item">
                                <div>
                                    <div class="item-name"><?php echo htmlspecialchars($aff['name'] ?? $aff['email']); ?></div>
                                    <div style="color: #999; font-size: 12px;"><?php echo $aff['clicks']; ?> clicks</div>
                                </div>
                                <div class="item-value">KES <?php echo number_format($aff['commissions'], 0); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #999; padding: 20px; text-align: center;">No activity yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 2: LIVE VISITORS -->
        <div id="visitors" class="tab-content">
            <div class="section-title">👥 Live Visitors (Last 24 Hours)</div>
            <div class="metrics-grid">
                <div class="metric-card success">
                    <div class="metric-label">👥 Unique Visitors</div>
                    <div class="metric-value"><?php echo $live_visitors; ?></div>
                </div>
            </div>
        </div>

        <!-- TAB 3: ALL ORDERS -->
        <div id="orders" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">All Orders (<?php echo count($all_orders); ?>)</h2>
                <div class="search-box">
                    <input type="text" id="orderSearch" placeholder="Search orders..." onkeyup="filterTable('orderTable', 0)">
                </div>
                <table id="orderTable">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_orders as $order): ?>
                            <tr>
                                <td><strong>#<?php echo $order['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_phone'] ?? '-'); ?></td>
                                <td>KES <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $status_class = $order['status'] === 'Completed' ? 'status-completed' : ($order['status'] === 'Pending' ? 'status-pending' : 'status-' . strtolower($order['status']));
                                    echo '<span class="status-badge ' . $status_class . '">' . htmlspecialchars($order['status']) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: PRODUCTS -->
        <div id="products" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">All Products (<?php echo count($all_products); ?>)</h2>
                <div class="search-box">
                    <input type="text" id="productSearch" placeholder="Search products..." onkeyup="filterTable('productTable', 0)">
                </div>
                <table id="productTable">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Admin</th>
                            <th>Added</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_products as $prod): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($prod['name'], 0, 40)); ?></td>
                                <td>KES <?php echo number_format($prod['price'], 0); ?></td>
                                <td><?php echo htmlspecialchars($prod['admin_email'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d M Y', strtotime($prod['date_added'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this product?');">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                        <button type="submit" class="btn btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 5: ADMINS -->
        <div id="admins" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">All Admins (<?php echo count($all_admins); ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Name</th>
                            <th>Joined</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_admins as $adm): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($adm['email']); ?></td>
                                <td><?php echo htmlspecialchars($adm['name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d M Y', strtotime($adm['created_at'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="viewAdminDetails(<?php echo (int)$adm['id']; ?>)">View</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 6: AFFILIATES -->
        <div id="affiliates" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">All Affiliates (<?php echo count($all_affiliates); ?>)</h2>
                <div class="search-box">
                    <input type="text" id="affiliateSearch" placeholder="Search affiliates..." onkeyup="filterTable('affiliateTable', 0)">
                </div>
                <table id="affiliateTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Code</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_affiliates as $aff): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($aff['name']); ?></td>
                                <td><?php echo htmlspecialchars($aff['email']); ?></td>
                                <td><?php echo htmlspecialchars($aff['phone'] ?? 'N/A'); ?></td>
                                <td><code><?php echo htmlspecialchars($aff['referral_code']); ?></code></td>
                                <td>KES <?php echo number_format($aff['balance'], 0); ?></td>
                                <td><span class="badge <?php echo $aff['status'] === 'active' ? 'badge-active' : 'badge-pending'; ?>"><?php echo ucfirst($aff['status']); ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="viewAffiliateDetails(<?php echo (int)$aff['id']; ?>)">View</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 7: PAYMENTS -->
        <div id="payments" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">Pending Payments (<?php echo count($pending_payments); ?>)</h2>
                <?php if (!empty($pending_payments)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Affiliate</th>
                                <th>Email</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_payments as $pay): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pay['name']); ?></td>
                                    <td><?php echo htmlspecialchars($pay['email']); ?></td>
                                    <td><strong>KES <?php echo number_format($pay['amount'], 0); ?></strong></td>
                                    <td><?php echo ucfirst($pay['method']); ?></td>
                                    <td><span class="badge badge-pending"><?php echo ucfirst($pay['status']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($pay['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="approve_payment">
                                            <input type="hidden" name="payment_id" value="<?php echo $pay['id']; ?>">
                                            <button type="submit" class="btn btn-approve">Approve</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #999; padding: 30px; text-align: center;">No pending payments</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 8: WITHDRAWALS -->
        <div id="withdrawals" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">Pending Withdrawals (<?php echo count($pending_withdrawals); ?>)</h2>
                <?php if (!empty($pending_withdrawals)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Affiliate</th>
                                <th>Email</th>
                                <th>Amount</th>
                                <th>Destination</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_withdrawals as $wd): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($wd['name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($wd['email'] ?? 'N/A'); ?></td>
                                    <td><strong>KES <?php echo number_format($wd['amount'], 0); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($wd['destination']); ?></code></td>
                                    <td><span class="badge badge-pending"><?php echo ucfirst($wd['status']); ?></span></td>
                                    <td><?php echo date('d M Y H:i', strtotime($wd['requested_at'])); ?></td>
                                    <td>
                                        <?php if ($wd['status'] === 'Reserved'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="verify_withdrawal">
                                            <input type="hidden" name="withdrawal_id" value="<?php echo $wd['id']; ?>">
                                            <button type="submit" class="btn btn-approve" title="Verify and approve withdrawal">✓ Verify</button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Reject this withdrawal?');">
                                            <input type="hidden" name="action" value="reject_withdrawal">
                                            <input type="hidden" name="withdrawal_id" value="<?php echo $wd['id']; ?>">
                                            <button type="submit" class="btn btn-delete" title="Reject withdrawal">✗ Reject</button>
                                        </form>
                                        <?php elseif ($wd['status'] === 'Verified'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="mark_withdrawal_paid">
                                            <input type="hidden" name="withdrawal_id" value="<?php echo $wd['id']; ?>">
                                            <button type="submit" class="btn btn-success" title="Mark withdrawal as paid">✓ Mark Paid</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #999; padding: 30px; text-align: center;">No pending withdrawals</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function viewAdminDetails(adminId){
            window.location.href = 'admin_detail.php?id=' + adminId;
        }
        function viewAffiliateDetails(affiliateId){
            window.location.href = 'affiliate_detail.php?id=' + affiliateId;
        }

        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // auto-hide flash notifications
        const flash = document.getElementById('flashMsg');
        if (flash) {
            setTimeout(() => { flash.style.display = 'none'; }, 4000);
        }

        function filterTable(tableId, columnIndex) {
            const input = document.activeElement;
            const filter = input.value.toLowerCase();
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            Array.from(rows).forEach(row => {
                const cells = row.getElementsByTagName('td');
                const cell = cells[columnIndex];
                if (cell) {
                    const text = cell.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                }
            });
        }

        // Auto-refresh live visitors every 30 seconds
        // auto-refresh removed to prevent unwanted reloads
    </script>
  <script src="../loader.js"></script>
</body>
</html>
