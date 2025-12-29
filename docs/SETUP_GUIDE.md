# 🛠️ T-Trade Setup Guide

Complete installation and configuration guide for local development.

---

## 📋 **Table of Contents**

1. [Prerequisites](#prerequisites)
2. [Initial Setup](#initial-setup)
3. [Environment Configuration](#environment-configuration)
4. [Database Setup](#database-setup)
5. [Running the Application](#running-the-application)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

---

## ✅ **Prerequisites**

### **Required Software**
- **Docker Desktop** (v20.10+) - [Download](https://www.docker.com/products/docker-desktop)
- **Git** (v2.30+) - [Download](https://git-scm.com/downloads)
- **Code Editor** - VS Code recommended

### **Optional Tools**
- **Postman** - For API testing ([Download](https://www.postman.com/downloads/))
- **TablePlus/DBeaver** - Database GUI ([Download](https://tableplus.com/))
- **Redis Commander** - Redis GUI

### **System Requirements**
- **RAM:** 8GB minimum (16GB recommended)
- **Disk Space:** 10GB free
- **OS:** macOS, Windows 10/11, or Linux
- **Ports:** 8000, 5432, 6379 must be available

---

## 🚀 **Initial Setup**

### **1. Clone Repository**
```bash
# Clone from GitHub
git clone https://github.com/portalzone/trade.git
cd trade
```

### **2. Project Structure**
```
trade/
├── backend/                 # Laravel backend
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Models/
│   │   └── Services/
│   ├── config/
│   ├── database/
│   │   └── migrations/
│   ├── routes/
│   │   └── api.php
│   ├── .env.example
│   └── composer.json
├── docker-compose.yml       # Docker services
├── docs/                    # Documentation
└── README.md
```

---

## ⚙️ **Environment Configuration**

### **1. Copy Environment File**
```bash
cd backend
cp .env.example .env
```

### **2. Configure Environment Variables**

Edit `backend/.env` with your settings:

#### **Application Settings**
```env
APP_NAME="T-Trade"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
```

#### **Database Configuration**
```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=t_trade
DB_USERNAME=postgres
DB_PASSWORD=secret_change_in_production
```

> **Note:** These match the Docker Compose configuration.

#### **Redis Configuration**
```env
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=redis_password_change_in_production
```

#### **Queue & Cache**
```env
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
```

#### **Payment Gateways (Sandbox)**

**Paystack:**
```env
PAYSTACK_PUBLIC_KEY=pk_test_your_key_here
PAYSTACK_SECRET_KEY=sk_test_your_key_here
PAYSTACK_PAYMENT_URL=https://api.paystack.co
PAYSTACK_MERCHANT_EMAIL=your-email@example.com
```
Get keys from: https://dashboard.paystack.com/#/settings/developer

**Stripe:**
```env
STRIPE_PUBLISHABLE_KEY=pk_test_your_key_here
STRIPE_SECRET_KEY=sk_test_your_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_secret_here
```
Get keys from: https://dashboard.stripe.com/test/apikeys

#### **Email (SendGrid)**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.your_sendgrid_api_key
MAIL_FROM_ADDRESS=noreply@t-trade.com
MAIL_FROM_NAME="${APP_NAME}"
```
Get API key from: https://app.sendgrid.com/settings/api_keys

#### **AWS S3 (File Storage)**
```env
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=t-trade-dev
AWS_USE_PATH_STYLE_ENDPOINT=false
```
Create bucket at: https://s3.console.aws.amazon.com/

#### **KYC Verification (Dojah)**
```env
DOJAH_API_KEY=your_dojah_api_key
DOJAH_APP_ID=your_dojah_app_id
DOJAH_PUBLIC_KEY=your_dojah_public_key
DOJAH_BASE_URL=https://api.dojah.io
```
Sign up at: https://dojah.io/

### **3. Generate Application Key**

This will be done after containers are running (see step 5).

---

## 🐳 **Database Setup**

### **1. Start Docker Containers**
```bash
# From project root directory
docker-compose up -d
```

This starts:
- **Backend** (Laravel Octane) - Port 8000
- **PostgreSQL** - Port 5432
- **Redis** - Port 6379

### **2. Verify Containers**
```bash
docker ps
```

You should see 3 running containers:
- `t-trade-backend`
- `t-trade-postgres`
- `t-trade-redis`

### **3. Install Dependencies**
```bash
docker exec t-trade-backend composer install
```

### **4. Generate Application Key**
```bash
docker exec t-trade-backend php artisan key:generate
```

### **5. Run Migrations**
```bash
docker exec t-trade-backend php artisan migrate
```

This creates 30+ database tables.

### **6. Seed Database (Optional)**
```bash
docker exec t-trade-backend php artisan db:seed
```

This creates:
- Test users (seller, buyer, admin)
- Sample products
- Categories

**Test User Credentials:**
```
Seller: support@basepan.com / password
Buyer: contact@basepan.com / password
Admin: admin@basepan.com / password
```

---

## 🏃 **Running the Application**

### **Start Application**
```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f backend
```

### **Access Points**

- **API:** http://localhost:8000
- **Health Check:** http://localhost:8000/api/health
- **PostgreSQL:** localhost:5432
- **Redis:** localhost:6379

### **Stop Application**
```bash
docker-compose down
```

### **Restart Services**
```bash
docker-compose restart
```

### **View Container Logs**
```bash
# All logs
docker-compose logs -f

# Backend only
docker-compose logs -f backend

# Last 100 lines
docker-compose logs --tail=100 backend
```

---

## 🧪 **Testing**

### **1. Test API Endpoint**
```bash
curl http://localhost:8000/api/health
```

Expected response:
```json
{
  "status": "healthy",
  "timestamp": "2025-12-28T..."
}
```

### **2. Test Authentication**

**Register User:**
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "full_name": "Test User",
    "email": "test@example.com",
    "phone_number": "+2348012345678",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "user_type": "BUYER"
  }'
```

**Login:**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!"
  }'
```

Save the returned token for authenticated requests.

### **3. Test Database Connection**
```bash
# Access PostgreSQL
docker exec -it t-trade-postgres psql -U postgres -d t_trade

# List tables
\dt

# Count users
SELECT COUNT(*) FROM users;

# Exit
\q
```

### **4. Test Redis Connection**
```bash
# Access Redis CLI
docker exec -it t-trade-redis redis-cli -a redis_password_change_in_production

# Test command
PING

# Exit
exit
```

### **5. Import Postman Collection**

1. Open Postman
2. Import `docs/T-Trade-API.postman_collection.json`
3. Create environment with variables:
   - `base_url`: http://localhost:8000
   - `token`: (will be set after login)
4. Run collection tests

---

## 🐛 **Troubleshooting**

### **Port Already in Use**

**Error:** `Bind for 0.0.0.0:8000 failed: port is already allocated`

**Solution:**
```bash
# Find process using port
lsof -i :8000

# Kill process (replace PID)
kill -9 <PID>

# Or change port in docker-compose.yml
ports:
  - "8001:8000"  # Use 8001 instead
```

### **Database Connection Failed**

**Error:** `SQLSTATE[08006] Connection refused`

**Solution:**
```bash
# Check if PostgreSQL is running
docker ps | grep postgres

# Restart PostgreSQL
docker-compose restart postgres

# Check logs
docker-compose logs postgres
```

### **Migration Failed**

**Error:** `Migration table not found`

**Solution:**
```bash
# Reset database
docker exec t-trade-backend php artisan migrate:fresh

# If that fails, recreate database
docker exec -it t-trade-postgres psql -U postgres
DROP DATABASE t_trade;
CREATE DATABASE t_trade;
\q

# Run migrations again
docker exec t-trade-backend php artisan migrate
```

### **Composer Install Failed**

**Error:** `The requested PHP extension ext-... is missing`

**Solution:**
```bash
# Rebuild Docker image
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Install dependencies
docker exec t-trade-backend composer install
```

### **Redis Connection Failed**

**Error:** `Connection refused [tcp://redis:6379]`

**Solution:**
```bash
# Check Redis status
docker ps | grep redis

# Restart Redis
docker-compose restart redis

# Test connection
docker exec t-trade-redis redis-cli -a redis_password_change_in_production PING
```

### **Permission Errors**

**Error:** `Permission denied` when accessing storage

**Solution:**
```bash
# Fix permissions
docker exec t-trade-backend chmod -R 775 storage bootstrap/cache
docker exec t-trade-backend chown -R www-data:www-data storage bootstrap/cache
```

### **Clear Cache**

If you encounter strange behavior:
```bash
# Clear all caches
docker exec t-trade-backend php artisan cache:clear
docker exec t-trade-backend php artisan config:clear
docker exec t-trade-backend php artisan route:clear
docker exec t-trade-backend php artisan view:clear

# Optimize
docker exec t-trade-backend php artisan optimize
```

### **Check Laravel Logs**
```bash
# View Laravel logs
docker exec t-trade-backend tail -f storage/logs/laravel.log

# Or from host
tail -f backend/storage/logs/laravel.log
```

---

## 🔧 **Advanced Configuration**

### **Queue Workers**

Start queue workers for background jobs:
```bash
docker exec t-trade-backend php artisan queue:work
```

### **Laravel Horizon**

Monitor queues via Horizon:
```bash
docker exec t-trade-backend php artisan horizon
```

Access at: http://localhost:8000/horizon

### **Laravel Telescope**

Monitor requests and logs:

Access at: http://localhost:8000/telescope

### **Database GUI Connection**

**TablePlus/DBeaver Settings:**
```
Host: localhost
Port: 5432
Database: t_trade
User: postgres
Password: secret_change_in_production
```

---

## 📝 **Useful Commands**

### **Laravel Artisan**
```bash
# Run any artisan command
docker exec t-trade-backend php artisan [command]

# Common commands
php artisan migrate              # Run migrations
php artisan migrate:fresh       # Reset & re-run migrations
php artisan db:seed            # Seed database
php artisan route:list         # List all routes
php artisan tinker             # Interactive shell
php artisan make:model User    # Create model
php artisan make:controller    # Create controller
php artisan optimize:clear     # Clear all caches
```

### **Docker Compose**
```bash
docker-compose up -d           # Start services
docker-compose down           # Stop services
docker-compose restart        # Restart services
docker-compose logs -f        # View logs
docker-compose ps             # List running services
docker-compose build          # Rebuild images
docker-compose exec backend bash  # Access container shell
```

---

## 🎯 **Next Steps**

1. ✅ Application running
2. ✅ Database seeded
3. ✅ Test users created

**Ready to develop!**

- [API Reference](API_REFERENCE.md) - Learn all endpoints
- [Architecture Guide](ARCHITECTURE.md) - Understand the system
- [Deployment Guide](DEPLOYMENT.md) - Deploy to production

---

**[← Back to Main README](../README.md)**
