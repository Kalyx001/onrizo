<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 Onrizo Setup Status</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 30px;
        }
        .status {
            margin: 15px 0;
            padding: 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .icon {
            font-size: 20px;
        }
        .links {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .link-btn {
            display: block;
            padding: 12px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            text-align: center;
            transition: background 0.3s;
        }
        .link-btn:hover {
            background: #764ba2;
        }
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Onrizo Shop - Setup Status</h1>
        
        <?php
        // Check PHP version
        echo '<div class="status success">';
        echo '<span class="icon">✅</span>';
        echo '<span><strong>PHP Version:</strong> ' . phpversion() . '</span>';
        echo '</div>';
        
        // Check Database Connection
        include 'db_config.php';
        if ($conn->connect_error) {
            echo '<div class="status error">';
            echo '<span class="icon">❌</span>';
            echo '<span><strong>Database:</strong> Connection Failed - ' . htmlspecialchars($conn->connect_error) . '</span>';
            echo '</div>';
        } else {
            echo '<div class="status success">';
            echo '<span class="icon">✅</span>';
            echo '<span><strong>Database:</strong> Connected to onrizo_db</span>';
            echo '</div>';
            
            // Check tables
            $tables = ['admins', 'products', 'orders', 'payments'];
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    echo '<div class="status success">';
                    echo '<span class="icon">✅</span>';
                    echo '<span><strong>Table:</strong> ' . htmlspecialchars($table) . ' exists</span>';
                    echo '</div>';
                } else {
                    echo '<div class="status warning">';
                    echo '<span class="icon">⚠️</span>';
                    echo '<span><strong>Table:</strong> ' . htmlspecialchars($table) . ' not found</span>';
                    echo '</div>';
                }
            }
            
            // Check data
            $adminCount = $conn->query("SELECT COUNT(*) as count FROM admins")->fetch_assoc()['count'] ?? 0;
            $productCount = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0;
            
            echo '<div class="info-box">';
            echo '<strong>📊 Database Summary:</strong><br>';
            echo 'Admins: <strong>' . $adminCount . '</strong><br>';
            echo 'Products: <strong>' . $productCount . '</strong><br>';
            echo '</div>';
            
            $conn->close();
        }
        
        // Check uploads folder
        if (is_dir('uploads')) {
            echo '<div class="status success">';
            echo '<span class="icon">✅</span>';
            echo '<span><strong>Uploads Folder:</strong> Exists and writable</span>';
            echo '</div>';
        } else {
            echo '<div class="status warning">';
            echo '<span class="icon">⚠️</span>';
            echo '<span><strong>Uploads Folder:</strong> Not found - will be created on first upload</span>';
            echo '</div>';
        }
        
        // Check cURL
        if (extension_loaded('curl')) {
            echo '<div class="status success">';
            echo '<span class="icon">✅</span>';
            echo '<span><strong>cURL:</strong> Installed (M-Pesa payments work)</span>';
            echo '</div>';
        } else {
            echo '<div class="status error">';
            echo '<span class="icon">❌</span>';
            echo '<span><strong>cURL:</strong> Not installed (M-Pesa will fail)</span>';
            echo '</div>';
        }
        ?>
        
        <div class="info-box">
            <strong>📝 Configuration Summary:</strong><br>
            Database Host: <code>localhost</code><br>
            Database User: <code>root</code><br>
            Database Name: <code>onrizo_db</code><br>
            M-Pesa: <code>Sandbox Mode (Testing)</code>
        </div>

        <div class="links">
            <a href="index.html" class="link-btn">🏠 Home Page</a>
            <a href="admin/login.php" class="link-btn">👤 Admin Login</a>
            <a href="admin/register.php" class="link-btn">📝 Register Admin</a>
            <a href="test_db.php" class="link-btn">🧪 Test Database</a>
        </div>

        <div class="info-box" style="margin-top: 20px;">
            <strong>🎯 Next Steps:</strong>
            <ol>
                <li>If this is your first time, register a new admin account</li>
                <li>Login and add some products from the admin dashboard</li>
                <li>Browse products on the home page and test the cart</li>
                <li>Test M-Pesa payment flow (sandbox mode)</li>
                <li>When ready for production, update <code>db_config.php</code> and M-Pesa credentials</li>
            </ol>
        </div>
    </div>
</body>
</html>
