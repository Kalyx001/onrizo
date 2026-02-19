<?php
include '../db_config.php';
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>
    <link rel="stylesheet" href="admin_style.css">
    <!-- Quill Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .product-form { max-width: 980px; margin: 24px auto; padding: 24px; background:#fff;border-radius:12px;box-shadow:0 10px 24px rgba(0,0,0,0.08);} 
        .product-form h2 { margin-top:0; }
        .form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:16px; }
        .form-row { display:flex; gap:12px; align-items:center; }
        .full { grid-column: 1 / -1; }
        label { display:block; margin:10px 0 6px; font-weight:600; color:#333; }
        input[type="text"], input[type="number"], textarea, select, input[type="url"] { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .hint { font-size:12px; color:#777; margin-top:4px; }
        .file-upload-container { margin-top:6px; }
        .file-upload-label { display:flex; align-items:center; gap:12px; padding:10px; border:1px dashed #ddd; border-radius:8px; cursor:pointer; color:#333; }
        .image-preview img { max-width:220px; border-radius:8px; display:block; margin-top:8px; }
        #fetchedImages img { height:80px; margin-right:8px; border-radius:6px; border:1px solid #eee; cursor:pointer; }
        .section-title { font-weight:700; color:#111; margin:16px 0 8px; }
        .fetch-box { background:#f7f8fb; border:1px solid #e5e7eb; padding:12px; border-radius:10px; }
    </style>
</head>
<body>
  <div id="pageLoader" class="active">
    <div>
      <div class="spinner"></div>
      <div class="loader-text">Loading...</div>
    </div>
  </div>

<header>
    <h1>Add New Product</h1>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="view_products.php">View Products</a>
    </nav>
</header>

<main>
    <form action="upload.php" method="POST" enctype="multipart/form-data" class="product-form">
        <input type="hidden" id="image_url" name="image_url" value="">
        <input type="hidden" id="images_urls" name="images_urls" value="">
        <h2>Product Details</h2>
        <div class="fetch-box full">
            <label>Product Link (paste Amazon product URL to auto-fill)</label>
            <div class="form-row">
                <input type="url" id="productUrl" placeholder="https://www.amazon.com/your-product..." style="flex:1;" />
                <button type="button" id="fetchBtn" class="btn">Fetch</button>
            </div>
            <div id="fetchStatus" class="hint">Paste a full Amazon product URL for best results.</div>
        </div>

        <div class="form-grid">
            <div>
                <label>Product Name</label>
                <input type="text" name="name" placeholder="Enter product name" required maxlength="245">
            </div>
            <div>
                <label>Price (KES)</label>
                <input type="number" name="price" step="0.01" placeholder="Enter price" required>
            </div>
            <div>
                <label>Affiliate %</label>
                <input type="number" name="affiliate_percent" step="0.01" min="0" max="50" placeholder="e.g. 10">
                <div class="hint">Leave empty to use default (15%).</div>
            </div>
            <div class="full">
                <label>Category</label>
                <select name="category" required>
                    <option value="">Select category</option>
                    <option value="Smartphones">Phones/parts</option>
                    <option value="Smartwatches">Watches/Jewelery</option>
                    <option value="Cameras">Cameras</option>
                    <option value="Accessories">Accessories</option>
                    <option value="Laptops">Computers/Laptops</option>
                    <option value="Tablets">Tablets</option>
                    <option value="Gaming">Gaming</option>
                    <option value="Tech">Tech-Gadgets</option>
                    <option value="Loan Items">Loan Items</option>
                </select>
            </div>
        </div>

        <div class="section-title">Description</div>
        <div id="editor" style="height:150px; background:#fff; border-radius:5px;"></div>
        <input type="hidden" name="description" id="description">

        <div class="section-title">Images</div>
        <label style="margin-top: 20px;">📷 Main Product Image</label>
    <div id="fetchedImages" style="margin-bottom:10px; display:none;"></div>
        <div class="file-upload-container">
            <input type="file" id="mainImage" name="image" accept="image/*" required hidden>
            <label for="mainImage" class="file-upload-label">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <span>Click to upload main image</span>
            </label>
            <div id="mainImagePreview" class="image-preview" style="display:none;">
                <img id="mainImagePreviewImg" src="" alt="Preview">
                <button type="button" class="remove-btn" onclick="removeMainImage()">✕ Remove</button>
            </div>
        </div>

        <label style="margin-top: 20px;">🖼️ Additional Images (Optional)</label>
        <div class="hint">You can upload multiple images or use the fetched Amazon gallery.</div>
        <div class="file-upload-container">
            <input type="file" id="extraImages" name="images[]" accept="image/*" multiple hidden>
            <label for="extraImages" class="file-upload-label">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
                <span>Click to upload additional images</span>
            </label>
            <div id="extraImagesPreview" class="gallery-preview" style="display:none;">
                <div id="galleryContainer"></div>
            </div>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px;align-items:center;">
            <button type="submit" class="btn primary">🚀 Upload Product</button>
            <a href="view_products.php" class="btn" style="background:#f3f4f6;color:#333;padding:8px 12px;border-radius:6px;text-decoration:none;">← Back to list</a>
            <span id="uploadHint" style="font-size:13px;color:#666;margin-left:auto;">Tip: Use Fetch then review images before uploading.</span>
        </div>
    </form>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> Orizo Admin Panel</p>
</footer>

<!-- Quill JS -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
    const quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Enter product description...'
    });
    const fetchStatusEl = document.getElementById('fetchStatus');

    document.querySelector('form').addEventListener('submit', () => {
        document.getElementById('description').value = quill.root.innerHTML;
    });

    // Main Image Upload Handler
    const mainImage = document.getElementById('mainImage');
    mainImage.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                const preview = document.getElementById('mainImagePreview');
                const img = document.getElementById('mainImagePreviewImg');
                img.src = event.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    // Extra Images Upload Handler
    const extraImages = document.getElementById('extraImages');
    extraImages.addEventListener('change', (e) => {
        const files = e.target.files;
        const gallery = document.getElementById('galleryContainer');
        const preview = document.getElementById('extraImagesPreview');
        
        if (files.length > 0) {
            gallery.innerHTML = '';
            Array.from(files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (event) => {
                    const div = document.createElement('div');
                    div.className = 'gallery-item';
                    div.innerHTML = `
                        <img src="${event.target.result}" alt="Preview ${index + 1}">
                        <button type="button" class="remove-btn" onclick="removeImage(${index})">✕</button>
                    `;
                    gallery.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
            preview.style.display = 'grid';
        }
    });

    function removeMainImage() {
        document.getElementById('mainImage').value = '';
        document.getElementById('mainImagePreview').style.display = 'none';
    }

    function removeImage(index) {
        const fileInput = document.getElementById('extraImages');
        const dt = new DataTransfer();
        const files = fileInput.files;
        
        for (let i = 0; i < files.length; i++) {
            if (i !== index) {
                dt.items.add(files[i]);
            }
        }
        fileInput.files = dt.files;
        
        // Trigger change event to update preview
        extraImages.dispatchEvent(new Event('change', { bubbles: true }));
    }

    extraImages.addEventListener('change', () => {
        const count = extraImages.files.length;
        const extraFileNameEl = document.getElementById('extraFileName');
        if (extraFileNameEl) extraFileNameEl.textContent = count ? `${count} file(s) selected` : 'No files chosen';
    });

    // Fetch product data from Amazon
    const fetchBtn = document.getElementById('fetchBtn');
    const productUrlInput = document.getElementById('productUrl');
    fetchBtn.addEventListener('click', async () => {
        const url = productUrlInput.value.trim();
        const statusEl = fetchStatusEl;
        if (!url) { statusEl.textContent = 'Paste an Amazon product URL first'; return; }
        fetchBtn.disabled = true; fetchBtn.textContent = 'Fetching...'; statusEl.textContent = 'Fetching product details...';
        try {
            const resp = await fetch('fetch_amazon.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ url }) });
            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status} while fetching product.`);
            }
            const rawText = await resp.text();
            let j = null;
            try {
                j = JSON.parse(rawText);
            } catch (parseErr) {
                console.error('fetch_amazon parse error:', parseErr, rawText);
                statusEl.textContent = 'Fetch failed: Invalid response from server.';
                alert('Fetch failed: Invalid response from server. Check server logs or fetch_amazon.php output.');
                return;
            }
            console.log('fetch_amazon result:', j);
            if (!j || !j.success) {
                statusEl.textContent = 'Fetch failed: ' + (j.message || 'Unknown');
                alert('Fetch failed: ' + (j.message || 'Unknown') + '\nTip: Use the full Amazon product URL, not a shortened link.');
                return;
            }
            statusEl.textContent = 'Fetched product data successfully.';
            // populate fields
            if (j.title) document.querySelector('input[name="name"]').value = j.title;
            if (j.price) {
                // extract numeric from price string
                const num = (String(j.price).replace(/[^0-9\.]/g, '')) || '';
                if (num) document.querySelector('input[name="price"]').value = parseFloat(num);
            }
            if (j.description) quill.root.innerHTML = j.description;
            if (j.category) {
                const catSelect = document.querySelector('select[name="category"]');
                if (catSelect) {
                    const opt = Array.from(catSelect.options).find(o => o.textContent.trim().toLowerCase() === j.category.trim().toLowerCase());
                    if (opt) catSelect.value = opt.value;
                }
            }
            // images
            const mainImageUrl = j.main_image || (j.images && j.images.length ? j.images[0] : '');
            const additionalImages = j.images && j.images.length ? j.images : [];
            if (mainImageUrl || additionalImages.length) {
                document.getElementById('fetchedImages').style.display = 'block';
                const container = document.getElementById('fetchedImages');
                container.innerHTML = '';
                if (mainImageUrl) {
                    document.getElementById('image_url').value = mainImageUrl;
                }
                document.getElementById('images_urls').value = JSON.stringify(additionalImages);

                const allThumbs = [];
                if (mainImageUrl) allThumbs.push({ src: mainImageUrl, isMain: true });
                additionalImages.forEach(src => {
                    if (src && src !== mainImageUrl) allThumbs.push({ src, isMain: false });
                });

                allThumbs.forEach((item) => {
                    const img = document.createElement('img');
                    img.src = item.src;
                    img.style.height = '80px';
                    img.style.marginRight = '8px';
                    img.style.borderRadius = '6px';
                    img.style.cursor = 'pointer';
                    img.title = item.isMain ? 'Main image' : 'Click to use as main image';
                    if (!item.isMain) {
                        img.addEventListener('click', () => {
                            document.getElementById('image_url').value = item.src;
                            const preview = document.getElementById('mainImagePreview');
                            const imgEl = document.getElementById('mainImagePreviewImg');
                            imgEl.src = item.src;
                            preview.style.display = 'block';
                        });
                    }
                    container.appendChild(img);
                });

                if (mainImageUrl) {
                    const preview = document.getElementById('mainImagePreview');
                    const imgEl = document.getElementById('mainImagePreviewImg');
                    imgEl.src = mainImageUrl;
                    preview.style.display = 'block';
                }
            }
        } catch (e) {
            console.error(e);
            statusEl.textContent = 'Network error while fetching product.';
            alert('Network error while fetching product.');
        } finally {
            fetchBtn.disabled = false; fetchBtn.textContent = 'Fetch';
        }
    });
</script>

  <script src="../loader.js"></script>
</body>
</html>
