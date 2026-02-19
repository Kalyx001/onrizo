# 🔧 Troubleshooting Guide

## Common Issues & Solutions

### ❌ Issue 1: Can't Access Master Admin Dashboard

**Error**: Page not found or blank screen

**Solution**:
1. Check URL: `http://localhost/onrizo/admin/master_dashboard.php`
2. Verify you're logged in as admin
3. Check browser console for JavaScript errors
4. Verify file exists: `admin/master_dashboard.php`
5. Restart Apache/PHP if needed

**Code to check**:
```php
// This should show "No syntax errors"
C:\xampp\php\php.exe -l c:\xampp\htdocs\onrizo\admin\master_dashboard.php
```

---

### ❌ Issue 2: "No pending payments" shown

**Problem**: Payments tab is empty

**Likely causes**:
- No affiliates have requested payments yet
- All payments have already been approved/paid
- Affiliate payments table is empty

**Solution**:
1. Have an affiliate request a withdrawal first
2. Check `affiliate_payments` table in database
3. Look for records with `status = 'pending'`

**SQL to check**:
```sql
SELECT * FROM affiliate_payments WHERE status = 'pending';
```

If empty, have an affiliate request withdrawal first.

---

### ❌ Issue 3: Affiliate sees wrong balance

**Problem**: Numbers don't match expected values

**Causes**:
- Cache not refreshed
- Database queries need refresh
- Commission calculation incorrect

**Solution**:
1. Hard refresh browser: **Ctrl+F5** (Windows) or **Cmd+Shift+R** (Mac)
2. Log out and log back in
3. Clear browser cache
4. Check affiliate_clicks table for confirmed sales

**Check database**:
```sql
SELECT COUNT(*), SUM(commission) 
FROM affiliate_clicks 
WHERE affiliate_id = ? AND status = 'confirmed';
```

---

### ❌ Issue 4: "Approve Payment" button doesn't work

**Problem**: Click button, nothing happens

**Troubleshooting**:
1. Check browser console for errors (F12)
2. Verify form was submitted (check Network tab)
3. Check PHP error logs

**Code to verify**:
- File: `admin/master_dashboard.php`
- Look for: `approve_payment` action handler
- Should update: `affiliate_payments.status = 'approved'`

**Solution**:
1. Refresh the page
2. Try clicking again
3. Check that form POST is working
4. Verify database permissions

---

### ❌ Issue 5: Can't delete product

**Problem**: Delete button doesn't work

**Causes**:
- Confirmation dialog not triggering
- Form submission failing
- Database permissions issue

**Solution**:
1. Make sure confirmation dialog appears
2. Click OK on the dialog
3. Wait for page to refresh
4. Check if product is gone

**Testing**:
```php
// Test delete manually
$product_id = 123; // Test ID
$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param('i', $product_id);
$result = $stmt->execute();
echo $result ? "Deleted" : "Failed";
```

---

### ❌ Issue 6: Affiliate doesn't see "Pending Approval"

**Problem**: Balance metrics don't show pending amount

**Causes**:
- Affiliate dashboard not updated
- Cache not cleared
- Page needs refresh

**Solution**:
1. Hard refresh: Ctrl+F5
2. Log out and log back in
3. Check file: `affiliate_dashboard.php` is updated
4. Verify database query runs

**Check file**:
```bash
# Verify file is updated
grep -n "Pending Approval" c:\xampp\htdocs\onrizo\affiliate_dashboard.php
# Should find the text in the file
```

---

### ❌ Issue 7: Payment approval shows but not in affiliate dashboard

**Problem**: Admin approves, affiliate doesn't see it

**Causes**:
- Cache issue
- Affiliate needs to refresh
- Database not updated

**Solution**:
1. Affiliate should hard refresh (Ctrl+F5)
2. Log out and log back in
3. Wait 10 seconds and refresh
4. Check database to verify status changed

**Verify in database**:
```sql
SELECT id, affiliate_id, amount, status 
FROM affiliate_payments 
ORDER BY created_at DESC LIMIT 5;
```

Status should show "approved" after you click the button.

---

### ❌ Issue 8: Search not working in Master Dashboard

**Problem**: Search box doesn't filter table

**Causes**:
- JavaScript error
- Wrong table ID
- Filter function not working

**Solution**:
1. Check browser console (F12)
2. Verify search input is focused
3. Try reloading page
4. Test with simple search term

**JavaScript to check**:
- Function: `filterTable()` in master_dashboard.php
- Should filter based on text in search box

---

### ❌ Issue 9: Database connection error

**Problem**: "Connection failed" or database errors

**Solution**:
1. Verify Apache is running
2. Verify MySQL/MariaDB is running
3. Check `db_config.php` has correct credentials
4. Restart both services

**Test connection**:
```bash
# Check if services running
Get-Service Apache2.4 -ErrorAction SilentlyContinue
Get-Service MySQL80 -ErrorAction SilentlyContinue
```

---

### ❌ Issue 10: "Table doesn't exist" error

**Problem**: SQL error for affiliate_payments table

**Causes**:
- Table not created
- Database connection wrong
- Table name misspelled

**Solution**:
1. Run setup script to create tables
2. Check table exists in database
3. Verify field names match in code

**Check tables**:
```sql
SHOW TABLES LIKE 'affiliate%';
SHOW TABLES LIKE 'products';
SHOW TABLES LIKE 'orders';
```

All should exist.

---

## 🎯 Quick Verification Checklist

### Master Dashboard:
```
☑ File exists: admin/master_dashboard.php
☑ File has no syntax errors
☑ Can access /admin/master_dashboard.php
☑ All 5 tabs load correctly
☑ Overview shows 6 metrics
☑ Products tab shows list
☑ Admins tab shows list
☑ Affiliates tab shows list
☑ Payments tab shows pending only
```

### Affiliate Dashboard:
```
☑ File exists: affiliate_dashboard.php
☑ File has no syntax errors
☑ Shows 5 balance metrics
☑ "Pending Approval" shows
☑ "Approved Amount" shows
☑ Payment history shows approval status
☑ Color coding works (yellow, blue, green)
```

### Database:
```
☑ affiliate_payments table exists
☑ status field contains: pending/approved/paid
☑ affiliate_clicks table exists
☑ products table exists
☑ affiliates table exists
☑ Can connect to database
```

### Navigation:
```
☑ Admin home page loads: /admin/index.php
☑ Link to Master Dashboard works
☑ Link in sidebar visible
☑ All quick links work
```

---

## 🐛 Debug Mode

### Enable PHP Error Display:

Edit `db_config.php` at top:
```php
<?php
// Add these lines
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Then your existing code...
include 'db_config.php';
```

This will show all errors on the page.

### Check Browser Console:

Press **F12** to open developer tools:
1. Click "Console" tab
2. Look for red error messages
3. Copy the full error
4. Search for solution

### Check Server Logs:

```bash
# PHP error log
Get-Content C:\xampp\php\logs\php_errors.log -Tail 20

# Apache error log
Get-Content C:\xampp\apache\logs\error.log -Tail 20
```

---

## 📞 Support Steps

### If something doesn't work:

1. **Verify File Exists**:
   ```bash
   Test-Path c:\xampp\htdocs\onrizo\admin\master_dashboard.php
   # Should return True
   ```

2. **Check Syntax**:
   ```bash
   C:\xampp\php\php.exe -l c:\xampp\htdocs\onrizo\admin\master_dashboard.php
   # Should say "No syntax errors"
   ```

3. **Check Database**:
   ```sql
   SELECT * FROM affiliate_payments LIMIT 1;
   ```

4. **Test Connection**:
   - Visit `http://localhost/onrizo/db_test.php`
   - Should show connection status

5. **Check Logs**:
   - Look in Apache and PHP error logs
   - Most errors are logged there

6. **Browser Console**:
   - Press F12
   - Look for JavaScript errors
   - Help identify frontend issues

---

## 🔍 Common Error Messages

### "Unknown column 'status' in 'where clause'"
**Problem**: Payment approval code trying to update non-existent field
**Fix**: Verify `affiliate_payments` table has `status` column
```sql
DESCRIBE affiliate_payments;
```

### "Table 'onrizo.affiliate_payments' doesn't exist"
**Problem**: Table not created
**Fix**: Run affiliate setup script
```sql
CREATE TABLE affiliate_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    transaction_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

### "Warning: Undefined variable"
**Problem**: Variable used before initialization
**Fix**: Check that all variables are set before use
**Solution**: Add isset() checks

### "Session not set"
**Problem**: $_SESSION empty
**Fix**: Verify session_start() called at top of file
**Check**: First line should be `<?php session_start();`

---

## ✅ Testing Workflow

### Full Test Sequence:

1. **Access Master Dashboard**:
   ```
   http://localhost/onrizo/admin/index.php
   → Click "Master Admin Panel"
   → Should load without errors
   ```

2. **Test Products Tab**:
   ```
   → Click Products tab
   → Should show product list
   → Try search
   → Try delete (with test product)
   ```

3. **Test Affiliates Tab**:
   ```
   → Click Affiliates tab
   → Should show affiliate list
   → Try search
   ```

4. **Test Payments**:
   ```
   → Click Payments tab
   → Have affiliate request withdrawal first if empty
   → Try approve payment
   → Status should change
   ```

5. **Verify Affiliate Sees Update**:
   ```
   → Log in as affiliate
   → Refresh dashboard
   → Should see updated balance
   → Should see payment approval status
   ```

---

## 📊 Performance Checks

### If pages load slowly:

1. **Check database query performance**:
   ```sql
   -- Enable timing
   SET profiling = 1;
   
   -- Run query
   SELECT * FROM affiliate_clicks WHERE affiliate_id = 1;
   
   -- Check timing
   SHOW PROFILES;
   ```

2. **Optimize queries**:
   - Add indexes to frequently searched columns
   - Limit result sets with LIMIT clause
   - Use EXPLAIN to check query plans

3. **Reduce data transferred**:
   - Master dashboard limits to 100 items
   - Payments limited to 50 items
   - Products limited to 100 items

---

## 🔐 Security Verification

### Verify security measures:

```
☑ All forms use POST method
☑ All delete operations need confirmation
☑ Session authentication required
☑ Prepared statements used (no SQL injection)
☑ Data sanitized with htmlspecialchars()
☑ No sensitive data in URLs
☑ Password hashing used for affiliates
```

---

## 📚 Files to Review

If experiencing issues, check these files:

1. **Master Dashboard**: `admin/master_dashboard.php`
2. **Affiliate Dashboard**: `affiliate_dashboard.php`
3. **Database Config**: `db_config.php`
4. **Admin Home**: `admin/index.php`
5. **Database Schema**: Check tables exist

---

## 💡 Pro Tips

1. **Always hard refresh** after making changes (Ctrl+F5)
2. **Clear cache** if pages look wrong
3. **Check console** (F12) for JavaScript errors
4. **Review logs** for server errors
5. **Test in clean browser** to rule out cache issues
6. **Use incognito/private** mode to avoid cache
7. **Restart services** if nothing works

---

## 📞 When to Seek Help

Contact support if:
- Database won't connect
- Files won't load
- PHP errors persist after troubleshooting
- Features don't work after verification
- Unexpected behavior after updates

Always provide:
1. Error message (exact text)
2. URL where error occurs
3. Browser/OS information
4. Steps to reproduce
5. Screenshots if applicable

---

## ✅ Final Verification

```bash
# Run these commands to verify everything
C:\xampp\php\php.exe -l c:\xampp\htdocs\onrizo\admin\master_dashboard.php
C:\xampp\php\php.exe -l c:\xampp\htdocs\onrizo\affiliate_dashboard.php
C:\xampp\php\php.exe -l c:\xampp\htdocs\onrizo\admin\index.php

# All should return "No syntax errors detected"
```

If all pass, your system is ready to go! 🚀

