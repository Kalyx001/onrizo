<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🧪 Admin Registration Debug Test</h2>";

// Test database connection
include '../db_config.php';

if ($conn->connect_error) {
    echo "<p style='color:red;'><strong>❌ Database Connection Error:</strong> " . $conn->connect_error . "</p>";
    exit;
}

echo "<p style='color:green;'><strong>✅ Database Connected</strong></p>";

// Check admins table
$result = $conn->query("DESCRIBE admins");
if ($result) {
    echo "<h3>📋 Admins Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'><strong>❌ Error describing table:</strong> " . $conn->error . "</p>";
}

// Check existing admins
echo "<h3>👥 Existing Admin Accounts:</h3>";
$adminResult = $conn->query("SELECT id, username, email, created_at FROM admins");
if ($adminResult && $adminResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Created</th></tr>";
    while ($admin = $adminResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $admin['id'] . "</td>";
        echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
        echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
        echo "<td>" . $admin['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No admin accounts found (this is OK for first setup)</p>";
}

// Test registration form submission
echo "<h3>📝 Test Registration Form</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h4>Form Data Received:</h4>";
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $county = trim($_POST['county'] ?? '');
    $subcounty = trim($_POST['subcounty'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    echo "<ul>";
    echo "<li><strong>Name:</strong> " . htmlspecialchars($name) . "</li>";
    echo "<li><strong>Email:</strong> " . htmlspecialchars($email) . "</li>";
    echo "<li><strong>Phone:</strong> " . htmlspecialchars($phone) . "</li>";
    echo "<li><strong>County:</strong> " . htmlspecialchars($county) . "</li>";
    echo "<li><strong>Subcounty:</strong> " . htmlspecialchars($subcounty) . "</li>";
    echo "<li><strong>Username:</strong> " . htmlspecialchars($username) . "</li>";
    echo "<li><strong>Password:</strong> " . (strlen($password) > 0 ? "[" . strlen($password) . " chars]" : "[EMPTY]") . "</li>";
    echo "</ul>";
    
    // Validation checks
    echo "<h4>Validation Checks:</h4>";
    
    if (empty($name) || empty($email) || empty($phone) || empty($county) || empty($subcounty) || empty($username) || empty($password)) {
        echo "<p style='color:red;'><strong>❌ Empty Fields:</strong> All fields must be filled</p>";
    } else {
        echo "<p style='color:green;'><strong>✅ All fields filled</strong></p>";
    }
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<p style='color:red;'><strong>❌ Invalid Email:</strong> " . htmlspecialchars($email) . "</p>";
    } else {
        echo "<p style='color:green;'><strong>✅ Valid Email</strong></p>";
    }
    
    // Phone validation
    if (!preg_match('/^(?:254|\+254|0)?7\d{8}$/', $phone)) {
        echo "<p style='color:red;'><strong>❌ Invalid Phone:</strong> Must be Kenyan format (e.g., 254712345678)</p>";
    } else {
        echo "<p style='color:green;'><strong>✅ Valid Phone Number</strong></p>";
    }
    
    // Password strength
    $passwordErrors = [];
    if (strlen($password) < 8) {
        $passwordErrors[] = "Less than 8 characters";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $passwordErrors[] = "No uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $passwordErrors[] = "No lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $passwordErrors[] = "No number";
    }
    
    if (count($passwordErrors) > 0) {
        echo "<p style='color:red;'><strong>❌ Weak Password:</strong>";
        echo "<ul>";
        foreach ($passwordErrors as $err) {
            echo "<li>" . $err . "</li>";
        }
        echo "</ul></p>";
    } else {
        echo "<p style='color:green;'><strong>✅ Strong Password</strong></p>";
    }
    
    // Password match
    if ($password !== $confirm_password) {
        echo "<p style='color:red;'><strong>❌ Passwords don't match</strong></p>";
    } else {
        echo "<p style='color:green;'><strong>✅ Passwords match</strong></p>";
    }
    
    // Check if username/email exists
    echo "<h4>Database Checks:</h4>";
    $check = $conn->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        echo "<p style='color:red;'><strong>❌ Username or Email Already Exists</strong></p>";
    } else {
        echo "<p style='color:green;'><strong>✅ Username and Email available</strong></p>";
    }
    $check->close();
    
    // Try to insert
    echo "<h4>Insert Attempt:</h4>";
    if (empty($name) || empty($email) || empty($phone) || empty($county) || empty($subcounty) || empty($username) || empty($password)) {
        echo "<p style='color:orange;'>⚠️ Skipped: Empty fields detected</p>";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (name, email, phone, county, subcounty, username, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt === false) {
            echo "<p style='color:red;'><strong>❌ Prepare Error:</strong> " . $conn->error . "</p>";
        } else {
            $stmt->bind_param("sssssss", $name, $email, $phone, $county, $subcounty, $username, $hashed);
            
            if ($stmt->execute()) {
                echo "<p style='color:green;'><strong>✅ Account Created Successfully!</strong></p>";
                echo "<p>You can now <a href='login.php'><strong>Login here</strong></a></p>";
            } else {
                echo "<p style='color:red;'><strong>❌ Insert Error:</strong> " . $stmt->error . "</p>";
            }
            $stmt->close();
        }
    }
}

// Show test form
echo "<form method='POST' action='' style='margin-top:20px; background:#f9f9f9; padding:15px; border-radius:8px;'>";
echo "<p><strong>Quick Test Form:</strong></p>";
echo "<input type='text' name='name' placeholder='Full Name' value='Test User' required>";
echo "<input type='email' name='email' placeholder='Email' value='test" . time() . "@example.com' required>";
echo "<input type='text' name='phone' placeholder='Phone (254712345678)' value='254712345678' required>";
echo "<input type='text' name='county' placeholder='County' value='Nairobi' required>";
echo "<input type='text' name='subcounty' placeholder='Sub-County' value='Westlands' required>";
echo "<input type='text' name='username' placeholder='Username' value='testuser" . time() . "' required>";
echo "<input type='password' name='password' placeholder='Password (e.g., TestPass123)' value='TestPass123' required>";
echo "<input type='password' name='confirm_password' placeholder='Confirm Password' value='TestPass123' required>";
echo "<button type='submit' style='background:#0270df; color:white; padding:10px 20px; border:none; border-radius:5px; cursor:pointer;'>Test Registration</button>";
echo "</form>";

$conn->close();
?>

<hr style="margin-top:20px;">
<p><a href="register.php">← Back to Registration Form</a></p>
