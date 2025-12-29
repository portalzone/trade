# 🏗️ T-Trade Architecture

System design, database schema, and technical architecture documentation.

---

## 📋 **Table of Contents**

1. [System Overview](#system-overview)
2. [Technology Stack](#technology-stack)
3. [Architecture Layers](#architecture-layers)
4. [Database Schema](#database-schema)
5. [Key Workflows](#key-workflows)
6. [Security Architecture](#security-architecture)
7. [Scalability Considerations](#scalability-considerations)

---

## 🎯 **System Overview**

T-Trade is a multi-vendor marketplace platform built on a monolithic backend architecture with a service-oriented design pattern. The system supports multiple user types (buyers, sellers, admins, riders) with role-based access control.

### **Architecture Pattern**
- **Pattern:** Monolithic with Service Layer
- **Style:** RESTful API
- **Communication:** JSON over HTTP/HTTPS
- **Authentication:** JWT via Laravel Sanctum

### **High-Level Architecture**
```
┌─────────────────────────────────────────────────────────────┐
│                        Client Layer                          │
│  (Web App / Mobile App / Third-party Integrations)          │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      │ HTTPS/JSON
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                    API Gateway Layer                         │
│              Laravel Octane (Swoole)                         │
│          ┌──────────────────────────────────┐               │
│          │   Authentication & Rate Limiting  │               │
│          └──────────────────────────────────┘               │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                  Application Layer                           │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ Controllers  │  │  Middleware  │  │  Validation  │     │
│  └──────┬───────┘  └──────────────┘  └──────────────┘     │
│         │                                                   │
│  ┌──────▼──────────────────────────────────────────────┐  │
│  │            Service Layer                             │  │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐  │  │
│  │  │  Auth   │ │ Product │ │ Payment │ │  Order  │  │  │
│  │  │ Service │ │ Service │ │ Service │ │ Service │  │  │
│  │  └─────────┘ └─────────┘ └─────────┘ └─────────┘  │  │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐  │  │
│  │  │   KYC   │ │ Escrow  │ │ Search  │ │ Dispute │  │  │
│  │  │ Service │ │ Service │ │ Service │ │ Service │  │  │
│  │  └─────────┘ └─────────┘ └─────────┘ └─────────┘  │  │
│  └──────────────────────────────────────────────────────┘  │
│         │                                                   │
│  ┌──────▼──────────────────────────────────────────────┐  │
│  │            Data Access Layer (Eloquent ORM)          │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                   Data Layer                                 │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  PostgreSQL  │  │    Redis     │  │   AWS S3     │     │
│  │  (Primary)   │  │ (Cache/Queue)│  │ (File Store) │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│                 External Services                             │
│                                                              │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐      │
│  │ Paystack │ │  Stripe  │ │  Dojah   │ │ SendGrid │      │
│  │(Payment) │ │(Payment) │ │  (KYC)   │ │  (Email) │      │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘      │
└──────────────────────────────────────────────────────────────┘
```

---

## 💻 **Technology Stack**

### **Backend Framework**
- **Laravel 11.x** - PHP framework
- **Laravel Octane** - High-performance application server (Swoole)
- **Laravel Sanctum** - API authentication
- **Laravel Horizon** - Queue monitoring
- **Laravel Telescope** - Debug & monitoring

### **Database**
- **PostgreSQL 16** - Primary relational database
  - ACID compliance
  - Full-text search
  - JSONB support
  - Advanced indexing

### **Caching & Queues**
- **Redis 7** - In-memory data store
  - Session storage
  - Cache layer
  - Queue backend
  - Rate limiting

### **File Storage**
- **AWS S3** - Object storage
  - Product images
  - KYC documents
  - Evidence uploads
  - Waybill PDFs

### **Email & SMS**
- **SendGrid** - Email delivery
- **Twilio/Termii** - SMS delivery

### **Payment Gateways**
- **Paystack** - Primary (Africa)
- **Stripe** - International

### **KYC/Verification**
- **Dojah** - NIN/BVN verification
- **Custom** - Sanctions screening

---

## 🏛️ **Architecture Layers**

### **1. Presentation Layer (Controllers)**

Controllers handle HTTP requests and responses. They delegate business logic to services.

**Key Controllers:**
- `AuthController` - Authentication
- `ProductController` - Product management
- `OrderController` - Order processing
- `PaymentController` - Payment handling
- `KYCController` - Verification
- `StorefrontController` - Storefront management

**Pattern:**
```php
// Thin controllers
public function create(Request $request)
{
    // 1. Validate input
    $validated = $request->validate([...]);
    
    // 2. Delegate to service
    $result = $this->service->create($validated);
    
    // 3. Return response
    return response()->json($result);
}
```

### **2. Business Logic Layer (Services)**

Services contain all business logic, validations, and workflows.

**Key Services:**
- `ProductService` - Product operations
- `OrderService` - Order lifecycle
- `EscrowService` - Escrow management
- `KYCService` - Verification workflows
- `PaymentService` - Payment processing
- `SearchService` - Advanced search
- `BulkOperationsService` - Bulk operations

**Pattern:**
```php
// Service with transactions
public function createOrder(array $data): Order
{
    DB::beginTransaction();
    try {
        $order = Order::create($data);
        $this->escrowService->lock($order);
        $this->auditService->log('order.created', $order);
        
        DB::commit();
        return $order;
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

### **3. Data Access Layer (Models)**

Eloquent ORM models with relationships and business methods.

**Key Models:**
- `User` - Users with roles
- `Storefront` - Seller storefronts
- `StorefrontProduct` - Products
- `Order` - Orders
- `Payment` - Payments
- `EscrowLock` - Escrow transactions
- `ProductReview` - Reviews

**Relationships:**
```php
// Storefront has many products
public function products()
{
    return $this->hasMany(StorefrontProduct::class);
}

// Product belongs to storefront
public function storefront()
{
    return $this->belongsTo(Storefront::class);
}
```

---

## 🗄️ **Database Schema**

### **Core Tables**

#### **Users & Authentication**
```sql
users
├── id (PK)
├── full_name
├── email (unique)
├── phone_number (unique)
├── password
├── user_type (BUYER, SELLER, RIDER, ADMIN)
├── kyc_status
├── kyc_tier (0, 1, 2, 3)
├── account_status
├── nin_verified
├── bvn_verified
└── timestamps
```

#### **Storefronts**
```sql
storefronts
├── id (PK)
├── user_id (FK → users.id)
├── name
├── slug (unique)
├── subdomain (unique)
├── description
├── logo_url
├── banner_url
├── primary_color
├── currency
├── phone
├── email
├── address
├── city
├── state
├── status (active, suspended)
├── is_verified
├── total_products
├── total_sales
├── total_revenue
├── average_rating
└── timestamps
```

#### **Products**
```sql
storefront_products
├── id (PK)
├── storefront_id (FK → storefronts.id)
├── category_id (FK → product_categories.id)
├── name
├── slug (unique)
├── sku (unique, auto-generated)
├── description
├── short_description
├── price
├── compare_at_price
├── cost_price
├── stock_quantity
├── low_stock_threshold
├── track_inventory
├── stock_status (in_stock, low_stock, out_of_stock)
├── images (JSON array)
├── variants (JSON)
├── weight
├── dimensions (JSON)
├── is_featured
├── is_active
├── views_count
├── sales_count
├── average_rating
├── reviews_count
├── published_at
└── timestamps, soft_deletes
```

#### **Categories**
```sql
product_categories
├── id (PK)
├── storefront_id (FK → storefronts.id)
├── parent_id (FK → product_categories.id, nullable)
├── name
├── slug
├── description
├── icon
├── sort_order
├── is_active
└── timestamps
```

#### **Reviews**
```sql
product_reviews
├── id (PK)
├── product_id (FK → storefront_products.id)
├── user_id (FK → users.id)
├── order_id (FK → orders.id, nullable)
├── rating (1-5)
├── title
├── comment
├── images (JSON array)
├── is_verified_purchase
├── is_approved
├── helpful_count
├── not_helpful_count
├── seller_response
├── seller_responded_at
├── approved_at
└── timestamps, soft_deletes

review_votes
├── id (PK)
├── review_id (FK → product_reviews.id)
├── user_id (FK → users.id)
├── is_helpful (boolean)
└── timestamps
```

#### **Orders**
```sql
orders
├── id (PK)
├── order_number (unique, auto-generated)
├── buyer_id (FK → users.id)
├── seller_id (FK → users.id)
├── product_id (FK → storefront_products.id)
├── quantity
├── unit_price
├── total_amount
├── delivery_fee
├── escrow_fee
├── platform_fee
├── status (PENDING, CONFIRMED, SHIPPED, etc.)
├── payment_status
├── delivery_address
├── delivery_city
├── delivery_state
├── tracking_number
├── rider_id (FK → users.id, nullable)
├── inspection_window_ends_at
├── delivered_at
├── completed_at
└── timestamps
```

#### **Payments**
```sql
payments
├── id (PK)
├── user_id (FK → users.id)
├── order_id (FK → orders.id, nullable)
├── payment_reference (unique)
├── amount
├── payment_method (paystack, stripe, wallet)
├── payment_gateway
├── status (pending, success, failed)
├── metadata (JSON)
├── paid_at
└── timestamps
```

#### **Escrow**
```sql
escrow_locks
├── id (PK)
├── order_id (FK → orders.id)
├── amount
├── status (locked, released, refunded)
├── locked_at
├── released_at
├── refunded_at
└── timestamps
```

#### **KYC**
```sql
kyc_verifications
├── id (PK)
├── user_id (FK → users.id)
├── tier (1, 2, 3)
├── nin
├── bvn
├── business_name
├── business_type
├── registration_number
├── tax_id
├── verification_status
├── verified_at
├── rejected_at
├── rejection_reason
└── timestamps

beneficial_owners
├── id (PK)
├── user_id (FK → users.id)
├── full_name
├── nationality
├── ownership_percentage
├── date_of_birth
├── id_type
├── id_number
├── address
└── timestamps

sanctions_screening_results
├── id (PK)
├── user_id (FK → users.id)
├── full_name
├── date_of_birth
├── screening_date
├── match_found
├── risk_level
├── lists_checked (JSON)
└── timestamps
```

#### **Wishlist & Features**
```sql
wishlists
├── id (PK)
├── user_id (FK → users.id)
├── product_id (FK → storefront_products.id)
└── timestamps
(unique: user_id, product_id)

recently_viewed
├── id (PK)
├── user_id (FK → users.id, nullable)
├── session_id (nullable)
├── product_id (FK → storefront_products.id)
├── viewed_at
└── timestamps
```

### **Database Indexes**

**Performance Optimizations:**
```sql
-- Products
CREATE INDEX idx_products_storefront ON storefront_products(storefront_id);
CREATE INDEX idx_products_category ON storefront_products(category_id);
CREATE INDEX idx_products_active ON storefront_products(is_active, published_at);
CREATE INDEX idx_products_search ON storefront_products USING gin(to_tsvector('english', name || ' ' || description));

-- Orders
CREATE INDEX idx_orders_buyer ON orders(buyer_id, created_at);
CREATE INDEX idx_orders_seller ON orders(seller_id, created_at);
CREATE INDEX idx_orders_status ON orders(status, created_at);

-- Reviews
CREATE INDEX idx_reviews_product ON product_reviews(product_id, is_approved);
CREATE INDEX idx_reviews_rating ON product_reviews(rating, created_at);

-- Wishlist
CREATE INDEX idx_wishlist_user ON wishlists(user_id);

-- Recently Viewed
CREATE INDEX idx_viewed_user ON recently_viewed(user_id, viewed_at);
CREATE INDEX idx_viewed_session ON recently_viewed(session_id, viewed_at);
```

### **Total Tables: 32**

**Categories:**
- Users & Auth: 5 tables
- Storefronts: 2 tables
- Products: 4 tables
- Orders: 3 tables
- Payments: 3 tables
- KYC: 5 tables
- Reviews: 2 tables
- Features: 2 tables
- Admin: 4 tables
- Misc: 2 tables

---

## 🔄 **Key Workflows**

### **1. User Registration & KYC**
```
User Registration
    ↓
Email Verification
    ↓
Tier 0 (Basic Access)
    ↓
Submit NIN/BVN → Tier 1 KYC
    ↓
Verification Success
    ↓
Tier 1 (₦100K limits)
    ↓
Submit Business Docs → Tier 2 KYC
    ↓
Enhanced Verification
    ↓
Tier 2 (₦500K limits)
    ↓
Submit UBO + Sanctions → Tier 3 KYC
    ↓
Full Compliance Check
    ↓
Tier 3 (Unlimited)
```

### **2. Order Lifecycle**
```
Buyer Places Order
    ↓
Payment Processed
    ↓
Funds Locked in Escrow
    ↓
Seller Confirms Order
    ↓
Seller Ships Order
    ↓
Tracking Number Added
    ↓
Rider Picks Up (if express)
    ↓
Order In Transit
    ↓
Delivered to Buyer
    ↓
Inspection Window (24-72 hours)
    ↓
Buyer Confirms/Disputes
    ↓
If Confirmed:
    - Escrow Released to Seller
    - Order Completed
    ↓
If Disputed:
    - Dispute Resolution Process
    - Evidence Collection
    - Admin Review
    - Resolution (Refund/Release)
```

### **3. Product Search Flow**
```
User Search Query
    ↓
SearchService::searchProducts()
    ↓
┌─────────────────────────────┐
│ Apply Filters:              │
│ - Full-text search          │
│ - Category filter           │
│ - Price range              │
│ - Rating filter            │
│ - Stock status             │
└─────────────────────────────┘
    ↓
┌─────────────────────────────┐
│ Apply Sorting:              │
│ - Relevance scoring         │
│ - Price (low/high)          │
│ - Rating                    │
│ - Popularity (sales/views)  │
│ - Newest                    │
└─────────────────────────────┘
    ↓
Paginate Results (24/page)
    ↓
Calculate Filter Counts
    ↓
Return Results + Facets
```

---

## 🔒 **Security Architecture**

### **Authentication & Authorization**

**Authentication Flow:**
```
1. User sends credentials
2. Verify credentials
3. Generate JWT token via Sanctum
4. Return token to client
5. Client sends token in headers
6. Middleware validates token
7. Request processed if valid
```

**Authorization:**
- Role-based access control (RBAC)
- Resource ownership checks
- Tier-based limits
- API rate limiting

### **Data Protection**

**Encryption:**
- Passwords: bcrypt (12 rounds)
- Sensitive data: Laravel encryption
- API tokens: Hashed in database
- HTTPS in production

**Input Validation:**
- All inputs validated via FormRequests
- SQL injection protection (Eloquent ORM)
- XSS protection (Laravel escaping)
- CSRF tokens for web routes

**Audit Trail:**
- All critical actions logged
- User activity tracking
- IP address recording
- Timestamp tracking

### **Rate Limiting**
```
60 requests/minute per IP (default)
Custom limits per endpoint
Token bucket algorithm
Redis-based tracking
```

---

## 📈 **Scalability Considerations**

### **Current Architecture (Single Server)**
- Handles ~1000 concurrent users
- ~100 requests/second
- Suitable for initial launch

### **Scaling Strategy**

**Phase 1: Vertical Scaling**
- Increase server resources
- Optimize database queries
- Add database indexes
- Implement aggressive caching

**Phase 2: Horizontal Scaling**
```
Load Balancer
    ↓
┌────────┬────────┬────────┐
│ App 1  │ App 2  │ App 3  │
└────────┴────────┴────────┘
         ↓
┌─────────────────────────┐
│  Redis Cluster          │
│  (Session/Cache)        │
└─────────────────────────┘
         ↓
┌─────────────────────────┐
│  PostgreSQL             │
│  (Read Replicas)        │
└─────────────────────────┘
```

**Phase 3: Microservices (Future)**
- Extract search service
- Extract payment service
- Extract media service
- Event-driven architecture

### **Performance Optimizations**

**Database:**
- Query optimization
- Eager loading relationships
- Database indexing
- Connection pooling

**Caching:**
- Redis for sessions
- Query result caching
- Full-page caching
- CDN for static assets

**Queue Processing:**
- Background jobs for emails
- Asynchronous notifications
- Batch processing
- Horizon for monitoring

---

**[← Back to Main README](../README.md)**
