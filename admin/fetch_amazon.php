<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
include __DIR__ . '/../db_config.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo json_encode(['success'=>false,'message'=>'Not authorized']);
    exit;
}

$url = trim($_POST['url'] ?? '');
if (!$url) { echo json_encode(['success'=>false,'message'=>'No URL provided']); exit; }

// basic allowlist for amazon domains
$host = parse_url($url, PHP_URL_HOST);
// allow common Amazon hosts and short links (amzn.to)
$allowed_hosts = ['amazon.', 'amazonaws.com', 'amzn.to', 'smile.amazon'];
$host_ok = false;
if ($host) {
    foreach ($allowed_hosts as $ah) {
        if (strpos($host, $ah) !== false) { $host_ok = true; break; }
    }
}
if (!$host_ok) {
    // not a known amazon host; still attempt fetch since short/redirect links may be used
    // but warn in response if result doesn't look like an Amazon product
    // continue to fetch
}

// Prefer headless fetch when available (more reliable for Amazon)
$html = '';
$err = '';
$debugPath = __DIR__ . '/last_fetched_product.html';
$nodeScript = __DIR__ . '/fetch_headless.js';
if (is_file($nodeScript)) {
    $cmd = 'node ' . escapeshellarg($nodeScript) . ' ' . escapeshellarg($url) . ' 2>&1';
    $out = @shell_exec($cmd);
    $decoded = null;
    if ($out) {
        $maybe = trim($out);
        $lines = preg_split('/\r?\n/', $maybe);
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '') continue;
            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                break;
            }
        }
    }

    if (is_array($decoded) && !empty($decoded['success']) && !empty($decoded['html'])) {
        $html = $decoded['html'];
        @file_put_contents($debugPath, "<!-- Fetched by headless: $url -->\n" . $html);
    }
}

if (!$html) {
    // fetch page with cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/115.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept-Language: en-US,en;q=0.9',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $html = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($html === false || !$html) {
        echo json_encode(['success'=>false,'message'=>'Failed to fetch URL: '.$err]); exit;
    }

    // DEBUG: save fetched HTML to a file for inspection by admin
    @file_put_contents($debugPath, "<!-- Fetched from: $url -->\n" . $html);
}

// Detect common Amazon bot-check / captcha / robot pages
$lower = strtolower($html);
$is_captcha = (strpos($lower, '/errors/validatecaptcha') !== false)
    || (strpos($lower, 'to discuss automated access to amazon data') !== false)
    || (strpos($lower, 'validatecaptcha') !== false && strpos($lower, 'continue shopping') !== false)
    || (strpos($lower, 'opfcaptcha.amazon.com') !== false);

if ($is_captcha) {
    echo json_encode(['success'=>false, 'message' => 'Amazon returned a bot-check / captcha page. Please retry or ensure headless fetching is enabled. See admin/last_fetched_product.html.']);
    exit;
}

// If a captcha page still slipped through, stop early with a helpful message
if (strpos($lower, 'captcha') !== false && strpos($lower, 'amazon.com') !== false && strpos($lower, 'product') === false) {
    echo json_encode(['success'=>false, 'message' => 'Amazon returned a captcha page. Please try again or use a different Amazon link. Check admin/last_fetched_product.html for the fetched HTML.']);
    exit;
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// default result
$result = ['success'=>false, 'title'=>'', 'description'=>'', 'images'=>[], 'price'=>'', 'message'=> 'Could not parse product details from the provided URL.'];

// title
$nodes = $xpath->query('//meta[@property="og:title"]/@content');
if ($nodes->length) $result['title'] = $nodes->item(0)->nodeValue;
if (!$result['title']) {
    $t = $xpath->query('//span[@id="productTitle"]');
    if ($t->length) $result['title'] = trim($t->item(0)->textContent);
}
if (!$result['title']) {
    $t = $dom->getElementsByTagName('title');
    if ($t->length) $result['title'] = $t->item(0)->textContent;
}

// description: combine 'About this item', product description, and product information (HTML)
$descParts = [];

$aboutSelectors = [
    '//*[@id="feature-bullets"]',
    '//*[@id="featurebullets_feature_div"]'
];
$aboutHtml = '';
foreach ($aboutSelectors as $sel) {
    $about = $xpath->query($sel);
    if ($about->length) {
        $aboutHtml = $dom->saveHTML($about->item(0));
        if ($aboutHtml) break;
    }
}
if ($aboutHtml) {
    $descParts[] = '<h3>About this item</h3>' . $aboutHtml;
}

$prodDescSelectors = [
    '//*[@id="productDescription"]',
    '//*[@id="bookDescription_feature_div"]',
    '//*[@id="aplus"]',
    '//*[@id="aplus_feature_div"]',
    '//*[@id="aplus3p_feature_div"]',
    '//*[@id="aplusHContainer"]',
    '//*[@id="productDescription_feature_div"]',
    '//*[@id="importantInformation_feature_div"]',
    '//*[@id="importantInformation"]',
    '//*[@id="productOverview_feature_div"]'
];
foreach ($prodDescSelectors as $sel) {
    $prodDesc = $xpath->query($sel);
    if ($prodDesc->length) {
        $descHtml = $dom->saveHTML($prodDesc->item(0));
        if ($descHtml) {
            $descParts[] = '<h3>Product description</h3>' . $descHtml;
            break;
        }
    }
}

$productInfoParts = [];
$infoSelectors = [
    '//*[@id="productDetails_techSpec_section_1"]',
    '//*[@id="productDetails_techSpec_section_2"]',
    '//*[@id="productDetails_detailBullets_sections1"]',
    '//*[@id="productDetails_detailBullets_sections2"]',
    '//*[@id="detailBullets_feature_div"]',
    '//*[@id="detailBulletsWrapper_feature_div"]',
    '//*[@id="prodDetails"]',
    '//*[@id="techSpec_feature_div"]',
    '//*[@id="productDetails_detailBullets_sections1"]//table',
    '//*[@id="productDetails_detailBullets_sections2"]//table',
    '//*[@id="technicalSpecifications_feature_div"]',
    '//*[@id="technicalSpecifications_section_1"]',
    '//*[@id="technicalSpecifications_section_2"]',
    '//*[@id="detailBulletsWrapper_feature_div"]//li',
    '//*[@id="detailBullets_feature_div"]//li'
];
foreach ($infoSelectors as $sel) {
    $nodes = $xpath->query($sel);
    if ($nodes->length) {
        $infoHtml = $dom->saveHTML($nodes->item(0));
        if ($infoHtml) {
            $productInfoParts[] = $infoHtml;
        }
    }
}
if (!empty($productInfoParts)) {
    $descParts[] = '<h3>Product information</h3>' . implode("\n", array_unique($productInfoParts));
}

$result['description'] = implode("\n", $descParts);

if (!$result['description']) {
    $nodes = $xpath->query('//meta[@property="og:description"]/@content');
    if ($nodes->length) $result['description'] = $nodes->item(0)->nodeValue;
}
if (!$result['description']) {
    $nodes = $xpath->query('//meta[@name="description"]/@content');
    if ($nodes->length) $result['description'] = $nodes->item(0)->nodeValue;
}

// images: determine main image and additional gallery images
$imgs = [];
$mainImage = '';
$nodes = $xpath->query('//img[@id="landingImage"]/@data-a-dynamic-image');
if ($nodes->length) {
    $json = $nodes->item(0)->nodeValue;
    $json = html_entity_decode($json);
    $arr = json_decode($json, true);
    if (is_array($arr)) {
        foreach ($arr as $k => $v) {
            $imgs[] = $k;
            if (!$mainImage) {
                $mainImage = $k;
            }
        }
    }
}

// main image src
$nodes = $xpath->query('//img[@id="landingImage"]/@src');
foreach ($nodes as $n) {
    if ($n->nodeValue) {
        if (!$mainImage) $mainImage = $n->nodeValue;
        $imgs[] = $n->nodeValue;
    }
}

// alternative images in image block
$nodes = $xpath->query('//*[@id="altImages"]//img/@src');
foreach ($nodes as $n) { if ($n->nodeValue) $imgs[] = $n->nodeValue; }

// parse image data from scripts (colorImages or imageGalleryData or imageBlock)
$scriptNodes = $xpath->query('//script[contains(text(),"colorImages") or contains(text(),"imageGalleryData") or contains(text(),"ImageBlockATF") or contains(text(),"ImageBlockBTF") or contains(text(),"imageBlockJSON") or contains(text(),"imageBlockData") or contains(text(),"twister") or contains(text(),"data\" : {\"colorImages\"")]');
foreach ($scriptNodes as $scriptNode) {
    $scriptText = $scriptNode->textContent;
    if (!$scriptText) continue;
    if (preg_match_all('/\"(https?:\\\/\\\/[^\"]+?\.(?:jpg|jpeg|png))\"/i', $scriptText, $matches)) {
        foreach ($matches[1] as $rawUrl) {
            $url = str_replace('\\/', '/', $rawUrl);
            $imgs[] = $url;
        }
    }
}

// parse data-a-dynamic-image on any image in the gallery
$dynamicNodes = $xpath->query('//*[@data-a-dynamic-image]');
foreach ($dynamicNodes as $n) {
    $json = $n->getAttribute('data-a-dynamic-image');
    if (!$json) continue;
    $json = html_entity_decode($json);
    $arr = json_decode($json, true);
    if (is_array($arr)) {
        foreach ($arr as $k => $v) {
            $imgs[] = $k;
        }
    }
}

// fallback: og:image
$nodes = $xpath->query('//meta[@property="og:image"]/@content');
foreach ($nodes as $n) {
    if ($n->nodeValue) {
        if (!$mainImage) $mainImage = $n->nodeValue;
        $imgs[] = $n->nodeValue;
    }
}

// rel=image_src
$nodes = $xpath->query('//link[@rel="image_src"]/@href');
foreach ($nodes as $n) {
    if ($n->nodeValue) {
        if (!$mainImage) $mainImage = $n->nodeValue;
        $imgs[] = $n->nodeValue;
    }
}

// sanitize unique and split main/additional
$imgs = array_values(array_filter(array_unique($imgs)));
$additionalImgs = [];
foreach ($imgs as $img) {
    if ($mainImage && $img === $mainImage) {
        continue;
    }
    $additionalImgs[] = $img;
}
if (!$mainImage && !empty($imgs)) {
    $mainImage = $imgs[0];
    $additionalImgs = array_values(array_diff($imgs, [$mainImage]));
}

$result['main_image'] = $mainImage;
$result['images'] = $additionalImgs;

// price: try common Amazon selectors
$price = '';
$priceIds = ['priceblock_ourprice','priceblock_dealprice','priceblock_saleprice'];
foreach ($priceIds as $id) {
    $n = $xpath->query('//*[@id="' . $id . '"]');
    if ($n->length) { $price = trim($n->item(0)->textContent); break; }
}
if (!$price) {
    // look for meta product:price:amount
    $n = $xpath->query('//meta[@property="product:price:amount"]/@content');
    if ($n->length) $price = $n->item(0)->nodeValue;
}
if (!$price) {
    // common class for price
    $n = $xpath->query('//*[contains(@class,"a-price-whole")]');
    if ($n->length) {
        $whole = trim($n->item(0)->textContent);
        // fraction
        $f = $xpath->query('//*[contains(@class,"a-price-fraction")]');
        $fraction = $f->length ? trim($f->item(0)->textContent) : '';
        $price = $whole . ($fraction ? '.' . $fraction : '');
    }
}

$result['price'] = $price;

// category from breadcrumbs
$category = '';
$crumb = $xpath->query('//*[@id="wayfinding-breadcrumbs_container"]//a');
if ($crumb->length) {
    $category = trim($crumb->item($crumb->length - 1)->textContent);
}
if (!$category) {
    $crumb = $xpath->query('//div[contains(@class,"breadcrumb")]//a');
    if ($crumb->length) {
        $category = trim($crumb->item($crumb->length - 1)->textContent);
    }
}
$result['category'] = $category;

// Determine success: require at least a title or one image or a price
if (!empty($result['title']) || !empty($result['main_image']) || !empty($result['images']) || !empty($result['price'])) {
    $result['success'] = true;
    unset($result['message']);
} else {
    $result['success'] = false;
    $result['message'] = 'No recognizable product data found on the page. Try using the full Amazon product URL (not a non-Amazon redirect) or check network access on the server.';
}

echo json_encode($result);
exit;

?>
