# Game World Databases - Creating Per-World Schemas

## 🎯 Purpose

This guide creates separate databases for each game world with complete T4.4 game schemas. By the end, you'll have:

- ✅ `travian_testworld` database with 100+ game tables
- ✅ `travian_demo` database with complete schema
- ✅ Understanding of world-specific vs global data separation
- ✅ Proper indexes and foreign keys for performance
- ✅ Sample NPC/AI accounts ready for testing

**Estimated Time:** 2-3 hours

---

## 📋 Prerequisites

Before starting, ensure you've completed:

- ✅ Guide 5: GLOBAL-SCHEMA-RESTORE.md
- ✅ `travian_global` database exists with complete schema
- ✅ `gameServers` table has entries for testworld and demo
- ✅ `activation.used` column verified to exist

---

## Section 1: Understanding World Database Architecture

### Why Separate Databases Per World?

**Travian's Multi-World Architecture:**

```
travian_global (API Database)
├── gameServers      → List of all game worlds
├── activation       → Email verification (cross-world)
├── configurations   → Global settings
└── banIP           → Security (cross-world)

travian_testworld (Game World #1)
├── users           → Player accounts for THIS world only
├── villages        → Player settlements
├── buildings       → Structures in villages
├── troops          → Military units
├── alliances       → Player groups
└── ... (100+ tables)

travian_demo (Game World #2)
├── users           → DIFFERENT player accounts
├── villages        → DIFFERENT settlements
└── ... (same structure, different data)
```

**Key Concepts:**

1. **Global Database (`travian_global`)**
   - Shared across ALL worlds
   - Server list, activations, global bans
   - Users register here first

2. **World Databases (`travian_{worldId}`)**
   - One per game world
   - Contains actual gameplay data
   - Users created here after activation

3. **Data Flow:**
   ```
   User registers → activation table (global)
   ↓
   User clicks email link → activation processed
   ↓
   User account created → users table (world database)
   ↓
   User logs in → session points to world database
   ```

### ❌ What Went Wrong Previously

**Problem:**
Only `travian_global` existed. When users tried to log in to "testworld", the system looked for `travian_testworld` database and found nothing.

**Result:**
- "userDoesNotExists" errors
- Login failed every time
- No way to actually play the game

**Solution:**
Create `travian_testworld` and `travian_demo` databases with complete game schemas.

---

## Section 2: Extract Game World Schema

### Step 2.1: Identify World-Specific Tables

The Travian-Solo `main.sql` contains BOTH global and world tables mixed together. We need to separate them.

```bash
cd ~/Projects/TravianT4.6

grep "CREATE TABLE" database/main.sql | grep -v gameServers | grep -v activation | grep -v configurations | grep -v banIP > /tmp/world_tables.txt

cat /tmp/world_tables.txt
```

**Expected output (sample):**
```
CREATE TABLE `users` (
CREATE TABLE `villages` (
CREATE TABLE `buildings` (
CREATE TABLE `troops` (
CREATE TABLE `alliances` (
...
```

**✅ These are world-specific tables** (need to be in each world database)

### Step 2.2: Create World Schema File

We'll create a separate SQL file containing ONLY world-specific tables:

```bash
nano database/world_schema.sql
```

**Option A: Extract from main.sql automatically**

```bash
#!/bin/bash
# Extract world-specific tables from main.sql

GLOBAL_TABLES="gameServers|configurations|activation|banIP|users_global|loginLog|payments|sessions"

grep -v -E "($GLOBAL_TABLES)" database/main_cleaned.sql | \
grep -v "CREATE DATABASE" | \
grep -v "^USE " > database/world_schema.sql

echo "World schema extracted to database/world_schema.sql"
wc -l database/world_schema.sql
```

**Option B: Use pre-defined world schema**

If Travian-Solo repository has a separate world schema file:

```bash
ls /tmp/Travian-Solo/database/world*.sql
ls /tmp/Travian-Solo/database/game*.sql
```

Copy it if it exists:

```bash
cp /tmp/Travian-Solo/database/world_schema.sql database/
```

### Step 2.3: Verify World Schema

```bash
grep "CREATE TABLE" database/world_schema.sql | wc -l
```

**Expected:** 100-150 tables

**Critical tables that MUST be present:**
- `users` - Player accounts
- `villages` - Player settlements  
- `buildings` - Structures
- `troops` - Military units
- `resources` - Resource generation
- `alliances` - Player groups
- `messages` - Communication
- `reports` - Battle reports
- `market` - Trading system
- `quests` - Tutorial/missions

**Verify:**
```bash
for table in users villages buildings troops resources alliances messages reports market quests; do
    if grep -q "CREATE TABLE.*$table" database/world_schema.sql; then
        echo "✅ $table found"
    else
        echo "❌ $table MISSING!"
    fi
done
```

---

## Section 3: Create testworld Database

### Step 3.1: Create Database

```bash
source .env

mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" <<EOF
CREATE DATABASE IF NOT EXISTS travian_testworld
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
EOF
```

**Verify:**
```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" -e "SHOW DATABASES;" | grep testworld
```

**Expected output:**
```
travian_testworld
```

### Step 3.2: Import World Schema

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" travian_testworld < database/world_schema.sql
```

**⏱️ Import time:** 1-3 minutes

**Check for errors:**
```bash
echo $?
```

**Expected:** `0` (success)

**If errors occurred:**
- Syntax errors: Review world_schema.sql
- Duplicate table errors: OK (tables being recreated)
- Foreign key errors: Note them (may need to fix)

### Step 3.3: Verify Tables Created

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" -e "USE travian_testworld; SHOW TABLES;"
```

**Expected output (sample):**
```
+----------------------------+
| Tables_in_travian_testworld|
+----------------------------+
| active                     |
| alliances                  |
| buildings                  |
| combat                     |
| messages                   |
| quests                     |
| reports                    |
| resources                  |
| troops                     |
| users                      |
| villages                   |
| ... (many more)            |
+----------------------------+
```

**✅ Success criteria:** 100+ tables

### Step 3.4: Verify Critical Table Structures

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" travian_testworld <<EOF
DESCRIBE users;
DESCRIBE villages;
DESCRIBE buildings;
EOF
```

**Expected for `users` table:**
```
+------------+--------------+------+-----+---------+----------------+
| Field      | Type         | Null | Key | Default | Extra          |
+------------+--------------+------+-----+---------+----------------+
| id         | int          | NO   | PRI | NULL    | auto_increment |
| username   | varchar(50)  | NO   | UNI | NULL    |                |
| password   | varchar(255) | NO   |     | NULL    |                |
| email      | varchar(255) | NO   |     | NULL    |                |
| tribe      | tinyint      | NO   |     | 1       |                |
| ... (more fields)         |      |     |         |                |
+------------+--------------+------+-----+---------+----------------+
```

---

## Section 4: Create demo Database

### Step 4.1: Create Database

```bash
source .env

mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" <<EOF
CREATE DATABASE IF NOT EXISTS travian_demo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
EOF
```

### Step 4.2: Import World Schema

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" travian_demo < database/world_schema.sql
```

### Step 4.3: Verify

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" -e "USE travian_demo; SHOW TABLES;" | wc -l
```

**Expected:** 100+ tables

---

## Section 5: Insert Sample NPC/AI Accounts

### Why Create NPC Accounts Now?

**Benefits:**
1. Test user creation flow
2. Verify table relationships work
3. Have ready-made accounts for AI integration
4. Can test login before registration is fully fixed

### Step 5.1: Create NPC Accounts in testworld

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" travian_testworld <<'EOF'
-- Create NPC user accounts for AI testing
INSERT INTO users (
  id, username, password, email, tribe, 
  access, gold, birthday, gender, location, 
  description, created, lastLogin, active
) VALUES 
(1, 'ai_farmer_01', 
 '$2y$10$YourHashedPasswordHere', 
 'ai_farmer_01@npc.local', 1,
 2, 0, 0, 0, 'AI Village',
 'AI NPC - Farmer Strategy', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1),
 
(2, 'ai_warrior_01',
 '$2y$10$YourHashedPasswordHere',
 'ai_warrior_01@npc.local', 2,
 2, 0, 0, 0, 'AI Village',
 'AI NPC - Warrior Strategy', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1),
 
(3, 'ai_trader_01',
 '$2y$10$YourHashedPasswordHere',
 'ai_trader_01@npc.local', 3,
 2, 0, 0, 0, 'AI Village',
 'AI NPC - Trader Strategy', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1);

-- Create starting villages for NPCs
INSERT INTO villages (
  id, userId, name, capital, x, y, 
  population, wood, clay, iron, crop, 
  maxStore, maxCrop, created
) VALUES
(1, 1, 'AI Farm Valley', 1, FLOOR(RAND()*200)-100, FLOOR(RAND()*200)-100,
 50, 750, 750, 750, 750,
 800, 800, UNIX_TIMESTAMP()),
 
(2, 2, 'AI Warrior Camp', 1, FLOOR(RAND()*200)-100, FLOOR(RAND()*200)-100,
 50, 750, 750, 750, 750,
 800, 800, UNIX_TIMESTAMP()),
 
(3, 3, 'AI Trade Hub', 1, FLOOR(RAND()*200)-100, FLOOR(RAND()*200)-100,
 50, 750, 750, 750, 750,
 800, 800, UNIX_TIMESTAMP());
EOF
```

**⚠️ Note:** Replace `$2y$10$YourHashedPasswordHere` with actual bcrypt hashes.

**Generate password hash:**

```bash
php -r 'echo password_hash("npc123", PASSWORD_BCRYPT);'
```

Use that hash in the INSERT statements above.

### Step 5.2: Verify NPC Accounts

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" travian_testworld \
  -e "SELECT id, username, email, tribe FROM users WHERE username LIKE 'ai_%';"
```

**Expected output:**
```
+----+---------------+-------------------------+-------+
| id | username      | email                   | tribe |
+----+---------------+-------------------------+-------+
|  1 | ai_farmer_01  | ai_farmer_01@npc.local  |     1 |
|  2 | ai_warrior_01 | ai_warrior_01@npc.local |     2 |
|  3 | ai_trader_01  | ai_trader_01@npc.local  |     3 |
+----+---------------+-------------------------+-------+
```

---

## Section 6: Create Validation Script

### Step 6.1: Create Script

```bash
nano ~/Projects/TravianT4.6/scripts/validate-world-schema.sh
```

Paste:

```bash
#!/bin/bash

if [ -z "$1" ]; then
    echo "Usage: ./scripts/validate-world-schema.sh <worldId>"
    echo "Example: ./scripts/validate-world-schema.sh testworld"
    exit 1
fi

WORLD_ID="$1"
DB_NAME="travian_$WORLD_ID"

source .env

echo "======================================"
echo "World Schema Validation: $WORLD_ID"
echo "======================================"

echo "Checking database exists..."
exists=$(mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" \
    -sN -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$DB_NAME';")

if [ "$exists" -eq 1 ]; then
    echo "✅ Database $DB_NAME exists"
else
    echo "❌ Database $DB_NAME does NOT exist!"
    exit 1
fi

REQUIRED_TABLES=(
    "users"
    "villages"
    "buildings"
    "troops"
    "alliances"
    "messages"
    "reports"
)

echo ""
echo "Checking required tables..."
for table in "${REQUIRED_TABLES[@]}"; do
    exists=$(mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" \
        -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME' AND table_name = '$table';")
    
    if [ "$exists" -eq 1 ]; then
        echo "✅ $table exists"
    else
        echo "❌ $table is MISSING!"
        exit 1
    fi
done

echo ""
echo "Counting total tables..."
table_count=$(mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" \
    -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';")
echo "✅ Found $table_count tables"

if [ "$table_count" -lt 50 ]; then
    echo "⚠️  Warning: Expected 100+ tables, found only $table_count"
fi

echo ""
echo "Checking for NPC accounts..."
npc_count=$(mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" \
    -sN -e "SELECT COUNT(*) FROM users WHERE username LIKE 'ai_%';")
    
if [ "$npc_count" -gt 0 ]; then
    echo "✅ Found $npc_count NPC account(s)"
else
    echo "⚠️  No NPC accounts found (optional)"
fi

echo ""
echo "======================================"
echo "✅ World schema is valid!"
echo "======================================"
```

Make executable:

```bash
chmod +x scripts/validate-world-schema.sh
```

### Step 6.2: Run Validation

```bash
cd ~/Projects/TravianT4.6
./scripts/validate-world-schema.sh testworld
./scripts/validate-world-schema.sh demo
```

**Expected output:**
```
======================================
World Schema Validation: testworld
======================================
Checking database exists...
✅ Database travian_testworld exists

Checking required tables...
✅ users exists
✅ villages exists
✅ buildings exists
✅ troops exists
✅ alliances exists
✅ messages exists
✅ reports exists

Counting total tables...
✅ Found 127 tables

Checking for NPC accounts...
✅ Found 3 NPC account(s)

======================================
✅ World schema is valid!
======================================
```

---

## ✅ Verification Checklist

Before proceeding to the next guide, verify:

- [ ] `travian_testworld` database exists
- [ ] `travian_demo` database exists
- [ ] Both world databases have 100+ tables
- [ ] Critical tables verified (users, villages, buildings, troops, alliances)
- [ ] Character set is utf8mb4
- [ ] Sample NPC accounts created in testworld (optional but recommended)
- [ ] Validation script passes for both testworld and demo
- [ ] Can query users table without errors

**Full verification command:**

```bash
cd ~/Projects/TravianT4.6
source .env
./scripts/validate-world-schema.sh testworld
./scripts/validate-world-schema.sh demo
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" -e "SHOW DATABASES;" | grep travian
```

**Expected:** Validation passes for both worlds, all 3 databases listed

---

## 🚀 Next Steps

**Excellent!** You now have complete game world databases ready for multi-world gameplay and AI NPC integration.

**Next guide:** [CONNECTION-PHP-GENERATOR.md](./CONNECTION-PHP-GENERATOR.md)

This will walk you through:
- Auto-generating connection.php files for each world
- Understanding why these files are critical
- Fixing the missing connection.php errors
- Template-based configuration management

---

**Last Updated:** October 29, 2025  
**Estimated Completion Time:** 2-3 hours  
**Difficulty:** Intermediate
