# 🚀 Onrizo Shop - XAMPP Local Setup & Deployment Guide

## ✅ What's Been Fixed

1. **Database Configuration** - Updated to use local XAMPP MySQL
   - Host: `localhost`
   - Username: `root`
   - Database: `onrizo_db`
   - Password: (empty/no password)

2. **M-Pesa Callback URL** - Now uses dynamic host detection for local development

3. **File Include Paths** - Fixed `admin/add_product.php` include path

---

## 🎯 Quick Start (5 Minutes)

### Step 1: Ensure XAMPP is Running

Open **XAMPP Control Panel** and start:
- ✅ **Apache** - for web server
- ✅ **MySQL** - for database

```
c:\xampp\xampp-control.exe
```

### Step 2: Access Your Site

Open your browser and go to:
```
http://localhost/onrizo/
```

You should see the **Onrizo Shop** homepage with product listings!

### Step 3: Test Database Connection

Visit the test page:
```
http://localhost/onrizo/test_db.php
```

This will show you:
- ✅ Database connection status
- 📦 Current database name
- 📋 All tables
- 📊 Record counts

---

## 🛠️ Database Setup

### Database Details

**Database Name:** `onrizo_db`

**Tables:**
- `admins` - Admin user accounts (1 admin already exists)
- `products` - Product listings (currently empty - add products via admin panel)
- `orders` - Customer orders
- `payments` - Payment records

### Add Sample Data (Optional)

If you want to populate some sample products:

```sql
-- Sample Products
INSERT INTO products (admin_id, name, price, description, image, category, date_added) 
VALUES 
(1, 'iPhone 14 Pro', 129999, 'Latest Apple flagship phone', 'uploads/iphone.jpg', 'Smartphones', NOW()),
(1, 'MacBook Pro M2', 199999, 'Powerful laptop for professionals', 'uploads/macbook.jpg', 'Laptops', NOW()),
(1, 'Samsung Galaxy S23', 99999, 'Premium Android phone', 'uploads/samsung.jpg', 'Smartphones', NOW());
```

To run this:
1. Go to `http://localhost/phpmyadmin`
2. Select `onrizo_db` database
3. Click "SQL" tab
4. Paste the SQL above and click "Go"

---

## 👤 Admin Access

### Login Details

**Admin Email/Username:** Check with your admin setup
**Admin Password:** Set during registration

### Access Admin Panel

1. Go to: `http://localhost/onrizo/admin/login.php`
2. Login with your credentials
3. Upload products, manage orders, etc.

### Register New Admin

If needed, visit: `http://localhost/onrizo/admin/register.php`

---

## 🛒 Customer Features (Working)

✅ Browse products by category
✅ Search products
✅ View product details with modal
✅ Add items to cart (uses localStorage)
✅ View cart with quantities
✅ M-Pesa payment integration
✅ Order via WhatsApp

---

## 💳 M-Pesa Payment (Sandbox Mode)

**Status:** Currently in Sandbox mode (testing only)

### Test Payment
1. Add items to cart
2. Proceed to checkout
3. Pay with M-Pesa
4. Enter a Kenyan phone number: `254712345678`
5. Amount will be prompted on M-Pesa

### Important Notes
- ⚠️ Using **Sandbox credentials** (Safaricom test environment)
- For production, update credentials in `stk_push.php`
- Callbacks are logged locally to `admin/orders.json`

---

## 📁 Project Structure

```
c:\xampp\htdocs\onrizo\
├── index.html                 # Homepage with products
├── cart.html                  # Shopping cart
├── checkout.html              # Checkout summary
├── mpesa_payment.html         # Payment form
├── stk_push.php              # M-Pesa payment processor
├── mpesa_callback.php        # Payment callback handler
├── script.js                 # Frontend logic (312 lines)
├── styles.css                # Styling
├── db_config.php             # ✅ UPDATED - Local MySQL config
├── test_db.php               # Database test page (NEW)
│
├── admin/
│   ├── login.php             # Admin login
│   ├── register.php          # Admin registration
│   ├── dashboard.php         # Admin dashboard
│   ├── add_product.php       # Add products
│   ├── edit_product.php      # Edit products
│   ├── delete_product.php    # Delete products
│   ├── view_products.php     # List products
│   ├── orders.php            # View orders
│   ├── get_products.php      # API endpoint
│   └── admin_style.css
│
├── uploads/                  # Product images
├── pics/                     # Logo and banners
└── admin/orders.json         # Order storage
```

---

## 🔧 Configuration Files

### `db_config.php` (UPDATED ✅)
```php
$host = "localhost";      // XAMPP local server
$user = "root";           // Default XAMPP user
$pass = "";               // No password by default
$dbname = "onrizo_db";    // Your database
```

### `stk_push.php` (UPDATED ✅)
- Callback URL now dynamic for local development
- Uses Safaricom Sandbox credentials (for testing)

---

## 🌐 Making Site Live (Production)

When ready to deploy to production (live server):

### Step 1: Update Database
```php
// db_config.php
$host = "your_host.com";
$user = "your_username";
$pass = "your_password";
$dbname = "onrizo_db";
```

### Step 2: Update M-Pesa Credentials
```php
// stk_push.php
$consumerKey = 'YOUR_PRODUCTION_KEY';
$consumerSecret = 'YOUR_PRODUCTION_SECRET';
$accessTokenUrl = 'https://api.safaricom.co.ke/...'; // Production URL
$stkPushUrl = 'https://api.safaricom.co.ke/...';     // Production URL
```

### Step 3: Update Callback URL
Already handled dynamically - will use your live domain

### Step 4: Enable SSL/HTTPS
- Get SSL certificate for your domain
- Update all URLs to use `https://`

### Step 5: Create `.env` file (Optional but Recommended)
```
DB_HOST=your_host.com
DB_USER=your_user
DB_PASS=your_password
MPESA_KEY=your_key
MPESA_SECRET=your_secret
```

---

## 🔐 Security Checklist

**Before Going Live:**

- [ ] Move credentials to `.env` file or environment variables
- [ ] Enable HTTPS/SSL
- [ ] Set proper file permissions (uploads folder: 755)
- [ ] Disable admin registration (or add verification)
- [ ] Implement CSRF token protection
- [ ] Add rate limiting for payments
- [ ] Backup database regularly
- [ ] Enable database user password (remove empty password)
- [ ] Hide sensitive error messages in production
- [ ] Implement order verification system

---

## 🧹 Useful Commands

### Start MySQL from Command Line
```bash
cd c:\xampp\mysql\bin
.\mysql -u root -e "SHOW DATABASES;"
```

### Create Database Backup
```bash
cd c:\xampp\mysql\bin
.\mysqldump -u root onrizo_db > backup.sql
```

### Restore from Backup
```bash
cd c:\xampp\mysql\bin
.\mysql -u root onrizo_db < backup.sql
```

---

## 🐛 Troubleshooting

### "Database connection failed"
- ✅ Check if MySQL is running in XAMPP
- ✅ Verify `onrizo_db` exists: `http://localhost/phpmyadmin`
- ✅ Check `db_config.php` settings

### "Product images not showing"
- ✅ Ensure `/uploads` folder has write permissions
- ✅ Check file paths in database
- ✅ Verify images are in correct format (jpg, png, gif)

### "Admin login not working"
- ✅ Check `admins` table has at least one user
- ✅ Verify password is hashed correctly
- ✅ Clear browser cookies/cache

### "M-Pesa payment fails"
- ✅ Check internet connection (requires API calls)
- ✅ Verify Safaricom credentials are correct
- ✅ Check callback URL format
- ✅ Phone number must be in format: `254712345678`

---

## 📞 Support

For issues, check:
1. Browser console: `F12` → Console tab
2. XAMPP error logs: `c:\xampp\apache\logs\`
3. PHP error logs: `c:\xampp\php\logs\`
4. Database test: `http://localhost/onrizo/test_db.php`

---

## 🎉 You're Ready!

Your Onrizo Shop is now:
- ✅ Running locally on XAMPP
- ✅ Connected to `onrizo_db` database
- ✅ Ready for admin product uploads
- ✅ Ready for customer purchases

**Next Steps:**
1. Register an admin account
2. Add some products
3. Test the shopping flow
4. When ready, migrate to a production server

**Happy Selling! 🛍️**
