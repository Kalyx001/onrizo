<?php
session_start();
include __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

function normalizeKenyaPhone($raw){
    $p = preg_replace('/\D+/', '', $raw);
    if (strpos($p, '00') === 0) { $p = ltrim($p, '0'); }
    if (strpos($p, '+') === 0) { $p = ltrim($p, '+'); }
    // if starts with 0 -> replace with 254
    if (strlen($p) >= 9 && $p[0] === '0') {
        $p = '254' . substr($p, 1);
    }
    // if starts with 7 and 9 digits -> assume local without leading zero
    if (strlen($p) === 9 && $p[0] === '7') {
        $p = '254' . $p;
    }
    // if starts with 254 already, keep
    if (strpos($p, '254') === 0) {
        // ensure length is 12 (254 + 9)
        // pad or trim not advisable; just return
        return $p;
    }
    // as fallback, return raw digits
    return $p;
}

if ($action === 'step1') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if (empty($name) || empty($email)) {
        echo json_encode(['success'=>false,'message'=>'Please fill name and email']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success'=>false,'message'=>'Invalid email']); exit;
    }
    // check existing
    $chk = $conn->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
    $chk->bind_param('s', $email);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) { echo json_encode(['success'=>false,'message'=>'Email already registered']); exit; }
    $_SESSION['reg_tmp'] = ['name'=>$name,'email'=>$email];
    echo json_encode(['success'=>true]); exit;
}

if ($action === 'send_code') {
    if (empty($_SESSION['reg_tmp'])) { echo json_encode(['success'=>false,'message'=>'Start from step 1']); exit; }
    $phoneRaw = trim($_POST['phone'] ?? '');
    if (empty($phoneRaw)) { echo json_encode(['success'=>false,'message'=>'Phone required']); exit; }
    $phone = normalizeKenyaPhone($phoneRaw);
    // basic validation: must start with 254 and be 12 or 12+ length
    if (!preg_match('/^254\d{8,9}$/', $phone) && !preg_match('/^254\d{9}$/', $phone)) {
        // accept 12-digit (254 + 9) or 11?
        // We'll allow 254 followed by 8-9 digits to be permissive
        // but if grossly invalid, reject
        if (strlen($phone) < 11) {
            echo json_encode(['success'=>false,'message'=>'Invalid Kenyan number']); exit;
        }
    }
    // store phone
    $_SESSION['reg_tmp']['phone'] = $phone;

    // generate code
    $code = rand(100000, 999999);
    $_SESSION['reg_verif'] = ['code'=>strval($code), 'expires'=>time() + 900]; // 15 min

    // send SMS: for local dev, log to file
    $smsLog = __DIR__ . '/sms_log.txt';
    $log = date('c') . " | To: $phone | Code: $code\n";
    file_put_contents($smsLog, $log, FILE_APPEND);

    // try email too
    $to = $_SESSION['reg_tmp']['email'];
    $subject = 'Your Onrizo verification code';
    $body = "Your verification code is: $code\nIt expires in 15 minutes.";
    @mail($to, $subject, $body, 'From: no-reply@onrizo.local');

    echo json_encode(['success'=>true, 'message'=>'Verification code sent (check SMS log and email)']); exit;
}

if ($action === 'verify_code') {
    $entered = trim($_POST['code'] ?? '');
    if (empty($_SESSION['reg_verif'])) { echo json_encode(['success'=>false,'message'=>'No code sent']); exit; }
    if (time() > ($_SESSION['reg_verif']['expires'] ?? 0)) { echo json_encode(['success'=>false,'message'=>'Code expired']); exit; }
    if ($entered !== ($_SESSION['reg_verif']['code'] ?? '')) { echo json_encode(['success'=>false,'message'=>'Incorrect code']); exit; }
    $_SESSION['reg_tmp']['verified'] = true;
    echo json_encode(['success'=>true]); exit;
}

if ($action === 'step3') {
    if (empty($_SESSION['reg_tmp']['verified'])) { echo json_encode(['success'=>false,'message'=>'Please verify phone first']); exit; }
    $county = trim($_POST['county'] ?? '');
    $subcounty = trim($_POST['subcounty'] ?? '');
    if (empty($county) || empty($subcounty)) { echo json_encode(['success'=>false,'message'=>'Select county and sub-county']); exit; }
    $_SESSION['reg_tmp']['county'] = $county;
    $_SESSION['reg_tmp']['subcounty'] = $subcounty;
    echo json_encode(['success'=>true]); exit;
}

if ($action === 'finalize') {
    if (empty($_SESSION['reg_tmp'])) { echo json_encode(['success'=>false,'message'=>'Session lost']); exit; }
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (empty($username) || empty($password)) { echo json_encode(['success'=>false,'message'=>'Provide username and password']); exit; }
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        echo json_encode(['success'=>false,'message'=>'Password must be 8+ chars, include upper, lower and a number']); exit;
    }
    // check username/email
    $chk = $conn->prepare('SELECT id FROM admins WHERE username = ? OR email = ? LIMIT 1');
    $chk->bind_param('ss', $username, $_SESSION['reg_tmp']['email']);
    $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { echo json_encode(['success'=>false,'message'=>'Username or email already exists']); exit; }

    // insert
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO admins (name, email, phone, county, subcounty, username, password, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $name = $_SESSION['reg_tmp']['name'];
    $email = $_SESSION['reg_tmp']['email'];
    $phone = $_SESSION['reg_tmp']['phone'];
    $county = $_SESSION['reg_tmp']['county'] ?? '';
    $subcounty = $_SESSION['reg_tmp']['subcounty'] ?? '';
    $stmt->bind_param('sssssss', $name, $email, $phone, $county, $subcounty, $username, $hashed);
    if ($stmt->execute()) {
        // clear session temp
        unset($_SESSION['reg_tmp']); unset($_SESSION['reg_verif']);
        echo json_encode(['success'=>true, 'message'=>'Registration complete']); exit;
    } else {
        echo json_encode(['success'=>false,'message'=>'DB error: ' . $stmt->error]); exit;
    }
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);
