# Project Bootstrap - Repository Setup and Configuration

## 🎯 Purpose

This guide walks you through:
- Cloning the TravianT4.6 repository to the optimal location
- Understanding the project directory structure
- Creating and configuring environment variables
- Setting up proper file permissions
- Preparing for Docker deployment

**Estimated Time:** 1 hour

---

## 📋 Prerequisites

Before starting, ensure you've completed:

- ✅ Guide 2: WINDOWS-WSL2-DOCKER.md
- ✅ WSL2 is running with Ubuntu 22.04
- ✅ Docker Desktop is running
- ✅ Git is installed in WSL2

---

## Section 1: Clone the Repository

### Step 1.1: Choose the Optimal Location

**❌ Common Mistake:**
```bash
cd /mnt/c/Users/YourName/Desktop
git clone ...
```

**Why this fails:**
- WSL2 accessing Windows file system (`/mnt/c`) is 10-100x slower
- Docker volumes perform poorly on Windows file system
- File watching doesn't work properly
- Build times are significantly longer

**✅ Correct Approach:**
```bash
cd ~
mkdir -p Projects
cd Projects
```

**Why this works:**
- Uses native Linux file system (ext4)
- 10-100x faster I/O operations
- Proper file watching
- Docker volumes are much faster

### Step 1.2: Clone the Travian-Solo Repository

```bash
git clone https://github.com/Ghenghis/Travian-Solo.git TravianT4.6
cd TravianT4.6
```

**Expected output:**
```
Cloning into 'TravianT4.6'...
remote: Enumerating objects: XXXX, done.
remote: Counting objects: 100% (XXXX/XXXX), done.
remote: Compressing objects: 100% (XXXX/XXXX), done.
Receiving objects: 100% (XXXX/XXXX), XX.XX MiB | XX.XX MiB/s, done.
Resolving deltas: 100% (XXXX/XXXX), done.
```

**⏱️ Clone time:** 2-5 minutes depending on internet speed

### Step 1.3: Verify Repository Contents

```bash
ls -la
```

**Expected directories:**
```
angularIndex/         - Pre-compiled Angular frontend
database/            - SQL schemas and migrations
main_script/         - Core game logic
mailNotify/          - Email notification service
sections/            - PHP backend (API, servers, PMA)
TaskWorker/          - Background task processor
```

### Step 1.4: Check Repository Size

```bash
du -sh .
```

**Expected size:** 50-100 MB

---

## Section 2: Understanding the Project Structure

### Directory Tree

```
TravianT4.6/
├── angularIndex/
│   └── browser/              # Pre-compiled Angular app (production-ready)
│       ├── index.html
│       ├── main.*.js
│       ├── polyfills.*.js
│       └── assets/
│
├── sections/
│   ├── api/                  # RESTful API Backend
│   │   ├── include/          # PHP libraries (FastRoute, Twig, etc.)
│   │   │   ├── Database/     # DB connection layer
│   │   │   ├── Api/          # API controllers
│   │   │   └── functions.php # Utility functions
│   │   ├── index.php         # API entry point
│   │   └── bootstrap.php     # API initialization
│   │
│   ├── servers/              # Game World Servers
│   │   ├── dev/              # Development world template
│   │   ├── testworld/        # Test world (100x speed) - TO BE CREATED
│   │   └── demo/             # Demo world (5x speed) - TO BE CREATED
│   │
│   ├── pma/                  # PhpMyAdmin (Database Management GUI)
│   │   └── index.php
│   │
│   └── globalConfig.php      # Global configuration file
│
├── main_script/              # Core Game Logic
│   ├── game/                 # Game mechanics
│   ├── database/             # Database queries
│   └── utils/                # Utilities
│
├── mailNotify/               # Email Notification Service
│   ├── include/              # SendInBlue API
│   └── config.php
│
├── TaskWorker/               # Background Task Processor
│   ├── include/              # Guzzle, Cloudflare SDK
│   └── worker.php
│
├── database/                 # SQL Schemas
│   ├── main.sql              # Complete Travian-Solo schema (CRITICAL!)
│   └── migrations/
│
├── router.php                # Custom routing for PHP built-in server
├── composer.json             # PHP dependencies
└── .env.example              # Environment variables template
```

### Key Files Explained

**1. `sections/globalConfig.php`**
- Global configuration shared by all components
- Database connection parameters
- Domain settings
- Security settings

**2. `sections/api/index.php`**
- API entry point
- Routes incoming API requests (`/v1/*`)
- Returns JSON responses

**3. `database/main.sql`**
- **MOST IMPORTANT FILE FOR DATABASE SETUP**
- Contains complete Travian-Solo MySQL schema
- 100+ tables for game functionality
- Sample data for testing

**4. `router.php`**
- Custom router for PHP built-in server
- Routes `/v1/*` to API
- Serves static files from `angularIndex/browser/`
- Handles Angular SPA routing

---

## Section 3: Create Environment Variables

### Step 3.1: Understand Environment Variables

Environment variables store configuration that changes between environments (dev/production) without modifying code:

- Database credentials
- API keys
- Domain names
- Secret keys
- Feature flags

### Step 3.2: Create .env File

```bash
nano .env
```

Paste this template:

```env
# Project Settings
PROJECT_NAME=TravianT4.6
ENVIRONMENT=local

# Domain Configuration
YOUR_DOMAIN=localhost
REPLIT_DOMAIN=${YOUR_DOMAIN}

# Database Configuration (MySQL 8.0)
DB_HOST=mysql
DB_PORT=3306
DB_USER=travian_admin
DB_PASSWORD=SuperSecurePassword123!
DB_GLOBAL=travian_global

# Database Root User (for schema creation)
DB_ROOT_PASSWORD=RootPassword456!

# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379

# SMTP Configuration (Development - MailHog)
SMTP_HOST=mailhog
SMTP_PORT=1025
SMTP_USER=
SMTP_PASSWORD=
SMTP_FROM=noreply@localhost

# SMTP Configuration (Production - SendGrid)
# Uncomment these when ready to send real emails
# SMTP_HOST=smtp.sendgrid.net
# SMTP_PORT=587
# SMTP_USER=apikey
# SMTP_PASSWORD=SG.your-api-key-here
# SMTP_FROM=noreply@yourdomain.com

# Payment Configuration (Stripe Sandbox)
STRIPE_PUBLISHABLE_KEY=pk_test_YOUR_KEY_HERE
STRIPE_SECRET_KEY=sk_test_YOUR_KEY_HERE
STRIPE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET_HERE

# Payment Configuration (PayPal Sandbox)
PAYPAL_CLIENT_ID=YOUR_CLIENT_ID_HERE
PAYPAL_CLIENT_SECRET=YOUR_CLIENT_SECRET_HERE
PAYPAL_MODE=sandbox

# Security
SESSION_SECRET=ChangeThisToRandomString32CharsLong
JWT_SECRET=AnotherRandomString32CharsForJWT

# Game Configuration
GAME_SPEED_TESTWORLD=100
GAME_SPEED_DEMO=5

# AI Configuration (For later use)
AI_ENABLED=false
AI_LLM_ENDPOINT=http://localhost:11434
AI_LLM_MODEL=llama2
AI_NPC_COUNT=0
AI_MAX_CONCURRENT=10

# Docker Configuration
COMPOSE_PROJECT_NAME=travian
DOCKER_BUILDKIT=1
```

**Save and exit:**
- Press `Ctrl + X`
- Press `Y` to confirm
- Press `Enter`

### Step 3.3: Understand Each Variable

**Database Variables:**
- `DB_HOST=mysql` - Docker container name (NOT `localhost`)
- `DB_PORT=3306` - Standard MySQL port (NOT 3307)
- `DB_USER` - Database user for the application
- `DB_PASSWORD` - Strong password for database user
- `DB_GLOBAL` - Global database name (server list, activations, etc.)

**Why `DB_HOST=mysql` not `localhost`?**
- In Docker, containers communicate via **container names**
- `mysql` is the service name in docker-compose.yml
- `localhost` would try to connect to MySQL inside the PHP container (doesn't exist)

**Redis Variables:**
- `REDIS_HOST=redis` - Docker container name
- Used for caching, session storage, queue management

**SMTP Variables (Development):**
- `SMTP_HOST=mailhog` - MailHog catches emails for testing
- No real emails sent, but you can view them in MailHog UI
- Perfect for development without needing real SMTP server

**SMTP Variables (Production):**
- SendGrid, Postfix, or other real SMTP service
- Required for sending real activation emails to users
- Guide 10 covers detailed setup

**Payment Variables:**
- Sandbox/test mode credentials initially
- Guide 12 covers obtaining these keys
- Required for premium features, subscriptions

**Security Variables:**
- `SESSION_SECRET` - Used for session encryption
- `JWT_SECRET` - Used for JWT token signing
- **Change these to random strings!**

**Generating random secrets:**

```bash
openssl rand -base64 32
```

Run this twice and replace the SESSION_SECRET and JWT_SECRET values.

---

## Section 4: Update Global Configuration

### Step 4.1: Backup Original Config

```bash
cp sections/globalConfig.php sections/globalConfig.php.backup
```

### Step 4.2: Review Current Configuration

```bash
cat sections/globalConfig.php
```

**❌ Common issues found:**
- Hardcoded database credentials
- Hardcoded paths (`/travian/`)
- No environment variable usage
- Incorrect PostgreSQL references (should be MySQL)

### Step 4.3: Create Updated Configuration

We'll update this file to use environment variables properly. Create a helper script first:

```bash
nano scripts/load-env.php
```

Paste:

```php
<?php
function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        throw new Exception(".env file not found at: $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

loadEnv();

function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    
    if ($value === 'true') return true;
    if ($value === 'false') return false;
    if ($value === 'null') return null;
    
    return $value;
}
?>
```

Save and exit.

### Step 4.4: Update globalConfig.php

```bash
nano sections/globalConfig.php
```

Find the database configuration section and update it:

```php
<?php
require_once __DIR__ . '/../scripts/load-env.php';

define('DB_HOST', env('DB_HOST', 'mysql'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_USER', env('DB_USER', 'travian_admin'));
define('DB_PASSWORD', env('DB_PASSWORD', ''));
define('DB_GLOBAL', env('DB_GLOBAL', 'travian_global'));

define('REDIS_HOST', env('REDIS_HOST', 'redis'));
define('REDIS_PORT', env('REDIS_PORT', '6379'));

define('YOUR_DOMAIN', env('YOUR_DOMAIN', 'localhost'));
define('SMTP_HOST', env('SMTP_HOST', 'mailhog'));
define('SMTP_PORT', env('SMTP_PORT', '1025'));
?>
```

Save and exit.

### Step 4.5: Update API Database Connection

```bash
nano sections/api/include/Database/DB.php
```

Find the constructor and update the DSN:

```php
public function __construct() {
    $host = env('DB_HOST', 'mysql');
    $port = env('DB_PORT', '3306');
    $dbname = env('DB_GLOBAL', 'travian_global');
    $user = env('DB_USER', 'travian_admin');
    $password = env('DB_PASSWORD', '');
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    try {
        parent::__construct($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
}
```

Save and exit.

---

## Section 5: Set Up Directory Permissions

### Step 5.1: Understand Linux Permissions

Docker containers run as specific users (often `www-data` for PHP). We need to ensure:
- PHP can read code files
- PHP can write to logs, cache, sessions
- PHP can write uploaded files

### Step 5.2: Create Required Directories

```bash
mkdir -p storage/logs
mkdir -p storage/cache
mkdir -p storage/sessions
mkdir -p storage/uploads
mkdir -p storage/backups
```

### Step 5.3: Set Permissions

```bash
chmod -R 755 angularIndex/
chmod -R 755 sections/
chmod -R 755 main_script/
chmod -R 777 storage/
```

**What these permissions mean:**
- `755` - Owner can read/write/execute, others can read/execute
- `777` - Everyone can read/write/execute (needed for Docker containers to write logs)

**🔐 Security Note:** `777` is acceptable for local development. For production, use proper user/group ownership with `775` or `770`.

---

## Section 6: Install PHP Dependencies

### Step 6.1: Verify Composer is Available

We'll use Composer inside Docker, but let's check if it's available locally:

```bash
which composer
```

If not found, we'll install it in Docker (covered in Guide 4).

### Step 6.2: Understand Composer Dependencies

The project uses Composer for PHP libraries:

**sections/api/include** - Composer dependencies:
- `nikic/fast-route` - Fast URL routing
- `twig/twig` - Template engine
- `phpmailer/phpmailer` - Email sending
- `google/recaptcha` - CAPTCHA verification

**mailNotify/include** - Composer dependencies:
- `sendinblue/api-v3-sdk` - Email service API

**TaskWorker/include** - Composer dependencies:
- `guzzlehttp/guzzle` - HTTP client
- `cloudflare/sdk` - Cloudflare API

### Step 6.3: Check Existing Installations

```bash
ls -la sections/api/include/vendor/
ls -la mailNotify/include/vendor/
ls -la TaskWorker/include/vendor/
```

If these directories exist and have content, dependencies are already installed.

If missing, we'll install them after Docker setup (Guide 4).

---

## Section 7: Prepare Docker Configuration Files

### Step 7.1: Create docker-compose.yml Stub

We'll create a detailed docker-compose.yml in Guide 13, but let's prepare the location:

```bash
touch docker-compose.yml
```

### Step 7.2: Create Dockerfile Stub

```bash
touch Dockerfile
```

These will be populated in later guides.

### Step 7.3: Create .dockerignore

```bash
nano .dockerignore
```

Paste:

```
.git
.env
.env.example
node_modules
vendor
storage/logs/*
storage/cache/*
storage/sessions/*
*.log
.DS_Store
Thumbs.db
.vscode
.idea
*.md
docs/
```

**What .dockerignore does:**
- Excludes files from Docker build context
- Speeds up Docker builds
- Reduces image size
- Prevents secrets from being copied into images

---

## Section 8: Create Helper Scripts

### Step 8.1: Create scripts Directory

```bash
mkdir -p scripts
```

### Step 8.2: Create Environment Checker Script

```bash
nano scripts/check-env.sh
```

Paste:

```bash
#!/bin/bash

echo "=================================="
echo "Environment Variables Check"
echo "=================================="

required_vars=(
    "DB_HOST"
    "DB_PORT"
    "DB_USER"
    "DB_PASSWORD"
    "DB_GLOBAL"
    "REDIS_HOST"
    "REDIS_PORT"
    "YOUR_DOMAIN"
)

missing_vars=()

for var in "${required_vars[@]}"; do
    if [ -z "${!var}" ]; then
        echo "❌ $var is NOT set"
        missing_vars+=("$var")
    else
        echo "✅ $var is set"
    fi
done

if [ ${#missing_vars[@]} -eq 0 ]; then
    echo ""
    echo "=================================="
    echo "✅ All required variables are set!"
    echo "=================================="
    exit 0
else
    echo ""
    echo "=================================="
    echo "❌ Missing ${#missing_vars[@]} variable(s)"
    echo "=================================="
    echo "Please set these in your .env file:"
    for var in "${missing_vars[@]}"; do
        echo "  - $var"
    done
    exit 1
fi
```

Make it executable:

```bash
chmod +x scripts/check-env.sh
```

### Step 8.3: Test Environment Variables

```bash
source .env
./scripts/check-env.sh
```

**Expected output:**
```
==================================
Environment Variables Check
==================================
✅ DB_HOST is set
✅ DB_PORT is set
✅ DB_USER is set
✅ DB_PASSWORD is set
✅ DB_GLOBAL is set
✅ REDIS_HOST is set
✅ REDIS_PORT is set
✅ YOUR_DOMAIN is set

==================================
✅ All required variables are set!
==================================
```

---

## Section 9: Git Configuration

### Step 9.1: Create .gitignore

```bash
nano .gitignore
```

Paste:

```
.env
.env.local
.env.production

storage/logs/
storage/cache/
storage/sessions/
storage/uploads/
storage/backups/

vendor/
node_modules/

*.log
*.sql.gz
*.sql.bz2

.DS_Store
Thumbs.db
.vscode/
.idea/

docker-compose.override.yml
```

**What NOT to commit:**
- `.env` files (contain secrets)
- Generated files (logs, cache)
- Dependencies (vendor, node_modules)
- Backups (can be large)
- IDE settings

### Step 9.2: Initialize Git (If Not Already)

```bash
git status
```

If already initialized (cloned from GitHub), skip this step.

If not initialized:

```bash
git init
git add .
git commit -m "Initial local setup"
```

### Step 9.3: Create Local Branch

```bash
git checkout -b local-docker-setup
```

**Why create a branch?**
- Keep your local changes separate from upstream
- Easy to pull updates from Travian-Solo repo
- Can push to your own fork without conflicts

---

## Section 10: Verify Project Structure

### Step 10.1: Run Structure Verification

```bash
ls -R | grep ":$" | sed -e 's/:$//' -e 's/[^-][^\/]*\//--/g' -e 's/^/   /' -e 's/-/|/'
```

**Expected output includes:**
```
angularIndex/
   |--browser/
sections/
   |--api/
   |--servers/
   |--pma/
main_script/
mailNotify/
TaskWorker/
database/
storage/
   |--logs/
   |--cache/
   |--sessions/
scripts/
```

### Step 10.2: Verify Critical Files Exist

```bash
test -f .env && echo "✅ .env exists" || echo "❌ .env missing"
test -f sections/globalConfig.php && echo "✅ globalConfig.php exists" || echo "❌ globalConfig.php missing"
test -f database/main.sql && echo "✅ main.sql exists" || echo "❌ main.sql missing"
test -f router.php && echo "✅ router.php exists" || echo "❌ router.php missing"
```

**All should show ✅**

---

## ✅ Verification Checklist

Before proceeding to the next guide, verify:

- [ ] Repository cloned to WSL2 file system (not /mnt/c)
- [ ] Project location: `/home/{username}/Projects/TravianT4.6`
- [ ] `.env` file created with all required variables
- [ ] `.env` file has strong random values for SESSION_SECRET and JWT_SECRET
- [ ] `scripts/check-env.sh` passes all checks
- [ ] `storage/` directories created with proper permissions
- [ ] `.gitignore` configured to exclude secrets and generated files
- [ ] `sections/globalConfig.php` updated to use environment variables
- [ ] `sections/api/include/Database/DB.php` updated for MySQL
- [ ] All critical files verified to exist

**Verification command:**

```bash
cd ~/Projects/TravianT4.6
source .env
./scripts/check-env.sh
test -f .env && echo "✅ .env" || echo "❌ .env"
test -d storage/logs && echo "✅ storage/logs" || echo "❌ storage/logs"
test -f database/main.sql && echo "✅ database/main.sql" || echo "❌ database/main.sql"
```

**All checks should pass (✅)**

---

## 🚀 Next Steps

**Excellent progress!** Your project is now properly bootstrapped with:
- Optimized file location
- Environment variables configured
- Critical directories created
- Helper scripts ready

**Next guide:** [MYSQL-INFRASTRUCTURE.md](./MYSQL-INFRASTRUCTURE.md)

This will walk you through:
- Creating MySQL 8.0 Docker container
- Standardizing on port 3306
- Configuring secure database access
- Testing database connectivity

---

**Last Updated:** October 29, 2025  
**Estimated Completion Time:** 1 hour  
**Difficulty:** Beginner
