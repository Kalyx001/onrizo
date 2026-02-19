<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "sql109.infinityfree.com";
$user = "if0_40205357";
$pass = "ZYgJnVZH6t";
$dbname = "if0_40205357_shop_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "✅ Database connection successful!";
?>
