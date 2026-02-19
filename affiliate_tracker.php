<?php
include 'db_config.php';

// Track affiliate click/referral
$referral_code = isset($_GET['ref']) ? trim($_GET['ref']) : '';
$product_id = isset($_GET['product']) ? (int)$_GET['product'] : 0;

if (!empty($referral_code)) {
    // Find affiliate by referral code
    $stmt = $conn->prepare("SELECT id FROM affiliates WHERE referral_code = ? LIMIT 1");
    $stmt->bind_param('s', $referral_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $affiliate = $result->fetch_assoc();
        $affiliate_id = $affiliate['id'];
        
        // Log the click
        $stmt2 = $conn->prepare("INSERT INTO affiliate_clicks (affiliate_id, product_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt2->bind_param('ii', $affiliate_id, $product_id);
        $stmt2->execute();
        $stmt2->close();
        
        // Store in session for later
        $_SESSION['affiliate_id_ref'] = $affiliate_id;
    }
    $stmt->close();
}
?>
