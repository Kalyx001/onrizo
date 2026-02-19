<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Home</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            background: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        header h1 {
            color: #667eea;
            font-size: 36px;
            margin-bottom: 10px;
        }

        header p {
            color: #999;
            font-size: 16px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 5px solid #667eea;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .card h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 20px;
        }

        .card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: center;
        }

        .btn:hover {
            background: #764ba2;
            transform: scale(1.02);
        }

        .section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .section h2 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .quick-link {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .quick-link:hover {
            border-color: #667eea;
            background: #f0f0ff;
        }

        .badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .warning-badge {
            background: #dc3545;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #1976d2;
        }

        @media (max-width: 768px) {
            header {
                padding: 20px;
            }

            header h1 {
                font-size: 24px;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🛒 Onrizo Admin Portal</h1>
            <p>Manage your store, products, affiliates, and payments</p>
        </header>

        <div class="info-box">
            <strong>👋 Welcome!</strong> Choose an option below to get started managing your platform.
        </div>

        <div class="grid">
            <div class="card">
                <h3>📊 Store Dashboard</h3>
                <p>View your store's sales, revenue, top products, and affiliate performance with detailed analytics.</p>
                <a href="admin/store_dashboard.php" class="btn">Go to Dashboard</a>
            </div>

            <div class="card">
                <h3>🛠️ Admin Panel</h3>
                <p>Manage entire platform: all products, admins, affiliates, and approve affiliate payments.</p>
                <a href="admin_dashboard.php" class="btn">Go to Admin Panel</a>
            </div>

            <div class="card">
                <h3>📦 Product Management</h3>
                <p>Add, edit, and manage your store's products. Set affiliate commission percentages.</p>
                <a href="admin/dashboard.php" class="btn">Manage Products</a>
            </div>

            <div class="card">
                <h3>📋 Orders</h3>
                <p>View all orders, track order status, and monitor affiliate-driven sales.</p>
                <a href="admin/orders.php" class="btn">View Orders</a>
            </div>

            <div class="card">
                <h3>➕ Add Product</h3>
                <p>Add a new product to your store with pricing, description, and affiliate settings.</p>
                <a href="admin/add_product.php" class="btn">Add New Product</a>
            </div>

            <div class="card">
                <h3>🚀 Promote Products</h3>
                <p>Create affiliate promotions and marketing campaigns for your products.</p>
                <a href="admin/promote.php" class="btn">Create Promotion</a>
            </div>
        </div>

        <div class="section">
            <h2>⚡ Quick Actions</h2>
            <div class="quick-links">
                <a href="admin/add_product.php" class="quick-link">➕ Add Product</a>
                <a href="admin/dashboard.php" class="quick-link">📦 Products</a>
                <a href="admin/orders.php" class="quick-link">📋 Orders</a>
                <a href="admin_dashboard.php" class="quick-link">🛠️ Admin Panel</a>
                <a href="affiliate_dashboard.php" class="quick-link">👥 Affiliate Dashboard</a>
                <a href="admin/logout.php" class="quick-link">🚪 Logout</a>
            </div>
        </div>

        <div class="section">
            <h2>✨ Key Features</h2>
            
            <h3 style="color: #333; margin-top: 20px; margin-bottom: 10px;">📊 Dashboard</h3>
            <ul style="color: #666; line-height: 1.8; list-style-position: inside;">
                <li>✅ View total sales, revenue, and orders</li>
                <li>✅ Track top products and affiliates</li>
                <li>✅ See 6-month revenue trends</li>
                <li>✅ Monitor affiliate commissions</li>
            </ul>

            <h3 style="color: #333; margin-top: 20px; margin-bottom: 10px;">🛠️ Master Admin Panel</h3>
            <ul style="color: #666; line-height: 1.8; list-style-position: inside;">
                <li>✅ View ALL products across platform</li>
                <li>✅ View ALL affiliate users</li>
                <li>✅ Approve affiliate payments</li>
                <li>✅ Delete products and users</li>
                <li>✅ See total platform sales</li>
            </ul>

            <h3 style="color: #333; margin-top: 20px; margin-bottom: 10px;">💳 Payment Approval System</h3>
            <ul style="color: #666; line-height: 1.8; list-style-position: inside;">
                <li>✅ Affiliates request withdrawals</li>
                <li>✅ Admin reviews in Master Panel</li>
                <li>✅ Admin approves/denies</li>
                <li>✅ Affiliates see status in their dashboard</li>
                <li>✅ Status: Pending → Approved → Paid</li>
            </ul>

            <h3 style="color: #333; margin-top: 20px; margin-bottom: 10px;">🤝 Affiliate System</h3>
            <ul style="color: #666; line-height: 1.8; list-style-position: inside;">
                <li>✅ Affiliates generate product links</li>
                <li>✅ Earn commission on referred sales</li>
                <li>✅ Track earnings and payments</li>
                <li>✅ See pending approval amounts</li>
            </ul>
        </div>

        <div class="section">
            <h2>🔍 How to Use</h2>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="color: #333; margin-bottom: 15px;">1️⃣ Managing Products</h3>
                <p style="color: #666; margin-bottom: 10px;">• Go to <strong>Master Admin Panel → Products</strong> to see all products<br>
                • Click <strong>Delete</strong> to remove a product<br>
                • Go to <strong>Products</strong> page to add or edit your own</p>
            </div>

            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="color: #333; margin-bottom: 15px;">2️⃣ Managing Affiliates</h3>
                <p style="color: #666; margin-bottom: 10px;">• Go to <strong>Master Admin Panel → Affiliates</strong> to see all users<br>
                • Click <strong>Delete</strong> to remove an affiliate<br>
                • View their referral code and balance</p>
            </div>

            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="color: #333; margin-bottom: 15px;">3️⃣ Approving Payments</h3>
                <p style="color: #666; margin-bottom: 10px;">• Go to <strong>Master Admin Panel → Payments</strong><br>
                • See all PENDING payments from affiliates<br>
                • Click <strong>Approve</strong> to approve a payment<br>
                • Status changes and affiliate sees it in their dashboard</p>
            </div>

            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h3 style="color: #333; margin-bottom: 15px;">4️⃣ Viewing Sales</h3>
                <p style="color: #666; margin-bottom: 10px;">• Total sales visible in <strong>Master Admin Panel → Overview</strong><br>
                • See breakdown by order in <strong>Orders</strong> page<br>
                • Track affiliate-driven revenue</p>
            </div>
        </div>

        <div class="section" style="text-align: center;">
            <h2>📱 Access URLs</h2>
            <div style="background: #f0f0f0; padding: 20px; border-radius: 8px; text-align: left; font-family: monospace;">
                Store Dashboard: <strong>localhost/onrizo/admin/store_dashboard.php</strong><br>
                Master Admin: <strong>localhost/onrizo/admin/master_dashboard.php</strong><br>
                Products: <strong>localhost/onrizo/admin/dashboard.php</strong><br>
                Orders: <strong>localhost/onrizo/admin/orders.php</strong><br>
                Affiliate Dashboard: <strong>localhost/onrizo/affiliate_dashboard.php</strong>
            </div>
        </div>
    </div>
</body>
</html>
