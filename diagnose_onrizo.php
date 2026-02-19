<?php
// Diagnostic helper - safe to place temporarily on server for debugging
// Removes sensitive output unless you run it in a browser you control.
header('Content-Type: text/plain; charset=utf-8');

// PHP info summary
echo "PHP Version: " . phpversion() . "\n";
echo "SAPI: " . PHP_SAPI . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "error_reporting: " . error_reporting() . "\n";

// Include DB config and test connection
$cfgPath = __DIR__ . '/db_config.php';
if (file_exists($cfgPath)) {
    echo "db_config.php found.\n";
    // Try to include but suppress die output to capture failure
    ob_start();
    include $cfgPath;
    $out = ob_get_clean();
    echo "db_config include output (if any):\n" . trim($out) . "\n";

    if (isset($conn) && $conn instanceof mysqli) {
        if ($conn->connect_error) {
            echo "DB connect error (mysqli): " . $conn->connect_error . "\n";
        } else {
            echo "DB connected OK. Host: " . $conn->host_info . "\n";
        }
    } else {
        echo "No mysqli \$conn object available after include.\n";
    }
} else {
    echo "db_config.php NOT found at expected path: $cfgPath\n";
}

// Check uploads dir
$uploads = __DIR__ . '/uploads';
if (is_dir($uploads)) {
    echo "uploads directory exists.\n";
    $files = array_slice(scandir($uploads, SCANDIR_SORT_DESCENDING), 0, 20);
    echo "Sample files (up to 20):\n";
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $uploads . '/' . $f;
        echo " - $f (size=" . filesize($path) . ", perms=" . substr(sprintf('%o', fileperms($path)), -4) . ")\n";
    }
} else {
    echo "uploads directory NOT found at: $uploads\n";
}

// Example image URL generation using get_product.php logic
$example = 'uploads/example.jpg';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'HOST_UNKNOWN';
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http';
$scriptDir = dirname($_SERVER['REQUEST_URI'] ?? '/');
$exampleUrl = sprintf('%s://%s%s/../%s', $proto, $host, $scriptDir, ltrim($example, '/'));
echo "Example generated image URL: " . $exampleUrl . "\n";

// Quick permission checks for this script
echo "Script path: " . __FILE__ . "\n";
echo "cwd: " . getcwd() . "\n";

// Suggest next steps (displayed so user sees them when they open this file)
echo "\nQuick next steps:\n";
echo " - If DB connection fails, update db_config.php credentials to match hosting DB.\n";
echo " - Check server error logs for the 500 cause (apache/nginx/php-fpm).\n";
echo " - Ensure uploads/ exists and is readable by the web server (chmod 755/644).\n";

?>