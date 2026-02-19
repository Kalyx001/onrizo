<?php
// Simple DB setup script for Onrizo (run once)
// Opens in browser and creates missing tables. Keep it in admin folder for now.

include '../db_config.php';

$results = [];

function run($conn, $sql, &$results, $label){
    try{
        if ($conn->query($sql) === TRUE) {
            $results[] = ["label"=>$label, "ok"=>true, "message"=>"created or already exists"];
        } else {
            $results[] = ["label"=>$label, "ok"=>false, "message"=>$conn->error];
        }
    } catch (\Exception $e){
        $results[] = ["label"=>$label, "ok"=>false, "message"=>$e->getMessage()];
    }
}

// products table (non-destructive)
$sql = "CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT 0,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(12,2) DEFAULT 0,
    description TEXT,
    category VARCHAR(128),
    image VARCHAR(255),
    date_added DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'products');

// product_images
$sql = "CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'product_images');

// orders
$sql = "CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) DEFAULT NULL,
    customer_phone VARCHAR(64) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(64) DEFAULT 'Pending',
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'orders');

// order_items
$sql = "CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_name VARCHAR(255) DEFAULT NULL,
    quantity INT DEFAULT 1,
    price DECIMAL(12,2) DEFAULT 0,
    subtotal DECIMAL(12,2) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'order_items');

// withdrawals (add commission fields: commission_percent, commission_amount, net_amount)
$sql = "CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL,
    commission_percent DECIMAL(5,2) DEFAULT 5.00,
    commission_amount DECIMAL(12,2) DEFAULT 0,
    net_amount DECIMAL(12,2) DEFAULT 0,
    destination VARCHAR(255) NOT NULL,
    status VARCHAR(32) DEFAULT 'Reserved',
    transaction_id VARCHAR(255) DEFAULT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'withdrawals');

// promotions table
$sql = "CREATE TABLE IF NOT EXISTS promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT 0,
    product_id INT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    budget DECIMAL(12,2) DEFAULT 0,
    duration_days INT DEFAULT 7,
    status VARCHAR(32) DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'promotions');

// admins table (simple) if not exists — include profile fields used elsewhere
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(64) DEFAULT NULL,
    county VARCHAR(128) DEFAULT NULL,
    subcounty VARCHAR(128) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'admins');

// affiliates table: stores affiliate records and links to products
$sql = "CREATE TABLE IF NOT EXISTS affiliates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact VARCHAR(255) DEFAULT NULL,
    percent DECIMAL(5,2) DEFAULT 0,
    token VARCHAR(64) DEFAULT NULL,
    balance DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(32) DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'affiliates');

// affiliate_payments table: records payments to affiliates (include commission fields)
$sql = "CREATE TABLE IF NOT EXISTS affiliate_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT NOT NULL,
    order_id INT DEFAULT NULL,
    admin_id INT DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL,
    commission_percent DECIMAL(5,2) DEFAULT 5.00,
    commission_amount DECIMAL(12,2) DEFAULT 0,
    net_amount DECIMAL(12,2) DEFAULT 0,
    method VARCHAR(32) DEFAULT 'mpesa',
    status VARCHAR(32) DEFAULT 'pending',
    transaction_id VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'affiliate_payments');

// owner_ledger: records platform revenue (commissions) per withdrawal
$sql = "CREATE TABLE IF NOT EXISTS owner_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    withdrawal_id INT DEFAULT NULL,
    admin_id INT DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL,
    type VARCHAR(32) NOT NULL DEFAULT 'commission',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'owner_ledger');

// affiliate_earnings table: record affiliate earnings (credits) when referred sales happen
$sql = "CREATE TABLE IF NOT EXISTS affiliate_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT NOT NULL,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
run($conn, $sql, $results, 'affiliate_earnings');

// Ensure products table has affiliate_percent column (nullable). Add if missing.
$colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'affiliate_percent'");
if (!$colCheck || $colCheck->num_rows === 0) {
    $alter = "ALTER TABLE products ADD COLUMN affiliate_percent DECIMAL(5,2) DEFAULT NULL";
    try{ $conn->query($alter); $results[] = ["label"=>"products.affiliates", "ok"=>true, "message"=>"affiliate_percent column added"]; } catch (Exception $e){ $results[] = ["label"=>"products.affiliates", "ok"=>false, "message"=>$e->getMessage()]; }
} else {
    $results[] = ["label"=>"products.affiliates", "ok"=>true, "message"=>"affiliate_percent exists"]; 
}

// Ensure withdrawals table has commission columns. Add if missing.
$colCheck = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'commission_percent'");
if (!$colCheck || $colCheck->num_rows === 0) {
    $alter = "ALTER TABLE withdrawals ADD COLUMN commission_percent DECIMAL(5,2) DEFAULT 5.00";
    try{ $conn->query($alter); $results[] = ["label"=>"withdrawals.commission_percent", "ok"=>true, "message"=>"column added"]; } catch (Exception $e){ $results[] = ["label"=>"withdrawals.commission_percent", "ok"=>false, "message"=>$e->getMessage()]; }
} else {
    $results[] = ["label"=>"withdrawals.commission_percent", "ok"=>true, "message"=>"exists"]; 
}

$colCheck = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'commission_amount'");
if (!$colCheck || $colCheck->num_rows === 0) {
    $alter = "ALTER TABLE withdrawals ADD COLUMN commission_amount DECIMAL(12,2) DEFAULT 0";
    try{ $conn->query($alter); $results[] = ["label"=>"withdrawals.commission_amount", "ok"=>true, "message"=>"column added"]; } catch (Exception $e){ $results[] = ["label"=>"withdrawals.commission_amount", "ok"=>false, "message"=>$e->getMessage()]; }
} else {
    $results[] = ["label"=>"withdrawals.commission_amount", "ok"=>true, "message"=>"exists"]; 
}

$colCheck = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'net_amount'");
if (!$colCheck || $colCheck->num_rows === 0) {
    $alter = "ALTER TABLE withdrawals ADD COLUMN net_amount DECIMAL(12,2) DEFAULT 0";
    try{ $conn->query($alter); $results[] = ["label"=>"withdrawals.net_amount", "ok"=>true, "message"=>"column added"]; } catch (Exception $e){ $results[] = ["label"=>"withdrawals.net_amount", "ok"=>false, "message"=>$e->getMessage()]; }
} else {
    $results[] = ["label"=>"withdrawals.net_amount", "ok"=>true, "message"=>"exists"]; 
}

// Ensure affiliate_payments table has commission columns. Add if missing.
$colCheck = $conn->query("SHOW COLUMNS FROM affiliate_payments LIKE 'commission_percent'");
if (!$colCheck || $colCheck->num_rows === 0) {
    $alter = "ALTER TABLE affiliate_payments ADD COLUMN commission_percent DECIMAL(5,2) DEFAULT 5.00";
    try{ $conn->query($alter); $results[] = ["label"=>"affiliate_payments.commission_percent", "ok"=>true, "message"=>"column added"]; } catch (Exception $e){ $results[] = ["label"=>"affiliate_payments.commission_percent", "ok"=>false, "message"=>$e->getMessage()]; }
} else {
    $results[] = ["label"=>"affiliate_payments.commission_percent", "ok"=>true, "message"=>"exists"]; 
}

$colCheck = $conn->query("SHOW COLUMNS FROM affiliate_payments LIKE 'commission_amount'");
if (!$colCheck || $colCheck->num_rows === 0) {
    $alter = "ALTER TABLE affiliate_payments ADD COLUMN commission_amount DECIMAL(12,2) DEFAULT 0";
    try{ $conn->query($alter); $results[] = ["label"=>"affiliate_payments.commission_amount", "ok"=>true, "message"=>"column added"]; } catch (Exception $e){ $results[] = ["label"=>"affiliate_payments.commission_amount", "ok"=>false, "message"=>$e->getMessage()]; }
} else {
    $results[] = ["label"=>"affiliate_payments.commission_amount", "ok"=>true, "message"=>"exists"]; 
}

$colCheck = $conn->query("SHOW COLUMNS FROM affiliate_payments LIKE 'net_amount'");
if (!$colCheck || $colCheck->num_rows === 0) {
    $alter = "ALTER TABLE affiliate_payments ADD COLUMN net_amount DECIMAL(12,2) DEFAULT 0";
    try{ $conn->query($alter); $results[] = ["label"=>"affiliate_payments.net_amount", "ok"=>true, "message"=>"column added"]; } catch (Exception $e){ $results[] = ["label"=>"affiliate_payments.net_amount", "ok"=>false, "message"=>$e->getMessage()]; }
} else {
    $results[] = ["label"=>"affiliate_payments.net_amount", "ok"=>true, "message"=>"exists"]; 
}

// Optional: create a default admin if none exists (only in development)
$adminCheck = $conn->query("SELECT COUNT(*) as c FROM admins");
if ($adminCheck && ($row = $adminCheck->fetch_assoc()) && $row['c'] == 0) {
    $defaultUser = 'admin';
    $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
    // password column stores the hashed password
    $stmt = $conn->prepare("INSERT INTO admins (username, password, email, name) VALUES (?, ?, ?, ?)");
    $email = 'admin@onrizo.local';
    $name = 'Default Admin';
    $stmt->bind_param('ssss', $defaultUser, $defaultPass, $email, $name);
    $ok = $stmt->execute();
    $results[] = ["label"=>"default admin", "ok"=> (bool)$ok, "message"=> $ok?"created (username=admin, pass=admin123)":"failed: ".$conn->error];
}

// report
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Onrizo DB Setup</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;padding:24px;background:#f7f8fb} .ok{color:green}.bad{color:#b00}</style>
</head>
<body>
    <h2>Onrizo DB Setup</h2>
    <p>Created/checked tables below:</p>
    <ul>
    <?php foreach($results as $r): ?>
        <li class="<?= $r['ok'] ? 'ok' : 'bad' ?>"><strong><?= htmlspecialchars($r['label']) ?></strong>: <?= htmlspecialchars($r['message']) ?></li>
    <?php endforeach; ?>
    </ul>
    <p>Run once, then remove <code>admin/setup.php</code> or protect it.</p>
    <p><a href="dashboard.php">Back to dashboard</a></p>
</body>
</html>
