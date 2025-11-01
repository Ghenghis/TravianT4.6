# Travian-Solo Integration - Step-by-Step Execution Guide

**Repository Source:** https://github.com/Ghenghis/Travian-Solo  
**Target:** Current Replit TravianT4.6 Project  
**Estimated Time:** 8-12 hours total  
**Difficulty:** Intermediate

---

## 🎯 Quick Summary

This guide integrates **600MB of production-ready code** from Travian-Solo into our Replit project, giving us:
- ✅ Complete game engine (3,839 PHP files)
- ✅ Full database (90+ tables)
- ✅ Better frontend (pre-built Angular)
- ✅ Background workers
- ✅ Email system
- ✅ Testing suite
- ✅ Installer system

---

## ⚡ Quick Start (30 minutes)

If you just want to get the **core game working immediately**:

```bash
# 1. Backup
cp -r . ../travian-backup-$(date +%Y%m%d-%H%M%S)

# 2. Copy game engine
cp -r /tmp/Travian-Solo/main_script/* ./main_script/

# 3. Copy database schema
cp /tmp/Travian-Solo/database/main.sql ./database/

# 4. Done! (Configure database and test)
```

---

## 📋 Full Integration (8-12 hours)

### **PHASE 1: Preparation (30 minutes)**

#### **Step 1.1: Backup Everything**

```bash
# Create timestamped backup
BACKUP_DIR="../travian-backup-$(date +%Y%m%d-%H%M%S)"
cp -r . "$BACKUP_DIR"
echo "✅ Backup created: $BACKUP_DIR"

# Verify backup
ls -lh "$BACKUP_DIR"
```

**✅ Checkpoint:** Backup exists and contains all files

---

#### **Step 1.2: Verify Travian-Solo Repository**

```bash
# Check repository is cloned
ls -lh /tmp/Travian-Solo/

# Verify key directories exist
ls -ld /tmp/Travian-Solo/{main_script,sections,database,angularIndex,TaskWorker,mailNotify,Installer}

# Check file counts
echo "PHP files: $(find /tmp/Travian-Solo -name '*.php' | wc -l)"
echo "SQL files: $(find /tmp/Travian-Solo -name '*.sql' | wc -l)"
```

**Expected Output:**
```
PHP files: 3839
SQL files: 12
All directories present
```

**✅ Checkpoint:** Repository verified with correct file counts

---

#### **Step 1.3: Review Integration Plan**

```bash
# Read the analysis
cat INTEGRATION-ANALYSIS.md

# Confirm you understand:
# - What will be copied
# - What will be replaced
# - What will be kept
# - Potential issues
```

**✅ Checkpoint:** You understand the integration plan

---

### **PHASE 2: Core Game Engine (2-3 hours)**

#### **Step 2.1: Copy Main Game Script**

```bash
# Create backup of current main_script
mv main_script main_script_OLD_$(date +%Y%m%d)

# Copy new game engine
cp -r /tmp/Travian-Solo/main_script ./

# Verify copy
echo "Files in main_script: $(find main_script -type f | wc -l)"
du -sh main_script
```

**Expected Output:**
```
Files in main_script: 1000+
34M main_script
```

**✅ Checkpoint:** Game engine copied successfully

---

#### **Step 2.2: Update Sections (Production Code)**

```bash
# Backup current sections
mv sections sections_OLD_$(date +%Y%m%d)

# Copy new sections
cp -r /tmp/Travian-Solo/sections ./

# Verify size
du -sh sections
```

**Expected Output:**
```
509M sections
```

**Key Files to Check:**
```bash
# Should exist:
ls -lh sections/api/include/Database/
ls -lh sections/api/include/Api/
ls -lh sections/globalConfig.php
ls -lh sections/servers/dev/
```

**✅ Checkpoint:** Production code copied

---

#### **Step 2.3: Copy Angular Frontend**

```bash
# Backup old frontend
mv angularIndex angularIndex_OLD_$(date +%Y%m%d)

# Copy new frontend
cp -r /tmp/Travian-Solo/angularIndex ./

# Verify
du -sh angularIndex
ls -lh angularIndex/browser/
```

**Expected Output:**
```
45M angularIndex
index.html and JS bundles present
```

**✅ Checkpoint:** Frontend copied

---

### **PHASE 3: Database Setup (1-2 hours)**

#### **Step 3.1: Copy Database Schemas**

```bash
# Copy complete schema
cp /tmp/Travian-Solo/database/main.sql ./database/

# Copy global schema if exists
cp /tmp/Travian-Solo/database/schemas/*.sql ./database/schemas/ 2>/dev/null || true

# Verify
ls -lh database/
cat database/main.sql | grep "CREATE TABLE" | wc -l
```

**Expected Output:**
```
90+ tables in main.sql
```

**✅ Checkpoint:** Database schemas copied

---

#### **Step 3.2: Choose Database Engine**

**DECISION POINT:** MySQL (fast) vs PostgreSQL (Replit-native)

**Option A: Use MySQL (Recommended - Fastest Integration)**

```bash
# 1. Add MySQL via Nix
cat >> replit.nix << 'EOF'
{ pkgs }: {
  deps = [
    pkgs.mysql80
  ];
}
EOF

# 2. Start MySQL service
mkdir -p mysql-data
mysqld --initialize-insecure --datadir=./mysql-data
mysqld --datadir=./mysql-data --socket=./mysql.sock &

# 3. Create database
mysql -u root --socket=./mysql.sock << 'EOF'
CREATE DATABASE IF NOT EXISTS travian_global;
CREATE DATABASE IF NOT EXISTS travian_testworld;
CREATE DATABASE IF NOT EXISTS travian_demo;
EOF

# 4. Import schema
mysql -u root --socket=./mysql.sock travian_global < database/main.sql
mysql -u root --socket=./mysql.sock travian_testworld < database/main.sql
mysql -u root --socket=./mysql.sock travian_demo < database/main.sql

# 5. Verify
mysql -u root --socket=./mysql.sock -e "SHOW DATABASES;"
mysql -u root --socket=./mysql.sock travian_global -e "SHOW TABLES;" | wc -l
```

**Expected:** 90+ tables in each database

**Option B: Convert to PostgreSQL (Slower - More Complex)**

```bash
# This requires manual conversion
# See database conversion section below
```

**✅ Checkpoint:** Database created and schema imported

---

#### **Step 3.3: Update Database Configuration**

```bash
# For MySQL option:
cat > .env.database << 'EOF'
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=travian_global
DB_USERNAME=root
DB_PASSWORD=
DB_SOCKET=./mysql.sock

# World databases
TESTWORLD_DB=travian_testworld
DEMO_DB=travian_demo
EOF

# Merge with main .env
cat .env.database >> .env

# Update sections/globalConfig.php
# Find database connection section and update with:
# - MySQL instead of PostgreSQL
# - New credentials
# - Socket path
```

**✅ Checkpoint:** Database configured

---

### **PHASE 4: Background Services (1-2 hours)**

#### **Step 4.1: Copy TaskWorker**

```bash
# Copy background worker
cp -r /tmp/Travian-Solo/TaskWorker ./

# Install dependencies
cd TaskWorker/include
composer install
cd ../..

# Verify
du -sh TaskWorker
ls -lh TaskWorker/include/Core/
```

**Expected Output:**
```
4.0M TaskWorker
Core classes present
```

**✅ Checkpoint:** TaskWorker copied

---

#### **Step 4.2: Copy Email System**

```bash
# Copy email service
cp -r /tmp/Travian-Solo/mailNotify ./

# Install dependencies
cd mailNotify/include
composer install
cd ../..

# Verify
du -sh mailNotify
```

**Expected Output:**
```
572K mailNotify
```

**✅ Checkpoint:** Email system copied

---

#### **Step 4.3: Copy Installer**

```bash
# Copy installer
cp -r /tmp/Travian-Solo/Installer ./

# Verify
du -sh Installer
ls -lh Installer/api/
```

**Expected Output:**
```
144K Installer
API files present
```

**✅ Checkpoint:** Installer copied

---

### **PHASE 5: Testing & Quality Tools (1-2 hours)**

#### **Step 5.1: Copy Test Suite**

```bash
# Copy tests
cp -r /tmp/Travian-Solo/tests ./

# Copy config files
cp /tmp/Travian-Solo/phpunit.xml ./
cp /tmp/Travian-Solo/phpmd.xml ./
cp /tmp/Travian-Solo/phpstan.neon ./
cp /tmp/Travian-Solo/.php-cs-fixer.dist.php ./

# Verify
ls -lh tests/
cat phpunit.xml
```

**✅ Checkpoint:** Tests copied

---

#### **Step 5.2: Update Composer Dependencies**

```bash
# Backup current composer.json
cp composer.json composer.json.backup

# Merge dependencies from Travian-Solo
# Manual step: Compare and merge dependencies

# Install
composer install

# Run tests
vendor/bin/phpunit --colors=always
```

**✅ Checkpoint:** Dependencies installed, tests run

---

### **PHASE 6: Configuration & Integration (1-2 hours)**

#### **Step 6.1: Merge Environment Variables**

```bash
# Copy their .env.example
cp /tmp/Travian-Solo/.env.example ./.env.travian-solo

# Create comprehensive .env
cat > .env << 'EOF'
# === Database Configuration ===
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=travian_global
DB_USERNAME=root
DB_PASSWORD=
DB_SOCKET=./mysql.sock

# === Application Settings ===
APP_URL=https://your-repl-name.your-username.repl.co
APP_DEBUG=false
DOMAIN=your-repl-name.your-username.repl.co

# === Security ===
SECURE_HASH_SALT=$(openssl rand -hex 32)
SESSION_LIFETIME=86400
COOKIE_SECURE=true

# === Email Configuration ===
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_ENCRYPTION=tls
SMTP_FROM_ADDRESS=noreply@travian.com
SMTP_FROM_NAME=Travian

# === Redis Configuration ===
REDIS_HOST=localhost
REDIS_PORT=6379

# === Game World Configuration ===
TESTWORLD_SPEED=100
DEMO_SPEED=5

# === External Services ===
SENDINBLUE_API_KEY=your-key-here
RECAPTCHA_SITE_KEY=your-key-here
RECAPTCHA_SECRET_KEY=your-key-here
EOF

# Update with your actual values
nano .env
```

**✅ Checkpoint:** Environment configured

---

#### **Step 6.2: Update Replit Configuration**

```bash
# Update .replit file
cat > .replit << 'EOF'
run = "php -S 0.0.0.0:5000 router.php"
entrypoint = "router.php"

[nix]
channel = "stable-24_05"

[deployment]
run = ["sh", "-c", "php -S 0.0.0.0:5000 router.php"]
EOF
```

**✅ Checkpoint:** Replit configured

---

#### **Step 6.3: Update Router**

```bash
# Verify router.php handles all routes
cat router.php

# Should route:
# - /v1/* -> API
# - / -> Angular app
# - Static files
```

**✅ Checkpoint:** Router verified

---

### **PHASE 7: Testing & Verification (1-2 hours)**

#### **Step 7.1: Start Services**

```bash
# Start MySQL
mysqld --datadir=./mysql-data --socket=./mysql.sock &

# Start Redis (if not running)
docker run -d --name travian-redis -p 6379:6379 redis:7-alpine

# Start PHP server
php -S 0.0.0.0:5000 router.php
```

**✅ Checkpoint:** All services running

---

#### **Step 7.2: Test Database Connectivity**

```bash
# Test script
cat > test-db.php << 'EOF'
<?php
require_once 'sections/globalConfig.php';

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=travian_global;unix_socket=./mysql.sock",
        "root",
        ""
    );
    echo "✅ Database connection successful\n";
    
    // Count tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    echo "✅ Found " . count($tables) . " tables\n";
    
    // Test query
    $stmt = $pdo->query("SELECT * FROM gameServers LIMIT 1");
    $server = $stmt->fetch();
    echo "✅ Sample data: " . print_r($server, true) . "\n";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
EOF

php test-db.php
```

**Expected Output:**
```
✅ Database connection successful
✅ Found 90+ tables
✅ Sample data retrieved
```

**✅ Checkpoint:** Database connected

---

#### **Step 7.3: Test Frontend**

```bash
# Visit in browser
echo "Open: https://your-repl.replit.dev"

# Should see:
# - Travian login page
# - No 404 errors
# - API calls working
```

**✅ Checkpoint:** Frontend loads

---

#### **Step 7.4: Test Registration Flow**

```bash
# Register a test user via UI
# Or via API:
curl -X POST https://your-repl.replit.dev/v1/register/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "username": "testuser",
    "password": "TestPass123!",
    "confirmPassword": "TestPass123!"
  }'
```

**Expected:** Success response with activation link

**✅ Checkpoint:** Registration works

---

#### **Step 7.5: Test Login Flow**

```bash
# Login via UI
# Or via API:
curl -X POST https://your-repl.replit.dev/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "TestPass123!"
  }'
```

**Expected:** Success response with session token

**✅ Checkpoint:** Login works

---

#### **Step 7.6: Test Game World Connection**

```bash
# Test script
cat > test-world-connection.php << 'EOF'
<?php
// Test connecting to game world database
$pdo = new PDO(
    "mysql:host=localhost;dbname=travian_testworld;unix_socket=./mysql.sock",
    "root",
    ""
);

$tables = $pdo->query("SHOW TABLES")->fetchAll();
echo "✅ Testworld has " . count($tables) . " tables\n";

// Test some key tables
$tests = ['users', 'vdata', 'units', 'research'];
foreach ($tests as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo "  - $table: $count rows\n";
}
EOF

php test-world-connection.php
```

**✅ Checkpoint:** Game world accessible

---

### **PHASE 8: Finalization (1 hour)**

#### **Step 8.1: Update Documentation**

```bash
# Update replit.md
cat >> replit.md << 'EOF'

## Integration Completed ($(date +%Y-%m-%d))

### Changes Made
- ✅ Integrated Travian-Solo repository
- ✅ Complete game engine (3,839 PHP files)
- ✅ Full database schema (90+ tables)
- ✅ Production-ready frontend
- ✅ Background workers added
- ✅ Email system upgraded
- ✅ Testing suite added

### New Components
- **main_script/**: Complete game logic
- **sections/**: Full API backend (509MB)
- **angularIndex/**: Pre-built Angular frontend (45MB)
- **TaskWorker/**: Background job processor
- **mailNotify/**: Email service with SendInBlue
- **Installer/**: Web-based installation system
- **tests/**: PHPUnit test suite

### Database
- Engine: MySQL 8.0
- Global DB: travian_global (90+ tables)
- World DBs: travian_testworld, travian_demo
- Connection: Unix socket (./mysql.sock)

### Services
- PHP 8.2 server: 0.0.0.0:5000
- MySQL: ./mysql.sock
- Redis: localhost:6379

### Known Issues
- None currently

### Next Steps
- Set up background workers
- Configure email service
- Add AI/NPC system
- Production hardening
EOF
```

**✅ Checkpoint:** Documentation updated

---

#### **Step 8.2: Clean Up**

```bash
# Remove old backups if everything works
# (Keep at least one backup!)

# Remove Travian-Solo repo from /tmp
# (Can always re-clone if needed)

# Optimize database
mysql -u root --socket=./mysql.sock travian_global -e "OPTIMIZE TABLE users, gameServers, configurations;"
```

**✅ Checkpoint:** Cleanup complete

---

#### **Step 8.3: Final Verification**

```bash
# Run complete test suite
vendor/bin/phpunit --colors=always --testdox

# Check all services
ps aux | grep -E "(php|mysql|redis)"

# Check file sizes
du -sh . main_script sections angularIndex

# Check database
mysql -u root --socket=./mysql.sock -e "SELECT COUNT(*) FROM travian_global.users;"
```

**✅ Checkpoint:** All systems operational

---

## 🎉 SUCCESS CRITERIA

Your integration is complete when:

- [x] Game engine copied (3,839 files)
- [x] Database running (90+ tables in 3 databases)
- [x] Frontend loads without errors
- [x] Registration works
- [x] Login works
- [x] Game world accessible
- [x] Tests pass (PHPUnit)
- [x] Services running (PHP, MySQL, Redis)
- [x] Documentation updated
- [x] Backup created

---

## 🆘 Troubleshooting

### **Issue: MySQL won't start**

```bash
# Check logs
cat mysql-data/*.err

# Reset MySQL
rm -rf mysql-data
mysqld --initialize-insecure --datadir=./mysql-data
```

### **Issue: Frontend shows 404**

```bash
# Verify Angular files
ls -lh angularIndex/browser/index.html

# Check router
cat router.php | grep "angularIndex"

# Restart PHP server
pkill php && php -S 0.0.0.0:5000 router.php
```

### **Issue: Database connection fails**

```bash
# Check socket
ls -lh ./mysql.sock

# Test connection
mysql -u root --socket=./mysql.sock -e "SELECT 1"

# Update globalConfig.php with correct socket path
```

### **Issue: API returns 500 errors**

```bash
# Check PHP errors
tail -f /tmp/php_errors.log

# Enable debug mode
echo "APP_DEBUG=true" >> .env

# Check API logs
tail -f sections/api/logs/*.log
```

---

## 📊 Before vs After

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| PHP Files | 612 | 3,839 | **+3,227** (527% more) |
| Database Tables | 4 | 90+ | **+86** (2,150% more) |
| Frontend Size | Small | 45MB | Complete UI |
| Background Workers | None | TaskWorker | **NEW** |
| Email System | Basic | SendInBlue | **UPGRADED** |
| Tests | None | PHPUnit Suite | **NEW** |
| Installer | None | Web Installer | **NEW** |
| Total Size | ~70MB | **600MB** | **+530MB** |

---

## ✅ Final Checklist

- [ ] All backups created
- [ ] Repository cloned
- [ ] Game engine copied
- [ ] Production code copied
- [ ] Frontend copied
- [ ] Database created and imported
- [ ] Background workers installed
- [ ] Email system installed
- [ ] Installer added
- [ ] Tests added
- [ ] Environment configured
- [ ] Services running
- [ ] Registration tested
- [ ] Login tested
- [ ] Game world tested
- [ ] Documentation updated
- [ ] Cleanup completed
- [ ] Everything working!

---

**Congratulations! You now have a production-ready Travian game server!** 🎮🚀

**Next:** Start adding the AI/NPC system from `docs/AI/` documentation!
