# 🔧 Quick Fix - Complete Laravel Setup

## What Happened?

The Composer installation succeeded ✅ (125 packages installed!), but Laravel's base files weren't created yet. This is a simple fix.

## Fix in 3 Steps:

### Step 1: Run the Fix Script

```bash
chmod +x fix-laravel.sh
./fix-laravel.sh
```

This will:
- ✅ Create Laravel's `artisan` file
- ✅ Set up bootstrap files
- ✅ Create config files
- ✅ Set up storage directories
- ✅ Generate application key

### Step 2: Start Docker Services

```bash
docker-compose up -d
```

Wait ~30 seconds for services to start.

### Step 3: Run Database Migrations

```bash
# Run migrations
docker-compose exec backend php artisan migrate

# Seed test data (optional but recommended)
docker-compose exec backend php artisan db:seed
```

## Verify It Works

```bash
# Test health endpoint
curl http://localhost:8000/api/health

# Expected output:
# {
#   "status": "ok",
#   "database": "connected",
#   "redis": "connected"
# }
```

## Test Accounts (After Seeding)

| Role | Email | Password |
|------|-------|----------|
| Seller (Tier 1) | seller1@test.com | password |
| Business (Tier 2) | business@test.com | password |
| Buyer | buyer1@test.com | password |
| Rider | rider1@test.com | password |
| Admin | admin@test.com | password |
| Express Vendor | vendor1@test.com | password |

## Test the API

### Register a New User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "phone_number": "+2348012345678",
    "password": "SecurePass123!",
    "full_name": "Test User",
    "username": "testuser",
    "user_type": "BUYER"
  }'
```

### Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "seller1@test.com",
    "password": "password"
  }'
```

You'll get a JWT token back. Use it like this:

```bash
# Get user profile
curl http://localhost:8000/api/user \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"

# Get wallet balance
curl http://localhost:8000/api/wallet \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## What's Next?

Once everything is running:

1. **Explore the API** - Try all the endpoints
2. **Check the database** - Connect to PostgreSQL and view the tables
3. **Start Phase 1 development** - Build orders and escrow system

## Useful Commands

```bash
# View logs
docker-compose logs -f backend
docker-compose logs -f frontend

# Access backend container
docker-compose exec backend sh

# Run tests
docker-compose exec backend php artisan test

# View routes
docker-compose exec backend php artisan route:list

# Clear cache
docker-compose exec backend php artisan cache:clear
```

## Still Having Issues?

### "Migration fails"
```bash
# Drop all tables and start fresh
docker-compose exec backend php artisan migrate:fresh --seed
```

### "Port 8000 already in use"
```bash
# Change the port in docker-compose.yml
# Under backend service:
ports:
  - "8001:8000"  # Use 8001 instead
```

### "Database connection refused"
```bash
# Restart PostgreSQL
docker-compose restart postgres

# Wait 10 seconds, then try again
docker-compose exec backend php artisan migrate
```

---

**You're almost there! Just run the fix script and you'll be up and running! 🚀**
