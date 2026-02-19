<?php
/**
 * Quick Reference: How to Integrate Affiliate System
 * 
 * Step 1: Include the integration file at top of save_order.php
 * Step 2: After order is successfully saved, call processAffiliateCommission()
 * Step 3: Include tracking code in index.html/checkout page
 */

// =============================================================================
// STEP 1: In your save_order.php file, add this at the top:
// =============================================================================

/*
include 'affiliate_order_integration.php';
*/

// =============================================================================
// STEP 2: After you successfully save the order, add this:
// =============================================================================

/*
// Process affiliate commission if applicable
if (isset($_SESSION['affiliate_id_ref'])) {
    $affiliate_commission_result = processAffiliateCommission(
        $order_id,                    // Your order ID
        $product_id,                  // Product ID from order
        $total_amount,                // Total order amount
        $product_name                 // Product name
    );
    
    if ($affiliate_commission_result) {
        error_log("Affiliate commission processed successfully for order $order_id");
    }
}
*/

// =============================================================================
// STEP 3: Include this script in your index.html checkout page:
// =============================================================================

/*
<script>
    // Check if user came via affiliate link
    const urlParams = new URLSearchParams(window.location.search);
    const referralCode = urlParams.get('ref');
    
    if (referralCode) {
        // Store in localStorage or send to server
        localStorage.setItem('affiliate_ref', referralCode);
        
        // Optionally send to server to track the click
        fetch('affiliate_tracker.php?ref=' + referralCode)
            .then(r => r.json())
            .catch(e => console.log('Referral tracked'));
    }
</script>
*/

// =============================================================================
// ADDITIONAL HELPER FUNCTIONS AVAILABLE:
// =============================================================================

/*

1. getAffiliateFromReferralCode($referral_code)
   - Returns affiliate details from referral code
   - Usage: $affiliate = getAffiliateFromReferralCode('ABC123');

2. trackAffiliateClick($affiliate_id, $product_id)
   - Manually track an affiliate click
   - Usage: trackAffiliateClick(5, 10);

3. getAffiliateBalance($affiliate_id)
   - Get current balance for an affiliate
   - Usage: $balance = getAffiliateBalance(5);

4. requestAffiliateWithdrawal($affiliate_id, $amount, $method)
   - Create a withdrawal request
   - Methods: 'mpesa', 'bank'
   - Usage: $result = requestAffiliateWithdrawal(5, 2000, 'mpesa');

5. getAffiliateStats($affiliate_id)
   - Get earnings statistics for an affiliate
   - Usage: $stats = getAffiliateStats(5);

*/

?>
