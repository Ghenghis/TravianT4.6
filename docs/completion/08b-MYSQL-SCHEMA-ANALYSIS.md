# MySQL Game World Database Schema Analysis

**Document Version:** 1.0  
**Last Updated:** October 30, 2025  
**Database:** MySQL 8.0  
**Schema Version:** T4.4 (Travian-style game)

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [MySQL Architecture Overview](#mysql-architecture-overview)
3. [Per-World Database Pattern](#per-world-database-pattern)
4. [ServerDB Connection Manager](#serverdb-connection-manager)
5. [Complete Schema Reference](#complete-schema-reference)
6. [Performance Optimization](#performance-optimization)
7. [Integration Points](#integration-points)
8. [Common Query Patterns](#common-query-patterns)
9. [Comparison with PostgreSQL](#comparison-with-postgresql)

---

## Executive Summary

### Critical Context: Dual Database Architecture

This system employs a **dual-database architecture**:

- **PostgreSQL 14**: Global data, AI-NPC system, user authentication, world management
- **MySQL 8.0**: Per-world game databases (THIS DOCUMENT)

### MySQL Role

MySQL 8.0 serves as the **per-world game engine database**, storing:
- Player villages, resources, and populations
- Troop movements and battles
- Alliance data and diplomacy
- Market transactions and trade routes
- Building construction and research
- Hero progression and items
- Reports and messaging

### Key Statistics

- **90 total tables** in the game schema
- **Per-world isolation** via separate databases
- **InnoDB engine** for ACID compliance and row-level locking
- **utf8mb4** character set for emoji and international support
- **Optimized for high-concurrency** game actions

---

## MySQL Architecture Overview

### Database Naming Convention

Each game world has its own isolated MySQL database:

```
travian_global          # Global metadata (rarely used)
travian_testworld       # Test/development world
travian_demo            # Demo world
travian_world_ts1       # Production world: ts1
travian_world_ts2       # Production world: ts2
travian_world_tt1       # Fire & Sand edition: tt1
```

**Pattern**: `travian_world_{world_key}`

### MySQL Configuration

**File**: `docker/mysql/my.cnf`

```ini
[mysqld]
max_connections = 500                    # High concurrency support
innodb_buffer_pool_size = 2G             # Large buffer for caching
innodb_log_file_size = 512M              # Large redo logs
innodb_flush_log_at_trx_commit = 2       # Performance-optimized durability
innodb_flush_method = O_DIRECT           # Bypass OS cache
innodb_file_per_table = 1                # Separate file per table

max_allowed_packet = 64M                 # Support large queries
tmp_table_size = 128M                    # In-memory temp tables
max_heap_table_size = 128M               # Memory table size

thread_cache_size = 50                   # Connection pooling
table_open_cache = 4096                  # Keep tables open

slow_query_log = 1                       # Performance monitoring
long_query_time = 2                      # Log queries > 2 seconds

character-set-server = utf8mb4           # Full Unicode support
collation-server = utf8mb4_unicode_ci
default-storage-engine = InnoDB
```

### Docker Setup

**File**: `docker/mysql/Dockerfile`

```dockerfile
FROM mysql:8.0

ENV MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
ENV MYSQL_DATABASE=travian_global
ENV MYSQL_USER=${MYSQL_USER}
ENV MYSQL_PASSWORD=${MYSQL_PASSWORD}

COPY my.cnf /etc/mysql/conf.d/custom.cnf
COPY init/ /docker-entrypoint-initdb.d/

EXPOSE 3306
```

**Initialization**: `docker/mysql/init/01-create-databases.sql`

```sql
CREATE DATABASE IF NOT EXISTS `travian_global` 
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `travian_testworld` 
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `travian_demo` 
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## Per-World Database Pattern

### Multi-Tenancy Strategy

**Approach**: Database-per-world (complete isolation)

**Advantages**:
- **Data isolation**: No cross-world data leaks
- **Performance isolation**: One world's load doesn't affect others
- **Easy backups**: Backup/restore individual worlds
- **Independent scaling**: Move worlds to different MySQL instances
- **Schema flexibility**: Can upgrade worlds independently

**Trade-offs**:
- **More databases**: Higher management overhead
- **Cross-world queries**: Impossible (by design - security feature)
- **Connection pool**: Must manage connections per world

### World Metadata (PostgreSQL)

World configuration is stored in PostgreSQL's `gameServers` table:

```sql
-- PostgreSQL: Global world registry
SELECT id, worldId, configFileLocation, name, startTime 
FROM gameServers 
WHERE finished = 0;
```

**Example Row**:
```
id: 1
worldId: ts1
configFileLocation: /var/www/worlds/ts1/config.php
name: "Speed Server 1"
startTime: 1698710400
```

### Per-World Schema Application

When a new world is created:

1. Create database: `CREATE DATABASE travian_world_ts1`
2. Apply schema: Execute `main_script/include/schema/T4.4.sql`
3. Register in PostgreSQL: Insert into `gameServers`
4. Generate config file: Create `/var/www/worlds/ts1/config.php`

---

## ServerDB Connection Manager

### Class Overview

**File**: `sections/api/include/Database/ServerDB.php`

The `ServerDB` class manages **per-world MySQL connections** with connection pooling.

### Implementation

```php
<?php
namespace Database;

class ServerDB
{
    private static $connections = [];

    /**
     * Get or create a database connection for a specific world
     * 
     * @param string $configFileLocation Path to world config file
     * @return \PDO PDO connection instance
     * @throws \Exception If config file not found or invalid
     */
    public static function getInstance($configFileLocation)
    {
        // Connection pooling: Reuse existing connections
        $configKey = substr(md5($configFileLocation), 0, 5);
        if (isset(self::$connections[$configKey])) {
            return self::$connections[$configKey];
        }

        // Load world-specific configuration
        if (!is_file($configFileLocation)) {
            throw new \Exception("Configuration file not found!");
        }
        require($configFileLocation);
        
        if (!isset($connection)) {
            throw new \Exception("Invalid data was in connection file!");
        }
        
        // Support both MySQL and PostgreSQL (for flexibility)
        $driver = isset($connection['database']['driver']) 
            ? $connection['database']['driver'] 
            : 'mysql';
        
        if ($driver === 'pgsql') {
            // PostgreSQL connection with schema support
            $dsn = 'pgsql:host=' . $connection['database']['hostname'] . 
                   ';port=' . $connection['database']['port'] .
                   ';dbname=' . $connection['database']['database'];
            
            if (isset($connection['database']['sslmode'])) {
                $dsn .= ';sslmode=' . $connection['database']['sslmode'];
            }
            
            $db = self::$connections[$configKey] = new \PDO(
                $dsn, 
                $connection['database']['username'], 
                $connection['database']['password']
            );
            
            // Set search_path for per-world schema isolation
            if (isset($connection['database']['schema'])) {
                $db->exec("SET search_path TO " . 
                    $connection['database']['schema'] . ", public");
            }
            
            $db->exec("SET client_encoding TO 'UTF8'");
        } else {
            // MySQL connection (default behavior)
            $dsn = 'mysql:charset=utf8mb4;host=' . 
                   $connection['database']['hostname'] . 
                   ';dbname=' . $connection['database']['database'];
            
            $db = self::$connections[$configKey] = new \PDO(
                $dsn, 
                $connection['database']['username'], 
                $connection['database']['password']
            );
            
            $db->exec("set names utf8");
        }
        
        return $db;
    }
}
```

### Usage Pattern in Controllers

**Example**: `sections/api/include/Api/Ctrl/VillageCtrl.php`

```php
<?php
namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\ServerDB;
use PDO;

class VillageCtrl extends ApiAbstractCtrl
{
    public function getVillageList()
    {
        // Step 1: Get world metadata from PostgreSQL
        $server = Server::getServerByWId($this->payload['worldId']);
        if (!$server) {
            $this->response = ['error' => 'Invalid world ID'];
            return;
        }

        // Step 2: Connect to world-specific MySQL database
        $serverDB = ServerDB::getInstance($server['configFileLocation']);
        
        // Step 3: Query game data from MySQL
        $stmt = $serverDB->prepare(
            "SELECT * FROM vdata 
             WHERE owner=:uid 
             ORDER BY capital DESC, kid ASC"
        );
        $stmt->bindValue('uid', $this->payload['uid'], PDO::PARAM_INT);
        $stmt->execute();

        // Step 4: Format response
        $villages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $villages[] = [
                'villageId' => (int)$row['kid'],
                'name' => $row['name'],
                'population' => (int)$row['pop'],
                'resources' => [
                    'wood' => (int)$row['wood'],
                    'clay' => (int)$row['clay'],
                    'iron' => (int)$row['iron'],
                    'crop' => (int)$row['crop']
                ]
            ];
        }

        $this->response = ['villages' => $villages];
    }
}
```

### Connection Flow

```
User Request
    ↓
Controller receives worldId
    ↓
Query PostgreSQL: SELECT configFileLocation FROM gameServers WHERE worldId=?
    ↓
ServerDB::getInstance(configFileLocation)
    ↓
Load world config → Connect to MySQL database
    ↓
Execute game queries (villages, troops, etc.)
    ↓
Return response
```

---

## Complete Schema Reference

### Table Categories

The MySQL schema contains **90 tables** organized into functional domains:

1. **Player Management** (8 tables)
2. **Village & Territory** (9 tables)
3. **Buildings & Infrastructure** (5 tables)
4. **Military Units & Combat** (11 tables)
5. **Alliance System** (10 tables)
6. **Communication** (6 tables)
7. **Hero System** (6 tables)
8. **Economy & Trade** (7 tables)
9. **Quests & Events** (5 tables)
10. **Administration & Logging** (8 tables)
11. **Game Configuration** (15 tables)

---

### 1. Player Management Tables

#### `users` (Core player data)

**Purpose**: Main player account information

**Schema**:
```sql
CREATE TABLE users (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid VARCHAR(40) NULL,                          -- Universal unique ID
  aid INT(11) UNSIGNED NOT NULL DEFAULT '0',      -- Alliance ID
  alliance_role MEDIUMINT(3) UNSIGNED DEFAULT '0',
  name VARCHAR(20) NOT NULL,
  password VARCHAR(40) NOT NULL,
  email VARCHAR(99) NULL DEFAULT '',
  email_verified TINYINT(3) UNSIGNED DEFAULT '0',
  race TINYINT(1) UNSIGNED NOT NULL,              -- 1=Roman, 2=Teuton, 3=Gaul, 4=Egyptian, 5=Hun
  access TINYINT(1) UNSIGNED DEFAULT '1',         -- Access level
  kid INT(6) UNSIGNED NOT NULL,                   -- Capital village ID
  
  -- Statistics
  total_pop BIGINT(255) NOT NULL DEFAULT '0',
  total_villages INT(5) NOT NULL DEFAULT '0',
  total_attack_points BIGINT(255) UNSIGNED DEFAULT '0',
  total_defense_points BIGINT(255) UNSIGNED DEFAULT '0',
  week_attack_points BIGINT(255) UNSIGNED DEFAULT '0',
  week_defense_points BIGINT(255) UNSIGNED DEFAULT '0',
  
  -- Economy
  gift_gold INT(11) NOT NULL DEFAULT '0',
  bought_gold INT(11) NOT NULL DEFAULT '0',
  silver INT(11) NOT NULL DEFAULT '0',
  plus INT(11) UNSIGNED DEFAULT '0',              -- Plus account expiry
  
  -- Timestamps
  signupTime INT(10) UNSIGNED DEFAULT '0',
  last_login_time INT(10) UNSIGNED DEFAULT '0',
  protection INT(10) UNSIGNED DEFAULT '0',        -- Beginner protection end
  
  PRIMARY KEY (id),
  KEY findPlayer (name, email),
  KEY statistics (total_attack_points, total_defense_points, total_pop),
  UNIQUE KEY (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Key Indexes**:
- `PRIMARY`: Fast lookups by user ID
- `findPlayer`: Search by name or email
- `statistics`: Ranking queries
- `uuid`: External ID mapping (unique)

**Related Tables**: `vdata` (villages), `hero` (hero), `face` (avatar)

---

#### `face` (Player avatar)

**Purpose**: Character appearance customization

```sql
CREATE TABLE face (
  uid INT(10) UNSIGNED NOT NULL,
  headProfile SMALLINT(2) NOT NULL,
  hairColor SMALLINT(2) NOT NULL,
  hairStyle SMALLINT(2) NOT NULL,
  ears SMALLINT(2) NOT NULL,
  eyebrow SMALLINT(2) NOT NULL,
  eyes SMALLINT(2) NOT NULL,
  nose SMALLINT(2) NOT NULL,
  mouth SMALLINT(2) NOT NULL,
  beard SMALLINT(2) NOT NULL,
  gender VARCHAR(6) NOT NULL DEFAULT 'male',
  lastupdate INT(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Usage**: Rendered client-side for profile display

---

#### `activation` (Pending registrations)

**Purpose**: Pre-activated accounts awaiting email confirmation

```sql
CREATE TABLE activation (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(15) NOT NULL,
  password VARCHAR(40) NOT NULL,
  email VARCHAR(90) NULL DEFAULT '',
  token VARCHAR(32) NOT NULL,
  refUid INT(11) NOT NULL,                        -- Referrer user ID
  time INT UNSIGNED NOT NULL DEFAULT '0',
  reminded TINYINT UNSIGNED DEFAULT '0',          -- Reminder email sent
  PRIMARY KEY (id),
  KEY name (name),
  KEY email (email),
  KEY token (token),
  KEY reminded (reminded)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Lifecycle**:
1. User submits registration → Insert into `activation`
2. Email sent with token
3. User clicks link → Move to `users`, delete from `activation`
4. Reminder cron job: Check `reminded=0` and `time < NOW() - 24h`

---

#### `deleting` (Account deletion queue)

**Purpose**: Scheduled account deletions

```sql
CREATE TABLE deleting (
  uid INT(11) UNSIGNED NOT NULL,
  time INT(11) UNSIGNED NOT NULL,               -- Deletion scheduled time
  PRIMARY KEY (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Process**: Worker checks this table and deletes users at scheduled time

---

#### `player_references` (Referral system)

**Purpose**: Track player referrals and rewards

```sql
CREATE TABLE player_references (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  ref_uid INT(11) UNSIGNED NOT NULL,            -- Referrer
  uid INT(11) UNSIGNED NOT NULL,                -- New player
  rewardGiven TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  KEY findById (uid, ref_uid),
  KEY rewardGiven (rewardGiven)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `ignoreList` (Blocked players)

**Purpose**: Player block list for messaging

```sql
CREATE TABLE ignoreList (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  ignore_id INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY uid (uid, ignore_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `friendlist` (Friend connections)

**Purpose**: Social connections between players

```sql
CREATE TABLE friendlist (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  to_uid INT(11) UNSIGNED NOT NULL,
  accepted TINYINT(1) UNSIGNED DEFAULT '0',     -- Pending/accepted
  PRIMARY KEY (id),
  KEY uid (uid, to_uid, accepted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `notes` (Player notes)

**Purpose**: Private notes about other players

```sql
CREATE TABLE notes (
  id INT(11) NOT NULL AUTO_INCREMENT,
  uid INT(10) UNSIGNED NOT NULL,
  to_uid INT(10) UNSIGNED NOT NULL,
  note_text TEXT,
  PRIMARY KEY (id),
  KEY search (uid, to_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 2. Village & Territory Tables

#### `vdata` (Village data)

**Purpose**: Main village information

```sql
CREATE TABLE vdata (
  kid INT(6) UNSIGNED NOT NULL,                 -- Village/coordinates ID
  owner INT(11) UNSIGNED NOT NULL,
  fieldtype TINYINT(2) UNSIGNED NOT NULL,       -- 15-crop, 9-crop, etc.
  name VARCHAR(45) NOT NULL,
  capital TINYINT(1) UNSIGNED NOT NULL,
  pop INT(10) NOT NULL,                         -- Population
  cp INT(10) NOT NULL,                          -- Culture points
  celebration INT(11) NOT NULL DEFAULT '0',     -- Active celebration end time
  
  -- Resources
  wood DOUBLE(50, 4) NOT NULL DEFAULT '0',
  clay DOUBLE(50, 4) NOT NULL DEFAULT '0',
  iron DOUBLE(50, 4) NOT NULL DEFAULT '0',
  crop DOUBLE(50, 4) NOT NULL DEFAULT '0',
  
  -- Production rates (per hour)
  woodp BIGINT(50) NOT NULL DEFAULT '0',
  clayp BIGINT(50) NOT NULL DEFAULT '0',
  ironp BIGINT(50) NOT NULL DEFAULT '0',
  cropp BIGINT(50) NOT NULL DEFAULT '0',
  
  -- Storage limits
  maxstore BIGINT(50) NOT NULL,                 -- Warehouse capacity
  maxcrop BIGINT(50) NOT NULL,                  -- Granary capacity
  upkeep BIGINT(50) NOT NULL DEFAULT '0',       -- Troop upkeep
  
  -- Loyalty (for conquered villages)
  loyalty DOUBLE(13, 10) UNSIGNED DEFAULT '100.0000000000',
  last_loyalty_update INT(10) UNSIGNED DEFAULT '0',
  
  -- Metadata
  lastmupdate BIGINT(15) UNSIGNED DEFAULT '0',  -- Last resource update
  created INT(11) UNSIGNED NOT NULL,
  isWW TINYINT(1) UNSIGNED DEFAULT '0',         -- Wonder of the World
  hidden TINYINT(1) UNSIGNED DEFAULT '0',       -- Hidden from ranking
  expandedfrom INT(6) UNSIGNED NOT NULL,        -- Parent village (for settlers)
  
  PRIMARY KEY (kid),
  KEY owner (owner),
  KEY capital (capital),
  KEY isWW (isWW)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Critical Fields**:
- `kid`: Coordinate-based ID (formula: `(y + map_size) * (2 * map_size + 1) + (x + map_size)`)
- `fieldtype`: Determines resource field distribution (15-crop, 9-crop, etc.)
- `lastmupdate`: Used for resource calculation: `current_resources = stored + production_rate * (now - lastmupdate)`

**Indexes**:
- `owner`: List all villages for a player
- `capital`: Find capital villages
- `isWW`: List Wonder villages for endgame

---

#### `wdata` (World map data)

**Purpose**: Map tile information

```sql
CREATE TABLE wdata (
  id INT(6) UNSIGNED NOT NULL AUTO_INCREMENT,
  x SMALLINT(4) NOT NULL,
  y SMALLINT(4) NOT NULL,
  fieldtype TINYINT(2) UNSIGNED NOT NULL,       -- Village type
  oasistype TINYINT(2) UNSIGNED NOT NULL,       -- Oasis resource type
  landscape TINYINT(2) UNSIGNED NOT NULL,       -- Terrain graphic
  crop_percent SMALLINT(3) UNSIGNED DEFAULT '0',-- Crop production bonus
  occupied TINYINT(1) NOT NULL,                 -- Has village/oasis
  map VARCHAR(50) NOT NULL DEFAULT '||=||',     -- Surrounding info
  PRIMARY KEY (id),
  KEY crop_percent (crop_percent),
  KEY fieldtype (fieldtype),
  KEY oasistype (oasistype),
  KEY occupied (occupied)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Map Generation**: Pre-populated during world creation with all map tiles

---

#### `odata` (Oasis data)

**Purpose**: Oasis information and ownership

```sql
CREATE TABLE odata (
  kid INT(6) UNSIGNED NOT NULL,                 -- Oasis ID
  type TINYINT(2) UNSIGNED NOT NULL,            -- Resource bonus type
  did INT(6) UNSIGNED NOT NULL DEFAULT '0',     -- Owning village ID
  
  -- Resources (for robber camps)
  wood DOUBLE(50, 2) NOT NULL,
  clay DOUBLE(50, 2) NOT NULL,
  iron DOUBLE(50, 2) NOT NULL,
  crop DOUBLE(50, 2) NOT NULL,
  
  lasttrain INT(10) UNSIGNED DEFAULT '0',
  lastfarmed INT(11) UNSIGNED DEFAULT '0',
  
  -- Loyalty (when conquering)
  loyalty DOUBLE(13, 10) UNSIGNED DEFAULT '100.0000000000',
  last_loyalty_update INT(10) UNSIGNED DEFAULT '0',
  conquered_time INT(10) UNSIGNED DEFAULT '0',
  
  owner INT(11) UNSIGNED NOT NULL DEFAULT '0',  -- Owner player ID
  lastmupdate BIGINT(15) UNSIGNED NOT NULL,
  
  PRIMARY KEY (kid),
  KEY did (did),
  KEY type (type),
  KEY owner (owner),
  KEY last_loyalty_update (last_loyalty_update)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Oasis Types**:
- 25% wood, 25% crop, etc.
- Animals spawn in oases for hero adventures

---

#### `fdata` (Village fields/buildings)

**Purpose**: Building slots in village

```sql
CREATE TABLE fdata (
  kid INT(6) UNSIGNED NOT NULL,
  
  -- Resource fields (1-18)
  f1 TINYINT(2) UNSIGNED DEFAULT '0',           -- Building level
  f1t TINYINT(2) UNSIGNED DEFAULT '0',          -- Building type
  f2 TINYINT(2) UNSIGNED DEFAULT '0',
  f2t TINYINT(2) UNSIGNED DEFAULT '0',
  -- ... f3-f18
  
  -- Village buildings (19-40)
  f19 TINYINT(2) UNSIGNED DEFAULT '0',
  f19t TINYINT(2) UNSIGNED DEFAULT '0',
  -- ... f20-f40
  
  -- Special buildings
  f99 TINYINT(3) UNSIGNED DEFAULT '0',          -- Rally point level
  f99t TINYINT(2) UNSIGNED DEFAULT '0',
  embassy TINYINT(2) DEFAULT '0',
  heroMansion TINYINT(2) DEFAULT '0',
  
  -- Wonder of the World
  lastWWUpgrade BIGINT(20) DEFAULT '0',
  wwname VARCHAR(25) DEFAULT '',
  
  PRIMARY KEY (kid),
  KEY embassy (embassy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Layout**:
- `f1-f18`: Resource fields (outside village)
- `f19-f40`: Inside village buildings
- `f99`: Rally point (always present)

---

#### `available_villages` (Expansion spots)

**Purpose**: Pre-generated village expansion locations

```sql
CREATE TABLE available_villages (
  kid INT(6) UNSIGNED NOT NULL AUTO_INCREMENT,
  fieldtype DOUBLE NOT NULL,
  r DOUBLE NOT NULL,                            -- Distance from center
  angle DOUBLE NOT NULL,                        -- Angle from center
  rand INT(10) UNSIGNED NOT NULL,               -- Randomization
  occupied TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (kid),
  KEY angle (angle),
  KEY fieldtype (fieldtype),
  KEY r (r),
  KEY occupied (occupied),
  KEY rand (rand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Usage**: When players send settlers, find nearest `occupied=0` spot

---

#### `surrounding` (Surrounding villages cache)

**Purpose**: Cache nearby villages for map rendering

```sql
CREATE TABLE surrounding (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(11) UNSIGNED NOT NULL,
  x SMALLINT(4) NOT NULL,
  y SMALLINT(4) NOT NULL,
  type TINYINT(2) UNSIGNED NOT NULL,
  params TEXT,
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY time (time),
  KEY kid (kid, x, y)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `map_block` (Map rendering cache)

**Purpose**: Pre-rendered map blocks for performance

```sql
CREATE TABLE map_block (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  tx0 MEDIUMINT(4) NOT NULL,                    -- Tile X start
  ty0 MEDIUMINT(4) NOT NULL,                    -- Tile Y start
  tx1 MEDIUMINT(4) NOT NULL,                    -- Tile X end
  ty1 MEDIUMINT(4) NOT NULL,                    -- Tile Y end
  zoomLevel TINYINT(1) UNSIGNED NOT NULL,
  version INT(11) NOT NULL DEFAULT '0',         -- Cache invalidation
  PRIMARY KEY (id),
  KEY tx0 (tx0, ty0, tx1, ty1, version),
  KEY zoomLevel (zoomLevel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `map_mark` (Player map markers)

**Purpose**: Player-specific map markers

```sql
CREATE TABLE map_mark (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) NOT NULL,
  tx0 MEDIUMINT(4) NOT NULL,
  ty0 MEDIUMINT(4) NOT NULL,
  tx1 MEDIUMINT(4) NOT NULL,
  ty1 MEDIUMINT(4) NOT NULL,
  zoomLevel TINYINT(1) UNSIGNED NOT NULL,
  version INT(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  KEY tx0 (uid, tx0, ty0, tx1, ty1, version),
  KEY zoomLevel (zoomLevel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `mapflag` (Map flags)

**Purpose**: Colored flags on map for alliance coordination

```sql
CREATE TABLE mapflag (
  id INT(11) NOT NULL AUTO_INCREMENT,
  aid INT(11) UNSIGNED NOT NULL,
  uid INT(11) UNSIGNED NOT NULL,
  targetId INT(11) UNSIGNED NOT NULL,           -- Village/coordinates
  text VARCHAR(50) NOT NULL,
  color SMALLINT(2) UNSIGNED NOT NULL,
  type SMALLINT(1) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY uid (uid, aid, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 3. Buildings & Infrastructure Tables

#### `building_upgrade` (Building construction queue)

**Purpose**: Track building upgrades in progress

```sql
CREATE TABLE building_upgrade (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(6) UNSIGNED NOT NULL,
  building_field TINYINT(2) UNSIGNED NOT NULL,  -- f1-f40
  isMaster TINYINT(1) UNSIGNED DEFAULT '0',     -- Master builder bonus
  start_time INT(10) NOT NULL,
  commence INT(11) NOT NULL,                    -- Completion time
  PRIMARY KEY (id),
  KEY (building_field, isMaster, commence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Worker Process**: Check `commence <= NOW()` and complete buildings

---

#### `demolition` (Building demolition queue)

**Purpose**: Buildings being demolished

```sql
CREATE TABLE demolition (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(6) UNSIGNED NOT NULL,
  building_field TINYINT(2) UNSIGNED NOT NULL,
  end_time INT(10) UNSIGNED NOT NULL,
  complete TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id, kid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `research` (Unit research queue)

**Purpose**: Academy/blacksmith research

```sql
CREATE TABLE research (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(6) UNSIGNED NOT NULL,
  nr TINYINT(2) NOT NULL,                       -- Unit type
  mode TINYINT(1) UNSIGNED NOT NULL,            -- Research type
  end_time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY mode (mode),
  KEY kid (kid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `smithy` (Weapon upgrades)

**Purpose**: Track weapon upgrade levels

```sql
CREATE TABLE smithy (
  kid INT(6) UNSIGNED NOT NULL AUTO_INCREMENT,
  u1 TINYINT(1) UNSIGNED DEFAULT '0',           -- Unit 1 upgrade level
  u2 TINYINT(1) UNSIGNED DEFAULT '0',
  u3 TINYINT(1) UNSIGNED DEFAULT '0',
  u4 TINYINT(1) UNSIGNED DEFAULT '0',
  u5 TINYINT(1) UNSIGNED DEFAULT '0',
  u6 TINYINT(1) UNSIGNED DEFAULT '0',
  u7 TINYINT(1) UNSIGNED DEFAULT '0',
  u8 TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (kid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Max Level**: 20 for each unit type

---

#### `tdata` (Unit research status)

**Purpose**: Track which units are researched

```sql
CREATE TABLE tdata (
  kid INT(6) UNSIGNED NOT NULL,
  u2 TINYINT(1) UNSIGNED DEFAULT '0',           -- 0=not researched, 1=researched
  u3 TINYINT(1) UNSIGNED DEFAULT '0',
  u4 TINYINT(1) UNSIGNED DEFAULT '0',
  u5 TINYINT(1) UNSIGNED DEFAULT '0',
  u6 TINYINT(1) UNSIGNED DEFAULT '0',
  u7 TINYINT(1) UNSIGNED DEFAULT '0',
  u8 TINYINT(1) UNSIGNED DEFAULT '0',
  u9 TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (kid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Note**: u1 (basic unit) is always available

---

### 4. Military Units & Combat Tables

#### `units` (Village troops)

**Purpose**: Troops stationed in a village

```sql
CREATE TABLE units (
  kid INT(6) UNSIGNED NOT NULL,
  race TINYINT(1) UNSIGNED DEFAULT '0',
  u1 BIGINT(50) UNSIGNED DEFAULT '0',           -- Unit type 1 count
  u2 BIGINT(50) UNSIGNED DEFAULT '0',
  u3 BIGINT(50) UNSIGNED DEFAULT '0',
  u4 BIGINT(50) UNSIGNED DEFAULT '0',
  u5 BIGINT(50) UNSIGNED DEFAULT '0',
  u6 BIGINT(50) UNSIGNED DEFAULT '0',
  u7 BIGINT(50) UNSIGNED DEFAULT '0',
  u8 BIGINT(50) UNSIGNED DEFAULT '0',
  u9 BIGINT(50) UNSIGNED DEFAULT '0',
  u10 BIGINT(50) UNSIGNED DEFAULT '0',
  u11 TINYINT(1) UNSIGNED DEFAULT '0',          -- Hero (0 or 1)
  u99 BIGINT(50) UNSIGNED DEFAULT '0',          -- Siege weapons
  PRIMARY KEY (kid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Unit Types by Race**:
- Roman: Legionnaire, Praetorian, Imperian, etc.
- Teuton: Clubswinger, Spearman, Axeman, etc.
- Gaul: Phalanx, Swordsman, Pathfinder, etc.

---

#### `training` (Troop training queue)

**Purpose**: Troops being trained

```sql
CREATE TABLE training (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(6) UNSIGNED NOT NULL,
  nr TINYINT(2) UNSIGNED NOT NULL,              -- Unit type
  num BIGINT(50) UNSIGNED NOT NULL,             -- Quantity
  item_id TINYINT(2) UNSIGNED NOT NULL,         -- Building ID
  training_time BIGINT(25) UNSIGNED NOT NULL,   -- Total time
  commence BIGINT(25) UNSIGNED NOT NULL,        -- Start time
  end_time BIGINT(25) UNSIGNED NOT NULL,        -- Completion time
  PRIMARY KEY (id),
  KEY kid (kid),
  KEY item_id (item_id),
  KEY commence (commence),
  KEY nr (nr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Worker Process**: 
- Check `commence <= NOW()`
- Complete one unit at a time
- Update `commence` for next unit

---

#### `movement` (Troop movements)

**Purpose**: All troop movements (attacks, reinforcements, returns)

```sql
CREATE TABLE movement (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(6) UNSIGNED NOT NULL,                 -- From village
  to_kid INT(6) UNSIGNED NOT NULL,              -- To village
  race TINYINT(1) UNSIGNED NOT NULL,
  
  -- Troops
  u1 BIGINT(50) DEFAULT '0',
  u2 BIGINT(50) DEFAULT '0',
  u3 BIGINT(50) DEFAULT '0',
  u4 BIGINT(50) DEFAULT '0',
  u5 BIGINT(50) DEFAULT '0',
  u6 BIGINT(50) DEFAULT '0',
  u7 BIGINT(50) DEFAULT '0',
  u8 BIGINT(50) DEFAULT '0',
  u9 BIGINT(50) DEFAULT '0',
  u10 BIGINT(50) DEFAULT '0',
  u11 SMALLINT(1) UNSIGNED DEFAULT '0',         -- Hero
  
  -- Catapult targets
  ctar1 TINYINT(2) UNSIGNED DEFAULT '0',
  ctar2 TINYINT(2) UNSIGNED DEFAULT '0',
  
  spyType TINYINT(1) UNSIGNED DEFAULT '0',
  redeployHero TINYINT(1) UNSIGNED DEFAULT '0',
  mode TINYINT(1) UNSIGNED NOT NULL,            -- Attack type
  attack_type TINYINT(1) UNSIGNED NOT NULL,     -- Normal/raid
  
  start_time BIGINT(15) UNSIGNED NOT NULL,
  end_time BIGINT(15) UNSIGNED NOT NULL,
  
  data VARCHAR(255) DEFAULT '',                 -- Additional data (resources, etc.)
  markState TINYINT(1) DEFAULT '0',
  proc TINYINT(1) DEFAULT '0',                  -- Processed flag
  
  PRIMARY KEY (id),
  KEY attack_type (attack_type),
  KEY kid (kid),
  KEY to_kid (to_kid),
  KEY u11 (u11),
  KEY search (kid, to_kid, mode, attack_type),
  KEY end_time (end_time),
  KEY mode (mode),
  KEY proc (proc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Movement Types** (`mode`):
- 3: Attack
- 4: Raid
- 5: Return
- 6: Reinforcement
- 7: Spy

**Critical Indexes**:
- `end_time`: Worker checks `end_time <= NOW()` to process arrivals
- `search`: Display outgoing/incoming attacks
- `proc`: Mark as processed to avoid duplicate handling

---

#### `enforcement` (Reinforcements)

**Purpose**: Troops reinforcing another village

```sql
CREATE TABLE enforcement (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  kid INT(6) UNSIGNED DEFAULT '0',              -- From village
  to_kid INT(6) UNSIGNED DEFAULT '0',           -- At village
  race TINYINT(1) UNSIGNED DEFAULT '0',
  u1 BIGINT(50) UNSIGNED DEFAULT '0',
  u2 BIGINT(50) UNSIGNED DEFAULT '0',
  u3 BIGINT(50) UNSIGNED DEFAULT '0',
  u4 BIGINT(50) UNSIGNED DEFAULT '0',
  u5 BIGINT(50) UNSIGNED DEFAULT '0',
  u6 BIGINT(50) UNSIGNED DEFAULT '0',
  u7 BIGINT(50) UNSIGNED DEFAULT '0',
  u8 BIGINT(50) UNSIGNED DEFAULT '0',
  u9 BIGINT(50) UNSIGNED DEFAULT '0',
  u10 BIGINT(50) UNSIGNED DEFAULT '0',
  u11 TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id),
  KEY kid (kid),
  KEY to_kid (to_kid),
  KEY uid (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `trapped` (Trapped troops)

**Purpose**: Troops caught in traps

```sql
CREATE TABLE trapped (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(6) UNSIGNED DEFAULT '0',
  to_kid INT(6) UNSIGNED DEFAULT '0',
  race TINYINT(1) UNSIGNED DEFAULT '0',
  u1 BIGINT(50) UNSIGNED DEFAULT '0',
  u2 BIGINT(50) UNSIGNED DEFAULT '0',
  u3 BIGINT(50) UNSIGNED DEFAULT '0',
  u4 BIGINT(50) UNSIGNED DEFAULT '0',
  u5 BIGINT(50) UNSIGNED DEFAULT '0',
  u6 BIGINT(50) UNSIGNED DEFAULT '0',
  u7 BIGINT(50) UNSIGNED DEFAULT '0',
  u8 BIGINT(50) UNSIGNED DEFAULT '0',
  u9 BIGINT(50) UNSIGNED DEFAULT '0',
  u10 BIGINT(50) UNSIGNED DEFAULT '0',
  u11 TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id),
  KEY (kid),
  KEY (to_kid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `a2b` (Attacks to be executed)

**Purpose**: Scheduled attacks (pre-computed)

```sql
CREATE TABLE a2b (
  id BIGINT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  timestamp INT(10) UNSIGNED DEFAULT '0',
  timestamp_checksum VARCHAR(6) NOT NULL,
  to_kid INT(6) UNSIGNED NOT NULL,
  u1 BIGINT(50) UNSIGNED NOT NULL,
  u2 BIGINT(50) UNSIGNED NOT NULL,
  u3 BIGINT(50) UNSIGNED NOT NULL,
  u4 BIGINT(50) UNSIGNED NOT NULL,
  u5 BIGINT(50) UNSIGNED NOT NULL,
  u6 BIGINT(50) UNSIGNED NOT NULL,
  u7 BIGINT(50) UNSIGNED NOT NULL,
  u8 BIGINT(50) UNSIGNED NOT NULL,
  u9 BIGINT(50) UNSIGNED NOT NULL,
  u10 BIGINT(50) UNSIGNED NOT NULL,
  u11 TINYINT(1) UNSIGNED NOT NULL,
  attack_type TINYINT(1) UNSIGNED NOT NULL,
  redeployHero TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id),
  KEY (timestamp, timestamp_checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose**: Pre-compute attacks for performance

---

#### `ndata` (Battle reports)

**Purpose**: Combat reports and notifications

```sql
CREATE TABLE ndata (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  aid INT(11) UNSIGNED NOT NULL,
  uid INT(11) UNSIGNED NOT NULL,
  isEnforcement TINYINT(1) UNSIGNED NOT NULL,
  kid INT(6) UNSIGNED NOT NULL,
  to_kid INT(6) UNSIGNED NOT NULL,
  type TINYINT(2) UNSIGNED NOT NULL,            -- Report type
  bounty VARCHAR(255) NOT NULL,                 -- Stolen resources
  data TEXT NOT NULL,                           -- JSON battle data
  time INT(10) UNSIGNED NOT NULL,
  private_key VARCHAR(12) NOT NULL,             -- Share link
  viewed TINYINT(1) UNSIGNED NOT NULL,
  archive TINYINT(1) UNSIGNED NOT NULL,
  deleted TINYINT(1) UNSIGNED DEFAULT '0',
  losses SMALLINT(3) UNSIGNED DEFAULT '0',      -- Casualty percentage
  non_deletable TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id),
  KEY aid (aid),
  KEY uid (uid),
  KEY to_kid (to_kid),
  KEY deleted (deleted),
  KEY archive (archive),
  KEY type (type),
  KEY losses (losses),
  KEY viewed (viewed),
  KEY count (uid, archive, deleted, type),
  KEY search (uid, viewed, deleted)
) ROW_FORMAT=COMPRESSED ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Report Types**:
- 1: Attack report
- 2: Defense report
- 3: Spy report
- 4: Reinforcement arrival
- 5: Return

**Data Storage**: JSON-encoded battle results

**Compression**: `ROW_FORMAT=COMPRESSED` saves space on large reports

---

#### `raidlist` (Farm list targets)

**Purpose**: Automated farming targets

```sql
CREATE TABLE raidlist (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  lid INT(11) UNSIGNED NOT NULL,                -- Farm list ID
  kid INT(6) UNSIGNED NOT NULL,                 -- Target village
  distance DOUBLE(4, 1) UNSIGNED NOT NULL,
  u1 BIGINT(50) NOT NULL,
  u2 BIGINT(50) NOT NULL,
  u3 BIGINT(50) NOT NULL,
  u4 BIGINT(50) NOT NULL,
  u5 BIGINT(50) NOT NULL,
  u6 BIGINT(50) NOT NULL,
  u7 BIGINT(50) NOT NULL,
  u8 BIGINT(50) NOT NULL,
  u9 BIGINT(50) NOT NULL,
  u10 BIGINT(50) NOT NULL,
  PRIMARY KEY (id),
  KEY (lid, kid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `farmlist` (Farm lists)

**Purpose**: Farm list configuration

```sql
CREATE TABLE farmlist (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(10) UNSIGNED NOT NULL,                -- Village ID
  owner INT(10) UNSIGNED NOT NULL,
  name VARCHAR(45) NOT NULL,
  auto TINYINT(1) DEFAULT '0',                  -- Auto-raid enabled
  lastRaid INT(11) DEFAULT '0',
  randSec INT(11) DEFAULT '30',                 -- Randomization
  PRIMARY KEY (id),
  KEY (kid, owner, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `farmlist_last_reports` (Farm list report cache)

**Purpose**: Track last raid result for each target

```sql
CREATE TABLE farmlist_last_reports (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  kid INT(11) UNSIGNED NOT NULL,
  report_id INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY (uid, kid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `casualties` (Server-wide casualties)

**Purpose**: Daily casualty statistics

```sql
CREATE TABLE casualties (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  attacks INT(10) UNSIGNED DEFAULT '0',
  casualties BIGINT(50) UNSIGNED DEFAULT '0',
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY time (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 5. Alliance System Tables

#### `alidata` (Alliance information)

**Purpose**: Alliance metadata

```sql
CREATE TABLE alidata (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(25) NOT NULL,
  tag VARCHAR(8) NOT NULL,
  desc1 TEXT DEFAULT NULL,                      -- Description
  desc2 TEXT DEFAULT NULL,                      -- Internal description
  info1 TEXT DEFAULT NULL,                      -- Diplomacy info
  info2 TEXT DEFAULT NULL,                      -- Internal info
  forumLink VARCHAR(200) DEFAULT NULL,
  max TINYINT(2) UNSIGNED DEFAULT '0',          -- Max members
  
  -- Statistics
  total_attack_points BIGINT(255) DEFAULT '0',
  total_defense_points BIGINT(255) DEFAULT '0',
  week_attack_points BIGINT(255) DEFAULT '0',
  week_defense_points BIGINT(255) DEFAULT '0',
  week_robber_points BIGINT(255) DEFAULT '0',
  week_pop_changes BIGINT(255) DEFAULT '0',
  oldPop BIGINT(255) DEFAULT '0',
  
  -- Alliance bonuses
  training_bonus_level TINYINT(1) DEFAULT '0',
  training_bonus_contributions BIGINT(255) DEFAULT '0',
  armor_bonus_level TINYINT(1) DEFAULT '0',
  armor_bonus_contributions BIGINT(255) DEFAULT '0',
  cp_bonus_level TINYINT(1) DEFAULT '0',
  cp_bonus_contributions BIGINT(255) DEFAULT '0',
  trade_bonus_level TINYINT(1) DEFAULT '0',
  trade_bonus_contributions BIGINT(255) DEFAULT '0',
  
  PRIMARY KEY (id),
  KEY (tag, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Alliance Bonuses**: Players contribute resources to unlock bonuses

---

#### `ali_invite` (Alliance invitations)

**Purpose**: Pending alliance invitations

```sql
CREATE TABLE ali_invite (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  from_uid INT(11) UNSIGNED NOT NULL,
  aid INT(11) UNSIGNED NOT NULL,
  uid INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY aid (aid),
  KEY uid (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `ali_log` (Alliance log)

**Purpose**: Alliance event history

```sql
CREATE TABLE ali_log (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  aid INT(11) NOT NULL,
  type TINYINT(1) UNSIGNED NOT NULL,
  data TEXT NOT NULL,
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY (type),
  KEY (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Event Types**:
- 1: Member joined
- 2: Member left
- 3: Member kicked
- 4: Rank changed
- 5: Diplomacy established

---

#### `alistats` (Alliance statistics)

**Purpose**: Historical alliance stats

```sql
CREATE TABLE alistats (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  aid INT(11) UNSIGNED NOT NULL,
  killed_by BIGINT(255) UNSIGNED DEFAULT '0',
  stolen_by BIGINT(255) UNSIGNED DEFAULT '0',
  killed_of BIGINT(255) UNSIGNED DEFAULT '0',
  stolen_of BIGINT(255) UNSIGNED DEFAULT '0',
  total_off_point BIGINT(255) UNSIGNED DEFAULT '0',
  total_def_point BIGINT(255) UNSIGNED DEFAULT '0',
  time INT(10) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id),
  KEY aid (aid),
  KEY time (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `diplomacy` (Alliance relations)

**Purpose**: NAPs, wars, confederations

```sql
CREATE TABLE diplomacy (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  aid1 INT(10) UNSIGNED NOT NULL,
  aid2 INT(10) UNSIGNED NOT NULL,
  type TINYINT(1) UNSIGNED NOT NULL,            -- 1=NAP, 2=Confederation, 3=War
  accepted INT(1) DEFAULT '0',
  PRIMARY KEY (id),
  KEY (aid1, aid2, type, accepted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `alliance_notification` (Alliance notifications)

**Purpose**: Alliance-wide notifications

```sql
CREATE TABLE alliance_notification (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  aid INT(11) UNSIGNED NOT NULL,
  to_uid INT(11) UNSIGNED NOT NULL,
  type TINYINT(1) UNSIGNED NOT NULL,
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY (aid, to_uid, type, time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `alliance_bonus_upgrade_queue` (Bonus upgrades)

**Purpose**: Alliance bonus upgrade queue

```sql
CREATE TABLE alliance_bonus_upgrade_queue (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  aid INT(11) UNSIGNED NOT NULL,
  type TINYINT(1) UNSIGNED NOT NULL,
  time INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY (aid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `allimedal` (Alliance medals)

**Purpose**: Weekly alliance medals

```sql
CREATE TABLE allimedal (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  aid INT(11) UNSIGNED NOT NULL,
  category TINYINT(2) UNSIGNED NOT NULL,
  week INT(3) UNSIGNED NOT NULL,
  rank TINYINT(2) UNSIGNED NOT NULL,
  points VARCHAR(30) NOT NULL,
  img VARCHAR(10) NOT NULL,
  PRIMARY KEY (id),
  KEY aid (aid),
  KEY rank (rank),
  KEY category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### Forum Tables

**Purpose**: Alliance forum system

```sql
-- Forum categories
CREATE TABLE forum_forums (
  id INT(11) NOT NULL AUTO_INCREMENT,
  aid INT(11) UNSIGNED NOT NULL,
  name VARCHAR(20) NOT NULL,
  forum_desc VARCHAR(38) NOT NULL,
  area TINYINT(1) UNSIGNED NOT NULL,
  sitter TINYINT(1) UNSIGNED DEFAULT '0',
  pos INT(6) NOT NULL,
  PRIMARY KEY (id),
  KEY aid (aid),
  KEY area (area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Forum topics
CREATE TABLE forum_topic (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  forumId INT(11) UNSIGNED NOT NULL,
  thread VARCHAR(35) NOT NULL,
  close TINYINT(1) UNSIGNED DEFAULT '0',
  stick TINYINT(1) UNSIGNED DEFAULT '0',
  SurveyStartTime INT(10) UNSIGNED NOT NULL,
  Survey VARCHAR(60) NOT NULL,
  end_time INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY forumId (forumId, thread)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Forum posts
CREATE TABLE forum_post (
  id INT(11) NOT NULL AUTO_INCREMENT,
  aid INT(11) UNSIGNED NOT NULL,
  uid INT(11) UNSIGNED NOT NULL,
  forumId INT(11) UNSIGNED NOT NULL,
  topicId INT(11) UNSIGNED NOT NULL,
  post LONGTEXT NOT NULL,
  time INT(10) UNSIGNED NOT NULL,
  deleted TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id),
  KEY time (time),
  KEY forumId (forumId),
  KEY topicId (topicId),
  KEY deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Forum polls
CREATE TABLE forum_options (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  topicId INT(11) UNSIGNED NOT NULL,
  option_desc VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  KEY topicId (topicId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE forum_vote (
  id INT(11) NOT NULL AUTO_INCREMENT,
  uid INT(11) NOT NULL,
  topicId INT(11) NOT NULL,
  value INT(11) NOT NULL,
  PRIMARY KEY (id),
  KEY uid (uid, topicId, value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 6. Communication Tables

#### `mdata` (Messages)

**Purpose**: Player-to-player messages

```sql
CREATE TABLE mdata (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,                -- Sender
  to_uid INT(11) UNSIGNED NOT NULL,             -- Receiver
  topic VARCHAR(100) NOT NULL,
  message LONGTEXT NOT NULL,
  viewed TINYINT(1) UNSIGNED DEFAULT '0',
  archived TINYINT(1) UNSIGNED DEFAULT '0',
  delete_receiver SMALLINT(1) UNSIGNED DEFAULT '0',
  delete_sender SMALLINT(1) UNSIGNED DEFAULT '0',
  reported TINYINT(1) UNSIGNED DEFAULT '0',
  md5_checksum VARCHAR(32) DEFAULT '',
  mode TINYINT(1) UNSIGNED DEFAULT '0',
  time INT(10) UNSIGNED DEFAULT '0',
  autoType TINYINT(1) UNSIGNED DEFAULT '0',     -- System message
  isAlliance TINYINT(1) UNSIGNED DEFAULT '0',   -- Alliance message
  PRIMARY KEY (id),
  KEY (uid),
  KEY (to_uid),
  KEY search (uid, to_uid, viewed, delete_receiver)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Key Indexes**:
- `search`: Efficient inbox queries
- `uid`, `to_uid`: Sent/received lookups

---

#### `messages_report` (Reported messages)

**Purpose**: Flagged messages for moderation

```sql
CREATE TABLE messages_report (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  reported_uid INT(11) UNSIGNED NOT NULL,
  message_id INT(11) UNSIGNED NOT NULL,
  type VARCHAR(255) NOT NULL,
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `infobox` (System notifications)

**Purpose**: Admin broadcasts and system messages

```sql
CREATE TABLE infobox (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  forAll TINYINT(1) UNSIGNED DEFAULT '1',       -- Global or per-user
  uid INT(11) UNSIGNED NOT NULL,
  type TINYINT(2) UNSIGNED DEFAULT '0',
  params TEXT NOT NULL,
  readStatus TINYINT(1) UNSIGNED DEFAULT '0',
  del TINYINT(1) UNSIGNED DEFAULT '0',
  showFrom INT(10) UNSIGNED NOT NULL,
  showTo INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY uid (uid, forAll, readStatus, del, showFrom, showTo),
  KEY type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE infobox_read (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  infoId INT(11) UNSIGNED NOT NULL,
  uid INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY infoId (infoId, uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE infobox_delete (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  infoId INT(11) UNSIGNED NOT NULL,
  uid INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY infoId (infoId, uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 7. Hero System Tables

#### `hero` (Hero data)

**Purpose**: Player hero statistics

```sql
CREATE TABLE hero (
  uid INT(11) UNSIGNED NOT NULL,
  kid INT(6) UNSIGNED NOT NULL,                 -- Current location
  exp BIGINT(255) DEFAULT '0',
  health DOUBLE(13, 10) UNSIGNED DEFAULT '100.0000000000',
  itemHealth INT(11) UNSIGNED DEFAULT '0',      -- Bandages used
  power SMALLINT(3) UNSIGNED DEFAULT '0',       -- Fighting strength
  offBonus SMALLINT(3) UNSIGNED DEFAULT '0',
  defBonus SMALLINT(3) UNSIGNED DEFAULT '0',
  production SMALLINT(3) UNSIGNED DEFAULT '4',  -- Resource production
  productionType SMALLINT(1) UNSIGNED DEFAULT '0',
  lastupdate INT(10) UNSIGNED DEFAULT '0',
  hide TINYINT(1) UNSIGNED DEFAULT '1',
  PRIMARY KEY (uid),
  KEY health (health),
  KEY lastupdate (lastupdate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Hero Attributes**: Players distribute points to fighting, off/def bonus, or production

---

#### `items` (Hero items)

**Purpose**: Hero inventory items

```sql
CREATE TABLE items (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  btype TINYINT(2) UNSIGNED NOT NULL,           -- Bag type
  type SMALLINT(3) UNSIGNED NOT NULL,           -- Item type
  num BIGINT(100) UNSIGNED NOT NULL,            -- Quantity/durability
  placeId INT(11) UNSIGNED NOT NULL,            -- 0=bag, >0=equipped
  proc TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id),
  KEY (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Item Types**:
- Helmets, armor, weapons, boots, horses
- Consumables: bandages, ointments, cages

---

#### `inventory` (Equipped items)

**Purpose**: Currently equipped hero items

```sql
CREATE TABLE inventory (
  uid INT(11) UNSIGNED NOT NULL,
  helmet INT(11) UNSIGNED DEFAULT '0',          -- Item ID
  body INT(11) UNSIGNED DEFAULT '0',
  leftHand INT(11) UNSIGNED DEFAULT '0',
  rightHand INT(11) UNSIGNED DEFAULT '0',
  shoes INT(11) UNSIGNED DEFAULT '0',
  horse INT(11) UNSIGNED DEFAULT '0',
  bag INT(11) UNSIGNED DEFAULT '0',             -- Bag size item
  lastupdate INT(10) UNSIGNED DEFAULT '0',
  lastWaterBucketUse INT(10) UNSIGNED DEFAULT '0',
  PRIMARY KEY (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `adventure` (Hero adventures)

**Purpose**: Hero adventure quests

```sql
CREATE TABLE adventure (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) NOT NULL,
  kid INT(6) UNSIGNED NOT NULL,
  dif TINYINT(1) NOT NULL,                      -- Difficulty
  time INT(10) UNSIGNED NOT NULL,
  end TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id),
  KEY uid (uid),
  KEY kid (kid),
  KEY time (time),
  KEY end (end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `auction` (Auction house)

**Purpose**: Hero item auctions

```sql
CREATE TABLE auction (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  btype TINYINT(2) UNSIGNED NOT NULL,
  type SMALLINT(3) UNSIGNED NOT NULL,
  num BIGINT(100) UNSIGNED NOT NULL,
  bids INT(11) UNSIGNED DEFAULT '0',
  silver INT(10) UNSIGNED DEFAULT '0',
  maxSilver INT(10) UNSIGNED DEFAULT '0',
  activeUid INT(10) UNSIGNED DEFAULT '0',       -- Current bidder
  activeId INT(11) UNSIGNED DEFAULT '0',
  secure_id VARCHAR(100) DEFAULT '',
  time INT(10) UNSIGNED NOT NULL,
  finish TINYINT(1) UNSIGNED DEFAULT '0',
  cancel TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id),
  KEY activeUid (activeUid),
  KEY finish (finish),
  KEY cancel (cancel),
  KEY uid (uid),
  KEY secure_id (secure_id),
  KEY time (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bids (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  auctionId INT(11) UNSIGNED NOT NULL,
  outbid TINYINT(1) DEFAULT '0',
  del TINYINT(1) DEFAULT '0',
  PRIMARY KEY (id),
  KEY uid (uid, auctionId, outbid, del)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `accounting` (Silver transactions)

**Purpose**: Hero silver transaction log

```sql
CREATE TABLE accounting (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  cause VARCHAR(100) NOT NULL,
  reserve INT(10) NOT NULL,
  balance INT(10) UNSIGNED NOT NULL,
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY uid (uid, balance, time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 8. Economy & Trade Tables

#### `market` (Market offers)

**Purpose**: Resource exchange marketplace

```sql
CREATE TABLE market (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  aid INT(11) UNSIGNED NOT NULL,
  kid INT(6) UNSIGNED NOT NULL,
  x SMALLINT(4) NOT NULL,
  y SMALLINT(4) NOT NULL,
  rate DOUBLE UNSIGNED NOT NULL,
  needType TINYINT(1) UNSIGNED NOT NULL,        -- Resource type
  needValue BIGINT(50) UNSIGNED NOT NULL,
  giveType TINYINT(1) UNSIGNED NOT NULL,
  giveValue BIGINT(50) NOT NULL,
  maxtime INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY aid (aid),
  KEY rate (rate),
  KEY rType (needType),
  KEY giveType (giveType),
  KEY x (x, y)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Exchange Rate**: `rate = giveValue / needValue`

---

#### `send` (Resource transports)

**Purpose**: Active resource transports

```sql
CREATE TABLE send (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(6) UNSIGNED NOT NULL,
  to_kid INT(6) UNSIGNED NOT NULL,
  wood BIGINT(50) UNSIGNED NOT NULL,
  clay BIGINT(50) UNSIGNED NOT NULL,
  iron BIGINT(50) UNSIGNED NOT NULL,
  crop BIGINT(50) UNSIGNED NOT NULL,
  x TINYINT(1) UNSIGNED NOT NULL,               -- Merchant count
  mode TINYINT(1) UNSIGNED NOT NULL,
  end_time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY end_time (end_time),
  KEY kid (kid),
  KEY to_kid (to_kid),
  KEY mode (mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `traderoutes` (Automated trade routes)

**Purpose**: Recurring resource transfers

```sql
CREATE TABLE traderoutes (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(6) UNSIGNED NOT NULL,
  to_kid INT(6) UNSIGNED NOT NULL,
  r1 BIGINT(50) UNSIGNED NOT NULL,              -- Wood
  r2 BIGINT(50) UNSIGNED NOT NULL,              -- Clay
  r3 BIGINT(50) UNSIGNED NOT NULL,              -- Iron
  r4 BIGINT(50) UNSIGNED NOT NULL,              -- Crop
  enabled TINYINT(1) UNSIGNED NOT NULL,
  start_hour INT(10) UNSIGNED NOT NULL,
  times INT(10) UNSIGNED NOT NULL,              -- Deliveries per day
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY kid (kid, enabled, time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `artefacts` (Artifacts)

**Purpose**: Special artifacts (endgame)

```sql
CREATE TABLE artefacts (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  kid INT(6) UNSIGNED NOT NULL,
  release_kid INT(6) UNSIGNED DEFAULT '0',
  type SMALLINT(3) UNSIGNED NOT NULL,
  size TINYINT(1) UNSIGNED NOT NULL,            -- 1=village, 2=alliance
  conquered INT(11) UNSIGNED NOT NULL,
  lastupdate INT(10) UNSIGNED DEFAULT '0',
  num SMALLINT(3) NOT NULL,
  effecttype SMALLINT(2) NOT NULL,
  effect DOUBLE NOT NULL,
  aoe INT(10) NOT NULL,
  status TINYINT(1) DEFAULT '1',
  active TINYINT(1) DEFAULT '0',
  PRIMARY KEY (id),
  KEY (kid),
  KEY (size),
  KEY (conquered),
  KEY (status),
  KEY (type),
  KEY (effecttype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE artlog (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  artId INT(11) UNSIGNED NOT NULL,
  uid INT(11) DEFAULT NULL,
  name VARCHAR(15) DEFAULT NULL,
  kid INT(6) UNSIGNED NOT NULL,
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY artId (artId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Artifact Effects**:
- 2x troop production
- 1.5x resource production
- Faster building
- Larger cranny
- Spy defense

---

### 9. Quests & Events Tables

#### `daily_quest` (Daily quests)

**Purpose**: Daily quest progress

```sql
CREATE TABLE daily_quest (
  uid INT(10) UNSIGNED NOT NULL,
  qst1 TINYINT(1) UNSIGNED DEFAULT '0',
  qst2 TINYINT(1) UNSIGNED DEFAULT '0',
  qst3 TINYINT(1) UNSIGNED DEFAULT '0',
  qst4 TINYINT(1) UNSIGNED DEFAULT '0',
  qst5 TINYINT(1) UNSIGNED DEFAULT '0',
  qst6 TINYINT(1) UNSIGNED DEFAULT '0',
  qst7 TINYINT(1) UNSIGNED DEFAULT '0',
  qst8 TINYINT(1) UNSIGNED DEFAULT '0',
  qst9 TINYINT(1) UNSIGNED DEFAULT '0',
  qst10 TINYINT(1) UNSIGNED DEFAULT '0',
  qst11 TINYINT(1) UNSIGNED DEFAULT '0',
  alliance_contribution BIGINT(255) UNSIGNED DEFAULT '0',
  reward1Type TINYINT(1) UNSIGNED DEFAULT '0',
  reward1Done TINYINT(1) UNSIGNED DEFAULT '0',
  reward2Type TINYINT(1) UNSIGNED DEFAULT '0',
  reward2Done TINYINT(1) UNSIGNED DEFAULT '0',
  reward3Type TINYINT(1) UNSIGNED DEFAULT '0',
  reward3Done TINYINT(1) UNSIGNED DEFAULT '0',
  reward4Type TINYINT(1) UNSIGNED DEFAULT '0',
  reward4Done TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `medal` (Player medals)

**Purpose**: Weekly achievement medals

```sql
CREATE TABLE medal (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  category TINYINT(2) UNSIGNED NOT NULL,
  week SMALLINT(3) UNSIGNED NOT NULL,
  rank TINYINT(2) UNSIGNED NOT NULL,
  points VARCHAR(15) NOT NULL,
  img VARCHAR(10) NOT NULL,
  PRIMARY KEY (id),
  KEY uid (uid),
  KEY rank (rank),
  KEY category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `autoExtend` (Plus feature extensions)

**Purpose**: Automatic gold feature renewals

```sql
CREATE TABLE autoExtend (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  type TINYINT(1) UNSIGNED NOT NULL,
  commence INT(10) UNSIGNED NOT NULL,
  lastChecked INT(11) UNSIGNED DEFAULT '0',
  enabled TINYINT(1) UNSIGNED NOT NULL,
  finished TINYINT(1) UNSIGNED DEFAULT '0',
  PRIMARY KEY (id),
  KEY (uid),
  KEY (commence, enabled, finished),
  KEY (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `buyGoldMessages` (Gold purchase notifications)

**Purpose**: Gold purchase confirmations

```sql
CREATE TABLE buyGoldMessages (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(10) UNSIGNED NOT NULL,
  gold INT(10) UNSIGNED NOT NULL,
  type TINYINT(1) UNSIGNED NOT NULL,
  trackingCode VARCHAR(100) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `voting_reward_queue` (Voting rewards)

**Purpose**: Rewards from voting sites

```sql
CREATE TABLE voting_reward_queue (
  id INT(11) NOT NULL AUTO_INCREMENT,
  uid INT(10) UNSIGNED NOT NULL,
  votingName VARCHAR(25) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 10. Administration & Logging Tables

#### `general_log` (General activity log)

**Purpose**: Comprehensive activity logging

```sql
CREATE TABLE general_log (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) NOT NULL,
  type VARCHAR(50) NOT NULL,
  log_info LONGTEXT NOT NULL,
  time INT(11) NOT NULL,
  PRIMARY KEY (id),
  KEY uid (uid),
  KEY type (type),
  KEY time (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Log Types**:
- `login`, `logout`
- `village_expand`
- `gold_purchase`
- `resource_trade`

---

#### `admin_log` (Admin actions)

**Purpose**: Administrative action audit log

```sql
CREATE TABLE admin_log (
  id INT(11) NOT NULL AUTO_INCREMENT,
  ip VARCHAR(100) NOT NULL,
  log TEXT,
  time INT(25) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `log_ip` (IP address log)

**Purpose**: Track player IP addresses

```sql
CREATE TABLE log_ip (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) NOT NULL,
  ip BIGINT(12) NOT NULL,
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY uid (uid, time, ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Multi-account Detection**: Compare IPs across users

---

#### `multiaccount_log` (Multi-account events)

**Purpose**: Multi-account detection log

```sql
CREATE TABLE multiaccount_log (
  id INT(11) NOT NULL AUTO_INCREMENT,
  uid INT(11) NOT NULL,
  to_uid INT(11) NOT NULL,
  type INT(11) NOT NULL,
  time INT(11) NOT NULL,
  PRIMARY KEY (id),
  KEY type (type),
  KEY to_uid (to_uid),
  KEY uid (uid),
  KEY time (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE multiaccount_users (
  id INT(11) NOT NULL AUTO_INCREMENT,
  uid INT(10) UNSIGNED NOT NULL,
  data TEXT NOT NULL,
  priority BIGINT(50) UNSIGNED NOT NULL,
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `banHistory` & `banQueue` (Bans)

**Purpose**: Player ban management

```sql
CREATE TABLE banHistory (
  id INT(11) NOT NULL AUTO_INCREMENT,
  uid INT(11) NOT NULL,
  reason VARCHAR(100) NOT NULL,
  time INT(11) NOT NULL,
  end INT(11) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY uid (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE banQueue (
  id INT(11) NOT NULL AUTO_INCREMENT,
  uid INT(11) DEFAULT NULL,
  reason VARCHAR(100) NOT NULL,
  time INT(11) DEFAULT NULL,
  end INT(11) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `transfer_gold_log` (Gold transfers)

**Purpose**: Track gold transfers between players

```sql
CREATE TABLE transfer_gold_log (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) NOT NULL,
  to_uid INT(11) NOT NULL,
  amount VARCHAR(50) NOT NULL,
  time INT(11) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### `activation_progress` (Activation tracking)

**Purpose**: Email verification progress

```sql
CREATE TABLE activation_progress (
  id INT(11) NOT NULL AUTO_INCREMENT,
  uid INT(10) UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  activationCode VARCHAR(30) NOT NULL,
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY uid (uid, email, activationCode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 11. Game Configuration Tables

#### `config` (Server configuration)

**Purpose**: World-wide settings

```sql
CREATE TABLE config (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  startTime INT(10) UNSIGNED DEFAULT '0',
  map_size INT(10) UNSIGNED DEFAULT '0',
  worldUniqueId INT(10) UNSIGNED DEFAULT '0',
  patchVersion INT(10) UNSIGNED DEFAULT '0',
  installed TINYINT(1) UNSIGNED DEFAULT '0',
  automationState TINYINT(1) UNSIGNED DEFAULT '1',
  
  -- Truce settings
  truceFrom INT(10) UNSIGNED DEFAULT '0',
  truceTo INT(10) UNSIGNED DEFAULT '0',
  truceReasonId TINYINT(1) UNSIGNED DEFAULT '0',
  
  -- Server state
  serverFinished TINYINT(1) UNSIGNED DEFAULT '0',
  serverFinishTime INT(11) UNSIGNED DEFAULT '0',
  maintenance TINYINT(1) UNSIGNED DEFAULT '0',
  
  -- Scheduled jobs
  lastSystemCleanup INT(10) UNSIGNED DEFAULT '0',
  lastFakeAuction INT(10) UNSIGNED DEFAULT '0',
  lastNatarsExpand INT(10) UNSIGNED DEFAULT '0',
  lastDailyGold INT(10) UNSIGNED DEFAULT '0',
  lastDailyQuestReset INT(10) UNSIGNED DEFAULT '0',
  lastMedalsGiven INT(10) UNSIGNED DEFAULT '0',
  lastAllianceContributeReset INT(10) UNSIGNED DEFAULT '0',
  lastBackup INT(10) UNSIGNED DEFAULT '0',
  
  -- Endgame
  ArtifactsReleased TINYINT(1) UNSIGNED DEFAULT '0',
  WWPlansReleased TINYINT(1) UNSIGNED DEFAULT '0',
  
  -- Messages
  loginInfoTitle VARCHAR(100) NOT NULL,
  loginInfoHTML LONGTEXT NOT NULL,
  message LONGTEXT NOT NULL,
  
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Usage**: Single row (id=1) stores all world settings

---

#### `summary` (World statistics)

**Purpose**: Overall world statistics

```sql
CREATE TABLE summary (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  players_count INT(11) DEFAULT '0',
  roman_players_count INT(11) DEFAULT '0',
  teuton_players_count INT(11) DEFAULT '0',
  gaul_players_count INT(11) DEFAULT '0',
  egyptians_players_count INT(11) DEFAULT '0',
  huns_players_count INT(11) DEFAULT '0',
  first_village_player_name VARCHAR(255) DEFAULT NULL,
  first_village_time INT(11) DEFAULT '0',
  first_art_player_name VARCHAR(255) DEFAULT NULL,
  first_art_time INT(11) DEFAULT '0',
  first_ww_plan_player_name VARCHAR(255) DEFAULT NULL,
  first_ww_plan_time INT(11) DEFAULT '0',
  first_ww_player_name VARCHAR(255) DEFAULT NULL,
  first_ww_time INT(11) DEFAULT '0',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO summary (id) VALUES (1);
```

---

#### Miscellaneous Tables

```sql
-- User links widget
CREATE TABLE links (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT(11) UNSIGNED NOT NULL,
  name VARCHAR(30) NOT NULL,
  url VARCHAR(255) NOT NULL,
  pos INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password change queue
CREATE TABLE newproc (
  uid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  cpw VARCHAR(30) NOT NULL,                     -- Current password
  npw VARCHAR(45) NOT NULL,                     -- New password
  time INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email change queue
CREATE TABLE changeEmail (
  uid INT(11) UNSIGNED NOT NULL,
  email VARCHAR(99) NOT NULL,
  code1 VARCHAR(5) NOT NULL,
  code2 VARCHAR(5) NOT NULL,
  PRIMARY KEY (uid),
  KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login session tokens
CREATE TABLE login_handshake (
  id INT(11) NOT NULL AUTO_INCREMENT,
  uid INT(10) UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL,
  isSitter TINYINT(1) DEFAULT '0',
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Oasis deletion queue
CREATE TABLE odelete (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  kid INT(6) UNSIGNED NOT NULL,
  oid INT(6) UNSIGNED NOT NULL,
  end_time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY kid (oid),
  KEY (end_time, oid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Map blocks/marks
CREATE TABLE blocks (
  id INT(11) NOT NULL AUTO_INCREMENT,
  kid INT(11) NOT NULL,
  map_id INT(11) NOT NULL,
  PRIMARY KEY (id),
  KEY kid (kid),
  KEY map_id (map_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE marks (
  id INT(11) NOT NULL AUTO_INCREMENT,
  kid INT(6) UNSIGNED NOT NULL,
  map_id INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY (kid),
  KEY map_id (map_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notification queue
CREATE TABLE notificationQueue (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  message TEXT NOT NULL,
  time INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Performance Optimization

### Index Strategy

#### High-Traffic Query Patterns

**1. Player Login**
```sql
SELECT * FROM users WHERE name=? AND password=?;
```
**Index**: `KEY findPlayer (name, email)` covers name lookups

**2. Village List**
```sql
SELECT * FROM vdata WHERE owner=? ORDER BY capital DESC, kid ASC;
```
**Index**: `KEY owner (owner)` + sort in application

**3. Troop Movements**
```sql
SELECT * FROM movement 
WHERE to_kid=? AND end_time > ? 
ORDER BY end_time ASC;
```
**Index**: `KEY (to_kid)` + `KEY end_time (end_time)`

**4. Battle Reports**
```sql
SELECT * FROM ndata 
WHERE uid=? AND deleted=0 AND archive=0 
ORDER BY time DESC LIMIT 20;
```
**Index**: `KEY search (uid, viewed, deleted)` composite index

**5. Market Offers**
```sql
SELECT * FROM market 
WHERE needType=? 
ORDER BY rate ASC LIMIT 100;
```
**Index**: `KEY rType (needType)` + `KEY rate (rate)`

---

### Query Optimization Recommendations

#### 1. Resource Update (High Frequency)

**Current**:
```sql
UPDATE vdata 
SET wood=?, clay=?, iron=?, crop=?, lastmupdate=? 
WHERE kid=?;
```

**Optimization**:
- **Index**: `PRIMARY KEY (kid)` already optimal
- **Recommendation**: Batch updates when possible
- **Caching**: Redis cache for resource calculations

**Pattern**:
```php
// Calculate resources in application
$resources = calculateResources($village, $now);

// Single update
$stmt = $db->prepare("UPDATE vdata SET wood=?, clay=?, iron=?, crop=?, lastmupdate=? WHERE kid=?");
$stmt->execute([$resources['wood'], $resources['clay'], $resources['iron'], $resources['crop'], $now, $kid]);
```

---

#### 2. Worker Queue Processing

**Movement Arrivals**:
```sql
SELECT * FROM movement 
WHERE end_time <= ? AND proc=0 
ORDER BY end_time ASC LIMIT 100;
```

**Index**: 
```sql
KEY end_time (end_time),
KEY proc (proc)
```

**Optimization**: Composite index
```sql
KEY worker_queue (proc, end_time)
```

**Building Completions**:
```sql
SELECT * FROM building_upgrade 
WHERE commence <= ? 
ORDER BY commence ASC LIMIT 100;
```

**Index**: `KEY (commence)`

---

#### 3. Ranking Queries

**Top Attackers**:
```sql
SELECT id, name, total_attack_points 
FROM users 
WHERE hidden=0 
ORDER BY total_attack_points DESC 
LIMIT 100;
```

**Current Index**:
```sql
KEY statistics (
  countryFlag, total_attack_points, total_defense_points, 
  week_attack_points, week_defense_points, week_robber_points, 
  oldRank, total_pop, total_villages, max_off_point, max_def_point, hidden
)
```

**Optimization**: Too many columns, consider separate indexes:
```sql
KEY rank_attack (hidden, total_attack_points DESC)
KEY rank_defense (hidden, total_defense_points DESC)
KEY rank_population (hidden, total_pop DESC)
```

---

### Sharding Strategy

**Current Implementation**: Database-per-world

**Advantages**:
1. **Complete isolation**: No cross-world queries
2. **Independent scaling**: Move worlds to different servers
3. **Easy backups**: Per-world dumps
4. **Schema flexibility**: Upgrade worlds independently

**Scaling Pattern**:
```
MySQL Master (ts1, ts2, ts3)
    ↓
MySQL Slave (ts4, ts5, ts6)
    ↓
MySQL Slave (demo, test)
```

**World Distribution**:
- **High population worlds**: Dedicated MySQL instance
- **Low population worlds**: Shared MySQL instance (3-5 worlds)

---

### Connection Pooling

**ServerDB Implementation**: 
- Pools connections by config file hash
- Reuses connections across requests
- Limits: 500 max connections (configured in `my.cnf`)

**Recommendations**:
1. **PHP-FPM**: Use persistent connections (`PDO::ATTR_PERSISTENT`)
2. **Connection timeout**: Set `wait_timeout=600` (10 minutes)
3. **Max connections per world**: Monitor with `SHOW STATUS LIKE 'Threads_connected'`

---

### InnoDB Optimizations

**Buffer Pool Size**: `innodb_buffer_pool_size = 2G`
- **Recommendation**: 70-80% of available RAM
- **Current**: 2GB for medium-traffic worlds
- **High-traffic**: 4-8GB

**Log File Size**: `innodb_log_file_size = 512M`
- **Impact**: Larger logs = fewer checkpoints = better write performance
- **Trade-off**: Longer crash recovery

**Flush Method**: `innodb_flush_method = O_DIRECT`
- **Benefit**: Bypass OS cache, avoid double buffering
- **Ideal for**: Dedicated database servers

---

## Integration Points

### 1. PostgreSQL ↔ MySQL Architecture

**PostgreSQL (Global)**:
- User authentication (`accounts` table)
- World registry (`gameServers` table)
- AI-NPC system (`ai_npcs`, `ai_conversation_history`)
- Cross-world analytics

**MySQL (Per-World)**:
- Game state (villages, troops, resources)
- Player progress (hero, quests, buildings)
- Alliance system
- Combat and movements

**Strict Separation**: 
- No foreign keys between databases
- No JOIN queries across databases
- Communication via application layer only

---

### 2. Cross-Database Query Pattern

**Example: Get player villages with global account info**

```php
<?php
// Step 1: Query PostgreSQL for account data
$globalDB = DB::getInstance(); // PostgreSQL
$accountStmt = $globalDB->prepare("SELECT id, email, created_at FROM accounts WHERE username=?");
$accountStmt->execute([$username]);
$account = $accountStmt->fetch(PDO::FETCH_ASSOC);

// Step 2: Get world configuration
$worldStmt = $globalDB->prepare("SELECT configFileLocation FROM gameServers WHERE worldId=?");
$worldStmt->execute([$worldId]);
$world = $worldStmt->fetch(PDO::FETCH_ASSOC);

// Step 3: Query MySQL for game data
$gameDB = ServerDB::getInstance($world['configFileLocation']); // MySQL
$villageStmt = $gameDB->prepare("SELECT * FROM vdata WHERE owner=?");
$villageStmt->execute([$userId]);
$villages = $villageStmt->fetchAll(PDO::FETCH_ASSOC);

// Step 4: Combine results
return [
    'account' => $account,
    'villages' => $villages
];
```

**Key Points**:
- **Two separate connections**: One to PostgreSQL, one to MySQL
- **No database-level joins**: All merging done in application
- **Security**: World isolation prevents cross-world data leaks

---

### 3. Worker Integration

**Worker Architecture**:
```
PostgreSQL (job queue)
    ↓
Workers poll queue
    ↓
Load world config from PostgreSQL
    ↓
Connect to MySQL world database
    ↓
Process game mechanics
    ↓
Update MySQL
    ↓
Log to PostgreSQL
```

**Example Worker**: Building Completion

```php
<?php
// Poll PostgreSQL for worlds needing processing
$globalDB = DB::getInstance();
$stmt = $globalDB->query("SELECT worldId, configFileLocation FROM gameServers WHERE automationEnabled=1");

while ($world = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Connect to world MySQL database
    $worldDB = ServerDB::getInstance($world['configFileLocation']);
    
    // Find completed buildings
    $buildingStmt = $worldDB->prepare("
        SELECT * FROM building_upgrade 
        WHERE commence <= ? 
        ORDER BY commence ASC 
        LIMIT 100
    ");
    $buildingStmt->execute([time()]);
    
    while ($building = $buildingStmt->fetch(PDO::FETCH_ASSOC)) {
        // Complete building in MySQL
        completeBuildingUpgrade($worldDB, $building);
        
        // Log to PostgreSQL (optional)
        logWorkerAction($globalDB, 'building_complete', $building);
    }
}
```

---

### 4. AI-NPC Integration

**PostgreSQL AI Tables**:
- `ai_npcs`: NPC definitions
- `ai_conversation_history`: Chat logs
- `ai_npc_actions`: Scheduled actions

**MySQL Game State**:
- `users`: NPC player accounts
- `vdata`: NPC villages
- `movement`: NPC troop movements

**Integration Flow**:
```
AI Service (PostgreSQL)
    ↓
Decide NPC action (attack, trade, chat)
    ↓
Load target world config
    ↓
Connect to MySQL world database
    ↓
Execute game action (send troops, build, etc.)
    ↓
Log conversation to PostgreSQL
```

**Example**: AI-NPC Attacks Player

```php
<?php
// AI decision in PostgreSQL
$aiDB = DB::getInstance(); // PostgreSQL
$npcStmt = $aiDB->prepare("SELECT * FROM ai_npcs WHERE worldId=? AND status='active'");
$npcStmt->execute([$worldId]);

while ($npc = $npcStmt->fetch(PDO::FETCH_ASSOC)) {
    // Get world config
    $world = Server::getServerByWId($worldId);
    
    // Connect to game database
    $gameDB = ServerDB::getInstance($world['configFileLocation']); // MySQL
    
    // Find NPC's villages
    $villageStmt = $gameDB->prepare("SELECT * FROM vdata WHERE owner=?");
    $villageStmt->execute([$npc['game_user_id']]);
    $npcVillage = $villageStmt->fetch(PDO::FETCH_ASSOC);
    
    // Find target player
    $targetVillage = findNearbyPlayerVillage($gameDB, $npcVillage);
    
    // Launch attack in MySQL
    launchAttack($gameDB, $npcVillage['kid'], $targetVillage['kid'], $troops);
    
    // Log to PostgreSQL
    $logStmt = $aiDB->prepare("INSERT INTO ai_npc_actions (npc_id, action_type, target, timestamp) VALUES (?, ?, ?, ?)");
    $logStmt->execute([$npc['id'], 'attack', $targetVillage['kid'], time()]);
}
```

---

## Common Query Patterns

### Player Queries

**1. Get Player Profile**
```sql
SELECT u.*, h.exp, h.power 
FROM users u 
LEFT JOIN hero h ON u.id = h.uid 
WHERE u.id = ?;
```

**2. Get Player Villages**
```sql
SELECT v.*, 
  (SELECT COUNT(*) FROM movement WHERE kid=v.kid AND end_time > ?) as outgoing,
  (SELECT COUNT(*) FROM movement WHERE to_kid=v.kid AND end_time > ?) as incoming
FROM vdata v 
WHERE v.owner = ? 
ORDER BY v.capital DESC, v.kid ASC;
```

---

### Alliance Queries

**3. Get Alliance Members**
```sql
SELECT u.id, u.name, u.total_pop, u.total_villages, u.alliance_role
FROM users u
WHERE u.aid = ?
ORDER BY u.total_pop DESC;
```

**4. Get Alliance Statistics**
```sql
SELECT a.*, COUNT(u.id) as member_count, SUM(u.total_pop) as total_population
FROM alidata a
LEFT JOIN users u ON a.id = u.aid
WHERE a.id = ?
GROUP BY a.id;
```

---

### Combat Queries

**5. Get Incoming Attacks**
```sql
SELECT m.*, 
  u.name as attacker_name,
  v1.name as from_village,
  v2.name as to_village
FROM movement m
LEFT JOIN users u ON m.kid IN (SELECT kid FROM vdata WHERE owner=u.id)
LEFT JOIN vdata v1 ON m.kid = v1.kid
LEFT JOIN vdata v2 ON m.to_kid = v2.kid
WHERE m.to_kid IN (SELECT kid FROM vdata WHERE owner=?)
  AND m.end_time > ?
  AND m.mode IN (3, 4)
ORDER BY m.end_time ASC;
```

**6. Get Battle Reports**
```sql
SELECT n.*, u.name as opponent_name
FROM ndata n
LEFT JOIN users u ON (n.uid = u.id OR n.to_kid IN (SELECT kid FROM vdata WHERE owner=u.id))
WHERE n.uid = ? 
  AND n.deleted = 0
  AND n.type IN (1, 2, 3)
ORDER BY n.time DESC
LIMIT 50;
```

---

### Economy Queries

**7. Get Market Offers**
```sql
SELECT m.*, v.name as village_name, u.name as player_name
FROM market m
LEFT JOIN vdata v ON m.kid = v.kid
LEFT JOIN users u ON v.owner = u.id
WHERE m.needType = ?
ORDER BY m.rate ASC
LIMIT 100;
```

**8. Get Active Transports**
```sql
SELECT s.*, 
  v1.name as from_village,
  v2.name as to_village
FROM send s
LEFT JOIN vdata v1 ON s.kid = v1.kid
LEFT JOIN vdata v2 ON s.to_kid = v2.kid
WHERE s.kid IN (SELECT kid FROM vdata WHERE owner=?)
  AND s.end_time > ?
ORDER BY s.end_time ASC;
```

---

### Worker Queries

**9. Process Building Completions**
```sql
SELECT b.*, v.owner, v.name
FROM building_upgrade b
LEFT JOIN vdata v ON b.kid = v.kid
WHERE b.commence <= ?
ORDER BY b.commence ASC
LIMIT 100;
```

**10. Process Troop Arrivals**
```sql
SELECT m.*, 
  v1.owner as from_owner,
  v2.owner as to_owner
FROM movement m
LEFT JOIN vdata v1 ON m.kid = v1.kid
LEFT JOIN vdata v2 ON m.to_kid = v2.kid
WHERE m.end_time <= ? AND m.proc = 0
ORDER BY m.end_time ASC
LIMIT 100;
```

---

## Comparison with PostgreSQL

### Database Separation

| Aspect | PostgreSQL | MySQL |
|--------|-----------|-------|
| **Purpose** | Global data, AI, authentication | Per-world game state |
| **Scope** | Single database for entire system | One database per world |
| **Data Type** | Accounts, worlds, AI conversations | Villages, troops, battles |
| **Scaling** | Vertical (larger instance) | Horizontal (more worlds) |
| **Backups** | Full system backup | Per-world backups |
| **Schema Changes** | Affects all users | Per-world rollout possible |

---

### Why Two Databases?

**PostgreSQL Strengths**:
- **JSONB**: Store AI conversation context
- **Full-text search**: Search player names globally
- **PostGIS**: Geographic data (future feature)
- **Advanced querying**: Complex analytics

**MySQL Strengths**:
- **High-concurrency writes**: InnoDB row-level locking
- **Simple sharding**: Database-per-world pattern
- **Proven at scale**: Travian-style games use MySQL
- **Lower latency**: Simpler query planner for game queries

**Decision**:
- Use PostgreSQL for **complex, infrequent queries**
- Use MySQL for **simple, high-frequency game queries**

---

### Data Flow

```
User Registration
    ↓
PostgreSQL: Insert into accounts
    ↓
Create world entry in gameServers
    ↓
MySQL: Insert into users (world-specific)
    ↓
MySQL: Create starting village in vdata
```

```
User Login
    ↓
PostgreSQL: Verify account credentials
    ↓
PostgreSQL: Fetch user's worlds
    ↓
MySQL: Load game state for selected world
    ↓
Return combined data to client
```

```
AI-NPC Action
    ↓
PostgreSQL: AI decides action
    ↓
PostgreSQL: Load world config
    ↓
MySQL: Execute game action (attack, build)
    ↓
PostgreSQL: Log action for learning
```

---

## Conclusion

### MySQL Schema Summary

The MySQL game schema contains **90 tables** organized into:
- **Player systems**: Accounts, villages, hero progression
- **Military systems**: Units, movements, combat reports
- **Alliance systems**: Diplomacy, forums, bonuses
- **Economy systems**: Markets, trade routes, artifacts
- **Supporting systems**: Quests, logs, configuration

### Key Design Principles

1. **Per-world isolation**: Each game world is a separate database
2. **InnoDB everywhere**: ACID compliance and row-level locking
3. **Denormalization**: Store computed values (population, resources) for performance
4. **Composite indexes**: Optimize common query patterns
5. **Worker-friendly**: Timestamp-based processing queues

### Performance Characteristics

- **High write throughput**: Resource updates, troop movements
- **Optimized for workers**: Batch processing of game mechanics
- **Connection pooling**: Reuse connections across requests
- **Sharding-ready**: Easy to distribute worlds across servers

### Integration Summary

- **PostgreSQL**: Global metadata, AI, analytics
- **MySQL**: Per-world game state
- **No cross-database joins**: Application-layer integration
- **Clean separation**: Security and scaling benefits

---

## Appendix A: Table Index

Quick reference of all 90 tables:

**Player**: users, face, activation, deleting, player_references, ignoreList, friendlist, notes

**Village**: vdata, wdata, odata, fdata, available_villages, surrounding, map_block, map_mark, mapflag

**Buildings**: building_upgrade, demolition, research, smithy, tdata

**Military**: units, training, movement, enforcement, trapped, a2b, ndata, raidlist, farmlist, farmlist_last_reports, casualties

**Alliance**: alidata, ali_invite, ali_log, alistats, diplomacy, alliance_notification, alliance_bonus_upgrade_queue, allimedal, forum_forums, forum_topic, forum_post, forum_options, forum_vote, forum_edit, forum_open_players, forum_open_alliances

**Communication**: mdata, messages_report, infobox, infobox_read, infobox_delete

**Hero**: hero, items, inventory, adventure, auction, bids, accounting

**Economy**: market, send, traderoutes, artefacts, artlog

**Quests**: daily_quest, medal, autoExtend, buyGoldMessages, voting_reward_queue

**Admin**: general_log, admin_log, log_ip, multiaccount_log, multiaccount_users, banHistory, banQueue, transfer_gold_log, activation_progress

**Config**: config, summary, links, newproc, changeEmail, login_handshake, odelete, blocks, marks, notificationQueue

---

**End of Document**
