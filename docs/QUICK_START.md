# ⚡ Quick Start Guide

The fastest way to get T-Trade running locally.

## 🚀 **5-Minute Setup**

### **1. Clone & Setup**
```bash
git clone https://github.com/portalzone/trade.git
cd trade
cd backend && cp .env.example .env && cd ..
```

### **2. Start Services**
```bash
docker-compose up -d
```

### **3. Install & Migrate**
```bash
docker exec t-trade-backend composer install
docker exec t-trade-backend php artisan key:generate
docker exec t-trade-backend php artisan migrate --seed
```

### **4. Test**
```bash
curl http://localhost:8000/api/health
```

✅ **Done!** API running at http://localhost:8000

## 📝 **Test Credentials**
```
Seller: support@basepan.com / password
Buyer: contact@basepan.com / password
Admin: admin@basepan.com / password
```

## 📚 **Next Steps**

- [Full Setup Guide](SETUP_GUIDE.md)
- [API Reference](API_REFERENCE.md)
- [Postman Collection](T-Trade-API.postman_collection.json)

---

**[← Back to Main README](../README.md)**
