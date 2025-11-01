# Fix 01: Convert From PostgreSQL to MySQL

## Problem Statement

The codebase currently has a **CRITICAL architecture mismatch**:
- Global database is PostgreSQL (Replit's default)
- Game world code expects MySQL
- This blocks ALL login/gameplay functionality

## What's Wrong (File by File)

### ❌ Broken File 1: `sections/api/include/Database/DB.php`

**Current Code** (PostgreSQL):
```php
$dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';sslmode=require';
```

**Problem**: Hardcoded for PostgreSQL, incompatible with MySQL game worlds

### ❌ Broken File 2: `sections/globalConfig.php`

**Current Code**:
```php
define('DB_HOST', getenv('PGHOST'));
define('DB_PORT', getenv('PGPORT'));
define('DB_USERNAME', getenv('PGUSER'));
define('DB_PASSWORD', getenv('PGPASSWORD'));
define('DB_DATABASE', getenv('PGDATABASE'));
```

**Problem**: Uses PostgreSQL environment variables (PGHOST, PGUSER, etc.)

### ❌ Broken File 3: `sections/api/include/Database/ServerDB.php`

**Current Code**: Expects MySQL but global DB is PostgreSQL

**Problem**: Architecture conflict - can't connect to game worlds

---

## Solution: Convert Entire Project to MySQL

### Step 1: Set Up External MySQL Database

**Why External?**: Replit only provides PostgreSQL. For MySQL, you need:
- External MySQL service (PlanetScale, Railway, Aiven)
- OR install MySQL in Docker locally

**Option A: PlanetScale (Recommended - Free Tier)**

1. Go to https://planetscale.com
2. Create account
3. Create new database: `travian-production`
4. Get connection details:
   ```
   Host: xxxxxxx.us-east-2.psdb.cloud
   Username: xxxxxxxxx
   Password: pscale_pw_xxxxxxxxx
   Port: 3306
   ```

**Option B: Railway ($5 free credit)**

1. Go to https://railway.app
2. Create new project
3. Add MySQL database
4. Get connection details from dashboard

**Option C: Local Docker MySQL**

```bash
docker run -d \
  --name travian-mysql \
  -e MYSQL_ROOT_PASSWORD=your_root_password \
  -e MYSQL_DATABASE=travian_global \
  -e MYSQL_USER=travian_user \
  -e MYSQL_PASSWORD=your_password \
  -p 3306:3306 \
  mysql:8.0
```

### Step 2: Update Environment Variables

**Edit `.env` file** (or create if missing):

```bash
# Replace PostgreSQL variables with MySQL
DB_HOST=your-mysql-host.example.com
DB_PORT=3306
DB_DATABASE=travian_global
DB_USERNAME=travian_user
DB_PASSWORD=your_secure_password

# Remove these (PostgreSQL):
# PGHOST=
# PGPORT=
# PGUSER=
# PGPASSWORD=
# PGDATABASE=
```

**For Replit Secrets**: Add these via Replit UI:
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

### Step 3: Fix `sections/globalConfig.php`

**REPLACE ENTIRE FILE** with this:

```php
<?php
/**
 * Global Configuration - MySQL Version
 * DO NOT use PostgreSQL variables (PGHOST, etc.)
 */

// Error Reporting (Production)
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/storage/logs/php-error.log');

// MySQL Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'mysql');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'travian_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'travian_global');

// Validate database configuration
if (empty(DB_PASSWORD)) {
    error_log('ERROR: DB_PASSWORD not configured');
    die('Database configuration error. Check logs.');
}

// Redis Configuration
define('REDIS_HOST', getenv('REDIS_HOST') ?: 'redis');
define('REDIS_PORT', getenv('REDIS_PORT') ?: 6379);

// Application Settings
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
define('DOMAIN', parse_url(APP_URL, PHP_URL_HOST));
define('DEBUG_MODE', getenv('APP_DEBUG') === 'true');

// Security
define('SECURE_HASH_SALT', getenv('SECURE_HASH_SALT') ?: 'CHANGE_THIS_SALT_IN_PRODUCTION');
define('SESSION_LIFETIME', (int)getenv('SESSION_LIFETIME') ?: 86400);

// Email Settings
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
define('SMTP_FROM_ADDRESS', getenv('SMTP_FROM_ADDRESS') ?: 'noreply@travian.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Travian');

// reCAPTCHA
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '');

// Paths
define('ROOT_PATH', __DIR__);
define('INCLUDE_PATH', ROOT_PATH . '/sections/api/include');

// Composer Autoload
if (file_exists(INCLUDE_PATH . '/vendor/autoload.php')) {
    require_once INCLUDE_PATH . '/vendor/autoload.php';
}

// Session Configuration (for MySQL/Redis)
ini_set('session.save_handler', 'files'); // Change to 'redis' when Redis is available
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', getenv('COOKIE_SECURE') === 'true' ? '1' : '0');
ini_set('session.use_strict_mode', '1');
```

**Save and verify**:
```bash
php -l sections/globalConfig.php
# Should output: No syntax errors
```

### Step 4: Fix `sections/api/include/Database/DB.php`

**REPLACE ENTIRE FILE** with this MySQL version:

```php
<?php
namespace Database;

use PDO;
use PDOException;

class DB
{
    private static $instance = null;
    
    /**
     * Get PDO instance for global MySQL database
     * @return PDO
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            try {
                $host = getenv('DB_HOST') ?: 'mysql';
                $port = getenv('DB_PORT') ?: '3306';
                $database = getenv('DB_DATABASE') ?: 'travian_global';
                $username = getenv('DB_USERNAME') ?: 'travian_user';
                $password = getenv('DB_PASSWORD') ?: '';
                
                // Validate credentials
                if (empty($password)) {
                    throw new \Exception('Database password not configured');
                }
                
                // MySQL DSN
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
                
                self::$instance = new PDO($dsn, $username, $password, $options);
                
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new \Exception("Database connection failed. Check configuration.");
            }
        }
        
        return self::$instance;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    private function __wakeup() {}
}
```

**Save and test**:
```bash
php -l sections/api/include/Database/DB.php
```

### Step 5: Verify `sections/api/include/Database/ServerDB.php`

**CHECK CURRENT FILE** - It should already support MySQL:

```php
$dsn = 'mysql:charset=utf8mb4;host=' . $connection['database']['hostname'] . ';dbname=' . $connection['database']['database'];
```

**If it has PostgreSQL code, REPLACE with**:

```php
<?php
namespace Database;

use PDO;

class ServerDB
{
    private static $connections = [];
    
    /**
     * Get PDO instance for game world database
     * @param string $configFileLocation Path to connection.php
     * @return PDO
     */
    public static function getInstance($configFileLocation)
    {
        $configKey = substr(md5($configFileLocation), 0, 5);
        
        if (isset(self::$connections[$configKey])) {
            return self::$connections[$configKey];
        }
        
        if (!is_file($configFileLocation)) {
            throw new \Exception("Configuration file not found: {$configFileLocation}");
        }
        
        require($configFileLocation);
        
        if (!isset($connection)) {
            throw new \Exception("Invalid connection configuration");
        }
        
        // MySQL DSN
        $dsn = 'mysql:charset=utf8mb4;host=' . $connection['database']['hostname'] 
             . ';dbname=' . $connection['database']['database'];
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $db = new PDO(
            $dsn,
            $connection['database']['username'],
            $connection['database']['password'],
            $options
        );
        
        $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        self::$connections[$configKey] = $db;
        
        return $db;
    }
}
```

### Step 6: Remove PostgreSQL Column Name Mapping

Since MySQL preserves camelCase, remove the mapping code:

**EDIT `sections/api/include/Core/Server.php`**:

**REMOVE this entire function**:
```php
// Map PostgreSQL lowercase column names to camelCase for backward compatibility
private static function mapColumnNames($row)
{
    // DELETE THIS ENTIRE FUNCTION
}
```

**CHANGE this**:
```php
public static function getServerById($id)
{
    // ...
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return self::mapColumnNames($row);  // REMOVE THIS LINE
}
```

**TO this**:
```php
public static function getServerById($id)
{
    // ...
    return $stmt->fetch(PDO::FETCH_ASSOC);  // Return directly
}
```

### Step 7: Create MySQL Global Database Schema

**Create file**: `database/schemas/mysql-global-schema.sql`

```sql
-- TravianT4.6 Global MySQL Database Schema

CREATE DATABASE IF NOT EXISTS travian_global 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE travian_global;

-- Game Servers
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

-- User Activation
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

-- Insert Sample Data
INSERT INTO `gameServers` (`worldId`, `speed`, `name`, `gameWorldUrl`, `startTime`, `roundLength`, `configFileLocation`)
VALUES
  ('testworld', 100, 'Test Server 100x', 'http://testworld.travian.local/', UNIX_TIMESTAMP(), 365, '/var/www/html/sections/servers/testworld/include/connection.php'),
  ('demo', 5, 'Demo Server 5x', 'http://demo.travian.local/', UNIX_TIMESTAMP(), 180, '/var/www/html/sections/servers/demo/include/connection.php');

INSERT INTO `configurations` (`key`, `value`, `description`)
VALUES
  ('site_title', 'Travian Legends', 'Website title'),
  ('registration_open', '1', 'Enable user registration'),
  ('maintenance_mode', '0', 'Enable maintenance mode');
```

### Step 8: Import Global Schema to MySQL

```bash
# Using external MySQL
mysql -h your-host.example.com -u travian_user -p travian_global < database/schemas/mysql-global-schema.sql

# OR using Docker
docker exec -i travian-mysql mysql -u root -p travian_global < database/schemas/mysql-global-schema.sql

# OR using MySQL client
mysql -h 127.0.0.1 -P 3306 -u travian_user -p travian_global < database/schemas/mysql-global-schema.sql
```

### Step 9: Verify MySQL Connection

**Create test file**: `test-mysql-connection.php`

```php
<?php
require_once __DIR__ . '/sections/globalConfig.php';
require_once __DIR__ . '/sections/api/include/Database/DB.php';

use Database\DB;

try {
    $db = DB::getInstance();
    $result = $db->query("SELECT COUNT(*) as count FROM gameServers")->fetch();
    echo "✓ MySQL Connection Successful!\n";
    echo "✓ Found {$result['count']} game servers\n";
    
    // Test data retrieval
    $servers = $db->query("SELECT worldId, name, speed FROM gameServers")->fetchAll();
    foreach ($servers as $server) {
        echo "  - {$server['name']} ({$server['worldId']}) - {$server['speed']}x speed\n";
    }
    
} catch (Exception $e) {
    echo "✗ Connection Failed: " . $e->getMessage() . "\n";
    exit(1);
}
```

**Run test**:
```bash
php test-mysql-connection.php
```

**Expected output**:
```
✓ MySQL Connection Successful!
✓ Found 2 game servers
  - Test Server 100x (testworld) - 100x speed
  - Demo Server 5x (demo) - 5x speed
```

### Step 10: Update Replit Workflow (if using Replit)

Since Replit's PostgreSQL won't be used anymore, update workflow:

**Keep the PHP server**:
```bash
php -S 0.0.0.0:5000 router.php
```

**No changes needed** - Just ensure MySQL credentials are in Replit Secrets.

---

## Verification Checklist

After completing all steps:

- [ ] External MySQL database created and accessible
- [ ] Environment variables updated (DB_HOST, DB_PORT, etc.)
- [ ] `sections/globalConfig.php` uses MySQL variables (not PGHOST)
- [ ] `sections/api/include/Database/DB.php` uses MySQL DSN
- [ ] `sections/api/include/Database/ServerDB.php` uses MySQL DSN
- [ ] Global database schema imported successfully
- [ ] Test connection script runs successfully
- [ ] Can query gameServers table and see data

---

## Troubleshooting

### "Connection refused"
- Check MySQL is running
- Verify host/port in .env
- Check firewall allows port 3306

### "Access denied"
- Verify username/password
- Check user has permissions: `GRANT ALL ON travian_global.* TO 'travian_user'@'%'`

### "Database not found"
- Create database: `CREATE DATABASE travian_global`
- Re-import schema

### "SSL connection error" (PlanetScale)
- Add to DSN: `;sslmode=require`

---

## Next Steps

Once MySQL conversion is complete:
- ✅ Continue to **FIX-02-GAME-WORLD-SETUP.md**
- Create game world databases
- Create game world configuration files
- Import game world schemas (90+ tables)
