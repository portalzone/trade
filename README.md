# T-Trade E-Commerce Platform

> A multi-channel, trust-based marketplace with automated escrow, real-time logistics tracking, and hyper-local food delivery.

**Version:** 1.0.0  
**Status:** 🚧 In Development - Phase 0  
**Target Market:** Nigeria → Global Expansion

---

## 🚀 Quick Start (5 Minutes)

### Prerequisites

Ensure you have installed:
- **Docker Desktop** (20.10+) & **Docker Compose** (2.0+)
- **Git**
- **Node.js** 22+ (optional - for local frontend development)
- **PHP** 8.3+ & **Composer** (optional - for local backend development)

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/your-org/t-trade-platform.git
cd t-trade-platform

# 2. Set up backend environment
cd backend
cp .env.example .env
# Edit .env and add your API keys (Paystack, Stripe, etc.)
composer install
cd ..

# 3. Set up frontend environment
cd frontend
cp .env.example .env.local
# Edit .env.local with your configuration
npm install
cd ..

# 4. Start all services with Docker Compose
docker-compose up -d

# 5. Wait for services to be healthy (~30 seconds)
docker-compose ps

# 6. Run database migrations
docker-compose exec backend php artisan migrate --seed

# 7. Generate application key
docker-compose exec backend php artisan key:generate

# 8. Access the application
# Backend API: http://localhost:8000
# Frontend: http://localhost:3000
# API Docs: http://localhost:8000/api/documentation
```

### Verify Installation

```bash
# Check health endpoint
curl http://localhost:8000/api/health

# Expected response:
# {
#   "status": "ok",
#   "database": "connected",
#   "redis": "connected",
#   "timestamp": "2025-12-25T12:00:00Z"
# }
```

---

## 📁 Project Structure

```
t-trade-platform/
├── backend/                    # Laravel Octane API
│   ├── app/
│   │   ├── Models/            # Eloquent models (User, Wallet, Order, etc.)
│   │   ├── Http/
│   │   │   ├── Controllers/   # API controllers
│   │   │   └── Middleware/    # Auth, rate limiting, CORS
│   │   ├── Services/          # Business logic (Escrow, Payment, KYC)
│   │   └── Jobs/              # Background jobs (Auto-release, notifications)
│   ├── database/
│   │   ├── migrations/        # Database schema
│   │   └── seeders/           # Test data
│   ├── routes/
│   │   └── api.php            # API routes
│   ├── tests/                 # PHPUnit tests
│   └── .env.example           # Environment configuration template
│
├── frontend/                   # Next.js Web App
│   ├── app/                   # Next.js 14 App Router
│   │   ├── (auth)/            # Authentication pages
│   │   ├── (buyer)/           # Buyer dashboard
│   │   ├── (seller)/          # Seller dashboard
│   │   └── (admin)/           # Admin dashboard
│   ├── components/            # Reusable React components
│   ├── lib/                   # Utilities, API clients
│   └── public/                # Static assets
│
├── mobile/                     # React Native App (Phase 1)
│   ├── src/
│   │   ├── screens/           # App screens
│   │   ├── components/        # Reusable components
│   │   └── navigation/        # Navigation setup
│   └── app.json               # Expo configuration
│
├── database/                   # Shared database scripts
│   └── init/                  # PostgreSQL initialization
│
├── nginx/                      # Nginx reverse proxy (production)
│   └── nginx.conf
│
├── docker-compose.yml          # Multi-container setup
└── README.md                   # This file
```

---

## 🛠️ Development Workflow

### Starting Development

```bash
# Start all services
docker-compose up -d

# Watch backend logs
docker-compose logs -f backend

# Watch frontend logs
docker-compose logs -f frontend
```

### Backend Development

```bash
# Enter backend container
docker-compose exec backend sh

# Run migrations
php artisan migrate

# Create a new migration
php artisan make:migration create_products_table

# Create a new controller
php artisan make:controller ProductController --api

# Create a new model with migration
php artisan make:model Product -m

# Run tests
php artisan test

# Run specific test
php artisan test --filter=AuthenticationTest

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Start queue worker
php artisan queue:work

# Monitor queues with Horizon
php artisan horizon
```

### Frontend Development

```bash
# Enter frontend directory
cd frontend

# Start dev server (if not using Docker)
npm run dev

# Build for production
npm run build

# Run tests
npm test

# Lint code
npm run lint

# Type check
npm run type-check
```

### Database Operations

```bash
# Access PostgreSQL
docker-compose exec postgres psql -U postgres -d t_trade

# Common SQL commands:
# \dt                  - List tables
# \d users             - Describe users table
# SELECT * FROM users; - Query users

# Backup database
docker-compose exec postgres pg_dump -U postgres t_trade > backup.sql

# Restore database
docker-compose exec -T postgres psql -U postgres t_trade < backup.sql
```

---

## 🧪 Testing

### Backend Tests

```bash
# Run all tests
docker-compose exec backend php artisan test

# Run with coverage
docker-compose exec backend php artisan test --coverage

# Run specific test suite
docker-compose exec backend php artisan test --testsuite=Feature

# Run specific test file
docker-compose exec backend php artisan test tests/Feature/AuthTest.php
```

### Frontend Tests

```bash
cd frontend

# Unit tests (Jest)
npm test

# E2E tests (Cypress or Playwright)
npm run test:e2e

# Component tests (Storybook)
npm run storybook
```

### Manual Testing

**Test Accounts (Created by Seeder):**

| User Type | Email | Password | Tier |
|-----------|-------|----------|------|
| Tier 1 Seller | seller1@test.com | password | 1 |
| Tier 2 Business | business@test.com | password | 2 |
| Buyer | buyer1@test.com | password | - |
| Admin | admin@test.com | password | - |
| Rider | rider1@test.com | password | - |

---

## 🔐 Security

### Environment Variables

**NEVER commit `.env` to version control!**

Required secrets:
- `APP_KEY` - Generate with `php artisan key:generate`
- `JWT_SECRET` - For API authentication
- `PAYSTACK_SECRET_KEY` - Sandbox key for testing
- `STRIPE_SECRET_KEY` - Sandbox key for testing
- `AWS_SECRET_ACCESS_KEY` - For S3 file uploads

### API Authentication

```bash
# Register a new user
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "phone_number": "+2348012345678",
    "password": "SecurePass123!",
    "full_name": "Test User",
    "username": "testuser",
    "user_type": "BUYER"
  }'

# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!"
  }'

# Response includes JWT token:
# {
#   "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
#   "user": { ... }
# }

# Use token in subsequent requests
curl http://localhost:8000/api/user \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

## 📊 Database Schema

### Core Tables

**Phase 1 (MVP):**
- `users` - All user accounts (buyers, sellers, admins, riders)
- `wallets` - Financial accounts (1:1 with users)
- `ledger_entries` - Double-entry accounting ledger (immutable)
- `escrow_vault` - Locked funds during transactions
- `orders` - Purchase orders
- `payments` - Payment records
- `disputes` - Dispute cases
- `evidence_uploads` - Dispute evidence (photos/videos)

**See [Database Schema Documentation](./docs/Database_Schema_Documentation.md) for complete details.**

### Key Principles

1. **Double-Entry Ledger** - Every transaction has debit + credit
2. **Immutable Ledger** - Entries are never updated/deleted
3. **Derived Balances** - Wallet balances calculated from ledger
4. **Soft Deletes** - User data retained for 10 years (compliance)

---

## 🚢 Deployment

### Production Deployment (Phase 1)

**Infrastructure Requirements:**
- **Compute**: 2 vCPUs, 4GB RAM (Laravel Octane)
- **Database**: PostgreSQL 16 (managed service recommended)
- **Cache**: Redis 7 (1GB RAM)
- **Storage**: AWS S3 or compatible (for evidence files)
- **CDN**: CloudFlare or similar

**Deployment Steps:**

```bash
# 1. Build production images
docker-compose -f docker-compose.prod.yml build

# 2. Push to registry
docker tag t-trade-backend:latest registry.example.com/t-trade-backend:v1.0.0
docker push registry.example.com/t-trade-backend:v1.0.0

# 3. Deploy to server
ssh user@production-server
docker-compose -f docker-compose.prod.yml up -d

# 4. Run migrations
docker-compose exec backend php artisan migrate --force

# 5. Clear caches
docker-compose exec backend php artisan optimize

# 6. Verify deployment
curl https://api.t-trade.com/health
```

---

## 📖 API Documentation

### Generating API Docs

```bash
# Generate Swagger/OpenAPI docs
php artisan l5-swagger:generate

# Access at: http://localhost:8000/api/documentation
```

### Core API Endpoints

**Authentication:**
- `POST /api/register` - Register new user
- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `POST /api/password/reset` - Request password reset

**Users:**
- `GET /api/user` - Get authenticated user
- `PUT /api/user` - Update profile
- `POST /api/user/verify-email` - Verify email
- `POST /api/user/verify-phone` - Verify phone (OTP)

**Wallets:**
- `GET /api/wallet` - Get wallet balance
- `GET /api/wallet/transactions` - Transaction history
- `POST /api/wallet/withdraw` - Request withdrawal

**Orders:**
- `POST /api/orders` - Create order
- `GET /api/orders/{id}` - Get order details
- `POST /api/orders/{id}/approve` - Approve order (buyer)
- `POST /api/orders/{id}/dispute` - Open dispute

**Payments:**
- `POST /api/payments/initialize` - Initialize payment
- `POST /api/webhooks/paystack` - Paystack webhook
- `POST /api/webhooks/stripe` - Stripe webhook

---

## 🐛 Troubleshooting

### Common Issues

**1. "Database connection refused"**
```bash
# Ensure PostgreSQL is running
docker-compose ps postgres

# Check logs
docker-compose logs postgres

# Restart service
docker-compose restart postgres
```

**2. "Port 8000 already in use"**
```bash
# Find process using port
lsof -i :8000  # macOS/Linux
netstat -ano | findstr :8000  # Windows

# Kill process or change port in docker-compose.yml
```

**3. "Composer install fails"**
```bash
# Clear cache
docker-compose exec backend composer clear-cache

# Try again
docker-compose exec backend composer install
```

**4. "Migration fails"**
```bash
# Rollback and retry
docker-compose exec backend php artisan migrate:rollback
docker-compose exec backend php artisan migrate

# Fresh migration (WARNING: deletes all data)
docker-compose exec backend php artisan migrate:fresh --seed
```

---

## 📞 Support

**Team Communication:**
- **Slack**: #t-trade-dev
- **GitHub Issues**: [Report bugs](https://github.com/your-org/t-trade-platform/issues)
- **Email**: dev@t-trade.com

**Daily Standup:** 9:00 AM WAT (15 min max)

**Sprint Reviews:** Every 2 weeks (Fridays, 3:00 PM WAT)

---

## 📝 Contributing

1. **Create feature branch**: `git checkout -b feature/user-wallet`
2. **Write tests** (TDD approach)
3. **Implement feature**
4. **Run tests**: `php artisan test`
5. **Lint code**: `./vendor/bin/php-cs-fixer fix`
6. **Commit**: `git commit -m "feat: add wallet balance display"`
7. **Push**: `git push origin feature/user-wallet`
8. **Create Pull Request** on GitHub
9. **Wait for CI** to pass
10. **Request review** from team
11. **Merge** when approved

**Commit Message Format:**
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `test:` Tests
- `refactor:` Code refactoring
- `style:` Code style (formatting)
- `chore:` Build/tooling

---

## 📅 Roadmap

### ✅ Phase 0: Foundation (Current)
- [x] Docker setup
- [x] Database schema (users, wallets, ledger)
- [ ] Authentication API
- [ ] Payment integration (Paystack/Stripe sandbox)
- [ ] Basic wallet operations

### 🚧 Phase 1: Core Marketplace MVP (Next)
- [ ] Order creation and tracking
- [ ] Escrow vault system
- [ ] Auto-release logic
- [ ] Basic dispute resolution
- [ ] Seller Tier 1 onboarding

### 📋 Phase 2: Business Growth
- [ ] Tier 2/3 seller onboarding
- [ ] Branded storefronts
- [ ] Logistics webhook integration
- [ ] Enhanced dispute mediation

### 🚀 Phase 3: T-Trade Express
- [ ] Express vendor onboarding
- [ ] Rider management
- [ ] Real-time GPS tracking
- [ ] Offline POD capture
- [ ] Fault-based dispute resolution

---

## 📜 License

Proprietary - All Rights Reserved

© 2025 T-Trade Platform. Unauthorized copying or distribution prohibited.

---

**Built with ❤️ in Nigeria 🇳🇬**

**Let's revolutionize e-commerce in Africa! 🚀**
# trade
