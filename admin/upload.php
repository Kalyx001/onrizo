<?php
session_start();
include __DIR__ . '/../db_config.php';

// only logged-in admins can upload
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

// sanitize inputs
$name = trim($_POST['name']);
$price = floatval($_POST['price']);
$description = trim($_POST['description']);
$category = trim($_POST['category']);

// uploads folder (relative to htdocs)
$uploadDir = __DIR__ . '/../uploads/';

// make sure uploads folder exists
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowed = ['jpg','jpeg','png','gif'];

function optimizeImage($sourcePath, $destPath, $ext, $maxWidth = 1280, $maxHeight = 1280, $quality = 75) {
    if (!extension_loaded('gd')) {
        // fallback: just move original
        return copy($sourcePath, $destPath);
    }

    $ext = strtolower($ext);
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $src = @imagecreatefromjpeg($sourcePath);
            break;
        case 'png':
            $src = @imagecreatefrompng($sourcePath);
            break;
        case 'gif':
            $src = @imagecreatefromgif($sourcePath);
            break;
        default:
            return copy($sourcePath, $destPath);
    }

    if (!$src) return copy($sourcePath, $destPath);

    $width = imagesx($src);
    $height = imagesy($src);
    $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
    $newW = (int)($width * $ratio);
    $newH = (int)($height * $ratio);

    $dst = imagecreatetruecolor($newW, $newH);
    if ($ext === 'png' || $ext === 'gif') {
        imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

    $saved = false;
    if ($ext === 'png') {
        $saved = imagepng($dst, $destPath, 7);
    } elseif ($ext === 'gif') {
        $saved = imagegif($dst, $destPath);
    } else {
        $saved = imagejpeg($dst, $destPath, $quality);
    }

    imagedestroy($src);
    imagedestroy($dst);

    return $saved;
}

// MAIN IMAGE: prefer uploaded file; if none, allow image_url to be provided
$relativeMain = '';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $mainExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($mainExt, $allowed)) die("Invalid main image type");
    $uniqueMain = uniqid('IMG_', true) . '.' . $mainExt;
    $targetMain = $uploadDir . $uniqueMain;
    $tmpMain = $_FILES['image']['tmp_name'];
    if (!optimizeImage($tmpMain, $targetMain, $mainExt)) { die("Failed to process main image"); }
    $relativeMain = 'uploads/' . $uniqueMain;
} else {
    // try image_url
    $image_url = trim($_POST['image_url'] ?? '');
    if ($image_url) {
        // download remote image
        $ch = curl_init($image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
        $data = curl_exec($ch);
        $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($data) {
            // determine extension
            $ext = '';
            if ($ct) {
                if (strpos($ct, 'jpeg') !== false) $ext = 'jpg';
                elseif (strpos($ct, 'png') !== false) $ext = 'png';
                elseif (strpos($ct, 'gif') !== false) $ext = 'gif';
            }
            if (!$ext) {
                $pathParts = pathinfo(parse_url($image_url, PHP_URL_PATH));
                $ext = strtolower($pathParts['extension'] ?? 'jpg');
            }
            if (!in_array($ext, $allowed)) $ext = 'jpg';
            $uniqueMain = uniqid('IMG_', true) . '.' . $ext;
            $targetMain = $uploadDir . $uniqueMain;
            file_put_contents($targetMain, $data);
            optimizeImage($targetMain, $targetMain, $ext);
            $relativeMain = 'uploads/' . $uniqueMain;
        }
    }
    if (!$relativeMain) { die("Main image upload failed"); }
}

// INSERT product
$affiliate_percent = isset($_POST['affiliate_percent']) && $_POST['affiliate_percent'] !== ''
    ? floatval($_POST['affiliate_percent'])
    : null;

$colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'affiliate_percent'");
$hasAffiliatePercent = $colCheck && $colCheck->num_rows > 0;
if (!$hasAffiliatePercent) {
    // silently migrate to add affiliate_percent column
    @$conn->query("ALTER TABLE products ADD COLUMN affiliate_percent DECIMAL(5,2) DEFAULT NULL");
    $colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'affiliate_percent'");
    $hasAffiliatePercent = $colCheck && $colCheck->num_rows > 0;
}

if ($hasAffiliatePercent) {
    $stmt = $conn->prepare("INSERT INTO products (admin_id, name, price, description, category, image, affiliate_percent, date_added)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isdsssd", $admin_id, $name, $price, $description, $category, $relativeMain, $affiliate_percent);
} else {
    $stmt = $conn->prepare("INSERT INTO products (admin_id, name, price, description, category, image, date_added)
    VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isdsss", $admin_id, $name, $price, $description, $category, $relativeMain);
}
$stmt->execute();

$productId = $stmt->insert_id;
$stmt->close();

// ADDITIONAL IMAGES
if (!empty($_FILES['images']['name'][0])) {
    foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
        if (!is_uploaded_file($tmp)) continue;
        $ext = strtolower(pathinfo($_FILES['images']['name'][$k], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;

        $unique = uniqid('IMG_', true) . '.' . $ext;
        $target = $uploadDir . $unique;
        if (move_uploaded_file($tmp, $target)) {
            optimizeImage($target, $target, $ext, 1280, 1280, 75);
            $relative = 'uploads/' . $unique;
            $ins = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
            $ins->bind_param("is", $productId, $relative);
            $ins->execute();
            $ins->close();
        }
    }
}

// Additional images provided as URLs (from scraper)
$images_urls = trim($_POST['images_urls'] ?? '');
if ($images_urls) {
    $arr = json_decode($images_urls, true);
    if (is_array($arr)) {
        foreach ($arr as $imgUrl) {
            $imgUrl = trim($imgUrl);
            if (!$imgUrl) continue;
            $ch = curl_init($imgUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
            $data = curl_exec($ch);
            $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            if (!$data) continue;
            $ext = '';
            if ($ct) {
                if (strpos($ct, 'jpeg') !== false) $ext = 'jpg';
                elseif (strpos($ct, 'png') !== false) $ext = 'png';
                elseif (strpos($ct, 'gif') !== false) $ext = 'gif';
            }
            if (!$ext) {
                $pathParts = pathinfo(parse_url($imgUrl, PHP_URL_PATH));
                $ext = strtolower($pathParts['extension'] ?? 'jpg');
            }
            if (!in_array($ext, $allowed)) $ext = 'jpg';
            $unique = uniqid('IMG_', true) . '.' . $ext;
            $target = $uploadDir . $unique;
            file_put_contents($target, $data);
            optimizeImage($target, $target, $ext, 1280, 1280, 75);
            $relative = 'uploads/' . $unique;
            $ins = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
            $ins->bind_param("is", $productId, $relative);
            $ins->execute();
            $ins->close();
        }
    }
}

$conn->close();
header("Location: dashboard.php?added=1");
exit;
?>
