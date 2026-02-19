# 📸 Visual Quick Start Guide

## 🏠 Getting Started

### Step 1: Admin Home Page
```
Visit: http://localhost/onrizo/admin/index.php

You will see:
┌─────────────────────────────────┐
│  🛒 Onrizo Admin Portal         │
│  Manage your store, products,   │
│  affiliates, and payments       │
└─────────────────────────────────┘

With 6 cards:
┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│ 📊 Store    │ │ 🛠️ Master   │ │ 📦 Product  │
│ Dashboard   │ │ Admin Panel  │ │ Management  │
└─────────────┘ └─────────────┘ └─────────────┘

┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│ 📋 Orders   │ │ ➕ Add      │ │ 🚀 Promote  │
│             │ │ Product     │ │ Products    │
└─────────────┘ └─────────────┘ └─────────────┘
```

### Step 2: Click Master Admin Panel
```
Click: "🛠️ Master Admin Panel" card or link

You will be taken to:
http://localhost/onrizo/admin/master_dashboard.php
```

---

## 🎯 Master Admin Panel Layout

### Dashboard Structure:
```
┌───────────────────────────────────────────┐
│ Header: 🛠️ Master Admin Panel             │
│ Links: [Admin Home] [Logout]              │
└───────────────────────────────────────────┘

┌───────────────────────────────────────────┐
│ Tab Buttons:                              │
│ [Overview] [Products] [Admins]            │
│ [Affiliates] [Payments]                   │
└───────────────────────────────────────────┘

┌───────────────────────────────────────────┐
│ Tab Content (changes based on selection)  │
│                                           │
│ Displays data for selected tab            │
└───────────────────────────────────────────┘
```

---

## 📊 Overview Tab

### What You See:
```
6 Metric Cards in a Grid:

┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ Total Sales  │ │ Total        │ │ Total Admins │
│ KES 500,000  │ │ Products     │ │ 3            │
│              │ │ 25           │ │              │
└──────────────┘ └──────────────┘ └──────────────┘

┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ Active       │ │ Total        │ │ Pending      │
│ Affiliates   │ │ Commissions  │ │ Payments     │
│ 12           │ │ KES 45,000   │ │ 4            │
└──────────────┘ └──────────────┘ └──────────────┘

Each card shows:
- Label (what it is)
- Big number (the value)
- Description
```

### How to Use:
```
1. Open Master Dashboard
2. See Overview tab selected by default
3. View 6 key platform metrics
4. Use for quick platform health check
```

---

## 📦 Products Tab

### What You See:
```
┌────────────────────────────────────────────────┐
│ Search Box: [Search products...             ]  │
└────────────────────────────────────────────────┘

┌────────────────────────────────────────────────┐
│ Product Name │ Price │ Admin │ Added │ Action  │
├────────────────────────────────────────────────┤
│ iPhone 13    │ 85000 │ john@ │ 5 Jan │ Delete  │
│ Samsung S21  │ 75000 │ jane@ │ 3 Jan │ Delete  │
│ iPad Pro     │ 95000 │ john@ │ 2 Jan │ Delete  │
│ ...          │ ...   │ ...   │ ...   │ ...     │
└────────────────────────────────────────────────┘

Total: 25 products shown
```

### How to Use:
```
1. Click "Products" tab
2. See all products in system
3. Use search box to find product
4. Click [Delete] to remove product
5. Confirm deletion in dialog
```

---

## 👥 Admins Tab

### What You See:
```
┌────────────────────────────────┐
│ Email        │ Name   │ Joined │
├────────────────────────────────┤
│ john@site.   │ John   │ Jan 1  │
│ jane@site.   │ Jane   │ Jan 5  │
│ admin@site.  │ Admin  │ Dec 25 │
└────────────────────────────────┘

Read-only: No actions
```

### How to Use:
```
1. Click "Admins" tab
2. View all administrators
3. See their email and join date
4. This is informational only
```

---

## 🤝 Affiliates Tab

### What You See:
```
┌──────────────────────────────────────────────────────┐
│ Search: [Search affiliates...                     ]  │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│ Name  │ Email  │ Phone │ Code  │ Balance │ Status   │
├──────────────────────────────────────────────────────┤
│ Jane  │ jane@  │ 0712  │ REF01 │ 5000    │ active   │
│ John  │ john@  │ 0712  │ REF02 │ 0       │ pending  │
│ Mary  │ mary@  │ 0712  │ REF03 │ 15000   │ active   │
│ ...   │ ...    │ ...   │ ...   │ ...     │ ...      │
└──────────────────────────────────────────────────────┘

Each row has: [Delete] button
```

### How to Use:
```
1. Click "Affiliates" tab
2. See all affiliate users
3. Use search to find specific user
4. Click [Delete] to remove user
5. Confirm deletion
```

---

## 💳 Payments Tab

### What You See:
```
Payments Waiting Your Approval:

┌─────────────────────────────────────────────────────┐
│ Affiliate │ Email │ Amount │ Method │ Requested    │
├─────────────────────────────────────────────────────┤
│ Jane Smith│ jane@ │ 25000  │ M-Pesa │ 18 Jan 2024  │
│ John Doe  │ john@ │ 15000  │ Bank   │ 17 Jan 2024  │
│ Mary Lee  │ mary@ │ 30000  │ M-Pesa │ 16 Jan 2024  │
└─────────────────────────────────────────────────────┘

Each row has: [Approve Payment] button
Status showing: "pending" (yellow badge)
```

### How to Use:
```
1. Click "Payments" tab
2. See pending payment requests
3. Review affiliate, amount, method
4. Click [Approve Payment] to approve
5. Status changes to "approved"
6. Affiliate sees it in their dashboard
```

---

## ✅ Payment Approval Workflow (Visual)

### Complete Process:

```
STEP 1: Affiliate Requests
═════════════════════════════════════
Affiliate Dashboard:
┌─────────────────────────┐
│ [💸 Request Withdrawal] │  ← Click button
└─────────────────────────┘
        ↓
    Enters: Amount + Method
        ↓
    Payment created


STEP 2: Admin Reviews
═════════════════════════════════════
Master Admin Panel → Payments tab:
┌───────────────────────────────────┐
│ Jane Smith │ KES 25,000 │ pending │
│ [Approve Payment]                 │
└───────────────────────────────────┘
        ↓
    Admin clicks button
        ↓
    Status updates: approved


STEP 3: Affiliate Sees Update
═════════════════════════════════════
Affiliate Dashboard (after refresh):
┌──────────────────────────┐
│ Pending Approval:        │  ← Decreased
│ KES 25,000 → KES 15,000  │
├──────────────────────────┤
│ Approved Amount:         │  ← Increased
│ KES 0 → KES 25,000       │
└──────────────────────────┘

Payment History:
┌────────────────┐
│ Status: APPROVED │  ← Changed from pending
└────────────────┘
```

---

## 👤 Affiliate Dashboard View (After Approval)

### Balance Metrics Now Show:

```
┌──────────────────────────────┐
│ Earned Commission            │
│ KES 50,000                   │  Total from all sales
│ 🟢 GREEN                     │
└──────────────────────────────┘

┌──────────────────────────────┐
│ Approved Amount              │
│ KES 25,000                   │  Admin approved
│ 🔵 BLUE                      │
└──────────────────────────────┘

┌──────────────────────────────┐
│ Pending Approval       ⭐NEW │
│ KES 25,000                   │  Waiting for approval
│ 🟨 YELLOW                    │  (NEEDS ATTENTION)
└──────────────────────────────┘

┌──────────────────────────────┐
│ Account Balance              │
│ KES 15,000                   │  Ready to withdraw
│ ⚪ GREY                      │
└──────────────────────────────┘

┌──────────────────────────────┐
│ Total Withdrawn              │
│ KES 10,000                   │  Already paid out
│ ⚪ GREY                      │
└──────────────────────────────┘
```

### Payment History Now Shows Approval Status:

```
┌────────────────────────────────────────────┐
│ Amount │ Method │ Status  │ Approval Status  │
├────────────────────────────────────────────┤
│ 25000  │ M-Pesa │ pending │ 🟨 PENDING      │
│        │        │         │ (Yellow badge)  │
├────────────────────────────────────────────┤
│ 15000  │ Bank   │ pending │ 🔵 APPROVED     │
│        │        │         │ (Blue badge)    │
├────────────────────────────────────────────┤
│ 10000  │ M-Pesa │ pending │ 🟩 PAID         │
│        │        │         │ (Green badge)   │
└────────────────────────────────────────────┘
```

---

## 🎬 Step-by-Step: Approve a Payment

### Complete Process with Visuals:

```
STEP 1: Log In
═════════════════════════════════════
Visit: http://localhost/onrizo/admin/index.php
Log in with admin credentials
See: Admin home page


STEP 2: Click Master Admin Panel
═════════════════════════════════════
Dashboard Home Page:
┌─────────────────────────┐
│ 🛠️ Master Admin Panel   │  ← Click this card
│ "Manage entire platform"│
└─────────────────────────┘
        ↓
    Redirects to: master_dashboard.php


STEP 3: Click Payments Tab
═════════════════════════════════════
Tab Row at Top:
[Overview] [Products] [Admins] [Affiliates] [💳 Payments]
                                            ↑ Click


STEP 4: Find Pending Payment
═════════════════════════════════════
Table Shows:
┌──────────────────────────────────┐
│ Jane Smith │ 25000 │ M-Pesa      │
│ [Approve Payment] ← This button  │
└──────────────────────────────────┘


STEP 5: Click Approve
═════════════════════════════════════
Before:
Status: 🟨 pending

Click [Approve Payment] button

After:
Status: 🔵 approved
Message: "Payment approved successfully"


STEP 6: Affiliate Sees Update
═════════════════════════════════════
Affiliate logs in and refreshes:

Dashboard changes:
- Pending Approval KES 25,000 → KES 15,000
- Approved Amount KES 0 → KES 25,000
- Payment shows "APPROVED"
```

---

## 🔍 Search Examples

### Search Products:
```
1. Click Products tab
2. In search box, type: "iPhone"
3. Only products with "iPhone" shown
4. Type "Samsung" → only Samsung shown
5. Clear box → all shown again
```

### Search Affiliates:
```
1. Click Affiliates tab
2. In search box, type: "jane"
3. Only "Jane Smith" (jane@...) shown
4. Type "john@" → only John shown
5. Clear box → all shown again
```

---

## 🗑️ Delete Examples

### Delete a Product:
```
1. Go to Products tab
2. Find product to delete
3. Click [Delete] button
4. Dialog appears: "Delete this product?"
5. Click OK to confirm
6. Product removed
7. Message: "Product deleted successfully"
```

### Delete an Affiliate:
```
1. Go to Affiliates tab
2. Find user to delete
3. Click [Delete] button
4. Dialog appears: "Delete this user?"
5. Click OK to confirm
6. User removed
7. Message: "User deleted successfully"
```

---

## 📱 Mobile View

### Master Dashboard on Mobile:
```
Portrait Mode:
┌─────────────────────┐
│ 🛠️ Master Admin     │
│ [Tabs as buttons]   │
├─────────────────────┤
│ Tab content (full   │
│ width, scrollable)  │
│                     │
│ Tables scroll →     │
└─────────────────────┘

Metrics Stack vertically
Tables scroll horizontally
All buttons touch-friendly
```

---

## 🎨 Color Scheme Reference

### Status Colors:
```
🟨 YELLOW (Pending)
├─ Color: #ffc107 or similar
├─ Meaning: Waiting / Needs attention
└─ Use: Payment pending approval

🔵 BLUE (Approved)
├─ Color: #007bff or similar
├─ Meaning: Approved / Ready
└─ Use: Payment approved by admin

🟩 GREEN (Paid/Active)
├─ Color: #28a745 or similar
├─ Meaning: Complete / Active
└─ Use: Payment paid or affiliate active

⚪ GREY/NEUTRAL
├─ Color: #6c757d or similar
├─ Meaning: Default / Informational
└─ Use: General information
```

---

## ⌨️ Keyboard Shortcuts

```
Not specific shortcuts, but helpful keys:

Ctrl+F5    → Hard refresh (clear cache)
F12        → Open browser console (for debugging)
Tab        → Navigate between form elements
Enter      → Submit forms
Escape     → Close dialogs
```

---

## 💡 Pro Tips

### For Quick Workflow:
```
1. Master Dashboard → Overview
   See health metrics

2. Master Dashboard → Payments
   Approve pending payments

3. Master Dashboard → Products
   Check for spam products

4. Master Dashboard → Affiliates
   Monitor active users

5. Back to Overview
   Track changes
```

### For Debugging:
```
1. Press F12 → Open console
2. Look for red error messages
3. Copy error text
4. Check TROUBLESHOOTING_GUIDE.md

Alternative:
1. Check URL correct
2. Verify logged in
3. Hard refresh (Ctrl+F5)
4. Try again
```

---

## 🎯 Common Tasks Summary

| Task | Steps | Time |
|------|-------|------|
| Approve 1 payment | Master Admin → Payments → [Approve] | 10 sec |
| Delete product | Master Admin → Products → [Delete] → OK | 5 sec |
| Delete affiliate | Master Admin → Affiliates → [Delete] → OK | 5 sec |
| View all sales | Master Admin → Overview → See metric | 2 sec |
| Find product | Master Admin → Products → Search → Type | 5 sec |
| Check metrics | Master Admin → Overview | 2 sec |

---

## ✨ Summary

This guide shows you:
- ✅ How to access Master Admin Panel
- ✅ What each tab shows
- ✅ How to approve payments
- ✅ How to search and delete
- ✅ How affiliate sees updates
- ✅ Mobile compatibility
- ✅ Color meanings

**Everything is visual and easy to follow!** 

For more details, read: `ADMIN_QUICK_GUIDE.md`

