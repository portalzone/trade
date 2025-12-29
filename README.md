# 🛒 T-Trade - Multi-Vendor Marketplace Platform

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-blue.svg)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-blue.svg)](https://postgresql.org)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A comprehensive, production-ready multi-vendor e-commerce platform built with Laravel 11, featuring advanced KYC, escrow payments, and seller storefronts.

---

## 📋 **Table of Contents**

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Quick Start](#quick-start)
- [Documentation](#documentation)
- [API Endpoints](#api-endpoints)
- [Project Status](#project-status)
- [License](#license)

---

## ✨ **Features**

### **🔐 Advanced KYC & Compliance**
- **3-Tier KYC System** (Basic → Enhanced → Business)
- NIN/BVN verification (Nigeria)
- Ultimate Beneficial Owner (UBO) disclosure
- Sanctions screening (OFAC, UN, EU)
- Enhanced Due Diligence (EDD) for high-risk users
- Transaction limits by KYC tier

### **🏪 Seller Storefronts**
- Custom branded storefronts
- Product management with inventory tracking
- Hierarchical categories
- Bulk operations (update, pricing, CSV import/export)
- Low stock alerts
- Sales analytics

### **📦 Product Management**
- Full CRUD operations
- Multi-image uploads
- Product variants
- Stock tracking with auto-status updates
- Featured products
- SKU generation

### **⭐ Reviews & Ratings**
- 5-star rating system
- Text reviews with images
- Helpful voting
- Seller responses
- Verified purchase badges
- Rating breakdowns

### **🔍 Advanced Search**
- Full-text search
- Multi-filter system (price, category, rating, stock)
- 6 sort options (relevance, price, rating, popular, newest)
- Auto-suggestions
- Faceted search with counts

### **❤️ User Features**
- Wishlist/Favorites
- Recently viewed tracking
- Product comparison (up to 4 items)
- Best sellers
- Trending products
- Top rated products

### **💳 Payment & Escrow**
- Paystack integration
- Stripe integration
- Escrow system with inspection windows
- Secure payment links
- Multi-currency support

### **🚚 Order Management**
- Complete order lifecycle
- Delivery tracking
- Waybill generation (PDF)
- Dispute resolution
- Order history

### **🔒 Security**
- JWT authentication
- Role-based access control
- Rate limiting
- Audit logging
- SQL injection protection
- XSS protection

---

## 🛠️ **Tech Stack**

### **Backend**
- **Framework:** Laravel 11.x
- **PHP:** 8.3+
- **Server:** Laravel Octane (Swoole)
- **Database:** PostgreSQL 16
- **Cache/Queue:** Redis 7

### **Key Packages**
- `laravel/sanctum` - API authentication
- `spatie/laravel-activitylog` - Audit logging
- `intervention/image` - Image processing
- `barryvdh/laravel-dompdf` - PDF generation
- `league/flysystem-aws-s3-v3` - S3 storage
- `stripe/stripe-php` - Stripe payments
- `unicodeveloper/laravel-paystack` - Paystack payments

### **Infrastructure**
- **Containerization:** Docker & Docker Compose
- **Storage:** AWS S3
- **Email:** SendGrid
- **SMS:** Twilio / Termii
- **Monitoring:** Laravel Telescope & Horizon

---

## 🚀 **Quick Start**

### **Prerequisites**
- Docker Desktop
- Git
- Postman (optional, for API testing)

### **Installation**

1. **Clone the repository**
```bash
git clone https://github.com/portalzone/trade.git
cd trade
```

2. **Set up environment**
```bash
cd backend
cp .env.example .env
```

3. **Start Docker containers**
```bash
cd ..
docker-compose up -d
```

4. **Install dependencies & migrate**
```bash
docker exec t-trade-backend composer install
docker exec t-trade-backend php artisan key:generate
docker exec t-trade-backend php artisan migrate --seed
```

5. **Access the application**
- API: http://localhost:8000
- Database: localhost:5432
- Redis: localhost:6379

**Default Test Users:**
- Seller: `support@basepan.com` / `password`
- Buyer: `contact@basepan.com` / `password`
- Admin: `admin@basepan.com` / `password`

---

## 📚 **Documentation**

- **[API Reference](docs/API_REFERENCE.md)** - Complete API documentation
- **[Setup Guide](docs/SETUP_GUIDE.md)** - Detailed installation instructions
- **[Architecture](docs/ARCHITECTURE.md)** - System design & database schema
- **[Deployment](docs/DEPLOYMENT.md)** - Production deployment guide
- **[Postman Collection](docs/T-Trade-API.postman_collection.json)** - Ready-to-use API tests

---

## 🔗 **API Endpoints**

### **Authentication**
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `GET /api/auth/me` - Get authenticated user

### **Products**
- `GET /api/search` - Advanced product search
- `GET /api/products/{id}` - Get single product
- `GET /api/products/{id}/reviews` - Get product reviews
- `POST /api/products` - Create product (seller)
- `PUT /api/products/{id}` - Update product (seller)

### **Storefront**
- `GET /api/store/{slug}/products` - Public storefront
- `GET /api/store/{slug}/categories` - Storefront categories
- `POST /api/storefront` - Create storefront (seller)

### **Orders**
- `POST /api/orders` - Create order
- `GET /api/orders` - List orders
- `PUT /api/orders/{id}/status` - Update order status

### **Wishlist**
- `POST /api/wishlist` - Add to wishlist
- `GET /api/wishlist` - Get wishlist
- `DELETE /api/wishlist/{id}` - Remove from wishlist

**[View Full API Reference →](docs/API_REFERENCE.md)**

Total Endpoints: **100+**

---

## 📊 **Project Status**

### **Phase 2: Backend Development** ✅ **100% Complete**

**Completed Features:**
- ✅ Authentication & Authorization
- ✅ Multi-tier KYC System
- ✅ Seller Storefronts
- ✅ Product Management
- ✅ Reviews & Ratings
- ✅ Advanced Search
- ✅ Wishlist & User Features
- ✅ Bulk Operations
- ✅ Payment Integration
- ✅ Order Management
- ✅ Escrow System
- ✅ Dispute Resolution

**Development Stats:**
- **Days:** 27
- **API Endpoints:** 100+
- **Database Tables:** 30+
- **Services:** 23
- **Controllers:** 27
- **Models:** 32

### **Phase 3: Frontend Development** 🚧 **Coming Next**
- React + Vite setup
- Tailwind CSS styling
- Storefront UI
- Admin dashboard
- Mobile responsive design

---

## 🗄️ **Database Schema**

**Core Tables:** 30+

**Key Entities:**
- users
- storefronts
- storefront_products
- product_categories
- product_reviews
- orders
- payments
- escrow_locks
- disputes
- kyc_verifications
- beneficial_owners
- sanctions_screening_results

[View Full Schema →](docs/ARCHITECTURE.md#database-schema)

---

## 🔒 **Security**

- **Authentication:** JWT tokens via Laravel Sanctum
- **Authorization:** Role-based access control (RBAC)
- **Data Protection:** Encrypted sensitive data
- **Audit Trail:** Complete activity logging
- **Input Validation:** All inputs validated
- **SQL Injection:** Protected via Eloquent ORM
- **XSS Protection:** Built-in Laravel protection
- **CSRF Protection:** Token-based verification

---

## 🤝 **Contributing**

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## 📄 **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 📞 **Support**

For questions or support:
- **Email:** support@t-trade.com
- **Issues:** [GitHub Issues](https://github.com/portalzone/trade/issues)
- **Documentation:** [Full Docs](docs/)

---

## 🙏 **Acknowledgments**

Built with:
- [Laravel](https://laravel.com)
- [PostgreSQL](https://postgresql.org)
- [Redis](https://redis.io)
- [Docker](https://docker.com)

---

**Made with ❤️ for the marketplace community**
