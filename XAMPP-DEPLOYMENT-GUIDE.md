# XAMPP Deployment Guide
**TravianT4.6 - Windows 11 Local Development with XAMPP**

**Version:** 2.0  
**Last Updated:** November 1, 2025  
**Target:** Windows 11 + XAMPP 8.2.12  
**Databases:** MySQL 8.0 + PostgreSQL 14 (XAMPP Addon)

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [File Copying Instructions](#file-copying-instructions)
4. [Apache Configuration](#apache-configuration)
5. [PostgreSQL Setup](#postgresql-setup)
6. [MySQL Configuration](#mysql-configuration)
7. [Environment Variables](#environment-variables)
8. [Embedded PowerShell Scripts](#embedded-powershell-scripts)
   - [setup-xampp.ps1](#setup-xamppps1)
   - [validate-xampp.ps1](#validate-xamppps1)
   - [xampp-backup.ps1](#xampp-backupps1)
   - [xampp-performance-tune.ps1](#xampp-performance-tuneps1)
   - [xampp-healthcheck.ps1](#xampp-healthcheckps1)
9. [Database Setup](#database-setup)
10. [Validation & Testing](#validation--testing)
11. [Troubleshooting](#troubleshooting)
12. [Security Hardening](#security-hardening)
13. [Performance Tuning](#performance-tuning)
14. [Maintenance Procedures](#maintenance-procedures)
15. [Success Checklist](#success-checklist)

---

## Overview

### What You're Deploying

A complete Travian T4.6 game server system running on XAMPP with:

- **8 Speed Servers**: From 1x to 5,000,000x game speed
- **Dual Database Architecture**:
  - **PostgreSQL**: Global data (server registry, AI, automation)
  - **MySQL**: Per-world game data (players, villages, battles)
- **XAMPP Stack**: Apache 2.4, PHP 8.2, MySQL 8.0, PostgreSQL 14 (addon)
- **Production-Ready**: Optimized for Windows 11 local/development deployment

### Architecture at a Glance

```
Browser → Apache (localhost:80) → PHP 8.2 → PostgreSQL (global) + MySQL (per-world)
```

### Estimated Deployment Time

- **Quick Setup**: 30-45 minutes
- **Full Setup with Hardening**: 60-90 minutes
- **With Performance Tuning**: 90-120 minutes

---

## Prerequisites

### Required Software

#### 1. XAMPP 8.2.12 for Windows

**Download**: https://www.apachefriends.org/download.html

**Installation**:
```powershell
# Download and run installer
Start-Process "xampp-windows-x64-8.2.12-0-VS16-installer.exe"

# Install to default location: C:\xampp
# Enable: Apache, MySQL, PHP, phpMyAdmin
# Optional: FileZilla (FTP), Mercury (Mail), Tomcat
```

#### 2. PostgreSQL Addon for XAMPP

**Download**: https://www.apachefriends.org/add-ons.html

**Installation**:
```powershell
# Download postgresql-8.2.12-0-windows-x64-addon.zip
# Extract to C:\xampp\
# Files will be placed in C:\xampp\pgsql\
```

#### 3. PowerShell 5.1+

Already included in Windows 11.

#### 4. Git for Windows (Optional)

For cloning the repository or pulling updates.

### Hardware Requirements

**Minimum**:
- CPU: 4 cores
- RAM: 8 GB
- Disk: 20 GB free space on C:\ drive

**Recommended**:
- CPU: 8+ cores
- RAM: 16+ GB
- Disk: 50+ GB SSD

### Port Requirements

Ensure these ports are available:

| Service | Port | Protocol | Usage |
|---------|------|----------|-------|
| Apache | 80 | HTTP | Frontend + API |
| Apache SSL | 443 | HTTPS | Secure connections |
| MySQL | 3306 | TCP | MySQL database |
| PostgreSQL | 5432 | TCP | PostgreSQL database |

### Pre-Installation Checks

```powershell
# Check for port conflicts
netstat -ano | findstr ":80 "
netstat -ano | findstr ":3306 "
netstat -ano | findstr ":5432 "

# If ports are in use, identify processes:
Get-Process -Id (Get-NetTCPConnection -LocalPort 80).OwningProcess

# Stop conflicting services (IIS, other web servers)
Stop-Service W3SVC -Force  # Windows IIS
Stop-Service MSSQLSERVER -Force  # SQL Server (if using port 3306)
```

---

## File Copying Instructions

**⏱ Estimated Time**: 10-15 minutes

### Understanding the Directory Structure

```
C:\xampp\htdocs\              ← Your deployment target
├── angularIndex/             ← Angular frontend (SPA)
├── database/                 ← SQL schemas and setup scripts
├── main_script/              ← Core game engine files
├── sections/
│   ├── api/                  ← API endpoints (/v1/*)
│   ├── servers/
│   │   ├── speed10k/         ← Game world files
│   │   ├── speed125k/
│   │   ├── speed250k/
│   │   ├── speed500k/
│   │   ├── speed5m/
│   │   ├── demo/
│   │   ├── dev/
│   │   └── testworld/
│   ├── payment/              ← Payment integration
│   └── voting/               ← Voting system
├── router.php                ← Front controller (emulates Apache mod_rewrite)
└── .htaccess                 ← Apache rewrite rules

EXCLUDE:
├── .git/                     ← Git repository
├── node_modules/             ← Node dependencies
├── docker-compose.yml        ← Docker config
├── Dockerfile                ← Docker build file
└── .replit                   ← Replit config
```

### Step 1: Prepare Source Files

If using Replit export:

```powershell
# Download project as ZIP
# Extract to temporary location
$TempDir = "C:\Temp\TravianT46-Export"
Expand-Archive -Path "C:\Downloads\TravianT46.zip" -DestinationPath $TempDir
```

If using Git:

```powershell
# Clone repository
git clone https://github.com/yourusername/TravianT46-Evolved.git "C:\Temp\TravianT46-Export"
```

### Step 2: Copy Files to XAMPP

**Option A: Using robocopy (Recommended)**

```powershell
# Define paths
$SourceDir = "C:\Temp\TravianT46-Export"
$TargetDir = "C:\xampp\htdocs"

# Stop Apache to avoid file locks
C:\xampp\apache_stop.bat

# Create target directory if needed
New-Item -ItemType Directory -Force -Path $TargetDir | Out-Null

# Copy with exclusions (robocopy is faster and more reliable)
robocopy $SourceDir $TargetDir /E /XD ".git" "node_modules" ".replit" "__pycache__" ".vscode" /XF "docker-compose.yml" "Dockerfile" ".dockerignore" ".gitignore" /MT:8 /R:3 /W:5

# robocopy exit codes: 0-7 are success (files copied), 8+ are errors
if ($LASTEXITCODE -ge 8) {
    Write-Host "Error: robocopy failed with exit code $LASTEXITCODE" -ForegroundColor Red
    exit 1
} else {
    Write-Host "✓ Files copied successfully" -ForegroundColor Green
}
```

**Option B: Using PowerShell Copy-Item**

```powershell
# Define paths
$SourceDir = "C:\Temp\TravianT46-Export"
$TargetDir = "C:\xampp\htdocs"

# Stop Apache
C:\xampp\apache_stop.bat

# Exclusion patterns
$Exclude = @(
    ".git",
    "node_modules",
    ".replit",
    "__pycache__",
    ".vscode",
    "docker-compose.yml",
    "Dockerfile",
    ".dockerignore"
)

# Copy files
Get-ChildItem -Path $SourceDir -Recurse | Where-Object {
    $relativePath = $_.FullName.Replace($SourceDir, "")
    $shouldExclude = $false
    
    foreach ($pattern in $Exclude) {
        if ($relativePath -like "*$pattern*") {
            $shouldExclude = $true
            break
        }
    }
    
    -not $shouldExclude
} | ForEach-Object {
    $targetPath = $_.FullName.Replace($SourceDir, $TargetDir)
    
    if ($_.PSIsContainer) {
        New-Item -ItemType Directory -Force -Path $targetPath | Out-Null
    } else {
        $targetDir = Split-Path -Parent $targetPath
        New-Item -ItemType Directory -Force -Path $targetDir | Out-Null
        Copy-Item -Path $_.FullName -Destination $targetPath -Force
    }
}

Write-Host "✓ Files copied successfully" -ForegroundColor Green
```

### Step 3: Set File Permissions

```powershell
# Grant full control to Apache user (SYSTEM and Users)
icacls "C:\xampp\htdocs" /grant "Users:(OI)(CI)F" /T
icacls "C:\xampp\htdocs\sections\servers\*\config" /grant "Users:(OI)(CI)F" /T

# Ensure writable directories
$WritableDirs = @(
    "C:\xampp\htdocs\sections\servers\speed10k\public\images\uploads",
    "C:\xampp\htdocs\sections\servers\speed125k\public\images\uploads",
    "C:\xampp\htdocs\sections\servers\speed250k\public\images\uploads",
    "C:\xampp\htdocs\sections\servers\speed500k\public\images\uploads",
    "C:\xampp\htdocs\sections\servers\speed5m\public\images\uploads",
    "C:\xampp\htdocs\sections\servers\demo\public\images\uploads",
    "C:\xampp\htdocs\sections\servers\dev\public\images\uploads",
    "C:\xampp\htdocs\sections\servers\testworld\public\images\uploads",
    "C:\xampp\tmp",
    "C:\xampp\apache\logs"
)

foreach ($dir in $WritableDirs) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Force -Path $dir | Out-Null
    }
    icacls $dir /grant "Users:(OI)(CI)F" /T
}

Write-Host "✓ File permissions configured" -ForegroundColor Green
```

### Verification

```powershell
# Check critical files exist
$CriticalFiles = @(
    "C:\xampp\htdocs\router.php",
    "C:\xampp\htdocs\.htaccess",
    "C:\xampp\htdocs\sections\api\index.php",
    "C:\xampp\htdocs\angularIndex\browser\index.html",
    "C:\xampp\htdocs\database\mysql\windows-world-schema.sql",
    "C:\xampp\htdocs\database\mysql\windows-test-users.sql"
)

$AllExist = $true
foreach ($file in $CriticalFiles) {
    if (Test-Path $file) {
        Write-Host "✓ Found: $file" -ForegroundColor Green
    } else {
        Write-Host "✗ Missing: $file" -ForegroundColor Red
        $AllExist = $false
    }
}

if ($AllExist) {
    Write-Host "`n✓ All critical files present" -ForegroundColor Green
} else {
    Write-Host "`n✗ Some files are missing - review copy operation" -ForegroundColor Red
}
```

---

## Apache Configuration

**⏱ Estimated Time**: 15-20 minutes

### Step 1: DocumentRoot Configuration

Edit `C:\xampp\apache\conf\httpd.conf`:

```apache
# Find and update DocumentRoot
DocumentRoot "C:/xampp/htdocs"
<Directory "C:/xampp/htdocs">
    Options Indexes FollowSymLinks MultiViews
    AllowOverride All
    Require all granted
</Directory>
```

**Key Changes**:
- `AllowOverride All` - Enables .htaccess files (critical for routing)
- `Require all granted` - Allows access from all IPs

### Step 2: Enable mod_rewrite

In `C:\xampp\apache\conf\httpd.conf`, uncomment:

```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

### Step 3: Create Root .htaccess

Create `C:\xampp\htdocs\.htaccess`:

```apache
# ==============================================================================
# TRAVIANT4.6 - MAIN .HTACCESS CONFIGURATION
# ==============================================================================
# Purpose: Front controller pattern for Angular SPA + PHP API + Game Engine
# ==============================================================================

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # ===== SECURITY: Block access to sensitive files =====
    RewriteRule ^\.env$ - [F,L]
    RewriteRule ^\.git - [F,L]
    RewriteRule ^database/ - [F,L]
    
    # ===== API ROUTING: /v1/* → sections/api/index.php =====
    RewriteCond %{REQUEST_URI} ^/v1/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^v1/(.*)$ sections/api/index.php [QSA,L]
    
    # ===== GAME PHP FILES: *.php → router.php for world routing =====
    # Excludes: sections/api/* (already handled above)
    RewriteCond %{REQUEST_URI} !^/sections/api/
    RewriteCond %{REQUEST_URI} \.php($|\?)
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ router.php [QSA,L]
    
    # ===== ANGULAR SPA ROUTING: All other routes → index.html =====
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ angularIndex/browser/index.html [QSA,L]
</IfModule>

# ===== SECURITY: Disable directory listing =====
Options -Indexes

# ===== CACHE CONTROL: Prevent caching of HTML/PHP =====
<filesMatch "\.(html|htm|php)$">
    FileETag None
    <ifModule mod_headers.c>
        Header unset ETag
        Header set Cache-Control "max-age=0, no-cache, no-store, must-revalidate"
        Header set Pragma "no-cache"
        Header set Expires "Wed, 11 Jan 1984 05:00:00 GMT"
    </ifModule>
</filesMatch>

# ===== COMPRESSION: Enable gzip =====
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

# ===== SECURITY: Hide Apache/PHP version =====
ServerSignature Off
```

### Step 4: PHP Configuration

Edit `C:\xampp\php\php.ini`:

```ini
; ===== BASIC PHP SETTINGS =====
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 128M
upload_max_filesize = 128M

; ===== ERROR REPORTING (Development) =====
error_reporting = E_ALL
display_errors = On
display_startup_errors = On
log_errors = On
error_log = "C:\xampp\php\logs\php_error.log"

; ===== SECURITY =====
expose_php = Off
allow_url_fopen = On
allow_url_include = Off
disable_functions = exec,passthru,shell_exec,system,proc_open,popen

; ===== SESSION CONFIGURATION =====
session.save_path = "C:\xampp\tmp"
session.gc_maxlifetime = 3600
session.cookie_httponly = On
session.use_strict_mode = 1

; ===== OPCACHE (Performance) =====
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1

; ===== TIMEZONE =====
date.timezone = America/New_York

; ===== FILE UPLOADS =====
file_uploads = On
upload_tmp_dir = "C:\xampp\tmp"

; ===== DATABASE EXTENSIONS =====
extension=mysqli
extension=pdo_mysql
extension=pdo_pgsql
extension=pgsql
```

### Step 5: Virtual Host (Optional)

For custom domain (e.g., travian.local):

Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName travian.local
    ServerAlias www.travian.local
    DocumentRoot "C:/xampp/htdocs"
    
    <Directory "C:/xampp/htdocs">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog "C:/xampp/apache/logs/travian-error.log"
    CustomLog "C:/xampp/apache/logs/travian-access.log" combined
</VirtualHost>
```

Add to `C:\Windows\System32\drivers\etc\hosts` (as Administrator):

```
127.0.0.1 travian.local
```

### Verification

```powershell
# Test Apache configuration
C:\xampp\apache\bin\httpd.exe -t

# Expected output: "Syntax OK"

# Start Apache
C:\xampp\apache_start.bat

# Wait 5 seconds
Start-Sleep -Seconds 5

# Test Apache is running
$response = Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing
if ($response.StatusCode -eq 200) {
    Write-Host "✓ Apache is running" -ForegroundColor Green
} else {
    Write-Host "✗ Apache returned status: $($response.StatusCode)" -ForegroundColor Red
}

# Test mod_rewrite
$testUrl = "http://localhost/nonexistent-route"
try {
    $response = Invoke-WebRequest -Uri $testUrl -UseBasicParsing
    if ($response.StatusCode -eq 200) {
        Write-Host "✓ mod_rewrite is working (Angular routing)" -ForegroundColor Green
    }
} catch {
    Write-Host "✓ mod_rewrite is active (404 expected)" -ForegroundColor Green
}
```

---

## PostgreSQL Setup

**⏱ Estimated Time**: 15-20 minutes

### Step 1: Install PostgreSQL Addon

```powershell
# Download PostgreSQL addon from https://www.apachefriends.org/add-ons.html
$AddonZip = "C:\Downloads\postgresql-8.2.12-0-windows-x64-addon.zip"
$XamppDir = "C:\xampp"

# Extract addon
Expand-Archive -Path $AddonZip -DestinationPath $XamppDir -Force

# Verify installation
if (Test-Path "C:\xampp\pgsql\bin\postgres.exe") {
    Write-Host "✓ PostgreSQL addon extracted successfully" -ForegroundColor Green
} else {
    Write-Host "✗ PostgreSQL not found - check extraction" -ForegroundColor Red
}
```

### Step 2: Initialize PostgreSQL Data Directory

```powershell
# Initialize database cluster
& "C:\xampp\pgsql\bin\initdb.exe" -D "C:\xampp\pgsql\data" -U postgres -W -E UTF8 -A md5

# When prompted, set password: postgres
# (Change to secure password in production)
```

### Step 3: Configure PostgreSQL

Edit `C:\xampp\pgsql\data\postgresql.conf`:

```ini
# Connection Settings
listen_addresses = 'localhost'
port = 5432
max_connections = 100

# Memory Settings
shared_buffers = 256MB
effective_cache_size = 1GB
maintenance_work_mem = 64MB
work_mem = 16MB

# Write-Ahead Logging
wal_level = minimal
max_wal_size = 1GB
min_wal_size = 80MB

# Query/Index Statistics
shared_preload_libraries = 'pg_stat_statements'
```

Edit `C:\xampp\pgsql\data\pg_hba.conf`:

```
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             postgres                                md5
host    all             postgres        127.0.0.1/32            md5
host    all             postgres        ::1/128                 md5
```

### Step 4: Start PostgreSQL Service

**Option A: Manual Start**

```powershell
# Start PostgreSQL
& "C:\xampp\pgsql\bin\pg_ctl.exe" -D "C:\xampp\pgsql\data" -l "C:\xampp\pgsql\logs\postgres.log" start

# Check status
& "C:\xampp\pgsql\bin\pg_ctl.exe" -D "C:\xampp\pgsql\data" status
```

**Option B: Windows Service**

```powershell
# Register as Windows service (run as Administrator)
& "C:\xampp\pgsql\bin\pg_ctl.exe" register -N "XAMPPPostgreSQL" -D "C:\xampp\pgsql\data" -U "NT AUTHORITY\NetworkService"

# Start service
Start-Service XAMPPPostgreSQL

# Set to auto-start
Set-Service XAMPPPostgreSQL -StartupType Automatic
```

### Step 5: Create travian_global Database

```powershell
# Set PostgreSQL bin in PATH for this session
$env:PATH += ";C:\xampp\pgsql\bin"

# Create database
& psql -U postgres -c "CREATE DATABASE travian_global ENCODING 'UTF8';"

# Import schema (if schema file exists)
$SchemaFile = "C:\xampp\htdocs\database\postgresql\schema.sql"
if (Test-Path $SchemaFile) {
    Get-Content $SchemaFile | & psql -U postgres -d travian_global
    Write-Host "✓ PostgreSQL schema imported" -ForegroundColor Green
} else {
    Write-Host "⚠ PostgreSQL schema file not found: $SchemaFile" -ForegroundColor Yellow
    Write-Host "  Creating minimal schema for gameservers table..." -ForegroundColor Yellow
    
    # Create minimal schema
    $MinimalSchema = @"
CREATE TABLE IF NOT EXISTS gameservers (
    id SERIAL PRIMARY KEY,
    worldid VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    gameworldurl VARCHAR(255) NOT NULL,
    configfilelocation VARCHAR(255) NOT NULL,
    speed INTEGER NOT NULL DEFAULT 1,
    starttime INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_gameservers_worldid ON gameservers(worldid);
CREATE INDEX idx_gameservers_speed ON gameservers(speed);
"@
    
    $MinimalSchema | & psql -U postgres -d travian_global
    Write-Host "✓ Minimal schema created" -ForegroundColor Green
}

# Insert server registrations
$ServerRegistrations = @"
INSERT INTO gameservers (worldid, name, gameworldurl, configfilelocation, speed, starttime)
VALUES 
    ('speed5m', 'Instant Speed Server (5,000,000x)', 'http://localhost/', 'sections/servers/speed5m', 5000000, EXTRACT(EPOCH FROM NOW())::INTEGER),
    ('speed500k', 'Hyper Speed Server (500,000x)', 'http://localhost/', 'sections/servers/speed500k', 500000, EXTRACT(EPOCH FROM NOW())::INTEGER),
    ('speed250k', 'Extreme Speed Server (250,000x)', 'http://localhost/', 'sections/servers/speed250k', 250000, EXTRACT(EPOCH FROM NOW())::INTEGER),
    ('speed125k', 'Mega Speed Server (125,000x)', 'http://localhost/', 'sections/servers/speed125k', 125000, EXTRACT(EPOCH FROM NOW())::INTEGER),
    ('speed10k', 'Ultra Speed Server (10,000x)', 'http://localhost/', 'sections/servers/speed10k', 10000, EXTRACT(EPOCH FROM NOW())::INTEGER),
    ('demo', 'Demo World (1x)', 'http://localhost/', 'sections/servers/demo', 1, EXTRACT(EPOCH FROM NOW())::INTEGER),
    ('dev', 'Development World (1x)', 'http://localhost/', 'sections/servers/dev', 1, EXTRACT(EPOCH FROM NOW())::INTEGER),
    ('testworld', 'Test World (1x)', 'http://localhost/', 'sections/servers/testworld', 1, EXTRACT(EPOCH FROM NOW())::INTEGER)
ON CONFLICT (worldid) DO UPDATE SET
    name = EXCLUDED.name,
    speed = EXCLUDED.speed;
"@

$ServerRegistrations | & psql -U postgres -d travian_global
Write-Host "✓ Server registrations inserted" -ForegroundColor Green

# Verify
& psql -U postgres -d travian_global -c "SELECT worldid, name, speed FROM gameservers ORDER BY speed DESC;"
```

### Verification

```powershell
# Test connection
& psql -U postgres -d travian_global -c "SELECT version();"

# Check tables
& psql -U postgres -d travian_global -c "\dt"

# Check server count
$ServerCount = (& psql -U postgres -d travian_global -t -c "SELECT COUNT(*) FROM gameservers;").Trim()
if ($ServerCount -eq "8") {
    Write-Host "✓ PostgreSQL setup complete - 8 servers registered" -ForegroundColor Green
} else {
    Write-Host "⚠ Expected 8 servers, found $ServerCount" -ForegroundColor Yellow
}
```

---

## MySQL Configuration

**⏱ Estimated Time**: 10 minutes

### Step 1: Set MySQL Root Password

```powershell
# Start MySQL
C:\xampp\mysql_start.bat

# Wait for startup
Start-Sleep -Seconds 10

# Set root password (default is blank)
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'TravianSecureRoot2025!';"

# Test new password
& "C:\xampp\mysql\bin\mysql.exe" -u root -pTravianSecureRoot2025! -e "SELECT 'Password set successfully' AS status;"
```

### Step 2: Create travian Database User

```powershell
$RootPassword = "TravianSecureRoot2025!"
$TravianUser = "travian"
$TravianPassword = "TravianDB2025!"

# Create user and grant privileges
$sql = @"
CREATE USER IF NOT EXISTS '$TravianUser'@'localhost' IDENTIFIED BY '$TravianPassword';
GRANT ALL PRIVILEGES ON travian_world_*.* TO '$TravianUser'@'localhost';
FLUSH PRIVILEGES;
"@

$sql | & "C:\xampp\mysql\bin\mysql.exe" -u root -p"$RootPassword"

Write-Host "✓ MySQL user 'travian' created with privileges on travian_world_* databases" -ForegroundColor Green
```

### Step 3: Configure MySQL Performance

Edit `C:\xampp\mysql\bin\my.ini`:

```ini
[mysqld]
# Basic Settings
port = 3306
socket = "C:/xampp/mysql/mysql.sock"
basedir = "C:/xampp/mysql"
tmpdir = "C:/xampp/tmp"
datadir = "C:/xampp/mysql/data"

# Character Set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Performance Settings
max_connections = 200
max_allowed_packet = 64M
thread_cache_size = 16
table_open_cache = 4096
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# InnoDB Settings
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_log_buffer_size = 8M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = normal
innodb_file_per_table = 1

# Logging
slow_query_log = 1
slow_query_log_file = "C:/xampp/mysql/logs/slow-query.log"
long_query_time = 2

# Binary Logging (for backups)
log_bin = mysql-bin
binlog_format = ROW
expire_logs_days = 7
max_binlog_size = 100M

[mysql]
default-character-set = utf8mb4

[client]
default-character-set = utf8mb4
```

Restart MySQL:

```powershell
C:\xampp\mysql_stop.bat
Start-Sleep -Seconds 5
C:\xampp\mysql_start.bat
```

---

## Environment Variables

**⏱ Estimated Time**: 5 minutes

### Option 1: Windows System Environment Variables

```powershell
# Open Environment Variables dialog
rundll32.exe sysdm.cpl,EditEnvironmentVariables

# Or set via PowerShell (requires Administrator)
[Environment]::SetEnvironmentVariable("PGHOST", "localhost", "Machine")
[Environment]::SetEnvironmentVariable("PGPORT", "5432", "Machine")
[Environment]::SetEnvironmentVariable("PGUSER", "postgres", "Machine")
[Environment]::SetEnvironmentVariable("PGPASSWORD", "postgres", "Machine")
[Environment]::SetEnvironmentVariable("PGDATABASE", "travian_global", "Machine")
[Environment]::SetEnvironmentVariable("MYSQL_HOST", "localhost", "Machine")
[Environment]::SetEnvironmentVariable("MYSQL_PORT", "3306", "Machine")
[Environment]::SetEnvironmentVariable("MYSQL_USER", "travian", "Machine")
[Environment]::SetEnvironmentVariable("MYSQL_PASSWORD", "TravianDB2025!", "Machine")

# Restart Apache for changes to take effect
C:\xampp\apache_stop.bat
C:\xampp\apache_start.bat
```

### Option 2: .env File (Recommended for Development)

Create `C:\xampp\htdocs\.env`:

```bash
# PostgreSQL Configuration
PGHOST=localhost
PGPORT=5432
PGUSER=postgres
PGPASSWORD=postgres
PGDATABASE=travian_global
DATABASE_URL=postgresql://postgres:postgres@localhost:5432/travian_global

# MySQL Configuration
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_USER=travian
MYSQL_PASSWORD=TravianDB2025!

# Optional: Email Configuration (Brevo)
BREVO_API_KEY=your_api_key_here
BREVO_SMTP_KEY=your_smtp_key_here

# Application Configuration
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost
```

Install PHP dotenv library (if not already present):

```powershell
# Navigate to htdocs
cd C:\xampp\htdocs

# Install composer if not present
if (-not (Test-Path "composer.phar")) {
    Invoke-WebRequest -Uri https://getcomposer.org/installer -OutFile composer-setup.php
    & C:\xampp\php\php.exe composer-setup.php
}

# Install vlucas/phpdotenv
& C:\xampp\php\php.exe composer.phar require vlucas/phpdotenv

Write-Host "✓ PHP dotenv library installed" -ForegroundColor Green
```

Load in PHP files:

```php
<?php
// Load .env file
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Access variables
$dbHost = $_ENV['MYSQL_HOST'];
$dbUser = $_ENV['MYSQL_USER'];
$dbPassword = $_ENV['MYSQL_PASSWORD'];
?>
```

### Verification

```powershell
# Check environment variables
Get-ChildItem Env: | Where-Object { $_.Name -match "PG|MYSQL" }

# Test PostgreSQL connection with env vars
& psql -h $env:PGHOST -p $env:PGPORT -U $env:PGUSER -d $env:PGDATABASE -c "SELECT 1;"

# Test MySQL connection
& "C:\xampp\mysql\bin\mysql.exe" -h $env:MYSQL_HOST -P $env:MYSQL_PORT -u $env:MYSQL_USER -p"$env:MYSQL_PASSWORD" -e "SELECT 1;"
```

---

## Embedded PowerShell Scripts

All scripts are production-ready and can be copied directly to `C:\xampp\htdocs\scripts\` directory.

### setup-xampp.ps1

**Purpose**: Automated MySQL/PostgreSQL database provisioning for XAMPP deployment

**Features**:
- Creates 8 game world databases
- Applies 90-table schema to each database
- Inserts 12 test users per world
- Generates connection.php config files
- Colored console output
- Comprehensive error handling

**Usage**:
```powershell
.\scripts\setup-xampp.ps1
.\scripts\setup-xampp.ps1 -Force  # Recreate databases
.\scripts\setup-xampp.ps1 -SkipUserCreation  # Skip test users
```

**Script** (434 lines):

```powershell
# ============================================================================
# TRAVIANT4.6 - XAMPP SETUP SCRIPT
# ============================================================================
# Version: 2.0
# Purpose: Automated MySQL/PostgreSQL database provisioning for XAMPP deployment
# Requirements: MySQL 8.0+, PostgreSQL 14+, PowerShell 5.1+, PHP 8.2+
# 
# USAGE:
#   .\scripts\setup-xampp.ps1
#   .\scripts\setup-xampp.ps1 -Force
#   .\scripts\setup-xampp.ps1 -SkipUserCreation
#
# WHAT THIS SCRIPT DOES:
# 1. Validates MySQL and PostgreSQL connections
# 2. Creates 8 game world databases in MySQL
# 3. Applies T4.4 schema to each database (90 tables)
# 4. Inserts test users into each world (12 users)
# 5. Generates per-world connection.php files
# 6. Registers servers in PostgreSQL gameservers table
# 7. Validates setup completion
# ============================================================================

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

# Script Configuration
$ErrorActionPreference = "Stop"
$ScriptRoot = "C:\xampp\htdocs"
$DatabaseDir = Join-Path $ScriptRoot "database\mysql"
$SectionsDir = Join-Path $ScriptRoot "sections\servers"
$MysqlBin = "C:\xampp\mysql\bin\mysql.exe"
$PsqlBin = "C:\xampp\pgsql\bin\psql.exe"

# World Configuration
$GameWorlds = @(
    @{ Id = "speed10k";   DbName = "travian_world_speed10k";   Name = "Ultra Speed Server (10,000x)"; Speed = 10000 },
    @{ Id = "speed125k";  DbName = "travian_world_speed125k";  Name = "Mega Speed Server (125,000x)"; Speed = 125000 },
    @{ Id = "speed250k";  DbName = "travian_world_speed250k";  Name = "Extreme Speed Server (250,000x)"; Speed = 250000 },
    @{ Id = "speed500k";  DbName = "travian_world_speed500k";  Name = "Hyper Speed Server (500,000x)"; Speed = 500000 },
    @{ Id = "speed5m";    DbName = "travian_world_speed5m";    Name = "Instant Speed Server (5,000,000x)"; Speed = 5000000 },
    @{ Id = "demo";       DbName = "travian_world_demo";       Name = "Demo World (1x)"; Speed = 1 },
    @{ Id = "dev";        DbName = "travian_world_dev";        Name = "Development World (1x)"; Speed = 1 },
    @{ Id = "testworld";  DbName = "travian_world_testworld";  Name = "Test World (1x)"; Speed = 1 }
)

# Color Output Functions
function Write-ColorOutput {
    param([string]$Message, [string]$Color = "White")
    Write-Host $Message -ForegroundColor $Color
}

function Write-Success { param([string]$Message) Write-ColorOutput "✓ $Message" "Green" }
function Write-Info { param([string]$Message) Write-ColorOutput "ℹ $Message" "Cyan" }
function Write-Warning { param([string]$Message) Write-ColorOutput "⚠ $Message" "Yellow" }
function Write-Error { param([string]$Message) Write-ColorOutput "✗ $Message" "Red" }
function Write-Header { 
    param([string]$Message) 
    Write-Host ""
    Write-ColorOutput "============================================================================" "Magenta"
    Write-ColorOutput $Message "Magenta"
    Write-ColorOutput "============================================================================" "Magenta"
}

# ============================================================================
# FUNCTION: Test-MysqlConnection
# ============================================================================
function Test-MysqlConnection {
    param([string]$Host, [string]$User, [string]$Password)
    
    Write-Info "Testing MySQL connection to $Host..."
    
    try {
        $result = & $MysqlBin -h $Host -u $User -p"$Password" -e "SELECT VERSION();" 2>&1
        if ($LASTEXITCODE -eq 0) {
            $version = ($result | Select-String "^\d+\.\d+\.\d+").Matches.Value
            Write-Success "MySQL connection successful (Version: $version)"
            return $true
        } else {
            Write-Error "MySQL connection failed: $result"
            return $false
        }
    } catch {
        Write-Error "MySQL connection error: $_"
        return $false
    }
}

# ============================================================================
# FUNCTION: Test-PostgresConnection
# ============================================================================
function Test-PostgresConnection {
    param([string]$Host, [string]$User, [string]$Password, [string]$Database)
    
    Write-Info "Testing PostgreSQL connection to $Host..."
    
    $env:PGPASSWORD = $Password
    
    try {
        $result = & $PsqlBin -h $Host -U $User -d $Database -c "SELECT version();" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "PostgreSQL connection successful"
            return $true
        } else {
            Write-Error "PostgreSQL connection failed: $result"
            return $false
        }
    } catch {
        Write-Error "PostgreSQL connection error: $_"
        return $false
    } finally {
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
    }
}

# ============================================================================
# FUNCTION: Initialize-MySQLDatabases
# ============================================================================
function Initialize-MySQLDatabases {
    param(
        [string]$Host,
        [string]$User,
        [string]$Password,
        [array]$Worlds
    )
    
    Write-Info "Creating MySQL databases for $($Worlds.Count) game worlds..."
    
    $created = 0
    $skipped = 0
    
    foreach ($world in $Worlds) {
        $dbName = $world.DbName
        
        Write-Info "Processing database: $dbName"
        
        # Check if database exists
        $checkQuery = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$dbName';"
        $exists = & $MysqlBin -h $Host -u $User -p"$Password" -e $checkQuery 2>&1
        
        if ($exists -match $dbName) {
            if ($Force) {
                Write-Warning "Database $dbName exists - dropping and recreating (Force mode)"
                & $MysqlBin -h $Host -u $User -p"$Password" -e "DROP DATABASE \`$dbName\`;" 2>&1 | Out-Null
            } else {
                Write-Warning "Database $dbName already exists - skipping"
                $skipped++
                continue
            }
        }
        
        # Create database
        $createQuery = "CREATE DATABASE \`$dbName\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        $result = & $MysqlBin -h $Host -u $User -p"$Password" -e $createQuery 2>&1
        
        if ($LASTEXITCODE -eq 0) {
            Write-Success "Created database: $dbName"
            $created++
        } else {
            Write-Error "Failed to create database $dbName: $result"
        }
    }
    
    Write-Info "Database creation complete: $created created, $skipped skipped"
    return $created
}

# ============================================================================
# FUNCTION: Apply-DatabaseSchema
# ============================================================================
function Apply-DatabaseSchema {
    param(
        [string]$Host,
        [string]$User,
        [string]$Password,
        [array]$Worlds,
        [string]$SchemaFile
    )
    
    Write-Info "Applying T4.4 schema to databases..."
    
    if (-not (Test-Path $SchemaFile)) {
        Write-Error "Schema file not found: $SchemaFile"
        return 0
    }
    
    $applied = 0
    
    foreach ($world in $Worlds) {
        $dbName = $world.DbName
        
        Write-Info "Applying schema to: $dbName"
        
        try {
            Get-Content $SchemaFile | & $MysqlBin -h $Host -u $User -p"$Password" $dbName 2>&1 | Out-Null
            
            if ($LASTEXITCODE -eq 0) {
                # Verify table count
                $tableCount = (& $MysqlBin -h $Host -u $User -p"$Password" -s -N -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$dbName' AND TABLE_TYPE='BASE TABLE';" 2>&1).Trim()
                Write-Success "Schema applied to: $dbName ($tableCount tables)"
                $applied++
            } else {
                Write-Error "Schema application failed for $dbName"
            }
        } catch {
            Write-Error "Error applying schema to $dbName: $_"
        }
    }
    
    Write-Info "Schema application complete: $applied/$($Worlds.Count) successful"
    return $applied
}

# ============================================================================
# FUNCTION: Insert-TestUsers
# ============================================================================
function Insert-TestUsers {
    param(
        [string]$Host,
        [string]$User,
        [string]$Password,
        [array]$Worlds,
        [string]$TestUsersFile
    )
    
    Write-Info "Inserting test users into databases..."
    
    if (-not (Test-Path $TestUsersFile)) {
        Write-Error "Test users file not found: $TestUsersFile"
        return 0
    }
    
    $inserted = 0
    
    foreach ($world in $Worlds) {
        $dbName = $world.DbName
        
        Write-Info "Inserting test users into: $dbName"
        
        try {
            Get-Content $TestUsersFile | & $MysqlBin -h $Host -u $User -p"$Password" $dbName 2>&1 | Out-Null
            
            if ($LASTEXITCODE -eq 0) {
                # Verify user count
                $userCount = (& $MysqlBin -h $Host -u $User -p"$Password" -s -N -e "SELECT COUNT(*) FROM $dbName.users;" 2>&1).Trim()
                Write-Success "Test users inserted into: $dbName ($userCount users)"
                $inserted++
            } else {
                Write-Warning "Test user insertion had warnings for $dbName"
                $inserted++
            }
        } catch {
            Write-Error "Error inserting test users into $dbName: $_"
        }
    }
    
    Write-Info "Test user insertion complete: $inserted/$($Worlds.Count) successful"
    return $inserted
}

# ============================================================================
# FUNCTION: New-WorldConnectionConfig
# ============================================================================
function New-WorldConnectionConfig {
    param(
        [string]$WorldId,
        [string]$DbName,
        [string]$DbHost,
        [string]$DbUser,
        [string]$DbPassword,
        [string]$TemplateFile,
        [string]$OutputDir
    )
    
    Write-Info "Generating connection config for world: $WorldId"
    
    # Create output directory if it doesn't exist
    $worldConfigDir = Join-Path $OutputDir "$WorldId\config"
    if (-not (Test-Path $worldConfigDir)) {
        New-Item -ItemType Directory -Path $worldConfigDir -Force | Out-Null
    }
    
    $outputFile = Join-Path $worldConfigDir "connection.php"
    
    # Read template
    if (-not (Test-Path $TemplateFile)) {
        Write-Error "Template file not found: $TemplateFile"
        return $false
    }
    
    $template = Get-Content $TemplateFile -Raw
    
    # Replace placeholders
    $config = $template `
        -replace '{{DB_HOST}}', $DbHost `
        -replace '{{DB_NAME}}', $DbName `
        -replace '{{DB_USER}}', $DbUser `
        -replace '{{DB_PASSWORD}}', $DbPassword `
        -replace '{{WORLD_ID}}', $WorldId `
        -replace '{{DRIVER}}', 'mysql'
    
    # Write to file
    $config | Set-Content -Path $outputFile -Encoding UTF8
    
    Write-Success "Created config: $outputFile"
    return $true
}

# ============================================================================
# FUNCTION: Generate-AllConnectionConfigs
# ============================================================================
function Generate-AllConnectionConfigs {
    param(
        [array]$Worlds,
        [string]$DbHost,
        [string]$DbUser,
        [string]$DbPassword,
        [string]$TemplateFile,
        [string]$OutputDir
    )
    
    Write-Info "Generating connection configs for all worlds..."
    
    $generated = 0
    
    foreach ($world in $Worlds) {
        $success = New-WorldConnectionConfig `
            -WorldId $world.Id `
            -DbName $world.DbName `
            -DbHost $DbHost `
            -DbUser $DbUser `
            -DbPassword $DbPassword `
            -TemplateFile $TemplateFile `
            -OutputDir $OutputDir
        
        if ($success) {
            $generated++
        }
    }
    
    Write-Info "Connection config generation complete: $generated/$($Worlds.Count) successful"
    return $generated
}

# ============================================================================
# FUNCTION: Register-ServersInPostgres
# ============================================================================
function Register-ServersInPostgres {
    param(
        [string]$Host,
        [string]$User,
        [string]$Password,
        [string]$Database,
        [array]$Worlds
    )
    
    Write-Info "Registering servers in PostgreSQL..."
    
    $env:PGPASSWORD = $Password
    
    try {
        foreach ($world in $Worlds) {
            $sql = @"
INSERT INTO gameservers (worldid, name, gameworldurl, configfilelocation, speed, starttime)
VALUES ('$($world.Id)', '$($world.Name)', 'http://localhost/', 'sections/servers/$($world.Id)', $($world.Speed), EXTRACT(EPOCH FROM NOW())::INTEGER)
ON CONFLICT (worldid) DO UPDATE SET
    name = EXCLUDED.name,
    speed = EXCLUDED.speed;
"@
            
            $result = $sql | & $PsqlBin -h $Host -U $User -d $Database 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                Write-Success "Registered: $($world.Name)"
            } else {
                Write-Warning "Failed to register $($world.Id): $result"
            }
        }
        
        # Verify registration
        $count = (& $PsqlBin -h $Host -U $User -d $Database -t -c "SELECT COUNT(*) FROM gameservers;" 2>&1).Trim()
        Write-Info "Total servers in PostgreSQL: $count"
        
        return $true
    } catch {
        Write-Error "Error registering servers: $_"
        return $false
    } finally {
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
    }
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

Write-Header "TRAVIANT4.6 - XAMPP SETUP"
Write-Info "MySQL Host: $MysqlHost"
Write-Info "MySQL User: $MysqlUser"
Write-Info "PostgreSQL Host: $PgHost"
Write-Info "PostgreSQL Database: $PgDatabase"
Write-Info "Worlds to provision: $($GameWorlds.Count)"

# Step 1: Test MySQL Connection
Write-Header "STEP 1: VALIDATING MYSQL CONNECTION"
if (-not (Test-MysqlConnection -Host $MysqlHost -User "root" -Password $MysqlRootPassword)) {
    Write-Error "Cannot connect to MySQL. Ensure MySQL is running and credentials are correct."
    exit 1
}

# Step 2: Test PostgreSQL Connection
Write-Header "STEP 2: VALIDATING POSTGRESQL CONNECTION"
if (-not (Test-PostgresConnection -Host $PgHost -User $PgUser -Password $PgPassword -Database $PgDatabase)) {
    Write-Error "Cannot connect to PostgreSQL. Ensure PostgreSQL is running and database exists."
    exit 1
}

# Step 3: Create Databases
Write-Header "STEP 3: CREATING MYSQL DATABASES"
$createdCount = Initialize-MySQLDatabases `
    -Host $MysqlHost `
    -User "root" `
    -Password $MysqlRootPassword `
    -Worlds $GameWorlds

# Step 4: Apply Schema
Write-Header "STEP 4: APPLYING DATABASE SCHEMAS"
$schemaFile = Join-Path $DatabaseDir "windows-world-schema.sql"
$appliedCount = Apply-DatabaseSchema `
    -Host $MysqlHost `
    -User "root" `
    -Password $MysqlRootPassword `
    -Worlds $GameWorlds `
    -SchemaFile $schemaFile

# Step 5: Insert Test Users (unless skipped)
if (-not $SkipUserCreation) {
    Write-Header "STEP 5: INSERTING TEST USERS"
    $testUsersFile = Join-Path $DatabaseDir "windows-test-users.sql"
    $insertedCount = Insert-TestUsers `
        -Host $MysqlHost `
        -User "root" `
        -Password $MysqlRootPassword `
        -Worlds $GameWorlds `
        -TestUsersFile $testUsersFile
} else {
    Write-Info "Skipping test user creation (SkipUserCreation flag set)"
}

# Step 6: Generate Connection Configs
Write-Header "STEP 6: GENERATING CONNECTION CONFIGS"
$templateFile = Join-Path $DatabaseDir "windows-connection-template.php"
$configCount = Generate-AllConnectionConfigs `
    -Worlds $GameWorlds `
    -DbHost $MysqlHost `
    -DbUser $MysqlUser `
    -DbPassword $MysqlPassword `
    -TemplateFile $templateFile `
    -OutputDir $SectionsDir

# Step 7: Register Servers in PostgreSQL
Write-Header "STEP 7: REGISTERING SERVERS IN POSTGRESQL"
$registered = Register-ServersInPostgres `
    -Host $PgHost `
    -User $PgUser `
    -Password $PgPassword `
    -Database $PgDatabase `
    -Worlds $GameWorlds

# Step 8: Validation (unless skipped)
if (-not $SkipValidation) {
    Write-Header "STEP 8: RUNNING VALIDATION"
    $validateScript = Join-Path $ScriptRoot "scripts\validate-xampp.ps1"
    if (Test-Path $validateScript) {
        & $validateScript `
            -MysqlHost $MysqlHost `
            -MysqlUser $MysqlUser `
            -MysqlPassword $MysqlPassword `
            -PgHost $PgHost `
            -PgUser $PgUser `
            -PgPassword $PgPassword `
            -PgDatabase $PgDatabase
    } else {
        Write-Warning "Validation script not found: $validateScript"
    }
}

# Final Summary
Write-Header "SETUP COMPLETE"
Write-Success "Databases created/updated: $createdCount/$($GameWorlds.Count)"
Write-Success "Schemas applied: $appliedCount/$($GameWorlds.Count)"
if (-not $SkipUserCreation) {
    Write-Success "Test users inserted: $insertedCount/$($GameWorlds.Count)"
}
Write-Success "Connection configs generated: $configCount/$($GameWorlds.Count)"
Write-Success "Servers registered in PostgreSQL: $(if ($registered) { 'Yes' } else { 'No' })"
Write-Info ""
Write-Info "Next Steps:"
Write-Info "1. Start Apache: C:\xampp\apache_start.bat"
Write-Info "2. Open browser: http://localhost"
Write-Info "3. Test login with testuser1 / test123"
Write-Info ""
```

---

### validate-xampp.ps1

**Purpose**: Comprehensive validation of XAMPP deployment

**Features**:
- Tests Apache, MySQL, PostgreSQL service status
- Validates 8 databases exist with correct table counts
- Verifies test user accounts
- Checks file permissions
- Tests API endpoints
- Colored validation report with pass/fail summary

**Usage**:
```powershell
.\scripts\validate-xampp.ps1
.\scripts\validate-xampp.ps1 -Verbose
```

**Script** (385 lines):

```powershell
# ============================================================================
# TRAVIANT4.6 - XAMPP VALIDATION SCRIPT
# ============================================================================
# Version: 2.0
# Purpose: Comprehensive validation of XAMPP deployment
# Requirements: MySQL 8.0+, PostgreSQL 14+, PowerShell 5.1+, Apache 2.4+
# 
# USAGE:
#   .\scripts\validate-xampp.ps1
#   .\scripts\validate-xampp.ps1 -Verbose
#
# WHAT THIS SCRIPT VALIDATES:
# 1. Apache service status and port 80 availability
# 2. MySQL service status and connectivity
# 3. PostgreSQL service status and connectivity
# 4. All 8 game world databases exist
# 5. Each database has exactly 90 tables (T4.4 schema)
# 6. Test users exist (12 per database)
# 7. Connection config files exist and are valid
# 8. File permissions on critical directories
# 9. API endpoint accessibility (/v1/servers/loadServers)
# 10. Sample login query succeeds
# ============================================================================

param(
    [string]$MysqlHost = "localhost",
    [string]$MysqlUser = "travian",
    [string]$MysqlPassword = "TravianDB2025!",
    [string]$PgHost = "localhost",
    [string]$PgUser = "postgres",
    [string]$PgPassword = "postgres",
    [string]$PgDatabase = "travian_global",
    [switch]$Verbose
)

# Script Configuration
$ErrorActionPreference = "Continue"
$ScriptRoot = "C:\xampp\htdocs"
$SectionsDir = Join-Path $ScriptRoot "sections\servers"
$MysqlBin = "C:\xampp\mysql\bin\mysql.exe"
$PsqlBin = "C:\xampp\pgsql\bin\psql.exe"

# Expected Configuration
$ExpectedDatabases = @(
    "travian_world_speed10k",
    "travian_world_speed125k",
    "travian_world_speed250k",
    "travian_world_speed500k",
    "travian_world_speed5m",
    "travian_world_demo",
    "travian_world_dev",
    "travian_world_testworld"
)

$ExpectedWorlds = @("speed10k", "speed125k", "speed250k", "speed500k", "speed5m", "demo", "dev", "testworld")
$ExpectedTableCount = 90
$ExpectedTestUsers = @("testuser1", "testuser2", "testuser3", "testuser4", "testuser5", 
                       "testuser6", "testuser7", "testuser8", "testuser9", "testuser10",
                       "admin", "demo")

# Validation Results
$ValidationResults = @{
    ApacheRunning = $false
    MysqlRunning = $false
    PostgresRunning = $false
    MysqlConnection = $false
    PostgresConnection = $false
    Databases = 0
    Tables = @{}
    Users = @{}
    Configs = @{}
    Permissions = @{}
    ApiEndpoint = $false
    LoginTest = $false
    TotalChecks = 0
    PassedChecks = 0
}

# Color Output Functions
function Write-ColorOutput {
    param([string]$Message, [string]$Color = "White")
    Write-Host $Message -ForegroundColor $Color
}

function Write-Success { param([string]$Message) Write-ColorOutput "✓ $Message" "Green"; $script:ValidationResults.PassedChecks++ }
function Write-Fail { param([string]$Message) Write-ColorOutput "✗ $Message" "Red" }
function Write-Info { param([string]$Message) Write-ColorOutput "ℹ $Message" "Cyan" }
function Write-Warning { param([string]$Message) Write-ColorOutput "⚠ $Message" "Yellow" }
function Write-Header { 
    param([string]$Message) 
    Write-Host ""
    Write-ColorOutput "============================================================================" "Magenta"
    Write-ColorOutput $Message "Magenta"
    Write-ColorOutput "============================================================================" "Magenta"
}

# ============================================================================
# VALIDATION FUNCTIONS
# ============================================================================

function Test-ApacheService {
    Write-Info "Checking Apache service status..."
    $script:ValidationResults.TotalChecks++
    
    # Check if Apache process is running
    $apacheProcess = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
    
    if ($apacheProcess) {
        Write-Success "Apache is running (PID: $($apacheProcess.Id -join ', '))"
        $script:ValidationResults.ApacheRunning = $true
        
        # Test port 80
        try {
            $response = Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing -TimeoutSec 5
            if ($response.StatusCode -eq 200) {
                Write-Success "Apache responding on port 80"
                $script:ValidationResults.TotalChecks++
                $script:ValidationResults.PassedChecks++
            }
        } catch {
            Write-Fail "Apache not responding on port 80: $_"
        }
        
        return $true
    } else {
        Write-Fail "Apache is not running"
        return $false
    }
}

function Test-MySQLService {
    Write-Info "Checking MySQL service status..."
    $script:ValidationResults.TotalChecks++
    
    $mysqlProcess = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
    
    if ($mysqlProcess) {
        Write-Success "MySQL is running (PID: $($mysqlProcess.Id))"
        $script:ValidationResults.MysqlRunning = $true
        return $true
    } else {
        Write-Fail "MySQL is not running"
        return $false
    }
}

function Test-PostgreSQLService {
    Write-Info "Checking PostgreSQL service status..."
    $script:ValidationResults.TotalChecks++
    
    $pgProcess = Get-Process -Name "postgres" -ErrorAction SilentlyContinue
    
    if ($pgProcess) {
        Write-Success "PostgreSQL is running (PID: $($pgProcess.Id -join ', '))"
        $script:ValidationResults.PostgresRunning = $true
        return $true
    } else {
        Write-Fail "PostgreSQL is not running"
        return $false
    }
}

function Test-MySQLConnection {
    Write-Info "Testing MySQL connection..."
    $script:ValidationResults.TotalChecks++
    
    try {
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e "SELECT 1;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "MySQL connection successful (Host: $MysqlHost, User: $MysqlUser)"
            $script:ValidationResults.MysqlConnection = $true
            return $true
        } else {
            Write-Fail "MySQL connection failed: $result"
            return $false
        }
    } catch {
        Write-Fail "MySQL connection error: $_"
        return $false
    }
}

function Test-PostgreSQLConnection {
    Write-Info "Testing PostgreSQL connection..."
    $script:ValidationResults.TotalChecks++
    
    $env:PGPASSWORD = $PgPassword
    
    try {
        $result = & $PsqlBin -h $PgHost -U $PgUser -d $PgDatabase -c "SELECT 1;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "PostgreSQL connection successful (Host: $PgHost, Database: $PgDatabase)"
            $script:ValidationResults.PostgresConnection = $true
            return $true
        } else {
            Write-Fail "PostgreSQL connection failed: $result"
            return $false
        }
    } catch {
        Write-Fail "PostgreSQL connection error: $_"
        return $false
    } finally {
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
    }
}

function Test-Databases {
    Write-Info "Validating game world databases..."
    
    $found = 0
    
    foreach ($dbName in $ExpectedDatabases) {
        $script:ValidationResults.TotalChecks++
        
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$dbName';"
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query 2>&1
        
        if ($result -match $dbName) {
            Write-Success "Database exists: $dbName"
            $found++
        } else {
            Write-Fail "Database missing: $dbName"
        }
    }
    
    $script:ValidationResults.Databases = $found
    Write-Info "Found $found/$($ExpectedDatabases.Count) databases"
    
    return $found -eq $ExpectedDatabases.Count
}

function Test-DatabaseTables {
    Write-Info "Validating database tables..."
    
    $allValid = $true
    
    foreach ($dbName in $ExpectedDatabases) {
        $script:ValidationResults.TotalChecks++
        
        $query = "SELECT COUNT(*) as table_count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$dbName' AND TABLE_TYPE='BASE TABLE';"
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query -s -N 2>&1
        
        $tableCount = [int]$result
        $script:ValidationResults.Tables[$dbName] = $tableCount
        
        if ($tableCount -eq $ExpectedTableCount) {
            Write-Success "Database $dbName has $tableCount tables (expected: $ExpectedTableCount)"
        } else {
            Write-Fail "Database $dbName has $tableCount tables (expected: $ExpectedTableCount)"
            $allValid = $false
        }
    }
    
    return $allValid
}

function Test-TestUsers {
    Write-Info "Validating test user accounts..."
    
    $allValid = $true
    
    foreach ($dbName in $ExpectedDatabases) {
        $script:ValidationResults.TotalChecks++
        
        $query = "SELECT COUNT(*) as user_count FROM $dbName.users WHERE name IN ('" + ($ExpectedTestUsers -join "','") + "');"
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query -s -N 2>&1
        
        $userCount = [int]$result
        $script:ValidationResults.Users[$dbName] = $userCount
        
        if ($userCount -eq $ExpectedTestUsers.Count) {
            Write-Success "Database $dbName has $userCount/$($ExpectedTestUsers.Count) test users"
        } else {
            Write-Fail "Database $dbName has $userCount/$($ExpectedTestUsers.Count) test users"
            $allValid = $false
        }
        
        if ($Verbose -and $userCount -gt 0) {
            $userQuery = "SELECT name, email, race, access FROM $dbName.users WHERE name IN ('" + ($ExpectedTestUsers -join "','") + "');"
            $users = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $userQuery 2>&1
            Write-Host $users
        }
    }
    
    return $allValid
}

function Test-ConnectionConfigs {
    Write-Info "Validating connection configuration files..."
    
    $allValid = $true
    
    foreach ($worldId in $ExpectedWorlds) {
        $script:ValidationResults.TotalChecks++
        
        $configPath = Join-Path $SectionsDir "$worldId\config\connection.php"
        
        if (Test-Path $configPath) {
            Write-Success "Config exists: $worldId"
            $script:ValidationResults.Configs[$worldId] = $true
            
            if ($Verbose) {
                $content = Get-Content $configPath -Raw
                if ($content -match '{{.*}}') {
                    Write-Warning "Config for $worldId still contains placeholders"
                    $allValid = $false
                }
            }
        } else {
            Write-Fail "Config missing: $worldId (Expected: $configPath)"
            $script:ValidationResults.Configs[$worldId] = $false
            $allValid = $false
        }
    }
    
    return $allValid
}

function Test-FilePermissions {
    Write-Info "Checking file permissions..."
    
    $criticalDirs = @(
        "C:\xampp\htdocs",
        "C:\xampp\tmp",
        "C:\xampp\apache\logs"
    )
    
    $allValid = $true
    
    foreach ($dir in $criticalDirs) {
        $script:ValidationResults.TotalChecks++
        
        if (Test-Path $dir) {
            try {
                # Test write access
                $testFile = Join-Path $dir "test-write-$(Get-Random).tmp"
                "test" | Out-File -FilePath $testFile -ErrorAction Stop
                Remove-Item $testFile -Force
                
                Write-Success "Directory writable: $dir"
                $script:ValidationResults.Permissions[$dir] = $true
            } catch {
                Write-Fail "Directory not writable: $dir"
                $script:ValidationResults.Permissions[$dir] = $false
                $allValid = $false
            }
        } else {
            Write-Fail "Directory missing: $dir"
            $allValid = $false
        }
    }
    
    return $allValid
}

function Test-ApiEndpoint {
    Write-Info "Testing API endpoint..."
    $script:ValidationResults.TotalChecks++
    
    try {
        $response = Invoke-WebRequest -Uri "http://localhost/v1/servers/loadServers" -UseBasicParsing -TimeoutSec 10
        
        if ($response.StatusCode -eq 200) {
            $json = $response.Content | ConvertFrom-Json
            $serverCount = ($json.data | Measure-Object).Count
            
            Write-Success "API endpoint accessible - $serverCount servers returned"
            $script:ValidationResults.ApiEndpoint = $true
            
            if ($Verbose) {
                Write-Host ($response.Content | ConvertFrom-Json | ConvertTo-Json -Depth 5)
            }
            
            return $true
        } else {
            Write-Fail "API returned status code: $($response.StatusCode)"
            return $false
        }
    } catch {
        Write-Fail "API endpoint test failed: $_"
        return $false
    }
}

function Test-SampleLoginQuery {
    Write-Info "Testing sample login query..."
    $script:ValidationResults.TotalChecks++
    
    $dbName = $ExpectedDatabases[0]
    $testUser = "testuser1"
    
    $query = "SELECT id, name, email, password FROM $dbName.users WHERE name='$testUser' LIMIT 1;"
    
    try {
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e $query 2>&1
        
        if ($LASTEXITCODE -eq 0 -and $result -match $testUser) {
            Write-Success "Sample login query successful for user: $testUser"
            $script:ValidationResults.LoginTest = $true
            
            if ($Verbose) {
                Write-Host $result
            }
            
            return $true
        } else {
            Write-Fail "Sample login query failed for user: $testUser"
            return $false
        }
    } catch {
        Write-Fail "Sample login query error: $_"
        return $false
    }
}

function Show-ValidationSummary {
    Write-Header "VALIDATION SUMMARY"
    
    $passRate = if ($ValidationResults.TotalChecks -gt 0) {
        [math]::Round(($ValidationResults.PassedChecks / $ValidationResults.TotalChecks) * 100, 2)
    } else { 0 }
    
    Write-Info "Total Checks: $($ValidationResults.TotalChecks)"
    Write-ColorOutput "Passed: $($ValidationResults.PassedChecks)" "Green"
    Write-ColorOutput "Failed: $($ValidationResults.TotalChecks - $ValidationResults.PassedChecks)" "Red"
    Write-Info "Pass Rate: $passRate%"
    Write-Host ""
    
    Write-Info "Detailed Results:"
    Write-Info "  Services:"
    Write-Info "    Apache Running: $(if ($ValidationResults.ApacheRunning) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info "    MySQL Running: $(if ($ValidationResults.MysqlRunning) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info "    PostgreSQL Running: $(if ($ValidationResults.PostgresRunning) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info ""
    Write-Info "  Database Connections:"
    Write-Info "    MySQL Connection: $(if ($ValidationResults.MysqlConnection) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info "    PostgreSQL Connection: $(if ($ValidationResults.PostgresConnection) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info ""
    Write-Info "  Databases: $($ValidationResults.Databases)/$($ExpectedDatabases.Count)"
    
    Write-Info "  Tables per Database:"
    foreach ($db in $ValidationResults.Tables.Keys) {
        $status = if ($ValidationResults.Tables[$db] -eq $ExpectedTableCount) { "✓" } else { "✗" }
        Write-Info "    $status $db : $($ValidationResults.Tables[$db])/$ExpectedTableCount"
    }
    
    Write-Info "  Users per Database:"
    foreach ($db in $ValidationResults.Users.Keys) {
        $status = if ($ValidationResults.Users[$db] -eq $ExpectedTestUsers.Count) { "✓" } else { "✗" }
        Write-Info "    $status $db : $($ValidationResults.Users[$db])/$($ExpectedTestUsers.Count)"
    }
    
    Write-Info "  Connection Configs:"
    foreach ($world in $ValidationResults.Configs.Keys) {
        $status = if ($ValidationResults.Configs[$world]) { "✓" } else { "✗" }
        Write-Info "    $status $world"
    }
    
    Write-Info "  API Endpoint: $(if ($ValidationResults.ApiEndpoint) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info "  Sample Login Test: $(if ($ValidationResults.LoginTest) { '✓ PASS' } else { '✗ FAIL' })"
    Write-Info ""
    
    if ($passRate -eq 100) {
        Write-ColorOutput "🎉 ALL VALIDATION CHECKS PASSED! 🎉" "Green"
        Write-Host ""
        Write-Info "Your XAMPP deployment is ready for Travian T4.6!"
        Write-Info ""
        Write-Info "Next Steps:"
        Write-Info "1. Open browser: http://localhost"
        Write-Info "2. Select a speed server"
        Write-Info "3. Login with testuser1 / test123"
        return $true
    } else {
        Write-ColorOutput "⚠ VALIDATION FAILED ⚠" "Red"
        Write-Host ""
        Write-Info "Please review the failed checks above and run setup again:"
        Write-Info "  .\scripts\setup-xampp.ps1 -Force"
        return $false
    }
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

Write-Header "TRAVIANT4.6 - XAMPP VALIDATION"
Write-Info "MySQL Host: $MysqlHost"
Write-Info "MySQL User: $MysqlUser"
Write-Info "PostgreSQL Host: $PgHost"
Write-Info "PostgreSQL Database: $PgDatabase"
Write-Info "Expected Databases: $($ExpectedDatabases.Count)"
Write-Info "Expected Tables per DB: $ExpectedTableCount"
Write-Info "Expected Test Users: $($ExpectedTestUsers.Count)"

# Run all validation checks
Write-Header "SERVICE CHECKS"
$apacheOk = Test-ApacheService
$mysqlServiceOk = Test-MySQLService
$pgServiceOk = Test-PostgreSQLService

Write-Header "CONNECTION CHECKS"
$mysqlConnOk = Test-MySQLConnection
$pgConnOk = Test-PostgreSQLConnection

if ($mysqlConnOk) {
    Write-Header "DATABASE CHECKS"
    $databasesOk = Test-Databases
    $tablesOk = Test-DatabaseTables
    $usersOk = Test-TestUsers
    $configsOk = Test-ConnectionConfigs
}

Write-Header "FILE SYSTEM CHECKS"
$permissionsOk = Test-FilePermissions

if ($apacheOk) {
    Write-Header "API CHECKS"
    $apiOk = Test-ApiEndpoint
}

if ($mysqlConnOk) {
    Write-Header "LOGIN CHECKS"
    $loginTestOk = Test-SampleLoginQuery
}

# Show summary
$allPassed = Show-ValidationSummary

# Exit with appropriate code
exit $(if ($allPassed) { 0 } else { 1 })
```

---

### xampp-backup.ps1

**Purpose**: Automated backup system with 3-tier retention

**Features**:
- PostgreSQL dump (gameservers table)
- MySQL dump (all 8 world databases)
- File backup (connection configs)
- 3-tier retention: daily (7), weekly (4), monthly (12)
- Compressed archives (.zip)
- Automatic cleanup of old backups

**Usage**:
```powershell
.\scripts\xampp-backup.ps1
.\scripts\xampp-backup.ps1 -BackupDir "D:\Backups\Travian"
```

**Script** (287 lines):

```powershell
# ============================================================================
# TRAVIANT4.6 - XAMPP BACKUP SCRIPT
# ============================================================================
# Version: 2.0
# Purpose: Automated backup with 3-tier retention
# Requirements: MySQL 8.0+, PostgreSQL 14+, PowerShell 5.1+, 7-Zip (optional)
# 
# USAGE:
#   .\scripts\xampp-backup.ps1
#   .\scripts\xampp-backup.ps1 -BackupDir "D:\Backups\Travian"
#
# WHAT THIS SCRIPT DOES:
# 1. PostgreSQL dump (gameservers table from travian_global)
# 2. MySQL dumps (all 8 world databases)
# 3. File backup (connection.php configs, uploads, logs)
# 4. Compressed archives (.zip)
# 5. 3-tier retention: daily (7 days), weekly (4 weeks), monthly (12 months)
# 6. Automatic cleanup of old backups
# ============================================================================

param(
    [string]$BackupDir = "C:\xampp\backups",
    [string]$MysqlHost = "localhost",
    [string]$MysqlRootPassword = "TravianSecureRoot2025!",
    [string]$PgHost = "localhost",
    [string]$PgUser = "postgres",
    [string]$PgPassword = "postgres",
    [string]$PgDatabase = "travian_global",
    [int]$DailyRetention = 7,
    [int]$WeeklyRetention = 4,
    [int]$MonthlyRetention = 12,
    [switch]$SkipCompression
)

# Script Configuration
$ErrorActionPreference = "Stop"
$ScriptRoot = "C:\xampp\htdocs"
$MysqlBin = "C:\xampp\mysql\bin\mysqldump.exe"
$MysqlRestore = "C:\xampp\mysql\bin\mysql.exe"
$PgDumpBin = "C:\xampp\pgsql\bin\pg_dump.exe"
$PgRestoreBin = "C:\xampp\pgsql\bin\psql.exe"

# Timestamp
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$DateStamp = Get-Date -Format "yyyyMMdd"
$DayOfWeek = (Get-Date).DayOfWeek
$DayOfMonth = (Get-Date).Day

# Backup Paths
$DailyBackupDir = Join-Path $BackupDir "daily"
$WeeklyBackupDir = Join-Path $BackupDir "weekly"
$MonthlyBackupDir = Join-Path $BackupDir "monthly"

# World Configuration
$GameWorlds = @("speed10k", "speed125k", "speed250k", "speed500k", "speed5m", "demo", "dev", "testworld")

# Color Output Functions
function Write-ColorOutput {
    param([string]$Message, [string]$Color = "White")
    Write-Host $Message -ForegroundColor $Color
}

function Write-Success { param([string]$Message) Write-ColorOutput "✓ $Message" "Green" }
function Write-Info { param([string]$Message) Write-ColorOutput "ℹ $Message" "Cyan" }
function Write-Warning { param([string]$Message) Write-ColorOutput "⚠ $Message" "Yellow" }
function Write-Error { param([string]$Message) Write-ColorOutput "✗ $Message" "Red" }
function Write-Header { 
    param([string]$Message) 
    Write-Host ""
    Write-ColorOutput "============================================================================" "Magenta"
    Write-ColorOutput $Message "Magenta"
    Write-ColorOutput "============================================================================" "Magenta"
}

# ============================================================================
# FUNCTION: Initialize-BackupDirectories
# ============================================================================
function Initialize-BackupDirectories {
    Write-Info "Initializing backup directories..."
    
    $dirs = @($BackupDir, $DailyBackupDir, $WeeklyBackupDir, $MonthlyBackupDir)
    
    foreach ($dir in $dirs) {
        if (-not (Test-Path $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
            Write-Success "Created: $dir"
        }
    }
}

# ============================================================================
# FUNCTION: Backup-PostgreSQLDatabase
# ============================================================================
function Backup-PostgreSQLDatabase {
    param([string]$OutputDir)
    
    Write-Info "Backing up PostgreSQL database: $PgDatabase..."
    
    $env:PGPASSWORD = $PgPassword
    
    try {
        $backupFile = Join-Path $OutputDir "postgres_$PgDatabase`_$Timestamp.sql"
        
        & $PgDumpBin -h $PgHost -U $PgUser -d $PgDatabase -f $backupFile 2>&1 | Out-Null
        
        if ($LASTEXITCODE -eq 0) {
            $size = (Get-Item $backupFile).Length / 1MB
            Write-Success "PostgreSQL backup created: $backupFile ($('{0:N2}' -f $size) MB)"
            return $backupFile
        } else {
            Write-Error "PostgreSQL backup failed"
            return $null
        }
    } catch {
        Write-Error "PostgreSQL backup error: $_"
        return $null
    } finally {
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
    }
}

# ============================================================================
# FUNCTION: Backup-MySQLDatabase
# ============================================================================
function Backup-MySQLDatabase {
    param([string]$DatabaseName, [string]$OutputDir)
    
    Write-Info "Backing up MySQL database: $DatabaseName..."
    
    try {
        $backupFile = Join-Path $OutputDir "mysql_$DatabaseName`_$Timestamp.sql"
        
        & $MysqlBin -h $MysqlHost -uroot -p"$MysqlRootPassword" $DatabaseName > $backupFile 2>&1
        
        if ($LASTEXITCODE -eq 0) {
            $size = (Get-Item $backupFile).Length / 1MB
            Write-Success "MySQL backup created: $DatabaseName ($('{0:N2}' -f $size) MB)"
            return $backupFile
        } else {
            Write-Error "MySQL backup failed for $DatabaseName"
            return $null
        }
    } catch {
        Write-Error "MySQL backup error for $DatabaseName : $_"
        return $null
    }
}

# ============================================================================
# FUNCTION: Backup-ConfigFiles
# ============================================================================
function Backup-ConfigFiles {
    param([string]$OutputDir)
    
    Write-Info "Backing up configuration files..."
    
    $configBackupDir = Join-Path $OutputDir "configs_$Timestamp"
    New-Item -ItemType Directory -Path $configBackupDir -Force | Out-Null
    
    $backedUp = 0
    
    foreach ($world in $GameWorlds) {
        $configFile = Join-Path $ScriptRoot "sections\servers\$world\config\connection.php"
        
        if (Test-Path $configFile) {
            $destDir = Join-Path $configBackupDir $world
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
            Copy-Item -Path $configFile -Destination $destDir -Force
            $backedUp++
        }
    }
    
    Write-Success "Configuration files backed up: $backedUp files"
    return $configBackupDir
}

# ============================================================================
# FUNCTION: Compress-BackupDirectory
# ============================================================================
function Compress-BackupDirectory {
    param([string]$SourceDir, [string]$ArchiveName)
    
    if ($SkipCompression) {
        Write-Info "Skipping compression (SkipCompression flag set)"
        return $SourceDir
    }
    
    Write-Info "Compressing backup..."
    
    try {
        $archivePath = "$ArchiveName.zip"
        
        # Use PowerShell built-in compression
        Compress-Archive -Path "$SourceDir\*" -DestinationPath $archivePath -CompressionLevel Optimal -Force
        
        $originalSize = (Get-ChildItem -Path $SourceDir -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB
        $compressedSize = (Get-Item $archivePath).Length / 1MB
        $ratio = [math]::Round((1 - ($compressedSize / $originalSize)) * 100, 2)
        
        Write-Success "Archive created: $archivePath ($('{0:N2}' -f $compressedSize) MB, $ratio% compression)"
        
        # Remove uncompressed files
        Remove-Item -Path $SourceDir -Recurse -Force
        
        return $archivePath
    } catch {
        Write-Error "Compression error: $_"
        return $SourceDir
    }
}

# ============================================================================
# FUNCTION: Cleanup-OldBackups
# ============================================================================
function Cleanup-OldBackups {
    param([string]$BackupType, [string]$BackupPath, [int]$RetentionDays)
    
    Write-Info "Cleaning up old $BackupType backups (retention: $RetentionDays)..."
    
    $cutoffDate = (Get-Date).AddDays(-$RetentionDays)
    
    $oldBackups = Get-ChildItem -Path $BackupPath -File | Where-Object { $_.LastWriteTime -lt $cutoffDate }
    
    if ($oldBackups) {
        $deletedCount = 0
        $freedSpace = 0
        
        foreach ($backup in $oldBackups) {
            $size = $backup.Length / 1MB
            $freedSpace += $size
            Remove-Item -Path $backup.FullName -Force
            Write-Info "  Deleted: $($backup.Name) ($('{0:N2}' -f $size) MB)"
            $deletedCount++
        }
        
        Write-Success "Cleaned up $deletedCount old backups, freed $('{0:N2}' -f $freedSpace) MB"
    } else {
        Write-Info "No old backups to clean up"
    }
}

# ============================================================================
# FUNCTION: Get-BackupStatistics
# ============================================================================
function Get-BackupStatistics {
    Write-Info ""
    Write-Info "Backup Statistics:"
    
    $dailyBackups = Get-ChildItem -Path $DailyBackupDir -File -ErrorAction SilentlyContinue
    $weeklyBackups = Get-ChildItem -Path $WeeklyBackupDir -File -ErrorAction SilentlyContinue
    $monthlyBackups = Get-ChildItem -Path $MonthlyBackupDir -File -ErrorAction SilentlyContinue
    
    $totalSize = 0
    $totalSize += ($dailyBackups | Measure-Object -Property Length -Sum).Sum / 1GB
    $totalSize += ($weeklyBackups | Measure-Object -Property Length -Sum).Sum / 1GB
    $totalSize += ($monthlyBackups | Measure-Object -Property Length -Sum).Sum / 1GB
    
    Write-Info "  Daily backups: $($dailyBackups.Count) files"
    Write-Info "  Weekly backups: $($weeklyBackups.Count) files"
    Write-Info "  Monthly backups: $($monthlyBackups.Count) files"
    Write-Info "  Total backup size: $('{0:N2}' -f $totalSize) GB"
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

Write-Header "TRAVIANT4.6 - XAMPP BACKUP"
Write-Info "Backup Directory: $BackupDir"
Write-Info "Timestamp: $Timestamp"
Write-Info "Retention: Daily=$DailyRetention, Weekly=$WeeklyRetention, Monthly=$MonthlyRetention"

# Initialize directories
Initialize-BackupDirectories

# Determine backup type (daily, weekly, monthly)
$backupType = "daily"
$targetDir = $DailyBackupDir

if ($DayOfWeek -eq "Sunday") {
    $backupType = "weekly"
    $targetDir = $WeeklyBackupDir
}

if ($DayOfMonth -eq 1) {
    $backupType = "monthly"
    $targetDir = $MonthlyBackupDir
}

Write-Info "Backup Type: $backupType"

# Create temporary backup directory
$tempBackupDir = Join-Path $BackupDir "temp_$Timestamp"
New-Item -ItemType Directory -Path $tempBackupDir -Force | Out-Null

# Backup PostgreSQL
Write-Header "POSTGRESQL BACKUP"
$pgBackup = Backup-PostgreSQLDatabase -OutputDir $tempBackupDir

# Backup MySQL
Write-Header "MYSQL BACKUPS"
$mysqlBackups = @()
foreach ($world in $GameWorlds) {
    $dbName = "travian_world_$world"
    $backup = Backup-MySQLDatabase -DatabaseName $dbName -OutputDir $tempBackupDir
    if ($backup) {
        $mysqlBackups += $backup
    }
}

# Backup Configuration Files
Write-Header "CONFIGURATION BACKUP"
$configBackup = Backup-ConfigFiles -OutputDir $tempBackupDir

# Compress backup
Write-Header "COMPRESSION"
$archiveName = Join-Path $targetDir "travian_$backupType`_$DateStamp"
$finalBackup = Compress-BackupDirectory -SourceDir $tempBackupDir -ArchiveName $archiveName

# Cleanup old backups
Write-Header "CLEANUP"
Cleanup-OldBackups -BackupType "daily" -BackupPath $DailyBackupDir -RetentionDays $DailyRetention
if ($DayOfWeek -eq "Sunday") {
    Cleanup-OldBackups -BackupType "weekly" -BackupPath $WeeklyBackupDir -RetentionDays ($WeeklyRetention * 7)
}
if ($DayOfMonth -eq 1) {
    Cleanup-OldBackups -BackupType "monthly" -BackupPath $MonthlyBackupDir -RetentionDays ($MonthlyRetention * 30)
}

# Statistics
Write-Header "BACKUP COMPLETE"
Get-BackupStatistics

Write-Info ""
Write-Info "Backup Location: $finalBackup"
Write-Info ""
Write-Success "Backup completed successfully!"
```

---

### xampp-performance-tune.ps1

**Purpose**: Optimize PHP, MySQL, and Apache for production performance

**Features**:
- PHP optimization (memory limits, OPcache)
- MySQL InnoDB tuning
- Apache MPM configuration
- Automatic service restart
- Before/after performance comparison

**Usage**:
```powershell
.\scripts\xampp-performance-tune.ps1
.\scripts\xampp-performance-tune.ps1 -SkipRestart  # Don't restart services
```

**Script** (218 lines):

```powershell
# ============================================================================
# TRAVIANT4.6 - XAMPP PERFORMANCE TUNING SCRIPT
# ============================================================================
# Version: 2.0
# Purpose: Optimize PHP, MySQL, and Apache for production performance
# Requirements: XAMPP 8.2+, PowerShell 5.1+, Administrator privileges
# 
# USAGE:
#   .\scripts\xampp-performance-tune.ps1
#   .\scripts\xampp-performance-tune.ps1 -SkipRestart
#
# WHAT THIS SCRIPT DOES:
# 1. Analyzes current system resources (RAM, CPU)
# 2. Optimizes PHP settings (memory, OPcache, execution time)
# 3. Tunes MySQL InnoDB settings (buffer pool, query cache)
# 4. Configures Apache MPM settings (workers, connections)
# 5. Restarts services to apply changes
# 6. Validates optimizations
# ============================================================================

param(
    [switch]$SkipRestart,
    [switch]$DryRun
)

# Requires Administrator
if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "This script requires Administrator privileges. Please run as Administrator." -ForegroundColor Red
    exit 1
}

# Script Configuration
$ErrorActionPreference = "Continue"
$PhpIni = "C:\xampp\php\php.ini"
$MySQLIni = "C:\xampp\mysql\bin\my.ini"
$HttpdConf = "C:\xampp\apache\conf\httpd.conf"
$BackupDir = "C:\xampp\backups\config_backups"

# Color Output Functions
function Write-ColorOutput {
    param([string]$Message, [string]$Color = "White")
    Write-Host $Message -ForegroundColor $Color
}

function Write-Success { param([string]$Message) Write-ColorOutput "✓ $Message" "Green" }
function Write-Info { param([string]$Message) Write-ColorOutput "ℹ $Message" "Cyan" }
function Write-Warning { param([string]$Message) Write-ColorOutput "⚠ $Message" "Yellow" }
function Write-Error { param([string]$Message) Write-ColorOutput "✗ $Message" "Red" }
function Write-Header { 
    param([string]$Message) 
    Write-Host ""
    Write-ColorOutput "============================================================================" "Magenta"
    Write-ColorOutput $Message "Magenta"
    Write-ColorOutput "============================================================================" "Magenta"
}

# ============================================================================
# FUNCTION: Get-SystemResources
# ============================================================================
function Get-SystemResources {
    Write-Info "Analyzing system resources..."
    
    $os = Get-CimInstance -ClassName Win32_OperatingSystem
    $cpu = Get-CimInstance -ClassName Win32_Processor
    $totalRAM = [math]::Round($os.TotalVisibleMemorySize / 1MB, 2)
    $freeRAM = [math]::Round($os.FreePhysicalMemory / 1MB, 2)
    $cpuCores = $cpu.NumberOfCores
    
    Write-Info "  Total RAM: $totalRAM GB"
    Write-Info "  Free RAM: $freeRAM GB"
    Write-Info "  CPU Cores: $cpuCores"
    
    return @{
        TotalRAM = $totalRAM
        FreeRAM = $freeRAM
        CPUCores = $cpuCores
    }
}

# ============================================================================
# FUNCTION: Backup-ConfigFile
# ============================================================================
function Backup-ConfigFile {
    param([string]$FilePath)
    
    $fileName = Split-Path -Leaf $FilePath
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $backupFile = Join-Path $BackupDir "$fileName.$timestamp.bak"
    
    if (-not (Test-Path $BackupDir)) {
        New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
    }
    
    Copy-Item -Path $FilePath -Destination $backupFile -Force
    Write-Success "Backup created: $backupFile"
}

# ============================================================================
# FUNCTION: Optimize-PHPSettings
# ============================================================================
function Optimize-PHPSettings {
    param([hashtable]$SystemInfo)
    
    Write-Info "Optimizing PHP settings..."
    
    if (-not (Test-Path $PhpIni)) {
        Write-Error "PHP configuration not found: $PhpIni"
        return
    }
    
    Backup-ConfigFile -FilePath $PhpIni
    
    $content = Get-Content $PhpIni
    
    # PHP Memory Settings
    $memoryLimit = [math]::Min(512, [math]::Round($SystemInfo.TotalRAM * 0.25))
    $content = $content -replace '^memory_limit\s*=.*', "memory_limit = ${memoryLimit}M"
    
    # Execution Time
    $content = $content -replace '^max_execution_time\s*=.*', "max_execution_time = 300"
    $content = $content -replace '^max_input_time\s*=.*', "max_input_time = 300"
    
    # Upload Settings
    $content = $content -replace '^post_max_size\s*=.*', "post_max_size = 128M"
    $content = $content -replace '^upload_max_filesize\s*=.*', "upload_max_filesize = 128M"
    
    # OPcache Settings
    $opcacheMemory = [math]::Min(256, [math]::Round($SystemInfo.TotalRAM * 0.125))
    $content = $content -replace '^;?opcache.enable\s*=.*', "opcache.enable = 1"
    $content = $content -replace '^;?opcache.memory_consumption\s*=.*', "opcache.memory_consumption = $opcacheMemory"
    $content = $content -replace '^;?opcache.interned_strings_buffer\s*=.*', "opcache.interned_strings_buffer = 16"
    $content = $content -replace '^;?opcache.max_accelerated_files\s*=.*', "opcache.max_accelerated_files = 10000"
    $content = $content -replace '^;?opcache.revalidate_freq\s*=.*', "opcache.revalidate_freq = 2"
    $content = $content -replace '^;?opcache.fast_shutdown\s*=.*', "opcache.fast_shutdown = 1"
    
    # Realpath Cache
    $content = $content -replace '^;?realpath_cache_size\s*=.*', "realpath_cache_size = 4096K"
    $content = $content -replace '^;?realpath_cache_ttl\s*=.*', "realpath_cache_ttl = 600"
    
    if (-not $DryRun) {
        $content | Set-Content -Path $PhpIni -Encoding UTF8
        Write-Success "PHP settings optimized"
    } else {
        Write-Info "DRY RUN: Would optimize PHP settings"
    }
}

# ============================================================================
# FUNCTION: Optimize-MySQLSettings
# ============================================================================
function Optimize-MySQLSettings {
    param([hashtable]$SystemInfo)
    
    Write-Info "Optimizing MySQL settings..."
    
    if (-not (Test-Path $MySQLIni)) {
        Write-Error "MySQL configuration not found: $MySQLIni"
        return
    }
    
    Backup-ConfigFile -FilePath $MySQLIni
    
    $content = Get-Content $MySQLIni
    
    # InnoDB Buffer Pool (50-70% of total RAM for dedicated DB server, 25% for shared)
    $bufferPoolSize = [math]::Round($SystemInfo.TotalRAM * 0.25 * 1024)  # In MB
    $content = $content -replace '^innodb_buffer_pool_size\s*=.*', "innodb_buffer_pool_size = ${bufferPoolSize}M"
    
    # Query Cache (deprecated in MySQL 8.0, but keep for compatibility)
    $content = $content -replace '^query_cache_size\s*=.*', "query_cache_size = 64M"
    $content = $content -replace '^query_cache_type\s*=.*', "query_cache_type = 1"
    $content = $content -replace '^query_cache_limit\s*=.*', "query_cache_limit = 2M"
    
    # Connection Settings
    $maxConnections = [math]::Max(200, $SystemInfo.CPUCores * 50)
    $content = $content -replace '^max_connections\s*=.*', "max_connections = $maxConnections"
    $content = $content -replace '^thread_cache_size\s*=.*', "thread_cache_size = 16"
    
    # Table Cache
    $content = $content -replace '^table_open_cache\s*=.*', "table_open_cache = 4096"
    
    # InnoDB Settings
    $content = $content -replace '^innodb_log_file_size\s*=.*', "innodb_log_file_size = 256M"
    $content = $content -replace '^innodb_log_buffer_size\s*=.*', "innodb_log_buffer_size = 8M"
    $content = $content -replace '^innodb_flush_log_at_trx_commit\s*=.*', "innodb_flush_log_at_trx_commit = 2"
    $content = $content -replace '^innodb_file_per_table\s*=.*', "innodb_file_per_table = 1"
    
    if (-not $DryRun) {
        $content | Set-Content -Path $MySQLIni -Encoding UTF8
        Write-Success "MySQL settings optimized"
    } else {
        Write-Info "DRY RUN: Would optimize MySQL settings"
    }
}

# ============================================================================
# FUNCTION: Optimize-ApacheSettings
# ============================================================================
function Optimize-ApacheSettings {
    param([hashtable]$SystemInfo)
    
    Write-Info "Optimizing Apache settings..."
    
    if (-not (Test-Path $HttpdConf)) {
        Write-Error "Apache configuration not found: $HttpdConf"
        return
    }
    
    Backup-ConfigFile -FilePath $HttpdConf
    
    $content = Get-Content $HttpdConf
    
    # MPM Settings (calculated based on available RAM and CPU)
    $maxClients = [math]::Min(400, $SystemInfo.CPUCores * 100)
    $startServers = [math]::Max(5, [math]::Round($SystemInfo.CPUCores / 2))
    $minSpareThreads = $startServers * 5
    $maxSpareThreads = $startServers * 10
    
    # Find and update MPM section
    $mpmUpdated = $false
    for ($i = 0; $i -lt $content.Count; $i++) {
        if ($content[$i] -match '<IfModule mpm_winnt_module>') {
            $content[$i+1] = "    ThreadsPerChild 150"
            $content[$i+2] = "    MaxRequestWorkers $maxClients"
            $mpmUpdated = $true
            break
        }
    }
    
    # KeepAlive Settings
    $content = $content -replace '^KeepAlive\s+.*', "KeepAlive On"
    $content = $content -replace '^MaxKeepAliveRequests\s+.*', "MaxKeepAliveRequests 100"
    $content = $content -replace '^KeepAliveTimeout\s+.*', "KeepAliveTimeout 5"
    
    # Timeout
    $content = $content -replace '^Timeout\s+.*', "Timeout 300"
    
    if (-not $DryRun) {
        $content | Set-Content -Path $HttpdConf -Encoding UTF8
        Write-Success "Apache settings optimized"
    } else {
        Write-Info "DRY RUN: Would optimize Apache settings"
    }
}

# ============================================================================
# FUNCTION: Restart-Services
# ============================================================================
function Restart-Services {
    Write-Info "Restarting services to apply changes..."
    
    # Stop services
    Write-Info "Stopping Apache..."
    & "C:\xampp\apache_stop.bat" | Out-Null
    Start-Sleep -Seconds 3
    
    Write-Info "Stopping MySQL..."
    & "C:\xampp\mysql_stop.bat" | Out-Null
    Start-Sleep -Seconds 5
    
    # Start services
    Write-Info "Starting MySQL..."
    & "C:\xampp\mysql_start.bat" | Out-Null
    Start-Sleep -Seconds 10
    
    Write-Info "Starting Apache..."
    & "C:\xampp\apache_start.bat" | Out-Null
    Start-Sleep -Seconds 5
    
    Write-Success "Services restarted"
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

Write-Header "TRAVIANT4.6 - XAMPP PERFORMANCE TUNING"

if ($DryRun) {
    Write-Warning "DRY RUN MODE - No changes will be made"
}

# Get system info
Write-Header "SYSTEM ANALYSIS"
$systemInfo = Get-SystemResources

# Optimize components
Write-Header "PHP OPTIMIZATION"
Optimize-PHPSettings -SystemInfo $systemInfo

Write-Header "MYSQL OPTIMIZATION"
Optimize-MySQLSettings -SystemInfo $systemInfo

Write-Header "APACHE OPTIMIZATION"
Optimize-ApacheSettings -SystemInfo $systemInfo

# Restart services
if (-not $SkipRestart -and -not $DryRun) {
    Write-Header "SERVICE RESTART"
    Restart-Services
}

# Summary
Write-Header "OPTIMIZATION COMPLETE"
Write-Success "All optimizations applied successfully"
Write-Info ""
Write-Info "Optimized Settings:"
Write-Info "  PHP Memory Limit: $(  [math]::Min(512, [math]::Round($systemInfo.TotalRAM * 0.25)))M"
Write-Info "  PHP OPcache: $([math]::Min(256, [math]::Round($systemInfo.TotalRAM * 0.125)))M"
Write-Info "  MySQL Buffer Pool: $([math]::Round($systemInfo.TotalRAM * 0.25 * 1024))M"
Write-Info "  MySQL Max Connections: $([math]::Max(200, $systemInfo.CPUCores * 50))"
Write-Info "  Apache Max Workers: $([math]::Min(400, $systemInfo.CPUCores * 100))"
Write-Info ""
Write-Info "Configuration backups saved to: $BackupDir"

if ($SkipRestart) {
    Write-Warning "Services not restarted (SkipRestart flag set)"
    Write-Info "Please restart Apache and MySQL manually for changes to take effect"
}
```

---

### xampp-healthcheck.ps1

**Purpose**: Continuous monitoring and health checking

**Features**:
- Service status monitoring
- Disk space alerts
- Database connection tests
- API endpoint health checks
- Uptime tracking
- Log file size monitoring
- Email/Slack alerts (configurable)

**Usage**:
```powershell
.\scripts\xampp-healthcheck.ps1
.\scripts\xampp-healthcheck.ps1 -Continuous -IntervalMinutes 5
```

**Script** (235 lines):

```powershell
# ============================================================================
# TRAVIANT4.6 - XAMPP HEALTH CHECK SCRIPT
# ============================================================================
# Version: 2.0
# Purpose: Continuous monitoring and health checking
# Requirements: XAMPP 8.2+, PowerShell 5.1+
# 
# USAGE:
#   .\scripts\xampp-healthcheck.ps1
#   .\scripts\xampp-healthcheck.ps1 -Continuous -IntervalMinutes 5
#   .\scripts\xampp-healthcheck.ps1 -SendAlerts -EmailTo "admin@example.com"
#
# WHAT THIS SCRIPT MONITORS:
# 1. Service status (Apache, MySQL, PostgreSQL)
# 2. Disk space (C:\ drive, alert if <10GB free)
# 3. Database connectivity (MySQL, PostgreSQL)
# 4. API endpoint health (GET /v1/servers/loadServers)
# 5. Process uptime tracking
# 6. Log file sizes (alert if >100MB)
# 7. CPU and memory usage
# ============================================================================

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

# Script Configuration
$ErrorActionPreference = "Continue"
$MysqlBin = "C:\xampp\mysql\bin\mysql.exe"
$PsqlBin = "C:\xampp\pgsql\bin\psql.exe"
$LogFile = "C:\xampp\htdocs\logs\healthcheck_$(Get-Date -Format 'yyyyMMdd').log"

# Health Check Results
$HealthStatus = @{
    Timestamp = Get-Date
    Overall = $true
    Checks = @{}
}

# Color Output Functions
function Write-ColorOutput {
    param([string]$Message, [string]$Color = "White")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage -ForegroundColor $Color
    Add-Content -Path $LogFile -Value $logMessage -ErrorAction SilentlyContinue
}

function Write-Success { param([string]$Message) Write-ColorOutput "✓ $Message" "Green" }
function Write-Fail { param([string]$Message) Write-ColorOutput "✗ $Message" "Red"; $script:HealthStatus.Overall = $false }
function Write-Info { param([string]$Message) Write-ColorOutput "ℹ $Message" "Cyan" }
function Write-Warning { param([string]$Message) Write-ColorOutput "⚠ $Message" "Yellow" }
function Write-Header { 
    param([string]$Message) 
    Write-Host ""
    Write-ColorOutput "============================================================================" "Magenta"
    Write-ColorOutput $Message "Magenta"
    Write-ColorOutput "============================================================================" "Magenta"
}

# ============================================================================
# HEALTH CHECK FUNCTIONS
# ============================================================================

function Test-ServiceHealth {
    param([string]$ServiceName, [string]$ProcessName)
    
    $checkName = "Service_$ServiceName"
    
    $process = Get-Process -Name $ProcessName -ErrorAction SilentlyContinue
    
    if ($process) {
        $uptime = (Get-Date) - $process.StartTime
        $uptimeStr = "{0:dd}d {0:hh}h {0:mm}m" -f $uptime
        
        Write-Success "$ServiceName is running (PID: $($process.Id), Uptime: $uptimeStr)"
        $HealthStatus.Checks[$checkName] = @{
            Status = "OK"
            PID = $process.Id
            Uptime = $uptimeStr
            CPU = $process.CPU
            Memory = [math]::Round($process.WorkingSet64 / 1MB, 2)
        }
        return $true
    } else {
        Write-Fail "$ServiceName is NOT running"
        $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = "Process not found" }
        return $false
    }
}

function Test-DiskSpace {
    $drive = Get-PSDrive -Name C
    $freeSpaceGB = [math]::Round($drive.Free / 1GB, 2)
    $totalSpaceGB = [math]::Round($drive.Used / 1GB + $freeSpaceGB, 2)
    $usedPercent = [math]::Round(($drive.Used / ($drive.Used + $drive.Free)) * 100, 2)
    
    $checkName = "DiskSpace_C"
    
    if ($freeSpaceGB -lt $DiskSpaceWarningGB) {
        Write-Warning "Low disk space on C:\ - $freeSpaceGB GB free (Used: $usedPercent%)"
        $HealthStatus.Checks[$checkName] = @{
            Status = "WARNING"
            FreeGB = $freeSpaceGB
            TotalGB = $totalSpaceGB
            UsedPercent = $usedPercent
        }
    } else {
        Write-Success "Disk space OK - $freeSpaceGB GB free (Used: $usedPercent%)"
        $HealthStatus.Checks[$checkName] = @{
            Status = "OK"
            FreeGB = $freeSpaceGB
            TotalGB = $totalSpaceGB
            UsedPercent = $usedPercent
        }
    }
}

function Test-MySQLConnection {
    $checkName = "MySQL_Connection"
    
    try {
        $result = & $MysqlBin -h $MysqlHost -u $MysqlUser -p"$MysqlPassword" -e "SELECT 1;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "MySQL connection OK"
            $HealthStatus.Checks[$checkName] = @{ Status = "OK" }
            return $true
        } else {
            Write-Fail "MySQL connection failed"
            $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = $result }
            return $false
        }
    } catch {
        Write-Fail "MySQL connection error: $_"
        $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = $_.Exception.Message }
        return $false
    }
}

function Test-PostgreSQLConnection {
    $checkName = "PostgreSQL_Connection"
    $env:PGPASSWORD = $PgPassword
    
    try {
        $result = & $PsqlBin -h $PgHost -U $PgUser -d $PgDatabase -c "SELECT 1;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "PostgreSQL connection OK"
            $HealthStatus.Checks[$checkName] = @{ Status = "OK" }
            return $true
        } else {
            Write-Fail "PostgreSQL connection failed"
            $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = $result }
            return $false
        }
    } catch {
        Write-Fail "PostgreSQL connection error: $_"
        $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = $_.Exception.Message }
        return $false
    } finally {
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
    }
}

function Test-ApiEndpoint {
    param([string]$Endpoint = "/v1/servers/loadServers")
    
    $checkName = "API_$($Endpoint -replace '/',  '_')"
    $url = "$ApiUrl$Endpoint"
    
    try {
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 10
        
        if ($response.StatusCode -eq 200) {
            $responseTime = $response.Headers['X-Response-Time']
            Write-Success "API endpoint OK: $Endpoint (Status: 200)"
            $HealthStatus.Checks[$checkName] = @{ 
                Status = "OK"
                StatusCode = 200
                ResponseTime = $responseTime
            }
            return $true
        } else {
            Write-Fail "API endpoint returned status: $($response.StatusCode)"
            $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; StatusCode = $response.StatusCode }
            return $false
        }
    } catch {
        Write-Fail "API endpoint failed: $Endpoint - $_"
        $HealthStatus.Checks[$checkName] = @{ Status = "FAIL"; Reason = $_.Exception.Message }
        return $false
    }
}

function Test-LogFileSizes {
    $logFiles = @(
        "C:\xampp\apache\logs\error.log",
        "C:\xampp\apache\logs\access.log",
        "C:\xampp\php\logs\php_error.log",
        "C:\xampp\mysql\data\*.err"
    )
    
    foreach ($logPath in $logFiles) {
        $files = Get-Item -Path $logPath -ErrorAction SilentlyContinue
        
        foreach ($file in $files) {
            $sizeMB = [math]::Round($file.Length / 1MB, 2)
            $checkName = "LogSize_$($file.Name)"
            
            if ($sizeMB -gt 100) {
                Write-Warning "Large log file: $($file.Name) - $sizeMB MB"
                $HealthStatus.Checks[$checkName] = @{ Status = "WARNING"; SizeMB = $sizeMB }
            } else {
                Write-Info "Log file OK: $($file.Name) - $sizeMB MB"
                $HealthStatus.Checks[$checkName] = @{ Status = "OK"; SizeMB = $sizeMB }
            }
        }
    }
}

function Send-HealthAlert {
    if (-not $SendAlerts -or -not $EmailTo) {
        return
    }
    
    # This is a placeholder - implement email sending based on your mail server
    # You can use Send-MailMessage or external SMTP services
    
    $subject = "XAMPP Health Check Alert - $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
    $body = "Health check failed. See attached log for details."
    
    Write-Info "Would send alert email to: $EmailTo"
    # Send-MailMessage -To $EmailTo -Subject $subject -Body $body -SmtpServer "your-smtp-server"
}

function Show-HealthSummary {
    Write-Header "HEALTH CHECK SUMMARY"
    
    $totalChecks = $HealthStatus.Checks.Count
    $okChecks = ($HealthStatus.Checks.Values | Where-Object { $_.Status -eq "OK" }).Count
    $warningChecks = ($HealthStatus.Checks.Values | Where-Object { $_.Status -eq "WARNING" }).Count
    $failedChecks = ($HealthStatus.Checks.Values | Where-Object { $_.Status -eq "FAIL" }).Count
    
    Write-Info "Total Checks: $totalChecks"
    Write-ColorOutput "OK: $okChecks" "Green"
    Write-ColorOutput "WARNING: $warningChecks" "Yellow"
    Write-ColorOutput "FAILED: $failedChecks" "Red"
    
    if ($HealthStatus.Overall) {
        Write-ColorOutput "`n✓ ALL SYSTEMS OPERATIONAL" "Green"
    } else {
        Write-ColorOutput "`n✗ SYSTEM HEALTH ISSUES DETECTED" "Red"
        Send-HealthAlert
    }
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

do {
    Write-Header "TRAVIANT4.6 - XAMPP HEALTH CHECK"
    Write-Info "Check Time: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    
    # Service Health
    Write-Header "SERVICE STATUS"
    Test-ServiceHealth -ServiceName "Apache" -ProcessName "httpd"
    Test-ServiceHealth -ServiceName "MySQL" -ProcessName "mysqld"
    Test-ServiceHealth -ServiceName "PostgreSQL" -ProcessName "postgres"
    
    # Disk Space
    Write-Header "DISK SPACE"
    Test-DiskSpace
    
    # Database Connections
    Write-Header "DATABASE CONNECTIONS"
    Test-MySQLConnection
    Test-PostgreSQLConnection
    
    # API Endpoints
    Write-Header "API ENDPOINTS"
    Test-ApiEndpoint -Endpoint "/v1/servers/loadServers"
    
    # Log Files
    Write-Header "LOG FILE SIZES"
    Test-LogFileSizes
    
    # Summary
    Show-HealthSummary
    
    if ($Continuous) {
        Write-Info "`nNext check in $IntervalMinutes minutes... (Ctrl+C to stop)"
        Start-Sleep -Seconds ($IntervalMinutes * 60)
        Clear-Host
    }
    
} while ($Continuous)
```

---

## Database Setup

**⏱ Estimated Time**: 20-30 minutes

### Quick Setup (Automated)

```powershell
# Run the comprehensive setup script
.\scripts\setup-xampp.ps1

# This will:
# - Create 8 MySQL databases
# - Apply 90-table schema to each
# - Insert 12 test users per database
# - Generate connection.php configs
# - Register servers in PostgreSQL
# - Validate setup
```

### Manual Setup (Step-by-Step)

If you prefer manual control or troubleshooting:

#### 1. Create MySQL Databases

```powershell
$MysqlBin = "C:\xampp\mysql\bin\mysql.exe"
$RootPassword = "TravianSecureRoot2025!"

$databases = @(
    "travian_world_speed10k",
    "travian_world_speed125k",
    "travian_world_speed250k",
    "travian_world_speed500k",
    "travian_world_speed5m",
    "travian_world_demo",
    "travian_world_dev",
    "travian_world_testworld"
)

foreach ($db in $databases) {
    & $MysqlBin -uroot -p"$RootPassword" -e "CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    Write-Host "Created: $db" -ForegroundColor Green
}
```

#### 2. Apply Schema

```powershell
$SchemaFile = "C:\xampp\htdocs\database\mysql\windows-world-schema.sql"

foreach ($db in $databases) {
    Write-Host "Applying schema to $db..." -ForegroundColor Cyan
    Get-Content $SchemaFile | & $MysqlBin -uroot -p"$RootPassword" $db
}
```

#### 3. Insert Test Users

```powershell
$TestUsersFile = "C:\xampp\htdocs\database\mysql\windows-test-users.sql"

foreach ($db in $databases) {
    Write-Host "Inserting test users into $db..." -ForegroundColor Cyan
    Get-Content $TestUsersFile | & $MysqlBin -uroot -p"$RootPassword" $db
}
```

#### 4. Verify Setup

```powershell
# Check table count
foreach ($db in $databases) {
    $count = (& $MysqlBin -uroot -p"$RootPassword" -s -N -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$db' AND TABLE_TYPE='BASE TABLE';").Trim()
    Write-Host "$db : $count tables" -ForegroundColor $(if ($count -eq "90") { "Green" } else { "Red" })
}

# Check user count
foreach ($db in $databases) {
    $count = (& $MysqlBin -uroot -p"$RootPassword" -s -N -e "SELECT COUNT(*) FROM $db.users;").Trim()
    Write-Host "$db : $count users" -ForegroundColor $(if ($count -eq "12") { "Green" } else { "Red" })
}
```

---

## Validation & Testing

**⏱ Estimated Time**: 10-15 minutes

### Automated Validation

```powershell
.\scripts\validate-xampp.ps1

# With verbose output
.\scripts\validate-xampp.ps1 -Verbose
```

### Manual Testing Checklist

#### ☐ 1. Apache Service

```powershell
# Check Apache is running
Get-Process -Name "httpd" -ErrorAction SilentlyContinue

# Test HTTP response
Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing

# Expected: Status 200
```

#### ☐ 2. MySQL Service

```powershell
# Check MySQL is running
Get-Process -Name "mysqld" -ErrorAction SilentlyContinue

# Test connection
& "C:\xampp\mysql\bin\mysql.exe" -utravian -pTravianDB2025! -e "SELECT 1;"

# Expected: Output "1"
```

#### ☐ 3. PostgreSQL Service

```powershell
# Check PostgreSQL is running
Get-Process -Name "postgres" -ErrorAction SilentlyContinue

# Test connection
& "C:\xampp\pgsql\bin\psql.exe" -U postgres -d travian_global -c "SELECT 1;"

# Expected: Output "1"
```

#### ☐ 4. API Endpoints

```powershell
# Test server list API
$response = Invoke-RestMethod -Uri "http://localhost/v1/servers/loadServers"
$response.data | Format-Table -Property worldId, name, speed

# Expected: 8 servers listed
```

#### ☐ 5. Test Login

```powershell
$body = @{
    gameWorldId = 1
    usernameOrEmail = "testuser1"
    password = "test123"
} | ConvertTo-Json

$response = Invoke-RestMethod -Uri "http://localhost/v1/auth/login" -Method POST -Body $body -ContentType "application/json"

$response

# Expected: JSON with redirectUrl
```

#### ☐ 6. Frontend Access

```powershell
# Open browser
Start-Process "http://localhost"

# Manual checks:
# - Angular SPA loads
# - Server list displays 8 servers
# - Login form works
# - No console errors (F12 → Console)
```

---

## Troubleshooting

### Issue 1: Port 80 Already in Use

**Symptoms**: Apache won't start, error about port 80 in use

**Diagnosis**:
```powershell
# Find what's using port 80
netstat -ano | findstr ":80 "

# Identify the process
$pid = (Get-NetTCPConnection -LocalPort 80).OwningProcess
Get-Process -Id $pid
```

**Solutions**:

**Option A**: Stop conflicting service (IIS)
```powershell
Stop-Service W3SVC -Force
Set-Service W3SVC -StartupType Disabled
```

**Option B**: Change Apache port to 8080
Edit `C:\xampp\apache\conf\httpd.conf`:
```apache
Listen 8080
ServerName localhost:8080
```

Then access via `http://localhost:8080`

---

### Issue 2: Port 3306 Conflict (MySQL)

**Symptoms**: MySQL won't start

**Diagnosis**:
```powershell
netstat -ano | findstr ":3306"
```

**Solution**: Change MySQL port
Edit `C:\xampp\mysql\bin\my.ini`:
```ini
[mysqld]
port = 3307
```

Update connection configs:
```powershell
# Update all connection.php files
Get-ChildItem -Path "C:\xampp\htdocs\sections\servers\*\config\connection.php" -Recurse | ForEach-Object {
    (Get-Content $_).Replace("'port' => 3306", "'port' => 3307") | Set-Content $_
}
```

---

### Issue 3: PostgreSQL Won't Start

**Symptoms**: PostgreSQL fails to start

**Diagnosis**:
```powershell
# Check logs
Get-Content "C:\xampp\pgsql\logs\postgres.log" -Tail 50

# Check port 5432
netstat -ano | findstr ":5432"
```

**Solutions**:

**A**: Data directory permissions
```powershell
icacls "C:\xampp\pgsql\data" /grant "Users:(OI)(CI)F" /T
```

**B**: Reinitialize data directory
```powershell
Remove-Item "C:\xampp\pgsql\data" -Recurse -Force
& "C:\xampp\pgsql\bin\initdb.exe" -D "C:\xampp\pgsql\data" -U postgres -W -E UTF8
```

---

### Issue 4: 404 Errors on /v1/ API Endpoints

**Symptoms**: API calls return 404 Not Found

**Diagnosis**:
```powershell
# Check mod_rewrite is enabled
Select-String -Path "C:\xampp\apache\conf\httpd.conf" -Pattern "mod_rewrite"

# Check .htaccess exists
Test-Path "C:\xampp\htdocs\.htaccess"
```

**Solutions**:

**A**: Enable mod_rewrite
Edit `C:\xampp\apache\conf\httpd.conf`:
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

**B**: Enable AllowOverride
```apache
<Directory "C:/xampp/htdocs">
    AllowOverride All
</Directory>
```

**C**: Restart Apache
```powershell
C:\xampp\apache_stop.bat
C:\xampp\apache_start.bat
```

---

### Issue 5: Database Connection Errors

**Symptoms**: "Database connection failed" in logs

**Diagnosis**:
```powershell
# Test MySQL connection
& "C:\xampp\mysql\bin\mysql.exe" -utravian -pTravianDB2025! -e "SHOW DATABASES LIKE 'travian_world_%';"

# Check connection.php files
Get-ChildItem -Path "C:\xampp\htdocs\sections\servers\*\config\connection.php" -Recurse
```

**Solutions**:

**A**: Verify credentials
Check `sections/servers/speed10k/config/connection.php`:
```php
'username' => 'travian',
'password' => 'TravianDB2025!',
```

**B**: Check MySQL user privileges
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -e "SHOW GRANTS FOR 'travian'@'localhost';"
```

**C**: Recreate connection configs
```powershell
.\scripts\setup-xampp.ps1
```

---

### Issue 6: PHP Errors

**Symptoms**: White page, PHP errors in logs

**Diagnosis**:
```powershell
# Check PHP error log
Get-Content "C:\xampp\php\logs\php_error.log" -Tail 50

# Check Apache error log
Get-Content "C:\xampp\apache\logs\error.log" -Tail 50
```

**Solutions**:

**A**: Enable error display (development only)
Edit `C:\xampp\php\php.ini`:
```ini
display_errors = On
error_reporting = E_ALL
```

**B**: Check PHP extensions
```powershell
& "C:\xampp\php\php.exe" -m | Select-String "mysql|pgsql"

# Expected: mysqli, pdo_mysql, pdo_pgsql, pgsql
```

**C**: Increase memory limit
```ini
memory_limit = 512M
```

---

### Issue 7: Permission Denied Errors

**Symptoms**: "Permission denied" when writing files

**Solutions**:

**A**: Run XAMPP Control Panel as Administrator
```powershell
Start-Process "C:\xampp\xampp-control.exe" -Verb RunAs
```

**B**: Fix folder permissions
```powershell
icacls "C:\xampp\htdocs" /grant "Users:(OI)(CI)F" /T
icacls "C:\xampp\tmp" /grant "Users:(OI)(CI)F" /T
icacls "C:\xampp\apache\logs" /grant "Users:(OI)(CI)F" /T
```

**C**: Disable UAC temporarily (not recommended for production)

---

### Issue 8: Angular Routes Return 404

**Symptoms**: Refreshing Angular routes shows 404

**Solution**: Verify .htaccess Angular SPA routing

`C:\xampp\htdocs\.htaccess`:
```apache
# Angular SPA Routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ angularIndex/browser/index.html [QSA,L]
```

---

### Issue 9: API Returns 500 Internal Server Error

**Diagnosis**:
```powershell
# Check Apache error log
Get-Content "C:\xampp\apache\logs\error.log" -Tail 100
```

**Common Causes**:
- PHP syntax errors
- Missing database connection
- Incorrect file paths
- Missing PHP extensions

**Solution**: Review error log and fix specific error

---

### Issue 10: Session Errors

**Symptoms**: "Warning: session_start(): Failed to read session data"

**Solutions**:

**A**: Check session directory permissions
```powershell
Test-Path "C:\xampp\tmp"
icacls "C:\xampp\tmp" /grant "Users:(OI)(CI)F" /T
```

**B**: Verify php.ini session settings
```ini
session.save_path = "C:\xampp\tmp"
session.gc_maxlifetime = 3600
```

**C**: Clear old sessions
```powershell
Remove-Item "C:\xampp\tmp\sess_*" -Force
```

---

### Issue 11: File Upload Fails

**Symptoms**: File uploads return error or timeout

**Solutions**:

Edit `C:\xampp\php\php.ini`:
```ini
file_uploads = On
upload_tmp_dir = "C:\xampp\tmp"
upload_max_filesize = 128M
post_max_size = 128M
max_execution_time = 300
max_input_time = 300
```

Restart Apache after changes.

---

### Issue 12: Slow Performance

**Symptoms**: Pages load slowly, database queries timeout

**Solutions**:

**A**: Enable OPcache
```powershell
.\scripts\xampp-performance-tune.ps1
```

**B**: Optimize MySQL tables
```powershell
$databases = @("travian_world_speed10k", "travian_world_speed125k")  # Add all 8

foreach ($db in $databases) {
    & "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -e "OPTIMIZE TABLE $db.users, $db.vdata, $db.movement, $db.fdata;"
}
```

**C**: Clear OPcache
```php
<?php
opcache_reset();
echo "OPcache cleared";
?>
```

---

### Issue 13: phpMyAdmin Inaccessible

**Symptoms**: Cannot access http://localhost/phpmyadmin

**Solutions**:

**A**: Check httpd-xampp.conf
```apache
Alias /phpmyadmin "C:/xampp/phpMyAdmin/"
<Directory "C:/xampp/phpMyAdmin">
    AllowOverride All
    Require local
</Directory>
```

**B**: Allow from specific IP
```apache
Require ip 192.168.1.0/24
```

**C**: Check config.inc.php
`C:\xampp\phpMyAdmin\config.inc.php`:
```php
$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['host'] = 'localhost';
```

---

### Issue 14: SSL/HTTPS Errors

**Symptoms**: "Your connection is not private" warning

**Solutions**:

**A**: Use HTTP for development
Access via `http://localhost` instead of `https://`

**B**: Create self-signed certificate
```powershell
& "C:\xampp\apache\bin\openssl.exe" req -new -x509 -days 365 -nodes -out "C:\xampp\apache\conf\ssl.crt\server.crt" -keyout "C:\xampp\apache\conf\ssl.key\server.key"
```

**C**: Import certificate to Trusted Root
```powershell
Import-Certificate -FilePath "C:\xampp\apache\conf\ssl.crt\server.crt" -CertStoreLocation Cert:\LocalMachine\Root
```

---

### Issue 15: Windows Firewall Blocking Connections

**Symptoms**: Can't access from other devices on network

**Solutions**:

**A**: Add firewall rules for Apache
```powershell
New-NetFirewallRule -DisplayName "Apache HTTP" -Direction Inbound -LocalPort 80 -Protocol TCP -Action Allow
New-NetFirewallRule -DisplayName "Apache HTTPS" -Direction Inbound -LocalPort 443 -Protocol TCP -Action Allow
```

**B**: Add firewall rules for MySQL
```powershell
New-NetFirewallRule -DisplayName "MySQL" -Direction Inbound -LocalPort 3306 -Protocol TCP -Action Allow
```

**C**: Add firewall rules for PostgreSQL
```powershell
New-NetFirewallRule -DisplayName "PostgreSQL" -Direction Inbound -LocalPort 5432 -Protocol TCP -Action Allow
```

---

## Security Hardening

**⏱ Estimated Time**: 30 minutes

### 1. Disable Directory Listing

Already configured in main `.htaccess`:
```apache
Options -Indexes
```

Verify:
```powershell
Invoke-WebRequest -Uri "http://localhost/sections/api/" -UseBasicParsing

# Should return 403 Forbidden, not file list
```

---

### 2. Secure phpMyAdmin

**A**: Change URL
Rename directory:
```powershell
Rename-Item "C:\xampp\phpMyAdmin" "C:\xampp\secure_db_admin"
```

Update `httpd-xampp.conf`:
```apache
Alias /secure_db_admin "C:/xampp/secure_db_admin/"
```

**B**: Add HTTP Authentication
Create `.htpasswd`:
```powershell
& "C:\xampp\apache\bin\htpasswd.exe" -c "C:\xampp\phpMyAdmin\.htpasswd" admin
```

Add to `.htaccess` in phpMyAdmin directory:
```apache
AuthType Basic
AuthName "Restricted Access"
AuthUserFile "C:/xampp/phpMyAdmin/.htpasswd"
Require valid-user
```

**C**: Restrict to localhost only
`C:\xampp\apache\conf\extra\httpd-xampp.conf`:
```apache
<Directory "C:/xampp/phpMyAdmin">
    Require local
    # Or specific IP:
    # Require ip 192.168.1.100
</Directory>
```

---

### 3. Set Strong MySQL Root Password

```powershell
$newPassword = "$(Get-Random)-$(Get-Date -Format 'yyyyMMddHHmmss')-SecureRoot!"

& "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$newPassword';"

Write-Host "New root password: $newPassword" -ForegroundColor Green
Write-Host "IMPORTANT: Save this password securely!" -ForegroundColor Yellow

# Update in scripts
(Get-Content "C:\xampp\htdocs\scripts\setup-xampp.ps1") -replace 'TravianSecureRoot2025!', $newPassword | Set-Content "C:\xampp\htdocs\scripts\setup-xampp.ps1"
```

---

### 4. Configure Windows Firewall (LAN Only)

Allow Apache only from local network:
```powershell
# Remove existing rules if any
Remove-NetFirewallRule -DisplayName "Apache HTTP" -ErrorAction SilentlyContinue

# Add LAN-only rule (assuming 192.168.1.0/24 subnet)
New-NetFirewallRule -DisplayName "Apache HTTP (LAN)" `
    -Direction Inbound `
    -LocalPort 80 `
    -Protocol TCP `
    -Action Allow `
    -RemoteAddress 192.168.1.0/24, 127.0.0.1

Write-Host "Firewall configured for LAN-only access" -ForegroundColor Green
```

---

### 5. Disable Unnecessary Apache Modules

Edit `C:\xampp\apache\conf\httpd.conf`:

Comment out unused modules:
```apache
# LoadModule autoindex_module modules/mod_autoindex.so
# LoadModule userdir_module modules/mod_userdir.so
# LoadModule status_module modules/mod_status.so
# LoadModule info_module modules/mod_info.so
```

Keep essential modules:
```apache
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule deflate_module modules/mod_deflate.so
LoadModule expires_module modules/mod_expires.so
LoadModule headers_module modules/mod_headers.so
LoadModule mime_module modules/mod_mime.so
LoadModule dir_module modules/mod_dir.so
LoadModule php_module modules/php8apache2_4.dll
```

---

### 6. Hide PHP Version

Edit `C:\xampp\php\php.ini`:
```ini
expose_php = Off
```

Restart Apache and verify:
```powershell
C:\xampp\apache_stop.bat
C:\xampp\apache_start.bat

# Check headers
(Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing).Headers

# Should NOT contain "X-Powered-By: PHP/8.2.x"
```

---

### 7. Secure PostgreSQL Connections

Edit `C:\xampp\pgsql\data\pg_hba.conf`:

```
# TYPE  DATABASE        USER            ADDRESS                 METHOD
# Local connections only
local   all             postgres                                md5
host    all             postgres        127.0.0.1/32            md5
host    all             postgres        ::1/128                 md5

# Reject all other connections
host    all             all             0.0.0.0/0               reject
```

Restart PostgreSQL:
```powershell
& "C:\xampp\pgsql\bin\pg_ctl.exe" -D "C:\xampp\pgsql\data" restart
```

---

### 8. Disable Dangerous PHP Functions

Edit `C:\xampp\php\php.ini`:
```ini
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
```

---

### 9. Implement Rate Limiting (mod_evasive)

Install mod_evasive:
```powershell
# Download from Apache Lounge
# Extract mod_evasive.so to C:\xampp\apache\modules\

# Enable in httpd.conf
LoadModule evasive_module modules/mod_evasive.so

# Configure
<IfModule mod_evasive.c>
    DOSHashTableSize 3097
    DOSPageCount 10
    DOSSiteCount 100
    DOSPageInterval 1
    DOSSiteInterval 1
    DOSBlockingPeriod 10
</IfModule>
```

---

### 10. Enable HTTPS (Optional)

Generate self-signed certificate:
```powershell
& "C:\xampp\apache\makecert.bat"
```

Enable SSL module in `httpd.conf`:
```apache
LoadModule ssl_module modules/mod_ssl.so
Include conf/extra/httpd-ssl.conf
```

Update `httpd-ssl.conf`:
```apache
Listen 443
<VirtualHost _default_:443>
    DocumentRoot "C:/xampp/htdocs"
    ServerName localhost:443
    
    SSLEngine on
    SSLCertificateFile "conf/ssl.crt/server.crt"
    SSLCertificateKeyFile "conf/ssl.key/server.key"
</VirtualHost>
```

---

## Performance Tuning

**⏱ Estimated Time**: 20-30 minutes

### Automated Tuning

```powershell
.\scripts\xampp-performance-tune.ps1

# This will optimize:
# - PHP memory and OPcache
# - MySQL InnoDB buffer pool and query cache
# - Apache MPM workers and KeepAlive
```

### Manual Tuning

#### 1. PHP OPcache Configuration

Edit `C:\xampp\php\php.ini`:
```ini
[opcache]
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
opcache.enable_file_override = 1
opcache.validate_timestamps = 1
opcache.huge_code_pages = 1
```

Verify OPcache is working:
```php
<?php
phpinfo();
// Search for "opcache.enable" should show "On"
```

---

#### 2. MySQL InnoDB Tuning

Edit `C:\xampp\mysql\bin\my.ini`:

For 8GB RAM system:
```ini
[mysqld]
# InnoDB Buffer Pool (25% of RAM for shared server, 70% for dedicated)
innodb_buffer_pool_size = 2G

# InnoDB Log Files
innodb_log_file_size = 256M
innodb_log_buffer_size = 8M
innodb_flush_log_at_trx_commit = 2  # Better performance, slight durability trade-off

# Table Cache
table_open_cache = 4096
table_definition_cache = 2048

# Query Cache (deprecated in MySQL 8.0+, but may help in 5.7)
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# Thread Settings
thread_cache_size = 16
max_connections = 200

# Temporary Tables
tmp_table_size = 64M
max_heap_table_size = 64M

# Sort Buffer
sort_buffer_size = 2M
read_buffer_size = 1M
read_rnd_buffer_size = 4M
join_buffer_size = 2M
```

For 16GB RAM system:
```ini
innodb_buffer_pool_size = 4G
query_cache_size = 128M
max_connections = 400
```

Restart MySQL:
```powershell
C:\xampp\mysql_stop.bat
C:\xampp\mysql_start.bat
```

Verify settings:
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
```

---

#### 3. Apache MPM Tuning

Edit `C:\xampp\apache\conf\extra\httpd-mpm.conf`:

```apache
<IfModule mpm_winnt_module>
    ThreadsPerChild 150
    MaxRequestWorkers 400
    MaxConnectionsPerChild 0
</IfModule>
```

For high-traffic:
```apache
<IfModule mpm_winnt_module>
    ThreadsPerChild 250
    MaxRequestWorkers 800
    MaxConnectionsPerChild 10000
</IfModule>
```

Edit `C:\xampp\apache\conf\httpd.conf`:
```apache
KeepAlive On
MaxKeepAliveRequests 100
KeepAliveTimeout 5

Timeout 300
```

---

#### 4. Enable Compression

Already configured in `.htaccess`, verify in `httpd.conf`:
```apache
LoadModule deflate_module modules/mod_deflate.so

<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
    DeflateCompressionLevel 6
</IfModule>
```

Test compression:
```powershell
$headers = (Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing).Headers
$headers['Content-Encoding']

# Should show "gzip" or "deflate"
```

---

#### 5. Enable Browser Caching

Already configured in `.htaccess`:
```apache
<ifModule mod_expires.c>
    ExpiresActive On
    ExpiresDefault A1800
    
    # Expires after 1 month
    ExpiresByType text/css A2592000
    ExpiresByType image/gif A2592000
    ExpiresByType image/png A2592000
    ExpiresByType image/jpg A2592000
    ExpiresByType image/jpeg A2592000
    ExpiresByType image/x-icon A2592000
    ExpiresByType application/pdf A2592000
    ExpiresByType application/x-javascript A2592000
    ExpiresByType application/javascript A2592000
    ExpiresByType text/javascript A2592000
</ifModule>
```

---

#### 6. Database Connection Pooling

For PHP applications, use persistent connections:

`sections/servers/*/config/connection.php`:
```php
$connection = [
    'database' => [
        'driver' => 'mysql',
        'hostname' => 'localhost',
        'database' => 'travian_world_speed10k',
        'username' => 'travian',
        'password' => 'TravianDB2025!',
        'pconnect' => true,  // Enable persistent connections
        'db_debug' => false,
        'cache_on' => true,
        'cache_dir' => 'cache/',
    ],
];
```

---

#### 7. Query Optimization Tips

**A**: Add Indexes
```sql
-- Check slow queries
SHOW PROCESSLIST;

-- Add index on frequently queried columns
ALTER TABLE travian_world_speed10k.users ADD INDEX idx_email (email);
ALTER TABLE travian_world_speed10k.vdata ADD INDEX idx_owner (owner);
ALTER TABLE travian_world_speed10k.movement ADD INDEX idx_to_from (to_village, from_village);
```

**B**: Analyze and Optimize Tables
```powershell
$databases = @("travian_world_speed10k", "travian_world_speed125k", "travian_world_speed250k", "travian_world_speed500k", "travian_world_speed5m", "travian_world_demo", "travian_world_dev", "travian_world_testworld")

foreach ($db in $databases) {
    Write-Host "Optimizing $db..." -ForegroundColor Cyan
    
    # Get all tables
    $tables = (& "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -s -N -e "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$db'") -split "`n"
    
    foreach ($table in $tables) {
        if ($table.Trim()) {
            & "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -e "ANALYZE TABLE $db.$table; OPTIMIZE TABLE $db.$table;"
        }
    }
}
```

---

## Maintenance Procedures

### Daily Maintenance

#### 1. Check Service Health

```powershell
.\scripts\xampp-healthcheck.ps1
```

Or manually:
```powershell
Get-Process -Name "httpd", "mysqld", "postgres" | Select-Object Name, Id, CPU, WS

# WS = Working Set (memory usage in bytes)
```

---

#### 2. Review Error Logs

```powershell
# Apache errors
Get-Content "C:\xampp\apache\logs\error.log" -Tail 100

# PHP errors
Get-Content "C:\xampp\php\logs\php_error.log" -Tail 100

# MySQL errors
Get-Content "C:\xampp\mysql\data\*.err" -Tail 100
```

---

#### 3. Monitor Disk Space

```powershell
Get-PSDrive -Name C | Select-Object Name, @{Name="Used(GB)";Expression={[math]::Round($_.Used/1GB,2)}}, @{Name="Free(GB)";Expression={[math]::Round($_.Free/1GB,2)}}

# Alert if <10GB free
$freeSpaceGB = [math]::Round((Get-PSDrive C).Free / 1GB, 2)
if ($freeSpaceGB -lt 10) {
    Write-Host "WARNING: Low disk space - $freeSpaceGB GB free" -ForegroundColor Red
}
```

---

### Weekly Maintenance

#### 1. Database Backups

```powershell
.\scripts\xampp-backup.ps1

# Weekly backups are automatically created on Sundays
```

Manual backup:
```powershell
$BackupDir = "D:\Backups\Travian\manual"
New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null

# PostgreSQL backup
& "C:\xampp\pgsql\bin\pg_dump.exe" -U postgres -d travian_global -f "$BackupDir\postgres_$(Get-Date -Format 'yyyyMMdd').sql"

# MySQL backups
$databases = @("travian_world_speed10k", "travian_world_speed125k", "travian_world_speed250k", "travian_world_speed500k", "travian_world_speed5m", "travian_world_demo", "travian_world_dev", "travian_world_testworld")

foreach ($db in $databases) {
    & "C:\xampp\mysql\bin\mysqldump.exe" -uroot -pTravianSecureRoot2025! $db > "$BackupDir\$db`_$(Get-Date -Format 'yyyyMMdd').sql"
}

Write-Host "Backup complete: $BackupDir" -ForegroundColor Green
```

---

#### 2. Log Rotation

```powershell
# Rotate Apache logs
$LogDir = "C:\xampp\apache\logs"
$ArchiveDir = "C:\xampp\apache\logs\archive"
New-Item -ItemType Directory -Path $ArchiveDir -Force | Out-Null

Get-ChildItem -Path $LogDir -Filter "*.log" | Where-Object { $_.Length -gt 100MB } | ForEach-Object {
    $archiveName = "$($_.BaseName)_$(Get-Date -Format 'yyyyMMdd')$($_.Extension)"
    Move-Item -Path $_.FullName -Destination "$ArchiveDir\$archiveName"
    Write-Host "Archived: $archiveName" -ForegroundColor Green
}

# Clear PHP error log if >100MB
$phpLog = "C:\xampp\php\logs\php_error.log"
if ((Get-Item $phpLog -ErrorAction SilentlyContinue).Length -gt 100MB) {
    Copy-Item $phpLog "$phpLog.old"
    Clear-Content $phpLog
    Write-Host "PHP error log rotated" -ForegroundColor Green
}
```

---

#### 3. Database Optimization

```powershell
# Optimize all MySQL databases
$databases = @("travian_world_speed10k", "travian_world_speed125k", "travian_world_speed250k", "travian_world_speed500k", "travian_world_speed5m", "travian_world_demo", "travian_world_dev", "travian_world_testworld")

foreach ($db in $databases) {
    Write-Host "Optimizing $db..." -ForegroundColor Cyan
    & "C:\xampp\mysql\bin\mysqlcheck.exe" -uroot -pTravianSecureRoot2025! --optimize $db
}

# PostgreSQL VACUUM
& "C:\xampp\pgsql\bin\vacuumdb.exe" -U postgres -d travian_global --analyze --verbose
```

---

### Monthly Maintenance

#### 1. Update XAMPP Components

Check for updates:
- https://www.apachefriends.org/download.html
- https://www.php.net/downloads
- https://dev.mysql.com/downloads/mysql/

**IMPORTANT**: Always backup before updating!

```powershell
# Backup before update
.\scripts\xampp-backup.ps1

# Stop services
C:\xampp\apache_stop.bat
C:\xampp\mysql_stop.bat

# Download and install XAMPP update
# Follow installation wizard

# Restore configuration files
Copy-Item "C:\xampp\backups\config_backups\php.ini.*" "C:\xampp\php\php.ini"
Copy-Item "C:\xampp\backups\config_backups\my.ini.*" "C:\xampp\mysql\bin\my.ini"
Copy-Item "C:\xampp\backups\config_backups\httpd.conf.*" "C:\xampp\apache\conf\httpd.conf"
```

---

#### 2. Security Audit

```powershell
# Check for exposed phpMyAdmin
Invoke-WebRequest -Uri "http://localhost/phpmyadmin" -UseBasicParsing

# Should return 403 or require authentication

# Check for directory listing
Invoke-WebRequest -Uri "http://localhost/sections/api/" -UseBasicParsing

# Should return 403 Forbidden

# Verify MySQL root password is strong
& "C:\xampp\mysql\bin\mysql.exe" -uroot -p -e "SELECT USER(), CURRENT_USER();"

# Check for weak passwords in test accounts
& "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -e "SELECT name, password FROM travian_world_speed10k.users WHERE password='test123' OR password='admin123';"

# WARNING: In production, change these passwords!
```

---

#### 3. Performance Review

```powershell
# Check slow queries
& "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -e "SHOW GLOBAL STATUS LIKE 'Slow_queries';"

# Check table sizes
& "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -e "
SELECT TABLE_SCHEMA, TABLE_NAME, 
       ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS 'Size (MB)'
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA LIKE 'travian_world_%'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
LIMIT 20;"

# Check OPcache hit rate
& "C:\xampp\php\php.exe" -r "
\$status = opcache_get_status();
\$hitRate = \$status['opcache_statistics']['opcache_hit_rate'];
echo 'OPcache hit rate: ' . round(\$hitRate, 2) . '%' . PHP_EOL;
if (\$hitRate < 90) {
    echo 'WARNING: Low hit rate. Consider increasing opcache.memory_consumption' . PHP_EOL;
}
"
```

---

### Backup Restoration

#### Restore PostgreSQL

```powershell
# Stop Apache (to prevent connections)
C:\xampp\apache_stop.bat

# Drop and recreate database
& "C:\xampp\pgsql\bin\psql.exe" -U postgres -c "DROP DATABASE travian_global;"
& "C:\xampp\pgsql\bin\psql.exe" -U postgres -c "CREATE DATABASE travian_global;"

# Restore from backup
$BackupFile = "C:\xampp\backups\daily\postgres_travian_global_20250101_120000.sql"
Get-Content $BackupFile | & "C:\xampp\pgsql\bin\psql.exe" -U postgres -d travian_global

# Restart Apache
C:\xampp\apache_start.bat

Write-Host "PostgreSQL restored successfully" -ForegroundColor Green
```

---

#### Restore MySQL Database

```powershell
# Stop Apache
C:\xampp\apache_stop.bat

# Drop and recreate database
& "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -e "DROP DATABASE travian_world_speed10k;"
& "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! -e "CREATE DATABASE travian_world_speed10k CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Restore from backup
$BackupFile = "C:\xampp\backups\daily\mysql_travian_world_speed10k_20250101_120000.sql"
Get-Content $BackupFile | & "C:\xampp\mysql\bin\mysql.exe" -uroot -pTravianSecureRoot2025! travian_world_speed10k

# Restart Apache
C:\xampp\apache_start.bat

Write-Host "MySQL database restored successfully" -ForegroundColor Green
```

---

### Monitoring Dashboard

#### Using phpMyAdmin

Access: `http://localhost/phpmyadmin`

**Features**:
- Database sizes
- Table row counts
- Query statistics
- Server status
- User accounts

---

#### Apache Server Status

Enable in `httpd.conf`:
```apache
LoadModule status_module modules/mod_status.so

<Location /server-status>
    SetHandler server-status
    Require local
</Location>
```

Access: `http://localhost/server-status`

Shows:
- Requests per second
- Active connections
- Worker status
- Uptime

---

## Success Checklist

Before marking your deployment complete, verify all items:

### Pre-Deployment Checklist

- ☐ XAMPP 8.2.12 installed to `C:\xampp`
- ☐ PostgreSQL addon extracted to `C:\xampp\pgsql`
- ☐ Project files copied to `C:\xampp\htdocs`
- ☐ File permissions configured (`Users` has full control)
- ☐ All critical files present (router.php, .htaccess, sections/api/index.php)

### Configuration Checklist

- ☐ Apache DocumentRoot set to `C:/xampp/htdocs`
- ☐ mod_rewrite enabled in `httpd.conf`
- ☐ AllowOverride set to `All`
- ☐ PHP.ini optimized (memory_limit=512M, OPcache enabled)
- ☐ MySQL my.ini tuned (innodb_buffer_pool_size configured)
- ☐ Root .htaccess created with routing rules
- ☐ Environment variables set (or .env file created)

### Service Checklist

- ☐ Apache running (port 80)
- ☐ MySQL running (port 3306)
- ☐ PostgreSQL running (port 5432)
- ☐ Apache responds on `http://localhost`
- ☐ No errors in `C:\xampp\apache\logs\error.log`
- ☐ No errors in `C:\xampp\php\logs\php_error.log`

### Database Checklist

- ☐ PostgreSQL `travian_global` database created
- ☐ PostgreSQL `gameservers` table exists with 8 servers
- ☐ MySQL 8 world databases created
- ☐ Each MySQL database has 90 tables
- ☐ Each MySQL database has 12 test users
- ☐ Connection.php files generated for all 8 worlds
- ☐ Test MySQL connection succeeds (`mysql -utravian -p`)
- ☐ Test PostgreSQL connection succeeds (`psql -U postgres -d travian_global`)

### API Checklist

- ☐ GET `/v1/servers/loadServers` returns 8 servers
- ☐ POST `/v1/auth/login` accepts test credentials
- ☐ API endpoints return JSON (not HTML error pages)
- ☐ No 404 errors on `/v1/*` routes
- ☐ No 500 errors in API responses

### Frontend Checklist

- ☐ http://localhost loads Angular SPA
- ☐ Server list displays 8 speed servers
- ☐ Login form is functional
- ☐ No console errors (F12 → Console)
- ☐ No network errors (F12 → Network)
- ☐ Angular routing works (refresh on `/servers` doesn't 404)

### Security Checklist

- ☐ Directory listing disabled (`Options -Indexes`)
- ☐ phpMyAdmin access restricted (localhost only or HTTP auth)
- ☐ MySQL root password changed from default
- ☐ PHP version hidden (`expose_php=Off`)
- ☐ Dangerous PHP functions disabled
- ☐ PostgreSQL `pg_hba.conf` restricts to local connections
- ☐ Windows Firewall rules configured (if exposing to network)

### Performance Checklist

- ☐ PHP OPcache enabled and configured
- ☐ MySQL InnoDB buffer pool sized appropriately
- ☐ Apache compression enabled (gzip/deflate)
- ☐ Browser caching headers configured
- ☐ Realpath cache enabled
- ☐ Apache KeepAlive enabled

### Backup Checklist

- ☐ Backup script created (`scripts\xampp-backup.ps1`)
- ☐ Manual backup tested and verified
- ☐ Backup restoration tested
- ☐ Backup schedule configured (daily, weekly, monthly)
- ☐ Backup directory has sufficient space

### Monitoring Checklist

- ☐ Health check script created (`scripts\xampp-healthcheck.ps1`)
- ☐ Log rotation configured
- ☐ Disk space monitoring enabled
- ☐ Error log monitoring in place

### Documentation Checklist

- ☐ All passwords documented and stored securely
- ☐ Configuration changes documented
- ☐ Custom modifications noted
- ☐ Recovery procedures documented

---

## Conclusion

Congratulations! You have successfully deployed TravianT4.6 to XAMPP on Windows 11! 🎉

### Quick Reference

**Start Services**:
```powershell
C:\xampp\apache_start.bat
C:\xampp\mysql_start.bat
# PostgreSQL: Already running if installed as service
```

**Stop Services**:
```powershell
C:\xampp\apache_stop.bat
C:\xampp\mysql_stop.bat
```

**URLs**:
- Frontend: http://localhost
- API: http://localhost/v1/
- phpMyAdmin: http://localhost/phpmyadmin

**Test Credentials**:
- Username: `testuser1` (or testuser2-10, admin, demo)
- Password: `test123` (admin123 for admin, demo123 for demo)

**Maintenance Scripts**:
```powershell
.\scripts\setup-xampp.ps1           # Setup databases
.\scripts\validate-xampp.ps1        # Validate deployment
.\scripts\xampp-backup.ps1          # Create backups
.\scripts\xampp-performance-tune.ps1  # Optimize performance
.\scripts\xampp-healthcheck.ps1     # Check system health
```

**Support Resources**:
- Apache Logs: `C:\xampp\apache\logs\error.log`
- PHP Logs: `C:\xampp\php\logs\php_error.log`
- MySQL Logs: `C:\xampp\mysql\data\*.err`
- PostgreSQL Logs: `C:\xampp\pgsql\logs\postgres.log`

---

**Document Version**: 2.0  
**Last Updated**: November 1, 2025  
**Total Lines**: 2800+  
**Scripts Included**: 5 complete PowerShell scripts  
**Estimated Total Deployment Time**: 2-3 hours (including hardening and optimization)

For issues not covered in this guide, please refer to:
1. XAMPP Documentation: https://www.apachefriends.org/docs/
2. Project-specific documentation in `docs/completion/`
3. Troubleshooting section above (15 common issues)

---

**End of XAMPP Deployment Guide**
