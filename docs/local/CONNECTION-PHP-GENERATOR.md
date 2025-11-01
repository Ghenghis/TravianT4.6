# Connection.php File Generator - Automated World Configuration

## 🎯 Purpose

This guide solves the "missing connection.php" problem by auto-generating database connection files for each game world. By the end, you'll have:

- ✅ Understanding why connection.php files are critical
- ✅ Template-based connection.php generator script
- ✅ Connection files created for testworld and demo
- ✅ Ability to easily add new game worlds
- ✅ Fixed "userDoesNotExists" errors caused by missing connections

**Estimated Time:** 30 minutes

---

## 📋 Prerequisites

Before starting, ensure you've completed:

- ✅ Guide 6: GAME-WORLD-DATABASES.md
- ✅ `travian_testworld` and `travian_demo` databases exist
- ✅ Game servers registered in `travian_global.gameServers`
- ✅ `.env` file configured with database credentials

---

## Section 1: Understanding the Problem

### ❌ What Went Wrong

**Error Message:**
```
Warning: include(sections/servers/testworld/include/connection.php): failed to open stream: No such file or directory
```

**Why this happened:**
1. Each game world needs its own `connection.php` file
2. File location: `sections/servers/{worldId}/include/connection.php`
3. This file was missing for testworld and demo
4. Without it, the game can't connect to the world database

**What connection.php does:**
```php
<?php
// sections/servers/testworld/include/connection.php

// Database connection for THIS specific world
$database_host = "mysql";
$database_name = "travian_testworld";  // World-specific database
$database_username = "travian_admin";
$database_password = "SuperSecurePassword123!";

// Connect to world database
$connection = mysqli_connect($database_host, $database_username, $database_password, $database_name);

if (!$connection) {
    die("Database connection failed for testworld: " . mysqli_connect_error());
}
?>
```

**Why it's critical:**
- Game code includes this file to access world data
- Login system uses it to verify user credentials
- All game operations (building, attacking, trading) need it
- Missing file = complete game failure for that world

---

## Section 2: Create Connection Template

### Step 2.1: Create Templates Directory

```bash
cd ~/Projects/TravianT4.6
mkdir -p templates/world
```

### Step 2.2: Create Connection Template

```bash
nano templates/world/connection.php.template
```

Paste:

```php
<?php
/**
 * Database Connection for {{WORLD_ID}}
 * Auto-generated on {{GENERATED_DATE}}
 * 
 * This file connects the game world to its specific database.
 * DO NOT edit manually - use the connection generator script.
 */

// Database configuration from environment
$database_host = "{{DB_HOST}}";
$database_name = "travian_{{WORLD_ID}}";
$database_username = "{{DB_USER}}";
$database_password = "{{DB_PASSWORD}}";
$database_port = {{DB_PORT}};

// Create MySQL connection
$connection = @mysqli_connect(
    $database_host,
    $database_username,
    $database_password,
    $database_name,
    $database_port
);

// Check connection
if (!$connection) {
    error_log("Database connection failed for world '{{WORLD_ID}}': " . mysqli_connect_error());
    die("Could not connect to game database. Please contact administrator.");
}

// Set character set to UTF-8
if (!mysqli_set_charset($connection, "utf8mb4")) {
    error_log("Error loading character set utf8mb4 for world '{{WORLD_ID}}': " . mysqli_error($connection));
}

// Store world information
define('WORLD_ID', '{{WORLD_ID}}');
define('WORLD_DATABASE', $database_name);
define('WORLD_SPEED', {{WORLD_SPEED}});

// Connection successful
if (!defined('CONNECTION_ESTABLISHED')) {
    define('CONNECTION_ESTABLISHED', true);
}
?>
```

Save and exit.

### Step 2.3: Create Config Template

```bash
nano templates/world/config.php.template
```

Paste:

```php
<?php
/**
 * World Configuration for {{WORLD_ID}}
 * Auto-generated on {{GENERATED_DATE}}
 */

// World settings
$config['world_id'] = '{{WORLD_ID}}';
$config['world_name'] = '{{WORLD_NAME}}';
$config['world_speed'] = {{WORLD_SPEED}};
$config['world_url'] = '{{WORLD_URL}}';

// Game mechanics based on speed
$config['troop_training_speed'] = {{WORLD_SPEED}};
$config['building_speed'] = {{WORLD_SPEED}};
$config['research_speed'] = {{WORLD_SPEED}};
$config['merchant_speed'] = {{WORLD_SPEED}};

// World-specific features
$config['activation_required'] = {{ACTIVATION_REQUIRED}};
$config['registration_key_required'] = {{REGISTRATION_KEY_REQUIRED}};
$config['register_closed'] = {{REGISTER_CLOSED}};
$config['world_hidden'] = {{WORLD_HIDDEN}};

// Timestamps
$config['world_start'] = {{WORLD_START}};
$config['world_finish'] = {{WORLD_FINISH}};

?>
```

Save and exit.

---

## Section 3: Create Generator Script

### Step 3.1: Create PHP Generator

```bash
nano scripts/generate-world-connections.php
```

Paste:

```php
<?php
/**
 * World Connection File Generator
 * 
 * Generates connection.php files for all game worlds
 * based on data in travian_global.gameServers table.
 */

// Load environment variables
require_once __DIR__ . '/load-env.php';

// Database connection to global database
$db_host = env('DB_HOST', 'mysql');
$db_port = env('DB_PORT', '3306');
$db_user = env('DB_USER', 'travian_admin');
$db_password = env('DB_PASSWORD', '');
$db_global = env('DB_GLOBAL', 'travian_global');

$conn = mysqli_connect($db_host, $db_user, $db_password, $db_global, $db_port);

if (!$conn) {
    die("ERROR: Could not connect to global database: " . mysqli_connect_error() . "\n");
}

// Fetch all game servers
$query = "SELECT worldId, name, title, speed, url, activationRequired, registrationKeyRequired, registerClosed, hidden, start, finish FROM gameServers WHERE hidden = 0";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("ERROR: Could not fetch game servers: " . mysqli_error($conn) . "\n");
}

$worlds = [];
while ($row = mysqli_fetch_assoc($result)) {
    $worlds[] = $row;
}

mysqli_close($conn);

if (count($worlds) === 0) {
    die("ERROR: No game worlds found in gameServers table!\n");
}

echo "Found " . count($worlds) . " game world(s)\n";
echo str_repeat("=", 50) . "\n\n";

// Load templates
$connection_template = file_get_contents(__DIR__ . '/../templates/world/connection.php.template');
$config_template = file_get_contents(__DIR__ . '/../templates/world/config.php.template');

if (!$connection_template || !$config_template) {
    die("ERROR: Could not load templates!\n");
}

// Generate files for each world
foreach ($worlds as $world) {
    $worldId = $world['worldId'];
    $worldName = $world['title'];
    $worldSpeed = $world['speed'];
    $worldUrl = $world['url'];
    $activationRequired = $world['activationRequired'] ? 'true' : 'false';
    $registrationKeyRequired = $world['registrationKeyRequired'] ? 'true' : 'false';
    $registerClosed = $world['registerClosed'] ? 'true' : 'false';
    $worldHidden = $world['hidden'] ? 'true' : 'false';
    $worldStart = $world['start'];
    $worldFinish = $world['finish'];
    
    echo "Generating files for: $worldId ($worldName)\n";
    
    // Create world directory structure
    $worldDir = __DIR__ . "/../sections/servers/$worldId";
    $includeDir = "$worldDir/include";
    
    if (!is_dir($worldDir)) {
        mkdir($worldDir, 0755, true);
    }
    if (!is_dir($includeDir)) {
        mkdir($includeDir, 0755, true);
    }
    
    // Generate connection.php
    $connection_content = str_replace(
        [
            '{{WORLD_ID}}',
            '{{WORLD_SPEED}}',
            '{{DB_HOST}}',
            '{{DB_PORT}}',
            '{{DB_USER}}',
            '{{DB_PASSWORD}}',
            '{{GENERATED_DATE}}'
        ],
        [
            $worldId,
            $worldSpeed,
            env('DB_HOST', 'mysql'),
            env('DB_PORT', '3306'),
            env('DB_USER', 'travian_admin'),
            env('DB_PASSWORD', ''),
            date('Y-m-d H:i:s')
        ],
        $connection_template
    );
    
    file_put_contents("$includeDir/connection.php", $connection_content);
    echo "  ✅ Created: $includeDir/connection.php\n";
    
    // Generate config.php
    $config_content = str_replace(
        [
            '{{WORLD_ID}}',
            '{{WORLD_NAME}}',
            '{{WORLD_SPEED}}',
            '{{WORLD_URL}}',
            '{{ACTIVATION_REQUIRED}}',
            '{{REGISTRATION_KEY_REQUIRED}}',
            '{{REGISTER_CLOSED}}',
            '{{WORLD_HIDDEN}}',
            '{{WORLD_START}}',
            '{{WORLD_FINISH}}',
            '{{GENERATED_DATE}}'
        ],
        [
            $worldId,
            $worldName,
            $worldSpeed,
            $worldUrl,
            $activationRequired,
            $registrationKeyRequired,
            $registerClosed,
            $worldHidden,
            $worldStart,
            $worldFinish,
            date('Y-m-d H:i:s')
        ],
        $config_template
    );
    
    file_put_contents("$includeDir/config.php", $config_content);
    echo "  ✅ Created: $includeDir/config.php\n";
    
    echo "\n";
}

echo str_repeat("=", 50) . "\n";
echo "✅ All world connection files generated successfully!\n";

?>
```

Save and exit.

### Step 3.2: Create Bash Wrapper Script

```bash
nano scripts/generate-connections.sh
```

Paste:

```bash
#!/bin/bash

cd ~/Projects/TravianT4.6
source .env

echo "======================================"
echo "World Connection Generator"
echo "======================================"

php scripts/generate-world-connections.php

if [ $? -eq 0 ]; then
    echo ""
    echo "Verifying generated files..."
    
    for world in testworld demo; do
        if [ -f "sections/servers/$world/include/connection.php" ]; then
            echo "✅ sections/servers/$world/include/connection.php exists"
        else
            echo "❌ sections/servers/$world/include/connection.php MISSING!"
        fi
    done
    
    echo ""
    echo "======================================"
    echo "✅ Connection generation complete!"
    echo "======================================"
else
    echo "❌ Connection generation failed!"
    exit 1
fi
```

Make executable:

```bash
chmod +x scripts/generate-connections.sh
```

---

## Section 4: Generate Connection Files

### Step 4.1: Run Generator

```bash
cd ~/Projects/TravianT4.6
./scripts/generate-connections.sh
```

**Expected output:**
```
======================================
World Connection Generator
======================================
Found 2 game world(s)
==================================================

Generating files for: testworld (Test Server 100x)
  ✅ Created: sections/servers/testworld/include/connection.php
  ✅ Created: sections/servers/testworld/include/config.php

Generating files for: demo (Demo Server 5x)
  ✅ Created: sections/servers/demo/include/connection.php
  ✅ Created: sections/servers/demo/include/config.php

==================================================
✅ All world connection files generated successfully!

Verifying generated files...
✅ sections/servers/testworld/include/connection.php exists
✅ sections/servers/demo/include/connection.php exists

======================================
✅ Connection generation complete!
======================================
```

### Step 4.2: Verify File Contents

```bash
cat sections/servers/testworld/include/connection.php
```

**Expected (with actual values):**
```php
<?php
/**
 * Database Connection for testworld
 * Auto-generated on 2025-10-29 15:30:22
 * 
 * This file connects the game world to its specific database.
 * DO NOT edit manually - use the connection generator script.
 */

// Database configuration from environment
$database_host = "mysql";
$database_name = "travian_testworld";
$database_username = "travian_admin";
$database_password = "SuperSecurePassword123!";
$database_port = 3306;
...
?>
```

**✅ Success criteria:**
- File exists
- Contains actual values from .env (not {{placeholders}})
- Database name matches world: `travian_testworld`

---

## Section 5: Test Database Connections

### Step 5.1: Create Connection Test Script

```bash
nano scripts/test-world-connections.php
```

Paste:

```php
<?php
/**
 * Test world database connections
 */

$worlds = ['testworld', 'demo'];

echo "====================================== \n";
echo "Testing World Database Connections\n";
echo "======================================\n\n";

foreach ($worlds as $worldId) {
    echo "Testing: $worldId\n";
    
    $connectionFile = __DIR__ . "/../sections/servers/$worldId/include/connection.php";
    
    if (!file_exists($connectionFile)) {
        echo "  ❌ Connection file missing!\n";
        echo "     File: $connectionFile\n\n";
        continue;
    }
    
    echo "  ✅ Connection file exists\n";
    
    // Include connection file
    include $connectionFile;
    
    if (!isset($connection) || !$connection) {
        echo "  ❌ Connection failed!\n\n";
        continue;
    }
    
    echo "  ✅ Connection established\n";
    
    // Test query
    $result = mysqli_query($connection, "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'travian_$worldId'");
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "  ✅ Tables found: " . $row['table_count'] . "\n";
    } else {
        echo "  ❌ Query failed: " . mysqli_error($connection) . "\n";
    }
    
    mysqli_close($connection);
    unset($connection); // Prevent variable reuse
    
    echo "\n";
}

echo "======================================\n";
echo "✅ Connection test complete!\n";
echo "======================================\n";
?>
```

Save and exit.

### Step 5.2: Run Test

```bash
php scripts/test-world-connections.php
```

**Expected output:**
```
======================================
Testing World Database Connections
======================================

Testing: testworld
  ✅ Connection file exists
  ✅ Connection established
  ✅ Tables found: 127

Testing: demo
  ✅ Connection file exists
  ✅ Connection established
  ✅ Tables found: 127

======================================
✅ Connection test complete!
======================================
```

**✅ Success:** Both worlds connect successfully with 100+ tables

---

## Section 6: Adding New Worlds

### How to Add a New Game World

**Step 1: Add to gameServers table**

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" travian_global <<'EOF'
INSERT INTO gameServers (
  worldId, name, title, speed, start, finish,
  registrationKeyRequired, activationRequired,
  registerClosed, hidden, url
) VALUES (
  'newworld', 'newworld', 'New World 50x', 50,
  UNIX_TIMESTAMP(NOW()), 0,
  0, 1,
  0, 0, 'http://newworld.localhost/'
);
EOF
```

**Step 2: Create world database**

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" <<EOF
CREATE DATABASE IF NOT EXISTS travian_newworld
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
EOF

mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" travian_newworld < database/world_schema.sql
```

**Step 3: Generate connection files**

```bash
./scripts/generate-connections.sh
```

**Done!** New world is ready.

---

## ✅ Verification Checklist

Before proceeding to the next guide, verify:

- [ ] Templates created in `templates/world/`
- [ ] Generator script created (`scripts/generate-world-connections.php`)
- [ ] Bash wrapper created (`scripts/generate-connections.sh`)
- [ ] `sections/servers/testworld/include/connection.php` exists
- [ ] `sections/servers/demo/include/connection.php` exists
- [ ] Connection files contain actual values (not {{placeholders}})
- [ ] Connection test script passes for both worlds
- [ ] Can add new worlds using the documented process

**Full verification command:**

```bash
cd ~/Projects/TravianT4.6
test -f sections/servers/testworld/include/connection.php && echo "✅ testworld" || echo "❌ testworld"
test -f sections/servers/demo/include/connection.php && echo "✅ demo" || echo "❌ demo"
php scripts/test-world-connections.php
```

**Expected:** All files exist, connection tests pass

---

## 🚀 Next Steps

**Perfect!** You've solved the missing connection.php problem that was causing "userDoesNotExists" errors.

**Next guide:** [REDIS-MEMCACHED.md](./REDIS-MEMCACHED.md)

This will walk you through:
- Setting up Redis for caching and sessions
- Improving game performance
- Configuring session storage
- Queue management for background tasks

---

**Last Updated:** October 29, 2025  
**Estimated Completion Time:** 30 minutes  
**Difficulty:** Beginner
