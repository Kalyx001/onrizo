<?php
// Test the get_products API
echo "<h2>🧪 Testing get_products.php API</h2>";

// Include database config
include 'db_config.php';

// Simulate the get_products.php logic
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? floatval($_GET['max_price']) : 0;

// Build query
$sql = "SELECT p.*, a.phone AS whatsapp_number 
        FROM products p
        LEFT JOIN admins a ON p.admin_id = a.id";

$conds = [];
$params = [];
$types = "";

if ($category !== '') {
    $conds[] = "LOWER(p.category) LIKE CONCAT('%', LOWER(?), '%')";
    $types .= "s";
    $params[] = $category;
}

if ($location !== '') {
    $conds[] = "a.county = ?";
    $types .= "s";
    $params[] = $location;
}

if ($max_price > 0) {
    $conds[] = "p.price <= ?";
    $types .= "d";
    $params[] = $max_price;
}

if (count($conds) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conds);
}

$sql .= " ORDER BY RAND()";

echo "<h3>Query:</h3>";
echo "<pre>$sql</pre>";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo "<p style='color:red;'><strong>❌ Prepare failed:</strong> " . $conn->error . "</p>";
    exit;
}

if (count($params) > 0) {
    $bind_names = [$types];
    for ($i=0; $i<count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();

echo "<h3>Results:</h3>";
echo "<p><strong>Total Products:</strong> " . count($products) . "</p>";

if (count($products) > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Admin</th></tr>";
    foreach ($products as $p) {
        echo "<tr>";
        echo "<td>" . $p['id'] . "</td>";
        echo "<td>" . htmlspecialchars($p['name']) . "</td>";
        echo "<td>" . htmlspecialchars($p['category']) . "</td>";
        echo "<td>KES " . number_format($p['price']) . "</td>";
        echo "<td>" . htmlspecialchars($p['whatsapp_number']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>JSON Output (for JavaScript):</h3>";
    echo "<pre>";
    echo json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "</pre>";
} else {
    echo "<p style='color:orange;'><strong>⚠️ No products found</strong></p>";
}

$conn->close();
?>

<hr>
<p><a href="index.html">← Back to Home</a></p>
