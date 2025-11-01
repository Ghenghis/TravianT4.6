# Fix 02: Game World Database Setup

## Problem Statement

**Login fails** because game world databases and configuration files don't exist.

When a user tries to login, the code:
1. Reads `gameServers` table → finds `configFileLocation` path
2. Tries to load `connection.php` → **FILE NOT FOUND**
3. Tries to connect to game world database → **DATABASE NOT FOUND**
4. Login fails with error

## What's Missing (File by File)

### ❌ Missing Files

```
sections/servers/testworld/include/connection.php  → DOESN'T EXIST
sections/servers/demo/include/connection.php       → DOESN'T EXIST
```

### ❌ Missing Databases

```
travian_testworld  → NOT CREATED (needs 90+ tables)
travian_demo       → NOT CREATED (needs 90+ tables)
```

### ❌ Missing Directories

```
sections/servers/testworld/                        → May not exist
sections/servers/testworld/include/                → May not exist
sections/servers/testworld/public/                 → May not exist
sections/servers/demo/                             → May not exist
sections/servers/demo/include/                     → May not exist
sections/servers/demo/public/                      → May not exist
```

---

## Solution: Create Complete Game World Setup

### Step 1: Create Directory Structure

```bash
# For testworld
mkdir -p sections/servers/testworld/include
mkdir -p sections/servers/testworld/public
mkdir -p sections/servers/testworld/logs
mkdir -p sections/servers/testworld/cache

# For demo
mkdir -p sections/servers/demo/include
mkdir -p sections/servers/demo/public
mkdir -p sections/servers/demo/logs
mkdir -p sections/servers/demo/cache

# Set permissions
chmod 755 sections/servers/testworld
chmod 755 sections/servers/demo
chmod 775 sections/servers/testworld/logs
chmod 775 sections/servers/testworld/cache
chmod 775 sections/servers/demo/logs
chmod 775 sections/servers/demo/cache
```

**Verify**:
```bash
ls -la sections/servers/testworld/
# Should show: include/ public/ logs/ cache/

ls -la sections/servers/demo/
# Should show: include/ public/ logs/ cache/
```

### Step 2: Create testworld Connection File

**CREATE FILE**: `sections/servers/testworld/include/connection.php`

```php
<?php
/**
 * Test World Connection Configuration
 * Speed: 100x
 * Round Length: 365 days
 */

global $connection;
$connection = [
    // Game Settings
    'speed' => '100',              // 100x game speed
    'round_length' => '365',       // 365 days per round
    'worldId' => 'testworld',
    'title' => 'Test Server 100x',
    'serverName' => 'Test Server',
    'version' => 'T4.6',
    
    // URLs
    'gameWorldUrl' => 'http://testworld.travian.local/',
    
    // Security
    'secure_hash_code' => md5('testworld_' . time()),  // Generate unique hash
    
    // Auto-reinstall Settings (for auto-restart worlds)
    'auto_reinstall' => '0',                          // 0 = disabled, 1 = enabled
    'auto_reinstall_start_after' => '86400',          // Seconds after finish
    'engine_filename' => 'testworld.service',
    
    // Database Configuration - MySQL
    'database' => [
        'hostname' => getenv('DB_HOST') ?: 'mysql',
        'username' => getenv('DB_USERNAME') ?: 'travian_user',
        'password' => getenv('DB_PASSWORD') ?: '',
        'database' => 'travian_testworld',             // World-specific database
        'charset' => 'utf8mb4',
    ],
];

// Validation
if (empty($connection['database']['password'])) {
    error_log('ERROR: Database password not configured for testworld');
}
```

**Save and verify**:
```bash
php -l sections/servers/testworld/include/connection.php
# Should output: No syntax errors
```

### Step 3: Create demo Connection File

**CREATE FILE**: `sections/servers/demo/include/connection.php`

```php
<?php
/**
 * Demo World Connection Configuration
 * Speed: 5x
 * Round Length: 180 days
 */

global $connection;
$connection = [
    // Game Settings
    'speed' => '5',                // 5x game speed
    'round_length' => '180',       // 180 days per round
    'worldId' => 'demo',
    'title' => 'Demo Server 5x',
    'serverName' => 'Demo Server',
    'version' => 'T4.6',
    
    // URLs
    'gameWorldUrl' => 'http://demo.travian.local/',
    
    // Security
    'secure_hash_code' => md5('demo_' . time()),  // Generate unique hash
    
    // Auto-reinstall Settings
    'auto_reinstall' => '0',
    'auto_reinstall_start_after' => '86400',
    'engine_filename' => 'demo.service',
    
    // Database Configuration - MySQL
    'database' => [
        'hostname' => getenv('DB_HOST') ?: 'mysql',
        'username' => getenv('DB_USERNAME') ?: 'travian_user',
        'password' => getenv('DB_PASSWORD') ?: '',
        'database' => 'travian_demo',                  // World-specific database
        'charset' => 'utf8mb4',
    ],
];

// Validation
if (empty($connection['database']['password'])) {
    error_log('ERROR: Database password not configured for demo');
}
```

**Save and verify**:
```bash
php -l sections/servers/demo/include/connection.php
```

### Step 4: Update gameServers Table

**Update configFileLocation** paths to match actual files:

```sql
-- Connect to global database
USE travian_global;

-- Update testworld path
UPDATE gameServers 
SET configFileLocation = '/var/www/html/sections/servers/testworld/include/connection.php'
WHERE worldId = 'testworld';

-- Update demo path  
UPDATE gameServers
SET configFileLocation = '/var/www/html/sections/servers/demo/include/connection.php'
WHERE worldId = 'demo';

-- Verify
SELECT worldId, name, configFileLocation FROM gameServers;
```

**Expected output**:
```
worldId    | name                | configFileLocation
-----------|---------------------|----------------------------------------------------
testworld  | Test Server 100x    | /var/www/html/sections/servers/testworld/include/connection.php
demo       | Demo Server 5x      | /var/www/html/sections/servers/demo/include/connection.php
```

### Step 5: Create Game World Databases

```sql
-- Create testworld database
CREATE DATABASE IF NOT EXISTS travian_testworld 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create demo database
CREATE DATABASE IF NOT EXISTS travian_demo 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Verify
SHOW DATABASES LIKE 'travian%';
```

**Expected output**:
```
Database
-----------------
travian_global
travian_testworld
travian_demo
```

### Step 6: Import Game World Schema (90+ Tables)

The complete game schema is in `main_script/include/schema/T4.4.sql`

**Import to testworld**:
```bash
# Using MySQL command line
mysql -h your-host -u travian_user -p travian_testworld < main_script/include/schema/T4.4.sql

# OR using Docker
docker exec -i travian-mysql mysql -u root -p travian_testworld < main_script/include/schema/T4.4.sql
```

**Import to demo**:
```bash
mysql -h your-host -u travian_user -p travian_demo < main_script/include/schema/T4.4.sql
```

**Verify table count**:
```sql
-- Should have 90+ tables
USE travian_testworld;
SELECT COUNT(*) as table_count 
FROM information_schema.tables 
WHERE table_schema = 'travian_testworld';

USE travian_demo;
SELECT COUNT(*) as table_count 
FROM information_schema.tables 
WHERE table_schema = 'travian_demo';
```

**Expected output**: ~90 tables in each database

### Step 7: Verify Critical Tables Exist

```sql
USE travian_testworld;

-- Check critical tables
SHOW TABLES LIKE 'users';
SHOW TABLES LIKE 'villages';
SHOW TABLES LIKE 'alliances';
SHOW TABLES LIKE 'activation';
SHOW TABLES LIKE 'marketplace';

-- Check users table structure
DESCRIBE users;
```

**Critical tables must exist**:
- ✅ `users` - Player accounts
- ✅ `villages` - Player villages
- ✅ `alliances` - Alliance data
- ✅ `activation` - Account activation
- ✅ `marketplace` - Trading system
- ✅ `units` - Military units
- ✅ `research` - Technology research
- ✅ `buildings` - Building construction
- ✅ `movement` - Troop movements
- ... and ~80 more

### Step 8: Copy Game Engine Files

**Copy main game engine to each world**:

```bash
# Copy to testworld
cp -r main_script/* sections/servers/testworld/

# Copy to demo
cp -r main_script/* sections/servers/demo/

# Verify
ls -la sections/servers/testworld/
# Should show: include/ public/ etc.
```

### Step 9: Create World-Specific Public Index

**CREATE FILE**: `sections/servers/testworld/public/index.php`

```php
<?php
/**
 * Test World Entry Point
 */

// Load connection
require_once __DIR__ . '/../include/connection.php';

// Load game engine
if (file_exists(__DIR__ . '/../include/bootstrap.php')) {
    require_once __DIR__ . '/../include/bootstrap.php';
} else {
    die('Game engine not found. Please install game files.');
}

// Start game
// ... game initialization code ...
```

**CREATE FILE**: `sections/servers/demo/public/index.php`

```php
<?php
/**
 * Demo World Entry Point
 */

// Load connection
require_once __DIR__ . '/../include/connection.php';

// Load game engine
if (file_exists(__DIR__ . '/../include/bootstrap.php')) {
    require_once __DIR__ . '/../include/bootstrap.php';
} else {
    die('Game engine not found. Please install game files.');
}

// Start game
// ... game initialization code ...
```

### Step 10: Test Connection to Game World Database

**CREATE TEST FILE**: `test-world-connection.php`

```php
<?php
require_once __DIR__ . '/sections/api/include/Database/ServerDB.php';

use Database\ServerDB;

// Test testworld
echo "Testing testworld connection...\n";
try {
    $configPath = __DIR__ . '/sections/servers/testworld/include/connection.php';
    
    if (!file_exists($configPath)) {
        throw new Exception("Config file not found: {$configPath}");
    }
    
    $db = ServerDB::getInstance($configPath);
    $result = $db->query("SELECT COUNT(*) as count FROM users")->fetch();
    echo "✓ Testworld connected! Found {$result['count']} users\n";
    
    // Check table count
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Testworld has " . count($tables) . " tables\n";
    
} catch (Exception $e) {
    echo "✗ Testworld failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test demo
echo "Testing demo connection...\n";
try {
    $configPath = __DIR__ . '/sections/servers/demo/include/connection.php';
    
    if (!file_exists($configPath)) {
        throw new Exception("Config file not found: {$configPath}");
    }
    
    $db = ServerDB::getInstance($configPath);
    $result = $db->query("SELECT COUNT(*) as count FROM users")->fetch();
    echo "✓ Demo connected! Found {$result['count']} users\n";
    
    // Check table count
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Demo has " . count($tables) . " tables\n";
    
} catch (Exception $e) {
    echo "✗ Demo failed: " . $e->getMessage() . "\n";
}
```

**Run test**:
```bash
php test-world-connection.php
```

**Expected output**:
```
Testing testworld connection...
✓ Testworld connected! Found 0 users
✓ Testworld has 90 tables

Testing demo connection...
✓ Demo connected! Found 0 users
✓ Demo has 90 tables
```

### Step 11: Grant MySQL Permissions

Ensure the database user has permissions on world databases:

```sql
-- Grant permissions
GRANT ALL PRIVILEGES ON travian_testworld.* TO 'travian_user'@'%';
GRANT ALL PRIVILEGES ON travian_demo.* TO 'travian_user'@'%';
FLUSH PRIVILEGES;

-- Verify permissions
SHOW GRANTS FOR 'travian_user'@'%';
```

---

## Verification Checklist

- [ ] Directories created (testworld/include, demo/include, etc.)
- [ ] connection.php files created for both worlds
- [ ] gameServers table updated with correct paths
- [ ] travian_testworld database created
- [ ] travian_demo database created
- [ ] Schema imported (90+ tables in each)
- [ ] MySQL permissions granted
- [ ] Test connection script runs successfully
- [ ] Can query users table in both databases

---

## Common Issues

### "Configuration file not found"
```
Solution: Verify file exists:
ls -la sections/servers/testworld/include/connection.php
```

### "Access denied for user"
```sql
Solution: Grant permissions:
GRANT ALL PRIVILEGES ON travian_testworld.* TO 'travian_user'@'%';
FLUSH PRIVILEGES;
```

### "Table 'users' doesn't exist"
```
Solution: Re-import schema:
mysql -u travian_user -p travian_testworld < main_script/include/schema/T4.4.sql
```

### "Database not found"
```sql
Solution: Create database:
CREATE DATABASE travian_testworld CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## Next Steps

Once game world setup is complete:
- ✅ Continue to **FIX-03-LOGIN-SYSTEM.md**
- Test login functionality
- Create test user accounts
- Verify gameplay mechanics
