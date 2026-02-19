<?php
// ✅ LOCAL XAMPP DATABASE
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "onrizo_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for better compatibility
$conn->set_charset("utf8mb4");

// Optional: confirm connection
// echo "✅ Database connected successfully!";
?>
