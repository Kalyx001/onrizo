<?php
session_start();
include 'db_config.php';

// Check if affiliate is logged in
if (!isset($_SESSION['affiliate_logged_in']) || $_SESSION['affiliate_logged_in'] !== true) {
    header("Location: affiliate_login.php");
    exit;
}

$affiliate_id = (int)($_SESSION['affiliate_id'] ?? 0);
$_SESSION['affiliate_logged_in'] = false;
session_destroy();

header("Location: affiliate_login.php");
exit;
?>
