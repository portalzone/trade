# 📡 T-Trade API Reference

Complete API documentation for T-Trade marketplace platform.

**Base URL:** `http://localhost:8000/api`  
**Authentication:** Bearer Token (JWT via Laravel Sanctum)

---

## 📋 **Table of Contents**

1. [Authentication](#authentication)
2. [KYC & Verification](#kyc--verification)
3. [Storefronts](#storefronts)
4. [Products](#products)
5. [Categories](#categories)
6. [Reviews & Ratings](#reviews--ratings)
7. [Search](#search)
8. [Wishlist & Features](#wishlist--features)
9. [Orders](#orders)
10. [Payments](#payments)
11. [Escrow](#escrow)
12. [Disputes](#disputes)
13. [Bulk Operations](#bulk-operations)
14. [Admin](#admin)

---

## 🔐 **Authentication**

All authenticated endpoints require a Bearer token in the header:
```
Authorization: Bearer {your-token-here}
```

### **POST /api/auth/register**
Register a new user.

**Request:**
```json
{
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone_number": "+2348012345678",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "user_type": "BUYER"
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Doe",
      "email": "john@example.com",
      "user_type": "BUYER",
      "kyc_tier": 0
    },
    "token": "1|abcdef123456..."
  }
}
```

### **POST /api/auth/login**
Authenticate user.

**Request:**
```json
{
  "email": "john@example.com",
  "password": "SecurePass123!"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Doe",
      "email": "john@example.com",
      "kyc_tier": 1
    },
    "token": "2|xyz789..."
  }
}
```

### **POST /api/auth/logout**
Logout user (revoke token).

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

### **GET /api/auth/me**
Get authenticated user details.

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "id": 1,
    "full_name": "John Doe",
    "email": "john@example.com",
    "kyc_tier": 2,
    "account_status": "ACTIVE"
  }
}
```

---

## 🔍 **KYC & Verification**

### **POST /api/kyc/tier1**
Submit Tier 1 KYC (NIN/BVN verification).

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "nin": "12345678901",
  "bvn": "22334455667",
  "date_of_birth": "1990-01-15",
  "address": "123 Main St, Lagos"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Tier 1 KYC submitted successfully",
  "data": {
    "kyc_tier": 1,
    "nin_verified": true,
    "bvn_verified": true
  }
}
```

### **POST /api/kyc/tier2**
Submit Tier 2 KYC (Enhanced verification).

**Headers:** `Authorization: Bearer {token}`

**Request:** `multipart/form-data`
```
business_name: Tech Solutions Ltd
business_type: LIMITED_COMPANY
registration_number: RC123456
tax_id: TIN987654
proof_of_address: [File]
business_registration: [File]
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Tier 2 KYC submitted",
  "data": {
    "kyc_tier": 2,
    "status": "PENDING"
  }
}
```

### **POST /api/tier3/beneficial-owners**
Submit UBO information (Tier 3).

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "owners": [
    {
      "full_name": "Jane Smith",
      "nationality": "NG",
      "ownership_percentage": 60,
      "date_of_birth": "1985-03-20",
      "id_number": "12345678901",
      "address": "456 Business Ave, Lagos"
    }
  ]
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "1 beneficial owner(s) submitted",
  "data": {
    "total_percentage": 60,
    "owners_count": 1
  }
}
```

---

## 🏪 **Storefronts**

### **POST /api/storefront**
Create seller storefront.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "name": "Tech Haven Store",
  "description": "Your one-stop shop for electronics",
  "subdomain": "tech-haven",
  "phone": "08012345678",
  "email": "store@techhaven.com",
  "address": "45 Computer Village, Ikeja",
  "city": "Lagos",
  "state": "Lagos",
  "primary_color": "#3b82f6"
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Storefront created successfully",
  "data": {
    "id": 1,
    "name": "Tech Haven Store",
    "slug": "tech-haven-store",
    "subdomain": "tech-haven",
    "status": "active"
  }
}
```

### **GET /api/store/{slug}/products**
Get public storefront products (no auth required).

**Query Parameters:**
- `category` - Filter by category ID
- `featured` - Filter featured products (true/false)
- `min_price` - Minimum price
- `max_price` - Maximum price
- `sort` - Sort by: `newest`, `price_low`, `price_high`, `popular`, `rating`
- `per_page` - Results per page (default: 24)

**Example:** `GET /api/store/tech-haven/products?sort=price_low&per_page=12`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "iPhone 15 Pro Max 256GB",
        "slug": "iphone-15-pro-max-256gb",
        "price": "850000.00",
        "stock_status": "in_stock",
        "average_rating": "5.00",
        "images": []
      }
    ],
    "total": 5
  }
}
```

---

## 📦 **Products**

### **POST /api/products**
Create a new product (seller only).

**Headers:** `Authorization: Bearer {token}`

**Request:** `multipart/form-data`
```
name: iPhone 15 Pro Max 256GB
description: Latest iPhone with A17 Pro chip...
price: 850000
compare_at_price: 950000
stock_quantity: 25
category_id: 2
is_featured: true
images[]: [File1, File2, File3]
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Product created successfully",
  "data": {
    "id": 1,
    "sku": "PRD-3BKI8YVK",
    "name": "iPhone 15 Pro Max 256GB",
    "slug": "iphone-15-pro-max-256gb",
    "price": "850000.00",
    "stock_status": "in_stock"
  }
}
```

### **GET /api/products/{id}**
Get single product details (auto-tracks view).

**Headers:** `X-Session-Id: {session-id}` (optional, for tracking)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "iPhone 15 Pro Max 256GB",
    "price": "850000.00",
    "description": "...",
    "stock_quantity": 25,
    "average_rating": "5.00",
    "reviews_count": 3,
    "category": {
      "id": 2,
      "name": "Smartphones"
    }
  }
}
```

### **PUT /api/products/{id}**
Update product (seller only).

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "price": 840000,
  "stock_quantity": 30,
  "is_featured": true
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Product updated successfully",
  "data": {
    "id": 1,
    "price": "840000.00",
    "stock_quantity": 30
  }
}
```

### **DELETE /api/products/{id}**
Delete product (seller only).

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Product deleted successfully"
}
```

### **GET /api/products/my**
Get seller's products.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `status` - Filter: `active`, `inactive`
- `stock_status` - Filter: `in_stock`, `low_stock`, `out_of_stock`
- `per_page` - Results per page (default: 20)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [/* products */],
    "total": 45
  }
}
```

---

## 📂 **Categories**

### **POST /api/categories**
Create category (seller only).

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "name": "Smartphones",
  "parent_id": 1,
  "description": "Latest smartphones from top brands",
  "icon": "📱",
  "sort_order": 1
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Category created successfully",
  "data": {
    "id": 2,
    "name": "Smartphones",
    "slug": "smartphones",
    "parent_id": 1
  }
}
```

### **GET /api/categories/my**
Get seller's categories (tree + flat).

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "tree": [
      {
        "id": 1,
        "name": "Electronics",
        "children": [
          {"id": 2, "name": "Smartphones"}
        ]
      }
    ],
    "flat": [/* all categories */]
  }
}
```

---

## ⭐ **Reviews & Ratings**

### **POST /api/products/{productId}/reviews**
Create product review.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "rating": 5,
  "title": "Amazing phone!",
  "comment": "Best purchase ever. Highly recommend!",
  "order_id": 123
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Review created successfully",
  "data": {
    "id": 1,
    "rating": 5,
    "is_verified_purchase": true,
    "helpful_count": 0
  }
}
```

### **GET /api/products/{productId}/reviews**
Get product reviews.

**Query Parameters:**
- `rating` - Filter by rating (1-5)
- `verified_only` - Show only verified purchases (true/false)
- `sort` - Sort by: `newest`, `helpful`, `rating_high`, `rating_low`
- `per_page` - Results per page (default: 10)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "rating": 5,
        "title": "Amazing phone!",
        "comment": "...",
        "is_verified_purchase": true,
        "helpful_count": 5,
        "user": {
          "full_name": "Jane Buyer"
        }
      }
    ]
  }
}
```

### **GET /api/products/{productId}/reviews/breakdown**
Get rating statistics.

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "average_rating": "4.67",
    "total_reviews": 12,
    "breakdown": {
      "5": {"count": 8, "percentage": 66.7},
      "4": {"count": 3, "percentage": 25.0},
      "3": {"count": 1, "percentage": 8.3}
    }
  }
}
```

### **POST /api/reviews/{id}/vote**
Vote review helpful/not helpful.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "is_helpful": true
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Vote recorded successfully"
}
```

---

## 🔍 **Search**

### **GET /api/search**
Advanced product search.

**Query Parameters:**
- `q` - Search query
- `category_id` - Filter by category
- `min_price` - Minimum price
- `max_price` - Maximum price
- `min_rating` - Minimum rating (1-5)
- `stock_status` - Filter: `in_stock`, `low_stock`, `out_of_stock`
- `in_stock_only` - Show only in stock (true/false)
- `featured` - Show only featured (true/false)
- `sort` - Sort by: `relevance`, `price_low`, `price_high`, `rating`, `popular`, `newest`
- `per_page` - Results per page (default: 24, max: 100)

**Example:** `GET /api/search?q=phone&min_price=100000&max_price=900000&sort=price_low`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [/* products */],
    "total": 15
  },
  "filters": {
    "counts": {
      "stock_status": {
        "in_stock": 12,
        "low_stock": 2,
        "out_of_stock": 1
      },
      "rating": {
        "5": 8,
        "4": 5,
        "3": 2
      },
      "price_ranges": {
        "under_100k": 3,
        "100k_500k": 7,
        "500k_1m": 4,
        "over_1m": 1
      }
    },
    "applied": {
      "search": "phone",
      "price_range": {"min": "100000", "max": "900000"}
    }
  }
}
```

### **GET /api/search/suggestions**
Get search autocomplete suggestions.

**Query Parameters:**
- `q` - Search query (min 2 chars)
- `limit` - Max suggestions (default: 10, max: 20)

**Example:** `GET /api/search/suggestions?q=iph`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro Max 256GB",
      "slug": "iphone-15-pro-max-256gb",
      "price": "850000.00",
      "image": null
    }
  ]
}
```

### **GET /api/search/price-range**
Get min/max prices for filtering.

**Query Parameters:**
- `category_id` - Optional category filter

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "min": 145000,
    "max": 1250000
  }
}
```

---

## ❤️ **Wishlist & Features**

### **POST /api/wishlist**
Add product to wishlist.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "product_id": 1
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Product added to wishlist"
}
```

### **GET /api/wishlist**
Get user's wishlist.

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro Max 256GB",
      "price": "850000.00",
      "stock_status": "in_stock"
    }
  ]
}
```

### **DELETE /api/wishlist/{productId}**
Remove from wishlist.

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Product removed from wishlist"
}
```

### **GET /api/recently-viewed**
Get recently viewed products.

**Headers:** `Authorization: Bearer {token}` (optional)  
**Headers:** `X-Session-Id: {session-id}` (for guests)

**Query Parameters:**
- `limit` - Max products (default: 20)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [/* recently viewed products */]
}
```

### **GET /api/best-sellers**
Get best selling products.

**Query Parameters:**
- `limit` - Max products (default: 10)
- `days` - Time period in days (default: 30)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [/* top selling products */]
}
```

### **GET /api/trending**
Get trending products (most viewed).

**Query Parameters:**
- `limit` - Max products (default: 10)
- `hours` - Time period in hours (default: 24)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [/* trending products */]
}
```

### **GET /api/top-rated**
Get top rated products.

**Query Parameters:**
- `limit` - Max products (default: 10)
- `min_rating` - Minimum rating (default: 4.0)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [/* top rated products */]
}
```

### **POST /api/compare**
Compare products.

**Request:**
```json
{
  "product_ids": [1, 2]
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro Max",
      "price": "850000.00",
      "rating": "5.00",
      "discount": 10.53,
      "weight": "221g",
      "dimensions": {"length": 159.9, "width": 76.7}
    },
    {
      "id": 2,
      "name": "Samsung Galaxy S24",
      "price": "780000.00",
      "rating": "4.00",
      "discount": 8.24
    }
  ]
}
```

---

## 🔄 **Bulk Operations**

### **POST /api/bulk/update**
Bulk update products (seller only).

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "updates": [
    {"id": 1, "price": 840000, "stock_quantity": 30},
    {"id": 2, "price": 770000, "is_featured": true}
  ]
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Bulk update completed",
  "data": {
    "updated": 2,
    "failed": 0,
    "errors": []
  }
}
```

### **POST /api/bulk/toggle-status**
Bulk activate/deactivate products.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "product_ids": [1, 2, 3],
  "is_active": false
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "3 products deactivated",
  "data": {"count": 3}
}
```

### **POST /api/bulk/price-adjustment**
Bulk price changes.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "product_ids": [4, 5],
  "type": "percentage_decrease",
  "value": 10
}
```

**Types:**
- `percentage_increase` - Increase by %
- `percentage_decrease` - Decrease by %
- `fixed_increase` - Add fixed amount
- `fixed_decrease` - Subtract fixed amount

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "2 products updated",
  "data": {"count": 2}
}
```

### **GET /api/bulk/export-csv**
Export products to CSV.

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK` (CSV file download)
```csv
ID,SKU,Name,Category,Price,Stock,Status
1,PRD-3BKI8YVK,"iPhone 15 Pro Max","Smartphones",850000,25,Active
```

### **POST /api/bulk/import-csv**
Import products from CSV.

**Headers:** `Authorization: Bearer {token}`

**Request:** `multipart/form-data`
```
file: [CSV File]
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Import completed",
  "data": {
    "imported": 15,
    "updated": 5,
    "failed": 1,
    "errors": [
      {"row": 12, "error": "Invalid SKU format"}
    ]
  }
}
```

### **GET /api/inventory/low-stock**
Get low stock alerts.

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "name": "MacBook Air M3",
      "stock_quantity": 4,
      "low_stock_threshold": 5
    }
  ]
}
```

---

## 💡 **Response Codes**

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

---

## 📝 **Error Response Format**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "price": ["The price must be at least 0."]
  }
}
```

---

**[← Back to Main README](../README.md)**
