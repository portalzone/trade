# T-Trade Production Deployment Checklist

## Pre-Deployment

### Code Quality
- [ ] All tests passing
- [ ] No debug statements in code
- [ ] Environment variables configured
- [ ] Database migrations tested
- [ ] Seeders verified

### Security
- [ ] SSL certificates installed
- [ ] Security headers enabled
- [ ] Rate limiting configured
- [ ] CORS settings reviewed
- [ ] API keys rotated
- [ ] Sensitive data encrypted

### Performance
- [ ] Database indexes created (197 total)
- [ ] Redis cache configured
- [ ] Query optimization complete
- [ ] Full-text search enabled
- [ ] File upload limits set

### Monitoring
- [ ] Health checks tested (/api/health)
- [ ] Logging configured
- [ ] Error tracking enabled
- [ ] Performance monitoring ready
- [ ] Backup strategy in place

## Deployment Steps

### 1. Database
```bash
php artisan migrate --force
php artisan db:seed --class=MonitoringRulesSeeder
php artisan db:seed --class=RetentionPoliciesSeeder
```

### 2. Cache
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Permissions
```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 4. Services
```bash
supervisorctl restart all
service nginx restart
```

## Post-Deployment

### Verification
- [ ] Health check returns 200
- [ ] Database connection working
- [ ] Redis connection working
- [ ] File uploads functional
- [ ] Email sending works
- [ ] SMS sending works

### Testing
- [ ] Authentication flow
- [ ] KYC submission
- [ ] Product creation
- [ ] Order placement
- [ ] Payment processing
- [ ] Tier automation
- [ ] Monitoring alerts

### Monitoring
- [ ] Check error logs
- [ ] Monitor response times
- [ ] Track cache hit rate
- [ ] Review security alerts
- [ ] Verify backup completion

## Rollback Plan

If issues occur:
1. Switch to previous release
2. Restore database backup
3. Clear all caches
4. Restart services
5. Verify health checks

## Success Criteria

- [ ] All health checks green
- [ ] Response time <200ms (p95)
- [ ] Zero critical errors
- [ ] Cache hit rate >60%
- [ ] All core features working

## Phase 4 Features Checklist

### Transaction Monitoring (Day 28)
- [ ] 8 monitoring rules active
- [ ] Alert system functional
- [ ] SAR generation working
- [ ] Risk scoring accurate

### Compliance (Day 29)
- [ ] CBN reports generating
- [ ] Data subject requests working
- [ ] 6 retention policies active
- [ ] Regulatory submissions tracked

### Tier Automation (Day 30)
- [ ] Auto tier-up on KYC
- [ ] Auto tier-down on violations
- [ ] Notification queue processing
- [ ] Transaction limits enforced

### Optimization (Day 31-32)
- [ ] 197 database indexes
- [ ] Caching enabled
- [ ] Rate limiting active
- [ ] Security headers set
- [ ] Health checks passing
