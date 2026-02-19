# Onrizo Shop - Codebase Overview

## 📋 Project Summary
**Onrizo Shop** is an e-commerce platform for selling smartphones, laptops, tablets, and various tech products in Kenya. It features:
- Customer shopping interface with product browsing
- Shopping cart functionality
- M-Pesa payment integration
- Admin dashboard for product management
- Multi-seller support with admin registration

---

## 🏗️ Architecture Overview

### Tech Stack
- **Frontend:** HTML5, CSS3, JavaScript (vanilla)
- **Backend:** PHP 7+
- **Database:** MySQL (hosted on InfinityFree)
- **Payment Gateway:** Safaricom M-Pesa (Daraja API - Sandbox)
- **Storage:** Local file uploads for product images

---

## 📁 Directory Structure

### **Root Files (Customer-facing)**

#### `index.html`
- Main landing page with product listing
- Navigation with category filters
- Search functionality
- Advertisement section with image slideshow
- Footer with newsletter subscription
- Floating shopping cart icon

#### `styles.css`
- Global styling for the entire site
- Responsive grid layout for products
- Navigation bar styling (blue background)
- Modal dialog styling for product details
- Cart button styling
- Footer layout

#### `script.js` (312 lines)
**Core Frontend Logic:**
- **Product Management:**
  - `fetchProducts()` - Fetches from `admin/get_products.php`
  - `displayProducts()` - Renders product cards
  - `sanitizeProduct()` - Data validation

- **Category Filtering:** `setupCategoryLinks()` - Filter by product category

- **Search:** `setupSearch()` - Real-time product search with multi-word matching

- **Cart Operations:**
  - `addToCart()` - Add items to localStorage
  - `saveCart()` - Persist cart to browser storage
  - `updateCartIcon()` - Display cart count

- **Product Details Modal:** `displayProductDetails()` - Show full product info with images

- **WhatsApp Integration:** `orderOnWhatsApp()` - Direct messaging to seller

- **UI Features:**
  - Typing animation effect in header
  - Image slideshow for advertisements
  - Newsletter subscription form

#### `cart.html`
- Shopping cart display page
- Shows all items with quantities and subtotals
- Functions: `removeOne()`, `clearCart()`, `goToCheckout()`
- Uses localStorage for cart persistence

#### `checkout.html`
- Summary page showing total amount
- Link to M-Pesa payment page

#### `mpesa_payment.html`
- Payment form with phone number input
- Submits to `stk_push.php`
- Displays total amount

#### `stk_push.php`
- **M-Pesa STK Push Implementation**
- Safaricom Daraja API integration (Sandbox)
- Steps:
  1. Generate OAuth access token
  2. Format phone number (convert to 254 format)
  3. Create STK push request
  4. Send to M-Pesa API
  5. Save pending orders to `admin/orders.json`
- Credentials stored in file (⚠️ **Security Issue**: hardcoded secrets)

#### `mpesa_callback.php`
- Webhook handler for M-Pesa payment callbacks
- Updates order status to "Paid" or "Failed"
- Logs transactions to `admin/orders.json` as JSON

#### `db_config.php`
- Database connection configuration
- InfinityFree MySQL hosting
- Credentials: User `if0_40205357`

#### `db_test.php`
- Simple connection test file

---

### **Admin Panel** (`/admin`)

#### `login.php`
- Admin authentication system
- Accepts username or email
- Supports both hashed and plain-text passwords (⚠️ **Security Issue**)
- Sets session variables on successful login

#### `register.php`
- Admin registration form
- Validates:
  - Email format
  - Phone number (Kenyan format: 254XXXXXXXXX)
  - Password strength (8+ chars, uppercase, lowercase, numbers)
- Stores location info (county, subcounty)
- Hashes password with `password_hash()`

#### `dashboard.php`
- Main admin dashboard showing:
  - User's products in a table
  - Product management controls
  - Sidebar navigation
  - Search functionality
  - Bootstrap 5 styling

#### `add_product.php`
- Product upload form
- Fields:
  - Product name, price, description
  - Category dropdown (10 categories)
  - Main image upload
  - Multiple additional images
- Uses Quill WYSIWYG editor for description
- Form submits to `upload.php`

#### `upload.php`
- Handles product image uploads
- Stores images in `/uploads` folder with unique filenames
- Inserts product record in `products` table
- Inserts extra images in `product_images` table

#### `edit_product.php`
- Loads product data from `products` table
- Form to update name, price, description
- Optional image replacement
- Submits to `update_product.php`

#### `update_product.php`
- Updates product record in database
- Can replace main image (old image deletion not shown)

#### `view_products.php`
- Lists all products for the logged-in admin
- Shows edit/delete options

#### `delete_product.php`
- Deletes product from database
- Cleanup of product images (if implemented)

#### `orders.php`
- Displays all orders from `admin/orders.json`
- Shows: phone number, amount, payment status, date
- Table format with Bootstrap styling

#### `get_products.php`
- **API Endpoint** for fetching products
- Accepts query parameters:
  - `category` - Filter by category (case-insensitive)
  - `location` - Filter by admin's location
  - `max_price` - Filter by maximum price
- Returns JSON array of products
- Includes WhatsApp number from admin
- Fetches extra images from `product_images` table
- Randomizes product order

#### `get_locations.php`
- Returns available Kenya locations (counties & subcounties)
- Used for admin registration location selector

#### `logout.php`
- Destroys session and redirects to login

#### `forgot_password.php` / `reset_password.php`
- Password recovery functionality (needs detailed review)

#### `admin_style.css`
- Admin panel styling
- Form layouts, tables, sidebars, modals

#### `dashboard.js`
- Sidebar toggle functionality
- Table interactions

---

### **Admin Data**

#### `orders.json`
- JSON file storing order records
- Format: `{ phone, amount, status, date, cart }`
- Updated by `stk_push.php` and `mpesa_callback.php`

#### `kenya_locations.json`
- Nested JSON with Kenya counties and subcounties
- Used for admin registration location selection

---

### **Static Directories**

#### `/pics`
- Logo, banners, and promotional images
- Social media icons

#### `/uploads`
- Product images uploaded by admins
- Filenames generated with `uniqid()`

---

## 🗄️ Database Schema

### Expected Tables:

**`admins`** table:
- `id` (PK)
- `name, email, phone, county, subcounty, username, password`
- `date_created` (optional)
- `location` (optional)

**`products`** table:
- `id` (PK)
- `admin_id` (FK to admins)
- `name, price, description, category, image`
- `date_added`

**`product_images`** table:
- `id` (PK)
- `product_id` (FK)
- `image_path`

---

## 🔄 User Flow

### **Customer Journey**
1. Browse products on `index.html`
2. Filter by category or search
3. Click "Details" to view full product info in modal
4. Click "Add to Cart" → stored in localStorage
5. View cart at `cart.html`
6. Proceed to checkout → `checkout.html`
7. Choose M-Pesa payment → `mpesa_payment.html`
8. Enter phone and confirm → triggers STK push via `stk_push.php`
9. Payment notification stored in `orders.json`

### **Admin Journey**
1. Register at `admin/register.php`
2. Login at `admin/login.php` (creates session)
3. Access dashboard at `admin/dashboard.php`
4. Upload products at `admin/add_product.php`
5. Manage products (edit/delete) via `edit_product.php` and `delete_product.php`
6. View orders at `admin/orders.php`

---

## 🔴 Security Issues Identified

1. **Hardcoded M-Pesa Credentials** - `stk_push.php`
   - Consumer key and secret exposed in source code
   - Should use environment variables

2. **Plain-Text Password Support** - `login.php`
   - Some passwords stored as plain text (legacy?)
   - Only uses `password_verify()` for hashed passwords

3. **Session Management**
   - No session timeout implemented
   - No CSRF token protection on forms
   - Sessions stored in default location (may be exposed)

4. **File Upload Risks** - `upload.php`
   - No file type validation (extension-based only)
   - Relies on MIME type, vulnerable to bypass

5. **Database Credentials**
   - Database user/pass exposed in `db_config.php`
   - No parameterized queries in all places

6. **Input Validation**
   - HTML output not always escaped (potential XSS)
   - Some form inputs lack validation

7. **Orders.json Storage**
   - Cart data stored as string in JSON
   - No database backup (JSON file can be lost)
   - Publicly accessible if path is guessed

---

## ✅ Positive Code Aspects

1. **Prepared Statements** - Most queries use MySQLi prepared statements
2. **Responsive Design** - Mobile-friendly layout with flexbox/grid
3. **Base64 Encoding** - Product data safely encoded in HTML attributes
4. **Search Features** - Multi-word search with proper filtering
5. **Cart Persistence** - LocalStorage for offline cart
6. **Payment Integration** - Proper OAuth flow with M-Pesa Daraja
7. **Form Validation** - Email, phone, and password validation
8. **Error Handling** - Try-catch blocks and error messages to users

---

## 🚀 Features

- ✅ Product listing with multiple categories
- ✅ Search functionality (multi-word)
- ✅ Shopping cart with quantity management
- ✅ M-Pesa payment integration (Sandbox)
- ✅ Admin product management
- ✅ Multi-seller support
- ✅ Order tracking (JSON-based)
- ✅ WhatsApp direct messaging to sellers
- ✅ Responsive design
- ✅ Newsletter subscription (form present)

---

## 📦 Dependencies

- Bootstrap 5.3.2 (admin dashboard)
- Quill.js (rich text editor for product descriptions)
- Safaricom Daraja API (M-Pesa payments)
- MySQLi (PHP database extension)
- cURL (for API calls)

---

## 🎯 Summary

This is a **multi-vendor e-commerce platform** with:
- Customer-facing storefront with product discovery
- Admin panel for product management
- Payment processing via M-Pesa
- Seller-to-customer WhatsApp integration

The code is **functional** but has **security concerns** that should be addressed before production deployment, especially around payment credentials and data validation.
