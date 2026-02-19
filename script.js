// --- CONFIG ---
const API_ENDPOINT = "admin/get_products.php";

// --- GLOBALS ---
let allProducts = [];
let cart = JSON.parse(localStorage.getItem("cart")) || [];

// --- DOM READY ---
document.addEventListener("DOMContentLoaded", () => {
  setupHamburgerMenu();
  setupCategoryLinks();
  setupSearch();
  setupLocationFilter();
  setupModalClose();
  setupNewsletterForm();
  setupCartIcon();
  startTyping();
  startSlideshow();
  captureReferral();
  fetchProducts();
});

// Capture referral token from URL and persist to localStorage for later crediting
function captureReferral(){
  try{
    const params = new URLSearchParams(window.location.search);
    const ref = params.get('ref');
    const product = params.get('product');
    
    console.log('captureReferral - ref:', ref, 'product:', product);
    
    if(ref){
      const payload = { token: ref, product: product || null, ts: Date.now() };
      // store referral
      localStorage.setItem('affiliate_ref', JSON.stringify(payload));
      console.log('Stored affiliate ref:', payload);

      // Redirect to cart immediately
      console.log('Redirecting to cart...');
      const cartPath = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) + '/cart.html';
      window.location.href = cartPath;
      
      // Fetch and add product to cart in background (after redirect)
      if (product) {
        // This will execute after redirect, but browser will load new page first
        fetch('get_product.php?product_id=' + encodeURIComponent(product))
          .then(r => r.json())
          .then(j => {
            console.log('Background product fetch response:', j);
            if (!j || !j.success || !j.product) return;
            const p = j.product;
            // build product object similar to the cart's expected shape
            const cartItem = {
              id: p.id,
              name: p.name,
              price: parseFloat(p.price) || 0,
              description: p.description || '',
              category: p.category || '',
              image: p.image || '',
              extra_images: Array.isArray(p.extra_images) ? p.extra_images : [],
              whatsapp_number: p.whatsapp_number || '',
              affiliate_percent: (typeof p.affiliate_percent !== 'undefined') ? p.affiliate_percent : (p.affiliatePercent || 0),
              quantity: 1
            };

            // load cart, append (prevent duplicates by product id)
            try{
              const cart = JSON.parse(localStorage.getItem('cart') || '[]');
              const found = cart.find(it => String(it.id) === String(cartItem.id));
              if (found) {
                found.quantity = (found.quantity || 1) + 1;
              } else {
                cart.push(cartItem);
              }
              localStorage.setItem('cart', JSON.stringify(cart));
              localStorage.setItem('affiliate_ref_processed', JSON.stringify({ token: ref, product: product, ts: Date.now() }));
              console.log('Product added to cart in background');
            } catch(e){ console.error('background cart add failed', e); }
          }).catch(err => {
            console.error('background product fetch failed', err);
          });
      }
    } else {
      console.log('No referral code in URL');
    }
  }catch(e){ console.error('ref capture failed', e); }
}

// --- HAMBURGER MENU ---
function setupHamburgerMenu() {
  const hamburger = document.getElementById("hamburger");
  const nav = document.getElementById("nav");
  
  if (!hamburger || !nav) return;
  
  hamburger.addEventListener("click", () => {
    hamburger.classList.toggle("active");
    nav.classList.toggle("active");
  });
  
  // Close menu when a link is clicked
  const navLinks = nav.querySelectorAll("a");
  navLinks.forEach(link => {
    link.addEventListener("click", () => {
      hamburger.classList.remove("active");
      nav.classList.remove("active");
    });
  });
}

// --- SLIDESHOW ---
function startSlideshow() {
  const slides = document.querySelectorAll("#advertisementSection img");
  if (!slides.length) return;

  let currentSlide = 0;
  slides[currentSlide].classList.add("active");

  setInterval(() => {
    slides[currentSlide].classList.remove("active");
    currentSlide = (currentSlide + 1) % slides.length;
    slides[currentSlide].classList.add("active");
  }, 5000);
}

// --- FETCH PRODUCTS ---
async function fetchProducts(queryParams = {}) {
  const section = document.getElementById("products");
  try {
    const url = new URL(API_ENDPOINT, window.location.href);
    Object.keys(queryParams).forEach(k => {
      if (queryParams[k]) url.searchParams.set(k, queryParams[k]);
    });

    showProductSkeletons(section, 8);

    const res = await fetch(url.toString(), { cache: "no-store" });
    
    if (!res.ok) {
      throw new Error(`HTTP error! status: ${res.status}`);
    }
    
    const data = await res.json();
    
    allProducts = Array.isArray(data) ? data : [];

    if (!allProducts.length) {
      section.innerHTML = "<p>No products found in your location for now.</p>";
      return;
    }

    displayProducts(allProducts, true);
  } catch (err) {
    console.error("Error fetching products:", err);
    section.innerHTML = "<p style='color:red;'>❌ Failed to load products. Check browser console for details.</p>";
  }
}

function showProductSkeletons(section, count) {
  section.innerHTML = "";
  for (let i = 0; i < count; i++) {
    const div = document.createElement('div');
    div.className = 'product skeleton';
    div.innerHTML = `
      <div class="skeleton-block skeleton-img"></div>
      <div class="skeleton-block skeleton-line"></div>
      <div class="skeleton-block skeleton-line short"></div>
    `;
    section.appendChild(div);
  }
}

function appendProducts(products, lazyLoad = false) {
  const section = document.getElementById('products');
  products.forEach(product => {
    const safeProduct = sanitizeProduct(product);
    const encoded = btoa(unescape(encodeURIComponent(JSON.stringify(safeProduct))));

    const div = document.createElement('div');
    div.className = 'product';
    div.style.cursor = 'pointer';

    const allImages = [safeProduct.image];
    if (Array.isArray(safeProduct.extra_images) && safeProduct.extra_images.length > 0) {
      allImages.push(...safeProduct.extra_images);
    }

    const imgElement = document.createElement('img');
    imgElement.src = safeProduct.image;
    imgElement.alt = safeProduct.name;
    if (lazyLoad) {
      imgElement.loading = 'lazy';
      imgElement.decoding = 'async';
    }
    imgElement.className = 'product-img';
    imgElement.dataset.allImages = JSON.stringify(allImages);
    imgElement.dataset.currentIndex = '0';

    const h2 = document.createElement('h2');
    h2.textContent = safeProduct.name;

    const p = document.createElement('p');
    p.textContent = `KES ${Number(safeProduct.price).toLocaleString()}`;

    div.appendChild(imgElement);
    div.appendChild(h2);
    div.appendChild(p);

    div.addEventListener('mouseenter', () => {
      if (Array.isArray(safeProduct.extra_images) && safeProduct.extra_images.length > 0) {
        const rand = safeProduct.extra_images[Math.floor(Math.random() * safeProduct.extra_images.length)];
        if (rand) imgElement.src = rand;
      }
    });

    div.addEventListener('mouseleave', () => {
      imgElement.src = safeProduct.image;
    });

    div.addEventListener('click', () => {
      const product = JSON.parse(decodeURIComponent(escape(atob(encoded))));
      displayProductDetails(product);
    });
    section.appendChild(div);
  });

}

function displayProducts(products, lazyLoad = false) {
  const section = document.getElementById("products");
  section.innerHTML = "";
  appendProducts(products, lazyLoad);
}

// --- SANITIZE PRODUCT ---
function sanitizeProduct(product) {
  return {
    id: product.id || product.product_id || product.name || Math.random().toString(36).substring(2, 9),
    name: product.name || "Unnamed Product",
    price: product.price || 0,
    description: product.description || "No description available.",
    category: product.category || "General",
    image: product.image || "assets/default.jpg",
    extra_images: Array.isArray(product.extra_images) ? product.extra_images : [],
    whatsapp_number: product.whatsapp_number || product.phone || "",
    phone: product.phone || "",
    affiliate_percent: (typeof product.affiliate_percent !== 'undefined') ? product.affiliate_percent : (typeof product.affiliatePercent !== 'undefined' ? product.affiliatePercent : 0),
  };
}

// Remove HTML tags and return plain text
function stripHTML(html){
  const d = document.createElement('div');
  d.innerHTML = html || '';
  return d.textContent || d.innerText || '';
}

// truncate text to length with no mid-word cuts
function truncate(str, max){
  if(!str) return '';
  if(str.length <= max) return str;
  let s = str.substr(0, max);
  const last = s.lastIndexOf(' ');
  if(last > 40) s = s.substr(0, last);
  return s + '...';
}

// --- CATEGORY FILTER ---
function setupCategoryLinks() {
  const links = document.querySelectorAll("nav a[data-category]");
  links.forEach(link => {
    link.addEventListener("click", e => {
      e.preventDefault();
      const cat = link.getAttribute("data-category");
      links.forEach(l => l.classList.remove("active"));
      link.classList.add("active");
      fetchProducts(cat === "All" ? {} : { category: cat });
    });
  });
}

// --- SEARCH ---
function setupSearch() {
  const searchInput = document.getElementById("searchInput");
  if (!searchInput) return;

  searchInput.addEventListener("input", e => {
    const term = e.target.value.trim().toLowerCase();
    if (!term) {
      displayProducts(allProducts);
      return;
    }

    const words = term.split(/\s+/);
    const filtered = allProducts.filter(p => {
      const text = `${p.name || ""} ${p.category || ""} ${p.description || ""}`.toLowerCase();
      return words.every(w => text.includes(w));
    });

    displayProducts(filtered);
  });
}

async function setupLocationFilter() {
  const countySelect = document.getElementById('countySelect');
  const subcountySelect = document.getElementById('subcountySelect');
  const button = document.getElementById('locationBtn');
  if (!countySelect || !subcountySelect) return;

  let locations = {};

  try {
    const res = await fetch('admin/get_locations.php');
    locations = await res.json();

    if (locations && typeof locations === 'object') {
      Object.keys(locations).forEach(county => {
        if (!county) return;
        const opt = document.createElement('option');
        opt.value = county;
        opt.textContent = county;
        countySelect.appendChild(opt);
      });
    }
  } catch (e) {
    console.error('Failed to load locations', e);
  }

  const filtersWrap = document.getElementById('locationFilters');

  if (button) {
    button.addEventListener('click', () => {
      if (filtersWrap) filtersWrap.classList.toggle('hidden');
      countySelect.focus();
      countySelect.click();
    });
  }

  countySelect.addEventListener('change', () => {
    const county = countySelect.value;
    subcountySelect.innerHTML = '<option value="">All sub-counties</option>';
    const subs = locations[county] || [];
    subs.forEach(sub => {
      if (!sub) return;
      const opt = document.createElement('option');
      opt.value = sub;
      opt.textContent = sub;
      subcountySelect.appendChild(opt);
    });
    applyLocationFilter();
  });

  subcountySelect.addEventListener('change', applyLocationFilter);

  function applyLocationFilter() {
    const county = countySelect.value;
    const subcounty = subcountySelect.value;
    const activeCat = document.querySelector('nav a.active');
    const category = activeCat ? activeCat.getAttribute('data-category') : 'All';
    const query = {};
    if (category && category !== 'All') query.category = category;
    if (county) query.county = county;
    if (subcounty) query.subcounty = subcounty;
    fetchProducts(query);
  }
}

// --- MODAL HANDLING ---
document.addEventListener("click", e => {
  const target = e.target;

  if (target.classList.contains("details-btn")) {
    const product = JSON.parse(decodeURIComponent(escape(atob(target.dataset.product))));
    displayProductDetails(product);
  }

  if (target.classList.contains("add-to-cart")) {
    const product = JSON.parse(decodeURIComponent(escape(atob(target.dataset.product))));
    addToCart(product);
  }

  if (target.classList.contains("whatsapp-btn")) {
    const product = JSON.parse(decodeURIComponent(escape(atob(target.dataset.product))));
    orderOnWhatsApp(product);
  }

  if (target.classList.contains('modal-desc-toggle')) {
    e.preventDefault();
    const desc = document.querySelector('.modal-desc');
    if (!desc) return;
    const expanded = target.dataset.expanded === 'true';
    if (expanded) {
      desc.classList.add('collapsed');
      target.textContent = 'View more';
      target.dataset.expanded = 'false';
    } else {
      desc.classList.remove('collapsed');
      target.textContent = 'View less';
      target.dataset.expanded = 'true';
    }
  }
});

function displayProductDetails(product) {
  const modalContent = document.getElementById("modalContent");
  const images = [product.image, ...(Array.isArray(product.extra_images) ? product.extra_images : [])];
  const cleanedPhone = (product.whatsapp_number || product.phone || "").replace(/[^0-9]/g, "");

  // Encode again safely for buttons
  const encodedProduct = btoa(unescape(encodeURIComponent(JSON.stringify(product))));

  const descHtml = product.description || '';
  const descText = stripHTML(descHtml);
  const needsMore = descText.length > 240;

  modalContent.innerHTML = `
    <h2>${product.name}</h2>
    <div class="modal-desc${needsMore ? ' collapsed' : ''}">
      ${descHtml}
    </div>
    ${needsMore ? '<a href="#" class="modal-desc-toggle" data-expanded="false">View more</a>' : ''}
    <div>${images.map(img => `<img src="${img}" style="width:100%;border-radius:10px;">`).join("")}</div>
    <p>KES ${Number(product.price).toLocaleString()}</p>
    <div class="btns"> 
      <button class="whatsapp-btn" data-product="${encodedProduct}">
        Order on WhatsApp
      </button> 
      <button class="add-to-cart" data-product="${encodedProduct}">
        Add to Cart
      </button>
      <button id="sellBtn" class="sell-btn" data-product="${encodedProduct}" style="margin-left:8px;background:#667eea;color:white;padding:8px 15px;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
        💰 Sell this product
      </button>
    </div>
    <div id="sellPanel" style="display:none;margin-top:15px;border:2px solid #667eea;padding:15px;border-radius:8px;background:linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
      <div style="font-size:13px;margin-bottom:10px;">Affiliate percent for this product: <strong id="modalAffPercent">${Number(product.affiliate_percent || 0).toFixed(2)}%</strong></div>
      
      <!-- Email Entry Section -->
      <div id="emailEntrySection" style="margin-bottom:12px;">
        <div style="display:flex;gap:8px;align-items:center;">
          <input id="affiliateEmail" type="email" placeholder="Enter your affiliate email" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:14px;" />
          <button id="checkEmailBtn" style="padding:8px 12px;border-radius:6px;background:#667eea;color:#fff;border:none;cursor:pointer;font-weight:600;">Check</button>
        </div>
        <div id="emailError" style="color:#dc3545;font-size:12px;margin-top:5px;display:none;"></div>
      </div>

      <!-- Link Generation Section (shown after email verification) -->
      <div id="linkGenSection" style="display:none;border-top:1px solid #ddd;padding-top:10px;">
        <div style="font-size:13px;margin-bottom:8px;"><strong id="affiliateNameDisplay"></strong> is ready to generate link</div>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
          <button id="generateLinkBtn" style="padding:8px 12px;border-radius:6px;background:#667eea;color:#fff;border:none;cursor:pointer;font-weight:600;">Generate Link</button>
          <span id="genStatus" style="font-size:13px;color:#555;"></span>
        </div>
      </div>

      <!-- Generated Link Section -->
      <div id="genResult" style="margin-top:10px;display:none;border-top:1px solid #ddd;padding-top:10px;">
        <div style="word-break:break-all;margin-bottom:8px;">
          <strong>Your Affiliate Link:</strong><br/>
          <input id="genLink" type="text" readonly style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-family:monospace;font-size:12px;" />
        </div>
        <div style="display:flex;gap:8px;">
          <button id="copyLinkBtn" style="padding:6px 12px;border-radius:6px;background:#007bff;color:#fff;border:none;cursor:pointer;font-weight:600;">📋 Copy Link</button>
          <button id="copyShareBtn" style="padding:6px 12px;border-radius:6px;background:#17a2b8;color:#fff;border:none;cursor:pointer;font-weight:600;">📱 Share</button>
          <a id="buyNowLink" href="#" target="_blank" style="padding:6px 12px;border-radius:6px;background:#28a745;color:#fff;text-decoration:none;font-weight:600;display:inline-block;">🛒 Test Link</a>
        </div>
      </div>
      <div id="errorMsg" style="margin-top:8px;color:#dc3545;font-size:12px;display:none;"></div>
  
  `;

  document.getElementById("productModal").style.display = "block";

  // create a persistent floating action bar for quick access to buy buttons
  try { createFloatingBuyBar(encodedProduct); } catch(e){ console.error('createFloatingBuyBar failed', e); }

  // hide floating bar when modal bottom buttons are in view
  setTimeout(() => {
    const modalEl = document.getElementById('productModal');
    const btns = modalContent.querySelector('.btns');
    if (!modalEl || !btns || !('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        const bar = document.getElementById('floatingBuyBar');
        if (!bar) return;
        bar.style.opacity = entry.isIntersecting ? '0' : '1';
        bar.style.pointerEvents = entry.isIntersecting ? 'none' : 'auto';
      });
    }, { root: modalEl, threshold: 0.6 });
    observer.observe(btns);
  }, 50);

  // Sell panel toggle and handlers
  const sellBtn = document.getElementById('sellBtn');
  const sellPanel = document.getElementById('sellPanel');
  const checkEmailBtn = document.getElementById('checkEmailBtn');
  const affiliateEmail = document.getElementById('affiliateEmail');
  const emailError = document.getElementById('emailError');
  const emailEntrySection = document.getElementById('emailEntrySection');
  const linkGenSection = document.getElementById('linkGenSection');
  const affiliateNameDisplay = document.getElementById('affiliateNameDisplay');
  const generateBtn = document.getElementById('generateLinkBtn');
  const genStatus = document.getElementById('genStatus');
  const genResult = document.getElementById('genResult');
  const genLink = document.getElementById('genLink');
  const copyBtn = document.getElementById('copyLinkBtn');
  const buyNowLink = document.getElementById('buyNowLink');

  let currentAffiliateEmail = '';
  let currentAffiliateInfo = {};

  if (sellBtn) {
    sellBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      sellPanel.style.display = sellPanel.style.display === 'none' ? 'block' : 'none';
      if (sellPanel.style.display === 'block') {
        affiliateEmail.focus();
      }
    });
  }

  if (checkEmailBtn) {
    checkEmailBtn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const email = affiliateEmail.value.trim();
      
      if (!email) {
        emailError.textContent = 'Please enter an email';
        emailError.style.display = 'block';
        return;
      }

      checkEmailBtn.disabled = true;
      checkEmailBtn.textContent = '⏳ Checking...';
      
      try {
        const resp = await fetch('check_affiliate_email.php?email=' + encodeURIComponent(email));
        
        if (!resp.ok) {
          throw new Error(`HTTP error! status: ${resp.status}`);
        }
        
        const data = await resp.json();
        console.log('Email check response:', data);
        
        if (data.success && data.found) {
          // Email is registered as affiliate
          emailError.style.display = 'none';
          emailEntrySection.style.display = 'none';
          linkGenSection.style.display = 'block';
          affiliateNameDisplay.textContent = data.affiliate_name;
          currentAffiliateEmail = email;
          currentAffiliateInfo = data;
        } else if (!data.found) {
          // Email not registered - redirect to registration
          emailError.textContent = 'Not registered as affiliate. Redirecting to registration...';
          emailError.style.display = 'block';
          setTimeout(() => {
            window.location.href = data.redirect || ('affiliate_login.php?email=' + encodeURIComponent(email));
          }, 2000);
        } else {
          // Other error
          emailError.textContent = data.message || 'Error checking email';
          emailError.style.display = 'block';
        }
      } catch (err) {
        console.error('Error checking email:', err);
        emailError.textContent = 'Error checking email: ' + err.message;
        emailError.style.display = 'block';
      } finally {
        checkEmailBtn.disabled = false;
        checkEmailBtn.textContent = 'Check';
      }
    });

    // Allow Enter key to submit email check
    affiliateEmail.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        checkEmailBtn.click();
      }
    });
  }

  if (generateBtn) {
    generateBtn.addEventListener('click', async (e) => {
      e.stopPropagation();
      
      if (!currentAffiliateEmail) {
        alert('Please check email first');
        return;
      }

      genStatus.textContent = '⏳ Generating...';
      genResult.style.display = 'none';
      const errorMsg = document.getElementById('errorMsg');
      errorMsg.style.display = 'none';
      
      try {
        const resp = await fetch('generate_affiliate_link.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'email=' + encodeURIComponent(currentAffiliateEmail) + 
                '&product_id=' + product.id +
                '&affiliate_name=' + encodeURIComponent(currentAffiliateInfo.affiliate_name || '') +
                '&affiliate_phone=' + encodeURIComponent(currentAffiliateInfo.phone || '')
        });
        const j = await resp.json();
        
        if (!j.success) {
          errorMsg.textContent = j.message || 'Could not generate link';
          errorMsg.style.display = 'block';
          genStatus.textContent = '❌ Failed';
          return;
        }
        
        genStatus.textContent = '✓ Link generated successfully!';
        genResult.style.display = 'block';
        
        // Set the link value in the input field
        genLink.value = j.affiliate_link;
        
        // Set the test link href
        buyNowLink.href = j.affiliate_link;
        
        // Copy button handler
        copyBtn.onclick = () => {
          navigator.clipboard.writeText(j.affiliate_link)
            .then(() => { 
              copyBtn.textContent = '✓ Copied!'; 
              setTimeout(() => { copyBtn.textContent = '📋 Copy Link'; }, 2000);
            })
            .catch(() => { alert('Copy failed'); });
        };
        
        // Share button handler
        const shareBtn = document.getElementById('copyShareBtn');
        if (shareBtn) {
          shareBtn.onclick = () => {
            const text = `Check out this product I'm selling: ${product.name}\n${j.affiliate_link}`;
            if (navigator.share) {
              navigator.share({
                title: product.name,
                text: text,
                url: j.affiliate_link
              }).catch(err => console.error('Share failed:', err));
            } else {
              navigator.clipboard.writeText(text)
                .then(() => { 
                  shareBtn.textContent = '✓ Text copied!'; 
                  setTimeout(() => { shareBtn.textContent = '📱 Share'; }, 2000);
                });
            }
          };
        }
      } catch(err) { 
        console.error(err); 
        errorMsg.textContent = 'Network error. Please try again.';
        errorMsg.style.display = 'block';
        genStatus.textContent = '❌ Error'; 
      }
    });
  }
}

function setupModalClose() {
  const modal = document.getElementById("productModal");
  if (!modal) return;

  window.addEventListener("click", e => {
    if (e.target === modal) { modal.style.display = "none"; removeFloatingBuyBar(); }
  });

  const closeBtn = document.querySelector(".close");
  if (closeBtn) {
    closeBtn.addEventListener("click", () => {
      modal.style.display = "none";
      // remove floating bar when modal closed
      removeFloatingBuyBar();
    });
  }
}

// Floating buy bar helpers
function createFloatingBuyBar(encodedProduct){
  // remove existing if any
  removeFloatingBuyBar();
  const prod = JSON.parse(decodeURIComponent(escape(atob(encodedProduct))));

  const bar = document.createElement('div');
  bar.id = 'floatingBuyBar';
  bar.style.position = 'fixed';
  bar.style.left = '50%';
  bar.style.transform = 'translateX(-50%)';
  bar.style.bottom = '18px';
  bar.style.zIndex = 21000;
  bar.style.display = 'flex';
  bar.style.gap = '10px';
  bar.style.padding = '8px';
  bar.style.background = 'rgba(255,255,255,0.55)';
  bar.style.backdropFilter = 'blur(6px)';
  bar.style.borderRadius = '10px';
  bar.style.boxShadow = '0 8px 30px rgba(0,0,0,0.12)';
  bar.style.alignItems = 'center';

  const title = document.createElement('div');
  title.textContent = prod.name;
  title.style.fontSize = '13px';
  title.style.fontWeight = '700';
  title.style.maxWidth = '240px';
  title.style.overflow = 'hidden';
  title.style.textOverflow = 'ellipsis';
  title.style.whiteSpace = 'nowrap';
  bar.appendChild(title);

  const spacer = document.createElement('div'); spacer.style.width = '8px'; bar.appendChild(spacer);

  const whatsappBtn = document.createElement('button');
  whatsappBtn.className = 'whatsapp-btn';
  whatsappBtn.style.padding = '8px 10px';
  whatsappBtn.style.background = '#25D366';
  whatsappBtn.style.color = '#fff';
  whatsappBtn.style.border = 'none';
  whatsappBtn.style.borderRadius = '8px';
  whatsappBtn.style.cursor = 'pointer';
  whatsappBtn.textContent = 'Order on WhatsApp';
  whatsappBtn.onclick = (e) => { e.stopPropagation(); orderOnWhatsApp(prod); };
  bar.appendChild(whatsappBtn);

  const addBtn = document.createElement('button');
  addBtn.className = 'add-to-cart';
  addBtn.style.padding = '8px 10px';
  addBtn.style.background = '#222';
  addBtn.style.color = '#fff';
  addBtn.style.border = 'none';
  addBtn.style.borderRadius = '8px';
  addBtn.style.cursor = 'pointer';
  addBtn.textContent = 'Add to Cart';
  addBtn.onclick = (e) => { e.stopPropagation(); addToCart(prod); };
  bar.appendChild(addBtn);

  const buyNow = document.createElement('button');
  buyNow.style.padding = '8px 10px';
  buyNow.style.background = '#ff9800';
  buyNow.style.color = '#000';
  buyNow.style.border = 'none';
  buyNow.style.borderRadius = '8px';
  buyNow.style.cursor = 'pointer';
  buyNow.textContent = 'Buy Now';
  buyNow.onclick = (e) => { e.stopPropagation(); addToCart(prod); window.location.href = 'cart.html'; };
  bar.appendChild(buyNow);

  document.body.appendChild(bar);
}

function removeFloatingBuyBar(){
  const ex = document.getElementById('floatingBuyBar');
  if (ex) ex.remove();
}

// --- CART LOGIC ---
function addToCart(product) {
  const existing = cart.find(item => item.id === product.id);
  if (existing) {
    existing.quantity += 1;
  } else {
    cart.push({ ...product, quantity: 1 });
  }
  saveCart();
  updateCartIcon();
  // show confirmation toast
  showToast(`${product.name} added to cart`, { actionText: 'View Cart', action: () => { window.location.href = 'cart.html'; } });
}

function saveCart() {
  localStorage.setItem("cart", JSON.stringify(cart));
}

function setupCartIcon() {
  updateCartIcon();
}

// Simple toast notification system
function showToast(message, options = {}) {
  try {
    const containerId = 'toastContainer';
    let container = document.getElementById(containerId);
    if (!container) {
      container = document.createElement('div');
      container.id = containerId;
      container.style.position = 'fixed';
      container.style.right = '18px';
      container.style.bottom = '18px';
      container.style.zIndex = 2000;
      container.style.display = 'flex';
      container.style.flexDirection = 'column';
      container.style.gap = '10px';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'onrizo-toast';
    toast.style.minWidth = '220px';
    toast.style.maxWidth = '320px';
    toast.style.background = '#222';
    toast.style.color = '#fff';
    toast.style.padding = '12px 14px';
    toast.style.borderRadius = '10px';
    toast.style.boxShadow = '0 8px 24px rgba(0,0,0,0.25)';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.justifyContent = 'space-between';
    toast.style.gap = '10px';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(8px)';
    toast.style.transition = 'opacity 240ms ease, transform 240ms ease';

    const txt = document.createElement('div');
    txt.style.flex = '1';
    txt.style.fontSize = '14px';
    txt.textContent = message;
    toast.appendChild(txt);

    if (options.actionText && typeof options.action === 'function') {
      const btn = document.createElement('button');
      btn.textContent = options.actionText;
      btn.style.marginLeft = '8px';
      btn.style.background = '#fff';
      btn.style.color = '#222';
      btn.style.border = 'none';
      btn.style.padding = '6px 8px';
      btn.style.borderRadius = '6px';
      btn.style.cursor = 'pointer';
      btn.addEventListener('click', (e) => { e.stopPropagation(); options.action(); });
      toast.appendChild(btn);
    }

    container.appendChild(toast);
    // force reflow then show
    requestAnimationFrame(() => {
      toast.style.opacity = '1';
      toast.style.transform = 'translateY(0)';
    });

    const ttl = options.ttl || 3500;
    const timeout = setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(8px)';
      setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 260);
    }, ttl);

    // allow click to dismiss
    toast.addEventListener('click', () => {
      clearTimeout(timeout);
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(8px)';
      setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 260);
    });
  } catch (e) { console.error('showToast error', e); }
}

function updateCartIcon() {
  const count = cart.reduce((sum, i) => sum + i.quantity, 0);
  const icon = document.getElementById("cartCount");
  const floatingCart = document.getElementById("floatingCart");

  if (icon) icon.textContent = count;
  if (floatingCart) floatingCart.style.display = count ? "block" : "none";
}

document.addEventListener("DOMContentLoaded", setupCartIcon);

// --- ORDER ON WHATSAPP ---
function orderOnWhatsApp(product) {
  const phone = (product.whatsapp_number || product.phone || "").replace(/[^0-9]/g, "");
  if (!phone) {
    alert("Sorry, the supplier is out of stock or contact not provided.");
    return;
  }

  const message = `
🛒 *Product:* ${product.name}
💰 *Price:* KES ${Number(product.price).toLocaleString()}
🆔 *Product ID:* ${product.id}
🕒 ${new Date().toLocaleString()}

New product inquiry from Onrizo Shop:
${product.image}
  `;

  const whatsappUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
  window.open(whatsappUrl, "_blank");
}

// --- TYPING EFFECT ---
const texts = [
  "lcome to Onrizo Shop",
  " the best",
  " deliver Immediately",
  " sell Quality products",
  " value your Money & Time",
  "lcome All",
];
let i = 0, j = 0, isDeleting = false;
const speed = 150, deleteSpeed = 40, delayBeforeDelete = 2000, delayBetweenWords = 100;

function type() {
  const el = document.getElementById("text");
  if (!el) return;
  const currentText = texts[i];
  if (!isDeleting) {
    el.textContent = currentText.substring(0, j + 1);
    j++;
    if (j === currentText.length) {
      isDeleting = true;
      setTimeout(type, delayBeforeDelete);
      return;
    }
  } else {
    el.textContent = currentText.substring(0, j - 1);
    j--;
    if (j === 0) {
      isDeleting = false;
      i = (i + 1) % texts.length;
      setTimeout(type, delayBetweenWords);
      return;
    }
  }
  setTimeout(type, isDeleting ? deleteSpeed : speed);
}
function startTyping() { type(); }

// --- NEWSLETTER ---
function setupNewsletterForm() {
  const form = document.getElementById("newsletterForm");
  if (!form) return;
  form.addEventListener("submit", async e => {
    e.preventDefault();
    const email = document.getElementById("newsletterEmail").value.trim();
    if (!email) return alert("Enter email");
    try {
      const res = await fetch("newsletter_submit.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email })
      });
      const data = await res.json();
      alert(data.message || "Subscribed!");
      form.reset();
    } catch {
      alert("Failed to subscribe");
    }
  });
}
