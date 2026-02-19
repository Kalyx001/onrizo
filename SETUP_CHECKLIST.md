# ✅ Onrizo Shop - Go Live Checklist

## 🟢 COMPLETED SETUP ITEMS

### Database Configuration
- ✅ Updated `db_config.php` to use local XAMPP MySQL
- ✅ Connected to `onrizo_db` database
- ✅ Added missing `category` column to products table
- ✅ Added `date_added` column to products table
- ✅ Created `product_images` table for additional images
- ✅ Verified all required tables exist

### Code Fixes
- ✅ Fixed include path in `admin/add_product.php`
- ✅ Updated M-Pesa callback URL to use dynamic hostname
- ✅ Created database test page (`test_db.php`)
- ✅ Created setup status page (`setup_status.php`)

### Documentation
- ✅ Created comprehensive `SETUP_GUIDE.md`
- ✅ Created `DATABASE_SCHEMA.md` with all table structures
- ✅ Updated `CODEBASE_OVERVIEW.md`

---

## 🚀 QUICK START (Run This NOW!)

### 1. Start XAMPP Services
```
Launch: C:\xampp\xampp-control.exe

Click START on:
✅ Apache
✅ MySQL
```

### 2. Verify Setup
Open browser and go to:
```
http://localhost/onrizo/setup_status.php
```

Should show all green checkmarks ✅

### 3. Access Your Site
```
Home Page: http://localhost/onrizo/
Admin Panel: http://localhost/onrizo/admin/login.php
Database Test: http://localhost/onrizo/test_db.php
```

---

## 📋 PRE-LAUNCH CHECKLIST

### Database
- [ ] MySQL is running and database `onrizo_db` is accessible
- [ ] All 5 tables exist: admins, products, product_images, orders, payments
- [ ] At least 1 admin account exists for testing
- [ ] Database backup created: `onrizo_backup.sql`

### Admin Features
- [ ] Admin can login successfully
- [ ] Admin can register new account
- [ ] Admin can upload products with images
- [ ] Products appear on homepage
- [ ] Admin can edit products
- [ ] Admin can delete products
- [ ] Admin can view orders

### Customer Features
- [ ] Homepage loads with product categories
- [ ] Search functionality works
- [ ] Category filtering works
- [ ] Product detail modal opens
- [ ] "Add to Cart" button works
- [ ] Cart page displays items correctly
- [ ] Cart persists after page refresh
- [ ] Checkout shows correct total
- [ ] M-Pesa payment form appears
- [ ] WhatsApp order button works

### Security
- [ ] No sensitive data in browser console
- [ ] Passwords are being hashed (not plain text)
- [ ] SQL injection protection active (prepared statements)
- [ ] Images upload only to `/uploads` folder

---

## 🔧 CONFIGURATION FILES UPDATED

### `db_config.php` ✅
```php
$host = "localhost";      // ✅ Local XAMPP
$user = "root";
$pass = "";
$dbname = "onrizo_db";    // ✅ Correct database
```

### `stk_push.php` ✅
```php
// Callback URL now uses dynamic hostname
$callbackUrl = $protocol . '://' . $host . '/onrizo/mpesa_callback.php';
```

### `admin/add_product.php` ✅
```php
include '../db_config.php';  // ✅ Fixed include path
```

---

## 📁 GENERATED FILES

New files created for setup:
1. `test_db.php` - Database connection test page
2. `setup_status.php` - Setup verification dashboard
3. `SETUP_GUIDE.md` - Comprehensive setup documentation
4. `DATABASE_SCHEMA.md` - Database structure documentation
5. `SETUP_CHECKLIST.md` - This file

---

## 🌐 URL REFERENCE

| Page | URL | Purpose |
|------|-----|---------|
| Homepage | `http://localhost/onrizo/` | Browse products |
| Setup Status | `http://localhost/onrizo/setup_status.php` | Verify setup |
| Database Test | `http://localhost/onrizo/test_db.php` | Test DB connection |
| Admin Login | `http://localhost/onrizo/admin/login.php` | Admin access |
| Admin Register | `http://localhost/onrizo/admin/register.php` | Create admin |
| Admin Dashboard | `http://localhost/onrizo/admin/dashboard.php` | Manage products |
| Add Product | `http://localhost/onrizo/admin/add_product.php` | Upload products |
| View Orders | `http://localhost/onrizo/admin/orders.php` | See orders |

---

## 💾 DATABASE COMMANDS

### Quick Commands via Terminal

**Check if database exists:**
```bash
cd c:\xampp\mysql\bin
.\mysql -u root -e "SHOW DATABASES LIKE 'onrizo_db';"
```

**Check tables:**
```bash
cd c:\xampp\mysql\bin
.\mysql -u root onrizo_db -e "SHOW TABLES;"
```

**Backup database:**
```bash
cd c:\xampp\mysql\bin
.\mysqldump -u root onrizo_db > backup.sql
```

**Count records:**
```bash
cd c:\xampp\mysql\bin
.\mysql -u root onrizo_db -e "SELECT COUNT(*) FROM products;"
```

---

## 🎯 NEXT STEPS

### Immediate (Do This Now)
1. [ ] Start XAMPP (Apache + MySQL)
2. [ ] Visit `http://localhost/onrizo/setup_status.php`
3. [ ] Verify all checks are GREEN ✅
4. [ ] Visit homepage and test browsing

### Short Term (Next 24 hours)
1. [ ] Register an admin account at `/admin/register.php`
2. [ ] Upload 3-5 test products
3. [ ] Test entire shopping flow
4. [ ] Test M-Pesa payment (sandbox)
5. [ ] Create database backup

### Before Production
1. [ ] Migrate to live server (upgrade to production hosting)
2. [ ] Update `db_config.php` with production credentials
3. [ ] Update M-Pesa credentials to production
4. [ ] Enable HTTPS/SSL certificate
5. [ ] Set proper file permissions
6. [ ] Implement additional security measures
7. [ ] Set up automated backups
8. [ ] Configure email for notifications
9. [ ] Set up domain pointing
10. [ ] Test everything on live server

---

## ⚠️ COMMON ISSUES & FIXES

### Issue: "Database connection failed"
**Solution:**
1. Ensure MySQL is running in XAMPP
2. Check `db_config.php` has correct settings
3. Run: `http://localhost/onrizo/test_db.php`

### Issue: "File not found" for db_config.php
**Solution:**
- Already fixed in `admin/add_product.php`
- Check other admin files use: `include '../db_config.php';`

### Issue: "No products showing"
**Solution:**
1. Check products table is empty: `http://localhost/onrizo/test_db.php`
2. Login to admin and add products
3. Verify images are uploaded to `/uploads` folder

### Issue: "M-Pesa payment fails"
**Solution:**
1. Check internet connection
2. Verify sandbox credentials in `stk_push.php`
3. Check callback URL is correct
4. Use phone format: `254712345678`

### Issue: "Admin login not working"
**Solution:**
1. Check admins table has users: `http://localhost/onrizo/test_db.php`
2. Try registering new admin account
3. Clear browser cookies
4. Check password was hashed correctly

---

## 📊 DATABASE SIZE TRACKING

Check database size:
```sql
SELECT 
    SUM(ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2)) AS size_mb
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'onrizo_db';
```

**Typical sizes:**
- Empty: ~0.1 MB
- With 100 products: ~1-2 MB
- With 1000+ orders: ~5-10 MB

---

## 📞 SUPPORT & DEBUGGING

### Enable Debug Mode
In `db_config.php`, uncomment:
```php
echo "✅ Database connected successfully!";
```

### View PHP Errors
1. Press `F12` in browser (Developer Tools)
2. Check "Console" tab for JavaScript errors
3. Check "Network" tab for failed requests

### Check Server Logs
```
Apache: C:\xampp\apache\logs\error.log
PHP: C:\xampp\php\logs\php_error.log
MySQL: C:\xampp\mysql\data\*.err
```

---

## 🎉 LAUNCH STEPS

When ready to go live on a production server:

### Step 1: Choose Hosting
- Recommended: Namecheap, Bluehost, or Hostinger
- Requirements: PHP 7.2+, MySQL 5.7+, 5GB storage

### Step 2: Prepare Files
```
Upload to server:
- All files from c:\xampp\htdocs\onrizo\
- Keep .htaccess for rewrites (if using)
- Keep uploads folder with permissions 755
```

### Step 3: Create Production Database
```
Import: onrizo_backup.sql into production MySQL
Update credentials in db_config.php
```

### Step 4: Update Configuration
```php
// db_config.php - production settings
$host = "your-production-host.com";
$user = "your_db_user";
$pass = "your_db_pass";
$dbname = "onrizo_db";
```

### Step 5: Update M-Pesa
```php
// stk_push.php - production credentials
$consumerKey = 'YOUR_LIVE_KEY';
$consumerSecret = 'YOUR_LIVE_SECRET';
// Change URLs to production (not sandbox)
```

### Step 6: Enable HTTPS
- Get SSL certificate
- Update all URLs to use https://
- Force HTTPS redirect in .htaccess

### Step 7: Test Live Site
- Test admin login
- Test product upload
- Test customer checkout
- Monitor error logs

---

## ✨ FINAL CHECKLIST

Before declaring site "LIVE":

```
Functionality
☐ All pages load correctly
☐ Database connectivity working
☐ Admin can manage products
☐ Customers can browse and add to cart
☐ Payment integration working
☐ Orders are saved to database
☐ Email notifications (if implemented)

Security
☐ No error messages exposing system info
☐ All inputs validated
☐ Prepared statements used throughout
☐ Passwords properly hashed
☐ HTTPS enabled
☐ Database backed up

Performance
☐ Pages load in <3 seconds
☐ Images optimized
☐ Database indexed properly
☐ Caching implemented

Content
☐ All text spelled correctly
☐ Links all working
☐ Contact information correct
☐ Terms & conditions added
```

---

## 🎊 YOU'RE READY!

Your Onrizo Shop is configured and ready to:
- ✅ Run locally on XAMPP
- ✅ Accept admin product uploads
- ✅ Process customer orders
- ✅ Handle M-Pesa payments
- ✅ Send WhatsApp messages to sellers
- ✅ Scale to production when ready

**Good luck with your e-commerce platform! 🚀**

