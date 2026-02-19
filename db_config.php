<?php
$host = getenv("DB_HOST");     // e.g., infinityfree host
$user = getenv("DB_USER");     // your database username
$password = getenv("DB_PASS"); // your database password
$db = getenv("DB_NAME");       // your database name

$conn = new mysqli($host, $user, $password, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
