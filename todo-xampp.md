# TravianT4.6 XAMPP Deployment - Interactive TODO Checklist

**Version**: 2.0  
**Last Updated**: November 1, 2025  
**Estimated Total Time**: 4-6 hours  
**Target System**: Windows 11 with XAMPP 8.2.12

---

## Quick Reference

| Phase | Time | Status | Description |
|-------|------|--------|-------------|
| [Phase 1](#phase-1-prerequisites--installation) | 30-45 min | ⬜ | Install XAMPP & Prerequisites |
| [Phase 2](#phase-2-file-deployment) | 15-20 min | ⬜ | Copy application files |
| [Phase 3](#phase-3-apache-configuration) | 20-30 min | ⬜ | Configure Apache web server |
| [Phase 4](#phase-4-php-configuration) | 15-20 min | ⬜ | Configure PHP runtime |
| [Phase 5](#phase-5-postgresql-setup) | 30-45 min | ⬜ | Install & configure PostgreSQL |
| [Phase 6](#phase-6-mysql-configuration) | 20-30 min | ⬜ | Configure MySQL databases |
| [Phase 7](#phase-7-database-provisioning) | 20-30 min | ⬜ | Run automated setup scripts |
| [Phase 8](#phase-8-validation--testing) | 15-20 min | ⬜ | Validate deployment |
| [Phase 9](#phase-9-performance-tuning) | 15-20 min | ⬜ | Optimize for production |
| [Phase 10](#phase-10-backup--maintenance) | 15-20 min | ⬜ | Configure automated tasks |

---

## Phase 1: Prerequisites & Installation

**Estimated Time**: 30-45 minutes  
**Goal**: Install XAMPP and prepare Windows environment

### 1.1 Download XAMPP
- [ ] Download XAMPP 8.2.12 for Windows from [https://www.apachefriends.org/](https://www.apachefriends.org/)
- [ ] Verify download integrity (optional but recommended)
- [ ] Ensure you have Administrator privileges

### 1.2 Install XAMPP
- [ ] Run XAMPP installer as Administrator
- [ ] Install to default path: `C:\xampp`
- [ ] Select components:
  - [x] Apache
  - [x] MySQL
  - [x] PHP
  - [x] phpMyAdmin
  - [ ] Perl (optional, not required)
  - [ ] Tomcat (optional, not required)
- [ ] Complete installation and launch XAMPP Control Panel

### 1.3 Download PostgreSQL Addon
- [ ] Download PostgreSQL 14 addon for XAMPP from [https://bitnami.com/stack/xampp](https://bitnami.com/stack/xampp)
- [ ] Extract to `C:\xampp\pgsql`
- [ ] Verify directory structure exists: `C:\xampp\pgsql\bin\`

### 1.4 Verify Windows Firewall
- [ ] Open Windows Firewall settings
- [ ] Allow Apache (port 80, 443) through firewall
- [ ] Allow MySQL (port 3306) if accessing from other machines
- [ ] Allow PostgreSQL (port 5432) if accessing from other machines

### 1.5 Disable IIS (if installed)
- [ ] Open Services (`services.msc`)
- [ ] Find "World Wide Web Publishing Service" (W3SVC)
- [ ] Stop service
- [ ] Set startup type to "Disabled"
- [ ] Verify port 80 is free: `netstat -ano | findstr ":80"`

**Phase 1 Complete**: ✅ XAMPP installed, ports available

---

## Phase 2: File Deployment

**Estimated Time**: 15-20 minutes  
**Goal**: Copy TravianT4.6 files to XAMPP htdocs

### 2.1 Backup Default htdocs (Optional)
- [ ] Rename `C:\xampp\htdocs` to `C:\xampp\htdocs.bak`
- [ ] Create fresh `C:\xampp\htdocs` directory

### 2.2 Copy Application Files
- [ ] Copy entire TravianT4.6 codebase to `C:\xampp\htdocs\`
- [ ] Verify critical directories exist:
  - [ ] `C:\xampp\htdocs\sections\api\`
  - [ ] `C:\xampp\htdocs\sections\servers\`
  - [ ] `C:\xampp\htdocs\angularIndex\browser\`
  - [ ] `C:\xampp\htdocs\database\mysql\`
  - [ ] `C:\xampp\htdocs\scripts\`
- [ ] Verify critical files exist:
  - [ ] `C:\xampp\htdocs\router.php`
  - [ ] `C:\xampp\htdocs\.htaccess`
  - [ ] `C:\xampp\htdocs\sections\api\index.php`

### 2.3 Set File Permissions
```powershell
icacls "C:\xampp\htdocs" /grant "Users:(OI)(CI)F" /T
icacls "C:\xampp\tmp" /grant "Users:(OI)(CI)F" /T
icacls "C:\xampp\apache\logs" /grant "Users:(OI)(CI)F" /T
```
- [ ] Execute permission commands as Administrator
- [ ] Verify write access to `C:\xampp\tmp`

**Phase 2 Complete**: ✅ Files deployed, permissions set

---

## Phase 3: Apache Configuration

**Estimated Time**: 20-30 minutes  
**Goal**: Configure Apache for TravianT4.6 routing

### 3.1 Enable Required Apache Modules
Edit `C:\xampp\apache\conf\httpd.conf`:
- [ ] Find and uncomment: `LoadModule rewrite_module modules/mod_rewrite.so`
- [ ] Find and uncomment: `LoadModule headers_module modules/mod_headers.so`
- [ ] Find and uncomment: `LoadModule expires_module modules/mod_expires.so`
- [ ] Find and uncomment: `LoadModule deflate_module modules/mod_deflate.so`

### 3.2 Configure DocumentRoot
Edit `C:\xampp\apache\conf\httpd.conf`:
- [ ] Find `DocumentRoot "C:/xampp/htdocs"`
- [ ] Verify it points to `C:/xampp/htdocs`
- [ ] Find `<Directory "C:/xampp/htdocs">`
- [ ] Change `AllowOverride None` to `AllowOverride All`

### 3.3 Verify .htaccess File
- [ ] Confirm `C:\xampp\htdocs\.htaccess` exists
- [ ] Review content (API routing, Angular SPA routing, security headers)
- [ ] If missing, create from template (see XAMPP-DEPLOYMENT-GUIDE.md)

### 3.4 Test Apache Configuration
```powershell
C:\xampp\apache\bin\httpd.exe -t
```
- [ ] Run syntax check
- [ ] Verify output: "Syntax OK"
- [ ] Fix any reported errors

### 3.5 Start Apache
- [ ] Open XAMPP Control Panel
- [ ] Click "Start" next to Apache
- [ ] Verify status shows "Running" (green)
- [ ] Test in browser: `http://localhost` (should load Angular app or show directory)

**Phase 3 Complete**: ✅ Apache configured and running

---

## Phase 4: PHP Configuration

**Estimated Time**: 15-20 minutes  
**Goal**: Configure PHP for optimal performance

### 4.1 Edit php.ini
Edit `C:\xampp\php\php.ini`:

#### Memory & Execution
- [ ] Set `memory_limit = 512M`
- [ ] Set `max_execution_time = 300`
- [ ] Set `max_input_time = 300`

#### Upload Settings
- [ ] Set `post_max_size = 128M`
- [ ] Set `upload_max_filesize = 128M`

#### Error Reporting (Development)
- [ ] Set `display_errors = On`
- [ ] Set `error_reporting = E_ALL`
- [ ] Set `log_errors = On`

#### Session Settings
- [ ] Set `session.save_path = "C:\xampp\tmp"`
- [ ] Set `session.gc_maxlifetime = 3600`

#### Timezone
- [ ] Set `date.timezone = America/Los_Angeles` (or your timezone)

#### Required Extensions
Verify enabled (uncommented):
- [ ] `extension=curl`
- [ ] `extension=mysqli`
- [ ] `extension=pdo_mysql`
- [ ] `extension=pgsql`
- [ ] `extension=pdo_pgsql`
- [ ] `extension=mbstring`
- [ ] `extension=openssl`

### 4.2 OPcache Configuration (Optional but Recommended)
- [ ] Find `[opcache]` section
- [ ] Set `opcache.enable = 1`
- [ ] Set `opcache.memory_consumption = 256`
- [ ] Set `opcache.max_accelerated_files = 10000`

### 4.3 Restart Apache
- [ ] Stop Apache in XAMPP Control Panel
- [ ] Wait 5 seconds
- [ ] Start Apache
- [ ] Verify PHP version: Create `C:\xampp\htdocs\info.php` with `<?php phpinfo(); ?>`
- [ ] Browse to `http://localhost/info.php`
- [ ] Verify PHP 8.2.x is loaded with correct settings
- [ ] **Delete info.php after verification** (security)

**Phase 4 Complete**: ✅ PHP configured and verified

---

## Phase 5: PostgreSQL Setup

**Estimated Time**: 30-45 minutes  
**Goal**: Install and configure PostgreSQL for global data

### 5.1 Initialize PostgreSQL Data Directory
```powershell
cd C:\xampp\pgsql\bin
.\initdb.exe -D "C:\xampp\pgsql\data" -U postgres -E UTF8 --auth=md5 --pwprompt
```
- [ ] Run initdb command
- [ ] When prompted, enter password: `postgres` (or your choice)
- [ ] Verify data directory created: `C:\xampp\pgsql\data\`

### 5.2 Configure postgresql.conf
Edit `C:\xampp\pgsql\data\postgresql.conf`:
- [ ] Set `listen_addresses = 'localhost'`
- [ ] Set `port = 5432`
- [ ] Set `max_connections = 100`
- [ ] Set `shared_buffers = 128MB`

### 5.3 Configure pg_hba.conf
Edit `C:\xampp\pgsql\data\pg_hba.conf`:
- [ ] Add line: `host all postgres 127.0.0.1/32 md5`
- [ ] Add line: `host all postgres ::1/128 md5`

### 5.4 Create PostgreSQL Windows Service
```powershell
cd C:\xampp\pgsql\bin
.\pg_ctl.exe register -N "PostgreSQL-XAMPP" -D "C:\xampp\pgsql\data"
```
- [ ] Run as Administrator
- [ ] Verify service created in `services.msc`

### 5.5 Start PostgreSQL
```powershell
cd C:\xampp\pgsql\bin
.\pg_ctl.exe -D "C:\xampp\pgsql\data" start
```
- [ ] Start PostgreSQL service
- [ ] Verify running: Check for `postgres.exe` process

### 5.6 Create travian_global Database
```powershell
cd C:\xampp\pgsql\bin
$env:PGPASSWORD = "postgres"
.\psql.exe -U postgres -c "CREATE DATABASE travian_global;"
```
- [ ] Create database
- [ ] Verify: `.\psql.exe -U postgres -l` (should list travian_global)

### 5.7 Import PostgreSQL Schema
```powershell
cd C:\xampp\pgsql\bin
$env:PGPASSWORD = "postgres"
Get-Content "C:\xampp\htdocs\database\postgresql\travian-global-schema.sql" | .\psql.exe -U postgres -d travian_global
```
- [ ] Import schema SQL file
- [ ] Verify tables created: `.\psql.exe -U postgres -d travian_global -c "\dt"`
- [ ] Should see `gameservers` table

**Phase 5 Complete**: ✅ PostgreSQL configured with travian_global database

---

## Phase 6: MySQL Configuration

**Estimated Time**: 20-30 minutes  
**Goal**: Configure MySQL and create travian user

### 6.1 Start MySQL
- [ ] Open XAMPP Control Panel
- [ ] Click "Start" next to MySQL
- [ ] Verify status shows "Running" (green)

### 6.2 Set MySQL Root Password
```powershell
cd C:\xampp\mysql\bin
.\mysql.exe -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'TravianSecureRoot2025!';"
```
- [ ] Set root password
- [ ] Test: `.\mysql.exe -u root -p` (enter password)

### 6.3 Update phpMyAdmin Configuration
Edit `C:\xampp\phpMyAdmin\config.inc.php`:
- [ ] Find `$cfg['Servers'][$i]['password'] = '';`
- [ ] Change to `$cfg['Servers'][$i]['password'] = 'TravianSecureRoot2025!';`
- [ ] Save file
- [ ] Test phpMyAdmin: `http://localhost/phpmyadmin` (login with root)

### 6.4 Create Travian MySQL User
```powershell
cd C:\xampp\mysql\bin
.\mysql.exe -u root -p"TravianSecureRoot2025!" -e "CREATE USER 'travian'@'localhost' IDENTIFIED BY 'TravianDB2025!';"
.\mysql.exe -u root -p"TravianSecureRoot2025!" -e "GRANT ALL PRIVILEGES ON travian_world_*.* TO 'travian'@'localhost';"
.\mysql.exe -u root -p"TravianSecureRoot2025!" -e "FLUSH PRIVILEGES;"
```
- [ ] Create travian user
- [ ] Grant privileges
- [ ] Test: `.\mysql.exe -u travian -p"TravianDB2025!" -e "SHOW DATABASES;"`

### 6.5 Optimize MySQL Configuration (Optional)
Edit `C:\xampp\mysql\bin\my.ini`:
- [ ] Set `innodb_buffer_pool_size = 512M` (adjust based on RAM)
- [ ] Set `max_connections = 200`
- [ ] Set `table_open_cache = 4096`
- [ ] Restart MySQL after changes

**Phase 6 Complete**: ✅ MySQL configured with travian user

---

## Phase 7: Database Provisioning

**Estimated Time**: 20-30 minutes  
**Goal**: Create game world databases and populate data

### 7.1 Verify Required Files
- [ ] Check `C:\xampp\htdocs\database\mysql\windows-world-schema.sql` exists
- [ ] Check `C:\xampp\htdocs\database\mysql\windows-test-users.sql` exists
- [ ] Check `C:\xampp\htdocs\database\mysql\windows-connection-template.php` exists
- [ ] **NOTE**: If files missing, see TODO comments in placeholder files

### 7.2 Run Setup Script
```powershell
cd C:\xampp\htdocs\scripts
.\setup-xampp.ps1
```
- [ ] Execute setup script
- [ ] Monitor output for errors
- [ ] Verify completion message

### 7.3 Verify Script Results
Expected output:
- [ ] 8 MySQL databases created (`travian_world_*`)
- [ ] Schema applied to each database (90 tables each)
- [ ] Test users inserted (12 per database)
- [ ] Connection configs generated (`sections/servers/*/config/connection.php`)
- [ ] Servers registered in PostgreSQL `gameservers` table

### 7.4 Manual Verification (if script fails)
```powershell
cd C:\xampp\mysql\bin
.\mysql.exe -u travian -p"TravianDB2025!" -e "SHOW DATABASES LIKE 'travian_world_%';"
```
- [ ] Should list 8 databases
- [ ] Check table count: `.\mysql.exe -u travian -p"TravianDB2025!" -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='travian_world_speed10k';"`
- [ ] Should return 90

**Phase 7 Complete**: ✅ Databases provisioned and populated

---

## Phase 8: Validation & Testing

**Estimated Time**: 15-20 minutes  
**Goal**: Comprehensive validation of deployment

### 8.1 Run Validation Script
```powershell
cd C:\xampp\htdocs\scripts
.\validate-xampp.ps1 -Verbose
```
- [ ] Execute validation script
- [ ] Review all checks
- [ ] Verify 100% pass rate

### 8.2 Validation Checklist
The script validates:
- [ ] Apache is running on port 80
- [ ] MySQL is running and connectable
- [ ] PostgreSQL is running and connectable
- [ ] All 8 world databases exist
- [ ] Each database has 90 tables
- [ ] Test users exist (12 per database)
- [ ] Connection config files exist
- [ ] File permissions are correct
- [ ] API endpoint `/v1/servers/loadServers` is accessible
- [ ] Sample login query succeeds

### 8.3 Manual Browser Testing
- [ ] Open browser: `http://localhost`
- [ ] Verify Angular SPA loads without errors
- [ ] Open browser console (F12) - check for JavaScript errors
- [ ] Navigate to server selection page
- [ ] Verify 8 game servers are listed
- [ ] Test login with credentials: `testuser1` / `test123`
- [ ] Verify successful login/redirect

### 8.4 API Testing
```powershell
Invoke-RestMethod -Uri "http://localhost/v1/servers/loadServers" -Method POST -Body '{"lang":"en-US"}' -ContentType "application/json"
```
- [ ] Test API endpoint
- [ ] Verify JSON response with 8 servers

### 8.5 Check Logs for Errors
- [ ] Review `C:\xampp\apache\logs\error.log` (should be empty or minor warnings)
- [ ] Review `C:\xampp\php\logs\php_error.log`
- [ ] Review `C:\xampp\mysql\data\*.err`

**Phase 8 Complete**: ✅ Deployment validated and tested

---

## Phase 9: Performance Tuning

**Estimated Time**: 15-20 minutes  
**Goal**: Optimize for production performance

### 9.1 Run Performance Tuning Script
```powershell
cd C:\xampp\htdocs\scripts
.\xampp-performance-tune.ps1
```
- [ ] Execute tuning script (requires Administrator)
- [ ] Review optimization summary
- [ ] Verify services restart successfully

### 9.2 Verify Optimizations Applied
The script optimizes:
- [ ] PHP memory limit (based on RAM)
- [ ] PHP OPcache enabled
- [ ] MySQL InnoDB buffer pool sized
- [ ] MySQL max_connections increased
- [ ] Apache MPM workers configured

### 9.3 Manual Performance Tweaks (Optional)

#### Enable Gzip Compression
Already configured in `.htaccess`, verify:
```powershell
(Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing).Headers['Content-Encoding']
```
- [ ] Should return "gzip" or "deflate"

#### Enable Browser Caching
- [ ] Verify `.htaccess` has `mod_expires` rules
- [ ] Test with browser DevTools (Network tab, check cache headers)

### 9.4 Benchmark Performance (Optional)
```powershell
Measure-Command { Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing }
```
- [ ] Record baseline response time
- [ ] Target: < 500ms for initial load

**Phase 9 Complete**: ✅ Performance optimized

---

## Phase 10: Backup & Maintenance

**Estimated Time**: 15-20 minutes  
**Goal**: Configure automated backups and monitoring

### 10.1 Test Backup Script
```powershell
cd C:\xampp\htdocs\scripts
.\xampp-backup.ps1
```
- [ ] Run manual backup test
- [ ] Verify backup created in `C:\xampp\backups\daily\`
- [ ] Check backup archive size

### 10.2 Schedule Daily Backups
```powershell
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\scripts\xampp-backup.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At 2am
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName "TravianDailyBackup" -Action $action -Trigger $trigger -Principal $principal
```
- [ ] Create scheduled task (requires Administrator)
- [ ] Verify in Task Scheduler (`taskschd.msc`)

### 10.3 Schedule Hourly Health Checks
```powershell
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\scripts\xampp-healthcheck.ps1"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Hours 1) -RepetitionDuration ([TimeSpan]::MaxValue)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName "TravianHourlyHealthCheck" -Action $action -Trigger $trigger -Principal $principal
```
- [ ] Create scheduled health check
- [ ] Test: `Start-ScheduledTask -TaskName "TravianHourlyHealthCheck"`

### 10.4 Configure Log Rotation (Optional)
```powershell
# Create weekly cleanup task
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-Command ""Get-ChildItem 'C:\xampp\apache\logs\*.log' | Where-Object {`$_.LastWriteTime -lt (Get-Date).AddDays(-30)} | Remove-Item"""
$trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Sunday -At 3am
Register-ScheduledTask -TaskName "TravianLogCleanup" -Action $action -Trigger $trigger
```
- [ ] Create log cleanup task
- [ ] Verify in Task Scheduler

### 10.5 Document Credentials
Create `C:\xampp\CREDENTIALS.txt` (secure location):
```
MySQL Root: TravianSecureRoot2025!
MySQL Travian User: TravianDB2025!
PostgreSQL: postgres
Test User: testuser1 / test123
```
- [ ] Create credentials file
- [ ] Store securely (encrypt or move to password manager)
- [ ] **Never commit to version control**

**Phase 10 Complete**: ✅ Backup and maintenance configured

---

## Post-Deployment Checklist

### Security Hardening (Recommended for Production)
- [ ] Change default MySQL root password to something unique
- [ ] Disable directory listing (already done in `.htaccess`)
- [ ] Rename phpMyAdmin directory: `C:\xampp\phpMyAdmin` → `C:\xampp\secure_admin`
- [ ] Add HTTP authentication to phpMyAdmin
- [ ] Configure Windows Firewall to allow only LAN access
- [ ] Disable unnecessary Apache modules
- [ ] Set `expose_php = Off` in `php.ini`
- [ ] Enable HTTPS with SSL certificate (optional)

### Monitoring Setup
- [ ] Test health check script: `.\scripts\xampp-healthcheck.ps1`
- [ ] Configure email alerts (modify script with SMTP settings)
- [ ] Set up continuous monitoring: `.\scripts\xampp-healthcheck.ps1 -Continuous -IntervalMinutes 5`

### Documentation
- [ ] Document any custom configuration changes
- [ ] Update team wiki with deployment details
- [ ] Create runbook for common operations
- [ ] Document firewall rules and port mappings

---

## Troubleshooting Quick Reference

### Apache Won't Start
- [ ] Check port 80: `netstat -ano | findstr ":80"`
- [ ] Stop IIS: `Stop-Service W3SVC; Set-Service W3SVC -StartupType Disabled`
- [ ] Check syntax: `C:\xampp\apache\bin\httpd.exe -t`
- [ ] Review logs: `C:\xampp\apache\logs\error.log`

### MySQL Won't Start
- [ ] Check port 3306: `netstat -ano | findstr ":3306"`
- [ ] Review error log: `C:\xampp\mysql\data\*.err`
- [ ] Verify `my.ini` syntax

### PostgreSQL Won't Start
- [ ] Check logs: `C:\xampp\pgsql\logs\postgres.log`
- [ ] Verify data directory: `C:\xampp\pgsql\data\`
- [ ] Check port 5432: `netstat -ano | findstr ":5432"`
- [ ] Reinitialize if needed: `.\initdb.exe -D "C:\xampp\pgsql\data" -U postgres -E UTF8`

### 404 Errors on API Endpoints
- [ ] Verify `mod_rewrite` enabled in `httpd.conf`
- [ ] Check `.htaccess` exists and has correct content
- [ ] Verify `AllowOverride All` in `httpd.conf`
- [ ] Restart Apache

### Database Connection Errors
- [ ] Verify MySQL/PostgreSQL running
- [ ] Test credentials manually
- [ ] Check connection config files: `sections/servers/*/config/connection.php`
- [ ] Review PHP error log: `C:\xampp\php\logs\php_error.log`

---

## Success Criteria

✅ **Deployment is successful when:**
1. All validation checks pass (100% pass rate)
2. Angular SPA loads without errors
3. 8 game servers are listed on server selection page
4. Login with test credentials works
5. API endpoints return valid JSON responses
6. No critical errors in Apache/MySQL/PostgreSQL logs
7. Automated backups are scheduled and running
8. Health checks pass consistently

---

## Next Steps After Deployment

### Development
- [ ] Set up local development workflow
- [ ] Configure IDE for PHP/JavaScript debugging
- [ ] Review codebase structure and architecture
- [ ] Set up version control workflow

### Production Preparation
- [ ] Review security hardening checklist
- [ ] Set up SSL/HTTPS certificates
- [ ] Configure production-grade firewall rules
- [ ] Set up external monitoring (e.g., UptimeRobot)
- [ ] Create disaster recovery plan
- [ ] Test backup restoration procedure

### Team Onboarding
- [ ] Share access credentials securely
- [ ] Provide training on PowerShell scripts
- [ ] Document any custom modifications
- [ ] Set up team communication channels

---

## Useful Commands Reference

### Service Management
```powershell
# Start services
C:\xampp\apache_start.bat
C:\xampp\mysql_start.bat
C:\xampp\pgsql\bin\pg_ctl.exe -D "C:\xampp\pgsql\data" start

# Stop services
C:\xampp\apache_stop.bat
C:\xampp\mysql_stop.bat
C:\xampp\pgsql\bin\pg_ctl.exe -D "C:\xampp\pgsql\data" stop

# Check service status
Get-Process httpd, mysqld, postgres
```

### Database Operations
```powershell
# MySQL
cd C:\xampp\mysql\bin
.\mysql.exe -u travian -p"TravianDB2025!" -e "SHOW DATABASES;"
.\mysql.exe -u travian -p"TravianDB2025!" travian_world_speed10k < backup.sql

# PostgreSQL
cd C:\xampp\pgsql\bin
$env:PGPASSWORD = "postgres"
.\psql.exe -U postgres -d travian_global -c "SELECT * FROM gameservers;"
```

### Log Viewing
```powershell
# Real-time Apache error log
Get-Content C:\xampp\apache\logs\error.log -Wait -Tail 50

# View PHP errors
Get-Content C:\xampp\php\logs\php_error.log -Tail 100

# View MySQL errors
Get-Content C:\xampp\mysql\data\*.err -Tail 100
```

---

## Support & Resources

- **Deployment Guide**: [XAMPP-DEPLOYMENT-GUIDE.md](XAMPP-DEPLOYMENT-GUIDE.md)
- **Quick Start**: [XAMPP-QUICKSTART.md](XAMPP-QUICKSTART.md)
- **Scripts Reference**: [XAMPP-SCRIPTS-REFERENCE.md](XAMPP-SCRIPTS-REFERENCE.md)
- **Scripts Location**: `C:\xampp\htdocs\scripts\`

---

**Document Version**: 2.0  
**Compatibility**: Windows 11, XAMPP 8.2.12, PHP 8.2, MySQL 8.0, PostgreSQL 14  
**Last Updated**: November 1, 2025
