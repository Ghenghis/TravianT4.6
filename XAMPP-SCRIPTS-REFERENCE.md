# XAMPP Scripts Reference
**TravianT4.6 - PowerShell Automation Scripts Documentation**

**Version:** 2.0  
**Last Updated:** November 1, 2025  
**Target:** Windows 11 + XAMPP 8.2.12

---

## Table of Contents

1. [Overview](#overview)
2. [setup-xampp.ps1](#setup-xamppps1)
3. [validate-xampp.ps1](#validate-xamppps1)
4. [xampp-backup.ps1](#xampp-backupps1)
5. [xampp-performance-tune.ps1](#xampp-performance-tuneps1)
6. [xampp-healthcheck.ps1](#xampp-healthcheckps1)
7. [Common Workflows](#common-workflows)
8. [Windows Task Scheduler Integration](#windows-task-scheduler-integration)
9. [Error Codes Reference](#error-codes-reference)
10. [Troubleshooting](#troubleshooting)

---

## Overview

This document provides comprehensive technical documentation for all PowerShell automation scripts used in XAMPP deployment of TravianT4.6.

### Script Locations

All scripts should be placed in: `C:\xampp\htdocs\scripts\`

### Prerequisites

- **PowerShell**: 5.1+ (included in Windows 11)
- **XAMPP**: 8.2.12+ with Apache, MySQL, PHP
- **PostgreSQL Addon**: Installed to C:\xampp\pgsql\
- **Administrator Rights**: Required for some operations

### Quick Reference

| Script | Purpose | Frequency | Runtime |
|--------|---------|-----------|---------|
| setup-xampp.ps1 | Database provisioning | Once (initial setup) | 2-5 min |
| validate-xampp.ps1 | Setup validation | After setup, troubleshooting | 30-60 sec |
| xampp-backup.ps1 | Database backups | Daily (automated) | 5-15 min |
| xampp-performance-tune.ps1 | Performance optimization | Once (after setup) | 30 sec |
| xampp-healthcheck.ps1 | System monitoring | Hourly (automated) | 10-20 sec |

---

## setup-xampp.ps1

### Purpose

Automated MySQL/PostgreSQL database provisioning for XAMPP deployment. This is the primary setup script that creates all game world databases and prepares the system for operation.

### Prerequisites

- XAMPP services running (MySQL, PostgreSQL)
- PowerShell running as Administrator
- Database schema files present in `database/mysql/`

### Parameters

```powershell
param(
    [string]$MysqlHost = "localhost",
    [string]$MysqlRootPassword = "TravianSecureRoot2025!",
    [string]$MysqlUser = "travian",
    [string]$MysqlPassword = "TravianDB2025!",
    [string]$PgHost = "localhost",
    [string]$PgUser = "postgres",
    [string]$PgPassword = "postgres",
    [string]$PgDatabase = "travian_global",
    [switch]$SkipUserCreation,
    [switch]$SkipValidation,
    [switch]$Force
)
```

#### Parameter Details

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| MysqlHost | string | localhost | MySQL server hostname |
| MysqlRootPassword | string | TravianSecureRoot2025! | MySQL root password |
| MysqlUser | string | travian | MySQL database user to create |
| MysqlPassword | string | TravianDB2025! | Password for MySQL database user |
| PgHost | string | localhost | PostgreSQL server hostname |
| PgUser | string | postgres | PostgreSQL username |
| PgPassword | string | postgres | PostgreSQL password |
| PgDatabase | string | travian_global | PostgreSQL database name |
| SkipUserCreation | switch | false | Skip test user account creation |
| SkipValidation | switch | false | Skip post-setup validation |
| Force | switch | false | Drop existing databases and recreate |

### What It Does

1. **Connection Validation**: Tests MySQL and PostgreSQL connectivity
2. **MySQL Setup**:
   - Creates 8 game world databases (travian_world_speed10k, etc.)
   - Applies 90-table schema to each database
   - Creates travian database user with permissions
3. **User Provisioning**: Inserts 12 test accounts per world (unless -SkipUserCreation)
4. **Config Generation**: Creates connection.php files in sections/servers/*/config/
5. **PostgreSQL Registration**: Registers all 8 servers in gameservers table
6. **Validation**: Runs post-setup checks (unless -SkipValidation)

### Game Worlds Created

| World ID | Database Name | Display Name | Speed |
|----------|---------------|--------------|-------|
| speed10k | travian_world_speed10k | Ultra Speed Server | 10,000x |
| speed125k | travian_world_speed125k | Mega Speed Server | 125,000x |
| speed250k | travian_world_speed250k | Extreme Speed Server | 250,000x |
| speed500k | travian_world_speed500k | Hyper Speed Server | 500,000x |
| speed5m | travian_world_speed5m | Instant Speed Server | 5,000,000x |
| demo | travian_world_demo | Demo World | 1x |
| dev | travian_world_dev | Development World | 1x |
| testworld | travian_world_testworld | Test World | 1x |

### Test Accounts Created

Each world receives 12 test accounts:
- **testuser1-10**: Password `test123`, regular player access
- **admin**: Password `admin123`, administrator (level 9)
- **demo**: Password `demo123`, regular player with 1000 bonus gold

### Usage Examples

#### Basic Setup (Default Credentials)

```powershell
cd C:\xampp\htdocs\scripts
.\setup-xampp.ps1
```

#### Custom MySQL Password

```powershell
.\setup-xampp.ps1 -MysqlRootPassword "MySecurePassword123!"
```

#### Force Re-setup (Drops Existing Databases)

```powershell
.\setup-xampp.ps1 -Force
```

**⚠️ WARNING**: `-Force` will **delete all existing data** in the databases!

#### Production Deployment (No Test Users)

```powershell
.\setup-xampp.ps1 -SkipUserCreation
```

#### Quick Setup (Skip Validation)

```powershell
.\setup-xampp.ps1 -SkipValidation
```

#### Full Custom Setup

```powershell
.\setup-xampp.ps1 `
    -MysqlHost "localhost" `
    -MysqlRootPassword "SecureRoot2025!" `
    -MysqlUser "travian_prod" `
    -MysqlPassword "TravianProd2025!" `
    -PgHost "localhost" `
    -PgUser "postgres" `
    -PgPassword "PgSecure2025!" `
    -PgDatabase "travian_global"
```

### Output

Colored console output:
- 🟢 **Green**: Successful operations
- 🔴 **Red**: Errors and failures
- 🟡 **Yellow**: Warnings and skipped operations
- 🔵 **Cyan**: Informational messages
- 🟣 **Magenta**: Section headers

### Files Created

- **MySQL Databases**: 8 databases with 90 tables each (720 total tables)
- **Connection Configs**: 8 files in `sections/servers/*/config/connection.php`
- **PostgreSQL Entries**: 8 server records in gameservers table

### Exit Codes

| Code | Meaning | Action Required |
|------|---------|-----------------|
| 0 | Success | None - proceed to validation |
| 1 | MySQL connection failed | Check MySQL service, credentials |
| 2 | PostgreSQL connection failed | Check PostgreSQL service, credentials |
| 3 | Schema application failed | Check schema SQL files, database permissions |
| 4 | User creation failed | Check test user SQL files |
| 5 | Config generation failed | Check file permissions in sections/servers/ |

### Estimated Runtime

- **Fresh Install**: 2-5 minutes
- **With -Force Flag**: 3-7 minutes (includes database drops)
- **Large Dataset**: 5-10 minutes (if importing production data)

### Troubleshooting

#### "MySQL connection failed"
```powershell
# Check MySQL service
Get-Service | Where-Object {$_.Name -like "*mysql*"}

# Test manual connection
C:\xampp\mysql\bin\mysql.exe -u root -p
```

#### "PostgreSQL connection failed"
```powershell
# Check PostgreSQL service
C:\xampp\pgsql\bin\pg_isready.exe

# Check pg_hba.conf allows local connections
Get-Content C:\xampp\pgsql\data\pg_hba.conf
```

#### "Database already exists" (without -Force)
```powershell
# Option 1: Use -Force to recreate
.\setup-xampp.ps1 -Force

# Option 2: Manually drop databases
C:\xampp\mysql\bin\mysql.exe -u root -p -e "DROP DATABASE travian_world_speed10k;"
```

---

## validate-xampp.ps1

### Purpose

Comprehensive validation of XAMPP deployment to ensure all components are correctly configured and operational.

### Prerequisites

- setup-xampp.ps1 completed successfully
- All XAMPP services running

### Parameters

```powershell
param(
    [string]$MysqlHost = "localhost",
    [string]$MysqlUser = "travian",
    [string]$MysqlPassword = "TravianDB2025!",
    [string]$PgHost = "localhost",
    [string]$PgUser = "postgres",
    [string]$PgPassword = "postgres",
    [string]$PgDatabase = "travian_global",
    [string]$ApiUrl = "http://localhost",
    [switch]$Verbose
)
```

#### Parameter Details

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| MysqlHost | string | localhost | MySQL server hostname |
| MysqlUser | string | travian | MySQL database user |
| MysqlPassword | string | TravianDB2025! | MySQL user password |
| PgHost | string | localhost | PostgreSQL server hostname |
| PgUser | string | postgres | PostgreSQL username |
| PgPassword | string | postgres | PostgreSQL password |
| PgDatabase | string | travian_global | PostgreSQL database name |
| ApiUrl | string | http://localhost | Base URL for API testing |
| Verbose | switch | false | Enable detailed validation output |

### What It Validates

1. **Service Health**:
   - Apache service running (port 80/8080)
   - MySQL service running and connectable
   - PostgreSQL service running and connectable

2. **Database Structure**:
   - 8 MySQL databases exist
   - Each database has exactly 90 tables
   - Each database has 12 test user accounts
   - PostgreSQL travian_global database exists
   - gameservers table has 8 server entries

3. **File System**:
   - Connection config files exist (8 files)
   - Config files contain valid database credentials
   - File permissions correct for Apache user

4. **API Endpoints**:
   - POST /v1/servers/loadServers returns 200 OK
   - Response contains all 8 game servers
   - JSON format is valid

5. **Authentication Flow**:
   - Sample login query succeeds
   - Password verification works (bcrypt)

### Usage Examples

#### Standard Validation

```powershell
cd C:\xampp\htdocs\scripts
.\validate-xampp.ps1
```

#### Verbose Output (Detailed Checks)

```powershell
.\validate-xampp.ps1 -Verbose
```

#### Custom Credentials

```powershell
.\validate-xampp.ps1 `
    -MysqlUser "travian_prod" `
    -MysqlPassword "TravianProd2025!" `
    -PgPassword "PgSecure2025!"
```

#### Custom API URL (Port 8080)

```powershell
.\validate-xampp.ps1 -ApiUrl "http://localhost:8080"
```

### Output Format

```
============================================================================
TRAVIANT4.6 - XAMPP VALIDATION REPORT
============================================================================

[SERVICE HEALTH]
✓ Apache service is running (port 80)
✓ MySQL service is running and connectable
✓ PostgreSQL service is running and connectable

[MYSQL DATABASES]
✓ Database travian_world_speed10k exists (90 tables, 12 users)
✓ Database travian_world_speed125k exists (90 tables, 12 users)
✓ Database travian_world_speed250k exists (90 tables, 12 users)
✓ Database travian_world_speed500k exists (90 tables, 12 users)
✓ Database travian_world_speed5m exists (90 tables, 12 users)
✓ Database travian_world_demo exists (90 tables, 12 users)
✓ Database travian_world_dev exists (90 tables, 12 users)
✓ Database travian_world_testworld exists (90 tables, 12 users)

[POSTGRESQL]
✓ Database travian_global exists
✓ Table gameservers has 8 server entries

[CONFIGURATION FILES]
✓ Connection file exists: sections/servers/speed10k/config/connection.php
✓ Connection file exists: sections/servers/speed125k/config/connection.php
[... 6 more files ...]

[API ENDPOINTS]
✓ POST /v1/servers/loadServers returns 200 OK
✓ Response contains 8 game servers
✓ JSON format is valid

[AUTHENTICATION]
✓ Sample login query succeeds (testuser1@speed10k)
✓ Password verification works (bcrypt)

============================================================================
VALIDATION RESULT: ALL CHECKS PASSED ✓
============================================================================
Total: 32 checks passed, 0 failed
```

### Exit Codes

| Code | Meaning | Action Required |
|------|---------|-----------------|
| 0 | All checks passed | None - deployment successful |
| 1 | One or more checks failed | Review output, fix issues, re-run |

### Estimated Runtime

- **Standard Mode**: 30-60 seconds
- **Verbose Mode**: 45-75 seconds

### Troubleshooting

#### "Apache service not found"
```powershell
# Check if Apache is running via XAMPP Control Panel
# Or manually start:
C:\xampp\apache_start.bat
```

#### "Database X does not have 90 tables"
```powershell
# Re-run setup script
.\setup-xampp.ps1 -Force
```

#### "API endpoint returned 404"
```powershell
# Check .htaccess file exists
Test-Path C:\xampp\htdocs\.htaccess

# Check mod_rewrite is enabled in httpd.conf
Select-String "LoadModule rewrite_module" C:\xampp\apache\conf\httpd.conf
```

---

## xampp-backup.ps1

### Purpose

Automated database and file backups with 3-tier retention policy (daily/weekly/monthly).

### Prerequisites

- XAMPP services running
- 20GB+ free disk space on backup drive
- Write permissions to backup directory

### Parameters

```powershell
param(
    [string]$BackupPath = "C:\xampp\backups",
    [string]$MysqlHost = "localhost",
    [string]$MysqlUser = "travian",
    [string]$MysqlPassword = "TravianDB2025!",
    [string]$PgHost = "localhost",
    [string]$PgUser = "postgres",
    [string]$PgPassword = "postgres",
    [string]$PgDatabase = "travian_global",
    [switch]$Compress,
    [int]$DailyRetention = 7,
    [int]$WeeklyRetention = 4,
    [int]$MonthlyRetention = 12
)
```

#### Parameter Details

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| BackupPath | string | C:\xampp\backups | Root backup directory |
| MysqlHost | string | localhost | MySQL server hostname |
| MysqlUser | string | travian | MySQL database user |
| MysqlPassword | string | TravianDB2025! | MySQL user password |
| PgHost | string | localhost | PostgreSQL server hostname |
| PgUser | string | postgres | PostgreSQL username |
| PgPassword | string | postgres | PostgreSQL password |
| PgDatabase | string | travian_global | PostgreSQL database name |
| Compress | switch | false | Create ZIP archives of backups |
| DailyRetention | int | 7 | Number of daily backups to keep |
| WeeklyRetention | int | 4 | Number of weekly backups to keep |
| MonthlyRetention | int | 12 | Number of monthly backups to keep |

### What It Backs Up

1. **PostgreSQL**:
   - travian_global database (full dump)
   - gameservers table
   - User accounts and permissions

2. **MySQL**:
   - All 8 game world databases (full dumps)
   - Schema and data
   - Stored procedures and triggers

3. **Configuration Files**:
   - All connection.php files (8 files)
   - .env file (if present)
   - Apache .htaccess

4. **Logs** (optional):
   - Apache error/access logs
   - MySQL slow query log
   - PostgreSQL logs

### Backup Structure

```
C:\xampp\backups\
├── daily\
│   ├── 2025-11-01\
│   │   ├── postgresql_travian_global.sql
│   │   ├── mysql_world_speed10k.sql
│   │   ├── mysql_world_speed125k.sql
│   │   ├── mysql_world_speed250k.sql
│   │   ├── mysql_world_speed500k.sql
│   │   ├── mysql_world_speed5m.sql
│   │   ├── mysql_world_demo.sql
│   │   ├── mysql_world_dev.sql
│   │   ├── mysql_world_testworld.sql
│   │   └── config_files.zip
│   ├── 2025-11-02\
│   └── ... (7 days total)
├── weekly\
│   ├── 2025-W44\
│   └── ... (4 weeks total)
└── monthly\
    ├── 2025-10\
    └── ... (12 months total)
```

### Retention Policy

- **Daily**: Last 7 days (e.g., Oct 25 - Nov 1)
- **Weekly**: Last 4 weeks (e.g., Week 40-44)
- **Monthly**: Last 12 months (e.g., Nov 2024 - Oct 2025)

### Usage Examples

#### Manual Backup (Now)

```powershell
cd C:\xampp\htdocs\scripts
.\xampp-backup.ps1
```

#### Backup with Compression

```powershell
.\xampp-backup.ps1 -Compress
```

#### Custom Backup Location

```powershell
.\xampp-backup.ps1 -BackupPath "D:\TravianBackups"
```

#### Extended Retention (30 days daily)

```powershell
.\xampp-backup.ps1 -DailyRetention 30 -WeeklyRetention 8 -MonthlyRetention 24
```

#### Schedule Daily Backup (2 AM)

```powershell
# Create scheduled task
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-File C:\xampp\htdocs\scripts\xampp-backup.ps1 -Compress"
$trigger = New-ScheduledTaskTrigger -Daily -At 2am
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName "TravianDailyBackup" -Action $action -Trigger $trigger -Principal $principal

Write-Host "✓ Scheduled task created: Daily backup at 2:00 AM" -ForegroundColor Green
```

### Backup Sizes (Approximate)

| Component | Uncompressed | Compressed (.zip) |
|-----------|--------------|-------------------|
| PostgreSQL (travian_global) | 5-50 MB | 1-10 MB |
| MySQL (per world, empty) | 10-20 MB | 2-5 MB |
| MySQL (per world, 1000 players) | 100-500 MB | 20-100 MB |
| Config files | <1 MB | <1 MB |
| **Total (8 worlds, empty)** | ~100 MB | ~20 MB |
| **Total (8 worlds, populated)** | ~1-4 GB | ~200-800 MB |

### Restore Procedure

```powershell
# 1. Stop XAMPP services
C:\xampp\apache_stop.bat
C:\xampp\mysql_stop.bat

# 2. Restore PostgreSQL
$env:PGPASSWORD = "postgres"
& C:\xampp\pgsql\bin\psql.exe -U postgres -d postgres -c "DROP DATABASE travian_global;"
& C:\xampp\pgsql\bin\psql.exe -U postgres -d postgres -c "CREATE DATABASE travian_global;"
& C:\xampp\pgsql\bin\psql.exe -U postgres -d travian_global -f "C:\xampp\backups\daily\2025-11-01\postgresql_travian_global.sql"

# 3. Restore MySQL (example for one world)
& C:\xampp\mysql\bin\mysql.exe -u root -p"TravianSecureRoot2025!" -e "DROP DATABASE travian_world_speed10k;"
& C:\xampp\mysql\bin\mysql.exe -u root -p"TravianSecureRoot2025!" -e "CREATE DATABASE travian_world_speed10k CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& C:\xampp\mysql\bin\mysql.exe -u root -p"TravianSecureRoot2025!" travian_world_speed10k < "C:\xampp\backups\daily\2025-11-01\mysql_world_speed10k.sql"

# 4. Restore config files
Expand-Archive -Path "C:\xampp\backups\daily\2025-11-01\config_files.zip" -DestinationPath "C:\xampp\htdocs\sections\servers\" -Force

# 5. Restart services
C:\xampp\mysql_start.bat
C:\xampp\apache_start.bat

Write-Host "✓ Restore complete" -ForegroundColor Green
```

### Exit Codes

| Code | Meaning | Action Required |
|------|---------|-----------------|
| 0 | Backup successful | None |
| 1 | Backup failed | Check disk space, permissions, database connectivity |

### Estimated Runtime

- **Without Compression**: 5-10 minutes (empty databases)
- **With Compression**: 8-15 minutes (empty databases)
- **Populated Databases**: 15-30 minutes (depends on data size)

### Troubleshooting

#### "Insufficient disk space"
```powershell
# Check free space
Get-PSDrive C | Select-Object Used,Free

# Move backup location to another drive
.\xampp-backup.ps1 -BackupPath "D:\Backups"
```

#### "Backup file is 0 bytes"
```powershell
# Check database connectivity
.\validate-xampp.ps1

# Test manual mysqldump
& C:\xampp\mysql\bin\mysqldump.exe -u travian -p"TravianDB2025!" travian_world_speed10k > test_backup.sql
```

---

## xampp-performance-tune.ps1

### Purpose

Optimize PHP, MySQL, and Apache configuration based on system resources for maximum performance.

### Prerequisites

- PowerShell running as Administrator
- XAMPP services running
- Backup of current configuration (script creates .bak files)

### Parameters

```powershell
param(
    [int]$SystemRamGB = 0,  # 0 = auto-detect
    [switch]$DryRun,
    [switch]$SkipRestart
)
```

#### Parameter Details

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| SystemRamGB | int | 0 (auto-detect) | Override system RAM detection (in GB) |
| DryRun | switch | false | Show recommended changes without applying |
| SkipRestart | switch | false | Don't restart services after tuning |

### What It Tunes

#### 1. PHP Configuration (php.ini)

| Setting | 8GB RAM | 16GB RAM | 32GB+ RAM |
|---------|---------|----------|-----------|
| memory_limit | 256M | 512M | 1024M |
| opcache.memory_consumption | 128M | 256M | 512M |
| opcache.max_accelerated_files | 10000 | 20000 | 30000 |
| max_execution_time | 300 | 300 | 300 |
| post_max_size | 128M | 256M | 512M |

#### 2. MySQL Configuration (my.ini)

| Setting | 8GB RAM | 16GB RAM | 32GB+ RAM |
|---------|---------|----------|-----------|
| innodb_buffer_pool_size | 2G | 4G | 8G |
| max_connections | 200 | 400 | 800 |
| query_cache_size | 64M | 128M | 256M |
| innodb_log_file_size | 256M | 512M | 1G |
| table_open_cache | 4096 | 8192 | 16384 |

#### 3. Apache Configuration (httpd.conf)

| Setting | 8GB RAM | 16GB RAM | 32GB+ RAM |
|---------|---------|----------|-----------|
| MaxRequestWorkers | 150 | 300 | 400 |
| KeepAlive | On | On | On |
| KeepAliveTimeout | 5 | 5 | 5 |
| MaxConnectionsPerChild | 1000 | 2000 | 3000 |

### Tuning Profiles

#### Development (8GB RAM)
- Balanced settings for local development
- Moderate resource usage
- Good for 1-10 concurrent users

#### Production (16GB+ RAM)
- Optimized for high performance
- Higher memory allocation
- Supports 50-100+ concurrent users

#### Enterprise (32GB+ RAM)
- Maximum performance settings
- Large buffer pools and caches
- Supports 200+ concurrent users

### Usage Examples

#### Dry Run (See Recommendations)

```powershell
cd C:\xampp\htdocs\scripts
.\xampp-performance-tune.ps1 -DryRun
```

#### Apply Optimizations (Auto-detect RAM)

```powershell
.\xampp-performance-tune.ps1
```

**Note**: Services will be restarted automatically. Ensure no active users!

#### Apply Without Restart

```powershell
.\xampp-performance-tune.ps1 -SkipRestart
```

Manually restart later:
```powershell
C:\xampp\apache_stop.bat
C:\xampp\mysql_stop.bat
Start-Sleep -Seconds 5
C:\xampp\mysql_start.bat
C:\xampp\apache_start.bat
```

#### Override RAM Detection (16GB System)

```powershell
.\xampp-performance-tune.ps1 -SystemRamGB 16
```

### Output

```
============================================================================
TRAVIANT4.6 - XAMPP PERFORMANCE TUNING
============================================================================

[SYSTEM ANALYSIS]
✓ Detected: 16 GB RAM, 8 CPU cores
✓ Tuning profile: Production

[PHP OPTIMIZATION]
✓ memory_limit: 256M → 512M
✓ opcache.memory_consumption: 128M → 256M
✓ opcache.max_accelerated_files: 10000 → 20000
✓ max_execution_time: 30 → 300
✓ Configuration saved to: C:\xampp\php\php.ini
✓ Backup created: C:\xampp\php\php.ini.bak

[MYSQL OPTIMIZATION]
✓ innodb_buffer_pool_size: 1G → 4G
✓ max_connections: 100 → 400
✓ query_cache_size: 0 → 128M
✓ Configuration saved to: C:\xampp\mysql\bin\my.ini
✓ Backup created: C:\xampp\mysql\bin\my.ini.bak

[APACHE OPTIMIZATION]
✓ MaxRequestWorkers: 150 → 300
✓ KeepAlive: Off → On
✓ KeepAliveTimeout: 15 → 5
✓ Configuration saved to: C:\xampp\apache\conf\httpd.conf
✓ Backup created: C:\xampp\apache\conf\httpd.conf.bak

[SERVICE RESTART]
✓ Apache restarted
✓ MySQL restarted

============================================================================
OPTIMIZATION COMPLETE
============================================================================

Optimized Settings:
  PHP Memory Limit: 512M
  PHP OPcache: 256M
  MySQL Buffer Pool: 4096M
  MySQL Max Connections: 400
  Apache Max Workers: 300

Configuration backups saved to: C:\xampp\backups\config\
```

### Rollback Procedure

If performance degrades after tuning:

```powershell
# Restore PHP config
Copy-Item "C:\xampp\php\php.ini.bak" -Destination "C:\xampp\php\php.ini" -Force

# Restore MySQL config
Copy-Item "C:\xampp\mysql\bin\my.ini.bak" -Destination "C:\xampp\mysql\bin\my.ini" -Force

# Restore Apache config
Copy-Item "C:\xampp\apache\conf\httpd.conf.bak" -Destination "C:\xampp\apache\conf\httpd.conf" -Force

# Restart services
C:\xampp\apache_stop.bat
C:\xampp\mysql_stop.bat
Start-Sleep -Seconds 5
C:\xampp\mysql_start.bat
C:\xampp\apache_start.bat

Write-Host "✓ Configurations restored to previous settings" -ForegroundColor Green
```

### Exit Codes

| Code | Meaning | Action Required |
|------|---------|-----------------|
| 0 | Optimization successful | None |
| 1 | Failed to apply changes | Check file permissions, service status |

### Estimated Runtime

- **Dry Run**: 5-10 seconds
- **Apply Changes**: 30 seconds + service restart time (~30-60 seconds)
- **Total**: ~1-2 minutes

---

## xampp-healthcheck.ps1

### Purpose

Continuous monitoring and health reporting for XAMPP deployment with optional alerting.

### Prerequisites

- XAMPP services running
- PowerShell 5.1+
- (Optional) SMTP credentials for email alerts

### Parameters

```powershell
param(
    [string]$MysqlHost = "localhost",
    [string]$MysqlUser = "travian",
    [string]$MysqlPassword = "TravianDB2025!",
    [string]$PgHost = "localhost",
    [string]$PgUser = "postgres",
    [string]$PgPassword = "postgres",
    [string]$PgDatabase = "travian_global",
    [string]$ApiUrl = "http://localhost",
    [switch]$Continuous,
    [int]$IntervalMinutes = 5,
    [switch]$SendAlerts,
    [string]$EmailTo = "",
    [int]$DiskSpaceWarningGB = 10
)
```

#### Parameter Details

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| MysqlHost | string | localhost | MySQL server hostname |
| MysqlUser | string | travian | MySQL database user |
| MysqlPassword | string | TravianDB2025! | MySQL user password |
| PgHost | string | localhost | PostgreSQL server hostname |
| PgUser | string | postgres | PostgreSQL username |
| PgPassword | string | postgres | PostgreSQL password |
| PgDatabase | string | travian_global | PostgreSQL database name |
| ApiUrl | string | http://localhost | Base URL for API testing |
| Continuous | switch | false | Run indefinitely, check at intervals |
| IntervalMinutes | int | 5 | Minutes between checks (if Continuous) |
| SendAlerts | switch | false | Send email alerts on critical issues |
| EmailTo | string | "" | Email address for alerts |
| DiskSpaceWarningGB | int | 10 | Warn if disk space below this (GB) |

### What It Monitors

1. **Service Status**:
   - Apache process running
   - MySQL process running
   - PostgreSQL process running

2. **Disk Space**:
   - C:\ drive free space
   - Alert if below threshold (default 10GB)

3. **Database Connectivity**:
   - MySQL connection test
   - PostgreSQL connection test
   - Query execution test

4. **API Health**:
   - POST /v1/servers/loadServers response time
   - HTTP status code (200 expected)
   - JSON format validation

5. **Process Metrics**:
   - Apache uptime
   - MySQL uptime
   - CPU usage (Apache, MySQL)
   - Memory usage (Apache, MySQL)

6. **Log File Sizes**:
   - Apache error.log size
   - MySQL error log size
   - Alert if >100MB

### Health Indicators

| Indicator | Status | Meaning |
|-----------|--------|---------|
| 🟢 GREEN | Healthy | All systems operational |
| 🟡 YELLOW | Warning | Non-critical issue detected |
| 🔴 RED | Critical | Service down or critical failure |

### Usage Examples

#### One-Time Health Check

```powershell
cd C:\xampp\htdocs\scripts
.\xampp-healthcheck.ps1
```

#### Continuous Monitoring (Every 5 Minutes)

```powershell
.\xampp-healthcheck.ps1 -Continuous
```

**Ctrl+C** to stop monitoring.

#### Custom Interval (Every 30 Seconds)

```powershell
.\xampp-healthcheck.ps1 -Continuous -IntervalMinutes 0.5
```

#### With Email Alerts

```powershell
.\xampp-healthcheck.ps1 -Continuous -SendAlerts -EmailTo "admin@example.com"
```

**Note**: Requires SMTP configuration in script or environment variables.

#### Custom Disk Space Threshold (20GB)

```powershell
.\xampp-healthcheck.ps1 -DiskSpaceWarningGB 20
```

#### Schedule Hourly Health Check

```powershell
# Create scheduled task
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-File C:\xampp\htdocs\scripts\xampp-healthcheck.ps1"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Hours 1) -RepetitionDuration ([TimeSpan]::MaxValue)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName "TravianHourlyHealthCheck" -Action $action -Trigger $trigger -Principal $principal

Write-Host "✓ Scheduled task created: Hourly health check" -ForegroundColor Green
```

### Output

```
============================================================================
TRAVIANT4.6 - XAMPP HEALTH CHECK REPORT
============================================================================
Timestamp: 2025-11-01 14:30:15

[SERVICE HEALTH]
🟢 Apache: Running (Uptime: 2 days, 14 hours)
🟢 MySQL: Running (Uptime: 2 days, 14 hours)
🟢 PostgreSQL: Running (Uptime: 2 days, 14 hours)

[DISK SPACE]
🟢 C:\ Drive: 45.2 GB free (82% available)

[DATABASE CONNECTIVITY]
🟢 MySQL: Connected (Response time: 12ms)
🟢 PostgreSQL: Connected (Response time: 8ms)
🟢 Query execution: Success

[API HEALTH]
🟢 POST /v1/servers/loadServers: 200 OK (Response time: 145ms)
🟢 JSON format: Valid
🟢 Server count: 8 servers

[PROCESS METRICS]
🟢 Apache CPU: 2.3%
🟢 Apache Memory: 285 MB
🟢 MySQL CPU: 1.8%
🟢 MySQL Memory: 1.2 GB

[LOG FILES]
🟢 Apache error.log: 12.5 MB
🟢 MySQL error log: 3.2 MB

============================================================================
OVERALL HEALTH: HEALTHY 🟢
============================================================================
All checks passed: 15/15
Next check in: 5 minutes (Continuous mode)
```

### Alert Triggers

Alerts are sent when:
- 🔴 Any service stops unexpectedly
- 🔴 Database connectivity fails
- 🔴 API returns non-200 status code
- 🟡 Disk space below threshold
- 🟡 Log files exceed 100MB

### Log File

All health checks are logged to:
```
C:\xampp\htdocs\logs\healthcheck_YYYYMMDD.log
```

Example log entry:
```
[2025-11-01 14:30:15] ✓ Apache service is running
[2025-11-01 14:30:16] ✓ MySQL connection successful
[2025-11-01 14:30:17] ⚠ Disk space low: 8.2 GB free (threshold: 10 GB)
[2025-11-01 14:30:18] ✓ API endpoint health check passed
```

### Exit Codes

| Code | Meaning | Action Required |
|------|---------|-----------------|
| 0 | All checks healthy | None |
| 1 | Warnings detected | Review warnings, may need attention |
| 2 | Critical issues detected | Immediate action required |

### Estimated Runtime

- **One-Time Check**: 10-20 seconds
- **Continuous Mode**: Runs indefinitely until stopped

### Troubleshooting

#### "Service not found"
```powershell
# Check XAMPP Control Panel
# Verify services are started

# Check process manually
Get-Process | Where-Object {$_.Name -like "*httpd*" -or $_.Name -like "*mysql*"}
```

#### "API health check failed"
```powershell
# Test API manually
Invoke-WebRequest -Uri "http://localhost/v1/servers/loadServers" -Method POST -UseBasicParsing

# Check Apache error log
Get-Content C:\xampp\apache\logs\error.log -Tail 50
```

---

## Common Workflows

### Initial Setup Workflow

```powershell
# 1. Copy files to C:\xampp\htdocs
robocopy "C:\source" "C:\xampp\htdocs" /E /XD ".git" "node_modules" /MT:8

# 2. Start services
# Use XAMPP Control Panel: Apache → Start, MySQL → Start, PostgreSQL → Start

# 3. Run setup script
cd C:\xampp\htdocs\scripts
.\setup-xampp.ps1

# 4. Validate setup
.\validate-xampp.ps1

# 5. Optimize performance
.\xampp-performance-tune.ps1

# 6. Test application
Start-Process "http://localhost"

# 7. Schedule automated tasks
# See Windows Task Scheduler Integration section
```

### Daily Operations Workflow

```powershell
# Morning: Check health
cd C:\xampp\htdocs\scripts
.\xampp-healthcheck.ps1

# Review logs if any warnings
if ($LASTEXITCODE -ne 0) {
    Get-Content C:\xampp\apache\logs\error.log -Tail 100
    Get-Content C:\xampp\mysql\data\*.err -Tail 100
}

# Afternoon: Monitor services (continuous)
.\xampp-healthcheck.ps1 -Continuous -IntervalMinutes 30
```

### Weekly Maintenance Workflow

```powershell
# 1. Manual backup (in addition to automated daily)
cd C:\xampp\htdocs\scripts
.\xampp-backup.ps1 -Compress

# 2. Check backup integrity
$latestBackup = Get-ChildItem "C:\xampp\backups\daily\" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
Write-Host "Latest backup: $($latestBackup.FullName) ($([math]::Round($latestBackup.Length / 1MB, 2)) MB)"

# 3. Optimize MySQL tables
& C:\xampp\mysql\bin\mysql.exe -u travian -p"TravianDB2025!" -e "OPTIMIZE TABLE users;" travian_world_speed10k
# Repeat for all 8 worlds...

# 4. Review performance
.\xampp-performance-tune.ps1 -DryRun

# 5. Clear old logs (keep last 30 days)
Get-ChildItem "C:\xampp\apache\logs\*.log" | Where-Object {$_.LastWriteTime -lt (Get-Date).AddDays(-30)} | Remove-Item
```

### Troubleshooting Workflow

```powershell
# 1. Run validation to identify issues
cd C:\xampp\htdocs\scripts
.\validate-xampp.ps1 -Verbose

# 2. Check health status
.\xampp-healthcheck.ps1

# 3. Review logs
Get-Content C:\xampp\apache\logs\error.log -Tail 100
Get-Content C:\xampp\mysql\data\*.err -Tail 100
Get-Content C:\xampp\pgsql\logs\postgres.log -Tail 100

# 4. Restart services if needed
C:\xampp\apache_stop.bat
C:\xampp\mysql_stop.bat
Start-Sleep -Seconds 5
C:\xampp\mysql_start.bat
C:\xampp\apache_start.bat

# 5. Re-validate
.\validate-xampp.ps1
```

### Disaster Recovery Workflow

```powershell
# 1. Stop services
C:\xampp\apache_stop.bat
C:\xampp\mysql_stop.bat

# 2. Identify latest good backup
$latestBackup = Get-ChildItem "C:\xampp\backups\daily\" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
Write-Host "Restoring from: $($latestBackup.FullName)"

# 3. Restore databases (see xampp-backup.ps1 restore procedure)

# 4. Restart services
C:\xampp\mysql_start.bat
C:\xampp\apache_start.bat

# 5. Validate restoration
cd C:\xampp\htdocs\scripts
.\validate-xampp.ps1

# 6. Test application
Start-Process "http://localhost"
```

---

## Windows Task Scheduler Integration

### Recommended Schedule

| Task | Schedule | Command |
|------|----------|---------|
| Daily Backup | Every day at 2:00 AM | xampp-backup.ps1 -Compress |
| Hourly Health Check | Every hour | xampp-healthcheck.ps1 |
| Weekly Optimization | Sunday at 3:00 AM | xampp-performance-tune.ps1 -DryRun |

### Create Scheduled Tasks

#### Daily Backup (2 AM)

```powershell
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\scripts\xampp-backup.ps1 -Compress"

$trigger = New-ScheduledTaskTrigger -Daily -At 2am

$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

Register-ScheduledTask -TaskName "TravianDailyBackup" `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal `
    -Settings $settings `
    -Description "Daily backup of TravianT4.6 databases at 2:00 AM"

Write-Host "✓ Task created: TravianDailyBackup" -ForegroundColor Green
```

#### Hourly Health Check

```powershell
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\scripts\xampp-healthcheck.ps1"

$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
    -RepetitionInterval (New-TimeSpan -Hours 1) `
    -RepetitionDuration ([TimeSpan]::MaxValue)

$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

Register-ScheduledTask -TaskName "TravianHourlyHealthCheck" `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal `
    -Settings $settings `
    -Description "Hourly health check of TravianT4.6 services"

Write-Host "✓ Task created: TravianHourlyHealthCheck" -ForegroundColor Green
```

### Manage Scheduled Tasks

```powershell
# List all Travian tasks
Get-ScheduledTask | Where-Object {$_.TaskName -like "Travian*"}

# View task details
Get-ScheduledTask -TaskName "TravianDailyBackup" | Get-ScheduledTaskInfo

# Manually run a task
Start-ScheduledTask -TaskName "TravianDailyBackup"

# Disable a task
Disable-ScheduledTask -TaskName "TravianHourlyHealthCheck"

# Enable a task
Enable-ScheduledTask -TaskName "TravianHourlyHealthCheck"

# Remove a task
Unregister-ScheduledTask -TaskName "TravianDailyBackup" -Confirm:$false
```

### View Task Logs

```powershell
# Check if task ran successfully
Get-ScheduledTask -TaskName "TravianDailyBackup" | Get-ScheduledTaskInfo | Select-Object LastRunTime, LastTaskResult

# View task history (Event Viewer)
Get-WinEvent -LogName "Microsoft-Windows-TaskScheduler/Operational" | Where-Object {$_.Message -like "*Travian*"} | Select-Object -First 10
```

---

## Error Codes Reference

### Unified Exit Code Table

| Script | Exit Code | Meaning | Severity | Action Required |
|--------|-----------|---------|----------|-----------------|
| setup-xampp.ps1 | 0 | Success | ✅ Info | None |
| setup-xampp.ps1 | 1 | MySQL connection failed | 🔴 Critical | Check MySQL service, credentials |
| setup-xampp.ps1 | 2 | PostgreSQL connection failed | 🔴 Critical | Check PostgreSQL service, credentials |
| setup-xampp.ps1 | 3 | Schema application failed | 🔴 Critical | Check schema SQL files, permissions |
| setup-xampp.ps1 | 4 | User creation failed | 🟡 Warning | Check test user SQL files |
| setup-xampp.ps1 | 5 | Config generation failed | 🔴 Critical | Check file permissions |
| validate-xampp.ps1 | 0 | All checks passed | ✅ Info | None |
| validate-xampp.ps1 | 1 | One or more checks failed | 🔴 Critical | Review output, fix issues |
| xampp-backup.ps1 | 0 | Backup successful | ✅ Info | None |
| xampp-backup.ps1 | 1 | Backup failed | 🔴 Critical | Check disk space, permissions |
| xampp-performance-tune.ps1 | 0 | Optimization successful | ✅ Info | None |
| xampp-performance-tune.ps1 | 1 | Failed to apply changes | 🔴 Critical | Check file permissions, service status |
| xampp-healthcheck.ps1 | 0 | All checks healthy | ✅ Info | None |
| xampp-healthcheck.ps1 | 1 | Warnings detected | 🟡 Warning | Review warnings |
| xampp-healthcheck.ps1 | 2 | Critical issues detected | 🔴 Critical | Immediate action required |

### Checking Exit Codes

```powershell
# Run script and capture exit code
.\setup-xampp.ps1
$exitCode = $LASTEXITCODE

# Check result
if ($exitCode -eq 0) {
    Write-Host "✓ Script completed successfully" -ForegroundColor Green
} else {
    Write-Host "✗ Script failed with exit code: $exitCode" -ForegroundColor Red
}
```

---

## Troubleshooting

### Common Issues Across All Scripts

#### Issue: "Execution Policy" Error

```
.\setup-xampp.ps1 : File C:\xampp\htdocs\scripts\setup-xampp.ps1 cannot be loaded because
running scripts is disabled on this system.
```

**Solution**:
```powershell
# Check current execution policy
Get-ExecutionPolicy

# Set to RemoteSigned (allows local scripts)
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser

# Or bypass for this session only
powershell.exe -ExecutionPolicy Bypass -File .\setup-xampp.ps1
```

#### Issue: "Access Denied" / Permission Errors

**Solution**:
```powershell
# Run PowerShell as Administrator
# Right-click PowerShell → Run as Administrator

# Or use runas
runas /user:Administrator powershell.exe
```

#### Issue: Scripts Not Found

```
.\setup-xampp.ps1 : The term '.\setup-xampp.ps1' is not recognized...
```

**Solution**:
```powershell
# Navigate to scripts directory
cd C:\xampp\htdocs\scripts

# Verify file exists
Test-Path .\setup-xampp.ps1

# If missing, re-copy from source or XAMPP-DEPLOYMENT-GUIDE.md
```

#### Issue: MySQL/PostgreSQL Binaries Not Found

**Solution**:
```powershell
# Check paths exist
Test-Path "C:\xampp\mysql\bin\mysql.exe"
Test-Path "C:\xampp\pgsql\bin\psql.exe"

# If missing, verify XAMPP installation
# Reinstall XAMPP or PostgreSQL addon if needed
```

#### Issue: Database Connection Timeouts

**Solution**:
```powershell
# Check services are running
Get-Process | Where-Object {$_.Name -like "*mysql*" -or $_.Name -like "*postgres*"}

# Start services via XAMPP Control Panel

# Check ports are listening
netstat -ano | findstr ":3306"
netstat -ano | findstr ":5432"

# Test connection manually
& C:\xampp\mysql\bin\mysql.exe -u root -p
& C:\xampp\pgsql\bin\psql.exe -U postgres
```

### Script-Specific Issues

#### setup-xampp.ps1: "Database already exists"

**Solution**:
```powershell
# Use -Force to recreate
.\setup-xampp.ps1 -Force
```

#### validate-xampp.ps1: "API endpoint returned 404"

**Solution**:
```powershell
# Check .htaccess exists
Test-Path C:\xampp\htdocs\.htaccess

# Check mod_rewrite enabled
Select-String "LoadModule rewrite_module" C:\xampp\apache\conf\httpd.conf

# If not enabled, uncomment the line and restart Apache
```

#### xampp-backup.ps1: "Insufficient disk space"

**Solution**:
```powershell
# Check free space
Get-PSDrive C | Select-Object Used,Free

# Use different backup location
.\xampp-backup.ps1 -BackupPath "D:\Backups"
```

#### xampp-performance-tune.ps1: "Failed to modify config file"

**Solution**:
```powershell
# Check file permissions
icacls C:\xampp\php\php.ini

# Grant write permissions
icacls C:\xampp\php\php.ini /grant "Users:M"

# Run as Administrator
```

#### xampp-healthcheck.ps1: "Service not found"

**Solution**:
```powershell
# Verify services started in XAMPP Control Panel
# Apache, MySQL, PostgreSQL should show "Running"

# If not, start manually
C:\xampp\apache_start.bat
C:\xampp\mysql_start.bat
```

### Getting Help

If issues persist:

1. **Review full deployment guide**: [XAMPP-DEPLOYMENT-GUIDE.md](XAMPP-DEPLOYMENT-GUIDE.md)
2. **Check XAMPP logs**:
   - Apache: `C:\xampp\apache\logs\error.log`
   - MySQL: `C:\xampp\mysql\data\*.err`
   - PostgreSQL: `C:\xampp\pgsql\logs\postgres.log`
3. **Validate setup**: Run `.\validate-xampp.ps1 -Verbose`
4. **Check health**: Run `.\xampp-healthcheck.ps1`

---

## Document Information

**Version**: 2.0  
**Last Updated**: November 1, 2025  
**Maintained By**: TravianT4.6 Development Team  
**Related Documents**:
- [XAMPP-DEPLOYMENT-GUIDE.md](XAMPP-DEPLOYMENT-GUIDE.md) - Complete deployment guide
- [XAMPP-QUICKSTART.md](XAMPP-QUICKSTART.md) - Quick reference
- [WINDOWS-DEPLOYMENT-GUIDE.md](WINDOWS-DEPLOYMENT-GUIDE.md) - Docker alternative

---

**End of Document**
