<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    date_default_timezone_set('Africa/Nairobi');

    include __DIR__ . '/db_config.php';

    // --- SAFARICOM DARAJA CREDENTIALS ---
    $consumerKey = 'dyCoZGgdMQSq6so7T6prfi2oKLygiuBWwotgMAQECTZZeDbu';
    $consumerSecret = 'maG2q9A7AEI6Z23SeLNuCLgIixp3JgbBsOJQztcvGnvc1rGWl00SRBpPgdbASQ4z';

    // --- DARAJA SANDBOX URLs ---
    $accessTokenUrl = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $stkPushUrl = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    // --- STEP 1: REQUEST ACCESS TOKEN ---
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $curl = curl_init($accessTokenUrl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        die('Error: ' . curl_error($curl));
    }
    $result = json_decode($response);
    $access_token = $result->access_token ?? '';
    curl_close($curl);

    if (!$access_token) {
        die('Error: Unable to get access token. Check your Consumer Key/Secret.');
    }

    // --- STEP 2: SET TRANSACTION DATA ---
    $BusinessShortCode = '174379';
    $Passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    $phone = preg_replace('/\D/', '', $_POST['phone']); // remove all non-digits
    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1); // convert 07... to 2547...
    } elseif (substr($phone, 0, 3) === '254') {
        // already okay
    } elseif (substr($phone, 0, 4) === '2547') {
        // still okay
    } else {
        $phone = '254' . $phone; // fallback
    }
    $PartyA = $phone;
    $PartyB = $BusinessShortCode;
    $AccountReference = 'OnrizoShop';
    $TransactionDesc = 'Online Purchase';
    $Amount = (int)$_POST['amount'];
    $Timestamp = date('YmdHis');
    $Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);

    // --- STEP 3: CREATE STK PUSH BODY ---
    // Get the callback URL dynamically
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $callbackUrl = $protocol . '://' . $host . '/onrizo/mpesa_callback.php';
    
    $stkData = [
        'BusinessShortCode' => $BusinessShortCode,
        'Password' => $Password,
        'Timestamp' => $Timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $Amount,
        'PartyA' => $PartyA,
        'PartyB' => $PartyB,
        'PhoneNumber' => $PartyA,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => $AccountReference,
        'TransactionDesc' => $TransactionDesc
    ];

    // --- STEP 4: SEND STK PUSH REQUEST ---
    $dataString = json_encode($stkData);
    $curl = curl_init($stkPushUrl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $dataString);
    $response = curl_exec($curl);
    curl_close($curl);

    $responseData = json_decode($response, true);

    // --- STEP 5: SAVE ORDER / PENDING PROMOTION ---
    if (isset($responseData['ResponseCode']) && $responseData['ResponseCode'] == '0') {
        // successful submission of STK push
        $checkoutRequestID = $responseData['CheckoutRequestID'] ?? null;

        // Record a pending promotion when promotion flag is sent
        if (!empty($_POST['promotion']) && $_POST['promotion'] == '1') {
            $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
            $title = isset($_POST['title']) ? $_POST['title'] : 'Promotion';
            $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 7;
            $budget = $Amount;

            // find product owner (admin)
            $admin_id = 0;
            if ($product_id) {
                $stmt = $conn->prepare("SELECT admin_id FROM products WHERE id = ? LIMIT 1");
                $stmt->bind_param('i', $product_id);
                $stmt->execute();
                $r = $stmt->get_result();
                if ($r && $row = $r->fetch_assoc()) $admin_id = (int)$row['admin_id'];
                $stmt->close();
            }

            // ensure promotions table has checkout_request_id column
            $dbNameRow = $conn->query("SELECT DATABASE() as db")->fetch_assoc();
            $dbName = $dbNameRow ? $conn->real_escape_string($dbNameRow['db']) : '';
            $colCheck = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'checkout_request_id'");
            if (!$colCheck || $colCheck->num_rows == 0) {
                // add column
                @$conn->query("ALTER TABLE promotions ADD COLUMN checkout_request_id VARCHAR(255) DEFAULT NULL");
            }

            // insert pending promotion
            $stmt = $conn->prepare("INSERT INTO promotions (admin_id, product_id, title, budget, duration_days, status, created_at, checkout_request_id) VALUES (?, ?, ?, ?, ?, 'Pending', NOW(), ?)");
            $stmt->bind_param('iisdis', $admin_id, $product_id, $title, $budget, $duration, $checkoutRequestID);
            @$stmt->execute();
            $stmt->close();
        }

        // Save a local record as well
        $ordersFile = __DIR__ . '/admin/orders.json';
        $orders = file_exists($ordersFile) ? json_decode(file_get_contents($ordersFile), true) : [];

        // Get cart info from POST if available
        $cartItems = $_POST['cart'] ?? 'Unknown Product(s)';

        $orders[] = [
            'phone' => $PartyA,
            'amount' => $Amount,
            'status' => 'Pending Payment',
            'date' => date('Y-m-d H:i:s'),
            'cart' => $cartItems,
            'checkoutRequestID' => $checkoutRequestID
        ];

        file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT));

        echo "<h3>✅ Payment request sent successfully!</h3>";
        echo "<p>Please check your phone to complete the M-Pesa payment.</p>";

    } else {
        echo "<h3>❌ Failed to send payment request.</h3>";
        echo "<pre>" . print_r($responseData, true) . "</pre>";
    }
} else {
    echo "Invalid request.";
}
?>
