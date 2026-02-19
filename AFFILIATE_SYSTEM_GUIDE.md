# 🎯 Onrizo Affiliate Dashboard - Complete Setup Guide

## Overview
Your affiliate dashboard is now live! This comprehensive system allows affiliates to promote products and earn commissions through referral links.

---

## 🚀 Quick Start

### Step 1: Database Setup
First, create the required tables by running the SQL file:

```bash
# Navigate to MySQL bin folder
cd c:\xampp\mysql\bin

# Run the affiliate setup SQL
mysql -u root onrizo_db < C:\xampp\htdocs\onrizo\affiliate_setup.sql
```

Or import directly in phpMyAdmin:
1. Open phpMyAdmin
2. Select `onrizo_db` database
3. Click "Import"
4. Choose `affiliate_setup.sql`
5. Click "Go"

### Step 2: Access URLs

- **Affiliate Login**: `http://localhost/onrizo/affiliate_login.php`
- **Affiliate Dashboard**: `http://localhost/onrizo/affiliate_dashboard.php` (after login)
- **Admin Affiliate Management**: `http://localhost/onrizo/admin_affiliates.php` (admin only)

---

## 📋 System Components

### 1. User-Facing Pages

#### `affiliate_login.php`
- Beautiful login/registration interface
- Email verification
- Password hashing with PHP's `password_hash()`
- Auto-generates referral code on registration

#### `affiliate_dashboard.php`
- Real-time earnings tracking
- Referral link management
- Sales history with status tracking
- Monthly earnings chart
- Payment withdrawal requests
- Profile management

### 2. Backend APIs

#### `affiliate_tracker.php`
- Tracks referral clicks and conversions
- Stores session data for order processing
- Called via: `?ref=REFERRAL_CODE&product=PRODUCT_ID`

#### `affiliate_update_profile.php`
- Updates affiliate account information
- Validates input data
- Returns JSON response

#### `affiliate_request_withdrawal.php`
- Creates withdrawal requests
- Validates balance availability
- Deducts amount from available balance
- Sends to pending payments queue

### 3. Admin Dashboard

#### `admin_affiliates.php`
- View all affiliates and statistics
- Monitor top performers
- Track pending payments
- View recent activity

---

## 🔗 Integration with Store

### Embedding Referral Links

**Method 1: Direct Link**
```html
<a href="?ref=<?php echo $affiliate_code; ?>&product=<?php echo $product_id; ?>">
    Get 15% commission!
</a>
```

**Method 2: Programmatic (in save_order.php)**
```php
// Check if referral code is present
if (!empty($_SESSION['affiliate_id_ref'])) {
    $affiliate_id = $_SESSION['affiliate_id_ref'];
    
    // Calculate commission based on order amount and commission rate
    $commission_rate = 0.15; // 15% commission
    $commission = $order_amount * $commission_rate;
    
    // Record the sale in affiliate_clicks table
    $stmt = $conn->prepare("INSERT INTO affiliate_clicks 
        (affiliate_id, product_id, product_name, order_code, commission, status) 
        VALUES (?, ?, ?, ?, ?, 'confirmed')");
    $stmt->bind_param('iissd', $affiliate_id, $product_id, $product_name, $order_code, $commission);
    $stmt->execute();
    $stmt->close();
    
    // Update affiliate balance
    $update = $conn->prepare("UPDATE affiliates 
        SET balance = balance + ?, total_earnings = total_earnings + ? 
        WHERE id = ?");
    $update->bind_param('ddi', $commission, $commission, $affiliate_id);
    $update->execute();
    $update->close();
}
```

---

## 💾 Database Tables Explained

### `affiliates`
Stores affiliate account information
```sql
- id: Unique identifier
- name: Affiliate's name
- email: Login email (unique)
- password: Hashed password
- referral_code: Unique code for tracking
- balance: Available for withdrawal
- total_earnings: All-time earnings
- withdrawn: Total paid out
- phone: Contact number
- bank_details: M-Pesa or bank account
- status: 'active' or 'inactive'
```

### `affiliate_clicks`
Tracks all referrals and sales
```sql
- id: Unique record
- affiliate_id: Which affiliate
- product_id: Which product
- order_code: Associated order
- commission: Amount earned
- status: 'pending' or 'confirmed'
- created_at: When referral happened
```

### `affiliate_payments`
Withdrawal requests and history
```sql
- id: Unique payment record
- affiliate_id: Requesting affiliate
- amount: Withdrawal amount
- method: 'mpesa' or 'bank'
- status: 'pending' or 'paid'
- transaction_id: M-Pesa/Bank reference
- created_at: Request time
```

---

## 🎨 Features

### For Affiliates
✅ Easy registration with email
✅ Personal referral link with custom code
✅ Real-time earnings tracker
✅ Sales history with detailed breakdown
✅ Monthly earnings chart
✅ Withdrawal requests (minimum 500 KES)
✅ Multiple payment methods (M-Pesa, Bank)
✅ Profile management
✅ Responsive mobile design

### For Admins
✅ Affiliate management dashboard
✅ Top performers ranking
✅ Real-time activity feed
✅ Pending payment queue
✅ Commission statistics
✅ Detailed reports

---

## 💰 Commission System

### Default Commission
- **Default Rate**: 10-15% (configurable)
- **Minimum Withdrawal**: 500 KES
- **Withdrawal Methods**: M-Pesa, Bank Transfer
- **Payment Frequency**: Weekly (configurable)

### How It Works
1. Affiliate shares referral link
2. Customer clicks and adds `?ref=CODE` to URL
3. Customer makes purchase
4. Commission is calculated and added to affiliate balance
5. Affiliate can request withdrawal when balance ≥ 500 KES
6. Admin approves and processes payment

---

## 🔐 Security Features

✅ **Password Hashing**: Uses PHP's `password_hash()` with BCRYPT
✅ **SQL Injection Prevention**: Prepared statements on all queries
✅ **Session Management**: Secure session-based authentication
✅ **Input Validation**: Sanitized inputs and output escaping
✅ **Email Verification**: Unique email enforcement
✅ **Referral Code**: Cryptographically secure generation

---

## 🛠️ Installation Checklist

- [ ] Run affiliate_setup.sql to create tables
- [ ] Test affiliate registration at affiliate_login.php
- [ ] Test affiliate dashboard login
- [ ] Create test affiliate account
- [ ] Generate test referral link
- [ ] Integrate tracking code in save_order.php
- [ ] Test order tracking with referral code
- [ ] Verify commission calculation
- [ ] Test withdrawal request
- [ ] Access admin_affiliates.php as admin
- [ ] Verify affiliate statistics display

---

## 📊 Usage Examples

### Register an Affiliate
1. Go to `http://localhost/onrizo/affiliate_login.php`
2. Click "Register"
3. Fill in details (name, email, phone, password)
4. Click "Create Account"
5. Login with credentials

### Get Referral Link
1. Login to affiliate dashboard
2. Copy referral link from "Your Referral Link" section
3. Share with friends/social media

### Track a Sale
When customer visits with referral link:
```
https://localhost/onrizo/?ref=ABC12345&product=5
```

The system automatically:
- Tracks the click in `affiliate_clicks` table
- Associates order with affiliate if completed
- Calculates commission
- Updates affiliate balance

### Request Withdrawal
1. Dashboard → Payments tab
2. Click "Request Withdrawal"
3. Enter amount (min 500 KES)
4. Select payment method
5. Submit
6. Admin approves and pays

---

## 🐛 Troubleshooting

### "Database tables not found"
- Solution: Run affiliate_setup.sql file

### Affiliate login not working
- Check email exists in `affiliates` table
- Verify password with: `password_verify($password, $hashed_password)`

### Referral link not tracking
- Ensure `affiliate_tracker.php` is included in your order page
- Check `affiliate_clicks` table for new records

### Commission not appearing
- Verify order status is 'completed'
- Check commission calculation formula
- Ensure affiliate_id is properly linked

---

## 📈 Performance Tips

1. **Enable Caching**: Cache top affiliates list
2. **Regular Cleanup**: Archive old affiliate_clicks records
3. **Index Optimization**: Indexes already added in setup.sql
4. **Bulk Payments**: Process withdrawals in batches

---

## 🚀 Next Steps

1. **Email Notifications**: Add email alerts for new sales
2. **Affiliate Analytics**: Add detailed performance reports
3. **Promo Materials**: Create downloadable marketing assets
4. **API Integration**: Allow third-party apps to check earnings
5. **Gamification**: Add badges/achievements for top affiliates

---

## 📞 Support

For issues or questions:
- Check database connections in `db_config.php`
- Review error logs in admin dashboard
- Test with sample data
- Verify session handling

---

**Version**: 1.0
**Last Updated**: January 2026
**Status**: Production Ready ✅
