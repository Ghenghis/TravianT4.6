# Redis & Caching Layer Setup

## 🎯 Purpose

Set up Redis for caching, session storage, and queue management to improve game performance.

**Estimated Time:** 1 hour

---

## Quick Setup

### Step 1: Run Redis Container

```bash
docker run -d \
  --name travian-redis \
  --network travian-network \
  -p 6379:6379 \
  --restart unless-stopped \
  redis:7-alpine redis-server --appendonly yes
```

### Step 2: Test Connection

```bash
docker exec -it travian-redis redis-cli PING
```

**Expected:** `PONG`

### Step 3: Configure PHP to Use Redis

Update `.env`:
```env
REDIS_HOST=redis
REDIS_PORT=6379
SESSION_DRIVER=redis
CACHE_DRIVER=redis
```

---

## ✅ Verification

```bash
docker ps | grep travian-redis
docker exec travian-redis redis-cli INFO | grep redis_version
```

**Expected:** Container running, Redis version 7.x

---

**Next guide:** [REGISTRATION-FLOW-REPAIR.md](./REGISTRATION-FLOW-REPAIR.md)

---

**Last Updated:** October 29, 2025
