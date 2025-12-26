#!/bin/bash

# T-Trade Platform - Quick Setup Script
# This script automates the initial setup process

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Banner
echo "
╔════════════════════════════════════════════════════════╗
║                                                        ║
║           T-Trade E-Commerce Platform                 ║
║           Automated Setup Script v1.0                 ║
║                                                        ║
╚════════════════════════════════════════════════════════╝
"

# Step 1: Check prerequisites
print_info "Checking prerequisites..."

if ! command_exists docker; then
    print_error "Docker is not installed. Please install Docker Desktop from https://www.docker.com/products/docker-desktop"
    exit 1
fi

if ! command_exists docker-compose; then
    print_error "Docker Compose is not installed. Please install it from https://docs.docker.com/compose/install/"
    exit 1
fi

print_info "✓ Docker is installed: $(docker --version)"
print_info "✓ Docker Compose is installed: $(docker-compose --version)"

# Step 2: Set up backend environment
print_info "Setting up backend environment..."

if [ ! -f backend/.env ]; then
    print_info "Creating backend/.env from .env.example..."
    cp backend/.env.example backend/.env
    
    # Generate a random app key
    APP_KEY=$(openssl rand -base64 32)
    sed -i.bak "s/APP_KEY=/APP_KEY=base64:${APP_KEY}/" backend/.env
    
    print_info "✓ Backend .env file created"
    print_warning "Please edit backend/.env and add your API keys:"
    print_warning "  - PAYSTACK_SECRET_KEY"
    print_warning "  - STRIPE_SECRET_KEY"
    print_warning "  - AWS credentials (if using S3)"
    echo ""
else
    print_warning "backend/.env already exists, skipping..."
fi

# Step 3: Set up frontend environment
print_info "Setting up frontend environment..."

if [ ! -f frontend/.env.local ]; then
    if [ -f frontend/.env.example ]; then
        print_info "Creating frontend/.env.local from .env.example..."
        cp frontend/.env.example frontend/.env.local
        print_info "✓ Frontend .env.local file created"
    else
        print_warning "frontend/.env.example not found, creating basic .env.local..."
        cat > frontend/.env.local <<EOF
NEXT_PUBLIC_API_URL=http://localhost:8000/api
NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=pk_test_xxxxxxxxxx
NEXT_PUBLIC_GOOGLE_MAPS_KEY=AIzaSyxxxxxxxxxx
EOF
        print_info "✓ Frontend .env.local file created"
    fi
else
    print_warning "frontend/.env.local already exists, skipping..."
fi

# Step 4: Install backend dependencies (if Composer is available locally)
if command_exists composer; then
    print_info "Installing backend dependencies..."
    cd backend
    composer install --no-interaction
    cd ..
    print_info "✓ Backend dependencies installed"
else
    print_warning "Composer not found locally. Dependencies will be installed inside Docker container."
fi

# Step 5: Install frontend dependencies (if Node is available locally)
if command_exists npm; then
    print_info "Installing frontend dependencies..."
    cd frontend
    npm install
    cd ..
    print_info "✓ Frontend dependencies installed"
else
    print_warning "npm not found locally. Dependencies will be installed inside Docker container."
fi

# Step 6: Start Docker containers
print_info "Starting Docker containers..."
docker-compose up -d

# Wait for services to be healthy
print_info "Waiting for services to be healthy (this may take up to 60 seconds)..."
sleep 10

MAX_RETRIES=12
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if docker-compose ps | grep -q "Up (healthy)"; then
        print_info "✓ Services are healthy"
        break
    fi
    
    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "  Waiting... ($RETRY_COUNT/$MAX_RETRIES)"
    sleep 5
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    print_warning "Services did not become healthy in time. Check logs with: docker-compose logs"
fi

# Step 7: Run database migrations
print_info "Running database migrations..."
docker-compose exec -T backend php artisan migrate --force

if [ $? -eq 0 ]; then
    print_info "✓ Database migrations completed"
else
    print_error "Database migrations failed. Check logs with: docker-compose logs backend"
    exit 1
fi

# Step 8: Seed database with test data
read -p "Do you want to seed the database with test data? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_info "Seeding database..."
    docker-compose exec -T backend php artisan db:seed
    print_info "✓ Database seeded with test accounts"
    echo ""
    print_info "Test accounts created:"
    echo "  Seller: seller1@test.com / password"
    echo "  Buyer:  buyer1@test.com / password"
    echo "  Admin:  admin@test.com / password"
    echo ""
fi

# Step 9: Verify installation
print_info "Verifying installation..."
sleep 3

HEALTH_CHECK=$(curl -s http://localhost:8000/api/health)
if echo "$HEALTH_CHECK" | grep -q '"status":"ok"'; then
    print_info "✓ Backend API is healthy"
else
    print_warning "Backend health check failed. Response: $HEALTH_CHECK"
fi

# Final summary
echo ""
echo "╔════════════════════════════════════════════════════════╗"
echo "║                   SETUP COMPLETE! 🎉                   ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""
print_info "Services are running:"
echo "  • Backend API:  http://localhost:8000"
echo "  • Frontend:     http://localhost:3000"
echo "  • PostgreSQL:   localhost:5432"
echo "  • Redis:        localhost:6379"
echo ""
print_info "Next steps:"
echo "  1. Edit backend/.env and add your API keys"
echo "  2. Access the API docs at http://localhost:8000/api/documentation"
echo "  3. Test the health endpoint: curl http://localhost:8000/api/health"
echo "  4. Open frontend at http://localhost:3000"
echo ""
print_info "Useful commands:"
echo "  • View logs:        docker-compose logs -f"
echo "  • Stop services:    docker-compose down"
echo "  • Restart services: docker-compose restart"
echo "  • Run migrations:   docker-compose exec backend php artisan migrate"
echo ""
print_info "For more information, see README.md"
echo ""
