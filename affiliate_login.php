<?php
session_start();
include 'db_config.php';

// If already logged in as affiliate, redirect to dashboard
if (isset($_SESSION['affiliate_logged_in']) && $_SESSION['affiliate_logged_in'] === true) {
    header("Location: affiliate_dashboard.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter email and password';
        } else {
            // Check affiliate account
            $stmt = $conn->prepare("SELECT id, name, email, password, phone, bank_details, status FROM affiliates WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $affiliate = $result->fetch_assoc();
                
                if ($affiliate['status'] !== 'active') {
                    $error = 'Your account is inactive. Please contact support.';
                } else if (password_verify($password, $affiliate['password'])) {
                    // Login successful
                    $_SESSION['affiliate_logged_in'] = true;
                    $_SESSION['affiliate_id'] = $affiliate['id'];
                    $_SESSION['affiliate_email'] = $affiliate['email'];
                    $_SESSION['affiliate_name'] = $affiliate['name'];
                    
                    header("Location: affiliate_dashboard.php");
                    exit;
                } else {
                    $error = 'Invalid email or password';
                }
            } else {
                $error = 'Invalid email or password';
            }
            $stmt->close();
        }
    } 
    elseif ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        
        if (empty($name) || empty($email) || empty($phone) || empty($password)) {
            $error = 'All fields are required';
        } else if ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } else {
            // Check if email already exists
            $check_stmt = $conn->prepare("SELECT id FROM affiliates WHERE email = ? LIMIT 1");
            $check_stmt->bind_param('s', $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = 'Email already registered';
            } else {
                // Create account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $referral_code = strtoupper(substr(md5($email . time()), 0, 8));
                
                $insert_stmt = $conn->prepare("INSERT INTO affiliates (name, email, phone, password, referral_code, status, balance, created_at) VALUES (?, ?, ?, ?, ?, 'active', 0, NOW())");
                $insert_stmt->bind_param('sssss', $name, $email, $phone, $hashed_password, $referral_code);
                
                if ($insert_stmt->execute()) {
                    $message = 'Registration successful! You can now login.';
                } else {
                    $error = 'Registration failed: ' . $insert_stmt->error;
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliate Login - Onrizo Shop</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            display: flex;
            max-width: 900px;
            width: 100%;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .left-side {
            flex: 1;
            padding: 50px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }

        .left-side h1 {
            font-size: 32px;
            margin-bottom: 20px;
        }

        .left-side p {
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
            opacity: 0.95;
        }

        .features {
            text-align: left;
            margin-top: 30px;
        }

        .features li {
            list-style: none;
            margin: 12px 0;
            display: flex;
            align-items: center;
        }

        .features li:before {
            content: "✓";
            display: inline-block;
            width: 25px;
            height: 25px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            text-align: center;
            line-height: 25px;
            margin-right: 15px;
            font-weight: bold;
        }

        .right-side {
            flex: 1;
            padding: 50px 40px;
        }

        .tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
        }

        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #999;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .form-subtitle {
            color: #999;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="tel"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .form-footer {
            text-align: center;
            color: #999;
            font-size: 14px;
            margin-top: 20px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .left-side {
                padding: 30px 20px;
            }

            .right-side {
                padding: 30px 20px;
            }

            .left-side h1 {
                font-size: 24px;
            }

            .features li {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
  <div id="pageLoader" class="active">
    <div>
      <div class="spinner"></div>
      <div class="loader-text">Loading...</div>
    </div>
  </div>
    <div class="container">
        <div class="left-side">
            <h1>🎯 Affiliate Program</h1>
            <p>Join Onrizo Shop's affiliate program and start earning commissions by promoting quality products!</p>
            <ul class="features">
                <li>Earn up to 30% commission</li>
                <li>Real-time earnings tracking</li>
                <li>Weekly payments</li>
                <li>Dedicated support</li>
                <li>Marketing materials</li>
                <li>Performance analytics</li>
            </ul>
        </div>

        <div class="right-side">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab-button active" onclick="switchTab('login')">Login</button>
                <button class="tab-button" onclick="switchTab('register')">Register</button>
            </div>

            <!-- LOGIN FORM -->
            <div id="login" class="form-section active">
                <h2>Welcome Back</h2>
                <p class="form-subtitle">Sign in to your affiliate account</p>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label for="login_email">Email Address</label>
                        <input type="email" id="login_email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="login_password">Password</label>
                        <input type="password" id="login_password" name="password" required>
                    </div>

                    <button type="submit" class="btn">Sign In</button>
                </form>

                <div class="form-footer">
                    Don't have an account? <a href="#" onclick="switchTab('register'); return false;" style="color: #667eea; text-decoration: none;">Register here</a>
                </div>
            </div>

            <!-- REGISTRATION FORM -->
            <div id="register" class="form-section">
                <h2>Join Our Program</h2>
                <p class="form-subtitle">Create your affiliate account in seconds</p>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="register">

                    <div class="form-group">
                        <label for="reg_name">Full Name</label>
                        <input type="text" id="reg_name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="reg_email">Email Address</label>
                        <input type="email" id="reg_email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="reg_phone">Phone Number</label>
                        <input type="tel" id="reg_phone" name="phone" required>
                    </div>

                    <div class="form-group">
                        <label for="reg_password">Password</label>
                        <input type="password" id="reg_password" name="password" required>
                    </div>

                    <div class="form-group">
                        <label for="reg_confirm">Confirm Password</label>
                        <input type="password" id="reg_confirm" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn">Create Account</button>
                </form>

                <div class="form-footer">
                    Already have an account? <a href="#" onclick="switchTab('login'); return false;" style="color: #667eea; text-decoration: none;">Login here</a>
                </div>
            </div>

            <a href="index.html" class="back-link">← Back to Store</a>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all sections
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });

            // Show selected section
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
  <script src="loader.js"></script>
</body>
</html>
