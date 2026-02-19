<?php
include __DIR__ . '/../db_config.php';
$message = "";
$user = null;

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT * FROM admins WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newPass = trim($_POST['password'] ?? '');
            if (strlen($newPass) < 6) {
                $message = 'Password must be at least 6 characters.';
            } else {
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);

                $update = $conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                $update->bind_param('si', $hashed, $user['id']);

                if ($update->execute()) {
                    $message = "✅ Password successfully reset! <a href='login.php'>Login now</a>";
                    $user = null;
                } else {
                    $message = "❌ Something went wrong. Try again.";
                }
            }
        }
    } else {
        $message = "⚠️ Invalid or expired token!";
    }
} else {
    $message = "Invalid request!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Orizo Shop</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <h2>🔒 Reset Password</h2>

        <?php if ($message): ?>
            <p class="error"><?= $message ?></p>
        <?php endif; ?>

        <?php if ($user): ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Enter new password" required>
            <button type="submit">Reset Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
