<?php
include __DIR__ . '/../db_config.php';
$message = "";

// Ensure reset_token and reset_expires columns exist
@$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) DEFAULT NULL");
// Some MySQL versions don't support IF NOT EXISTS for ADD COLUMN; fall back
if (!$conn->query("SELECT reset_token FROM admins LIMIT 1")) {
    @$conn->query("ALTER TABLE admins ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL");
}
if (!$conn->query("SELECT reset_expires FROM admins LIMIT 1")) {
    @$conn->query("ALTER TABLE admins ADD COLUMN reset_expires DATETIME DEFAULT NULL");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username']); // can be username or email

    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        $token = bin2hex(random_bytes(16));
        $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        $update = $conn->prepare("UPDATE admins SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $update->bind_param("ssi", $token, $expiry, $user['id']);
        $update->execute();

        $resetLink = "http://localhost/onrizo/admin/reset_password.php?token=$token";

        $subject = "Orizo Shop Admin Password Reset";
        $body = "Click this link to reset your password:\n\n$resetLink\n\nThis link expires in 15 minutes.";
        $headers = "From: no-reply@orizoshop.com";

        // send to registered email if present
        if (!empty($user['email'])) {
            @mail($user['email'], $subject, $body, $headers);
            $message = "✅ Reset link sent to your registered email (if it exists).";
        } else {
            // show link on screen for testing
            $message = "✅ Reset link generated! (Copy below for testing)<br><a href='$resetLink'>$resetLink</a>";
        }
    } else {
        $message = "❌ User not found!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Orizo Shop</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <h2>🔑 Forgot Password</h2>

        <?php if ($message): ?>
            <p class="error"><?= $message ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Enter your username" required>
            <button type="submit">Send Reset Link</button>
        </form>

        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>
