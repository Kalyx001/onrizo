<?php
/**
 * Affiliate Order Integration Helper
 * Include this file in your save_order.php to track affiliate commissions
 * 
 * Usage:
 * include 'affiliate_order_integration.php';
 * processAffiliateCommission($order_id, $product_id, $order_amount);
 */

function processAffiliateCommission($order_id, $product_id, $order_amount, $product_name = 'Product') {
    global $conn;
    
    // Check if this order came from an affiliate referral
    if (empty($_SESSION['affiliate_id_ref'])) {
        return false;
    }
    
    $affiliate_id = (int)$_SESSION['affiliate_id_ref'];
    
    // Get commission percentage for this product
    $product_stmt = $conn->prepare("
        SELECT COALESCE(ap.commission_percent, s.default_commission_percent, 15) as commission_rate
        FROM affiliate_products ap
        LEFT JOIN affiliate_settings s ON 1=1
        WHERE ap.product_id = ?
        LIMIT 1
    ");
    $product_stmt->bind_param('i', $product_id);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    $product_data = $product_result->fetch_assoc();
    $product_stmt->close();
    
    $commission_rate = floatval($product_data['commission_rate']) / 100;
    $commission = $order_amount * $commission_rate;
    
    try {
        // 1. Record the affiliate click/sale
        $click_stmt = $conn->prepare("
            INSERT INTO affiliate_clicks 
            (affiliate_id, product_id, product_name, order_code, commission, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'confirmed', NOW())
        ");
        $click_stmt->bind_param('iissd', $affiliate_id, $product_id, $product_name, $order_id, $commission);
        $click_ok = $click_stmt->execute();
        $click_stmt->close();
        
        if (!$click_ok) {
            return false;
        }
        
        // 2. Update affiliate balance and earnings
        $update_stmt = $conn->prepare("
            UPDATE affiliates 
            SET balance = balance + ?, 
                total_earnings = total_earnings + ?,
                active_referrals = active_referrals + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->bind_param('ddi', $commission, $commission, $affiliate_id);
        $update_ok = $update_stmt->execute();
        $update_stmt->close();
        
        if ($update_ok) {
            // 3. Log activity
            error_log("Affiliate Commission: Affiliate ID $affiliate_id earned KES $commission for order $order_id");
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Affiliate Commission Error: " . $e->getMessage());
        return false;
    }
}

function getAffiliateFromReferralCode($referral_code) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, name, email FROM affiliates WHERE referral_code = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param('s', $referral_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $affiliate = $result->fetch_assoc();
    $stmt->close();
    
    return $affiliate;
}

function trackAffiliateClick($affiliate_id, $product_id = 0) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO affiliate_clicks (affiliate_id, product_id, status, created_at) 
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->bind_param('ii', $affiliate_id, $product_id);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

function getAffiliateBalance($affiliate_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT balance FROM affiliates WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $affiliate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data ? floatval($data['balance']) : 0;
}

function requestAffiliateWithdrawal($affiliate_id, $amount, $method = 'mpesa') {
    global $conn;
    
    // Validate amount
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Invalid amount'];
    }
    
    // Check balance
    $current_balance = getAffiliateBalance($affiliate_id);
    if ($current_balance < $amount) {
        return ['success' => false, 'message' => 'Insufficient balance'];
    }
    
    // Create withdrawal request
    $transaction_id = 'WD-' . strtoupper(substr(md5(time() . $affiliate_id), 0, 10));
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO affiliate_payments (affiliate_id, amount, method, status, transaction_id) 
            VALUES (?, ?, ?, 'pending', ?)
        ");
        $stmt->bind_param('idss', $affiliate_id, $amount, $method, $transaction_id);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            // Deduct from balance
            $update = $conn->prepare("UPDATE affiliates SET balance = balance - ? WHERE id = ?");
            $update->bind_param('di', $amount, $affiliate_id);
            $update->execute();
            $update->close();
            
            return [
                'success' => true,
                'message' => 'Withdrawal request created',
                'transaction_id' => $transaction_id
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to create withdrawal'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getAffiliateStats($affiliate_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_clicks,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_sales,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_sales,
            SUM(CASE WHEN status = 'confirmed' THEN commission ELSE 0 END) as earned_commission
        FROM affiliate_clicks 
        WHERE affiliate_id = ?
    ");
    $stmt->bind_param('i', $affiliate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc() ?? [
        'total_clicks' => 0,
        'pending_sales' => 0,
        'confirmed_sales' => 0,
        'earned_commission' => 0
    ];
    $stmt->close();
    
    return $stats;
}

?>
