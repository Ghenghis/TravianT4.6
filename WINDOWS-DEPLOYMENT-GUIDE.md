# Windows Production Deployment Guide
**TravianT4.6 - AI-Driven Solo-Play Strategy Game**

**Version:** 1.0  
**Last Updated:** October 31, 2025  
**Target:** Windows 11 with Docker Desktop  
**Databases:** PostgreSQL 14 + MySQL 8.0

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Quick Start](#quick-start)
4. [Detailed Setup](#detailed-setup)
5. [MySQL Database Setup](#mysql-database-setup)
6. [Validation & Testing](#validation--testing)
7. [Accessing Your Game](#accessing-your-game)
8. [Troubleshooting](#troubleshooting)
9. [Server Configuration](#server-configuration)
10. [Maintenance](#maintenance)

---

## Overview

### What You're Deploying

A complete Travian T4.6 game server system with:

- **8 Speed Servers**: From 1x to 5,000,000x game speed
- **Dual Database Architecture**:
  - **PostgreSQL**: Global data (server registry, AI, automation)
  - **MySQL**: Per-world game data (players, villages, battles)
- **Docker Stack**: 5 services (Nginx, PHP-FPM, PostgreSQL, MySQL, Redis)
- **Production-Ready**: Optimized for Windows 11 local deployment

### Architecture at a Glance

```
Browser → Nginx (9080/9443) → PHP-FPM → PostgreSQL (global) + MySQL (per-world) + Redis
```

---

## Prerequisites

### Required Software

1. **Windows 11** (or Windows 10 Pro/Enterprise with WSL2)
2. **Docker Desktop** 4.30+ with WSL2 backend
3. **PowerShell** 5.1+ (built-in to Windows)
4. **Git for Windows** (optional, for updates)

### Hardware Requirements

**Minimum**:
- CPU: 4 cores
- RAM: 8 GB
- Disk: 20 GB free space

**Recommended**:
- CPU: 8+ cores
- RAM: 16+ GB
- Disk: 50+ GB SSD

### Port Requirements

Ensure these ports are available:
- **9080**: HTTP (frontend)
- **9443**: HTTPS (frontend)
- **3306**: MySQL (internal Docker network)
- **5432**: PostgreSQL (internal Docker network)
- **6379**: Redis (internal Docker network)

---

## Quick Start

### 🚀 5-Minute Setup

Open PowerShell as **Administrator** and run:

```powershell
# 1. Navigate to project directory
cd G:\TravianT46-Evolved

# 2. Start Docker services
docker-compose up -d

# 3. Wait for services to become healthy (30-60 seconds)
docker-compose ps

# 4. Initialize databases
.\scripts\setup-windows.ps1

# 5. Validate setup
.\scripts\validate-mysql-setup.ps1

# 6. Open browser
start http://localhost:9080
```

**Expected Result**: Frontend loads, 8 servers appear in server list, login works with test accounts.

---

## Detailed Setup

### Step 1: Clone/Extract Project

```powershell
# If using Git:
git clone https://github.com/yourusername/TravianT46-Evolved.git
cd TravianT46-Evolved

# Or extract ZIP to:
# G:\TravianT46-Evolved (or your preferred location)
```

### Step 2: Configure Environment Variables

Edit `docker-compose.yml` or create `.env` file:

```env
# PostgreSQL Configuration
POSTGRES_USER=postgres
POSTGRES_PASSWORD=TravianPG2025!
POSTGRES_DB=travian_global
PGPORT=5432

# MySQL Configuration
MYSQL_ROOT_PASSWORD=TravianSecureRoot2025!
MYSQL_USER=travian
MYSQL_PASSWORD=TravianDB2025!
MYSQL_DATABASE=travian_global

# Redis Configuration
REDIS_PASSWORD=TravianRedis2025!

# Application Configuration
APP_ENV=production
APP_DEBUG=false
```

### Step 3: Start Docker Services

```powershell
# Pull images and start containers
docker-compose up -d

# Check service status
docker-compose ps

# Expected output:
# NAME               STATUS
# travian_nginx      Up (healthy)
# travian_app        Up (healthy)
# travian_postgres   Up (healthy)
# travian_mysql      Up (healthy)
# travian_redis      Up (healthy)
```

### Step 4: Verify Services

```powershell
# Test Nginx
curl http://localhost:9080

# Test API health endpoint
curl http://localhost:9080/v1/health

# Expected: {"ok":true,"service":"api","time":1234567890}
```

---

## MySQL Database Setup

### Understanding the Dual-Database Architecture

**PostgreSQL** (Global):
- Server registry (`gameservers` table)
- AI/NPC automation data
- User authentication metadata

**MySQL** (Per-World):
- Game data: users, villages, troops, battles
- **90 tables** per world
- **8 separate databases** (one per speed server)

### Database Names

```
travian_world_speed10k    → Ultra Speed (10,000x)
travian_world_speed125k   → Mega Speed (125,000x)
travian_world_speed250k   → Extreme Speed (250,000x)
travian_world_speed500k   → Hyper Speed (500,000x)
travian_world_speed5m     → Instant Speed (5,000,000x)
travian_world_demo        → Demo Server (1x)
travian_world_dev         → Development Server (1x)
travian_world_testworld   → Test World (1x)
```

### Automated MySQL Setup (Recommended)

Run the all-in-one setup script:

```powershell
.\scripts\setup-windows.ps1
```

**What it does**:
1. ✅ Tests MySQL connection
2. ✅ Creates 8 game world databases
3. ✅ Applies 90-table schema to each database
4. ✅ Inserts 12 test user accounts per world
5. ✅ Generates connection.php config files
6. ✅ Validates complete setup

**Optional Switches**:
```powershell
# Force recreate databases (WARNING: Deletes existing data)
.\scripts\setup-windows.ps1 -Force

# Skip test user creation
.\scripts\setup-windows.ps1 -SkipUserCreation

# Skip validation checks
.\scripts\setup-windows.ps1 -SkipValidation
```

### Manual MySQL Setup (Advanced)

If you prefer manual control:

**Step 1**: Create databases
```powershell
docker exec -it travian_mysql mysql -uroot -pTravianSecureRoot2025! < database/mysql/windows-setup-mysql.sql
```

**Step 2**: Apply schema to each world
```powershell
Get-Content database/mysql/windows-world-schema.sql | docker exec -i travian_mysql mysql -uroot -pTravianSecureRoot2025! travian_world_speed10k
# Repeat for all 8 databases...
```

**Step 3**: Insert test users
```powershell
Get-Content database/mysql/windows-test-users.sql | docker exec -i travian_mysql mysql -uroot -pTravianSecureRoot2025! travian_world_speed10k
# Repeat for all 8 databases...
```

**Step 4**: Generate connection configs
```powershell
# See scripts/setup-windows.ps1 for template-based generation
```

---

## Validation & Testing

### Comprehensive Validation

```powershell
.\scripts\validate-mysql-setup.ps1
```

**Checks**:
- ✅ MySQL connectivity
- ✅ 8 databases exist
- ✅ 90 tables per database
- ✅ 12 test users per database
- ✅ Connection config files exist
- ✅ Sample login query succeeds

### Expected Output

```
===========================================
MySQL Setup Validation Report
===========================================

[✓] MySQL Connection: OK
[✓] Database travian_world_speed10k: 90 tables, 12 users
[✓] Database travian_world_speed125k: 90 tables, 12 users
[✓] Database travian_world_speed250k: 90 tables, 12 users
[✓] Database travian_world_speed500k: 90 tables, 12 users
[✓] Database travian_world_speed5m: 90 tables, 12 users
[✓] Database travian_world_demo: 90 tables, 12 users
[✓] Database travian_world_dev: 90 tables, 12 users
[✓] Database travian_world_testworld: 90 tables, 12 users

Summary: 8/8 databases configured correctly
Status: ✅ PASS
```

### Manual Testing

**Test 1**: Server List
```powershell
curl -s http://localhost:9080/v1/servers/loadServers | ConvertFrom-Json | Select-Object -ExpandProperty data
```

**Test 2**: Login
```powershell
$body = @{
    gameWorldId = 1
    usernameOrEmail = "testuser1"
    password = "test123"
} | ConvertTo-Json

curl -X POST http://localhost:9080/v1/auth/login `
     -H "Content-Type: application/json" `
     -d $body
```

**Expected**: JSON response with redirect URL

---

## Accessing Your Game

### Frontend Access

**URL**: http://localhost:9080

### Test Accounts

**12 accounts per world**:

| Username | Password | Access Level | Notes |
|----------|----------|--------------|-------|
| testuser1 | test123 | Player | Basic account |
| testuser2 | test123 | Player | Basic account |
| testuser3 | test123 | Player | Basic account |
| testuser4 | test123 | Player | Basic account |
| testuser5 | test123 | Player | Basic account |
| testuser6 | test123 | Player | Basic account |
| testuser7 | test123 | Player | Basic account |
| testuser8 | test123 | Player | Basic account |
| testuser9 | test123 | Player | Basic account |
| testuser10 | test123 | Player | Basic account |
| admin | admin123 | Admin (level 9) | Full privileges |
| demo | demo123 | Player | 1000 bonus gold |

### Login Flow

1. Open http://localhost:9080
2. Select a speed server (e.g., "Ultra Speed Server (10,000x)")
3. Enter username: `testuser1`
4. Enter password: `test123`
5. Click "Login"

---

## Troubleshooting

### Issue: Docker Services Won't Start

**Symptoms**: `docker-compose up -d` fails

**Solutions**:
```powershell
# Check Docker Desktop is running
Get-Process "Docker Desktop"

# Restart Docker Desktop
Restart-Service -Name "com.docker.service" -Force

# Check ports aren't in use
netstat -ano | findstr ":9080"
netstat -ano | findstr ":3306"
```

### Issue: MySQL Connection Errors

**Symptoms**: `ERROR 1045 (28000): Access denied`

**Solutions**:
```powershell
# Verify MySQL credentials
docker exec -it travian_mysql mysql -uroot -pTravianSecureRoot2025! -e "SELECT 1"

# Reset MySQL root password
docker-compose down
docker volume rm travian_mysql_data
docker-compose up -d
.\scripts\setup-windows.ps1
```

### Issue: Login Returns "Database Connection Error"

**Symptoms**: Login fails with database error

**Solutions**:
```powershell
# 1. Verify MySQL databases exist
docker exec travian_mysql mysql -uroot -pTravianSecureRoot2025! -e "SHOW DATABASES LIKE 'travian_world_%'"

# 2. Verify connection config files exist
Get-ChildItem -Recurse -Path sections/servers/*/config/connection.php

# 3. Re-run setup
.\scripts\setup-windows.ps1 -Force
```

### Issue: Frontend Shows "Failed to communicate with API"

**Symptoms**: White page or API error

**Solutions**:
```powershell
# 1. Check PHP-FPM logs
docker-compose logs php-fpm --tail=50

# 2. Check Nginx logs
docker-compose logs nginx --tail=50

# 3. Test API directly
curl http://localhost:9080/v1/health

# 4. Rebuild PHP-FPM
docker-compose build --no-cache php-fpm
docker-compose restart php-fpm
```

### Issue: No Servers in Server List

**Symptoms**: Empty server list

**Solutions**:
```powershell
# Verify PostgreSQL has server registrations
docker exec travian_postgres psql -U postgres -d travian_global -c "SELECT worldid, name, speed FROM gameservers ORDER BY speed DESC;"

# Expected: 8 rows (speed5m, speed250k, speed125k, speed10k, etc.)

# If empty, re-register servers (see setup-windows.ps1)
```

---

## Server Configuration

### Speed Server Overview

| Server ID | Name | Speed | Population | Use Case |
|-----------|------|-------|------------|----------|
| speed5m | Instant Speed | 5,000,000x | Testing | Instant gratification, test features |
| speed250k | Extreme Speed | 250,000x | Testing | Rapid progression |
| speed125k | Mega Speed | 125,000x | Testing | Fast gameplay |
| speed10k | Ultra Speed | 10,000x | Light play | Casual gaming |
| demo | Demo Server | 1x | Public | Demo/showcase |
| dev | Development | 1x | Development | Development/debugging |
| testworld | Test World | 1x | Testing | Integration tests |

### Customizing Speed Servers

Edit `database/mysql/windows-setup-mysql.sql` or PostgreSQL directly:

```sql
-- Add new speed server
INSERT INTO gameservers (worldid, name, gameworldurl, configfilelocation, speed, starttime)
VALUES ('speedcustom', 'Custom Speed (1000x)', 'http://localhost:9080/', 'sections/servers/speedcustom', 1000, EXTRACT(EPOCH FROM NOW())::INTEGER);
```

Then create the MySQL database and config:
```powershell
# Create database
docker exec travian_mysql mysql -uroot -pTravianSecureRoot2025! -e "CREATE DATABASE travian_world_speedcustom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Apply schema
Get-Content database/mysql/windows-world-schema.sql | docker exec -i travian_mysql mysql -uroot -pTravianSecureRoot2025! travian_world_speedcustom

# Generate connection config
# (Modify scripts/setup-windows.ps1 or manually create)
```

---

## Maintenance

### Daily Operations

**Check Service Health**:
```powershell
docker-compose ps
```

**View Logs**:
```powershell
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f php-fpm
```

**Restart Services**:
```powershell
# Restart all
docker-compose restart

# Restart specific service
docker-compose restart php-fpm
```

### Database Backups

**PostgreSQL Backup**:
```powershell
docker exec travian_postgres pg_dump -U postgres travian_global > backup_postgres_$(Get-Date -Format 'yyyyMMdd_HHmmss').sql
```

**MySQL Backup** (all worlds):
```powershell
docker exec travian_mysql mysqldump -uroot -pTravianSecureRoot2025! --all-databases > backup_mysql_$(Get-Date -Format 'yyyyMMdd_HHmmss').sql
```

**MySQL Backup** (single world):
```powershell
docker exec travian_mysql mysqldump -uroot -pTravianSecureRoot2025! travian_world_speed10k > backup_speed10k_$(Get-Date -Format 'yyyyMMdd_HHmmss').sql
```

### Updates and Code Changes

**After modifying PHP code**:
```powershell
# Rebuild and restart
docker-compose build php-fpm
docker-compose restart php-fpm
```

**After modifying Nginx config**:
```powershell
docker-compose restart nginx
```

**After database schema changes**:
```powershell
# Apply new schema
Get-Content database/mysql/new-schema.sql | docker exec -i travian_mysql mysql -uroot -pTravianSecureRoot2025! travian_world_speed10k
```

### Performance Tuning

**MySQL Performance**:
```powershell
# Check slow queries
docker exec travian_mysql mysql -uroot -pTravianSecureRoot2025! -e "SHOW GLOBAL STATUS LIKE 'Slow_queries';"

# Optimize tables
docker exec travian_mysql mysql -uroot -pTravianSecureRoot2025! travian_world_speed10k -e "OPTIMIZE TABLE users, vdata, movement;"
```

**Redis Cache Clear**:
```powershell
docker exec travian_redis redis-cli -a TravianRedis2025! FLUSHALL
```

---

## Advanced Topics

### Credential Management

**Storing Credentials Securely**:

```powershell
# Create credentials file (excluded from Git)
$credentials = @{
    MYSQL_ROOT_PASSWORD = "TravianSecureRoot2025!"
    MYSQL_PASSWORD = "TravianDB2025!"
    POSTGRES_PASSWORD = "TravianPG2025!"
    REDIS_PASSWORD = "TravianRedis2025!"
}
$credentials | ConvertTo-Json | Out-File -FilePath ".env.ps1"

# Load in scripts
. .\.env.ps1
```

### External Access

**Port Forwarding** (access from other devices on network):

Edit `docker-compose.yml`:
```yaml
nginx:
  ports:
    - "0.0.0.0:9080:80"  # Allow external connections
    - "0.0.0.0:9443:443"
```

**Firewall Rule**:
```powershell
New-NetFirewallRule -DisplayName "Travian HTTP" -Direction Inbound -LocalPort 9080 -Protocol TCP -Action Allow
```

---

## Support and Resources

### Documentation

- **Project Documentation**: `docs/completion/`
- **MySQL Schema**: `docs/completion/08b-MYSQL-SCHEMA-ANALYSIS.md`
- **API Endpoints**: `docs/completion/07-API-ENDPOINT-CATALOG.md`
- **Architecture**: `docs/completion/03-ARCHITECTURE-OVERVIEW.md`

### Common Commands Reference

```powershell
# Start everything
docker-compose up -d

# Stop everything
docker-compose down

# Rebuild everything
docker-compose build --no-cache
docker-compose up -d

# Complete reset (WARNING: Deletes all data)
docker-compose down -v
.\scripts\setup-windows.ps1
```

---

## Success Checklist

Before going to production, verify:

- ✅ All 5 Docker services are healthy
- ✅ PostgreSQL has 8 server registrations
- ✅ MySQL has 8 databases with 90 tables each
- ✅ All 8 connection.php files generated
- ✅ Test login succeeds on all 8 worlds
- ✅ Frontend loads without errors
- ✅ API health endpoint returns 200 OK
- ✅ Database backups configured
- ✅ Credentials stored securely
- ✅ Firewall rules configured (if needed)

---

**Congratulations!** Your TravianT4.6 Windows production deployment is complete! 🎉

For issues not covered here, check:
1. Docker logs: `docker-compose logs`
2. PHP errors: `docker-compose logs php-fpm`
3. Database connectivity: `.\scripts\validate-mysql-setup.ps1`
