# Global Database Schema Restoration

## 🎯 Purpose

This guide fixes the incomplete database schema by importing the complete Travian-Solo MySQL schema. By the end, you'll have:

- ✅ Complete `travian_global` database with all required tables
- ✅ Fixed `activation.used` column (the critical missing column)
- ✅ Sample game server data for testing
- ✅ All foreign keys and indexes properly created
- ✅ Verified schema integrity

**Estimated Time:** 1-2 hours

---

## 📋 Prerequisites

Before starting, ensure you've completed:

- ✅ Guide 4: MYSQL-INFRASTRUCTURE.md
- ✅ MySQL 8.0 container running on port 3306
- ✅ `travian_global` database exists
- ✅ Can connect to MySQL from WSL2

---

## Section 1: Understanding the Schema Problem

### ❌ What Went Wrong Previously

**The Problem:**
Only 4 tables were manually created in `travian_global`:
- `gameServers` - Game world list
- `configurations` - App settings
- `activation` - Email verification
- `banIP` - Security

**Why this failed:**
1. **Missing `activation.used` column** - Caused activation handler to crash
2. **Incomplete schema** - Missing 20+ global tables needed for full functionality
3. **Manual creation** - Error-prone, inconsistent with production schema
4. **No indexes** - Poor performance
5. **No foreign keys** - Data integrity issues
6. **No default data** - Empty configurations table breaks app

### ✅ Correct Approach

**Import the complete Travian-Solo schema from `/tmp/Travian-Solo/database/main.sql`**

This file contains:
- ✅ All global tables with proper structure
- ✅ All game world tables (for each world database)
- ✅ Proper indexes for performance
- ✅ Foreign key constraints for data integrity
- ✅ Default/sample data
- ✅ Triggers and stored procedures (if any)

---

## Section 2: Locate and Inspect the Schema File

### Step 2.1: Find the Schema File

The schema file should be in the Travian-Solo repository you cloned:

```bash
ls -lh /tmp/Travian-Solo/database/main.sql
```

**If file doesn't exist:**
You may need to clone the repository to `/tmp` first:

```bash
cd /tmp
git clone https://github.com/Ghenghis/Travian-Solo.git
ls -lh /tmp/Travian-Solo/database/main.sql
```

**Expected size:** 500KB - 5MB (depending on sample data)

### Step 2.2: Inspect Schema Structure

```bash
head -100 /tmp/Travian-Solo/database/main.sql
```

**What to look for:**
- `CREATE TABLE` statements
- `CREATE DATABASE` statements (if any)
- `INSERT INTO` statements (sample data)
- Character set declarations (`utf8mb4`)

### Step 2.3: Count Tables in Schema

```bash
grep -c "CREATE TABLE" /tmp/Travian-Solo/database/main.sql
```

**Expected output:** 100-150 (depending on Travian-Solo version)

**These tables include:**
- **Global tables:** gameServers, configurations, activation, banIP, etc.
- **Game world tables:** users, villages, buildings, troops, alliances, etc.

---

## Section 3: Backup Current Database

### Step 3.1: Create Backup

Before importing new schema, backup what you have:

```bash
cd ~/Projects/TravianT4.6
./scripts/mysql-backup.sh
```

**Expected output:**
```
✅ Backup completed successfully!
📦 Size: 150K
📁 Location: ./storage/backups/mysql/travian_backup_20251029_150000.sql.gz
```

**Why backup?**
- Safety net if import fails
- Can compare before/after
- Can rollback if needed

---

## Section 4: Prepare Schema for Import

### Step 4.1: Copy Schema to Project

```bash
cp /tmp/Travian-Solo/database/main.sql ~/Projects/TravianT4.6/database/
```

### Step 4.2: Analyze Schema File

```bash
cd ~/Projects/TravianT4.6
head -50 database/main.sql
```

**Check for:**
1. **Database creation statements**
   ```sql
   CREATE DATABASE IF NOT EXISTS `travian_global`;
   USE `travian_global`;
   ```

2. **Drop table statements**
   ```sql
   DROP TABLE IF EXISTS `gameServers`;
   ```

3. **Character set issues**
   Look for `utf8` vs `utf8mb4`

### Step 4.3: Fix Common Schema Issues

Create a cleaned version:

```bash
nano database/main_cleaned.sql
```

**Common fixes needed:**

**Fix 1: Remove CREATE DATABASE statements (we already have the database)**
```bash
sed '/^CREATE DATABASE/d' database/main.sql > database/main_cleaned.sql
sed -i '/^USE /d' database/main_cleaned.sql
```

**Fix 2: Ensure utf8mb4 encoding**
```bash
sed -i 's/utf8 /utf8mb4 /g' database/main_cleaned.sql
sed -i 's/utf8;/utf8mb4;/g' database/main_cleaned.sql
sed -i 's/utf8_/utf8mb4_/g' database/main_cleaned.sql
```

**Fix 3: Remove engine=MyISAM (use InnoDB)**
```bash
sed -i 's/ENGINE=MyISAM/ENGINE=InnoDB/g' database/main_cleaned.sql
```

**Verify cleaned file:**
```bash
wc -l database/main_cleaned.sql
grep "CREATE TABLE" database/main_cleaned.sql | head -5
```

---

## Section 5: Import Global Schema

### Step 5.1: Import to travian_global Database

```bash
source .env

mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" < database/main_cleaned.sql
```

**⏱️ Import time:** 30 seconds - 2 minutes

**Expected output:** (Usually silent if successful)

**If you see errors:**
- Warning about existing tables: OK (they'll be dropped and recreated)
- Duplicate key errors: Usually OK (sample data conflicts)
- Syntax errors: STOP and investigate

### Step 5.2: Verify Import Success

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" -e "USE $DB_GLOBAL; SHOW TABLES;"
```

**Expected output (should include at least these tables):**
```
+---------------------------+
| Tables_in_travian_global  |
+---------------------------+
| activation                |
| banIP                     |
| configurations            |
| gameServers               |
| loginLog                  |
| payments                  |
| sessions                  |
| users_global              |
| ... (many more)           |
+---------------------------+
```

**✅ Success criteria:** At least 20+ tables listed

### Step 5.3: Verify Critical Tables

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" <<EOF
DESCRIBE activation;
DESCRIBE gameServers;
DESCRIBE configurations;
EOF
```

**Expected for `activation` table:**
```
+------------+--------------+------+-----+---------+----------------+
| Field      | Type         | Null | Key | Default | Extra          |
+------------+--------------+------+-----+---------+----------------+
| id         | int          | NO   | PRI | NULL    | auto_increment |
| email      | varchar(255) | NO   | UNI | NULL    |                |
| code       | varchar(64)  | NO   |     | NULL    |                |
| worldId    | varchar(50)  | NO   |     | NULL    |                |
| created    | int          | NO   |     | NULL    |                |
| used       | tinyint      | NO   |     | 0       |                |  <-- CRITICAL!
+------------+--------------+------+-----+---------+----------------+
```

**✅ CRITICAL CHECK:** The `used` column **MUST** exist!

**If `used` column is missing:**
```sql
ALTER TABLE activation ADD COLUMN used TINYINT NOT NULL DEFAULT 0;
```

---

## Section 6: Fix the Missing `used` Column Issue

### Understanding the Problem

**What is the `used` column?**
- Tracks whether an activation link has been clicked
- Prevents activation code reuse
- Values: `0` (unused) or `1` (used)

**Why was it missing?**
- Different Travian-Solo versions have different schemas
- Manual table creation missed this column
- Older schema versions didn't have it

**Why does it break registration?**
The activation handler code does:
```php
UPDATE activation SET used = 1 WHERE code = ?
```

Without the column, this query fails catastrophically.

### Step 6.1: Check if Column Exists

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" \
  -e "SHOW COLUMNS FROM activation LIKE 'used';"
```

**If column exists:**
```
+-------+---------+------+-----+---------+-------+
| Field | Type    | Null | Key | Default | Extra |
+-------+---------+------+-----+---------+-------+
| used  | tinyint | NO   |     | 0       |       |
+-------+---------+------+-----+---------+-------+
```

**If empty output:** Column is missing!

### Step 6.2: Add Missing Column (If Needed)

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" <<EOF
ALTER TABLE activation 
ADD COLUMN IF NOT EXISTS used TINYINT NOT NULL DEFAULT 0
AFTER created;

CREATE INDEX idx_used ON activation(used);
EOF
```

**Verify:**
```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" \
  -e "DESCRIBE activation;"
```

**✅ Success:** `used` column appears in output

---

## Section 7: Insert Sample Game Server Data

### Step 7.1: Check Existing Game Servers

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" \
  -e "SELECT * FROM gameServers;"
```

**If empty or incomplete:**

### Step 7.2: Insert Test Servers

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" <<'EOF'
-- Clear existing servers
TRUNCATE TABLE gameServers;

-- Insert test server (100x speed)
INSERT INTO gameServers (
  id, worldId, name, title, speed, start, finish,
  registrationKeyRequired, activationRequired, 
  registerClosed, hidden, url
) VALUES (
  1, 'testworld', 'testworld', 'Test Server 100x', 100,
  UNIX_TIMESTAMP(NOW()), 0,
  0, 1,
  0, 0, 'http://testworld.localhost/'
);

-- Insert demo server (5x speed)
INSERT INTO gameServers (
  id, worldId, name, title, speed, start, finish,
  registrationKeyRequired, activationRequired,
  registerClosed, hidden, url
) VALUES (
  2, 'demo', 'demo', 'Demo Server 5x', 5,
  UNIX_TIMESTAMP(NOW()), 0,
  0, 1,
  0, 0, 'http://demo.localhost/'
);

-- Insert production server (1x speed) - hidden initially
INSERT INTO gameServers (
  id, worldId, name, title, speed, start, finish,
  registrationKeyRequired, activationRequired,
  registerClosed, hidden, url
) VALUES (
  3, 'production', 'production', 'Main Server 1x', 1,
  UNIX_TIMESTAMP(DATE_ADD(NOW(), INTERVAL 7 DAY)), 0,
  0, 1,
  1, 1, 'http://production.localhost/'
);
EOF
```

**Verify:**
```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" \
  -e "SELECT id, worldId, title, speed, activationRequired FROM gameServers;"
```

**Expected output:**
```
+----+------------+------------------+-------+---------------------+
| id | worldId    | title            | speed | activationRequired  |
+----+------------+------------------+-------+---------------------+
|  1 | testworld  | Test Server 100x |   100 |                   1 |
|  2 | demo       | Demo Server 5x   |     5 |                   1 |
|  3 | production | Main Server 1x   |     1 |                   1 |
+----+------------+------------------+-------+---------------------+
```

---

## Section 8: Verify Schema Integrity

### Step 8.1: Check Table Count

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" \
  -e "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '$DB_GLOBAL';"
```

**Expected:** 20-50 tables (depending on Travian-Solo version)

### Step 8.2: Check for Missing Indexes

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" <<EOF
SELECT 
  TABLE_NAME,
  INDEX_NAME,
  GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = '$DB_GLOBAL'
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;
EOF
```

**Expected:** Multiple indexes per table (PRIMARY, idx_worldId, idx_email, etc.)

### Step 8.3: Check Foreign Key Constraints

```bash
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" <<EOF
SELECT 
  CONSTRAINT_NAME,
  TABLE_NAME,
  COLUMN_NAME,
  REFERENCED_TABLE_NAME,
  REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = '$DB_GLOBAL'
  AND REFERENCED_TABLE_NAME IS NOT NULL;
EOF
```

**Expected:** Foreign keys linking tables (if schema includes them)

### Step 8.4: Create Schema Validation Script

```bash
nano ~/Projects/TravianT4.6/scripts/validate-global-schema.sh
```

Paste:

```bash
#!/bin/bash

source .env

echo "======================================"
echo "Global Schema Validation"
echo "======================================"

REQUIRED_TABLES=(
    "gameServers"
    "configurations"
    "activation"
    "banIP"
)

echo "Checking required tables..."
for table in "${REQUIRED_TABLES[@]}"; do
    exists=$(mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" \
        -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_GLOBAL' AND table_name = '$table';")
    
    if [ "$exists" -eq 1 ]; then
        echo "✅ $table exists"
    else
        echo "❌ $table is missing!"
        exit 1
    fi
done

echo ""
echo "Checking activation.used column..."
used_col=$(mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" \
    -sN -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$DB_GLOBAL' AND table_name = 'activation' AND column_name = 'used';")

if [ "$used_col" -eq 1 ]; then
    echo "✅ activation.used column exists"
else
    echo "❌ activation.used column is MISSING!"
    exit 1
fi

echo ""
echo "Checking game server data..."
server_count=$(mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" \
    -sN -e "SELECT COUNT(*) FROM gameServers;")

if [ "$server_count" -ge 1 ]; then
    echo "✅ Found $server_count game server(s)"
else
    echo "❌ No game servers configured!"
    exit 1
fi

echo ""
echo "======================================"
echo "✅ Global schema is valid!"
echo "======================================"
```

Make executable:

```bash
chmod +x scripts/validate-global-schema.sh
```

### Step 8.5: Run Validation

```bash
cd ~/Projects/TravianT4.6
./scripts/validate-global-schema.sh
```

**Expected output:**
```
======================================
Global Schema Validation
======================================
Checking required tables...
✅ gameServers exists
✅ configurations exists
✅ activation exists
✅ banIP exists

Checking activation.used column...
✅ activation.used column exists

Checking game server data...
✅ Found 3 game server(s)

======================================
✅ Global schema is valid!
======================================
```

---

## ✅ Verification Checklist

Before proceeding to the next guide, verify:

- [ ] Complete Travian-Solo schema imported to `travian_global`
- [ ] At least 20+ tables exist in `travian_global`
- [ ] `activation` table has `used` column (TINYINT)
- [ ] `gameServers` table has sample data (testworld, demo)
- [ ] All required tables verified (gameServers, configurations, activation, banIP)
- [ ] Schema validation script passes
- [ ] Backup created before import
- [ ] Character set is utf8mb4 (not utf8)
- [ ] Engine is InnoDB (not MyISAM)

**Full verification command:**

```bash
cd ~/Projects/TravianT4.6
source .env
./scripts/validate-global-schema.sh
mysql -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASSWORD" "$DB_GLOBAL" \
  -e "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.tables WHERE table_schema = '$DB_GLOBAL' LIMIT 5;"
```

**Expected:** Validation passes, engine=InnoDB, collation=utf8mb4_*

---

## 🚀 Next Steps

**Fantastic!** Your global database now has the complete schema with all required tables and the critical `used` column fixed.

**Next guide:** [GAME-WORLD-DATABASES.md](./GAME-WORLD-DATABASES.md)

This will walk you through:
- Creating separate databases for each game world
- Importing the T4.4 game schema (100+ tables)
- Understanding world-specific vs global data
- Preparing for multi-world gameplay

---

**Last Updated:** October 29, 2025  
**Estimated Completion Time:** 1-2 hours  
**Difficulty:** Intermediate
