# 🎯 System Architecture & Feature Overview

## 📊 Complete System Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                        ONRIZO PLATFORM                              │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────────┐         ┌──────────────────────┐
│    ADMIN USERS       │         │   AFFILIATE USERS    │
├──────────────────────┤         ├──────────────────────┤
│ - Manage products    │         │ - Generate links     │
│ - Manage store       │         │ - Track earnings     │
│ - View orders        │         │ - Request payments   │
│ - Approve payments   │         │ - Monitor stats      │
└──────────────────────┘         └──────────────────────┘
         │                                │
         ├─── Master Admin Panel ────────┤
         │                                │
         ▼                                ▼
┌─────────────────────────────────────────────────────────────┐
│           MASTER ADMIN PANEL (NEW!)                         │
├─────────────────────────────────────────────────────────────┤
│ 📊 Overview: Key metrics for entire platform               │
│ 📦 Products: All products, delete option                   │
│ 👥 Admins: All system administrators                       │
│ 🤝 Affiliates: All users, delete option                    │
│ 💳 Payments: Approve pending affiliate payments            │
└─────────────────────────────────────────────────────────────┘
         │
         ├─────────────────────────────────────────────────────┐
         │                                                     │
    [PRODUCTS]                                         [PAYMENTS]
         │                                                     │
         ▼                                                     ▼
    ┌─────────────┐                                   ┌──────────────┐
    │  Database   │                                   │  Affiliate   │
    │  Products   │                                   │  Dashboard   │
    │  Table      │                                   │              │
    └─────────────┘                                   │ Shows:       │
                                                      │ - Earned $   │
                                                      │ - Approved $ │
                                                      │ - Pending $  │
                                                      │ - Withdrawn $│
                                                      └──────────────┘
```

---

## 🔄 Payment Approval Flow (Detailed)

```
STEP 1: AFFILIATE EARNS COMMISSION
┌─────────────────────────────────────────┐
│ Affiliate generates link for product     │
│              ↓                           │
│ Customer clicks affiliate link           │
│              ↓                           │
│ Customer purchases product               │
│              ↓                           │
│ Commission calculated and recorded       │
│              ↓                           │
│ affiliate_clicks table updated with:     │
│ - status = "confirmed"                   │
│ - commission = amount                    │
│ - date = today                           │
│                                          │
│ RESULT: Earned Commission increases      │
│ Example: Earned = KES 50,000            │
└─────────────────────────────────────────┘

STEP 2: AFFILIATE REQUESTS PAYMENT
┌─────────────────────────────────────────┐
│ Affiliate logs into dashboard            │
│              ↓                           │
│ Sees "Earned Commission: KES 50,000"     │
│              ↓                           │
│ Clicks "Request Withdrawal"              │
│              ↓                           │
│ Enters: Amount, Payment Method           │
│              ↓                           │
│ Example:                                 │
│ - Amount: KES 25,000                    │
│ - Method: M-Pesa                         │
│              ↓                           │
│ affiliate_payments table created with:   │
│ - status = "pending"                     │
│ - amount = 25000                         │
│                                          │
│ RESULT: Pending Approval appears         │
│ - Earned = KES 50,000                   │
│ - Pending = KES 25,000                  │
│ - Approved = KES 0                      │
└─────────────────────────────────────────┘

STEP 3: ADMIN REVIEWS & APPROVES
┌─────────────────────────────────────────┐
│ Admin logs into Master Admin Panel       │
│              ↓                           │
│ Clicks "Payments" tab                    │
│              ↓                           │
│ Sees: Jane Smith | KES 25,000 | Pending │
│              ↓                           │
│ Clicks "Approve Payment" button          │
│              ↓                           │
│ affiliate_payments updated:              │
│ - status = "approved" (was "pending")    │
│              ↓                           │
│ RESULT: Payment approved                 │
└─────────────────────────────────────────┘

STEP 4: AFFILIATE SEES APPROVED STATUS
┌─────────────────────────────────────────┐
│ Affiliate refreshes dashboard            │
│              ↓                           │
│ Dashboard recalculates:                  │
│ - Earned = KES 50,000 (unchanged)       │
│ - Approved = KES 25,000 (NOW!)          │
│ - Pending = KES 25,000 → KES 25,000     │
│              ↓                           │
│ Sees in Payment History:                 │
│ │ Amount │ Status │ Approval │           │
│ │25,000  │pending │ APPROVED │           │
│              ↓                           │
│ RESULT: Affiliate knows payment approved │
└─────────────────────────────────────────┘

STEP 5: PAYMENT MARKED AS PAID (Optional)
┌─────────────────────────────────────────┐
│ Once actual money sent to affiliate:     │
│              ↓                           │
│ Admin updates status to "paid"           │
│              ↓                           │
│ affiliate_payments:                      │
│ - status = "paid"                        │
│              ↓                           │
│ Affiliate sees:                          │
│ - Total Withdrawn increased              │
│ - Payment history shows "PAID"           │
│                                          │
│ RESULT: Complete payment cycle           │
└─────────────────────────────────────────┘
```

---

## 💰 Balance Calculation

### Affiliate Dashboard Metrics:

```
EARNED COMMISSION
├─ Total from all confirmed sales
├─ Example: 5 sales × KES 10,000 = KES 50,000
├─ Shown in: GREEN (money earned)
└─ Formula: SUM(commission WHERE status='confirmed')

APPROVED AMOUNT
├─ Payments admin has approved
├─ Example: Admin approved 2 payments = KES 25,000
├─ Shown in: BLUE (money approved)
└─ Formula: SUM(amount WHERE status IN ('approved', 'paid'))

PENDING APPROVAL ⭐ NEW
├─ Earned - Approved = Waiting for approval
├─ Example: KES 50,000 - KES 25,000 = KES 25,000
├─ Shown in: YELLOW (needs attention)
└─ Formula: Earned Commission - Approved Amount

ACCOUNT BALANCE
├─ Money available to withdraw
├─ Changes when paid out
├─ Shown in: DEFAULT
└─ Formula: (Earned - Approved) or admin-set balance

TOTAL WITHDRAWN
├─ Money already paid out
├─ Example: KES 15,000 withdrawn previously
├─ Shown in: DEFAULT
└─ Formula: SUM(amount WHERE status='paid')

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Example Dashboard View:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Earned Commission:    KES 50,000  🟢 (GREEN)
Approved Amount:      KES 25,000  🔵 (BLUE)
Pending Approval:     KES 25,000  🟨 (YELLOW) ← WAITING
Account Balance:      KES 15,000  ⚪ (DEFAULT)
Total Withdrawn:      KES 10,000  ⚪ (DEFAULT)

This means:
- Affiliate has earned KES 50,000 total
- Admin has approved KES 25,000
- KES 25,000 still waiting approval
- Can withdraw KES 15,000 now
```

---

## 🎯 Master Admin Panel Structure

```
┌────────────────────────────────────────────────────┐
│          Master Admin Panel (/admin/master_dashboard.php)    │
├────────────────────────────────────────────────────┤
│  TAB BUTTONS: [Overview] [Products] [Admins] [Affiliates] [Payments] │
├────────────────────────────────────────────────────┤

📊 OVERVIEW TAB
├─ Metric 1: Total Sales (all orders)
├─ Metric 2: Total Products (count)
├─ Metric 3: Total Admins (count)
├─ Metric 4: Active Affiliates (count)
├─ Metric 5: Total Commissions (sum)
└─ Metric 6: Pending Payments (count)

📦 PRODUCTS TAB
├─ Table Headers: Name | Price | Admin | Added | Action
├─ 🔍 Search box (search by name)
├─ For each product:
│  ├─ Product name (clickable)
│  ├─ Price in KES
│  ├─ Admin email (owner)
│  ├─ Date added
│  └─ [Delete] button with confirmation
└─ Max 100 products shown

👥 ADMINS TAB
├─ Table Headers: Email | Name | Joined
├─ For each admin:
│  ├─ Email address
│  ├─ Admin name
│  └─ Join date
└─ Read-only (no actions)

🤝 AFFILIATES TAB
├─ Table Headers: Name | Email | Phone | Code | Balance | Status | Action
├─ 🔍 Search box (search by name/email)
├─ For each affiliate:
│  ├─ Name
│  ├─ Email
│  ├─ Phone
│  ├─ Referral code (used in links)
│  ├─ Balance (KES)
│  ├─ Status badge (active/pending)
│  └─ [Delete] button with confirmation
└─ Max 100 affiliates shown

💳 PAYMENTS TAB
├─ Table Headers: Affiliate | Email | Amount | Method | Status | Requested | Action
├─ For each pending payment:
│  ├─ Affiliate name
│  ├─ Email
│  ├─ Amount (KES)
│  ├─ Payment method (M-Pesa, Bank, etc)
│  ├─ Status: "pending" badge
│  ├─ Request date
│  └─ [Approve Payment] button
├─ Shows ONLY pending payments
└─ Max 50 payments shown

└────────────────────────────────────────────────────┘
```

---

## 🔀 Data Flow

```
AFFILIATE SYSTEM DATA FLOW:
═══════════════════════════

User Visits Store
    ↓
Clicks "Sell This Product"
    ↓
Enters Email
    ↓
check_affiliate_email.php (API)
    ├─ Validates email in database
    ├─ Gets referral_code
    └─ Returns OK or ERROR
    ↓
Generates Affiliate Link
    ├─ generate_affiliate_link.php (API)
    ├─ Creates affiliate_clicks record
    ├─ Status = "link_generated"
    └─ Returns shareable link
    ↓
Customer Clicks Link
    ├─ URL param: ?ref=REFERRAL_CODE&product=PRODUCT_ID
    ├─ script.js captureReferral() function
    ├─ Stores in localStorage
    ├─ Redirects to cart.html
    └─ Fetches product in background
    ↓
Customer Adds to Cart & Buys
    ├─ cart.html reads affiliate_ref
    ├─ Attaches to order data
    └─ Sends to save_order.php
    ↓
save_order.php Processes Order
    ├─ Looks up referral_code
    ├─ Gets affiliate_id
    ├─ Calculates commission
    ├─ Updates affiliate_clicks
    │  └─ Status = "confirmed"
    ├─ Records commission amount
    └─ Updates affiliate balance
    ↓
Affiliate Sees Earnings
    ├─ affiliate_dashboard.php calculates totals
    ├─ Shows Earned Commission
    ├─ Shows Approved Amount
    ├─ Shows Pending Approval ← NEW
    └─ Shows Account Balance
    ↓
Affiliate Requests Payment
    ├─ affiliate_request_withdrawal.php
    ├─ Creates affiliate_payments record
    ├─ Status = "pending"
    └─ Amount = requested amount
    ↓
Admin Reviews in Master Panel
    ├─ Sees in Payments tab
    ├─ Reviews affiliate info
    ├─ Clicks Approve Payment
    └─ Updates status to "approved"
    ↓
Affiliate Sees Update
    ├─ Refreshes dashboard
    ├─ affiliate_dashboard.php recalculates
    ├─ Approved Amount increases
    ├─ Pending Approval decreases
    └─ Payment shows as "APPROVED" in history
    ↓
[Payment Sent to Affiliate]
    ├─ Admin marks as paid
    ├─ Status = "paid"
    └─ Affiliate sees it withdrawn
```

---

## 🗄️ Database Tables Used

```
products
├─ id, admin_id, name, price, image, deleted
├─ affiliate_percent (commission for each product)
└─ Used by: Master Dashboard, Orders

affiliates
├─ id, name, email, phone, referral_code, balance
├─ status, created_at
└─ Used by: Master Dashboard, Balance tracking

admins
├─ id, email, name, created_at
└─ Used by: Master Dashboard view

affiliate_clicks
├─ id, affiliate_id, product_id, commission
├─ status (link_generated/pending/confirmed)
├─ order_code, created_at
└─ Used by: Commission tracking, earning calculation

affiliate_payments ⭐ KEY TABLE
├─ id, affiliate_id, amount, method
├─ status (pending/approved/paid) ← APPROVAL FIELD
├─ transaction_id, created_at
└─ Used by: Payment approval workflow

orders
├─ id, customer_name, email, status, created_at
├─ affiliate_ref, total
└─ Used by: Sales tracking

order_items
├─ id, order_id, product_id, subtotal
└─ Used by: Revenue calculation
```

---

## 🎬 Process Timeline

```
Timeline of a Complete Affiliate Sale:

Day 1, 10:00 AM
├─ Affiliate generates link
├─ Status: link_generated
└─ affiliate_clicks created

Day 1, 10:05 AM
├─ Customer clicks link
├─ Stored in localStorage
└─ Redirects to cart

Day 1, 10:15 AM
├─ Customer purchases
├─ save_order.php processes
├─ affiliate_clicks updated
├─ Status: confirmed
├─ Commission: KES 5,000
└─ Affiliate sees earned amount

Day 3, 02:00 PM
├─ Affiliate requests withdrawal
├─ Amount: KES 15,000
├─ affiliate_payments created
├─ Status: pending
└─ Dashboard shows "Pending Approval"

Day 3, 02:30 PM
├─ Admin approves payment
├─ Master Admin Panel
├─ Clicks "Approve Payment"
├─ Status: approved
└─ Affiliate gets notification

Day 3, 03:00 PM
├─ Affiliate refreshes dashboard
├─ Sees approved amount increased
├─ Sees pending decreased
└─ Payment shows "APPROVED"

Day 4, 09:00 AM
├─ Admin sends actual payment
├─ Via M-Pesa or Bank
├─ Updates status: paid
└─ Affiliate sees withdrawn
```

---

## 🎨 Color Coding Reference

```
PAYMENT STATUSES:
🟨 PENDING (Yellow)
   └─ Waiting for admin approval
   └─ Action needed

🟦 APPROVED (Blue)
   └─ Admin approved
   └─ Ready for payout

🟩 PAID (Green)
   └─ Money sent
   └─ Complete

METRICS:
🟢 GREEN = Earned Commission (positive)
🔵 BLUE = Approved Amount (positive)
🟨 YELLOW = Pending Approval (needs attention)
⚪ DEFAULT = Balance, Withdrawn, etc

AFFILIATE STATUS:
🟩 ACTIVE (Green badge)
🟨 PENDING (Yellow badge)
```

---

## 📱 Access Points Summary

```
ADMIN:
├─ Admin Home: /admin/index.php
├─ Master Admin: /admin/master_dashboard.php
├─ Store Dashboard: /admin/store_dashboard.php
├─ Products: /admin/dashboard.php
├─ Orders: /admin/orders.php
└─ Sidebar Link: "🛠️ Master Admin Panel"

AFFILIATE:
├─ Dashboard: /affiliate_dashboard.php
├─ Login: /affiliate_login.php
└─ API: /affiliate_balance_status.php

PUBLIC:
├─ Store: /index.html
├─ Product: /get_product.php
├─ Cart: /cart.html
└─ Checkout: /checkout.html
```

---

## ✅ Implementation Checklist

- ✅ Master Dashboard created
- ✅ Overview metrics implemented
- ✅ Products tab with search & delete
- ✅ Admins tab (read-only)
- ✅ Affiliates tab with search & delete
- ✅ Payments tab with approval system
- ✅ Affiliate dashboard updated with approval status
- ✅ Payment status colors implemented
- ✅ Admin home portal created
- ✅ Navigation links added
- ✅ Documentation created
- ✅ All files tested for syntax
- ✅ Real-time status updates working

**Status: COMPLETE AND READY TO USE! 🚀**

