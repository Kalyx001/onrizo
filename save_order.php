<?php
header('Content-Type: application/json');
include 'db_config.php';

// Get JSON data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Extract data
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';
$location = $data['location'] ?? '';
$cart = $data['cart'] ?? [];
$total = $data['total'] ?? 0;
$timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');

// Validate
if (empty($name) || empty($email) || empty($phone) || empty($cart)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Create orders table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS orders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        customer_name VARCHAR(255) NOT NULL,
        customer_email VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        location VARCHAR(255),
        total_amount DECIMAL(10, 2),
        status VARCHAR(50) DEFAULT 'Pending',
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createTableSQL)) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    // Create order items table if it doesn't exist
    $createItemsTableSQL = "CREATE TABLE IF NOT EXISTS order_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        order_id INT NOT NULL,
        product_id INT,
        product_name VARCHAR(255) NOT NULL,
        price DECIMAL(10, 2),
        quantity INT DEFAULT 1,
        subtotal DECIMAL(10, 2),
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($createItemsTableSQL)) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    // Ensure affiliates and affiliate_earnings tables exist (safe-guard for older installs)
    $createAffiliates = "CREATE TABLE IF NOT EXISTS affiliates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT DEFAULT 0,
        name VARCHAR(255) DEFAULT NULL,
        contact VARCHAR(255) DEFAULT NULL,
        percent DECIMAL(5,2) DEFAULT 0,
        token VARCHAR(128) DEFAULT NULL,
        balance DECIMAL(12,2) DEFAULT 0,
        status VARCHAR(32) DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createAffiliates);

    $createAffiliateEarnings = "CREATE TABLE IF NOT EXISTS affiliate_earnings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        affiliate_id INT NOT NULL,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (affiliate_id),
        INDEX (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createAffiliateEarnings);

    // Insert order
    $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_email, customer_phone, location, total_amount, status) 
                           VALUES (?, ?, ?, ?, ?, 'Pending')");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("ssssd", $name, $email, $phone, $location, $total);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
        exit;
    }

    $order_id = $conn->insert_id;

    // Insert order items
    foreach ($cart as $item) {
        $product_id = isset($item['id']) ? intval($item['id']) : null;
        $product_name = $item['name'] ?? 'Unknown Product';
        $price = isset($item['price']) ? floatval($item['price']) : 0;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $subtotal = $price * $quantity;

        $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                                   VALUES (?, ?, ?, ?, ?, ?)");
        
        if (!$itemStmt) {
            echo json_encode(['success' => false, 'message' => 'Item prepare failed: ' . $conn->error]);
            exit;
        }

        // Handle nullable product_id
        if ($product_id === null) {
            $itemStmt->bind_param("issdid", $order_id, $product_id, $product_name, $price, $quantity, $subtotal);
        } else {
            $itemStmt->bind_param("iisdid", $order_id, $product_id, $product_name, $price, $quantity, $subtotal);
        }
        
        if (!$itemStmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Item insert failed: ' . $itemStmt->error]);
            exit;
        }

        $itemStmt->close();
    }

    // Handle affiliate referral crediting if present (using new affiliate system with referral_code)
    $affiliateRef = $data['affiliate_ref'] ?? null; // This is the referral_code
    $affiliateProductId = isset($data['affiliate_product']) ? intval($data['affiliate_product']) : null;
    
    if ($affiliateRef) {
        // Find affiliate by referral_code
        $aStmt = $conn->prepare("SELECT id, referral_code FROM affiliates WHERE referral_code = ? AND status = 'active' LIMIT 1");
        if (!$aStmt) {
            echo json_encode(['success' => false, 'message' => 'Affiliate check failed']);
            exit;
        }
        
        $aStmt->bind_param('s', $affiliateRef);
        $aStmt->execute();
        $aRes = $aStmt->get_result();
        
        if ($aRes && $aRes->num_rows > 0) {
            $aRow = $aRes->fetch_assoc();
            $affiliateId = (int)$aRow['id'];

            // for each order item, credit affiliate with commission
            foreach ($cart as $item) {
                $prodId = isset($item['id']) ? intval($item['id']) : 0;
                $qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
                $price = isset($item['price']) ? floatval($item['price']) : 0;
                $subtotal = $price * $qty;

                // If affiliate_product specified, only credit for that product; otherwise credit all
                if ($affiliateProductId && $prodId !== $affiliateProductId) {
                    continue;
                }
                
                // Get product's commission percent
                $pstmt = $conn->prepare("SELECT COALESCE(affiliate_percent, 15) as ap FROM products WHERE id = ? LIMIT 1");
                if ($pstmt) {
                    $pstmt->bind_param('i', $prodId);
                    $pstmt->execute();
                    $presult = $pstmt->get_result();
                    $usePercent = 15; // Default commission
                    if ($presult && $prow = $presult->fetch_assoc()) {
                        $usePercent = (float)$prow['ap'];
                    }
                    $pstmt->close();
                    
                    if ($usePercent > 0) {
                        $commission = round($subtotal * ($usePercent / 100), 2);
                        if ($commission > 0) {
                            // Record the affiliate click as confirmed (sale)
                            $clickStmt = $conn->prepare("INSERT INTO affiliate_clicks (affiliate_id, product_id, product_name, commission, status) VALUES (?, ?, ?, ?, 'confirmed')");
                            if ($clickStmt) {
                                $productName = $item['name'] ?? 'Unknown';
                                $clickStmt->bind_param('iiss', $affiliateId, $prodId, $productName, $commission);
                                $clickStmt->execute();
                                $clickStmt->close();
                            }
                        }
                    }
                }
            }
        }
        $aStmt->close();
    }

    // Send confirmation email (optional)
    $to = $email;
    $subject = "Order Confirmation - Onrizo Shop #$order_id";
    $message = "Dear $name,\n\n";
    $message .= "Thank you for your order!\n\n";
    $message .= "Order ID: $order_id\n";
    $message .= "Total Amount: KES " . number_format($total, 2) . "\n";
    $message .= "Delivery Location: $location\n\n";
    $message .= "We will contact you soon with updates.\n\n";
    $message .= "Best regards,\n";
    $message .= "Onrizo Shop Team";

    $headers = "From: onrizo@gmail.com\r\n";
    $headers .= "Reply-To: onrizo@gmail.com\r\n";

    @mail($to, $subject, $message, $headers);

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $order_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
}

$conn->close();
?>
