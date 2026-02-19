<?php
include '../db_config.php';
header('Content-Type: application/json');

$locations = [];
$sourceFile = __DIR__ . '/kenya_locations.json';
if (file_exists($sourceFile)) {
    $json = file_get_contents($sourceFile);
    $data = json_decode($json, true);
    if (is_array($data)) {
        foreach ($data as $county) {
            $name = $county['name'] ?? '';
            if ($name === '') { continue; }
            $subs = $county['sub_counties'] ?? [];
            $locations[$name] = array_values(array_unique(array_filter($subs)));
        }
    }
}

ksort($locations);
foreach ($locations as $c => $subs) { sort($locations[$c]); }

echo json_encode($locations);
$conn->close();
?>
