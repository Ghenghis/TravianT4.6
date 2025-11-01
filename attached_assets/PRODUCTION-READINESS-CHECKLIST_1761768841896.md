# Production Readiness Checklist - File by File

## Executive Summary

**Current Status**: ❌ NOT PRODUCTION READY

**Blocking Issues**: 7 Critical, 5 High Priority, 3 Medium Priority

This document provides a complete file-by-file analysis of what's broken and what needs to be fixed before production deployment.

---

## CRITICAL ISSUES (Must Fix Before Any Deployment)

### 1. Database Architecture Mismatch ❌

**Problem**: Ensure a consistent MySQL architecture for both Global and Per‑World databases

**Files Affected**:
- `sections/api/include/Database/DB.php` - Must use MySQL DSN
- `sections/api/include/Database/ServerDB.php` - MySQL DSN
- `sections/globalConfig.php` - MySQL settings

**Required Fix**:
```php
// Option A: Convert DB.php to MySQL (RECOMMENDED)
// sections/api/include/Database/DB.php
$dsn = 'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4';

// Option B: Use external MySQL service
// Set up external MySQL and point both DB.php and ServerDB.php to it
```

**Status**: ⏳ PENDING
**Priority**: CRITICAL - Blocks all functionality
**Estimated Time**: 2 hours

---

### 2. Missing Game World Configuration Files ❌

**Problem**: Code references config files that don't exist

**Missing Files**:
```
sections/servers/testworld/include/connection.php  ❌ MISSING
sections/servers/demo/include/connection.php       ❌ MISSING
sections/servers/testworld/include/config.php      ❌ MISSING
sections/servers/demo/include/config.php           ❌ MISSING
```

**Required Fix**: Create these files with proper MySQL connection details

**Template Needed**:
```php
<?php
// sections/servers/testworld/include/connection.php
global $connection;
$connection = [
    'speed' => '100',
    'worldId' => 'testworld',
    'database' => [
        'hostname' => 'mysql',
        'username' => 'travian_user',
        'password' => getenv('DB_PASSWORD'),
        'database' => 'travian_testworld',
    ],
];
```

**Status**: ⏳ PENDING
**Priority**: CRITICAL - Blocks login
**Estimated Time**: 1 hour

---

### 3. Game World Databases Don't Exist ❌

**Problem**: Login tries to connect to game world databases that haven't been created

**Missing Databases**:
- `travian_testworld` ❌ NOT CREATED
- `travian_demo` ❌ NOT CREATED

**Missing Tables** (90+ tables per world):
- `users` - Player accounts
- `villages` - Player settlements
- `alliances` - Alliance data
- `marketplace` - Trading system
- ... 86 more tables

**Required Fix**:
```sql
-- Create databases
CREATE DATABASE travian_testworld CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE travian_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import schema
SOURCE main_script/include/schema/T4.4.sql;
```

**Status**: ⏳ PENDING
**Priority**: CRITICAL - Blocks gameplay
**Estimated Time**: 2 hours (schema import + testing)

---

### 4. Incomplete Global Database Schema ❌

**Problem**: Global database missing critical tables

**Required Global Tables** (MySQL):
- ✅ `gameServers`
- ✅ `activation`
- ✅ `configurations`
- ✅ `banIP`
- ✅ `email_blacklist`
- ✅ `mailserver`
- ✅ `passwordRecovery`

**Missing Tables** (create in MySQL):
- ❌ All the above tables in MySQL format
- ❌ `newsletter` - Email campaigns
- ❌ `payment_log` - Payment tracking
- ❌ `voting` - External voting sites

**Required Fix**: Import the MySQL schema (`database/main.sql`) and verify

**Status**: ⏳ PENDING
**Priority**: CRITICAL - Blocks registration/login
**Estimated Time**: 3 hours

---

### 5. Invalid configFileLocation Paths ❌

**Problem**: Database has wrong paths to config files

**Current Data**:
```sql
SELECT id, worldId, configFileLocation FROM gameServers;
-- Returns paths like: /var/www/html/sections/servers/testworld/include/connection.php
-- But those files don't exist!
```

**Required Fix**:
```sql
-- Update to correct paths OR create the files
UPDATE gameServers SET 
    configFileLocation = '/var/www/html/sections/servers/testworld/include/connection.php'
WHERE worldId = 'testworld';
```

**Status**: ⏳ PENDING
**Priority**: CRITICAL - Blocks ServerDB initialization
**Estimated Time**: 30 minutes

---

### 6. Column Name Case Sensitivity Checks

**Note**: Not applicable for MySQL defaults. Keep explicit column names in queries for consistency.

---

### 7. Email Service Not Configured ❌

**Problem**: Email credentials missing, password reset/activation won't work

**Missing Configuration**:
```bash
# .env - These are not set
SMTP_HOST=
SMTP_PORT=
SMTP_USERNAME=
SMTP_PASSWORD=
SENDINBLUE_API_KEY=
```

**Affected Files**:
- `sections/api/include/Core/EmailService.php` - Will fail to send
- `mailNotify/notify.php` - Not configured
- `TaskWorker/worker.php` - Email queue won't process

**Required Fix**: Get SMTP credentials and configure environment

**Status**: ⏳ PENDING
**Priority**: CRITICAL - Blocks account activation
**Estimated Time**: 1 hour (get credentials + test)

---

## HIGH PRIORITY ISSUES

### 8. Router.php API Routing Issues ⚠️

**Problem**: Router may not handle all API endpoints correctly

**File**: `router.php`

**Required Testing**:
- `/v1/loadConfig` ✓ Works
- `/v1/servers/loadServers` ✓ Works  
- `/v1/register/register` ✓ Works
- `/v1/auth/login` ⚠️ Untested (needs game world DB)
- `/v1/auth/forgotPassword` ⚠️ Untested
- All other endpoints ❌ Unknown

**Required Fix**: Comprehensive endpoint testing and documentation

**Status**: ⏳ PARTIAL
**Priority**: HIGH
**Estimated Time**: 2 hours

---

### 9. Angular Frontend API Integration ⚠️

**Problem**: Frontend may be calling wrong API URLs

**File**: `angularIndex/browser/main-*.bundle.js`

**Current Status**:
- ✅ FIXED: Removed `api.` subdomain requirement
- ⚠️ UNKNOWN: Are all API endpoints correct?
- ⚠️ UNKNOWN: Does HTTPS redirect work?

**Required Fix**: Full frontend testing with real backend

**Status**: ⏳ PARTIAL
**Priority**: HIGH
**Estimated Time**: 2 hours

---

### 10. Missing Background Worker Configuration ⚠️

**Problem**: Background workers not set up

**Missing Services**:
- ❌ TaskWorker not running
- ❌ Mail queue processor not running
- ❌ Cron jobs not configured

**Affected Files**:
- `TaskWorker/worker.php` - Exists but not running
- `mailNotify/notify.php` - Exists but not running

**Required Fix**: Set up systemd services or Docker containers for workers

**Status**: ⏳ PENDING
**Priority**: HIGH - Affects email delivery
**Estimated Time**: 3 hours

---

### 11. Redis Session Configuration ⚠️

**Problem**: PHP sessions not configured for Redis

**File**: `docker/php/php.ini` (doesn't exist yet)

**Required Fix**:
```ini
session.save_handler = redis
session.save_path = "tcp://redis:6379"
```

**Status**: ⏳ PENDING (will be created with Docker setup)
**Priority**: HIGH
**Estimated Time**: 30 minutes

---

### 12. Composer Dependencies Not Installed ⚠️

**Problem**: PHP dependencies may not be installed

**Directories**:
- `sections/api/include/vendor/` - ⚠️ May be incomplete
- `TaskWorker/include/vendor/` - ⚠️ Status unknown
- `mailNotify/include/vendor/` - ⚠️ Status unknown

**Required Fix**:
```bash
cd sections/api/include && composer install
cd TaskWorker/include && composer install  
cd mailNotify/include && composer install
```

**Status**: ⏳ UNKNOWN
**Priority**: HIGH
**Estimated Time**: 1 hour

---

## MEDIUM PRIORITY ISSUES

### 13. Missing Docker Configuration Files ⚠️

**Problem**: Docker files from docs don't exist in codebase

**Missing Files**:
```
docker/nginx/Dockerfile                    ❌ NOT CREATED
docker/nginx/nginx.conf                    ❌ NOT CREATED
docker/nginx/conf.d/default.conf           ❌ NOT CREATED
docker/php/Dockerfile                      ❌ NOT CREATED
docker/php/php.ini                         ❌ NOT CREATED
docker/mysql/init/01-global-schema.sql     ❌ NOT CREATED
docker/redis/redis.conf                    ❌ NOT CREATED
docker-compose.yml                         ❌ NOT CREATED
.env.example                               ❌ NOT CREATED
```

**Required Fix**: Create all Docker configuration files from documentation

**Status**: ⏳ PENDING
**Priority**: MEDIUM - Use local Docker dev server for now
**Estimated Time**: 4 hours

---

### 14. Missing Production Scripts ⚠️

**Problem**: Operational scripts not created

**Missing Scripts**:
```
scripts/backup-databases.sh                ❌ NOT CREATED
scripts/db-maintenance.sh                  ❌ NOT CREATED
scripts/production-backup.sh               ❌ NOT CREATED
scripts/verify-config.php                  ❌ NOT CREATED
scripts/test-email.php                     ❌ NOT CREATED
scripts/performance-check.sh               ❌ NOT CREATED
```

**Required Fix**: Create all operational scripts

**Status**: ⏳ PENDING
**Priority**: MEDIUM
**Estimated Time**: 3 hours

---

### 15. Security Hardening Not Implemented ⚠️

**Problem**: Security measures from docs not applied

**Missing Security**:
- ❌ CSRF protection
- ❌ Rate limiting
- ❌ Input sanitization helpers
- ❌ SQL injection prevention audit
- ❌ XSS protection headers

**Required Files to Create**:
```
sections/api/include/Core/Security.php     ❌ NOT CREATED
sections/api/include/Middleware/RateLimiter.php  ❌ NOT CREATED
sections/api/include/Core/Encryption.php   ❌ NOT CREATED
```

**Status**: ⏳ PENDING
**Priority**: MEDIUM - Important for production
**Estimated Time**: 4 hours

---

## PRODUCTION READINESS SUMMARY

### What's Working ✅
1. ✅ MySQL global database (Docker or external)
2. ✅ User registration saves data
3. ✅ API endpoints respond with JSON
4. ✅ Angular frontend loads
5. ✅ Router.php handles basic routing
6. ✅ Column naming verified (MySQL)

### What's Broken ❌
1. ❌ Login system (no game world databases)
2. ❌ Database architecture (ensure all components use MySQL)
3. ❌ Game world configurations missing
4. ❌ Email system not configured
5. ❌ Background workers not running
6. ❌ 90+ game tables not created
7. ❌ Docker setup doesn't exist

### Critical Path to Production

**Phase 1: Database Migration** (Est: 8 hours)
1. Set up external MySQL service OR install MySQL locally
2. Create global database with all tables
3. Create game world databases (testworld, demo)
4. Import T4.4.sql schema (90+ tables)
5. Update DB.php to use MySQL
6. Create connection.php files for each world
7. Test database connectivity

**Phase 2: Core Functionality** (Est: 6 hours)
8. Configure email SMTP credentials
9. Test registration flow end-to-end
10. Test login flow end-to-end
11. Test password recovery
12. Fix any routing issues discovered
13. Test all API endpoints

**Phase 3: Infrastructure** (Est: 8 hours)
14. Create all Docker configuration files
15. Set up background workers
16. Configure Redis for sessions
17. Set up monitoring and logging
18. Create backup scripts
19. Implement security hardening

**Phase 4: Testing & Deployment** (Est: 4 hours)
20. Full integration testing
21. Performance testing
22. Security audit
23. Production deployment
24. Post-deployment verification

**TOTAL ESTIMATED TIME: 26 hours** (3-4 days of focused work)

---

## Immediate Next Steps (Priority Order)

1. **DECIDE**: MySQL strategy (Docker default vs external service)
2. **CREATE**: MySQL database (Docker default)
3. **IMPORT**: Database schemas (global + game worlds)
4. **CREATE**: Game world connection.php files
5. **UPDATE**: DB.php to use MySQL
6. **CONFIGURE**: Email SMTP credentials
7. **TEST**: Registration and login flows
8. **FIX**: Any issues discovered during testing

---

## Files That Need Creation (Complete List)

### Configuration Files
- [ ] `sections/servers/testworld/include/connection.php`
- [ ] `sections/servers/demo/include/connection.php`
- [ ] `.env` (with all real values)

### Docker Files (26 files)
- [ ] `docker/nginx/Dockerfile`
- [ ] `docker/nginx/nginx.conf`
- [ ] `docker/nginx/conf.d/default.conf`
- [ ] `docker/php/Dockerfile`
- [ ] `docker/php/php.ini`
- [ ] `docker/mysql/init/01-global-schema.sql`
- [ ] `docker/mysql/conf.d/custom.cnf`
- [ ] `docker/redis/redis.conf`
- [ ] `docker-compose.yml`
- [ ] `docker-compose.monitoring.yml`
- [ ] `docker-compose.logging.yml`
- [ ] `.env.example`
- [ ] `.dockerignore`
- [ ] (13 more monitoring/logging config files)

### Operational Scripts (15+ files)
- [ ] `scripts/backup-databases.sh`
- [ ] `scripts/production-backup.sh`
- [ ] `scripts/db-maintenance.sh`
- [ ] `scripts/verify-config.php`
- [ ] `scripts/test-email.php`
- [ ] `scripts/test-redis.php`
- [ ] `scripts/performance-check.sh`
- [ ] `scripts/security-monitor.sh`
- [ ] `scripts/quick-health-check.sh`
- [ ] `scripts/full-diagnostic.sh`
- [ ] (5 more utility scripts)

### Security/Middleware Files
- [ ] `sections/api/include/Core/Security.php`
- [ ] `sections/api/include/Middleware/RateLimiter.php`
- [ ] `sections/api/include/Core/Encryption.php`
- [ ] `sections/api/include/Core/JWT.php`

### Monitoring Files (20+ files)
- [ ] `monitoring/prometheus.yml`
- [ ] `monitoring/alertmanager.yml`
- [ ] `monitoring/alert.rules.yml`
- [ ] Grafana dashboards
- [ ] (16 more monitoring configs)

**TOTAL NEW FILES NEEDED: 80+**

---

## Recommendation

**DO NOT DEPLOY TO PRODUCTION** until at least all CRITICAL and HIGH priority issues are resolved.

Current codebase is suitable for:
- ✅ Local Docker development/testing
- ✅ Understanding the architecture
- ❌ Production deployment

Estimated time to production-ready: **26-32 hours of focused development work**
