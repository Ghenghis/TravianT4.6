# TravianT4.6: Replit → Docker/Windows Conversion Blueprint

**Enterprise-Grade Migration Guide**  
**Version:** 1.0.0  
**Last Updated:** October 29, 2025  
**Estimated Migration Time:** 40-60 hours (full production setup)

---

## 📋 Executive Summary

This document provides a comprehensive, step-by-step blueprint for converting the TravianT4.6 project from **Replit cloud hosting** (PostgreSQL, serverless) to **local Docker/Windows hosting** (MySQL 8.0, full-stack) with enterprise-grade reliability.

### Migration Scope

**FROM (Current Replit Setup):**
- ☁️ Cloud-hosted PHP 8.2 built-in server
- 🗄️ PostgreSQL (Replit/Neon-backed) database
- 🌐 Single-domain deployment (no subdomains)
- 🔧 Environment variable configuration
- 📦 Minimal service orchestration

**TO (Target Docker/Windows Setup):**
- 🖥️ Windows 11 + WSL2 + Docker Desktop
- 🐳 Multi-container orchestration (Nginx, PHP-FPM, MySQL, Redis)
- 🗄️ MySQL 8.0 with complete Travian-Solo schema (3,839 files)
- 📧 Real email delivery (SendGrid/SMTP)
- 💳 Payment systems (Stripe/PayPal sandbox)
- 🤖 AI NPC framework (50-500 agents, local LLMs)
- 🔒 Production-grade security & monitoring

### Why Migrate?

✅ **Full Control:** Own your infrastructure, no cloud dependency  
✅ **AI Integration:** GPU access (RTX 3090 Ti + Tesla P40) for local LLM inference  
✅ **Performance:** Dedicated resources, <200ms response times  
✅ **Cost:** No monthly cloud fees, one-time hardware investment  
✅ **Privacy:** All data stays local, complete control  
✅ **Scalability:** Support 500+ NPCs with hybrid AI architecture  

---

## 🎯 Prerequisites & Planning

### Required Hardware

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **CPU** | Intel i5 8th gen / AMD Ryzen 5 | Intel i7 12th gen / AMD Ryzen 7 |
| **RAM** | 16 GB | 32 GB+ |
| **Storage** | 256 GB SSD | 512 GB NVMe SSD |
| **GPU** | None (optional) | NVIDIA RTX 3090 Ti (24GB VRAM) |
| **OS** | Windows 11 Pro | Windows 11 Pro/Enterprise |
| **Network** | 10 Mbps | 100+ Mbps |

### Required Software

- ✅ Windows 11 (build 22000+)
- ✅ WSL2 with Ubuntu 22.04 LTS
- ✅ Docker Desktop 4.20+
- ✅ Git for Windows
- ✅ Visual Studio Code (recommended IDE)
- ✅ Modern browser (Chrome/Edge)

### Required Access

- 🔑 GitHub account (for Travian-Solo repository)
- 📧 SMTP provider account (SendGrid/Postfix/MailHog)
- 💳 Payment gateway accounts (Stripe/PayPal sandbox)
- 🌐 Optional: Domain name + DNS control

### Time Allocation

| Phase | Tasks | Time Estimate |
|-------|-------|---------------|
| **Phase 1** | Environment Setup | 4-6 hours |
| **Phase 2** | Database Migration | 6-8 hours |
| **Phase 3** | Code Conversion | 8-12 hours |
| **Phase 4** | Service Configuration | 6-10 hours |
| **Phase 5** | Testing & Validation | 8-12 hours |
| **Phase 6** | AI Integration (Optional) | 16-24 hours |
| **Total** | Complete Migration | **40-60 hours** |

---

## 📐 Architecture Comparison

### Replit Architecture (Current)

```
┌─────────────────────────────────────────────┐
│           Replit Cloud Platform             │
├─────────────────────────────────────────────┤
│                                             │
│  ┌──────────────┐      ┌─────────────┐     │
│  │ PHP 8.2      │      │ PostgreSQL  │     │
│  │ Built-in     │◄────►│ (Neon DB)   │     │
│  │ Server :5000 │      │             │     │
│  └──────────────┘      └─────────────┘     │
│         │                                   │
│         ▼                                   │
│  ┌──────────────┐                          │
│  │ Angular App  │                          │
│  │ (Static)     │                          │
│  └──────────────┘                          │
│                                             │
└─────────────────────────────────────────────┘
```

**Characteristics:**
- Single process (PHP built-in server)
- Environment variable configuration
- PostgreSQL with SSL
- No caching layer
- No background workers
- Limited customization

---

### Docker/Windows Architecture (Target)

```
┌────────────────────────────────────────────────────────────┐
│                    Windows 11 Host                         │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                  WSL2 (Ubuntu 22.04)                 │  │
│  │  ┌────────────────────────────────────────────────┐  │  │
│  │  │         Docker Desktop Network                 │  │  │
│  │  │                                                │  │  │
│  │  │  ┌──────────┐  ┌──────────┐  ┌────────────┐   │  │  │
│  │  │  │  Nginx   │  │ PHP-FPM  │  │  MySQL 8   │   │  │  │
│  │  │  │  :80/443 │◄►│  :9000   │◄►│  :3306     │   │  │  │
│  │  │  └──────────┘  └──────────┘  └────────────┘   │  │  │
│  │  │       │             │              │           │  │  │
│  │  │       │             ▼              │           │  │  │
│  │  │       │        ┌──────────┐        │           │  │  │
│  │  │       │        │  Redis   │        │           │  │  │
│  │  │       │        │  :6379   │        │           │  │  │
│  │  │       │        └──────────┘        │           │  │  │
│  │  │       │                            │           │  │  │
│  │  │       ▼                            ▼           │  │  │
│  │  │  ┌──────────┐              ┌────────────┐     │  │  │
│  │  │  │ Angular  │              │ PHPMyAdmin │     │  │  │
│  │  │  │ Frontend │              │   :8080    │     │  │  │
│  │  │  └──────────┘              └────────────┘     │  │  │
│  │  │                                                │  │  │
│  │  │  ┌──────────┐  ┌──────────┐  ┌────────────┐  │  │  │
│  │  │  │ MailHog  │  │ TaskWorker│ │ AI Engine  │  │  │  │
│  │  │  │  :8025   │  │ (Cron)    │ │ (GPU LLM)  │  │  │  │
│  │  │  └──────────┘  └──────────┘  └────────────┘  │  │  │
│  │  └────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                            │
│  ┌──────────────────────────────────────────────────────┐  │
│  │           NVIDIA GPU Pass-through (Optional)         │  │
│  │     RTX 3090 Ti (24GB) + Tesla P40 (24GB)            │  │
│  │     → AI NPC Decision Engine (Local LLM)             │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────┘
```

**Characteristics:**
- Multi-container orchestration
- Production-grade web server (Nginx)
- MySQL 8.0 with full schema
- Redis caching layer
- Background workers (cron jobs)
- Email delivery service
- AI/GPU integration ready
- Comprehensive monitoring

---

## 🔄 Migration Phases Overview

### Phase 1: Environment Setup (4-6 hours)
- Install Windows 11 prerequisites
- Configure WSL2 with Ubuntu 22.04
- Install Docker Desktop with GPU support
- Set up development tools (Git, VSCode)

### Phase 2: Database Migration (6-8 hours)
- Export PostgreSQL data from Replit
- Install MySQL 8.0 Docker container
- Convert schema (PostgreSQL → MySQL)
- Import Travian-Solo complete schema
- Create game world databases
- Migrate data with type conversions

### Phase 3: Code Conversion (8-12 hours)
- Clone complete Travian-Solo repository
- Update database connection layer
- Convert environment variables to .env files
- Generate connection.php files
- Update SQL queries (PostgreSQL → MySQL syntax)
- Fix path references

### Phase 4: Service Configuration (6-10 hours)
- Configure Nginx reverse proxy
- Set up PHP-FPM worker pools
- Configure Redis caching
- Set up email delivery (SMTP/MailHog)
- Configure Docker Compose orchestration
- Set up background workers (cron)

### Phase 5: Testing & Validation (8-12 hours)
- Test database connectivity
- Validate registration flow
- Test login/session management
- Verify email delivery
- Test payment webhooks
- Load testing (performance validation)

### Phase 6: AI Integration (16-24 hours - OPTIONAL)
- Set up GPU Docker containers
- Install local LLM inference engine
- Integrate AI NPC framework
- Configure decision-making pipeline
- Deploy 50-500 AI agents

---

## 📦 PHASE 1: Environment Setup

### Step 1.1: Windows 11 Prerequisites

**Verify Windows Version:**
```powershell
# Open PowerShell as Administrator
winver

# Expected: Windows 11 build 22000 or higher
```

**Enable WSL2:**
```powershell
# PowerShell as Administrator
wsl --install -d Ubuntu-22.04
wsl --set-default-version 2
wsl --set-default Ubuntu-22.04

# Restart computer when prompted
```

**Verify WSL2:**
```powershell
wsl --list --verbose

# Expected output:
#   NAME            STATE           VERSION
# * Ubuntu-22.04    Running         2
```

### Step 1.2: Install Docker Desktop

**Download & Install:**
1. Download Docker Desktop from https://www.docker.com/products/docker-desktop/
2. Run installer as Administrator
3. Enable WSL2 backend during installation
4. Enable GPU support (for AI features)

**Configure Docker:**
```bash
# Open Docker Desktop → Settings → Resources:
# - CPUs: 8+ cores
# - Memory: 16+ GB
# - Swap: 4 GB
# - Disk: 256+ GB

# Enable features:
# ✅ Use WSL2 based engine
# ✅ Enable Kubernetes (optional)
# ✅ Enable GPU support (for AI)
```

**Verify Installation:**
```bash
wsl
docker --version
docker-compose --version
docker run hello-world

# Expected: "Hello from Docker!" message
```

### Step 1.3: Install Development Tools

**Git for Windows:**
```bash
# In WSL2 terminal:
sudo apt update
sudo apt install -y git curl wget nano vim

git --version
# Expected: git version 2.x
```

**Visual Studio Code:**
1. Download from https://code.visualstudio.com/
2. Install with WSL extension support
3. Install recommended extensions:
   - Remote - WSL
   - PHP Intelephense
   - Docker
   - GitLens

**Configure Git:**
```bash
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
git config --global core.autocrlf input
```

### Step 1.4: Create Project Directory

```bash
# In WSL2:
cd ~
mkdir -p projects/travian
cd projects/travian

# Verify location:
pwd
# Expected: /home/YOUR_USERNAME/projects/travian
```

---

## 🗄️ PHASE 2: Database Migration

### Step 2.1: Export Replit PostgreSQL Data

**Connect to Replit Database:**
```bash
# In Replit Shell:
psql $DATABASE_URL

# List tables:
\dt

# Export each table:
\copy gameServers TO 'gameServers.csv' CSV HEADER;
\copy configurations TO 'configurations.csv' CSV HEADER;
\copy activation TO 'activation.csv' CSV HEADER;
\copy banIP TO 'banIP.csv' CSV HEADER;

# Exit:
\q
```

**Download CSV Files:**
```bash
# In Replit Shell, compress exports:
tar -czf replit-data-export.tar.gz *.csv

# Download using Replit file browser or:
curl https://YOUR-REPL-NAME.YOUR-USERNAME.repl.co/replit-data-export.tar.gz -o ~/data.tar.gz
```

### Step 2.2: Install MySQL 8.0 Docker Container

**Create Docker Network:**
```bash
# In WSL2:
docker network create travian-network
docker network ls | grep travian

# Expected: travian-network listed
```

**Run MySQL Container:**
```bash
docker run -d \
  --name travian-mysql \
  --network travian-network \
  -p 3306:3306 \
  -e MYSQL_ROOT_PASSWORD=travian_root_pass_2025 \
  -e MYSQL_DATABASE=travian_global \
  -e MYSQL_USER=travian_user \
  -e MYSQL_PASSWORD=travian_pass_2025 \
  -v travian-mysql-data:/var/lib/mysql \
  --restart unless-stopped \
  mysql:8.0 \
  --character-set-server=utf8mb4 \
  --collation-server=utf8mb4_unicode_ci \
  --default-authentication-plugin=mysql_native_password
```

**Verify MySQL Running:**
```bash
docker ps | grep travian-mysql
docker logs travian-mysql

# Expected: MySQL ready for connections
```

**Test Connection:**
```bash
docker exec -it travian-mysql mysql -u travian_user -p

# Enter password: travian_pass_2025

# Inside MySQL:
SHOW DATABASES;
# Expected: travian_global listed

EXIT;
```

### Step 2.3: Convert PostgreSQL Schema to MySQL

**PostgreSQL → MySQL Type Mappings:**

| PostgreSQL | MySQL 8.0 | Notes |
|------------|-----------|-------|
| `SERIAL` | `INT AUTO_INCREMENT` | Primary keys |
| `BIGSERIAL` | `BIGINT AUTO_INCREMENT` | Large IDs |
| `VARCHAR(n)` | `VARCHAR(n)` | Same |
| `TEXT` | `TEXT` | Same |
| `INTEGER` | `INT` | Same |
| `SMALLINT` | `SMALLINT` | Same |
| `TINYINT` | `TINYINT` | MySQL native |
| `BOOLEAN` | `TINYINT(1)` | 0/1 values |
| `TIMESTAMP` | `DATETIME` | Better compatibility |
| `INTERVAL` | `TIME` | Duration fields |

**Create Global Tables (MySQL):**
```sql
-- Connect to MySQL:
docker exec -it travian-mysql mysql -u travian_user -p travian_global

-- Create gameServers table:
CREATE TABLE IF NOT EXISTS gameServers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    worldId VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    url VARCHAR(255) NOT NULL,
    speed INT NOT NULL DEFAULT 1,
    registrationKeyRequired TINYINT(1) NOT NULL DEFAULT 0,
    activationRequired TINYINT(1) NOT NULL DEFAULT 1,
    hidden TINYINT(1) NOT NULL DEFAULT 0,
    registerClosed TINYINT(1) NOT NULL DEFAULT 0,
    finished TINYINT(1) NOT NULL DEFAULT 0,
    startTime INT NOT NULL,
    endTime INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_worldId (worldId),
    INDEX idx_hidden (hidden),
    INDEX idx_finished (finished)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create configurations table:
CREATE TABLE IF NOT EXISTS configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT,
    description VARCHAR(255),
    category VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (`key`),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create activation table (CRITICAL: includes 'used' column):
CREATE TABLE IF NOT EXISTS activation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    worldId VARCHAR(50) NOT NULL,
    activationCode VARCHAR(64) NOT NULL UNIQUE,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    INDEX idx_code (activationCode),
    INDEX idx_email (email),
    INDEX idx_worldId (worldId),
    INDEX idx_used (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create banIP table:
CREATE TABLE IF NOT EXISTS banIP (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    reason TEXT,
    banned_by VARCHAR(50),
    banned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    permanent TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_ip (ip_address),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 2.4: Import Travian-Solo Complete Schema

**Clone Travian-Solo Repository:**
```bash
# In WSL2:
cd ~/projects/travian
git clone https://github.com/Ghenghis/Travian-Solo.git travian-solo

cd travian-solo
ls -la

# Expected: 3,839 PHP files, complete Travian implementation
```

**Locate SQL Files:**
```bash
find . -name "*.sql" -type f

# Expected files:
# ./install/sql/main.sql (global database)
# ./install/sql/server.sql (game world template)
# Additional schema files in install/sql/
```

**Import Main Schema:**
```bash
docker exec -i travian-mysql mysql -u travian_user -p'travian_pass_2025' travian_global < ./install/sql/main.sql

# Verify import:
docker exec -it travian-mysql mysql -u travian_user -p'travian_pass_2025' travian_global -e "SHOW TABLES;"

# Expected: 10+ tables including gameServers, configurations, activation, etc.
```

### Step 2.5: Create Game World Databases

**Create Test Server Database:**
```sql
-- Connect to MySQL:
docker exec -it travian-mysql mysql -u root -p

-- Create database:
CREATE DATABASE IF NOT EXISTS travian_testworld
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Grant permissions:
GRANT ALL PRIVILEGES ON travian_testworld.* TO 'travian_user'@'%';
FLUSH PRIVILEGES;

-- Switch to world database:
USE travian_testworld;

-- Import world schema:
SOURCE /path/to/install/sql/server.sql;

-- Verify tables:
SHOW TABLES;
-- Expected: 30-50 tables (villages, players, alliances, etc.)

EXIT;
```

**Alternative: Import via Docker:**
```bash
docker exec -i travian-mysql mysql -u travian_user -p'travian_pass_2025' -e "CREATE DATABASE IF NOT EXISTS travian_testworld CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

docker exec -i travian-mysql mysql -u travian_user -p'travian_pass_2025' travian_testworld < ./install/sql/server.sql

# Verify:
docker exec -it travian-mysql mysql -u travian_user -p'travian_pass_2025' travian_testworld -e "SHOW TABLES;"
```

**Create Demo Server Database:**
```bash
# Repeat for demo world:
docker exec -i travian-mysql mysql -u travian_user -p'travian_pass_2025' -e "CREATE DATABASE IF NOT EXISTS travian_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

docker exec -i travian-mysql mysql -u travian_user -p'travian_pass_2025' travian_demo < ./install/sql/server.sql
```

### Step 2.6: Import Replit Data

**Extract CSV Files:**
```bash
cd ~/projects/travian
tar -xzf replit-data-export.tar.gz
ls -la *.csv

# Expected: gameServers.csv, configurations.csv, activation.csv, banIP.csv
```

**Import CSV Data:**
```sql
-- Connect to MySQL:
docker exec -it travian-mysql mysql -u travian_user -p travian_global

-- Load gameServers:
LOAD DATA LOCAL INFILE '/path/to/gameServers.csv'
INTO TABLE gameServers
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(id, worldId, title, description, url, speed, registrationKeyRequired, activationRequired, hidden, registerClosed, finished, startTime, endTime);

-- Load configurations:
LOAD DATA LOCAL INFILE '/path/to/configurations.csv'
INTO TABLE configurations
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS;

-- Verify imports:
SELECT * FROM gameServers;
SELECT * FROM configurations;

EXIT;
```

**Alternative: Manual INSERT:**
```sql
-- If CSV import fails, use manual INSERTs:
INSERT INTO gameServers (worldId, title, url, speed, startTime, activationRequired)
VALUES 
  ('testworld', 'Test Server 100x', 'http://localhost/testworld/', 100, UNIX_TIMESTAMP(), 1),
  ('demo', 'Demo Server 5x', 'http://localhost/demo/', 5, UNIX_TIMESTAMP(), 1);

-- Verify:
SELECT * FROM gameServers;
```

---

## 🔧 PHASE 3: Code Conversion

### Step 3.1: Clone Complete Project

**Copy Travian-Solo to Project Root:**
```bash
cd ~/projects/travian

# If you cloned to travian-solo/, move files up:
cp -r travian-solo/* .
cp -r travian-solo/.* . 2>/dev/null || true

# Or clone directly:
# git clone https://github.com/Ghenghis/Travian-Solo.git .

# Verify structure:
ls -la

# Expected directories:
# sections/api/
# sections/servers/
# angularIndex/
# main_script/
# mailNotify/
# TaskWorker/
# install/
```

### Step 3.2: Update Database Connection Layer

**File:** `sections/api/include/Database/DB.php`

**Current (Replit/PostgreSQL):**
```php
<?php
class DB {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $host = getenv('PGHOST');
        $user = getenv('PGUSER');
        $pass = getenv('PGPASSWORD');
        $db = getenv('PGDATABASE');
        $port = getenv('PGPORT');
        
        $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
        
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DB();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
```

**Convert to MySQL:**
```php
<?php
class DB {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        // Load from .env file or environment variables
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'travian_user';
        $pass = getenv('DB_PASS') ?: 'travian_pass_2025';
        $db = getenv('DB_NAME') ?: 'travian_global';
        $port = getenv('DB_PORT') ?: '3306';
        $charset = 'utf8mb4';
        
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DB();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
```

**Apply Changes:**
```bash
# Backup original:
cp sections/api/include/Database/DB.php sections/api/include/Database/DB.php.replit.bak

# Edit file:
nano sections/api/include/Database/DB.php

# Paste MySQL version above
# Save: Ctrl+O, Enter, Ctrl+X
```

### Step 3.3: Create Environment Configuration

**Create `.env` File:**
```bash
cd ~/projects/travian

cat > .env << 'EOF'
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=travian_global
DB_USER=travian_user
DB_PASS=travian_pass_2025

# Application Configuration
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Domain Configuration
YOUR_DOMAIN=localhost
USERNAME_HERE=admin

# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=

# Email Configuration (Development - MailHog)
SMTP_HOST=localhost
SMTP_PORT=1025
SMTP_USER=
SMTP_PASSWORD=
SMTP_FROM=noreply@localhost
SMTP_FROM_NAME=Travian Server

# Email Configuration (Production - SendGrid)
# SMTP_HOST=smtp.sendgrid.net
# SMTP_PORT=587
# SMTP_USER=apikey
# SMTP_PASSWORD=SG.YOUR_API_KEY_HERE
# SMTP_FROM=noreply@yourdomain.com

# Payment Configuration (Sandbox)
STRIPE_PUBLISHABLE_KEY=pk_test_YOUR_KEY
STRIPE_SECRET_KEY=sk_test_YOUR_KEY
STRIPE_WEBHOOK_SECRET=whsec_YOUR_SECRET

PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=YOUR_CLIENT_ID
PAYPAL_CLIENT_SECRET=YOUR_CLIENT_SECRET

# Security
SESSION_LIFETIME=120
SESSION_DRIVER=redis

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
EOF

chmod 600 .env
```

**Create Environment Loader:**

**File:** `sections/api/include/loadEnv.php`
```php
<?php
/**
 * Load environment variables from .env file
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
    
    return true;
}

// Auto-load from project root
$envPath = __DIR__ . '/../../../../.env';
if (file_exists($envPath)) {
    loadEnv($envPath);
}
```

**Update Bootstrap to Load .env:**

**File:** `sections/api/include/bootstrap.php`
```php
<?php
// Load environment variables FIRST
require_once __DIR__ . '/loadEnv.php';

// Rest of bootstrap code...
require_once __DIR__ . '/Database/DB.php';
require_once __DIR__ . '/functions.php';
// ...
```

### Step 3.4: Generate connection.php Files

**Script:** `scripts/generate-connection-files.php`

```php
<?php
/**
 * Generate connection.php files for each game world
 */

// Load environment
require_once __DIR__ . '/../sections/api/include/loadEnv.php';

// Configuration
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'travian_user';
$dbPass = getenv('DB_PASS') ?: 'travian_pass_2025';

// Connect to global database
$globalDb = "travian_global";
$pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$globalDb;charset=utf8mb4", $dbUser, $dbPass);

// Get all game worlds
$stmt = $pdo->query("SELECT worldId FROM gameServers WHERE finished = 0");
$worlds = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Found " . count($worlds) . " active game worlds:\n";

foreach ($worlds as $worldId) {
    echo "Processing world: $worldId\n";
    
    // Path to world include directory
    $worldPath = __DIR__ . "/../sections/servers/$worldId/include";
    
    // Create directory if not exists
    if (!is_dir($worldPath)) {
        mkdir($worldPath, 0755, true);
        echo "  Created directory: $worldPath\n";
    }
    
    // connection.php content
    $connectionContent = <<<PHP
<?php
/**
 * Auto-generated database connection for world: $worldId
 * Generated: {date('Y-m-d H:i:s')}
 * DO NOT EDIT MANUALLY - Use scripts/generate-connection-files.php
 */

// Database configuration
\$database_host = '$dbHost';
\$database_port = '$dbPort';
\$database_user = '$dbUser';
\$database_pass = '$dbPass';
\$database_name = 'travian_$worldId';

// Create PDO connection
try {
    \$connection = new PDO(
        "mysql:host=\$database_host;port=\$database_port;dbname=\$database_name;charset=utf8mb4",
        \$database_user,
        \$database_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException \$e) {
    error_log("Database connection failed for world $worldId: " . \$e->getMessage());
    die("Database connection failed. Please contact administrator.");
}

// Legacy MySQL connection (for old code compatibility)
\$mysqli = new mysqli(\$database_host, \$database_user, \$database_pass, \$database_name, \$database_port);
if (\$mysqli->connect_error) {
    error_log("MySQL connection failed for world $worldId: " . \$mysqli->connect_error);
    die("Database connection failed. Please contact administrator.");
}
\$mysqli->set_charset('utf8mb4');

// Set timezone
date_default_timezone_set('UTC');
PHP;

    // Write connection.php
    $connectionFile = "$worldPath/connection.php";
    file_put_contents($connectionFile, $connectionContent);
    chmod($connectionFile, 0644);
    
    echo "  ✓ Generated: $connectionFile\n";
}

echo "\n✓ All connection.php files generated successfully!\n";
```

**Run Script:**
```bash
cd ~/projects/travian
php scripts/generate-connection-files.php

# Expected output:
# Found 2 active game worlds:
# Processing world: testworld
#   Created directory: /home/.../sections/servers/testworld/include
#   ✓ Generated: /home/.../sections/servers/testworld/include/connection.php
# Processing world: demo
#   Created directory: /home/.../sections/servers/demo/include
#   ✓ Generated: /home/.../sections/servers/demo/include/connection.php
#
# ✓ All connection.php files generated successfully!
```

### Step 3.5: Update SQL Queries (PostgreSQL → MySQL)

**Common Query Conversions:**

| PostgreSQL | MySQL | Change Type |
|------------|-------|-------------|
| `RETURNING id` | Use `LAST_INSERT_ID()` | Remove RETURNING clause |
| `NOW()` at insert | `NOW()` or `CURRENT_TIMESTAMP` | Same, but check timezone |
| `ILIKE` | `LIKE` (case-insensitive collation) | Change operator |
| `BOOLEAN` | `TINYINT(1)` | Change type in queries |
| `::integer` casting | `CAST(col AS SIGNED)` | Change syntax |

**Example Conversion:**

**Before (PostgreSQL):**
```php
// Insert with RETURNING
$stmt = $pdo->prepare("
    INSERT INTO activation (email, username, password, worldId, activationCode, used)
    VALUES (:email, :username, :password, :worldId, :code, 0)
    RETURNING id
");
$stmt->execute($params);
$newId = $stmt->fetchColumn();
```

**After (MySQL):**
```php
// Insert without RETURNING
$stmt = $pdo->prepare("
    INSERT INTO activation (email, username, password, worldId, activationCode, used)
    VALUES (:email, :username, :password, :worldId, :code, 0)
");
$stmt->execute($params);
$newId = $pdo->lastInsertId();
```

**Files to Update:**
```bash
# Find all PostgreSQL-specific syntax:
cd ~/projects/travian

grep -r "RETURNING" sections/api/ --include="*.php" | wc -l
grep -r "ILIKE" sections/api/ --include="*.php" | wc -l
grep -r "::" sections/api/ --include="*.php" | grep -v "http://" | wc -l

# Update each file identified
# Use search/replace in your editor
```

### Step 3.6: Update Global Configuration

**File:** `sections/globalConfig.php`

**Current (Replit):**
```php
<?php
// Database configuration from environment
$dbConfig = [
    'host' => getenv('PGHOST'),
    'user' => getenv('PGUSER'),
    'password' => getenv('PGPASSWORD'),
    'database' => getenv('PGDATABASE'),
    'port' => getenv('PGPORT'),
];

// Application settings
$appUrl = 'https://YOUR-REPL-NAME.YOUR-USERNAME.repl.co';
```

**Convert to Docker:**
```php
<?php
// Load environment variables
require_once __DIR__ . '/api/include/loadEnv.php';

// Database configuration
$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'user' => getenv('DB_USER') ?: 'travian_user',
    'password' => getenv('DB_PASS') ?: 'travian_pass_2025',
    'database' => getenv('DB_NAME') ?: 'travian_global',
    'port' => getenv('DB_PORT') ?: '3306',
    'charset' => 'utf8mb4',
];

// Application settings
$appUrl = getenv('APP_URL') ?: 'http://localhost';
$appEnv = getenv('APP_ENV') ?: 'local';
$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);

// Domain configuration
$yourDomain = getenv('YOUR_DOMAIN') ?: 'localhost';
$username = getenv('USERNAME_HERE') ?: 'admin';

// Redis configuration
$redisConfig = [
    'host' => getenv('REDIS_HOST') ?: 'localhost',
    'port' => getenv('REDIS_PORT') ?: 6379,
    'password' => getenv('REDIS_PASSWORD') ?: null,
];

// Email configuration
$emailConfig = [
    'host' => getenv('SMTP_HOST') ?: 'localhost',
    'port' => getenv('SMTP_PORT') ?: 1025,
    'user' => getenv('SMTP_USER') ?: '',
    'password' => getenv('SMTP_PASSWORD') ?: '',
    'from' => getenv('SMTP_FROM') ?: 'noreply@localhost',
    'fromName' => getenv('SMTP_FROM_NAME') ?: 'Travian Server',
];

// Paths
$baseDir = __DIR__;
$gpackDir = $baseDir . '/gpack';
```

### Step 3.7: Fix Path References

**Find Hardcoded Paths:**
```bash
grep -r "/travian/" sections/ --include="*.php" | head -20
grep -r "C:\\\\" sections/ --include="*.php" | head -20
```

**Replace with Relative Paths:**
```bash
# Backup before mass replace:
find sections/ -name "*.php" -exec cp {} {}.bak \;

# Replace absolute paths (careful!):
find sections/ -name "*.php" -exec sed -i 's|/travian/|__DIR__ . "/|g' {} \;

# Verify changes:
git diff sections/
```

---

## 🐳 PHASE 4: Service Configuration

### Step 4.1: Create Docker Compose File

**File:** `docker-compose.yml`

```yaml
version: '3.8'

networks:
  travian-network:
    driver: bridge

volumes:
  travian-mysql-data:
    driver: local
  travian-redis-data:
    driver: local

services:
  # MySQL 8.0 Database
  mysql:
    image: mysql:8.0
    container_name: travian-mysql
    networks:
      - travian-network
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: travian_root_pass_2025
      MYSQL_DATABASE: travian_global
      MYSQL_USER: travian_user
      MYSQL_PASSWORD: travian_pass_2025
    volumes:
      - travian-mysql-data:/var/lib/mysql
      - ./install/sql:/docker-entrypoint-initdb.d:ro
    command:
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
      - --default-authentication-plugin=mysql_native_password
      - --max_connections=500
      - --innodb_buffer_pool_size=1G
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-ptravian_root_pass_2025"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Redis Cache
  redis:
    image: redis:7-alpine
    container_name: travian-redis
    networks:
      - travian-network
    ports:
      - "6379:6379"
    volumes:
      - travian-redis-data:/data
    command: redis-server --appendonly yes --requirepass ""
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  # PHP-FPM 8.2
  php-fpm:
    image: php:8.2-fpm
    container_name: travian-php-fpm
    networks:
      - travian-network
    volumes:
      - .:/var/www/travian
      - ./docker/php/php.ini:/usr/local/etc/php/php.ini:ro
      - ./docker/php/www.conf:/usr/local/etc/php-fpm.d/www.conf:ro
    working_dir: /var/www/travian
    environment:
      DB_HOST: mysql
      DB_PORT: 3306
      DB_NAME: travian_global
      DB_USER: travian_user
      DB_PASS: travian_pass_2025
      REDIS_HOST: redis
      REDIS_PORT: 6379
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    restart: unless-stopped
    command: >
      sh -c "
        apt-get update &&
        apt-get install -y libzip-dev zip unzip git libpng-dev libjpeg-dev &&
        docker-php-ext-install pdo pdo_mysql mysqli zip gd &&
        pecl install redis &&
        docker-php-ext-enable redis &&
        php-fpm
      "

  # Nginx Web Server
  nginx:
    image: nginx:alpine
    container_name: travian-nginx
    networks:
      - travian-network
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - .:/var/www/travian:ro
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - php-fpm
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "wget", "-q", "--spider", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  # MailHog (Development Email Testing)
  mailhog:
    image: mailhog/mailhog
    container_name: travian-mailhog
    networks:
      - travian-network
    ports:
      - "1025:1025"  # SMTP
      - "8025:8025"  # Web UI
    restart: unless-stopped

  # PhpMyAdmin
  phpmyadmin:
    image: phpmyadmin:latest
    container_name: travian-phpmyadmin
    networks:
      - travian-network
    ports:
      - "8080:80"
    environment:
      PMA_HOST: mysql
      PMA_PORT: 3306
      PMA_USER: travian_user
      PMA_PASSWORD: travian_pass_2025
    depends_on:
      - mysql
    restart: unless-stopped

  # Cron Task Worker
  taskworker:
    image: php:8.2-cli
    container_name: travian-taskworker
    networks:
      - travian-network
    volumes:
      - .:/var/www/travian
      - ./docker/cron/crontab:/etc/cron.d/travian-cron:ro
    working_dir: /var/www/travian
    environment:
      DB_HOST: mysql
      DB_PORT: 3306
      DB_USER: travian_user
      DB_PASS: travian_pass_2025
    depends_on:
      mysql:
        condition: service_healthy
    restart: unless-stopped
    command: >
      sh -c "
        apt-get update &&
        apt-get install -y cron &&
        crontab /etc/cron.d/travian-cron &&
        cron -f
      "
```

### Step 4.2: Create Nginx Configuration

**File:** `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name localhost;
    
    root /var/www/travian;
    index index.php index.html;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Logging
    access_log /var/log/nginx/travian-access.log;
    error_log /var/log/nginx/travian-error.log warn;
    
    # API endpoints
    location ~ ^/v1/ {
        try_files $uri /sections/api/index.php$is_args$args;
    }
    
    # Angular frontend
    location / {
        root /var/www/travian/angularIndex/browser;
        try_files $uri $uri/ /index.html;
    }
    
    # PHP-FPM processing
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass php-fpm:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        
        # Timeouts
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }
    
    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # Health check endpoint
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ ^/(\.env|\.git|docker|install|scripts) {
        deny all;
    }
}
```

### Step 4.3: Create PHP Configuration

**File:** `docker/php/php.ini`

```ini
[PHP]
; Performance
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
post_max_size = 64M
upload_max_filesize = 64M

; Error handling
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = /var/log/php/error.log

; Session
session.save_handler = redis
session.save_path = "tcp://redis:6379"
session.gc_maxlifetime = 7200
session.cookie_lifetime = 0
session.cookie_secure = Off
session.cookie_httponly = On

; Security
expose_php = Off
allow_url_fopen = On
allow_url_include = Off

; Database
mysqli.default_host = mysql
mysqli.default_port = 3306
pdo_mysql.default_socket = /var/run/mysqld/mysqld.sock

; Timezone
date.timezone = UTC

; Character encoding
default_charset = "UTF-8"
```

### Step 4.4: Create Cron Jobs

**File:** `docker/cron/crontab`

```bash
# Travian Task Worker Cron Jobs
# Format: minute hour day month weekday command

# Game tick (every 1 minute)
* * * * * php /var/www/travian/TaskWorker/tick.php >> /var/log/travian-tick.log 2>&1

# Clean expired sessions (every 30 minutes)
*/30 * * * * php /var/www/travian/TaskWorker/cleanup-sessions.php >> /var/log/travian-cleanup.log 2>&1

# Process email queue (every 5 minutes)
*/5 * * * * php /var/www/travian/mailNotify/process-queue.php >> /var/log/travian-email.log 2>&1

# Generate reports (daily at 3 AM)
0 3 * * * php /var/www/travian/TaskWorker/daily-reports.php >> /var/log/travian-reports.log 2>&1

# Database backup (daily at 4 AM)
0 4 * * * mysqldump -h mysql -u travian_user -ptravian_pass_2025 --all-databases > /var/www/travian/backups/db-$(date +\%Y\%m\%d).sql

# Clean old backups (weekly on Sunday at 5 AM)
0 5 * * 0 find /var/www/travian/backups -name "db-*.sql" -mtime +7 -delete

# Empty line required at end of crontab

```

### Step 4.5: Start All Services

```bash
cd ~/projects/travian

# Create required directories:
mkdir -p docker/nginx docker/php docker/cron backups logs

# Start all containers:
docker-compose up -d

# View logs:
docker-compose logs -f

# Check status:
docker-compose ps

# Expected: All services "healthy" or "running"
```

---

## ✅ PHASE 5: Testing & Validation

### Step 5.1: Database Connectivity Test

```bash
# Test MySQL from host:
docker exec -it travian-mysql mysql -u travian_user -p'travian_pass_2025' -e "SHOW DATABASES;"

# Expected databases:
# - travian_global
# - travian_testworld
# - travian_demo

# Test from PHP container:
docker exec -it travian-php-fpm php -r "
  \$pdo = new PDO('mysql:host=mysql;dbname=travian_global', 'travian_user', 'travian_pass_2025');
  echo 'Connection successful!' . PHP_EOL;
  \$stmt = \$pdo->query('SELECT COUNT(*) FROM gameServers');
  echo 'Game servers: ' . \$stmt->fetchColumn() . PHP_EOL;
"

# Expected: Connection successful! Game servers: 2
```

### Step 5.2: API Endpoints Test

```bash
# Test loadConfig endpoint:
curl -s http://localhost/v1/loadConfig | jq .

# Expected: JSON with success: true

# Test loadServers endpoint:
curl -s -X POST http://localhost/v1/servers/loadServers \
  -H "Content-Type: application/json" \
  -d '{"lang":"international"}' | jq .

# Expected: JSON with gameWorlds array containing testworld and demo

# Test registration page:
curl -s http://localhost/v1/register/register | jq .

# Expected: JSON with registration form data
```

### Step 5.3: Frontend Test

```bash
# Access Angular app:
xdg-open http://localhost

# Expected: Travian homepage loads
# Check browser console for errors (should be none)

# Test navigation:
# - Click "Register" → Registration form appears
# - Select server → Test Server 100x / Demo Server 5x visible
# - View server list → Both servers displayed
```

### Step 5.4: Registration Flow Test

**Step 1: Submit Registration:**
```bash
curl -X POST http://localhost/v1/register/submit \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "username": "testplayer",
    "password": "Test123!@#",
    "confirmPassword": "Test123!@#",
    "worldId": "testworld"
  }' | jq .

# Expected: {"success": true, "message": "Activation email sent"}
```

**Step 2: Check Email (MailHog):**
```bash
# Open MailHog UI:
xdg-open http://localhost:8025

# Expected: Email with activation link visible
```

**Step 3: Verify Database Entry:**
```bash
docker exec -it travian-mysql mysql -u travian_user -p'travian_pass_2025' travian_global -e "
  SELECT id, email, username, worldId, used, created_at 
  FROM activation 
  WHERE email = 'test@example.com';
"

# Expected: Row with used = 0
```

**Step 4: Extract Activation Code:**
```bash
# Get activation code from database:
ACTIVATION_CODE=$(docker exec -it travian-mysql mysql -u travian_user -p'travian_pass_2025' travian_global -sN -e "
  SELECT activationCode FROM activation WHERE email = 'test@example.com';
")

echo $ACTIVATION_CODE
```

**Step 5: Activate Account:**
```bash
curl -X GET "http://localhost/v1/activate?code=$ACTIVATION_CODE" | jq .

# Expected: {"success": true, "message": "Account activated"}
```

**Step 6: Verify Activation:**
```bash
docker exec -it travian-mysql mysql -u travian_user -p'travian_pass_2025' travian_global -e "
  SELECT used FROM activation WHERE email = 'test@example.com';
"

# Expected: used = 1

# Check user created in game world:
docker exec -it travian-mysql mysql -u travian_user -p'travian_pass_2025' travian_testworld -e "
  SELECT id, username, email FROM users WHERE username = 'testplayer';
"

# Expected: User row exists
```

### Step 5.5: Login Flow Test

```bash
# Test login:
curl -X POST http://localhost/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testplayer",
    "password": "Test123!@#",
    "worldId": "testworld"
  }' -c cookies.txt | jq .

# Expected: {"success": true, "token": "...", "userId": 1}

# Test authenticated request:
curl -X GET http://localhost/v1/user/profile \
  -b cookies.txt | jq .

# Expected: User profile data
```

### Step 5.6: Performance Test

**Simple Load Test:**
```bash
# Install Apache Bench:
sudo apt install -y apache2-utils

# Test API endpoint (100 requests, 10 concurrent):
ab -n 100 -c 10 http://localhost/v1/loadConfig

# Expected metrics:
# - Requests per second: >100
# - Mean response time: <50ms
# - 95th percentile: <200ms
```

**Database Query Performance:**
```bash
docker exec -it travian-mysql mysql -u travian_user -p'travian_pass_2025' travian_global -e "
  EXPLAIN SELECT * FROM gameServers WHERE worldId = 'testworld';
"

# Expected: Using index on worldId
```

### Step 5.7: Redis Cache Test

```bash
# Connect to Redis:
docker exec -it travian-redis redis-cli

# Set test key:
SET test:key "test_value"
GET test:key
# Expected: "test_value"

# Check session storage:
KEYS sess:*
# Expected: Session keys if user logged in

# Exit Redis:
EXIT
```

---

## 🤖 PHASE 6: AI Integration (OPTIONAL)

### Step 6.1: Prerequisites Check

**Before proceeding, ensure:**
- ✅ Registration working (Phase 5.4 complete)
- ✅ Login working (Phase 5.5 complete)
- ✅ Database stable (no errors in logs)
- ✅ All API endpoints responding (<200ms)
- ✅ GPU available (NVIDIA RTX 3090 Ti or similar)

**Check GPU:**
```bash
# From Windows PowerShell:
nvidia-smi

# Expected: GPU listed with 24GB VRAM

# Install NVIDIA Container Toolkit:
wsl
distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
curl -s -L https://nvidia.github.io/nvidia-docker/gpgkey | sudo apt-key add -
curl -s -L https://nvidia.github.io/nvidia-docker/$distribution/nvidia-docker.list | sudo tee /etc/apt/sources.list.d/nvidia-docker.list

sudo apt-get update
sudo apt-get install -y nvidia-container-toolkit
sudo systemctl restart docker
```

### Step 6.2: Reference Existing AI Documentation

**Complete AI framework documentation exists in:**
```
docs/AI/
├── AI-FRAMEWORK-MASTER-BLUEPRINT.md (Master guide)
├── NPC-PERSONALITY-ENGINE.md
├── DECISION-TREE-HYBRID.md
├── LLAMA-INFERENCE-OPTIMIZATION.md
├── GAME-STATE-CONTEXT-COMPRESSION.md
├── ... (16 more AI-specific guides)
```

**Quick Start:**
```bash
# Read AI master blueprint:
cat docs/AI/AI-FRAMEWORK-MASTER-BLUEPRINT.md

# Follow implementation phases:
# 1. Install local LLM (Llama 3.1 8B)
# 2. Set up inference server (TGI/vLLM)
# 3. Integrate with game backend
# 4. Deploy 50-500 NPC agents
# 5. Monitor performance (<200ms target)
```

### Step 6.3: Local LLM Setup (Summary)

**Install Text Generation Inference (TGI):**
```bash
docker pull ghcr.io/huggingface/text-generation-inference:latest

docker run -d \
  --name travian-llm \
  --gpus all \
  --network travian-network \
  -p 8080:80 \
  -v /mnt/models:/data \
  -e MODEL_ID=meta-llama/Llama-3.1-8B-Instruct \
  -e MAX_TOTAL_TOKENS=4096 \
  -e MAX_BATCH_PREFILL_TOKENS=4096 \
  ghcr.io/huggingface/text-generation-inference:latest

# Verify LLM running:
curl http://localhost:8080/health

# Expected: {"status": "ok"}
```

### Step 6.4: AI NPC Integration (Summary)

**Architecture:**
- 95% Rule-based decisions (instant)
- 5% LLM decisions (strategy, diplomacy, emergencies)
- Batched inference (10-50 requests/batch)
- Context compression (<512 tokens/request)

**Implementation:**
```bash
# Install AI PHP client:
composer require guzzlehttp/guzzle

# Configure AI endpoint in .env:
echo "AI_LLM_ENDPOINT=http://localhost:8080/generate" >> .env
echo "AI_ENABLED=true" >> .env
echo "AI_NPC_COUNT=50" >> .env
```

**For complete AI implementation, see:**
- [docs/AI/AI-FRAMEWORK-MASTER-BLUEPRINT.md](../AI/AI-FRAMEWORK-MASTER-BLUEPRINT.md)
- [docs/local/AI-INTEGRATION-PRECHECK.md](./AI-INTEGRATION-PRECHECK.md)
- [docs/local/AI-FRAMEWORK-ACTIVATION.md](./AI-FRAMEWORK-ACTIVATION.md)

---

## 🔒 Security Hardening

### Production Checklist

**Database Security:**
- [ ] Change default passwords in `.env`
- [ ] Restrict MySQL to Docker network only (`127.0.0.1:3306`)
- [ ] Enable MySQL binary logging for backups
- [ ] Set up automated database backups (daily)

**Application Security:**
- [ ] Disable `APP_DEBUG` in production (`.env`)
- [ ] Enable HTTPS with Let's Encrypt SSL certificate
- [ ] Configure firewall (UFW) to allow only 80/443
- [ ] Set up fail2ban for brute-force protection
- [ ] Implement rate limiting in Nginx
- [ ] Enable PHP OPcache for performance
- [ ] Sanitize all user inputs (prepared statements)
- [ ] Add CSRF protection tokens

**Docker Security:**
- [ ] Run containers as non-root user
- [ ] Use read-only file systems where possible
- [ ] Scan images for vulnerabilities (`docker scan`)
- [ ] Keep Docker & images updated
- [ ] Limit container resources (CPU/memory limits)

**Monitoring:**
- [ ] Set up log aggregation (ELK stack or Graylog)
- [ ] Configure uptime monitoring (UptimeRobot)
- [ ] Enable MySQL slow query log
- [ ] Set up alerts for errors/downtime
- [ ] Monitor disk space usage

---

## 📊 Performance Optimization

### Target Metrics

| Metric | Target | Acceptable | Critical |
|--------|--------|------------|----------|
| API Response | <50ms | <200ms | >500ms |
| Database Query | <10ms | <50ms | >100ms |
| Page Load | <1s | <3s | >5s |
| CPU Usage | <40% | <70% | >90% |
| Memory Usage | <60% | <80% | >95% |
| NPC Decision | <200ms | <500ms | >1s |

### Optimization Strategies

**1. Database Optimization:**
```sql
-- Add missing indexes:
CREATE INDEX idx_activation_used ON activation(used);
CREATE INDEX idx_users_worldId ON users(worldId);
CREATE INDEX idx_villages_playerId ON villages(playerId);

-- Optimize tables:
OPTIMIZE TABLE activation, users, villages;

-- Analyze query performance:
EXPLAIN SELECT * FROM villages WHERE playerId = 123;
```

**2. Redis Caching:**
```php
// Cache game servers list (30 minutes):
$redis = new Redis();
$redis->connect('redis', 6379);

$key = 'gameServers:list';
if (!$servers = $redis->get($key)) {
    $servers = $db->query("SELECT * FROM gameServers")->fetchAll();
    $redis->setex($key, 1800, serialize($servers));
}
```

**3. PHP OPcache:**
```ini
; Add to docker/php/php.ini:
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.fast_shutdown=1
```

**4. Nginx Optimization:**
```nginx
# Add to docker/nginx/default.conf:
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css application/json application/javascript;

# Enable HTTP/2:
listen 443 ssl http2;
```

---

## 🔄 Rollback Procedure

### If Migration Fails

**Step 1: Stop Docker Services:**
```bash
cd ~/projects/travian
docker-compose down
```

**Step 2: Preserve Data:**
```bash
# Backup MySQL data:
docker run --rm -v travian-mysql-data:/data -v ~/backups:/backup alpine tar czf /backup/mysql-backup.tar.gz /data

# Backup project files:
tar czf ~/travian-docker-backup.tar.gz ~/projects/travian
```

**Step 3: Revert to Replit:**
```bash
# Your Replit project is unchanged
# Simply continue using Replit until issues resolved
```

**Step 4: Debug Locally:**
```bash
# Check logs:
docker-compose logs mysql
docker-compose logs php-fpm
docker-compose logs nginx

# Fix issues, then retry migration
```

---

## 📋 Post-Migration Checklist

### Functional Verification

- [ ] All Docker containers running (`docker-compose ps`)
- [ ] MySQL accessible with correct databases
- [ ] Redis cache responding to commands
- [ ] Nginx serving frontend on port 80
- [ ] API endpoints returning valid JSON
- [ ] PhpMyAdmin accessible at :8080
- [ ] MailHog catching test emails at :8025

### Application Testing

- [ ] Homepage loads without errors
- [ ] Server list displays correctly
- [ ] Registration form accepts submissions
- [ ] Activation emails delivered (MailHog)
- [ ] Activation links work (set `used=1`)
- [ ] User created in game world database
- [ ] Login successful with credentials
- [ ] Session persists across requests
- [ ] Game interface accessible after login

### Database Integrity

- [ ] All tables exist in `travian_global`
- [ ] All tables exist in `travian_testworld`
- [ ] All tables exist in `travian_demo`
- [ ] Foreign key constraints valid
- [ ] Indexes created on critical columns
- [ ] Sample data migrated correctly
- [ ] connection.php files generated

### Performance Validation

- [ ] API response time <200ms
- [ ] Database queries <50ms
- [ ] Page load time <3s
- [ ] No memory leaks (check `docker stats`)
- [ ] CPU usage <70% under load
- [ ] Disk I/O acceptable

### Security Validation

- [ ] Default passwords changed in `.env`
- [ ] Database not exposed to public (localhost only)
- [ ] `.env` file not in Git repository
- [ ] Error reporting disabled in production
- [ ] SQL injection protection verified
- [ ] CSRF protection enabled
- [ ] Session security configured

### Monitoring Setup

- [ ] Docker logs rotating properly
- [ ] Application logs writing to files
- [ ] Cron jobs executing on schedule
- [ ] Email queue processing regularly
- [ ] Database backups running daily
- [ ] Disk space monitoring configured

---

## 📚 Additional Resources

### Documentation References

**Local Deployment Guides:**
- [LOCAL-OVERVIEW.md](./LOCAL-OVERVIEW.md) - Master roadmap
- [WINDOWS-WSL2-DOCKER.md](./WINDOWS-WSL2-DOCKER.md) - Windows setup
- [MYSQL-INFRASTRUCTURE.md](./MYSQL-INFRASTRUCTURE.md) - MySQL configuration
- [GLOBAL-SCHEMA-RESTORE.md](./GLOBAL-SCHEMA-RESTORE.md) - Database setup
- [GAME-WORLD-DATABASES.md](./GAME-WORLD-DATABASES.md) - World databases
- [CONNECTION-PHP-GENERATOR.md](./CONNECTION-PHP-GENERATOR.md) - Connection files
- [EMAIL-DELIVERY.md](./EMAIL-DELIVERY.md) - SMTP setup
- [INDEX.md](./INDEX.md) - Documentation index

**AI Integration Guides:**
- [docs/AI/AI-FRAMEWORK-MASTER-BLUEPRINT.md](../AI/AI-FRAMEWORK-MASTER-BLUEPRINT.md)
- [AI-INTEGRATION-PRECHECK.md](./AI-INTEGRATION-PRECHECK.md)
- [AI-FRAMEWORK-ACTIVATION.md](./AI-FRAMEWORK-ACTIVATION.md)

### External Resources

- **Docker Documentation:** https://docs.docker.com/
- **MySQL 8.0 Reference:** https://dev.mysql.com/doc/refman/8.0/en/
- **PHP 8.2 Manual:** https://www.php.net/manual/en/
- **Nginx Documentation:** https://nginx.org/en/docs/
- **Redis Documentation:** https://redis.io/documentation
- **Travian Wiki:** https://travian.fandom.com/wiki/

---

## 🎯 Success Criteria

Your migration is **COMPLETE** when:

✅ All 6 phases finished without errors  
✅ Registration → Activation → Login flow working  
✅ Both game worlds (testworld, demo) accessible  
✅ Email delivery functional (MailHog or SMTP)  
✅ API response times <200ms consistently  
✅ No errors in Docker logs for 24 hours  
✅ Database backups running automatically  
✅ Can create and activate 10+ test accounts  
✅ Performance metrics meet targets  
✅ Post-migration checklist 100% complete  

**Optional (AI-Enhanced):**
✅ Local LLM inference <500ms per request  
✅ 50+ NPC agents active in game  
✅ AI decision-making integrated seamlessly  
✅ GPU utilization 40-60% under NPC load  

---

## 🆘 Troubleshooting

### Common Issues & Solutions

**Issue:** Docker containers won't start  
**Solution:** Check WSL2 running, restart Docker Desktop, verify resources allocated

**Issue:** MySQL connection refused  
**Solution:** Verify container running (`docker ps`), check port 3306 not in use, confirm credentials in `.env`

**Issue:** API returns 500 errors  
**Solution:** Check PHP-FPM logs (`docker logs travian-php-fpm`), verify database connection, check file permissions

**Issue:** Nginx 502 Bad Gateway  
**Solution:** Verify PHP-FPM running, check socket connection, restart nginx container

**Issue:** Registration fails silently  
**Solution:** Check `activation` table has `used` column, verify SMTP configured, check PHP error logs

**Issue:** Login returns "userDoesNotExists"  
**Solution:** Verify user created in game world database, check connection.php file exists, confirm worldId matches

**Issue:** Sessions not persisting  
**Solution:** Verify Redis running, check PHP session.save_handler configured, test Redis connection

**Issue:** Slow performance  
**Solution:** Add database indexes, enable Redis caching, configure PHP OPcache, check Docker resource limits

---

## 📞 Support

**For Issues:**
1. Check [INDEX.md](./INDEX.md) for relevant guide
2. Review Docker logs: `docker-compose logs`
3. Check database: `docker exec -it travian-mysql mysql -u travian_user -p`
4. Test API: `curl http://localhost/v1/loadConfig | jq .`
5. Consult Travian-Solo repository issues: https://github.com/Ghenghis/Travian-Solo/issues

**For AI Integration:**
- See [docs/AI/AI-FRAMEWORK-MASTER-BLUEPRINT.md](../AI/AI-FRAMEWORK-MASTER-BLUEPRINT.md)
- GPU setup guide in WINDOWS-WSL2-DOCKER.md
- LLM optimization in docs/AI/LLAMA-INFERENCE-OPTIMIZATION.md

---

## 📜 Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-10-29 | Initial enterprise-grade conversion blueprint |

---

**🎉 You're ready to convert TravianT4.6 from Replit to Docker/Windows!**

Start with **Phase 1** and follow each step carefully. Good luck! 🚀

---

**Document End**
