<?php
echo "<h2>🧪 Database Connection Test</h2>";

// Include the database configuration
include 'db_config.php';

// Test connection
if ($conn->connect_error) {
    echo "<p style='color: red;'><strong>❌ Connection Failed:</strong> " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'><strong>✅ Connected Successfully!</strong></p>";
    
    // Check database
    $dbCheckQuery = $conn->query("SELECT DATABASE()");
    $dbRow = $dbCheckQuery->fetch_row();
    echo "<p>📦 <strong>Current Database:</strong> " . htmlspecialchars($dbRow[0]) . "</p>";
    
    // Check tables
    echo "<h3>📋 Available Tables:</h3>";
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "<ul>";
        while ($row = $result->fetch_row()) {
            echo "<li>" . htmlspecialchars($row[0]) . "</li>";
        }
        echo "</ul>";
        
        // Check records count
        echo "<h3>📊 Record Counts:</h3>";
        $adminCount = $conn->query("SELECT COUNT(*) as count FROM admins")->fetch_assoc()['count'];
        $productCount = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
        $orderCount = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
        
        echo "<p><strong>Admins:</strong> " . $adminCount . "</p>";
        echo "<p><strong>Products:</strong> " . $productCount . "</p>";
        echo "<p><strong>Orders:</strong> " . $orderCount . "</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Could not fetch tables: " . $conn->error . "</p>";
    }
}

$conn->close();
?>

<hr>
<p><a href="index.html">← Back to Home</a></p>
