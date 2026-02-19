# 🎯 Quick Admin Reference Guide

## 🚀 Getting Started

### Access Points:
1. **Admin Home**: `http://localhost/onrizo/admin/index.php` - Dashboard home
2. **Store Dashboard**: `http://localhost/onrizo/admin/store_dashboard.php` - Sales analytics
3. **Master Admin Panel**: `http://localhost/onrizo/admin/master_dashboard.php` - Platform management
4. **Product Management**: `http://localhost/onrizo/admin/dashboard.php` - Add/edit products

---

## 🛠️ Master Admin Panel - Complete Guide

### Access: `/admin/master_dashboard.php`

### Tab 1: 📊 Overview
**What you see**: 6 key metrics
- Total Sales (KES)
- Total Products
- Total Admins
- Active Affiliates
- Total Commissions
- Pending Payments

**Use case**: Get quick snapshot of platform health

---

### Tab 2: 📦 Products Management
**What you see**: All products in the system

**Columns**:
| Column | Info |
|--------|------|
| Product Name | Name (truncated) |
| Price | Sale price in KES |
| Admin | Which admin owns it |
| Added | Date created |
| Action | Delete button |

**Features**:
- 🔍 Search by product name
- 🗑️ Delete product (with confirmation)
- Shows ALL products (not just yours)

**Example**:
```
Product: iPhone 13 Pro
Price: KES 85,000
Admin: john@store.com
Added: Jan 15, 2024
```

---

### Tab 3: 👥 Admins
**What you see**: All system administrators

**Columns**:
| Column | Info |
|--------|------|
| Email | Admin email |
| Name | Admin name |
| Joined | Account creation date |

**Use case**: View who has admin access

---

### Tab 4: 🤝 Affiliates
**What you see**: All affiliate users in system

**Columns**:
| Column | Info |
|--------|------|
| Name | Affiliate name |
| Email | Contact email |
| Phone | Phone number |
| Code | Referral code (used in affiliate links) |
| Balance | Available earnings in KES |
| Status | active/pending |
| Action | Delete button |

**Features**:
- 🔍 Search by name or email
- 🗑️ Delete user (removes them from system)
- Shows referral code used in links

**Example**:
```
Name: John Doe
Email: john@affiliate.com
Phone: 0712345678
Code: REF001234
Balance: KES 5,500
Status: active
```

---

### Tab 5: 💳 Payments
**What you see**: PENDING payments waiting your approval

**Columns**:
| Column | Info |
|--------|------|
| Affiliate | Who's requesting payment |
| Email | Contact email |
| Amount | KES amount requested |
| Method | M-Pesa, Bank Transfer, etc |
| Status | pending/approved/paid |
| Requested | Date they requested |
| Action | Approve button |

**Workflow**:
1. Affiliate earns commission → requests withdrawal
2. Payment appears here with status "pending"
3. You review and click "Approve"
4. Status changes to "approved"
5. Affiliate sees it approved in their dashboard

**Example**:
```
Affiliate: Jane Smith
Email: jane@affiliate.com
Amount: KES 15,000
Method: M-Pesa
Status: pending
Requested: Jan 18, 2024
→ Click "Approve" button
```

---

## 💳 Payment Approval Flow

### Step-by-Step Process:

```
AFFILIATE SIDE:
1. Earns commission from referral sales
2. Requests withdrawal in their dashboard
   └─ Enters amount and payment method
   
3. Payment request sent to admin
   └─ Status: "pending"
   
4. Waits for admin approval
   └─ Sees "Pending Approval" amount in dashboard

ADMIN SIDE:
1. Log into Master Admin Panel
2. Go to "Payments" tab
3. See list of pending payments
4. Review each request
5. Click "Approve Payment" for each one
6. Status changes to "approved"

AFFILIATE SIDE:
1. Checks dashboard
2. Sees "Pending Approval" amount decreased
3. Sees "Approved Amount" increased
4. Gets notification payment was approved
5. Receives actual payment via M-Pesa/Bank
```

---

## 📊 Dashboard Metrics Explained

### In Master Admin - Overview Tab:

| Metric | What It Shows | Formula |
|--------|--------------|---------|
| Total Sales | All money from all orders | SUM(all order amounts) |
| Total Products | Count of all products | COUNT(products) |
| Total Admins | Count of all admins | COUNT(admins) |
| Active Affiliates | Count of affiliate users | COUNT(affiliates) |
| Total Commissions | Commission paid to affiliates | SUM(affiliate commissions) |
| Pending Payments | Payments waiting approval | COUNT(payment WHERE status='pending') |

---

## 🎯 Common Tasks

### Task 1: Delete a Product
```
1. Go to Master Admin Panel
2. Click "Products" tab
3. Search for product name
4. Click "Delete" button
5. Confirm deletion
6. Product removed from system
```

### Task 2: Delete an Affiliate User
```
1. Go to Master Admin Panel
2. Click "Affiliates" tab
3. Search for affiliate name
4. Click "Delete" button
5. Confirm deletion
6. User removed, their links stop working
```

### Task 3: Approve Affiliate Payment
```
1. Go to Master Admin Panel
2. Click "Payments" tab
3. See list of pending requests
4. Review amount and affiliate info
5. Click "Approve Payment"
6. Status changes to "approved"
7. Affiliate sees it in their dashboard
```

### Task 4: See All Sales
```
1. Go to Master Admin Panel
2. Click "Overview" tab
3. See "Total Sales" metric (KES amount)
4. See "Pending Payments" count
5. See "Total Commissions" owed
```

---

## 👥 Affiliate Dashboard - What They See

### Balance Metrics:
```
┌─────────────────────────────────────┐
│ Earned Commission: KES 50,000       │  Total they've earned
├─────────────────────────────────────┤
│ Approved Amount: KES 20,000         │  Admin approved
├─────────────────────────────────────┤
│ Pending Approval: KES 30,000        │  Waiting for approval
├─────────────────────────────────────┤
│ Account Balance: KES 15,000         │  Ready to withdraw
└─────────────────────────────────────┘

When you approve a KES 10,000 payment:
  Approved Amount: KES 20,000 → KES 30,000 ✅
  Pending Approval: KES 30,000 → KES 20,000 ✅
```

### Payment History Table:
Shows all payment requests with status:
- 🟨 **PENDING** - Waiting for you to approve
- 🟦 **APPROVED** - You approved it
- 🟩 **PAID** - Actually sent to them

---

## ⚙️ Database Fields Reference

### affiliate_payments table:
```
id              → Payment ID number
affiliate_id    → Which affiliate
amount          → How much (KES)
method          → mpesa, bank, etc
status          → pending/approved/paid ⭐
transaction_id  → M-Pesa/Bank reference
created_at      → When requested
```

### affiliate_clicks table:
```
id              → Click ID
affiliate_id    → Which affiliate
product_id      → Which product
commission      → Commission amount
status          → link_generated/pending/confirmed
created_at      → When the click happened
```

---

## 🔒 Security Notes

✅ Authentication required - must be logged in as admin
✅ All data validated on server side
✅ Deletions are PERMANENT - no recovery
✅ Confirmations prevent accidental actions
✅ Prepared statements prevent SQL injection

---

## 🎨 Color Indicators

### Payment Status Colors:
- 🟨 Yellow (PENDING) - Action needed
- 🟦 Blue (APPROVED) - Approved by admin
- 🟩 Green (PAID) - Completed

### Affiliate Status:
- 🟩 Green badge = ACTIVE
- 🟨 Yellow badge = PENDING

---

## 📱 Mobile Compatibility

✅ All pages responsive
✅ Tables scroll on small screens
✅ Touch-friendly buttons
✅ Stack view on mobile
✅ Full functionality on mobile

---

## 🔄 Real-Time Updates

⏱️ All data refreshes when you load/reload page
⏱️ Changes appear immediately
⏱️ No caching delays
⏱️ Affiliate dashboard updates when they refresh

---

## ❓ FAQ

**Q: What happens when I approve a payment?**
A: Status changes to "approved". Affiliate sees it in their dashboard. They can then withdraw it.

**Q: Can I undo a deletion?**
A: No, deletions are permanent. Be careful!

**Q: When will affiliates see the approved payment?**
A: When they refresh their dashboard. It updates in real-time.

**Q: Can affiliates see the master admin panel?**
A: No, only admins can access it. Affiliates only see their own dashboard.

**Q: What's the difference between "Approved" and "Paid"?**
A: Approved = you approved it but haven't sent money yet. Paid = you sent the money to them.

**Q: How do I check total sales?**
A: Master Admin Panel → Overview tab → "Total Sales" metric.

---

## 📞 Support Resources

- `MASTER_ADMIN_FEATURES.md` - Detailed feature documentation
- `admin/index.php` - Admin home with all links
- `admin/master_dashboard.php` - The master panel itself

