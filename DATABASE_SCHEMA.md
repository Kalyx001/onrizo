# 🗄️ Onrizo Shop - Database Schema

## Database Information

**Database Name:** `onrizo_db`
**Charset:** UTF-8MB4 (supports emojis and special characters)
**Location:** XAMPP Local MySQL

---

## Tables Overview

### 1. **admins** - Admin User Accounts
Stores information about sellers/vendors who can manage products.

```sql
CREATE TABLE admins (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    county VARCHAR(100),
    subcounty VARCHAR(100),
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Columns:**
- `id` - Unique admin ID
- `name` - Admin's full name
- `email` - Admin's email (used for login)
- `username` - Admin's username (used for login)
- `password` - Hashed password (uses password_hash())
- `phone` - Admin's phone number (used in WhatsApp orders)
- `county` - Kenya county where admin is based
- `subcounty` - Subcounty within the county
- `reset_token` - Token for password reset functionality
- `reset_expires` - Expiration time for password reset token
- `created_at` - Account creation timestamp

**Current Data:** 1 admin account exists

---

### 2. **products** - Product Listings
Contains all products uploaded by admins.

```sql
CREATE TABLE products (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    admin_id INT(11) NOT NULL,
    name VARCHAR(255),
    category VARCHAR(100),
    price DECIMAL(10,2),
    description TEXT,
    image VARCHAR(500),
    images LONGTEXT,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id)
);
```

**Columns:**
- `id` - Unique product ID
- `admin_id` - ID of the admin who uploaded this product (FK to admins)
- `name` - Product name/title
- `category` - Product category (e.g., "Smartphones", "Laptops", "Tablets")
- `price` - Product price in KES (Kenyan Shilling)
- `description` - Product description (rich text from Quill editor)
- `image` - Main product image path (e.g., "uploads/IMG_123.jpg")
- `images` - JSON or serialized array of additional images
- `date_added` - When product was uploaded

**Categories Available:**
- Smartphones (Phones/parts)
- Smartwatches (Watches/Jewelery)
- Cameras
- Accessories
- Laptops (Computers/Laptops)
- Tablets
- Gaming
- Tech (Tech-Gadgets)
- Loan Items

**Current Data:** 0 products (ready for admin uploads)

---

### 3. **product_images** - Additional Product Images
Stores multiple images for each product.

```sql
CREATE TABLE product_images (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    product_id INT(11) NOT NULL,
    image_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
```

**Columns:**
- `id` - Unique image record ID
- `product_id` - ID of the product (FK to products)
- `image_path` - Path to the image file (e.g., "uploads/IMG_456.jpg")
- `created_at` - When image was added

**Purpose:** Allows products to have multiple images for gallery view

---

### 4. **orders** - Customer Orders
Stores order information from customers.

```sql
CREATE TABLE orders (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    order_code VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    items LONGTEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_status VARCHAR(50) DEFAULT 'Pending',
    order_status VARCHAR(50) DEFAULT 'Processing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Columns:**
- `id` - Unique order ID
- `order_code` - Unique order reference code
- `phone` - Customer's phone number (used for WhatsApp communication)
- `items` - JSON or serialized array of ordered items
- `amount` - Total order amount in KES
- `payment_status` - Status of payment:
  - `Pending` - Payment not yet received
  - `Paid` - Payment confirmed
  - `Failed` - Payment failed
  - `Cancelled` - Order cancelled
- `order_status` - Status of order fulfillment:
  - `Processing` - Order received, being prepared
  - `Shipped` - Order shipped
  - `Delivered` - Order delivered
  - `Returned` - Order returned
- `created_at` - When order was placed

**Current Data:** 0 orders

---

### 5. **payments** - Payment Records
Stores detailed payment transaction information.

```sql
CREATE TABLE payments (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(255),
    phone VARCHAR(20),
    amount DOUBLE,
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Columns:**
- `id` - Unique payment record ID
- `order_id` - Associated order code
- `phone` - Customer's phone number
- `amount` - Amount paid
- `status` - Payment status (from M-Pesa callback)
- `created_at` - Payment timestamp

**Purpose:** Detailed transaction log for accounting/reconciliation

---

## Data Flow

### Product Upload Flow
1. Admin logs in → `admins` table
2. Admin uploads product → `products` table (with admin_id)
3. Additional images uploaded → `product_images` table
4. Images stored in `/uploads` folder

### Order Flow
1. Customer browses products → fetches from `products` table
2. Customer adds items to cart → stored in browser localStorage
3. Customer proceeds to checkout
4. M-Pesa STK push initiated
5. Payment callback received → `payments` table updated
6. Order saved → `orders` table
7. Order visible in admin orders dashboard

---

## Important Relationships

```
admins (1) ──┬──→ (Many) products
             └──→ Products have images in product_images table

orders ────→ Contains items (JSON) with product_ids
payments ──→ Links to orders
```

---

## Database Statistics

| Table | Records | Purpose |
|-------|---------|---------|
| admins | 1 | Admin accounts |
| products | 0 | Product listings |
| product_images | 0 | Additional images |
| orders | 0 | Customer orders |
| payments | 0 | Payment logs |

---

## Adding Sample Data

### Add Sample Admin
```sql
INSERT INTO admins (name, email, username, password, phone, county, subcounty) 
VALUES (
    'John Doe',
    'john@example.com',
    'johndoe',
    '$2y$10$...',  -- Use password_hash() in PHP
    '254712345678',
    'Nairobi',
    'Westlands'
);
```

### Add Sample Products
```sql
INSERT INTO products (admin_id, name, category, price, description, image) 
VALUES 
(1, 'iPhone 14 Pro', 'Smartphones', 129999, 'Latest flagship phone', 'uploads/iphone.jpg'),
(1, 'MacBook Pro M2', 'Laptops', 199999, 'Powerful laptop', 'uploads/macbook.jpg'),
(1, 'iPad Air', 'Tablets', 89999, 'Premium tablet device', 'uploads/ipad.jpg');
```

---

## Queries Reference

### Get All Products
```sql
SELECT p.*, a.phone as whatsapp_number 
FROM products p 
LEFT JOIN admins a ON p.admin_id = a.id 
ORDER BY p.date_added DESC;
```

### Get Products by Category
```sql
SELECT * FROM products 
WHERE LOWER(category) LIKE '%smartphones%' 
ORDER BY date_added DESC;
```

### Get Orders for Admin
```sql
SELECT * FROM orders 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
ORDER BY created_at DESC;
```

### Get Payment Statistics
```sql
SELECT COUNT(*) as total_payments, SUM(amount) as total_amount 
FROM payments 
WHERE status = 'Paid';
```

---

## Backup & Maintenance

### Backup Database
```bash
cd c:\xampp\mysql\bin
.\mysqldump -u root onrizo_db > onrizo_backup.sql
```

### Restore from Backup
```bash
cd c:\xampp\mysql\bin
.\mysql -u root onrizo_db < onrizo_backup.sql
```

### Check Database Size
```sql
SELECT 
    SUM(ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2)) AS size_mb
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'onrizo_db';
```

---

## Notes

- All timestamps use CURRENT_TIMESTAMP for automatic timestamps
- Prices are stored as DECIMAL(10,2) for accuracy (avoids floating point errors)
- Images are stored as paths; actual files are in `/uploads` folder
- JSON fields (items) allow flexible order structure
- Foreign keys establish relationships between tables

---

## Security Considerations

- ✅ Passwords are hashed using PHP's password_hash()
- ✅ Prepared statements prevent SQL injection
- ✅ Input validation on all forms
- ⚠️ TODO: Add encryption for sensitive payment data
- ⚠️ TODO: Implement database audit logging
- ⚠️ TODO: Regular backups (automated)
