# Phase 3: AI-NPC Integration with Travian Game Engine

## Overview

Phase 3 implements the critical bridge between AI-NPC decision-making (PostgreSQL) and Travian gameplay (MySQL). This document explains how AI-NPCs are spawned into game worlds and interact with the Travian game engine.

## Dual-Database Architecture

### PostgreSQL (AI Decision-Making)
**Purpose:** Store AI configuration and track decision-making  
**Tables:**
- `players` - NPC player records with skill levels
- `ai_configs` - AI behavior configuration (difficulty, personality, LLM ratio)
- `world_npc_spawns` - Spawn tracking records
- `decision_logs` - AI decision history

### MySQL (Travian Game World)
**Purpose:** Store actual game state and enable gameplay  
**Tables:**
- `users` - Player accounts (NPCs appear as regular players)
- `vdata` - Village data (resources, population, buildings)
- `wdata` - World map data (coordinates, terrain)
- `fdata` - Resource field levels
- `building_upgrade` - Building queue
- `movement` - Troop movements and attacks

### Database Linking
**Connection:** PostgreSQL `players.game_player_id` → MySQL `users.id`

This allows:
- AI services to query player decisions from PostgreSQL
- Game controllers to update game state in MySQL
- Bidirectional sync between AI decisions and game actions

## NPC Spawning Process

### 1. **NPCInitializerService::createNPC()**

**Location:** `sections/api/include/Services/NPCInitializerService.php`

**Process:**
```
1. Get world configuration from PostgreSQL gameservers table
2. Connect to MySQL game world database via DatabaseBridge
3. Create user account in MySQL users table
4. Create village in MySQL vdata table
5. Update world map in MySQL wdata table
6. Initialize resource fields in MySQL fdata table
7. Create player record in PostgreSQL players table
8. Create AI config in PostgreSQL ai_configs table
9. Link PostgreSQL player to MySQL user via game_player_id
10. Create spawn tracking in PostgreSQL world_npc_spawns table
```

**Key Code:**
```php
// Get MySQL connection for game world
$worldInfo = $this->getWorldInfo($worldId);
$configFile = $worldInfo['configfilelocation'];
$mysqlDb = $this->bridge->getMySQLConnection($configFile);

// Create in MySQL
$mysqlUserId = $this->createUserAccountMySQL($mysqlDb, $username, $tribe);
$villageId = $this->createVillageMySQL($mysqlDb, $mysqlUserId, $location, $tribe, $villageName);

// Create in PostgreSQL
$playerId = $this->createPlayerRecordPostgres($worldId, $mysqlUserId, $username, $config);

// Link databases
$this->linkPlayerToGameWorld($playerId, $mysqlUserId);
```

### 2. **Travian User Account Creation**

**MySQL Table:** `users`

**Required Fields:**
```php
name            VARCHAR(20)      // NPC username
email           VARCHAR(99)      // npc_username@ai.npc
password        VARCHAR(40)      // MD5 hash (random)
race            TINYINT(1)       // 1=Romans, 2=Teutons, 3=Gauls
access          TINYINT(1)       // 2 = normal player access
kid             INT(6)           // Capital village ID (set after village creation)
signupTime      INT(10)          // Unix timestamp
protection      INT(10)          // Beginner protection end time (3 days)
last_login_time INT(10)          // Last activity timestamp
```

**Tribe Mapping:**
- Romans = 1
- Teutons = 2
- Gauls = 3

### 3. **Travian Village Creation**

**MySQL Tables:** `vdata`, `wdata`, `fdata`

**vdata Fields:**
```php
kid         INT(6)          // Village ID (from wdata.id at coordinates)
owner       INT(11)         // MySQL users.id
name        VARCHAR(45)     // Village name
capital     TINYINT(1)      // 1 = capital village
type        TINYINT(2)      // Village type (matches tribe)
fieldtype   TINYINT(2)      // Resource field distribution
wood        DOUBLE(50,4)    // Current wood (start: 750)
clay        DOUBLE(50,4)    // Current clay (start: 750)
iron        DOUBLE(50,4)    // Current iron (start: 750)
crop        DOUBLE(50,4)    // Current crop (start: 750)
woodp       BIGINT(50)      // Wood production per hour
clayp       BIGINT(50)      // Clay production per hour
ironp       BIGINT(50)      // Iron production per hour
cropp       BIGINT(50)      // Crop production per hour
maxstore    BIGINT(50)      // Warehouse capacity (start: 800)
maxcrop     BIGINT(50)      // Granary capacity (start: 800)
upkeep      BIGINT(50)      // Crop consumption
created     INT(11)         // Creation timestamp
pop         INT(10)         // Population (start: 2)
cp          INT(10)         // Culture points (start: 1)
```

**fdata Fields:**
```php
kid     INT(6)              // Village ID
f1-f18  TINYINT(2)          // Field levels (0-18 for 18 resource fields)
f1t-f18t TINYINT(2)         // Field types (0=empty, 1-4=resource types)
```

## NPC Building Automation

### Controller: BuildingCtrl

**File:** `sections/api/include/Api/Controllers/BuildingCtrl.php`

**Integration Points:**

1. **Query MySQL for Resources:**
```php
$mysqlDb = $this->bridge->getMySQLConnection($configFile);
$stmt = $mysqlDb->prepare("
    SELECT wood, clay, iron, crop, maxstore, maxcrop 
    FROM vdata WHERE kid = ?
");
```

2. **Travian Building IDs:**
```
Resource Fields (1-18):
1-4   Woodcutter levels 1-4
5-8   Clay pit levels 1-4  
9-12  Iron mine levels 1-4
13-18 Cropland levels 1-6

Infrastructure Buildings (19-40):
19 = Main Building
20 = Rally Point
21 = Marketplace
22 = Embassy
23 = Barracks
24 = Stable
25 = Workshop
26 = Academy
27 = Cranny
28 = Town Hall
29 = Residence/Palace
30 = Treasury
31 = Trade Office
32 = Great Barracks
33 = Great Stable
34 = City Wall/Palisade/Stone Wall
35 = Earth Wall
36 = Hospital
37 = Iron Foundry
38 = Grain Mill
39 = Bakery
40 = WW (Wonder of the World)
```

3. **Building Queue (MySQL `building_upgrade` table):**
```php
INSERT INTO building_upgrade (
    kid, building_field, isMaster, start_time, commence
) VALUES (?, ?, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + ?)
```

4. **Resource Deduction:**
```php
UPDATE vdata SET 
    wood = wood - ?,
    clay = clay - ?,
    iron = iron - ?,
    crop = crop - ?
WHERE kid = ?
```

## NPC Troop Training

### Controller: TrainingCtrl

**File:** `sections/api/include/Api/Controllers/TrainingCtrl.php`

**Travian Troop IDs:**

**Romans (tribe=1):**
- 1 = Legionnaire
- 2 = Praetorian  
- 3 = Imperian
- 4 = Equites Legati
- 5 = Equites Imperatoris
- 6 = Equites Caesaris
- 7 = Battering Ram
- 8 = Fire Catapult
- 9 = Senator
- 10 = Settler

**Teutons (tribe=2):**
- 11 = Clubswinger
- 12 = Spearman
- 13 = Axeman
- 14 = Scout
- 15 = Paladin
- 16 = Teutonic Knight
- 17 = Ram
- 18 = Catapult
- 19 = Chief
- 20 = Settler

**Gauls (tribe=3):**
- 21 = Phalanx
- 22 = Swordsman
- 23 = Pathfinder
- 24 = Theutates Thunder
- 25 = Druidrider
- 26 = Haeduan
- 27 = Ram
- 28 = Trebuchet
- 29 = Chieftain
- 30 = Settler

**Training Process:**
1. Check building levels (barracks for infantry, stable for cavalry)
2. Query MySQL for current resources
3. Calculate training time and costs
4. Deduct resources from vdata
5. Insert into training queue
6. Update troop counts when complete

## NPC Attack System

### Controllers: DefenseCtrl, FarmingCtrl

**Files:**
- `sections/api/include/Api/Controllers/DefenseCtrl.php`
- `sections/api/include/Api/Controllers/FarmingCtrl.php`

**MySQL `movement` Table:**
```php
id          INT(11)         // Movement ID
kid         INT(6)          // From village ID
to_kid      INT(6)          // To village ID
race        TINYINT(1)      // Tribe
u1-u10      BIGINT(50)      // Troop counts
u11         TINYINT(1)      // Hero (0 or 1)
timestamp   INT(10)         // Departure time
endtime     INT(10)         // Arrival time
attack_type TINYINT(1)      // 1=attack, 2=raid, 3=support, etc.
```

**Attack Types:**
- 1 = Attack (normal attack)
- 2 = Raid (farming)
- 3 = Support (reinforcement)
- 4 = Spy
- 5 = Settle
- 6 = Return

**Attack Creation:**
```php
INSERT INTO movement (
    kid, to_kid, race, u1, u2, u3, ..., u10, u11,
    timestamp, endtime, attack_type
) VALUES (?, ?, ?, ?, ?, ?, ..., ?, ?, ?, ?, ?)
```

## Testing NPC Integration

### Test Scripts Location
`sections/api/` directory

### 1. test-npc-spawn-travian.php
**Purpose:** Verify NPCs spawn correctly in both databases

**Tests:**
- PostgreSQL player record created
- MySQL user account created
- MySQL village created with correct resources
- Database link (game_player_id) established
- World map updated (wdata.occupied = 1)

### 2. test-npc-building.php
**Purpose:** Verify NPCs can queue building upgrades

**Tests:**
- Resource checks work correctly
- Building queue insertion successful
- Resource deduction accurate
- Travian building IDs valid

### 3. test-npc-training.php
**Purpose:** Verify NPCs can train troops

**Tests:**
- Troop type IDs match Travian standards
- Training costs calculated correctly
- Resources deducted properly
- Training queue functional

### 4. test-npc-attacks.php
**Purpose:** Verify NPCs can send attacks

**Tests:**
- Movement table insertion works
- Travel time calculated correctly
- Attack types properly set
- Target selection functional

### 5. test-npc-visibility.php
**Purpose:** Verify NPCs appear in game reports/statistics

**Tests:**
- NPC villages appear in rankings
- NPC attacks generate reports
- Statistics include NPC actions
- Game engine recognizes NPCs

## Troubleshooting

### Common Issues

**1. NPCs not appearing in game:**
- Check MySQL users table for NPC account
- Verify vdata.owner matches users.id
- Confirm wdata.occupied = 1 for spawn location

**2. Building automation fails:**
- Verify DatabaseBridge connection to correct world
- Check resource amounts in vdata
- Confirm building IDs match Travian standard

**3. Troop training not working:**
- Check barracks/stable building levels
- Verify troop IDs match tribe
- Confirm sufficient resources in vdata

**4. Attacks not sent:**
- Verify movement table structure matches Travian
- Check troop counts > 0
- Confirm target village exists in vdata

### Debug Queries

**Check NPC in both databases:**
```sql
-- PostgreSQL
SELECT p.*, ac.difficulty, ac.personality
FROM players p
JOIN ai_configs ac ON p.id = ac.npc_player_id
WHERE p.player_type = 'npc';

-- MySQL (via DatabaseBridge)
SELECT u.*, v.name, v.pop, v.wood, v.clay, v.iron, v.crop
FROM users u
JOIN vdata v ON u.id = v.owner
WHERE u.email LIKE '%@ai.npc';
```

**Verify database link:**
```sql
-- PostgreSQL
SELECT id, account_id, game_player_id, world_id
FROM players
WHERE player_type = 'npc' AND game_player_id IS NOT NULL;
```

## Performance Considerations

### Database Connection Pooling
DatabaseBridge maintains connection pool per MySQL config file to avoid reconnection overhead.

### Query Optimization
- Use prepared statements for all queries
- Index foreign keys (owner, kid, wref)
- Batch operations when possible

### Sync Frequency
- AI decisions: Every 5 minutes (configurable)
- Game state sync: On-demand via controllers
- Stat updates: Hourly via cron jobs

## Next Steps (Phase 4+)

- Phase 4: AI decision automation (workers/cron jobs)
- Phase 5: LLM integration for advanced decisions
- Phase 6: Performance optimization
- Phase 7: Integration testing and validation

## References

- Travian game mechanics: [T4.4 Documentation]
- DatabaseBridge API: `sections/api/include/Database/DatabaseBridge.php`
- NPC Services: `sections/api/include/Services/`
- Automation Controllers: `sections/api/include/Api/Controllers/`
