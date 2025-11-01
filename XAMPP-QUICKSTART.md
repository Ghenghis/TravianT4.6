# XAMPP Quick Start Guide
**TravianT4.6 - 5-Minute Deployment Reference**

---

## 30-Second Overview

**What**: One-page quick reference for deploying TravianT4.6 on XAMPP (Windows 11)  
**Who**: Experienced sysadmins familiar with XAMPP/Apache/MySQL/PostgreSQL  
**When**: Use this for rapid deployment. For troubleshooting or detailed config, see [XAMPP-DEPLOYMENT-GUIDE.md](XAMPP-DEPLOYMENT-GUIDE.md)

---

## Prerequisites Checklist

- [ ] XAMPP 8.2+ installed at `C:\xampp\`
- [ ] PostgreSQL addon for XAMPP (from apachefriends.org/add-ons.html)
- [ ] Windows 11 or Windows Server 2019+
- [ ] 8GB RAM minimum, 16GB recommended
- [ ] 50GB free disk space on C:\ drive

---

## Quick Deploy Steps (10 Steps)

```powershell
# 1. Copy project files to htdocs
robocopy "C:\path\to\source" "C:\xampp\htdocs" /E /XD ".git" "node_modules" /MT:8

# 2. Set file permissions
icacls "C:\xampp\htdocs" /grant "Users:(OI)(CI)F" /T

# 3. Start services in XAMPP Control Panel
# Apache → Start
# MySQL → Start  
# PostgreSQL → Start (or via: C:\xampp\pgsql\bin\pg_ctl.exe -D C:\xampp\pgsql\data start)

# 4. Initialize databases
cd C:\xampp\htdocs\scripts
.\setup-xampp.ps1

# 5. Validate setup
.\validate-xampp.ps1

# 6. Access frontend
Start-Process "http://localhost"

# 7. Test API
Invoke-WebRequest -Uri "http://localhost/v1/servers/loadServers" -Method POST -Body '{"lang":"en-US"}' -ContentType "application/json"

# 8. Login with test account
# Username: testuser1
# Password: test123

# 9. Verify health indicators (see Health Check section below)

# 10. Done! See Next Steps for production hardening
```

---

## Essential Commands

| Task | Command |
|------|---------|
| Setup databases | `.\scripts\setup-xampp.ps1` |
| Validate setup | `.\scripts\validate-xampp.ps1` |
| Backup databases | `.\scripts\xampp-backup.ps1` |
| Check health | `.\scripts\xampp-healthcheck.ps1` |
| Tune performance | `.\scripts\xampp-performance-tune.ps1` |
| View Apache logs | `Get-Content C:\xampp\apache\logs\error.log -Tail 50` |
| View MySQL logs | `Get-Content C:\xampp\mysql\data\*.err -Tail 50` |
| View PostgreSQL logs | `Get-Content C:\xampp\pgsql\logs\postgres.log -Tail 50` |
| Restart Apache | XAMPP Control Panel → Apache → Stop/Start |
| Restart MySQL | XAMPP Control Panel → MySQL → Stop/Start |
| Restart PostgreSQL | `C:\xampp\pgsql\bin\pg_ctl.exe -D C:\xampp\pgsql\data restart` |
| Test mod_rewrite | `Invoke-WebRequest http://localhost/v1/servers/loadServers -Method POST` |
| Check PHP version | `C:\xampp\php\php.exe -v` |
| MySQL root login | `C:\xampp\mysql\bin\mysql.exe -u root -p` |
| PostgreSQL login | `C:\xampp\pgsql\bin\psql.exe -U postgres -d travian_global` |

---

## Common Issues Quick Fix

| Issue | Fix |
|-------|-----|
| **Port 80 in use** | Edit `C:\xampp\apache\conf\httpd.conf`:<br>`Listen 8080`<br>Change `ServerName localhost:80` → `ServerName localhost:8080`<br>Restart Apache |
| **MySQL won't start** | Check port 3306: `netstat -ano \| findstr :3306`<br>Stop conflicting service or change MySQL port in `C:\xampp\mysql\bin\my.ini`<br>Set root password: `mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'password';"` |
| **404 on /v1/ API** | Enable mod_rewrite in `C:\xampp\apache\conf\httpd.conf`:<br>`LoadModule rewrite_module modules/mod_rewrite.so`<br>Verify `.htaccess` exists in `C:\xampp\htdocs\`<br>Set `AllowOverride All` in `<Directory "C:/xampp/htdocs">` |
| **Database connection error** | Run `.\scripts\setup-xampp.ps1 -Force`<br>Check credentials in `.env` or connection.php files<br>Verify MySQL/PostgreSQL are running |
| **Slow performance** | Run `.\scripts\xampp-performance-tune.ps1`<br>Increase PHP memory: `memory_limit = 512M` in `php.ini`<br>Tune MySQL: `innodb_buffer_pool_size = 1G` in `my.ini` |

---

## Test Account Credentials

| Username | Password | Access Level | Gold | Purpose |
|----------|----------|--------------|------|---------|
| testuser1 | test123 | Player | 0 | Basic gameplay testing |
| testuser2 | test123 | Player | 0 | Multi-user testing |
| testuser3 | test123 | Player | 0 | Multi-user testing |
| testuser4 | test123 | Player | 0 | Multi-user testing |
| testuser5 | test123 | Player | 0 | Multi-user testing |
| testuser6 | test123 | Player | 0 | Multi-user testing |
| testuser7 | test123 | Player | 0 | Multi-user testing |
| testuser8 | test123 | Player | 0 | Multi-user testing |
| testuser9 | test123 | Player | 0 | Multi-user testing |
| testuser10 | test123 | Player | 0 | Multi-user testing |
| admin | admin123 | Level 9 Admin | 10000 | Admin panel testing |
| demo | demo123 | Player | 1000 | Demo/presentation |

**Note**: Change passwords before production deployment!

---

## Critical File Locations

| Component | Path |
|-----------|------|
| **DocumentRoot** | `C:\xampp\htdocs` |
| **Apache config** | `C:\xampp\apache\conf\httpd.conf` |
| **Apache vhosts** | `C:\xampp\apache\conf\extra\httpd-vhosts.conf` |
| **Apache logs** | `C:\xampp\apache\logs\error.log` |
| **Apache access log** | `C:\xampp\apache\logs\access.log` |
| **MySQL config** | `C:\xampp\mysql\bin\my.ini` |
| **MySQL data** | `C:\xampp\mysql\data\` |
| **MySQL logs** | `C:\xampp\mysql\data\*.err` |
| **PostgreSQL config** | `C:\xampp\pgsql\data\postgresql.conf` |
| **PostgreSQL data** | `C:\xampp\pgsql\data\` |
| **PostgreSQL logs** | `C:\xampp\pgsql\logs\postgres.log` |
| **PHP config** | `C:\xampp\php\php.ini` |
| **PHP error log** | `C:\xampp\php\logs\php_error.log` |
| **Setup scripts** | `C:\xampp\htdocs\scripts\*.ps1` |
| **API endpoints** | `C:\xampp\htdocs\sections\api\` |
| **Angular frontend** | `C:\xampp\htdocs\angularIndex\browser\` |
| **Game worlds** | `C:\xampp\htdocs\sections\servers\*\` |
| **.htaccess** | `C:\xampp\htdocs\.htaccess` |
| **router.php** | `C:\xampp\htdocs\router.php` |
| **Database schemas** | `C:\xampp\htdocs\database\mysql\*.sql` |

---

## Health Check Indicators

### XAMPP Control Panel
- ✅ Apache: **Green** indicator
- ✅ MySQL: **Green** indicator  
- ✅ PostgreSQL: **Green** indicator (or running via `pg_ctl status`)

### Service Tests
```powershell
# Apache responds
✅ Invoke-WebRequest http://localhost | Select-Object StatusCode
# Expected: 200

# API responds
✅ Invoke-RestMethod -Uri http://localhost/v1/servers/loadServers -Method POST -Body '{"lang":"en-US"}' -ContentType "application/json"
# Expected: JSON object with data.servers array containing 8 servers

# MySQL accessible
✅ C:\xampp\mysql\bin\mysql.exe -u travian -pTravianDB2025! -e "SHOW DATABASES LIKE 'travian_world_%';"
# Expected: 8 databases listed

# PostgreSQL accessible
✅ C:\xampp\pgsql\bin\psql.exe -U postgres -d travian_global -c "SELECT COUNT(*) FROM gameservers;"
# Expected: count = 8

# Login works
✅ Navigate to http://localhost
✅ Login with testuser1 / test123
# Expected: Game dashboard loads
```

### Quick Health Check Script
```powershell
# One-liner health check
$tests = @(
    @{ Name="Apache"; Test={ (Invoke-WebRequest http://localhost -UseBasicParsing).StatusCode -eq 200 } },
    @{ Name="API"; Test={ (Invoke-WebRequest http://localhost/v1/servers/loadServers -Method POST -UseBasicParsing).StatusCode -eq 200 } },
    @{ Name="MySQL"; Test={ (& C:\xampp\mysql\bin\mysql.exe -u travian -pTravianDB2025! -e "SELECT 1;" 2>&1) -notmatch "ERROR" } },
    @{ Name="PostgreSQL"; Test={ (& C:\xampp\pgsql\bin\psql.exe -U postgres -d travian_global -c "SELECT 1;" 2>&1) -notmatch "ERROR" } }
)

$tests | ForEach-Object { 
    $result = if (& $_.Test) { "✅" } else { "❌" }
    Write-Host "$result $($_.Name)"
}
```

---

## Emergency Procedures

### All Services Down
```powershell
# Nuclear option: restart everything
C:\xampp\apache_stop.bat
C:\xampp\mysql_stop.bat
C:\xampp\pgsql\bin\pg_ctl.exe -D C:\xampp\pgsql\data stop

Start-Sleep -Seconds 10

C:\xampp\apache_start.bat
C:\xampp\mysql_start.bat
C:\xampp\pgsql\bin\pg_ctl.exe -D C:\xampp\pgsql\data start

# Or via XAMPP Control Panel: Stop All → Start All
```

### Database Corrupted
```powershell
# Restore from backup (created by xampp-backup.ps1)
$BackupDir = "C:\xampp\backups\$(Get-Date -Format 'yyyy-MM-dd')"

# Restore MySQL databases
Get-ChildItem "$BackupDir\mysql\*.sql" | ForEach-Object {
    $dbName = $_.BaseName
    Write-Host "Restoring $dbName..."
    Get-Content $_.FullName | C:\xampp\mysql\bin\mysql.exe -u root -pTravianSecureRoot2025!
}

# Restore PostgreSQL
C:\xampp\pgsql\bin\psql.exe -U postgres -d travian_global -f "$BackupDir\postgresql\travian_global.sql"
```

### Config Broken
```powershell
# Reset to working state
cd C:\xampp\htdocs\scripts

# Recreate databases
.\setup-xampp.ps1 -Force

# Revalidate
.\validate-xampp.ps1

# If Apache config broken, restore from backup or reinstall XAMPP
```

### Need Help
- Full deployment guide: [XAMPP-DEPLOYMENT-GUIDE.md](XAMPP-DEPLOYMENT-GUIDE.md)
- Script reference: [XAMPP-SCRIPTS-REFERENCE.md](XAMPP-SCRIPTS-REFERENCE.md)
- Troubleshooting section: [XAMPP-DEPLOYMENT-GUIDE.md#troubleshooting](XAMPP-DEPLOYMENT-GUIDE.md#troubleshooting)
- Community support: Open issue on GitHub or check documentation

---

## Next Steps

### Production Hardening
1. **Change default passwords**: Root, travian user, test accounts
2. **Configure SSL**: Use `C:\xampp\apache\makecert.bat` or Let's Encrypt
3. **Enable firewall**: Restrict MySQL/PostgreSQL to localhost
4. **Disable dev features**: Set `APP_DEBUG=false`, `display_errors=Off`
5. **Set up backups**: Schedule `xampp-backup.ps1` via Task Scheduler

### Performance Tuning
```powershell
# Run automated tuning
.\scripts\xampp-performance-tune.ps1

# Or manual tuning:
# - PHP: Increase opcache.memory_consumption to 512M
# - MySQL: Set innodb_buffer_pool_size to 50-70% of RAM
# - Apache: Enable mod_deflate, mod_expires for compression/caching
```

### Monitoring Setup
```powershell
# Schedule health checks via Task Scheduler
$action = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-File C:\xampp\htdocs\scripts\xampp-healthcheck.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At 9am
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "XAMPP Health Check" -Description "Daily XAMPP health verification"
```

### Related Documentation
- **Full deployment guide**: [XAMPP-DEPLOYMENT-GUIDE.md](XAMPP-DEPLOYMENT-GUIDE.md) - Comprehensive 15-section guide
- **Scripts reference**: [XAMPP-SCRIPTS-REFERENCE.md](XAMPP-SCRIPTS-REFERENCE.md) - Detailed script documentation
- **Windows Docker alternative**: [WINDOWS-DEPLOYMENT-GUIDE.md](WINDOWS-DEPLOYMENT-GUIDE.md) - Docker-based deployment

---

## Quick Reference: Game Worlds

| World ID | Speed | Database | Port | Access URL |
|----------|-------|----------|------|------------|
| speed5m | 5,000,000x | travian_world_speed5m | 80 | http://localhost/?worldId=speed5m |
| speed500k | 500,000x | travian_world_speed500k | 80 | http://localhost/?worldId=speed500k |
| speed250k | 250,000x | travian_world_speed250k | 80 | http://localhost/?worldId=speed250k |
| speed125k | 125,000x | travian_world_speed125k | 80 | http://localhost/?worldId=speed125k |
| speed10k | 10,000x | travian_world_speed10k | 80 | http://localhost/?worldId=speed10k |
| demo | 1x | travian_world_demo | 80 | http://localhost/?worldId=demo |
| dev | 1x | travian_world_dev | 80 | http://localhost/?worldId=dev |
| testworld | 1x | travian_world_testworld | 80 | http://localhost/?worldId=testworld |

---

## Support & Resources

- **Documentation**: `C:\xampp\htdocs\docs\`
- **Logs**: `C:\xampp\apache\logs\`, `C:\xampp\mysql\data\`, `C:\xampp\pgsql\logs\`
- **XAMPP Community**: https://community.apachefriends.org/
- **PHP Manual**: https://www.php.net/manual/en/
- **MySQL Docs**: https://dev.mysql.com/doc/
- **PostgreSQL Docs**: https://www.postgresql.org/docs/

---

**Last Updated**: November 1, 2025  
**Version**: 1.0  
**Deployment Target**: XAMPP 8.2.12 + Windows 11
