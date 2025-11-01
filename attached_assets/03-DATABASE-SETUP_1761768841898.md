# Database Setup and Configuration

## Overview

TravianT4.6 uses a multi-database architecture:
- **Global Database**: User registration, server list, global configuration
- **Per-World Databases**: Game data for each world (users, villages, alliances, etc.)

This guide covers MySQL setup for both development and production environments.

## Database Architecture

```
┌─────────────────────────────────────────────────────┐
│          Global MySQL Database                      │
│  - gameServers (server list)                        │
│  - activation (registrations)                       │
│  - configurations (settings)                        │
│  - passwordRecovery                                 │
│  - mailserver (email queue)                         │
│  - email_blacklist                                  │
│  - banIP                                            │
└─────────────────────────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          │               │               │
┌─────────▼───────┐ ┌─────▼──────┐ ┌─────▼──────┐
│ testworld DB    │ │ demo DB    │ │ world3 DB  │
│ - users         │ │ - users    │ │ - users    │
│ - villages      │ │ - villages │ │ - villages │
│ - alliances     │ │ - alliances│ │ - alliances│
│ - marketplace   │ │ - ...      │ │ - ...      │
│ - (90+ tables)  │ │            │ │            │
└─────────────────┘ └────────────┘ └────────────┘
```

## Step 1: Access MySQL Container

### Connect to MySQL

```bash
# Using Docker Compose
docker-compose exec mysql mysql -u root -p
# Enter root password from .env file

# Or using MySQL client from Windows
mysql -h 127.0.0.1 -P 3306 -u root -p
```

## Step 2: Create Global Database

The global database is created automatically by Docker Compose, but you can verify:

```sql
-- Show databases
SHOW DATABASES;

-- Use global database
USE travian_global;

-- Show tables (should be empty initially)
SHOW TABLES;
```

## Step 3: Import Global Schema

### Prepare Global Schema File

Create `docker/mysql/init/01-global-schema.sql`:

```sql
-- TravianT4.6 Global Database Schema
-- This handles user registration, server list, and global configuration

CREATE DATABASE IF NOT EXISTS travian_global CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE travian_global;

-- Game Servers Table
CREATE TABLE IF NOT EXISTS `gameServers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `worldId` VARCHAR(50) NOT NULL,
  `speed` INT(11) NOT NULL DEFAULT 1,
  `name` VARCHAR(255) NOT NULL,
  `version` VARCHAR(10) DEFAULT 'T4.6',
  `gameWorldUrl` VARCHAR(255) NOT NULL,
  `startTime` INT(10) UNSIGNED NOT NULL,
  `roundLength` INT(11) NOT NULL DEFAULT 365,
  `finished` TINYINT(1) NOT NULL DEFAULT 0,
  `registerClosed` TINYINT(1) NOT NULL DEFAULT 0,
  `activation` TINYINT(1) NOT NULL DEFAULT 1,
  `preregistration_key_only` TINYINT(1) NOT NULL DEFAULT 0,
  `hidden` TINYINT(1) NOT NULL DEFAULT 0,
  `promoted` TINYINT(1) DEFAULT 0,
  `configFileLocation` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worldId` (`worldId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Activation Table
CREATE TABLE IF NOT EXISTS `activation` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `wid` INT(11) UNSIGNED NOT NULL,
  `name` VARCHAR(20) NOT NULL,
  `password` VARCHAR(40) NOT NULL,
  `email` VARCHAR(99) NOT NULL DEFAULT '',
  `token` VARCHAR(32) NOT NULL,
  `refUid` INT(11) NOT NULL DEFAULT 0,
  `time` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `reminded` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `wid` (`wid`),
  KEY `name` (`name`),
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Global Configurations
CREATE TABLE IF NOT EXISTS `configurations` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IP Ban List
CREATE TABLE IF NOT EXISTS `banIP` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `reason` TEXT,
  `banned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Blacklist
CREATE TABLE IF NOT EXISTS `email_blacklist` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `domain` VARCHAR(255) DEFAULT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mail Server Queue
CREATE TABLE IF NOT EXISTS `mailserver` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `to_email` VARCHAR(255) NOT NULL,
  `from_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `body` TEXT NOT NULL,
  `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  `attempts` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password Recovery
CREATE TABLE IF NOT EXISTS `passwordRecovery` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT(11) UNSIGNED NOT NULL,
  `wid` INT(11) UNSIGNED NOT NULL,
  `recoveryCode` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `wid` (`wid`),
  KEY `recoveryCode` (`recoveryCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Sample Game Servers
INSERT INTO `gameServers` (`worldId`, `speed`, `name`, `gameWorldUrl`, `startTime`, `roundLength`, `finished`, `registerClosed`, `activation`, `configFileLocation`)
VALUES
  ('testworld', 100, 'Test Server 100x', 'http://testworld.travian.local/', UNIX_TIMESTAMP(), 365, 0, 0, 1, '/var/www/html/sections/servers/testworld/include/connection.php'),
  ('demo', 5, 'Demo Server 5x', 'http://demo.travian.local/', UNIX_TIMESTAMP(), 180, 0, 0, 1, '/var/www/html/sections/servers/demo/include/connection.php');

-- Insert Default Configurations
INSERT INTO `configurations` (`key`, `value`, `description`)
VALUES
  ('site_title', 'Travian Legends', 'Website title'),
  ('registration_open', '1', 'Enable user registration'),
  ('maintenance_mode', '0', 'Enable maintenance mode'),
  ('recaptcha_enabled', '0', 'Enable reCAPTCHA');
```

### Import Global Schema

```bash
# Method 1: Using Docker Compose (automatic on first start)
docker-compose up -d mysql

# Method 2: Manual import
docker-compose exec -T mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} travian_global < docker/mysql/init/01-global-schema.sql

# Method 3: From MySQL Workbench
# File → Run SQL Script → Select 01-global-schema.sql
```

## Step 4: Create Per-World Databases

### Extract Game World Schema

The complete schema is in `main_script/include/schema/T4.4.sql` (90+ tables).

Create a script to import it:

Create `docker/mysql/init/02-create-world-databases.sh`:

```bash
#!/bin/bash
# Create databases for each game world

# Read environment variables
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}"

# Create testworld database
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "CREATE DATABASE IF NOT EXISTS travian_testworld CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Create demo database
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "CREATE DATABASE IF NOT EXISTS travian_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "Game world databases created successfully"
```

Make it executable:

```bash
chmod +x docker/mysql/init/02-create-world-databases.sh
```

### Import Game World Schema

```bash
# Copy the schema file to Docker MySQL init directory
cp main_script/include/schema/T4.4.sql docker/mysql/init/03-world-schema.sql

# Import for testworld
docker-compose exec -T mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} travian_testworld < docker/mysql/init/03-world-schema.sql

# Import for demo
docker-compose exec -T mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} travian_demo < docker/mysql/init/03-world-schema.sql
```

## Step 5: Create Database Users

### Create Application User

```sql
-- Connect to MySQL as root
USE mysql;

-- Create travian user with proper permissions
CREATE USER IF NOT EXISTS 'travian_user'@'%' IDENTIFIED BY 'your_secure_password';

-- Grant permissions on global database
GRANT ALL PRIVILEGES ON travian_global.* TO 'travian_user'@'%';

-- Grant permissions on all game world databases
GRANT ALL PRIVILEGES ON travian_testworld.* TO 'travian_user'@'%';
GRANT ALL PRIVILEGES ON travian_demo.* TO 'travian_user'@'%';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify user
SELECT user, host FROM mysql.user WHERE user = 'travian_user';
```

## Step 6: Configure Connection Files

### Create Game World Connection File

Create `sections/servers/testworld/include/connection.php`:

```php
<?php
global $connection;
$connection = [
    'speed' => '100',
    'round_length' => '365',
    'worldId' => 'testworld',
    'secure_hash_code' => md5(uniqid(rand(), true)),
    'title' => 'Test Server 100x',
    'gameWorldUrl' => 'http://testworld.travian.local/',
    'serverName' => 'Test Server',
    'auto_reinstall' => '0',
    'auto_reinstall_start_after' => '86400',
    'engine_filename' => 'testworld.service',
    'database' => [
        'hostname' => getenv('DB_HOST') ?: 'mysql',
        'username' => getenv('DB_USERNAME') ?: 'travian_user',
        'password' => getenv('DB_PASSWORD') ?: 'your_secure_password',
        'database' => 'travian_testworld',
        'charset' => 'utf8mb4',
    ],
];
```

Create `sections/servers/demo/include/connection.php`:

```php
<?php
global $connection;
$connection = [
    'speed' => '5',
    'round_length' => '180',
    'worldId' => 'demo',
    'secure_hash_code' => md5(uniqid(rand(), true)),
    'title' => 'Demo Server 5x',
    'gameWorldUrl' => 'http://demo.travian.local/',
    'serverName' => 'Demo Server',
    'auto_reinstall' => '0',
    'auto_reinstall_start_after' => '86400',
    'engine_filename' => 'demo.service',
    'database' => [
        'hostname' => getenv('DB_HOST') ?: 'mysql',
        'username' => getenv('DB_USERNAME') ?: 'travian_user',
        'password' => getenv('DB_PASSWORD') ?: 'your_secure_password',
        'database' => 'travian_demo',
        'charset' => 'utf8mb4',
    ],
];
```

## Step 7: Update Global Config

Update `sections/globalConfig.php` to use Docker environment:

```php
<?php
// Database configuration for global database
$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'mysql',
    'port' => getenv('DB_PORT') ?: '3306',
    'username' => getenv('DB_USERNAME') ?: 'travian_user',
    'password' => getenv('DB_PASSWORD') ?: 'your_secure_password',
    'database' => getenv('DB_DATABASE') ?: 'travian_global',
    'charset' => 'utf8mb4',
];

// Redis configuration
$redisConfig = [
    'host' => getenv('REDIS_HOST') ?: 'redis',
    'port' => getenv('REDIS_PORT') ?: 6379,
];

// Application configuration
define('DOMAIN', getenv('APP_URL') ?: 'http://localhost');
define('DEBUG_MODE', getenv('APP_DEBUG') === 'true');
```

## Step 8: Verify Database Setup

### Check Global Database

```sql
-- Connect to global database
USE travian_global;

-- Verify tables
SHOW TABLES;
-- Should show: gameServers, activation, configurations, etc.

-- Check game servers
SELECT id, worldId, name, speed FROM gameServers;

-- Check configurations
SELECT * FROM configurations;
```

### Check World Database

```sql
-- Connect to world database
USE travian_testworld;

-- Count tables (should be 90+)
SELECT COUNT(*) as table_count 
FROM information_schema.tables 
WHERE table_schema = 'travian_testworld';

-- Check critical tables exist
SHOW TABLES LIKE 'users';
SHOW TABLES LIKE 'villages';
SHOW TABLES LIKE 'alliances';
```

## Step 9: Database Backup Strategy

### Create Backup Script

Create `scripts/backup-databases.sh`:

```bash
#!/bin/bash
# Backup all Travian databases

BACKUP_DIR="./backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}"

# Create backup directory
mkdir -p ${BACKUP_DIR}

# Backup global database
echo "Backing up global database..."
docker-compose exec -T mysql mysqldump -u root -p"${MYSQL_ROOT_PASSWORD}" \
    travian_global > "${BACKUP_DIR}/global_${TIMESTAMP}.sql"

# Backup testworld database
echo "Backing up testworld database..."
docker-compose exec -T mysql mysqldump -u root -p"${MYSQL_ROOT_PASSWORD}" \
    travian_testworld > "${BACKUP_DIR}/testworld_${TIMESTAMP}.sql"

# Backup demo database
echo "Backing up demo database..."
docker-compose exec -T mysql mysqldump -u root -p"${MYSQL_ROOT_PASSWORD}" \
    travian_demo > "${BACKUP_DIR}/demo_${TIMESTAMP}.sql"

# Compress backups
echo "Compressing backups..."
tar -czf "${BACKUP_DIR}/travian_backup_${TIMESTAMP}.tar.gz" \
    "${BACKUP_DIR}/"*_${TIMESTAMP}.sql

# Remove uncompressed files
rm "${BACKUP_DIR}/"*_${TIMESTAMP}.sql

echo "Backup completed: ${BACKUP_DIR}/travian_backup_${TIMESTAMP}.tar.gz"

# Keep only last 7 days of backups
find ${BACKUP_DIR} -name "travian_backup_*.tar.gz" -mtime +7 -delete
```

Make executable and run:

```bash
chmod +x scripts/backup-databases.sh
./scripts/backup-databases.sh
```

### Restore from Backup

```bash
# Extract backup
tar -xzf backups/travian_backup_20241028_120000.tar.gz -C backups/

# Restore global database
docker-compose exec -T mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} \
    travian_global < backups/global_20241028_120000.sql

# Restore world database
docker-compose exec -T mysql mysql -u root -p${MYSQL_ROOT_PASSWORD} \
    travian_testworld < backups/testworld_20241028_120000.sql
```

## Step 10: Database Optimization

### Create Optimization Script

Create `docker/mysql/conf.d/custom.cnf`:

```ini
[mysqld]
# Performance Settings
max_connections = 500
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query Cache (MySQL 5.7 only)
# query_cache_type = 1
# query_cache_size = 128M

# Character Set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2

# Binary Logging (for replication)
server-id = 1
log_bin = /var/log/mysql/mysql-bin.log
binlog_format = ROW
expire_logs_days = 7
```

Restart MySQL to apply:

```bash
docker-compose restart mysql
```

## Troubleshooting

### Can't Connect to MySQL

```bash
# Check if MySQL is running
docker-compose ps mysql

# Check MySQL logs
docker-compose logs mysql

# Test connection
docker-compose exec mysql mysql -u root -p -e "SELECT 1"
```

### Schema Import Errors

```bash
# Check for syntax errors
docker-compose exec mysql mysql -u root -p --force < schema.sql 2>&1 | grep ERROR

# Import with verbose output
docker-compose exec mysql mysql -u root -p -v < schema.sql
```

### Slow Queries

```sql
-- Find slow queries
SELECT * FROM mysql.slow_log ORDER BY query_time DESC LIMIT 10;

-- Analyze table
ANALYZE TABLE users;

-- Add missing indexes
SHOW INDEX FROM users;
```

## Next Steps

Continue to [04-APPLICATION-CONFIGURATION.md](04-APPLICATION-CONFIGURATION.md) for application setup and configuration.
