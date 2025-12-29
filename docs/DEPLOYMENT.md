# 🚀 T-Trade Deployment Guide

Production deployment instructions from shared hosting to cloud platforms.

---

## 📋 **Table of Contents**

1. [Deployment Overview](#deployment-overview)
2. [Pre-Deployment Checklist](#pre-deployment-checklist)
3. [Environment Preparation](#environment-preparation)
4. [Option 1: VPS Deployment (DigitalOcean/Linode)](#option-1-vps-deployment)
5. [Option 2: Cloud Deployment (AWS/GCP)](#option-2-cloud-deployment)
6. [Option 3: Shared Hosting (Hostinger - Testing)](#option-3-shared-hosting-hostinger)
7. [Database Migration](#database-migration)
8. [SSL/HTTPS Setup](#ssl-https-setup)
9. [Performance Optimization](#performance-optimization)
10. [Monitoring & Maintenance](#monitoring--maintenance)

---

## 🎯 **Deployment Overview**

### **Recommended Deployment Path**
```
Development (Local)
    ↓
Testing (Hostinger Shared - Short-term)
    ↓
Staging (VPS - Testing)
    ↓
Production (Cloud/VPS - Launch)
```

### **Minimum Server Requirements**

**For Production Launch:**
- **CPU:** 4+ cores
- **RAM:** 8GB minimum (16GB recommended)
- **Storage:** 50GB SSD
- **Bandwidth:** Unmetered or 5TB+
- **OS:** Ubuntu 22.04 LTS

**Recommended Providers:**
- **VPS:** DigitalOcean ($40/month), Linode ($36/month)
- **Cloud:** AWS, Google Cloud, Azure
- **Budget:** Vultr ($24/month), Hetzner (€20/month)

---

## ✅ **Pre-Deployment Checklist**

### **1. Code Preparation**

- [ ] All tests passing
- [ ] No debug code in production
- [ ] Environment variables configured
- [ ] Database migrations tested
- [ ] Assets compiled/optimized
- [ ] Git repository clean

### **2. Environment Variables**

Create production `.env`:
```bash
# Copy and modify
cp .env.example .env.production
```

**Critical Settings:**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Strong keys
APP_KEY=base64:...  # Generate new!
JWT_SECRET=...      # Generate new!

# Production database
DB_HOST=your-db-host
DB_DATABASE=t_trade_prod
DB_USERNAME=prod_user
DB_PASSWORD=strong_password_here

# Production Redis
REDIS_PASSWORD=strong_redis_password

# Real API keys (NOT sandbox)
PAYSTACK_SECRET_KEY=sk_live_...
STRIPE_SECRET_KEY=sk_live_...
```

### **3. Security Checklist**

- [ ] `APP_DEBUG=false`
- [ ] Strong database passwords
- [ ] HTTPS/SSL enabled
- [ ] Firewall configured
- [ ] Rate limiting enabled
- [ ] CORS configured
- [ ] API keys rotated
- [ ] Backup system tested

### **4. Services Configuration**

**Required External Services:**
- AWS S3 bucket (production)
- SendGrid account (production)
- Paystack live keys
- Stripe live keys
- Dojah production API
- Domain name & DNS

---

## 🖥️ **Option 1: VPS Deployment**

Recommended for: **Full control, best performance, scalable**

### **Step 1: Server Setup (DigitalOcean Example)**

**Create Droplet:**
1. Go to DigitalOcean
2. Create Droplet
   - **Image:** Ubuntu 22.04 LTS
   - **Plan:** $40/month (8GB RAM, 4 vCPUs)
   - **Datacenter:** Choose closest to users
   - **SSH Key:** Add your public key

**Initial Server Access:**
```bash
ssh root@your-server-ip
```

### **Step 2: Install Dependencies**
```bash
# Update system
apt update && apt upgrade -y

# Install required packages
apt install -y nginx postgresql postgresql-contrib redis-server \
    php8.3 php8.3-fpm php8.3-pgsql php8.3-redis php8.3-mbstring \
    php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd \
    composer git unzip curl

# Install Node.js (for future frontend)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Install Swoole for Octane
pecl install swoole
echo "extension=swoole.so" > /etc/php/8.3/mods-available/swoole.ini
phpenmod swoole
```

### **Step 3: Configure PostgreSQL**
```bash
# Switch to postgres user
sudo -u postgres psql

# Create database and user
CREATE DATABASE t_trade_prod;
CREATE USER t_trade_user WITH PASSWORD 'your_strong_password';
GRANT ALL PRIVILEGES ON DATABASE t_trade_prod TO t_trade_user;
\q

# Configure PostgreSQL for remote access (if needed)
nano /etc/postgresql/15/main/postgresql.conf
# Change: listen_addresses = 'localhost'

nano /etc/postgresql/15/main/pg_hba.conf
# Add: host    t_trade_prod    t_trade_user    127.0.0.1/32    md5

# Restart PostgreSQL
systemctl restart postgresql
```

### **Step 4: Configure Redis**
```bash
# Set password
nano /etc/redis/redis.conf

# Add/modify:
requirepass your_strong_redis_password
maxmemory 2gb
maxmemory-policy allkeys-lru

# Restart Redis
systemctl restart redis-server
```

### **Step 5: Deploy Application**
```bash
# Create application directory
mkdir -p /var/www/t-trade
cd /var/www/t-trade

# Clone repository
git clone https://github.com/portalzone/trade.git .

# Set permissions
chown -R www-data:www-data /var/www/t-trade
chmod -R 775 storage bootstrap/cache

# Install dependencies
cd backend
composer install --no-dev --optimize-autoloader

# Copy environment file
cp .env.production .env

# Generate keys
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Link storage
php artisan storage:link
```

### **Step 6: Configure Nginx**
```bash
nano /etc/nginx/sites-available/t-trade
```

**Nginx Configuration:**
```nginx
server {
    listen 80;
    server_name api.your-domain.com;
    root /var/www/t-trade/backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 20M;
}
```

**Enable site:**
```bash
ln -s /etc/nginx/sites-available/t-trade /etc/nginx/sites-enabled/
nginx -t
systemctl restart nginx
```

### **Step 7: Configure Octane**
```bash
# Install Supervisor
apt install -y supervisor

# Create Octane config
nano /etc/supervisor/conf.d/t-trade-octane.conf
```

**Supervisor Configuration:**
```ini
[program:t-trade-octane]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/t-trade/backend/artisan octane:start --server=swoole --host=127.0.0.1 --port=8000 --workers=4
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/t-trade/backend/storage/logs/octane.log
stopwaitsecs=3600
```

**Start Octane:**
```bash
supervisorctl reread
supervisorctl update
supervisorctl start t-trade-octane:*
```

**Update Nginx to use Octane:**
```nginx
location / {
    proxy_pass http://127.0.0.1:8000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection 'upgrade';
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
}
```

### **Step 8: Setup Queue Workers**
```bash
nano /etc/supervisor/conf.d/t-trade-worker.conf
```
```ini
[program:t-trade-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/t-trade/backend/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/t-trade/backend/storage/logs/worker.log
stopwaitsecs=3600
```
```bash
supervisorctl reread
supervisorctl update
supervisorctl start t-trade-worker:*
```

### **Step 9: Configure Firewall**
```bash
# Enable UFW
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable

# Check status
ufw status
```

---

## ☁️ **Option 2: Cloud Deployment (AWS)**

### **AWS EC2 Setup**

**1. Launch EC2 Instance:**
- **AMI:** Ubuntu 22.04 LTS
- **Instance Type:** t3.large (2 vCPU, 8GB RAM)
- **Storage:** 50GB gp3 SSD
- **Security Group:**
  - SSH (22) - Your IP only
  - HTTP (80) - Anywhere
  - HTTPS (443) - Anywhere

**2. RDS PostgreSQL:**
- **Engine:** PostgreSQL 16
- **Instance:** db.t3.medium
- **Storage:** 100GB
- **Backup:** Enabled (7 days retention)

**3. ElastiCache Redis:**
- **Engine:** Redis 7
- **Node Type:** cache.t3.medium
- **Cluster Mode:** Disabled

**4. S3 Bucket:**
- **Name:** t-trade-prod-media
- **Region:** Same as EC2
- **Versioning:** Enabled
- **Encryption:** Enabled

**5. Follow VPS steps above for application setup**

---

## 🏠 **Option 3: Shared Hosting (Hostinger - Testing Only)**

**⚠️ WARNING:** Shared hosting is NOT recommended for production. Use only for testing.

### **Limitations:**
- Limited PHP extensions
- No Octane/Swoole support
- Shared resources
- Performance issues
- Limited scalability

### **Setup Steps:**

**1. Upload Files:**
```bash
# Zip project
zip -r t-trade.zip backend/

# Upload via FTP/File Manager to public_html
```

**2. Extract & Configure:**
```bash
# In Hostinger File Manager or SSH
cd public_html
unzip t-trade.zip
mv backend/* .
rm -rf backend

# Set permissions
chmod -R 755 storage bootstrap/cache
```

**3. Database Setup:**
- Create MySQL database via cPanel
- Import database schema
- Update `.env` with credentials

**4. .htaccess Configuration:**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

**5. Composer Install:**
```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
```

**⚠️ Note:** You cannot use PostgreSQL, Redis, or Octane on shared hosting. Use MySQL and file cache instead.

---

## 🔄 **Database Migration**

### **From Development to Production**

**1. Export Development Data:**
```bash
# Export from local PostgreSQL
docker exec t-trade-postgres pg_dump -U postgres t_trade > backup.sql
```

**2. Import to Production:**
```bash
# Upload to server
scp backup.sql root@your-server:/tmp/

# Import on server
ssh root@your-server
psql -U t_trade_user -d t_trade_prod < /tmp/backup.sql
```

**3. Verify Migration:**
```bash
php artisan migrate:status
```

### **Zero-Downtime Migration**

For live databases:
```bash
# 1. Put site in maintenance mode
php artisan down

# 2. Run migrations
php artisan migrate --force

# 3. Clear caches
php artisan cache:clear
php artisan config:cache

# 4. Restart workers
supervisorctl restart t-trade-worker:*

# 5. Bring site back up
php artisan up
```

---

## 🔒 **SSL/HTTPS Setup**

### **Using Let's Encrypt (Free)**
```bash
# Install Certbot
apt install -y certbot python3-certbot-nginx

# Obtain certificate
certbot --nginx -d api.your-domain.com

# Auto-renewal is enabled by default
# Test renewal
certbot renew --dry-run
```

**Nginx will be automatically updated with SSL config.**

### **Force HTTPS**

Add to `.env`:
```env
APP_URL=https://api.your-domain.com
FORCE_HTTPS=true
```

---

## ⚡ **Performance Optimization**

### **1. OPcache Configuration**
```bash
nano /etc/php/8.3/fpm/conf.d/10-opcache.ini
```
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0
opcache.validate_timestamps=0  # Production only
opcache.fast_shutdown=1
```

### **2. PHP-FPM Tuning**
```bash
nano /etc/php/8.3/fpm/pool.d/www.conf
```
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

### **3. PostgreSQL Tuning**
```bash
nano /etc/postgresql/15/main/postgresql.conf
```
```ini
shared_buffers = 2GB
effective_cache_size = 6GB
maintenance_work_mem = 512MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 5242kB
min_wal_size = 1GB
max_wal_size = 4GB
```

### **4. Redis Tuning**

Already covered in VPS setup section.

### **5. Nginx Caching**
```nginx
# Add to http block
fastcgi_cache_path /var/cache/nginx levels=1:2 keys_zone=app_cache:100m inactive=60m;
fastcgi_cache_key "$scheme$request_method$host$request_uri";

# Add to location ~ \.php$ block
fastcgi_cache app_cache;
fastcgi_cache_valid 200 60m;
fastcgi_cache_bypass $http_cache_control;
add_header X-Cache-Status $upstream_cache_status;
```

---

## 📊 **Monitoring & Maintenance**

### **1. Application Monitoring**

**Laravel Telescope (Development):**
```bash
# Already installed
# Access: https://api.your-domain.com/telescope
```

**Laravel Horizon (Queue Monitoring):**
```bash
# Access: https://api.your-domain.com/horizon
```

### **2. Server Monitoring**

**Install monitoring tools:**
```bash
# Install htop for CPU/RAM
apt install -y htop

# Install iotop for disk I/O
apt install -y iotop

# Monitor PostgreSQL
apt install -y pgtop
```

**Recommended Services:**
- **Uptime Monitoring:** UptimeRobot (free)
- **Error Tracking:** Sentry
- **Performance:** New Relic / Datadog
- **Logs:** Papertrail / Loggly

### **3. Automated Backups**

**Database Backups:**
```bash
# Create backup script
nano /usr/local/bin/backup-db.sh
```
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/t-trade"
mkdir -p $BACKUP_DIR

# Backup PostgreSQL
pg_dump -U t_trade_user t_trade_prod | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Upload to S3 (optional)
aws s3 cp $BACKUP_DIR/db_$DATE.sql.gz s3://your-backup-bucket/

# Delete old backups (keep 7 days)
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +7 -delete
```
```bash
chmod +x /usr/local/bin/backup-db.sh

# Add to crontab
crontab -e

# Daily backup at 2 AM
0 2 * * * /usr/local/bin/backup-db.sh
```

### **4. Log Rotation**
```bash
nano /etc/logrotate.d/t-trade
```
```
/var/www/t-trade/backend/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### **5. Health Checks**
```bash
# Create health check endpoint (already exists)
# Monitor: https://api.your-domain.com/api/health

# Setup monitoring
curl -I https://api.your-domain.com/api/health
```

---

## 🚨 **Troubleshooting Production**

### **500 Internal Server Error**
```bash
# Check Laravel logs
tail -f /var/www/t-trade/backend/storage/logs/laravel.log

# Check Nginx logs
tail -f /var/log/nginx/error.log

# Check PHP-FPM logs
tail -f /var/log/php8.3-fpm.log
```

### **Database Connection Failed**
```bash
# Test connection
psql -U t_trade_user -d t_trade_prod -h localhost

# Check PostgreSQL status
systemctl status postgresql

# Check credentials in .env
```

### **Queue Not Processing**
```bash
# Check worker status
supervisorctl status t-trade-worker:*

# Restart workers
supervisorctl restart t-trade-worker:*

# Check worker logs
tail -f /var/www/t-trade/backend/storage/logs/worker.log
```

### **High Memory Usage**
```bash
# Check processes
htop

# Restart PHP-FPM
systemctl restart php8.3-fpm

# Restart Octane
supervisorctl restart t-trade-octane:*
```

---

## 📝 **Post-Deployment Checklist**

- [ ] SSL certificate installed and working
- [ ] All environment variables configured
- [ ] Database migrations successful
- [ ] Queue workers running
- [ ] Cron jobs configured
- [ ] Backups automated
- [ ] Monitoring tools active
- [ ] DNS records updated
- [ ] Test all critical API endpoints
- [ ] Load testing completed
- [ ] Error tracking configured
- [ ] Team notified of deployment

---

## 🎯 **Recommended Production Stack**
```
Domain: your-domain.com
├── Frontend: frontend.your-domain.com (Future)
├── API: api.your-domain.com
└── Admin: admin.your-domain.com (Future)

Infrastructure:
├── Application: DigitalOcean Droplet (8GB RAM)
├── Database: Managed PostgreSQL
├── Cache: Managed Redis
├── Storage: AWS S3
├── Email: SendGrid
├── SMS: Twilio
├── CDN: Cloudflare (Free tier)
├── Monitoring: UptimeRobot + Sentry
└── Backups: Automated daily to S3
```

**Estimated Monthly Cost:** $100-150/month

---

**[← Back to Main README](../README.md)**
