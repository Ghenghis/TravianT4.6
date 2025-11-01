# TravianT4.6 XAMPP Deployment Package

**Complete Windows 11 / XAMPP 8.2 deployment solution for TravianT4.6**

---

## 🚀 Quick Start (5 Minutes)

```powershell
# 1. Install XAMPP 8.2.12 to C:\xampp
# 2. Copy this project to C:\xampp\htdocs
# 3. Run automated setup:

cd C:\xampp\htdocs\scripts
.\setup-xampp.ps1
.\validate-xampp.ps1

# 4. Open browser:
Start-Process "http://localhost"

# 5. Login with: testuser1 / test123
```

**That's it!** Your TravianT4.6 installation is ready.

---

## 📚 Documentation

### Choose Your Path

#### 🎯 **Beginner? Start Here**
→ [XAMPP-QUICKSTART.md](XAMPP-QUICKSTART.md) - 5-minute quick reference

#### 📋 **Want Step-by-Step Instructions?**
→ [todo-xampp.md](todo-xampp.md) - Interactive 10-phase checklist (recommended!)

#### 📖 **Need Complete Details?**
→ [XAMPP-DEPLOYMENT-GUIDE.md](XAMPP-DEPLOYMENT-GUIDE.md) - Comprehensive guide

#### ⚙️ **Maintaining the System?**
→ [XAMPP-SCRIPTS-REFERENCE.md](XAMPP-SCRIPTS-REFERENCE.md) - Script documentation

#### 📊 **Want an Overview?**
→ [XAMPP-DEPLOYMENT-SUMMARY.md](XAMPP-DEPLOYMENT-SUMMARY.md) - Package summary

---

## 🛠️ What's Included

### ✅ 5 PowerShell Automation Scripts
Located in `scripts/`:

| Script | Purpose | Time |
|--------|---------|------|
| `setup-xampp.ps1` | Creates databases, applies schema, inserts test users | 5-10 min |
| `validate-xampp.ps1` | Validates entire deployment (100+ checks) | 2-3 min |
| `xampp-backup.ps1` | 3-tier backup with compression | 5-15 min |
| `xampp-performance-tune.ps1` | Optimizes PHP, MySQL, Apache | 2-5 min |
| `xampp-healthcheck.ps1` | Monitors system health | Continuous |

### ✅ Complete Documentation
- 4 deployment guides (quick, detailed, scripts, TODO)
- Interactive checklist with phase-by-phase instructions
- Troubleshooting reference
- API endpoint catalog

### ✅ Configuration Files
- `.htaccess` - Apache routing and security
- Connection templates for 8 game worlds
- Database schema templates (TODOs for completion)

---

## 🎯 System Requirements

- **OS**: Windows 11 or Windows Server 2019+
- **XAMPP**: 8.2.12 (includes PHP 8.2, Apache 2.4, MySQL 8.0)
- **PostgreSQL**: 14+ addon for XAMPP
- **RAM**: 8GB minimum, 16GB recommended
- **Disk**: 50GB free space

---

## 🏗️ Architecture

### Dual Database System
- **PostgreSQL** → Global data (gameservers table)
- **MySQL** → 8 game worlds (90 tables each)

### 8 Game Worlds Included
1. speed10k (10,000x speed)
2. speed125k (125,000x speed)
3. speed250k (250,000x speed)
4. speed500k (500,000x speed)
5. speed5m (5,000,000x speed)
6. demo (1x speed - demonstrations)
7. dev (1x speed - development)
8. testworld (1x speed - testing)

### Test Accounts (12 per world)
- `testuser1` - `testuser10` (password: `test123`)
- `admin` (password: `admin123`)
- `demo` (password: `demo123`)

---

## ⚡ Installation Methods

### Method 1: Automated (Recommended)
```powershell
cd C:\xampp\htdocs\scripts
.\setup-xampp.ps1        # Creates everything
.\validate-xampp.ps1     # Validates everything
```
**Time**: 10-15 minutes

### Method 2: Interactive Checklist
Follow [todo-xampp.md](todo-xampp.md) - check off each step as you complete it.
**Time**: 4-6 hours (comprehensive setup with learning)

### Method 3: Manual
Follow [XAMPP-DEPLOYMENT-GUIDE.md](XAMPP-DEPLOYMENT-GUIDE.md) for complete manual setup.
**Time**: 6-8 hours

---

## 🔧 Common Commands

### Run Scripts
```powershell
cd C:\xampp\htdocs\scripts

# Setup (first time only)
.\setup-xampp.ps1

# Validate (anytime)
.\validate-xampp.ps1

# Backup (manual or scheduled)
.\xampp-backup.ps1

# Performance tune (after changes)
.\xampp-performance-tune.ps1

# Health check (one-time or continuous)
.\xampp-healthcheck.ps1
.\xampp-healthcheck.ps1 -Continuous -IntervalMinutes 5
```

### Service Control
```powershell
# Start services
C:\xampp\apache_start.bat
C:\xampp\mysql_start.bat
C:\xampp\pgsql\bin\pg_ctl.exe -D "C:\xampp\pgsql\data" start

# Stop services
C:\xampp\apache_stop.bat
C:\xampp\mysql_stop.bat
C:\xampp\pgsql\bin\pg_ctl.exe -D "C:\xampp\pgsql\data" stop
```

### Database Access
```powershell
# MySQL
C:\xampp\mysql\bin\mysql.exe -u travian -p"TravianDB2025!"

# PostgreSQL
$env:PGPASSWORD = "postgres"
C:\xampp\pgsql\bin\psql.exe -U postgres -d travian_global
```

---

## ✅ Validation

After setup, verify everything works:

```powershell
# Run validation script
cd C:\xampp\htdocs\scripts
.\validate-xampp.ps1 -Verbose

# Expected: 100% pass rate
```

### Quick Manual Tests
1. **Apache**: Browse to `http://localhost` → Angular app loads
2. **API**: Test `POST /v1/servers/loadServers` → 8 servers returned
3. **MySQL**: Login to phpMyAdmin → 8 databases listed
4. **PostgreSQL**: Check gameservers table → 8 rows
5. **Login**: Use `testuser1` / `test123` → Success

---

## 🚨 Troubleshooting

### Port 80 in use?
```powershell
# Check what's using port 80
netstat -ano | findstr ":80"

# Stop IIS if it's running
Stop-Service W3SVC
Set-Service W3SVC -StartupType Disabled
```

### Apache won't start?
```powershell
# Check syntax
C:\xampp\apache\bin\httpd.exe -t

# View error log
Get-Content C:\xampp\apache\logs\error.log -Tail 50
```

### MySQL issues?
```powershell
# Check MySQL error log
Get-Content C:\xampp\mysql\data\*.err -Tail 50

# Reset root password if needed
.\setup-xampp.ps1 -Force
```

### More Help
See [XAMPP-QUICKSTART.md](XAMPP-QUICKSTART.md#common-issues-quick-fix) for comprehensive troubleshooting.

---

## 📋 Before Production

### Security Checklist
- [ ] Change MySQL root password
- [ ] Change test user passwords
- [ ] Configure HTTPS/SSL
- [ ] Restrict database access
- [ ] Configure firewall rules
- [ ] Review Apache security headers
- [ ] Disable directory listing
- [ ] Remove phpMyAdmin or restrict access

### Performance Checklist
- [ ] Run `.\xampp-performance-tune.ps1`
- [ ] Configure OPcache
- [ ] Set up automated backups
- [ ] Configure log rotation
- [ ] Monitor disk space
- [ ] Set up health monitoring

### Maintenance Checklist
- [ ] Schedule daily backups (2 AM)
- [ ] Schedule health checks (hourly)
- [ ] Set up email alerts
- [ ] Document custom changes
- [ ] Create disaster recovery plan

---

## 📞 Getting Help

### Documentation
1. Check [XAMPP-QUICKSTART.md](XAMPP-QUICKSTART.md) for common issues
2. Review [XAMPP-DEPLOYMENT-GUIDE.md](XAMPP-DEPLOYMENT-GUIDE.md) for detailed info
3. Read [XAMPP-SCRIPTS-REFERENCE.md](XAMPP-SCRIPTS-REFERENCE.md) for script help

### Script Help
```powershell
Get-Help .\setup-xampp.ps1 -Detailed
Get-Help .\validate-xampp.ps1 -Examples
```

### Logs
```powershell
# Apache
Get-Content C:\xampp\apache\logs\error.log -Tail 50

# PHP
Get-Content C:\xampp\php\logs\php_error.log -Tail 50

# MySQL
Get-Content C:\xampp\mysql\data\*.err -Tail 50

# Health check
Get-Content C:\xampp\htdocs\logs\healthcheck_*.log -Tail 50
```

---

## 🎓 Learning Path

### 1. **First Time?**
Start here: [XAMPP-QUICKSTART.md](XAMPP-QUICKSTART.md)  
Then: [todo-xampp.md](todo-xampp.md) (follow step-by-step)

### 2. **Need Details?**
Read: [XAMPP-DEPLOYMENT-GUIDE.md](XAMPP-DEPLOYMENT-GUIDE.md)

### 3. **Maintaining?**
Reference: [XAMPP-SCRIPTS-REFERENCE.md](XAMPP-SCRIPTS-REFERENCE.md)  
And: [XAMPP-DEPLOYMENT-SUMMARY.md](XAMPP-DEPLOYMENT-SUMMARY.md)

### 4. **Developing?**
Check: `docs/completion/07-API-ENDPOINT-CATALOG.md` for API reference

---

## 🏆 Success Criteria

Your deployment is successful when:
- ✅ All services start (Apache, MySQL, PostgreSQL)
- ✅ Validation script passes 100%
- ✅ Angular app loads at `http://localhost`
- ✅ 8 servers listed on server selection
- ✅ Login works with test credentials
- ✅ API returns valid JSON
- ✅ No critical errors in logs

---

## 📦 File Structure

```
C:\xampp\htdocs\
├── XAMPP-README.md                 ← You are here
├── XAMPP-QUICKSTART.md             ← Quick reference
├── XAMPP-DEPLOYMENT-GUIDE.md       ← Full guide
├── XAMPP-SCRIPTS-REFERENCE.md      ← Script docs
├── XAMPP-DEPLOYMENT-SUMMARY.md     ← Package summary
├── todo-xampp.md                   ← Interactive checklist
├── .htaccess                       ← Apache routing
├── router.php                      ← PHP router
├── scripts/                        ← PowerShell scripts
│   ├── setup-xampp.ps1
│   ├── validate-xampp.ps1
│   ├── xampp-backup.ps1
│   ├── xampp-performance-tune.ps1
│   └── xampp-healthcheck.ps1
├── sections/
│   ├── api/                        ← API endpoints
│   └── servers/                    ← Game worlds
├── angularIndex/browser/           ← Angular frontend
└── database/mysql/                 ← Schema & test data
```

---

## 🚀 Next Steps

1. **Install XAMPP 8.2.12** from [apachefriends.org](https://www.apachefriends.org)
2. **Copy this project** to `C:\xampp\htdocs`
3. **Choose your method**:
   - Quick: Run `.\scripts\setup-xampp.ps1`
   - Detailed: Follow `todo-xampp.md`
4. **Validate**: Run `.\scripts\validate-xampp.ps1`
5. **Test**: Browse to `http://localhost`

---

**Ready to deploy?** Open [todo-xampp.md](todo-xampp.md) and start checking off items! 🎉

---

*TravianT4.6 XAMPP Deployment Package v2.0*  
*Generated: November 1, 2025*
