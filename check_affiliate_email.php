<?php
/**
 * check_affiliate_email.php
 * Checks if an email is registered as an affiliate
 * 
 * POST/GET: email
 * Returns: JSON with affiliate info or redirect URL
 */

require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

$email = isset($_GET['email']) ? trim($_GET['email']) : (isset($_POST['email']) ? trim($_POST['email']) : '');

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // Check if affiliate with this email exists
    $stmt = $conn->prepare("SELECT id, name, email, phone, referral_code, status FROM affiliates WHERE email = ? LIMIT 1");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('s', $email);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        // Affiliate found
        $affiliate = $result->fetch_assoc();
        
        // Check if affiliate is active
        if ($affiliate['status'] !== 'active') {
            echo json_encode([
                'success' => false,
                'message' => 'This affiliate account is inactive. Please contact support.',
                'type' => 'inactive'
            ]);
            exit;
        }
        
        // Affiliate is registered and active
        echo json_encode([
            'success' => true,
            'found' => true,
            'affiliate_id' => (int)$affiliate['id'],
            'affiliate_name' => $affiliate['name'],
            'email' => $affiliate['email'],
            'phone' => $affiliate['phone'] ?? '',
            'referral_code' => $affiliate['referral_code'],
            'message' => 'Affiliate found! Ready to generate link.'
        ]);
        exit;
    } else {
        // Affiliate not found - redirect to registration
        echo json_encode([
            'success' => false,
            'found' => false,
            'message' => 'Email not registered as affiliate',
            'type' => 'not_registered',
            'redirect' => 'affiliate_login.php?email=' . urlencode($email) . '&action=register'
        ]);
        exit;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}
?>
