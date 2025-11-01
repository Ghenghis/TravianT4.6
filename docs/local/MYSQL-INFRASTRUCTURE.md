# MySQL 8.0 Infrastructure Setup

## 🎯 Purpose

This guide establishes a robust MySQL 8.0 database infrastructure using Docker. By the end, you'll have:

- ✅ MySQL 8.0 container running on **port 3306** (standard, not 3307)
- ✅ Persistent data volumes for database storage
- ✅ Secure root and application user accounts
- ✅ Connection verified from WSL2 and Docker containers
- ✅ Database ready for schema import

**Estimated Time:** 1-2 hours

---

## 📋 Prerequisites

Before starting, ensure you've completed:

- ✅ Guide 2: WINDOWS-WSL2-DOCKER.md
- ✅ Guide 3: PROJECT-BOOTSTRAP.md
- ✅ Docker Desktop is running
- ✅ `.env` file created with database credentials

---

## Section 1: Understanding the Port 3306 vs 3307 Issue

### ❌ What Went Wrong Previously

**The Problem:**
```yaml
services:
  mysql:
    ports:
      - "3307:3306"
```

**Why this causes confusion:**
- MySQL container listens on port 3306 *inside* Docker
- Port 3307 is exposed to Windows host
- Connection strings get confused: "Do I use 3306 or 3307?"
- Different connection strings needed for different contexts
- Documentation becomes confusing
- Tools expect 3306 by default

### ✅ Correct Approach

```yaml
services:
  mysql:
    ports:
      - "3306:3306"
```

**Why this works better:**
- Consistent port 3306 everywhere
- Standard MySQL port
- All tools and libraries work without configuration
- Clear, simple connection strings
- Industry best practice

**"But I already have MySQL on port 3306 on Windows!"**

**Solution A: Stop Windows MySQL Service**
```powershell
Stop-Service -Name "MySQL80"
Set-Service -Name "MySQL80" -StartupType Disabled
```

**Solution B: Use Docker Network Only**
Don't expose the port to Windows at all:
```yaml
services:
  mysql:
    expose:
      - "3306"
```

This allows Docker containers to connect, but not Windows directly.

---

## Section 2: Create MySQL Docker Container

### Step 2.1: Create Docker Network

First, create a dedicated network for all Travian services:

```bash
docker network create travian-network
```

**What this does:**
- Creates isolated network for Travian containers
- Containers can communicate by name (mysql, redis, php, etc.)
- Better security than default bridge network

**Verify:**
```bash
docker network ls | grep travian
```

### Step 2.2: Create Data Volume

```bash
docker volume create travian-mysql-data
```

**Why use volumes?**
- Data persists even if container is deleted
- Better performance than bind mounts
- Managed by Docker
- Easy to back up

**Verify:**
```bash
docker volume ls | grep travian
```

### Step 2.3: Run MySQL 8.0 Container

Load your environment variables:

```bash
cd ~/Projects/TravianT4.6
source .env
```

Run MySQL container:

```bash
docker run -d \
  --name travian-mysql \
  --network travian-network \
  -p 3306:3306 \
  -e MYSQL_ROOT_PASSWORD="${DB_ROOT_PASSWORD}" \
  -e MYSQL_DATABASE="${DB_GLOBAL}" \
  -e MYSQL_USER="${DB_USER}" \
  -e MYSQL_PASSWORD="${DB_PASSWORD}" \
  -v travian-mysql-data:/var/lib/mysql \
  --restart unless-stopped \
  mysql:8.0 \
  --default-authentication-plugin=mysql_native_password \
  --character-set-server=utf8mb4 \
  --collation-server=utf8mb4_unicode_ci \
  --max-connections=500 \
  --max-allowed-packet=256M
```

**Command breakdown:**

| Flag | Purpose |
|------|---------|
| `-d` | Run in background (detached) |
| `--name travian-mysql` | Container name for easy reference |
| `--network travian-network` | Connect to our custom network |
| `-p 3306:3306` | Expose port 3306 to Windows host |
| `-e MYSQL_ROOT_PASSWORD` | Set root password from .env |
| `-e MYSQL_DATABASE` | Create travian_global database on startup |
| `-e MYSQL_USER` | Create application user |
| `-e MYSQL_PASSWORD` | Set application user password |
| `-v travian-mysql-data:/var/lib/mysql` | Mount persistent volume |
| `--restart unless-stopped` | Auto-restart on Docker startup |
| `--default-authentication-plugin` | Use native password (PHP compatibility) |
| `--character-set-server=utf8mb4` | Full Unicode support |
| `--max-connections=500` | Allow 500 simultaneous connections |
| `--max-allowed-packet=256M` | Allow large queries/imports |

**⏱️ Startup time:** 15-30 seconds

### Step 2.4: Verify Container is Running

```bash
docker ps | grep travian-mysql
```

**Expected output:**
```
CONTAINER ID   IMAGE       COMMAND                  CREATED          STATUS          PORTS                    NAMES
abc123def456   mysql:8.0   "docker-entrypoint.s…"   30 seconds ago   Up 28 seconds   0.0.0.0:3306->3306/tcp   travian-mysql
```

**✅ Success criteria:**
- STATUS shows "Up"
- PORTS shows `0.0.0.0:3306->3306/tcp`

**❌ If container is not running:**

```bash
docker logs travian-mysql
```

Check for errors in the logs.

---

## Section 3: Verify Database Connectivity

### Step 3.1: Wait for MySQL to Initialize

MySQL takes 15-30 seconds to fully initialize on first run.

Check initialization status:

```bash
docker logs travian-mysql | grep "ready for connections"
```

**Expected output:**
```
[Server] /usr/sbin/mysqld: ready for connections. Version: '8.0.xx'  socket: '/var/run/mysqld/mysqld.sock'  port: 3306  MySQL Community Server - GPL.
```

**⏳ If not ready yet, wait 30 seconds and check again.**

### Step 3.2: Connect from WSL2 Terminal

Install MySQL client if not already installed:

```bash
sudo apt update
sudo apt install -y mysql-client
```

Connect to MySQL:

```bash
mysql -h 127.0.0.1 -P 3306 -u $DB_USER -p$DB_PASSWORD
```

**⚠️ Note:** No space between `-p` and password!

**Expected output:**
```
Welcome to the MySQL monitor.  Commands end with ; or \g.
Your MySQL connection id is 8
Server version: 8.0.xx MySQL Community Server - GPL

Copyright (c) 2000, 2024, Oracle and/or its affiliates.

Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

mysql>
```

**Test queries:**

```sql
SHOW DATABASES;
```

**Expected output:**
```
+--------------------+
| Database           |
+--------------------+
| information_schema |
| travian_global     |
+--------------------+
```

**Exit MySQL:**
```sql
exit;
```

**✅ Success:** You can connect to MySQL from WSL2!

### Step 3.3: Connect from Docker Container

Test connectivity from another container (simulating PHP):

```bash
docker run --rm --network travian-network mysql:8.0 \
  mysql -h mysql -u $DB_USER -p$DB_PASSWORD -e "SHOW DATABASES;"
```

**❌ If this fails with "Unknown MySQL server host 'mysql'":**

The container name resolution isn't working. Fix:

```bash
docker network connect travian-network travian-mysql
```

**⚠️ Note:** Use `-h mysql` not `-h travian-mysql` because we'll use service name `mysql` in docker-compose.yml.

Actually, let's fix this now by creating an alias:

```bash
docker network disconnect travian-network travian-mysql
docker network connect travian-network travian-mysql --alias mysql
```

**Try again:**
```bash
docker run --rm --network travian-network mysql:8.0 \
  mysql -h mysql -u $DB_USER -p$DB_PASSWORD -e "SHOW DATABASES;"
```

**Expected output:**
```
+--------------------+
| Database           |
+--------------------+
| information_schema |
| travian_global     |
+--------------------+
```

**✅ Success:** Docker containers can connect using hostname `mysql`!

---

## Section 4: Configure Database Users and Permissions

### Step 4.1: Connect as Root

```bash
mysql -h 127.0.0.1 -P 3306 -u root -p$DB_ROOT_PASSWORD
```

### Step 4.2: Verify Application User

```sql
SELECT User, Host FROM mysql.user WHERE User = 'travian_admin';
```

**Expected output:**
```
+---------------+------+
| User          | Host |
+---------------+------+
| travian_admin | %    |
+---------------+------+
```

**`Host = %` means:** User can connect from any host (required for Docker).

### Step 4.3: Grant Necessary Permissions

```sql
GRANT ALL PRIVILEGES ON `travian_%`.* TO 'travian_admin'@'%';
FLUSH PRIVILEGES;
```

**What this does:**
- Grants ALL permissions on any database starting with `travian_`
- Includes: `travian_global`, `travian_testworld`, `travian_demo`, etc.
- Allows creating/dropping databases, tables, users

**Verify permissions:**

```sql
SHOW GRANTS FOR 'travian_admin'@'%';
```

**Expected output:**
```
+---------------------------------------------------------------------+
| Grants for travian_admin@%                                          |
+---------------------------------------------------------------------+
| GRANT USAGE ON *.* TO `travian_admin`@`%`                          |
| GRANT ALL PRIVILEGES ON `travian_global`.* TO `travian_admin`@`%`  |
| GRANT ALL PRIVILEGES ON `travian_%`.* TO `travian_admin`@`%`       |
+---------------------------------------------------------------------+
```

**Exit MySQL:**
```sql
exit;
```

---

## Section 5: MySQL Configuration Tuning

### Step 5.1: Create Custom MySQL Configuration

Create a custom config file for MySQL:

```bash
mkdir -p ~/Projects/TravianT4.6/docker/mysql
nano ~/Projects/TravianT4.6/docker/mysql/custom.cnf
```

Paste:

```ini
[mysqld]
# Character Set & Collation
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci

# Authentication
default-authentication-plugin=mysql_native_password

# Connection Settings
max_connections=500
max_allowed_packet=256M
connect_timeout=10
wait_timeout=600
interactive_timeout=600

# Performance Settings
innodb_buffer_pool_size=1G
innodb_log_file_size=256M
innodb_flush_log_at_trx_commit=2
innodb_flush_method=O_DIRECT

# Query Cache (disabled in MySQL 8.0, but kept for reference)
# query_cache_type=1
# query_cache_size=64M

# Logging
general_log=0
slow_query_log=1
slow_query_log_file=/var/log/mysql/slow-query.log
long_query_time=2

# Binary Logging (for replication/backups)
log_bin=mysql-bin
expire_logs_days=7
max_binlog_size=100M

# SQL Mode
sql_mode=STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION

[client]
default-character-set=utf8mb4
```

Save and exit.

### Step 5.2: Apply Custom Configuration

Stop the current container:

```bash
docker stop travian-mysql
docker rm travian-mysql
```

Recreate with custom config:

```bash
cd ~/Projects/TravianT4.6
source .env

docker run -d \
  --name travian-mysql \
  --network travian-network \
  --network-alias mysql \
  -p 3306:3306 \
  -e MYSQL_ROOT_PASSWORD="${DB_ROOT_PASSWORD}" \
  -e MYSQL_DATABASE="${DB_GLOBAL}" \
  -e MYSQL_USER="${DB_USER}" \
  -e MYSQL_PASSWORD="${DB_PASSWORD}" \
  -v travian-mysql-data:/var/lib/mysql \
  -v $(pwd)/docker/mysql/custom.cnf:/etc/mysql/conf.d/custom.cnf:ro \
  --restart unless-stopped \
  mysql:8.0
```

**New addition:** `-v $(pwd)/docker/mysql/custom.cnf:/etc/mysql/conf.d/custom.cnf:ro`

This mounts your custom config into the container as **read-only** (`:ro`).

### Step 5.3: Verify Custom Configuration

Wait 30 seconds for MySQL to start, then:

```bash
docker exec -it travian-mysql mysql -u root -p$DB_ROOT_PASSWORD \
  -e "SHOW VARIABLES LIKE 'max_connections';"
```

**Expected output:**
```
+-----------------+-------+
| Variable_name   | Value |
+-----------------+-------+
| max_connections | 500   |
+-----------------+-------+
```

**✅ Success:** Custom configuration is loaded!

---

## Section 6: Database Health Checks

### Step 6.1: Create Health Check Script

```bash
nano ~/Projects/TravianT4.6/scripts/mysql-health.sh
```

Paste:

```bash
#!/bin/bash

echo "=================================="
echo "MySQL Health Check"
echo "=================================="

source .env

echo "Checking container status..."
if docker ps | grep -q travian-mysql; then
    echo "✅ Container is running"
else
    echo "❌ Container is not running"
    exit 1
fi

echo ""
echo "Checking MySQL connectivity..."
if mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1;" &>/dev/null; then
    echo "✅ MySQL is accepting connections"
else
    echo "❌ Cannot connect to MySQL"
    exit 1
fi

echo ""
echo "Checking database existence..."
DBS=$(mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" -e "SHOW DATABASES;" | grep travian_ | wc -l)
echo "✅ Found $DBS travian_* database(s)"

echo ""
echo "Checking MySQL version..."
VERSION=$(mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT VERSION();" | tail -n 1)
echo "✅ MySQL version: $VERSION"

echo ""
echo "Checking disk usage..."
docker exec travian-mysql df -h /var/lib/mysql | tail -n 1

echo ""
echo "Checking active connections..."
CONNECTIONS=$(mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" -e "SHOW STATUS LIKE 'Threads_connected';" | tail -n 1 | awk '{print $2}')
echo "✅ Active connections: $CONNECTIONS"

echo ""
echo "=================================="
echo "✅ MySQL is healthy!"
echo "=================================="
```

Make executable:

```bash
chmod +x scripts/mysql-health.sh
```

### Step 6.2: Run Health Check

```bash
cd ~/Projects/TravianT4.6
./scripts/mysql-health.sh
```

**Expected output:**
```
==================================
MySQL Health Check
==================================
Checking container status...
✅ Container is running

Checking MySQL connectivity...
✅ MySQL is accepting connections

Checking database existence...
✅ Found 1 travian_* database(s)

Checking MySQL version...
✅ MySQL version: 8.0.xx

Checking disk usage...
/dev/sda1       100G   5.2G   90G   6% /var/lib/mysql

Checking active connections...
✅ Active connections: 2

==================================
✅ MySQL is healthy!
==================================
```

---

## Section 7: Backup and Restore Strategy

### Step 7.1: Create Backup Script

```bash
nano ~/Projects/TravianT4.6/scripts/mysql-backup.sh
```

Paste:

```bash
#!/bin/bash

source .env

BACKUP_DIR="./storage/backups/mysql"
mkdir -p "$BACKUP_DIR"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/travian_backup_$TIMESTAMP.sql.gz"

echo "Creating MySQL backup..."
echo "Backup file: $BACKUP_FILE"

docker exec travian-mysql mysqldump \
  -u root -p"$DB_ROOT_PASSWORD" \
  --all-databases \
  --single-transaction \
  --quick \
  --lock-tables=false \
  --routines \
  --triggers \
  --events \
  | gzip > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo "✅ Backup completed successfully!"
    echo "📦 Size: $SIZE"
    echo "📁 Location: $BACKUP_FILE"
else
    echo "❌ Backup failed!"
    exit 1
fi

echo ""
echo "Keeping last 7 backups, deleting older ones..."
cd "$BACKUP_DIR"
ls -t travian_backup_*.sql.gz | tail -n +8 | xargs -r rm
echo "✅ Cleanup complete"
```

Make executable:

```bash
chmod +x scripts/mysql-backup.sh
```

### Step 7.2: Create Restore Script

```bash
nano ~/Projects/TravianT4.6/scripts/mysql-restore.sh
```

Paste:

```bash
#!/bin/bash

source .env

if [ -z "$1" ]; then
    echo "Usage: ./scripts/mysql-restore.sh <backup_file.sql.gz>"
    echo ""
    echo "Available backups:"
    ls -lh ./storage/backups/mysql/travian_backup_*.sql.gz 2>/dev/null
    exit 1
fi

BACKUP_FILE="$1"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Backup file not found: $BACKUP_FILE"
    exit 1
fi

echo "⚠️  WARNING: This will OVERWRITE all databases!"
echo "Backup file: $BACKUP_FILE"
echo ""
read -p "Are you sure? (type 'yes' to continue): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "❌ Restore cancelled"
    exit 0
fi

echo "Restoring from backup..."

gunzip < "$BACKUP_FILE" | docker exec -i travian-mysql mysql -u root -p"$DB_ROOT_PASSWORD"

if [ $? -eq 0 ]; then
    echo "✅ Restore completed successfully!"
else
    echo "❌ Restore failed!"
    exit 1
fi
```

Make executable:

```bash
chmod +x scripts/mysql-restore.sh
```

### Step 7.3: Test Backup

```bash
cd ~/Projects/TravianT4.6
./scripts/mysql-backup.sh
```

**Expected output:**
```
Creating MySQL backup...
Backup file: ./storage/backups/mysql/travian_backup_20251029_143022.sql.gz
✅ Backup completed successfully!
📦 Size: 1.2M
📁 Location: ./storage/backups/mysql/travian_backup_20251029_143022.sql.gz

Keeping last 7 backups, deleting older ones...
✅ Cleanup complete
```

---

## Section 8: Troubleshooting

### Issue 1: "Can't connect to MySQL server on '127.0.0.1' (10061)"

**Symptoms:**
```
ERROR 2003 (HY000): Can't connect to MySQL server on '127.0.0.1' (10061)
```

**Solutions:**

**Solution A: Check container is running**
```bash
docker ps | grep travian-mysql
```

If not running, start it:
```bash
docker start travian-mysql
```

**Solution B: Check port binding**
```bash
docker port travian-mysql
```

Expected output: `3306/tcp -> 0.0.0.0:3306`

If missing, recreate container with `-p 3306:3306`.

**Solution C: Wait for MySQL to finish initializing**
```bash
docker logs travian-mysql | tail -20
```

Look for "ready for connections" message.

### Issue 2: "Access denied for user 'travian_admin'@'...' (using password: YES)"

**Symptoms:**
```
ERROR 1045 (28000): Access denied for user 'travian_admin'@'172.18.0.3' (using password: YES)
```

**Solutions:**

**Solution A: Verify password in .env**
```bash
echo $DB_PASSWORD
```

Ensure it matches what you set during container creation.

**Solution B: Recreate user**

Connect as root:
```bash
mysql -h 127.0.0.1 -P 3306 -u root -p$DB_ROOT_PASSWORD
```

Drop and recreate user:
```sql
DROP USER IF EXISTS 'travian_admin'@'%';
CREATE USER 'travian_admin'@'%' IDENTIFIED BY 'YourPasswordHere';
GRANT ALL PRIVILEGES ON `travian_%`.* TO 'travian_admin'@'%';
FLUSH PRIVILEGES;
exit;
```

### Issue 3: Container keeps restarting

**Check logs:**
```bash
docker logs travian-mysql
```

**Common causes:**

**Cause A: Invalid configuration file**
Check syntax in `docker/mysql/custom.cnf`.

**Cause B: Insufficient memory**
Reduce `innodb_buffer_pool_size` in custom.cnf.

**Cause C: Corrupted data volume**
Last resort - delete volume and start fresh:
```bash
docker stop travian-mysql
docker rm travian-mysql
docker volume rm travian-mysql-data
```

Then recreate (you'll lose all data).

---

## ✅ Verification Checklist

Before proceeding to the next guide, verify:

- [ ] MySQL 8.0 container running (`docker ps`)
- [ ] Container named `travian-mysql`
- [ ] Port 3306 exposed (`0.0.0.0:3306->3306/tcp`)
- [ ] Can connect from WSL2 terminal (`mysql -h 127.0.0.1 ...`)
- [ ] Can connect from Docker network (`docker run ... mysql`)
- [ ] `travian_global` database exists
- [ ] `travian_admin` user has ALL privileges on `travian_%` databases
- [ ] Custom MySQL configuration loaded (max_connections=500)
- [ ] Health check script passes (`./scripts/mysql-health.sh`)
- [ ] Backup script works (`./scripts/mysql-backup.sh`)
- [ ] Data persists after container restart (`docker restart travian-mysql`)

**Full verification command:**

```bash
cd ~/Projects/TravianT4.6
source .env
./scripts/mysql-health.sh
docker exec travian-mysql mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "SHOW DATABASES;" | grep travian_global
```

**Expected:** Health check passes, travian_global database listed.

---

## 🚀 Next Steps

**Excellent!** You now have a production-ready MySQL 8.0 infrastructure running on the standard port 3306.

**Next guide:** [GLOBAL-SCHEMA-RESTORE.md](./GLOBAL-SCHEMA-RESTORE.md)

This will walk you through:
- Importing the complete Travian-Solo schema
- Fixing the missing `activation.used` column
- Adding sample game server data
- Verifying schema integrity

---

**Last Updated:** October 29, 2025  
**Estimated Completion Time:** 1-2 hours  
**Difficulty:** Intermediate
