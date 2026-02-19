<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db_config.php';

$message = ""; // Initialize message

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = trim($_POST['identifier']); // can be email or username
    $password = trim($_POST['password']);

    // Use prepared statement for safety
    $stmt = $conn->prepare("SELECT id, username, email, password FROM admins WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();
        $storedHash = $user['password'];

        // ✅ Handle both hashed and plain-text passwords
        if (password_verify($password, $storedHash) || $password === $storedHash) {
            // Set session variables
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_id'] = (int)$user['id'];
            header("Location: dashboard.php");
            exit;
        } else {
            $message = "❌ Invalid email/username or password.";
        }
    } else {
        $message = "❌ Email or username not found.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - Orizo Shop</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="login-body">
    <div id="pageLoader" class="active">
        <div>
            <div class="spinner"></div>
            <div class="loader-text">Loading...</div>
        </div>
    </div>
    <div class="login-container">
        <h2>👤 Admin Login</h2>

        <?php if ($message): ?>
            <p class="error"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="identifier" placeholder="Enter email or username" required>
            <input type="password" name="password" placeholder="Enter password" required>
            <button type="submit">Login</button>
        </form>
        <p><a href="register.php">Register</a></p>
        <p><a href="forgot_password.php">Forgot Password?</a></p>
    </div>
    <script src="../loader.js"></script>
</body>
</html>
