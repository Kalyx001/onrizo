<?php
session_start();
include '../db_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$is_super_admin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 1;

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

// Handle payment approval - FIXED PROCESS
if (isset($_POST['action']) && $_POST['action'] === 'approve_payment') {
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    if ($payment_id > 0) {
        // Get payment details
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

// ============ PLATFORM-WIDE DATA ============
// Get all products
$all_products = [];
$query = "SELECT p.id, p.name, p.price, p.admin_id, a.email as admin_email, p.date_added FROM products p LEFT JOIN admins a ON p.admin_id = a.id WHERE p.deleted = 0 ORDER BY p.date_added DESC LIMIT 100";
$res = $conn->query($query);
if ($res) while ($row = $res->fetch_assoc()) $all_products[] = $row;

// Get all admins
$all_admins = [];
$res = $conn->query("SELECT id, email, name, created_at FROM admins ORDER BY created_at DESC LIMIT 100");
if ($res) while ($row = $res->fetch_assoc()) $all_admins[] = $row;

// Get all affiliates
$all_affiliates = [];
$res = $conn->query("SELECT id, name, email, phone, referral_code, balance, status, created_at FROM affiliates ORDER BY created_at DESC LIMIT 100");
if ($res) while ($row = $res->fetch_assoc()) $all_affiliates[] = $row;

// Get total sales
$total_sales = 0;
$res = $conn->query("SELECT COALESCE(SUM(oi.subtotal), 0) as total FROM order_items oi");
if ($res && $row = $res->fetch_assoc()) $total_sales = (float)$row['total'];

// Get pending payments
$pending_payments = [];
$res = $conn->query("SELECT ap.id, ap.affiliate_id, af.name, af.email, ap.amount, ap.method, ap.status, ap.created_at FROM affiliate_payments ap JOIN affiliates af ON ap.affiliate_id = af.id WHERE ap.status = 'pending' ORDER BY ap.created_at DESC LIMIT 50");
if ($res) while ($row = $res->fetch_assoc()) $pending_payments[] = $row;

// Get total commissions
$total_commissions = 0;
$res = $conn->query("SELECT COALESCE(SUM(commission), 0) as total FROM affiliate_clicks WHERE status = 'confirmed'");
if ($res && $row = $res->fetch_assoc()) $total_commissions = (float)$row['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Onrizo</title>
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
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            margin-top: 20px;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div>
                <h1>🛠️ Admin Panel</h1>
                <p style="color: #999; font-size: 14px; margin-top: 5px;">Platform management - products, admins, affiliates, payments</p>
            </div>
            <div class="nav-links">
                <a href="store_dashboard.php">Store Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </header>

        <?php if (isset($msg)): ?>
            <div class="success-msg"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('overview')">🔍 Overview</button>
            <button class="tab-btn" onclick="showTab('products')">📦 Products</button>
            <button class="tab-btn" onclick="showTab('admins')">👥 Admins</button>
            <button class="tab-btn" onclick="showTab('affiliates')">🤝 Affiliates</button>
            <button class="tab-btn" onclick="showTab('payments')">💳 Payments</button>
        </div>

        <!-- TAB 1: OVERVIEW -->
        <div id="overview" class="tab-content active">
            <div class="section-title">📊 Platform Metrics</div>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-label">💵 Total Sales</div>
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
                <div class="metric-card danger">
                    <div class="metric-label">⏳ Pending Payments</div>
                    <div class="metric-value"><?php echo count($pending_payments); ?></div>
                </div>
            </div>
        </div>

        <!-- TAB 2: PRODUCTS -->
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

        <!-- TAB 3: ADMINS -->
        <div id="admins" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">All Admins (<?php echo count($all_admins); ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Name</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_admins as $adm): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($adm['email']); ?></td>
                                <td><?php echo htmlspecialchars($adm['name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d M Y', strtotime($adm['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: AFFILIATES -->
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
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $aff['id']; ?>">
                                        <button type="submit" class="btn btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 5: PAYMENTS -->
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
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
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
    </script>
</body>
</html>
