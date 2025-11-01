# Enterprise Implementation Blueprint
## Phase 4B APIs + Testing + Deployment + AI NPCs + Enhancements

**Created:** October 30, 2025  
**Status:** Implementation Guide  
**Target:** Professional, enterprise-grade completion without complexity

---

## Table of Contents
1. [Phase 4B API Implementation](#phase-4b-api-implementation)
2. [Comprehensive Testing Strategy](#comprehensive-testing-strategy)
3. [Production Deployment Guide](#production-deployment-guide)
4. [AI NPC Architecture](#ai-npc-architecture)
5. [Background Workers](#background-workers)
6. [Caching Strategies](#caching-strategies)
7. [WebSocket Integration](#websocket-integration)
8. [Master Roadmap](#master-roadmap)

---

# Phase 4B API Implementation

## Build Order & Dependencies

**Sequencing Rationale:**
1. **HeroCtrl** → Powers quest rewards, needed for adventures
2. **QuestCtrl** → Depends on hero system, provides progression
3. **ReportsCtrl** → Feeds statistics, independent of others
4. **MessagesCtrl** → Independent, alliance chat support
5. **StatisticsCtrl** → Aggregates data from all systems

### Week 1 Milestone: Complete all 34 Phase 4B endpoints

---

## 1. HeroCtrl (9 endpoints) - Build First

**File:** `sections/api/include/Api/Ctrl/HeroCtrl.php`  
**Priority:** IMPORTANT  
**Complexity:** HIGH (adventure calculations, auction system)

### Schema Additions Required

```sql
-- Hero profile (extended attributes)
CREATE TABLE IF NOT EXISTS hero_profile (
    uid INTEGER PRIMARY KEY,
    health INTEGER DEFAULT 100,
    level INTEGER DEFAULT 0,
    experience INTEGER DEFAULT 0,
    strength INTEGER DEFAULT 0,
    attack_bonus INTEGER DEFAULT 0,
    defense_bonus INTEGER DEFAULT 0,
    resource_bonus INTEGER DEFAULT 0,
    attribute_points INTEGER DEFAULT 0,
    FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_hero_profile_uid ON hero_profile(uid);

-- Hero adventures
CREATE TABLE IF NOT EXISTS hero_adventures (
    id SERIAL PRIMARY KEY,
    uid INTEGER NOT NULL,
    x INTEGER NOT NULL,
    y INTEGER NOT NULL,
    difficulty INTEGER DEFAULT 0,
    duration INTEGER DEFAULT 3600,
    start_time INTEGER,
    end_time INTEGER,
    status VARCHAR(20) DEFAULT 'available',
    rewards TEXT,
    FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_hero_adventures_uid ON hero_adventures(uid);
CREATE INDEX idx_hero_adventures_status ON hero_adventures(status);
CREATE INDEX idx_hero_adventures_end_time ON hero_adventures(end_time);

-- Hero items/equipment
CREATE TABLE IF NOT EXISTS hero_items (
    id SERIAL PRIMARY KEY,
    uid INTEGER NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    tier INTEGER DEFAULT 1,
    slot VARCHAR(20),
    equipped BOOLEAN DEFAULT FALSE,
    stats TEXT,
    FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_hero_items_uid ON hero_items(uid);
CREATE INDEX idx_hero_items_equipped ON hero_items(uid, equipped);
```

### Endpoints Implementation

#### 1. getHero
```php
public function getHero()
{
    // Validate: worldId, uid
    // Query: hero_profile JOIN hero_items WHERE equipped=true
    // Response: {hero: {health, level, xp, attributes, equipment}}
    // Performance: <75ms
}
```

#### 2. levelUp
```php
public function levelUp()
{
    // Validate: worldId, uid, attributes distribution
    // Check: attribute_points available
    // Update: hero_profile (strength, attack, defense, resources)
    // Response: {success, newStats}
    // Performance: <100ms
}
```

#### 3. equipItem
```php
public function equipItem()
{
    // Validate: worldId, uid, itemId, slot
    // Update: hero_items (unequip current, equip new)
    // Calculate: new stats from equipment
    // Response: {success, newStats}
    // Performance: <75ms
}
```

#### 4. startAdventure (COMPLEX)
```php
public function startAdventure()
{
    // Validate: worldId, uid, adventureId
    // Check: hero not on adventure, health > 0
    // Calculate:
    //   - Distance = sqrt((adv_x - hero_x)^2 + (adv_y - hero_y)^2)
    //   - Duration = baseTime + (distance * 60) - (level * 10)
    //   - Difficulty = distance + randomFactor
    //   - Rewards = {resources, xp, items} based on difficulty
    // Update: hero_adventures (status='in_progress', start_time, end_time)
    // Response: {success, duration, arrivalTime, estimatedRewards}
    // Performance: <150ms
    
    // Adventure Formula:
    // baseReward = difficulty * 100
    // resourceReward = baseReward * (1 + hero.resource_bonus / 100)
    // xpReward = difficulty * 10 * (1 + level / 10)
}
```

#### 5. getAdventures
```php
public function getAdventures()
{
    // Validate: worldId, uid
    // Query: hero_adventures WHERE status='available'
    // Calculate: distance from current hero position
    // Response: {adventures: [{id, x, y, difficulty, distance, duration}]}
    // Performance: <100ms
}
```

#### 6. sellItem
```php
public function sellItem()
{
    // Validate: worldId, uid, itemId
    // Calculate: silverValue = itemTier * 50 + itemStats * 10
    // Update: hero silver balance, delete item
    // Response: {success, silverGained, newBalance}
    // Performance: <75ms
}
```

#### 7-9. Auction Endpoints (auctionItem, bidOnAuction, getAuctions)
```php
// Requires auctions table from existing schema
// auctionItem: Create auction with starting bid, duration
// bidOnAuction: Place bid if > currentBid, check silver
// getAuctions: List active auctions with filters/pagination
```

### Critical Game Rules

**Hero Leveling:**
- XP per level = level * 100
- Attribute points per level = 5
- Max level = 100

**Adventure Mechanics:**
- Adventures spawn randomly on map
- Difficulty scales with distance from nearest village
- Rewards scale with difficulty and hero level
- Adventure cooldown = 12 hours after completion

**Item Tiers:**
- Common (tier 1): +5% bonus
- Uncommon (tier 2): +10% bonus
- Rare (tier 3): +20% bonus
- Epic (tier 4): +40% bonus
- Legendary (tier 5): +80% bonus

---

## 2. QuestCtrl (5 endpoints) - Build Second

**File:** `sections/api/include/Api/Ctrl/QuestCtrl.php`  
**Priority:** IMPORTANT  
**Complexity:** MEDIUM (progression tracking)

### Schema Additions

```sql
-- Quest progress tracking
CREATE TABLE IF NOT EXISTS quest_progress (
    id SERIAL PRIMARY KEY,
    uid INTEGER NOT NULL,
    quest_id INTEGER NOT NULL,
    progress INTEGER DEFAULT 0,
    required INTEGER DEFAULT 1,
    completed BOOLEAN DEFAULT FALSE,
    rewarded BOOLEAN DEFAULT FALSE,
    started_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP,
    FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_quest_progress_uid ON quest_progress(uid);
CREATE INDEX idx_quest_progress_completed ON quest_progress(uid, completed);

-- Quest rewards
CREATE TABLE IF NOT EXISTS quest_rewards (
    quest_id INTEGER PRIMARY KEY,
    gold INTEGER DEFAULT 0,
    wood INTEGER DEFAULT 0,
    clay INTEGER DEFAULT 0,
    iron INTEGER DEFAULT 0,
    crop INTEGER DEFAULT 0,
    xp INTEGER DEFAULT 0,
    troops TEXT,
    items TEXT
);
```

### Quest Types & Mechanics

**Tutorial Quests (1-20):**
- Linear progression
- Teach game mechanics
- Cannot skip without gold

**Economy Quests:**
- Build X buildings
- Produce X resources
- Trade X times

**Battle Quests:**
- Train X troops
- Win X battles
- Conquer X villages

**World Quests:**
- Reach population X
- Join alliance
- Capture oasis

**Daily Quests:**
- Reset at midnight server time
- 3 random quests per day
- Bonus gold for completing all 3

### Endpoints

#### 1. getActiveQuests
```php
public function getActiveQuests()
{
    // Query: quest_progress WHERE uid=X AND completed=false
    // Join: quest_rewards for reward preview
    // Response: {quests: [{id, title, description, progress, required, reward}]}
    // Performance: <100ms
}
```

#### 2. completeQuest (COMPLEX)
```php
public function completeQuest()
{
    // Validate: progress >= required
    // Check: not already rewarded
    // Grant rewards:
    //   - Resources to village
    //   - Gold to account
    //   - XP to hero
    //   - Troops to barracks queue
    // Update: quest_progress (completed=true, rewarded=true)
    // Response: {success, rewards: {gold, resources, xp, troops}}
    // Performance: <150ms
}
```

#### 3. getQuestRewards
```php
public function getQuestRewards()
{
    // Query: quest_rewards WHERE quest_id=X
    // Response: {rewards: {gold, resources, troops, items}}
    // Performance: <50ms
}
```

#### 4. skipQuest
```php
public function skipQuest()
{
    // Validate: tutorial quest only
    // Cost: 5 gold per quest
    // Update: quest_progress (completed=true, rewarded=false)
    // Response: {success, goldCost}
    // Performance: <100ms
}
```

#### 5. getQuestProgress
```php
public function getQuestProgress()
{
    // Query: quest_progress WHERE uid=X AND quest_id=Y
    // Calculate: percentage = (progress / required) * 100
    // Response: {progress: {current, required, percentage, steps}}
    // Performance: <75ms
}
```

### Daily Quest Reset Logic

**Background Worker:**
```php
// Runs daily at midnight server time
function resetDailyQuests() {
    // Delete all daily quest progress
    // Generate 3 new random daily quests per user
    // Quest pool: 20 different daily quests
    // Difficulty scales with player level/population
}
```

---

## 3. ReportsCtrl (6 endpoints) - Build Third

**File:** `sections/api/include/Api/Ctrl/ReportsCtrl.php`  
**Priority:** IMPORTANT  
**Complexity:** HIGH (battle report generation)

### Schema Additions

```sql
-- Battle reports
CREATE TABLE IF NOT EXISTS reports_battle (
    id SERIAL PRIMARY KEY,
    uid INTEGER NOT NULL,
    attacker_uid INTEGER,
    defender_uid INTEGER,
    village_from INTEGER,
    village_to INTEGER,
    timestamp TIMESTAMP DEFAULT NOW(),
    result VARCHAR(20),
    attacker_troops TEXT,
    defender_troops TEXT,
    attacker_casualties TEXT,
    defender_casualties TEXT,
    resources_captured TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    archived BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_reports_battle_uid ON reports_battle(uid);
CREATE INDEX idx_reports_battle_is_read ON reports_battle(uid, is_read);
CREATE INDEX idx_reports_battle_timestamp ON reports_battle(timestamp);

-- Trade reports
CREATE TABLE IF NOT EXISTS reports_trade (
    id SERIAL PRIMARY KEY,
    uid INTEGER NOT NULL,
    village_from INTEGER,
    village_to INTEGER,
    timestamp TIMESTAMP DEFAULT NOW(),
    trade_type VARCHAR(20),
    resources TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_reports_trade_uid ON reports_trade(uid);

-- System reports
CREATE TABLE IF NOT EXISTS reports_system (
    id SERIAL PRIMARY KEY,
    uid INTEGER NOT NULL,
    timestamp TIMESTAMP DEFAULT NOW(),
    type VARCHAR(50),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (uid) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_reports_system_uid ON reports_system(uid);
```

### Report Retention Policy

**Auto-delete rules:**
- Battle reports: 30 days
- Trade reports: 14 days
- System reports: 7 days
- Archived reports: Never auto-delete

**Background Worker:**
```php
// Runs daily at 3 AM
function purgeOldReports() {
    DELETE FROM reports_battle WHERE archived=false AND timestamp < NOW() - INTERVAL '30 days';
    DELETE FROM reports_trade WHERE timestamp < NOW() - INTERVAL '14 days';
    DELETE FROM reports_system WHERE timestamp < NOW() - INTERVAL '7 days';
}
```

### Battle Report Generation (COMPLEX)

**Combat Calculation:**
```php
function generateBattleReport($attackerId, $defenderId, $attackerTroops, $defenderTroops) {
    // 1. Calculate attack power
    $attackPower = 0;
    foreach ($attackerTroops as $unitType => $count) {
        $attackPower += $count * UNIT_STATS[$unitType]['attack'];
    }
    
    // 2. Calculate defense power
    $defensePower = 0;
    foreach ($defenderTroops as $unitType => $count) {
        $defensePower += $count * UNIT_STATS[$unitType]['defense'];
    }
    
    // 3. Apply bonuses (hero, buildings, artifacts)
    $attackPower *= (1 + $attackerHeroBonus + $smithyBonus);
    $defensePower *= (1 + $defenderHeroBonus + $wallBonus + $artifactBonus);
    
    // 4. Calculate casualties
    $attackerLosses = ($defensePower / $attackPower) * 0.5; // 50% base casualty rate
    $defenderLosses = ($attackPower / $defensePower) * 0.5;
    
    // 5. Determine winner
    $result = $attackPower > $defensePower ? 'attacker_won' : 'defender_won';
    
    // 6. Calculate resources captured (if attacker won)
    $resourcesCaptured = 0;
    if ($result === 'attacker_won') {
        $carryCapacity = calculateCarryCapacity($attackerTroops - $attackerCasualties);
        $availableResources = getVillageResources($defenderId);
        $resourcesCaptured = min($carryCapacity, $availableResources);
    }
    
    return [
        'result' => $result,
        'attacker_casualties' => $attackerCasualties,
        'defender_casualties' => $defenderCasualties,
        'resources_captured' => $resourcesCaptured
    ];
}
```

### Endpoints

#### 1. getReports
```php
public function getReports()
{
    // Validate: worldId, uid, type (all/attack/defense/trade/system)
    // Query: UNION of reports_battle, reports_trade, reports_system
    // Filters: unreadOnly, type filter
    // Order: timestamp DESC
    // Pagination: LIMIT 50, OFFSET X
    // Response: {reports: [{id, type, title, timestamp, isRead, preview}], total, unreadCount}
    // Performance: <125ms (requires optimized indexes)
}
```

#### 2. getReportDetails (COMPLEX)
```php
public function getReportDetails()
{
    // Validate: worldId, uid, reportId
    // Query: JOIN with users, vdata for names/coordinates
    // Format: Full battle breakdown, troop details, resources
    // Auto-mark: UPDATE is_read=true
    // Response: {report: {attacker, defender, troops, casualties, resources, result}}
    // Performance: <150ms
}
```

#### 3-6. Standard CRUD
```php
// markRead: Batch update is_read=true for reportIds[]
// deleteReport: DELETE WHERE id IN (reportIds)
// archiveReport: UPDATE archived=true
// getUnreadCount: COUNT(*) WHERE is_read=false, grouped by type
```

---

## 4. MessagesCtrl (8 endpoints) - Build Fourth

**File:** `sections/api/include/Api/Ctrl/MessagesCtrl.php`  
**Priority:** IMPORTANT  
**Complexity:** MEDIUM (threading, alliance chat)

### Schema Additions

```sql
-- Message threads (conversations)
CREATE TABLE IF NOT EXISTS messages_threads (
    id SERIAL PRIMARY KEY,
    subject VARCHAR(255),
    participants INTEGER[],
    created_at TIMESTAMP DEFAULT NOW(),
    last_message_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_messages_threads_participants ON messages_threads USING GIN(participants);

-- Messages
CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    thread_id INTEGER,
    from_uid INTEGER NOT NULL,
    to_uid INTEGER NOT NULL,
    subject VARCHAR(255),
    body TEXT,
    timestamp TIMESTAMP DEFAULT NOW(),
    is_read BOOLEAN DEFAULT FALSE,
    folder VARCHAR(20) DEFAULT 'inbox',
    attachments TEXT,
    FOREIGN KEY (from_uid) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_uid) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (thread_id) REFERENCES messages_threads(id) ON DELETE CASCADE
);
CREATE INDEX idx_messages_to_uid ON messages(to_uid);
CREATE INDEX idx_messages_from_uid ON messages(from_uid);
CREATE INDEX idx_messages_folder ON messages(to_uid, folder);
CREATE INDEX idx_messages_is_read ON messages(to_uid, is_read);

-- Alliance messages (chat)
CREATE TABLE IF NOT EXISTS alliance_messages (
    id SERIAL PRIMARY KEY,
    alliance_id INTEGER NOT NULL,
    sender_uid INTEGER NOT NULL,
    message TEXT,
    timestamp TIMESTAMP DEFAULT NOW(),
    is_announcement BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (alliance_id) REFERENCES alliance(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_uid) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_alliance_messages_alliance_id ON alliance_messages(alliance_id);
CREATE INDEX idx_alliance_messages_timestamp ON alliance_messages(alliance_id, timestamp DESC);
```

### Message Features

**Folders:**
- Inbox (default)
- Sent
- Archive
- Trash (auto-delete after 7 days)

**Alliance Chat:**
- Regular messages (all members can send)
- Announcements (leaders/officers only, highlighted)
- Max history: 500 messages
- Pagination: 50 messages per page

**Moderation (Admin API later):**
- Delete inappropriate messages
- Ban users from alliance chat
- Mute users (temporary)

### Endpoints

#### 1. getInbox
```php
public function getInbox()
{
    // Validate: worldId, uid, folder (inbox/sent/archive)
    // Query: messages WHERE to_uid=X AND folder=Y
    // Join: users for sender name
    // Pagination: LIMIT 50
    // Response: {messages: [{id, from, subject, timestamp, isRead, hasAttachment}], total}
    // Performance: <100ms
}
```

#### 2. getMessage
```php
public function getMessage()
{
    // Validate: worldId, uid, messageId
    // Check: Authorization (to_uid=uid OR from_uid=uid)
    // Query: messages JOIN users
    // Auto-mark: is_read=true if to_uid=uid
    // Response: {message: {from, to, subject, body, timestamp, attachments}}
    // Performance: <75ms
}
```

#### 3. sendMessage
```php
public function sendMessage()
{
    // Validate: worldId, uid, toUid, subject, body
    // Check: recipient exists, not blocked
    // Create/find thread: Check existing thread between users
    // Insert: messages (from_uid, to_uid, subject, body)
    // Response: {success, messageId, threadId}
    // Performance: <100ms
}
```

#### 4-5. Message Management
```php
// deleteMessage: UPDATE folder='trash' OR DELETE if already in trash
// archiveMessage: UPDATE folder='archive'
```

#### 6. getAllianceMessages
```php
public function getAllianceMessages()
{
    // Validate: worldId, allianceId
    // Check: User is alliance member
    // Query: alliance_messages WHERE alliance_id=X
    // Order: timestamp DESC
    // Pagination: LIMIT 50
    // Response: {messages: [{id, sender, message, timestamp, isAnnouncement}], total}
    // Performance: <125ms
}
```

#### 7. sendAllianceMessage
```php
public function sendAllianceMessage()
{
    // Validate: worldId, allianceId, uid, message
    // Check: Alliance membership
    // Check: If isAnnouncement=true, verify leader/officer role
    // Insert: alliance_messages
    // Emit: WebSocket event for real-time chat (Phase 4C+)
    // Response: {success, messageId}
    // Performance: <100ms
}
```

#### 8. getUnreadCount
```php
public function getUnreadCount()
{
    // Query: COUNT(*) WHERE to_uid=X AND is_read=false
    // Query: COUNT(*) alliance_messages WHERE timestamp > last_read_timestamp
    // Response: {unreadCount, inboxCount, allianceCount}
    // Performance: <50ms
    // Caching: Cache for 60 seconds
}
```

---

## 5. StatisticsCtrl (6 endpoints) - Build Fifth

**File:** `sections/api/include/Api/Ctrl/StatisticsCtrl.php`  
**Priority:** IMPORTANT  
**Complexity:** HIGH (aggregation, caching critical)

### Schema Additions

```sql
-- Statistics snapshots (pre-calculated, updated hourly)
CREATE TABLE IF NOT EXISTS statistics_snapshots (
    id SERIAL PRIMARY KEY,
    world_id VARCHAR(50) NOT NULL,
    snapshot_time TIMESTAMP DEFAULT NOW(),
    total_players INTEGER DEFAULT 0,
    total_villages INTEGER DEFAULT 0,
    total_alliances INTEGER DEFAULT 0,
    active_players INTEGER DEFAULT 0,
    total_population BIGINT DEFAULT 0
);
CREATE INDEX idx_statistics_snapshots_world_id ON statistics_snapshots(world_id);
CREATE INDEX idx_statistics_snapshots_time ON statistics_snapshots(snapshot_time);

-- Leaderboard cache (updated every 5 minutes)
CREATE TABLE IF NOT EXISTS leaderboard_cache (
    id SERIAL PRIMARY KEY,
    world_id VARCHAR(50) NOT NULL,
    category VARCHAR(50) NOT NULL,
    rank INTEGER NOT NULL,
    uid INTEGER,
    alliance_id INTEGER,
    value BIGINT NOT NULL,
    cached_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(world_id, category, rank)
);
CREATE INDEX idx_leaderboard_cache_lookup ON leaderboard_cache(world_id, category);
CREATE INDEX idx_leaderboard_cache_uid ON leaderboard_cache(uid);
```

### Performance Optimization Strategy

**Problem:** Ranking queries are expensive (aggregate all users, sort, rank)

**Solution:** Multi-tier caching
1. **Leaderboard Cache Table** (5-minute refresh)
2. **Redis Cache** (60-second TTL)
3. **Background Worker** (pre-calculate rankings)

### Background Worker: Rankings Calculator

```php
// Runs every 5 minutes
function updateRankings($worldId) {
    // 1. Population rankings
    $stmt = $db->prepare("
        SELECT u.id, u.name, SUM(v.pop) as total_pop, u.alliance_id
        FROM users u
        JOIN vdata v ON v.owner = u.id
        GROUP BY u.id
        ORDER BY total_pop DESC
        LIMIT 1000
    ");
    $results = $stmt->fetchAll();
    
    // 2. Clear old cache
    $db->exec("DELETE FROM leaderboard_cache WHERE world_id='$worldId' AND category='population'");
    
    // 3. Insert new rankings
    foreach ($results as $rank => $row) {
        $db->exec("INSERT INTO leaderboard_cache (world_id, category, rank, uid, value) 
                   VALUES ('$worldId', 'population', $rank+1, {$row['id']}, {$row['total_pop']})");
    }
    
    // Repeat for: attack_points, defense_points, villages, etc.
}
```

### Endpoints

#### 1. getPlayerRankings (COMPLEX)
```php
public function getPlayerRankings()
{
    // Validate: worldId, category (population/attack/defense/villages)
    // Check cache: Redis key "rankings:{worldId}:{category}"
    // If cache miss:
    //   Query: leaderboard_cache WHERE world_id=X AND category=Y
    //   Join: users, alliance for names
    //   Cache: TTL 60 seconds
    // Pagination: LIMIT 50, OFFSET X
    // Response: {rankings: [{rank, uid, name, value, villages, alliance}], total}
    // Performance: <200ms (with cache), <50ms (cache hit)
}
```

#### 2. getAllianceRankings
```php
public function getAllianceRankings()
{
    // Similar to player rankings
    // Categories: population, members, attack, defense
    // Aggregate: SUM of all member stats
    // Performance: <200ms
}
```

#### 3. getPlayerStats
```php
public function getPlayerStats()
{
    // Validate: worldId, uid
    // Query: User's rank from leaderboard_cache
    // Query: Total villages, population, points
    // Response: {stats: {rank, population, villages, attackPoints, defensePoints, alliance}}
    // Performance: <100ms
}
```

#### 4. getAllianceStats
```php
public function getAllianceStats()
{
    // Query: alliance table JOIN leaderboard_cache
    // Aggregate: member stats
    // Response: {stats: {rank, population, members, avgPopulation, totalAttack, totalDefense}}
    // Performance: <125ms
}
```

#### 5. getTop10
```php
public function getTop10()
{
    // Query: leaderboard_cache LIMIT 10 for each category
    // Categories: attackers, defenders, population, alliances
    // Cache: Redis, TTL 10 minutes
    // Response: {top10: {attackers: [], defenders: [], richest: [], alliances: []}}
    // Performance: <150ms (cache miss), <20ms (cache hit)
}
```

#### 6. getWorldStats
```php
public function getWorldStats()
{
    // Query: statistics_snapshots latest snapshot
    // Fallback: Calculate if no snapshot
    // Calculate: active_players = players with activity in last 7 days
    // Cache: Redis, TTL 15 minutes
    // Response: {stats: {totalPlayers, totalVillages, totalAlliances, activePlayers}}
    // Performance: <100ms
}
```

---

# Comprehensive Testing Strategy

## Manual Testing Checklist

### Current 26 Endpoints (Phase 4A)

**Village API (5 endpoints):**
```bash
# Test 1: Get village list
curl -X POST http://localhost:5000/v1/village/getVillageList \
  -H "Content-Type: application/json" \
  -d '{"worldId":"testworld","uid":2,"lang":"en-US"}'
# Expected: Array of 7 villages for TestPlayer1

# Test 2: Get village details
curl -X POST http://localhost:5000/v1/village/getVillageDetails \
  -H "Content-Type: application/json" \
  -d '{"worldId":"testworld","villageId":REPLACE_WITH_ACTUAL_KID,"lang":"en-US"}'
# Expected: Full village object with buildings, resources

# Test 3: Get resources with real-time calculation
# Test 4: Get building queue
# Test 5: Upgrade building (validation test)
```

**Map API (5 endpoints):**
```bash
# Test map data in 20x20 range
# Test village info at coordinates
# Test tile details (oasis/village/empty)
# Test search villages (min 3 chars)
# Test nearby villages (radius 10)
```

**Troop, Alliance, Market APIs:** Similar curl test scripts

### Phase 4B Endpoints (34 new)

**Hero API (9 endpoints):**
- Test hero stats retrieval
- Test level up with attribute distribution
- Test equip item
- Test start adventure (complex calculation)
- Test adventures list
- Test sell item
- Test auction CRUD

**Quest, Reports, Messages, Statistics:** Comprehensive test scenarios

## Automated Integration Test Suite

### Directory Structure

```
tests/
├── api/
│   ├── VillageApiTest.php
│   ├── MapApiTest.php
│   ├── TroopApiTest.php
│   ├── AllianceApiTest.php
│   ├── MarketApiTest.php
│   ├── HeroApiTest.php
│   ├── QuestApiTest.php
│   ├── ReportsApiTest.php
│   ├── MessagesApiTest.php
│   └── StatisticsApiTest.php
├── integration/
│   ├── GameFlowTest.php
│   ├── CombatFlowTest.php
│   └── AllianceFlowTest.php
├── helpers/
│   ├── ApiTestHelper.php
│   └── DatabaseHelper.php
└── fixtures/
    ├── test-accounts.json
    └── test-villages.json
```

### Test Framework: Curl + JQ (Simple, No Dependencies)

**File:** `tests/run-api-tests.sh`

```bash
#!/bin/bash
# Automated API Integration Tests

API_BASE="http://localhost:5000"
FAILED=0
PASSED=0

# Helper function: Test API endpoint
test_api() {
    local name="$1"
    local endpoint="$2"
    local payload="$3"
    local expected_key="$4"
    
    echo "Testing: $name..."
    
    response=$(curl -s -X POST "$API_BASE$endpoint" \
        -H "Content-Type: application/json" \
        -d "$payload")
    
    # Check if response contains expected key
    if echo "$response" | jq -e ".$expected_key" > /dev/null 2>&1; then
        echo "✅ PASS: $name"
        ((PASSED++))
    else
        echo "❌ FAIL: $name"
        echo "Response: $response"
        ((FAILED++))
    fi
}

# Test Village API
test_api "Village List" \
    "/v1/village/getVillageList" \
    '{"worldId":"testworld","uid":2,"lang":"en-US"}' \
    "villages"

test_api "Village Details" \
    "/v1/village/getVillageDetails" \
    '{"worldId":"testworld","villageId":12345,"lang":"en-US"}' \
    "village"

# Test Map API
test_api "Map Data" \
    "/v1/map/getMapData" \
    '{"worldId":"testworld","x1":-10,"y1":-10,"x2":10,"y2":10,"limit":100,"offset":0,"lang":"en-US"}' \
    "tiles"

# ... more tests

echo ""
echo "Results: $PASSED passed, $FAILED failed"
exit $FAILED
```

### PHPUnit Alternative (If Preferred)

**File:** `tests/api/VillageApiTest.php`

```php
<?php
use PHPUnit\Framework\TestCase;

class VillageApiTest extends TestCase
{
    private $apiBase = 'http://localhost:5000';
    
    public function testGetVillageList()
    {
        $response = $this->callApi('/v1/village/getVillageList', [
            'worldId' => 'testworld',
            'uid' => 2,
            'lang' => 'en-US'
        ]);
        
        $this->assertArrayHasKey('villages', $response);
        $this->assertIsArray($response['villages']);
        $this->assertCount(7, $response['villages']); // TestPlayer1 has 7 villages
    }
    
    public function testMissingParameter()
    {
        $response = $this->callApi('/v1/village/getVillageList', [
            'uid' => 2,
            'lang' => 'en-US'
            // Missing worldId
        ]);
        
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('MissingParameterException', $response['error']['errorType']);
    }
    
    private function callApi($endpoint, $payload)
    {
        $ch = curl_init($this->apiBase . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```

### Integration Test Scenarios

#### 1. Village Management Flow
```php
// 1. List villages
// 2. Get resources for village
// 3. Upgrade building (simulate)
// 4. Check building queue
// Assertion: Queue contains new building
```

#### 2. Combat Flow
```php
// 1. Get troops from village A
// 2. Send attack to village B
// 3. Verify movement created
// 4. (Simulate time passage)
// 5. Process movement arrival
// 6. Check battle report generated
// Assertion: Report shows correct casualties
```

#### 3. Alliance Flow
```php
// 1. Create alliance
// 2. Invite player
// 3. Accept invite (as other player)
// 4. Set diplomacy (war/peace/NAP)
// 5. Get alliance members
// Assertion: Member list includes both players
```

### Performance Benchmarking

**Tool:** ApacheBench (ab)

```bash
# Test 100 concurrent requests to getVillageList
ab -n 1000 -c 100 -p payload.json -T application/json \
   http://localhost:5000/v1/village/getVillageList

# Expected: <200ms average response time
```

**Payload file (payload.json):**
```json
{"worldId":"testworld","uid":2,"lang":"en-US"}
```

---

# Production Deployment Guide

## Pre-Deployment Checklist

### 1. Database Readiness
- ✅ All schema migrations complete
- ✅ Indexes created on critical columns
- ✅ Backup strategy in place
- ✅ Connection pooling configured
- ⏳ Production DATABASE_URL set

### 2. Environment Configuration
- ✅ BREVO_API_KEY secret configured
- ✅ Database credentials in environment
- ⏳ Production domain configured
- ⏳ SSL certificate (Replit auto-provides)
- ⏳ Error logging destination (Papertrail/Logtail)

### 3. Code Optimization
- ⏳ PHP opcache enabled
- ⏳ Remove debug code
- ⏳ Disable error display (production)
- ⏳ Enable error logging only
- ✅ All APIs tested and verified

### 4. Security Hardening
- ✅ SQL injection prevention (prepared statements)
- ⏳ CORS configuration for API
- ⏳ Rate limiting (optional Phase 4C+)
- ⏳ API authentication/authorization (JWT)
- ⏳ HTTPS enforcement

### 5. Monitoring Setup
- ⏳ Error logging (Monolog → Papertrail)
- ⏳ Performance monitoring (APM)
- ⏳ Database query monitoring (PgHero)
- ⏳ Uptime monitoring (Replit built-in)

## Replit Deployment Configuration

### Using deploy_config_tool

```php
// Deployment type: VM (stateful, always-running for game server)
deploy_config_tool([
    'deployment_target' => 'vm',
    'build' => ['composer', 'install', '--no-dev', '--optimize-autoloader'],
    'run' => ['php', '-S', '0.0.0.0:5000', 'router.php']
]);
```

**Alternative: Nginx + PHP-FPM (Production-grade)**

Create `.replit` deployment config:
```toml
[deployment]
build = ["sh", "-c", "composer install --no-dev && nginx -t"]
run = ["sh", "-c", "php-fpm && nginx -g 'daemon off;'"]

[[ports]]
localPort = 5000
externalPort = 80
```

### PHP Production Settings

**File:** `php.ini` (production)

```ini
[PHP]
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL
log_errors = On
error_log = /tmp/php-errors.log

opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2

max_execution_time = 30
memory_limit = 256M
```

### Nginx Configuration (If Using)

**File:** `nginx.conf`

```nginx
server {
    listen 5000;
    server_name _;
    root /home/runner/TravianT4.6;
    index index.php index.html;

    # Static files (Angular frontend)
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # API endpoints
    location /v1/ {
        try_files $uri $uri/ /router.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
```

## Monitoring & Logging

### Error Logging with Monolog

**File:** `sections/api/bootstrap.php` (add logging)

```php
<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;

// Create logger
$log = new Logger('travian-api');

// Development: Log to file
if (getenv('ENVIRONMENT') === 'development') {
    $log->pushHandler(new StreamHandler('/tmp/api.log', Logger::DEBUG));
}

// Production: Log to syslog (Papertrail)
if (getenv('ENVIRONMENT') === 'production') {
    $log->pushHandler(new SyslogHandler('travian-api', LOG_USER, Logger::WARNING));
}

// Example usage in controllers
try {
    // API logic
} catch (Exception $e) {
    $log->error('API Error: ' . $e->getMessage(), [
        'endpoint' => $_SERVER['REQUEST_URI'],
        'payload' => $this->payload,
        'trace' => $e->getTraceAsString()
    ]);
    throw $e;
}
```

### Performance Monitoring

**Simple APM (Application Performance Monitoring):**

```php
// Measure API response time
$startTime = microtime(true);

// ... API logic ...

$endTime = microtime(true);
$responseTime = ($endTime - $startTime) * 1000; // ms

if ($responseTime > 200) {
    $log->warning("Slow API response", [
        'endpoint' => $_SERVER['REQUEST_URI'],
        'time_ms' => $responseTime
    ]);
}

// Add to response headers (development only)
if (getenv('ENVIRONMENT') === 'development') {
    header('X-Response-Time: ' . round($responseTime, 2) . 'ms');
}
```

---

# AI NPC Architecture

## Core Philosophy: 95% Rules + 5% LLM

**Why this split:**
- Game mechanics are deterministic (resource production, combat formulas)
- Most decisions follow clear rules (build orders, attack timing)
- LLMs add unpredictability and human-like behavior
- Cost-effective (LLM inference expensive at scale)

## AI Agent Structure

```
NPC Player (AI Agent)
├── State Management
│   ├── Villages (resources, buildings, troops)
│   ├── Goals (short-term, long-term)
│   ├── Personality (aggressive, defensive, economic, expansionist)
│   └── Memory (recent actions, threats, opportunities)
│
├── Rule-Based Engine (95%)
│   ├── Economy Module
│   │   ├── Resource balancing
│   │   ├── Building upgrade priorities
│   │   ├── Trade decisions
│   │   └── Village expansion
│   │
│   ├── Military Module
│   │   ├── Troop training schedules
│   │   ├── Attack target selection
│   │   ├── Defense allocation
│   │   └── Movement timing
│   │
│   ├── Expansion Module
│   │   ├── Settler production
│   │   ├── Village placement (distance, resources)
│   │   ├── Oasis capture
│   │   └── Population growth
│   │
│   └── Diplomacy Module
│       ├── Alliance joining decisions
│       ├── Trade partner selection
│       ├── War/peace decisions
│       └── NAP agreements
│
└── LLM Module (5%)
    ├── Strategic Planning
    │   ├── Long-term goal setting
    │   ├── Adaptation to player strategies
    │   └── Coalition formation
    │
    ├── Social Interaction
    │   ├── Message responses
    │   ├── Alliance chat participation
    │   └── Negotiation tactics
    │
    └── Unpredictable Behavior
        ├── Random strategic pivots
        ├── Creative attack patterns
        └── Surprising alliances
```

## Rule-Based AI Implementation

### Economy Module

**Resource Balancing:**
```php
class EconomyModule
{
    public function balanceResources($village)
    {
        $resources = $village->getResources();
        $production = $village->getProduction();
        
        // Calculate resource ratios
        $ratios = [
            'wood' => $resources['wood'] / $production['wood'],
            'clay' => $resources['clay'] / $production['clay'],
            'iron' => $resources['iron'] / $production['iron'],
            'crop' => $resources['crop'] / $production['crop']
        ];
        
        // Find deficit resource
        $deficitResource = array_search(min($ratios), $ratios);
        
        // Prioritize deficit resource fields for upgrade
        return $this->upgradePriority($village, $deficitResource);
    }
    
    public function upgradePriority($village, $deficitResource)
    {
        // Build order priorities
        $priorities = [
            // Early game: Resource fields
            ['type' => $deficitResource . '_field', 'maxLevel' => 5],
            
            // Mid game: Warehouse, granary
            ['type' => 'warehouse', 'maxLevel' => 10],
            ['type' => 'granary', 'maxLevel' => 10],
            
            // Late game: All fields to 10
            ['type' => 'all_fields', 'maxLevel' => 10]
        ];
        
        return $this->selectNextBuilding($village, $priorities);
    }
}
```

**Building Upgrade Logic:**
```php
public function selectNextBuilding($village, $priorities)
{
    foreach ($priorities as $priority) {
        $building = $this->findUpgradableBuilding($village, $priority);
        if ($building && $this->canAfford($village, $building)) {
            return $building;
        }
    }
    return null;
}
```

### Military Module

**Troop Training Schedule:**
```php
class MilitaryModule
{
    private $personalities = [
        'aggressive' => ['offensive' => 0.7, 'defensive' => 0.3],
        'defensive' => ['offensive' => 0.3, 'defensive' => 0.7],
        'balanced' => ['offensive' => 0.5, 'defensive' => 0.5]
    ];
    
    public function trainTroops($village, $personality)
    {
        $availableResources = $village->getResources();
        $unitMix = $this->personalities[$personality];
        
        // Calculate how many troops we can train
        $offensiveUnits = $this->calculateUnits($availableResources, 'offensive', $unitMix['offensive']);
        $defensiveUnits = $this->calculateUnits($availableResources, 'defensive', $unitMix['defensive']);
        
        // Queue training
        $this->queueTraining($village, $offensiveUnits);
        $this->queueTraining($village, $defensiveUnits);
    }
}
```

**Attack Target Selection:**
```php
public function selectAttackTarget($attacker, $worldState)
{
    // 1. Find targets in range (distance < 50 tiles)
    $targets = $worldState->getVillagesInRadius($attacker->position, 50);
    
    // 2. Filter by profitability
    $profitableTargets = array_filter($targets, function($target) use ($attacker) {
        $resources = $this->estimateResources($target);
        $distance = $this->calculateDistance($attacker, $target);
        $travelTime = $distance * 60; // seconds
        
        // Only attack if resources > 1000 per hour of travel
        return $resources > ($travelTime / 3600) * 1000;
    });
    
    // 3. Sort by profitability (resources / distance)
    usort($profitableTargets, function($a, $b) use ($attacker) {
        $profitA = $this->estimateResources($a) / $this->calculateDistance($attacker, $a);
        $profitB = $this->estimateResources($b) / $this->calculateDistance($attacker, $b);
        return $profitB <=> $profitA;
    });
    
    // 4. Return top target
    return $profitableTargets[0] ?? null;
}
```

### Expansion Module

**Village Placement:**
```php
public function selectVillageLocation($player, $worldState)
{
    // Find unoccupied tiles within expansion range
    $candidates = $worldState->getEmptyTiles($player->territory, 20);
    
    // Score each tile
    $scored = array_map(function($tile) use ($player) {
        $score = 0;
        
        // 1. Resource bonus (15c, 9c, etc.)
        $score += $tile->getCropBonus() * 10;
        
        // 2. Nearby oases
        $nearbyOases = $this->countNearbyOases($tile, 3);
        $score += $nearbyOases * 5;
        
        // 3. Distance from capital (prefer closer)
        $distance = $this->calculateDistance($player->capital, $tile);
        $score -= $distance * 2;
        
        // 4. Defensive position (avoid corners)
        if ($this->isDefensible($tile)) {
            $score += 15;
        }
        
        return ['tile' => $tile, 'score' => $score];
    }, $candidates);
    
    // Return highest scored tile
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    return $scored[0]['tile'];
}
```

### Diplomacy Module

**Alliance Decision:**
```php
public function shouldJoinAlliance($player, $alliance, $worldState)
{
    // Calculate alliance strength
    $allianceStrength = $alliance->getTotalPopulation() + $alliance->getTotalAttackPoints();
    
    // Calculate player strength
    $playerStrength = $player->getTotalPopulation();
    
    // Join if:
    // 1. Alliance is top 10
    // 2. Alliance strength > player strength * 5
    // 3. Alliance members > 10
    $topAlliances = $worldState->getTopAlliances(10);
    
    return in_array($alliance, $topAlliances) ||
           ($allianceStrength > $playerStrength * 5 && $alliance->getMemberCount() > 10);
}
```

## LLM Integration (5%)

### Local LLM Setup

**Hardware:** RTX 3090 Ti (24GB) + Tesla P40s (24GB) or RTX 3060Ti (12GB)

**Recommended Models:**
- **Llama 3.1 8B** (Fast, fits on single GPU)
- **Mistral 7B** (Excellent reasoning)
- **Qwen 2.5 7B** (Strong strategic thinking)

**Inference Framework:**
- **llama.cpp** - Simple, efficient, GGUF format
- **vLLM** - High throughput, batch processing
- **TGI** (Text Generation Inference) - Production-grade

### LLM Server Setup (llama.cpp)

**Installation:**
```bash
# Clone llama.cpp
git clone https://github.com/ggerganov/llama.cpp
cd llama.cpp

# Build with CUDA support (RTX GPUs)
make LLAMA_CUBLAS=1

# Download model (Llama 3.1 8B GGUF)
wget https://huggingface.co/TheBloke/Llama-3.1-8B-GGUF/resolve/main/llama-3.1-8b.Q5_K_M.gguf

# Start server
./server -m llama-3.1-8b.Q5_K_M.gguf \
  --host 0.0.0.0 \
  --port 8080 \
  --n-gpu-layers 35 \
  --ctx-size 4096
```

**API Endpoint:**
```
POST http://localhost:8080/completion
{
  "prompt": "You are an AI player in Travian...",
  "max_tokens": 200,
  "temperature": 0.7
}
```

### PHP LLM Client

**File:** `ai/LLMClient.php`

```php
<?php
namespace AI;

class LLMClient
{
    private $baseUrl = 'http://localhost:8080';
    
    public function generateStrategy($context)
    {
        $prompt = $this->buildPrompt($context);
        
        $response = $this->callLLM([
            'prompt' => $prompt,
            'max_tokens' => 200,
            'temperature' => 0.7,
            'stop' => ['\n\n']
        ]);
        
        return $this->parseResponse($response);
    }
    
    private function buildPrompt($context)
    {
        return <<<PROMPT
You are an AI player in a Travian-style strategy game. Make a strategic decision based on this situation:

Current Status:
- Villages: {$context['villages']}
- Population: {$context['population']}
- Military Strength: {$context['military']}
- Resources: {$context['resources']}

Recent Events:
{$context['events']}

Question: {$context['question']}

Respond with a single strategic decision (attack/expand/defend/trade) and brief reasoning.
PROMPT;
    }
    
    private function callLLM($params)
    {
        $ch = curl_init($this->baseUrl . '/completion');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```

### When to Use LLM vs Rules

**Use Rules (95%):**
- Resource production calculations
- Building upgrade decisions
- Troop training schedules
- Combat calculations
- Movement timing
- Standard attack target selection

**Use LLM (5%):**
- Strategic pivots (when to switch from economy to military)
- Alliance diplomacy (negotiate terms, respond to messages)
- Adapting to player behavior (learning opponent patterns)
- Creative tactics (surprise attacks, unconventional strategies)
- Social interaction (alliance chat, player messages)

### LLM Decision Points

**Example 1: Strategic Planning (Weekly)**
```php
// Called once per week for each NPC
$context = [
    'villages' => $npc->getVillageCount(),
    'population' => $npc->getTotalPopulation(),
    'military' => $npc->getTotalTroops(),
    'resources' => $npc->getTotalResources(),
    'events' => $npc->getRecentEvents(), // Last 7 days
    'question' => 'What should be your focus for the next week: expand, attack, defend, or trade?'
];

$decision = $llm->generateStrategy($context);
$npc->setWeeklyGoal($decision);
```

**Example 2: Message Response**
```php
// When player sends message to NPC
$context = [
    'message' => $playerMessage,
    'relationship' => $npc->getRelationshipWith($playerId), // friendly/neutral/hostile
    'question' => 'How should you respond to this message?'
];

$response = $llm->generateStrategy($context);
$npc->sendMessage($playerId, $response);
```

**Example 3: Adaptive Tactics**
```php
// When NPC repeatedly loses to same player
$context = [
    'opponent' => $player->getStats(),
    'recent_battles' => $npc->getBattleHistoryWith($playerId),
    'question' => 'The opponent keeps defeating you. What tactical change should you make?'
];

$adaptation = $llm->generateStrategy($context);
$npc->updateTactics($adaptation);
```

## NPC Behavior Archetypes

### 1. Aggressive Raider
- **Goal:** Maximize attack points
- **Economy:** 30% focus
- **Military:** 70% focus
- **Expansion:** Low priority
- **Behavior:** Frequent raids, targets weak players

### 2. Defensive Turtle
- **Goal:** Survive to late game
- **Economy:** 50% focus
- **Military:** 50% defense focus
- **Expansion:** Slow, defensive positions
- **Behavior:** Strong walls, large garrisons

### 3. Economic Builder
- **Goal:** Maximize population
- **Economy:** 70% focus
- **Military:** 30% defense only
- **Expansion:** Fast village growth
- **Behavior:** Trading, resource production

### 4. Expansionist
- **Goal:** Most villages
- **Economy:** 60% focus (for settlers)
- **Military:** 40% focus (protect expansion)
- **Expansion:** Rapid settler production
- **Behavior:** Quick land grab, oasis capture

### 5. Diplomatic Leader
- **Goal:** Build strong alliance
- **Economy:** 50% focus
- **Military:** 50% balanced
- **Expansion:** Moderate
- **Behavior:** Alliance formation, coordinated attacks

## Scaling: 50-500 NPCs

### Tick-Based Processing

**Architecture:**
```
Game Tick (every 5 minutes)
├── Priority Queue (sorted by activity level)
├── Batch 1: Active NPCs (recently attacked/attacked)
├── Batch 2: Growing NPCs (building, training)
├── Batch 3: Idle NPCs (no recent activity)
└── LLM Batch (5% of decisions)
```

**Processing Strategy:**
```php
// Run every 5 minutes
function processTick($npcs)
{
    // 1. Sort NPCs by priority
    $prioritized = $this->prioritizeNPCs($npcs);
    
    // 2. Process high-priority NPCs first
    foreach ($prioritized as $npc) {
        // Rule-based decisions (fast)
        $npc->updateResources();
        $npc->processQueues();
        $npc->makeDecisions();
        
        // LLM decisions (5% chance, async)
        if (rand(1, 100) <= 5) {
            $this->queueLLMDecision($npc);
        }
    }
    
    // 3. Process LLM queue (batched)
    $this->processLLMQueue();
}
```

**Performance Target:**
- 100 NPCs: 5 seconds per tick
- 500 NPCs: 25 seconds per tick
- LLM calls: 5-25 per tick (batched)

### Redis-Based Work Distribution

**For multi-server setup:**
```php
// Worker 1: Economy & Expansion
Redis::lpush('npc_economy_queue', $npcId);

// Worker 2: Military & Combat
Redis::lpush('npc_military_queue', $npcId);

// Worker 3: LLM Processing
Redis::lpush('npc_llm_queue', $npcId);
```

---

# Background Workers

## Worker Architecture

### Required Workers

1. **ResourceProductionWorker** - Updates village resources (every 1 minute)
2. **MovementProcessorWorker** - Handles troop arrivals (every 30 seconds)
3. **BuildingQueueWorker** - Completes construction (every 1 minute)
4. **TrainingQueueWorker** - Completes troop training (every 1 minute)
5. **MarketWorker** - Processes merchant arrivals (every 30 seconds)
6. **AdventureWorker** - Completes hero adventures (every 5 minutes)
7. **NPCTickWorker** - Processes NPC decisions (every 5 minutes)
8. **RankingsWorker** - Updates leaderboards (every 5 minutes)

### Scheduling: Supervisor (Recommended)

**File:** `supervisor.conf`

```ini
[program:resource_worker]
command=php /home/runner/TravianT4.6/workers/ResourceProductionWorker.php
autostart=true
autorestart=true
stderr_logfile=/tmp/resource_worker.err.log
stdout_logfile=/tmp/resource_worker.out.log

[program:movement_worker]
command=php /home/runner/TravianT4.6/workers/MovementProcessorWorker.php
autostart=true
autorestart=true
stderr_logfile=/tmp/movement_worker.err.log
stdout_logfile=/tmp/movement_worker.out.log

# ... more workers
```

### Alternative: Cron Jobs

**File:** `crontab`

```cron
# Resource production (every minute)
* * * * * php /home/runner/TravianT4.6/workers/ResourceProductionWorker.php

# Movement processor (every 30 seconds)
* * * * * php /home/runner/TravianT4.6/workers/MovementProcessorWorker.php
* * * * * sleep 30; php /home/runner/TravianT4.6/workers/MovementProcessorWorker.php

# Building queue (every minute)
* * * * * php /home/runner/TravianT4.6/workers/BuildingQueueWorker.php

# Rankings update (every 5 minutes)
*/5 * * * * php /home/runner/TravianT4.6/workers/RankingsWorker.php
```

## Worker Implementation

### 1. ResourceProductionWorker

**File:** `workers/ResourceProductionWorker.php`

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Core\Server;
use Database\ServerDB;

class ResourceProductionWorker
{
    public function run()
    {
        // Get all active worlds
        $worlds = Server::getAllWorlds();
        
        foreach ($worlds as $world) {
            $this->updateWorldResources($world);
        }
    }
    
    private function updateWorldResources($world)
    {
        $serverDB = ServerDB::getInstance($world['configFileLocation']);
        
        // Get all villages with last update time
        $stmt = $serverDB->query("
            SELECT kid, owner, wood, clay, iron, crop, 
                   wood_prod, clay_prod, iron_prod, crop_prod,
                   storage_capacity, granary_capacity,
                   last_update
            FROM vdata
        ");
        
        $villages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($villages as $village) {
            $this->updateVillageResources($serverDB, $village);
        }
    }
    
    private function updateVillageResources($db, $village)
    {
        $now = time();
        $timePassed = $now - $village['last_update'];
        
        // Calculate production
        $woodProduced = ($timePassed / 3600) * $village['wood_prod'];
        $clayProduced = ($timePassed / 3600) * $village['clay_prod'];
        $ironProduced = ($timePassed / 3600) * $village['iron_prod'];
        $cropProduced = ($timePassed / 3600) * $village['crop_prod'];
        
        // Apply storage limits
        $newWood = min($village['wood'] + $woodProduced, $village['storage_capacity']);
        $newClay = min($village['clay'] + $clayProduced, $village['storage_capacity']);
        $newIron = min($village['iron'] + $ironProduced, $village['storage_capacity']);
        $newCrop = min($village['crop'] + $cropProduced, $village['granary_capacity']);
        
        // Update database
        $stmt = $db->prepare("
            UPDATE vdata 
            SET wood=:wood, clay=:clay, iron=:iron, crop=:crop, last_update=:now
            WHERE kid=:kid
        ");
        $stmt->execute([
            'wood' => $newWood,
            'clay' => $newClay,
            'iron' => $newIron,
            'crop' => $newCrop,
            'now' => $now,
            'kid' => $village['kid']
        ]);
    }
}

// Run worker
$worker = new ResourceProductionWorker();
$worker->run();
```

### 2. MovementProcessorWorker

**File:** `workers/MovementProcessorWorker.php`

```php
<?php
class MovementProcessorWorker
{
    public function run()
    {
        $worlds = Server::getAllWorlds();
        
        foreach ($worlds as $world) {
            $this->processWorldMovements($world);
        }
    }
    
    private function processWorldMovements($world)
    {
        $serverDB = ServerDB::getInstance($world['configFileLocation']);
        
        // Get movements that have arrived
        $stmt = $serverDB->query("
            SELECT * FROM movement 
            WHERE end_time <= " . time() . " 
            AND status='active'
        ");
        
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($movements as $movement) {
            $this->processArrival($serverDB, $movement);
        }
    }
    
    private function processArrival($db, $movement)
    {
        switch ($movement['type']) {
            case 'attack':
                $this->processAttack($db, $movement);
                break;
            case 'reinforcement':
                $this->processReinforcement($db, $movement);
                break;
            case 'resources':
                $this->processResourceDelivery($db, $movement);
                break;
        }
        
        // Update movement status
        $db->exec("UPDATE movement SET status='completed' WHERE id={$movement['id']}");
    }
    
    private function processAttack($db, $movement)
    {
        // 1. Get attacker and defender troops
        $attackerTroops = json_decode($movement['troops'], true);
        $defenderTroops = $this->getGarrison($db, $movement['to_kid']);
        
        // 2. Calculate battle
        $result = $this->simulateBattle($attackerTroops, $defenderTroops);
        
        // 3. Apply casualties
        $this->applyCasualties($db, $movement['from_kid'], $result['attacker_losses']);
        $this->applyCasualties($db, $movement['to_kid'], $result['defender_losses']);
        
        // 4. Capture resources (if attacker won)
        if ($result['winner'] === 'attacker') {
            $resources = $this->captureResources($db, $movement['to_kid'], $result['carry_capacity']);
            $this->addResources($db, $movement['from_kid'], $resources);
        }
        
        // 5. Generate battle report
        $this->createBattleReport($db, $movement, $result);
    }
    
    private function simulateBattle($attackerTroops, $defenderTroops)
    {
        // Implement Travian combat formula
        // This is simplified; real combat is complex
        $attackPower = $this->calculateAttackPower($attackerTroops);
        $defensePower = $this->calculateDefensePower($defenderTroops);
        
        $winner = $attackPower > $defensePower ? 'attacker' : 'defender';
        
        // Calculate casualties (simplified)
        $attackerLosses = ($defensePower / $attackPower) * count($attackerTroops) * 0.5;
        $defenderLosses = ($attackPower / $defensePower) * count($defenderTroops) * 0.5;
        
        return [
            'winner' => $winner,
            'attacker_losses' => $attackerLosses,
            'defender_losses' => $defenderLosses,
            'carry_capacity' => $this->calculateCarryCapacity($attackerTroops - $attackerLosses)
        ];
    }
}
```

### 3. BuildingQueueWorker

```php
<?php
class BuildingQueueWorker
{
    public function run()
    {
        $worlds = Server::getAllWorlds();
        
        foreach ($worlds as $world) {
            $this->processWorldBuildings($world);
        }
    }
    
    private function processWorldBuildings($world)
    {
        $serverDB = ServerDB::getInstance($world['configFileLocation']);
        
        // Get completed buildings
        $stmt = $serverDB->query("
            SELECT * FROM building_queue 
            WHERE completion_time <= " . time() . "
            AND status='building'
        ");
        
        $completedBuildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($completedBuildings as $building) {
            $this->completeBuilding($serverDB, $building);
        }
    }
    
    private function completeBuilding($db, $building)
    {
        // 1. Update fdata (building levels)
        $field = 'f' . $building['slot'];
        $db->exec("
            UPDATE fdata 
            SET {$field}={$building['new_level']} 
            WHERE kid={$building['kid']}
        ");
        
        // 2. Update village stats (population, production)
        $this->updateVillageStats($db, $building['kid']);
        
        // 3. Mark building complete
        $db->exec("UPDATE building_queue SET status='completed' WHERE id={$building['id']}");
        
        // 4. Send notification (system report)
        $this->createSystemReport($db, $building, 'Building completed');
    }
}
```

### 4. RankingsWorker (Statistics)

```php
<?php
class RankingsWorker
{
    public function run()
    {
        $worlds = Server::getAllWorlds();
        
        foreach ($worlds as $world) {
            $this->updateWorldRankings($world);
        }
    }
    
    private function updateWorldRankings($world)
    {
        $serverDB = ServerDB::getInstance($world['configFileLocation']);
        
        // Clear old cache
        $serverDB->exec("DELETE FROM leaderboard_cache WHERE world_id='{$world['id']}'");
        
        // Update population rankings
        $this->updatePopulationRankings($serverDB, $world['id']);
        
        // Update attack rankings
        $this->updateAttackRankings($serverDB, $world['id']);
        
        // Update defense rankings
        $this->updateDefenseRankings($serverDB, $world['id']);
        
        // Update alliance rankings
        $this->updateAllianceRankings($serverDB, $world['id']);
    }
    
    private function updatePopulationRankings($db, $worldId)
    {
        $stmt = $db->query("
            SELECT u.id, u.name, SUM(v.pop) as total_pop, u.alliance_id
            FROM users u
            JOIN vdata v ON v.owner = u.id
            GROUP BY u.id
            ORDER BY total_pop DESC
            LIMIT 1000
        ");
        
        $rank = 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec("
                INSERT INTO leaderboard_cache (world_id, category, rank, uid, value, cached_at)
                VALUES ('$worldId', 'population', $rank, {$row['id']}, {$row['total_pop']}, NOW())
            ");
            $rank++;
        }
    }
}
```

## Error Handling & Monitoring

### Worker Logging

```php
class WorkerLogger
{
    private $log;
    
    public function __construct($workerName)
    {
        $this->log = new Monolog\Logger($workerName);
        $this->log->pushHandler(new StreamHandler("/tmp/{$workerName}.log", Logger::INFO));
    }
    
    public function info($message, $context = [])
    {
        $this->log->info($message, $context);
    }
    
    public function error($message, $context = [])
    {
        $this->log->error($message, $context);
        
        // Send alert (Discord webhook, email, etc.)
        $this->sendAlert($message, $context);
    }
}

// Usage in worker
try {
    $worker->run();
    $logger->info('Worker completed successfully');
} catch (Exception $e) {
    $logger->error('Worker failed: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
    ]);
}
```

### Health Checks

```php
// Endpoint: /health/workers
public function checkWorkerHealth()
{
    $workers = [
        'resource_production' => '/tmp/resource_worker_last_run',
        'movement_processor' => '/tmp/movement_worker_last_run',
        'building_queue' => '/tmp/building_worker_last_run'
    ];
    
    $status = [];
    foreach ($workers as $name => $file) {
        $lastRun = file_exists($file) ? filemtime($file) : 0;
        $timeSinceRun = time() - $lastRun;
        
        $status[$name] = [
            'healthy' => $timeSinceRun < 300, // 5 minutes
            'last_run' => date('Y-m-d H:i:s', $lastRun),
            'seconds_since_run' => $timeSinceRun
        ];
    }
    
    return $status;
}
```

---

# Caching Strategies

## Cache Layers

### 1. Redis (Shared Cache)

**Use cases:**
- API response caching (rankings, statistics)
- Session management
- Rate limiting
- WebSocket pub/sub

**Installation:**
```bash
# Redis already installed as system dependency
# Start Redis server
redis-server --daemonize yes
```

**PHP Redis Client:**
```bash
composer require predis/predis
```

**Usage:**
```php
<?php
use Predis\Client;

$redis = new Client([
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379,
]);

// Cache API response
$cacheKey = "rankings:testworld:population";
$cached = $redis->get($cacheKey);

if (!$cached) {
    $data = $this->calculateRankings(); // Expensive operation
    $redis->setex($cacheKey, 300, json_encode($data)); // 5 min TTL
} else {
    $data = json_decode($cached, true);
}
```

### 2. APCu (Opcode Cache)

**Use cases:**
- Static game data (building definitions, unit stats)
- Configuration
- In-memory object caching

**Installation:**
```bash
# APCu enabled in php.ini
apc.enabled=1
apc.shm_size=128M
```

**Usage:**
```php
<?php
// Cache building definitions
$cacheKey = 'game:buildings:definitions';
$buildings = apcu_fetch($cacheKey, $success);

if (!$success) {
    $buildings = $this->loadBuildingDefinitions(); // From JSON/DB
    apcu_store($cacheKey, $buildings); // No TTL (never expires)
}
```

### 3. File-Based Cache (Fallback)

**Use cases:**
- When Redis unavailable
- Temporary storage

```php
<?php
class FileCache
{
    private $cacheDir = '/tmp/cache';
    
    public function get($key, $ttl = 300)
    {
        $file = $this->cacheDir . '/' . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    public function set($key, $value, $ttl = 300)
    {
        $file = $this->cacheDir . '/' . md5($key) . '.cache';
        
        $data = [
            'expires' => time() + $ttl,
            'value' => $value
        ];
        
        file_put_contents($file, serialize($data));
    }
}
```

## Cache Key Taxonomy

**Structure:** `namespace:world:resource:identifier`

**Examples:**
```
game:static:buildings:definitions
game:static:units:stats
game:testworld:village:12345:resources
game:testworld:rankings:population
game:testworld:alliance:1:members
user:session:abc123
api:ratelimit:192.168.1.1
```

## Cache Invalidation Strategies

### 1. TTL-Based (Time To Live)
- **Static Data:** Never expire (buildings, units)
- **Semi-Static:** 5-15 minutes (rankings, statistics)
- **Dynamic:** 30-60 seconds (resources, troops)

### 2. Event-Based Invalidation
```php
// When building completes
$this->cache->delete("game:{$worldId}:village:{$villageId}:buildings");

// When troop training completes
$this->cache->delete("game:{$worldId}:village:{$villageId}:troops");

// When alliance member joins
$this->cache->delete("game:{$worldId}:alliance:{$allianceId}:members");
```

### 3. Cache Tags (Advanced)
```php
// Tag resources with village ID
$this->cache->set("village:12345:resources", $data, ['tags' => ['village:12345']]);
$this->cache->set("village:12345:buildings", $data, ['tags' => ['village:12345']]);

// Invalidate all village data at once
$this->cache->invalidateTags(['village:12345']);
```

## Cache Warming

**Pre-populate cache on server startup:**

```php
function warmCache($worldId)
{
    // 1. Load static game data
    $this->cache->set('game:static:buildings', $this->loadBuildings());
    $this->cache->set('game:static:units', $this->loadUnits());
    
    // 2. Pre-calculate rankings
    $this->updateRankings($worldId);
    
    // 3. Cache top alliances
    $topAlliances = $this->getTopAlliances($worldId, 10);
    $this->cache->set("game:{$worldId}:alliances:top10", $topAlliances, 600);
}
```

## Performance Impact Estimates

**Without Cache:**
- Rankings query: 800ms (aggregate 10k+ users)
- Village data: 150ms (multiple JOINs)
- Alliance members: 100ms (JOIN + aggregate)

**With Cache:**
- Rankings: 15ms (Redis hit)
- Village data: 10ms (Redis hit)
- Alliance members: 8ms (Redis hit)

**Cache Hit Rate Target:** >80%

---

# WebSocket Integration

## Technology Selection

### Option 1: Ratchet (PHP WebSocket)

**Pros:**
- Native PHP integration
- Simple to integrate with existing codebase
- No additional language/runtime

**Cons:**
- Lower performance than Node.js
- Limited scaling (100-500 concurrent connections)

**Installation:**
```bash
composer require cboden/ratchet
```

### Option 2: Node.js Socket.IO (Recommended)

**Pros:**
- High performance (10k+ concurrent connections)
- Auto-fallback to polling
- Rich ecosystem

**Cons:**
- Requires Node.js runtime
- Separate service from PHP backend

**Installation:**
```bash
npm install socket.io redis
```

## WebSocket Server (Node.js)

**File:** `websocket/server.js`

```javascript
const io = require('socket.io')(8081, {
    cors: { origin: '*' }
});
const redis = require('redis');
const subscriber = redis.createClient();

// Subscribe to Redis pub/sub
subscriber.subscribe('game-events');

// Forward Redis events to WebSocket clients
subscriber.on('message', (channel, message) => {
    const event = JSON.parse(message);
    
    // Send to specific user
    if (event.uid) {
        io.to(`user:${event.uid}`).emit(event.type, event.data);
    }
    
    // Send to alliance
    if (event.allianceId) {
        io.to(`alliance:${event.allianceId}`).emit(event.type, event.data);
    }
});

// Handle client connections
io.on('connection', (socket) => {
    console.log('Client connected:', socket.id);
    
    // Authenticate
    socket.on('auth', (data) => {
        socket.uid = data.uid;
        socket.join(`user:${data.uid}`);
        
        if (data.allianceId) {
            socket.join(`alliance:${data.allianceId}`);
        }
    });
    
    socket.on('disconnect', () => {
        console.log('Client disconnected:', socket.id);
    });
});
```

## Event Publishing (PHP Backend)

**File:** `helpers/EventPublisher.php`

```php
<?php
class EventPublisher
{
    private $redis;
    
    public function __construct()
    {
        $this->redis = new Predis\Client();
    }
    
    public function publishTroopArrival($uid, $movement)
    {
        $event = [
            'type' => 'troop_arrival',
            'uid' => $uid,
            'data' => [
                'movementId' => $movement['id'],
                'troops' => $movement['troops'],
                'result' => $movement['result'],
                'timestamp' => time()
            ]
        ];
        
        $this->redis->publish('game-events', json_encode($event));
    }
    
    public function publishBuildingComplete($uid, $villageId, $building)
    {
        $event = [
            'type' => 'building_complete',
            'uid' => $uid,
            'data' => [
                'villageId' => $villageId,
                'building' => $building,
                'timestamp' => time()
            ]
        ];
        
        $this->redis->publish('game-events', json_encode($event));
    }
    
    public function publishAllianceMessage($allianceId, $message)
    {
        $event = [
            'type' => 'alliance_chat',
            'allianceId' => $allianceId,
            'data' => $message
        ];
        
        $this->redis->publish('game-events', json_encode($event));
    }
}

// Usage in worker
$publisher = new EventPublisher();
$publisher->publishTroopArrival($uid, $movement);
```

## Frontend Integration (Angular)

**File:** `angularIndex/src/app/services/websocket.service.ts`

```typescript
import { Injectable } from '@angular/core';
import { io, Socket } from 'socket.io-client';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class WebSocketService {
  private socket: Socket;
  
  constructor() {
    this.socket = io('http://localhost:8081');
  }
  
  authenticate(uid: number, allianceId?: number) {
    this.socket.emit('auth', { uid, allianceId });
  }
  
  onTroopArrival(): Observable<any> {
    return new Observable(observer => {
      this.socket.on('troop_arrival', (data) => {
        observer.next(data);
      });
    });
  }
  
  onBuildingComplete(): Observable<any> {
    return new Observable(observer => {
      this.socket.on('building_complete', (data) => {
        observer.next(data);
      });
    });
  }
  
  onAllianceChat(): Observable<any> {
    return new Observable(observer => {
      this.socket.on('alliance_chat', (data) => {
        observer.next(data);
      });
    });
  }
}
```

## Event Types

### 1. Troop Arrival
```json
{
  "type": "troop_arrival",
  "uid": 2,
  "data": {
    "movementId": 12345,
    "villageId": 67890,
    "troops": {"u1": 100, "u2": 50},
    "result": "victory",
    "resourcesCaptured": {"wood": 1000, "clay": 800},
    "timestamp": 1730000000
  }
}
```

### 2. Building Complete
```json
{
  "type": "building_complete",
  "uid": 2,
  "data": {
    "villageId": 67890,
    "building": "barracks",
    "level": 5,
    "timestamp": 1730000000
  }
}
```

### 3. Attack Alert
```json
{
  "type": "attack_alert",
  "uid": 2,
  "data": {
    "attackerId": 3,
    "attackerName": "Enemy",
    "targetVillageId": 67890,
    "arrivalTime": 1730001000,
    "timestamp": 1730000000
  }
}
```

### 4. Alliance Chat
```json
{
  "type": "alliance_chat",
  "allianceId": 1,
  "data": {
    "senderId": 2,
    "senderName": "Player1",
    "message": "Need reinforcements!",
    "isAnnouncement": false,
    "timestamp": 1730000000
  }
}
```

## Authentication & Security

**Short-lived tokens:**
```php
// Generate WebSocket auth token (expires in 5 minutes)
function generateWSToken($uid)
{
    $payload = [
        'uid' => $uid,
        'exp' => time() + 300
    ];
    
    return JWT::encode($payload, JWT_SECRET);
}

// Client requests token via API
// /v1/auth/getWSToken
public function getWSToken()
{
    $token = generateWSToken($this->payload['uid']);
    $this->response = ['token' => $token];
}
```

**WebSocket server validates:**
```javascript
socket.on('auth', (data) => {
    try {
        const decoded = jwt.verify(data.token, JWT_SECRET);
        socket.uid = decoded.uid;
        socket.join(`user:${decoded.uid}`);
    } catch (err) {
        socket.disconnect();
    }
});
```

## Priority Assessment

**WebSocket Priority:** Phase 4C+ (OPTIONAL)

**Rationale:**
- Core gameplay works without WebSocket (polling fallback)
- Adds complexity to deployment
- Real-time updates nice-to-have, not critical
- Can implement later without affecting APIs

**Polling Fallback:**
```typescript
// Frontend polls for updates every 30 seconds
setInterval(() => {
  this.api.getUnreadReports().subscribe(reports => {
    this.updateNotifications(reports);
  });
}, 30000);
```

---

# Master Implementation Roadmap

## 5-Week Plan to Production

### **Week 1: Phase 4B APIs + Utilities**

**Days 1-2: Shared Utilities**
- Create API helper classes (world resolver, validators, formatters)
- Set up error logging (Monolog)
- Create test harness (curl + jq script)

**Days 3-4: Hero & Quest APIs**
- Build HeroCtrl (9 endpoints)
- Build QuestCtrl (5 endpoints)
- Create schema additions (hero_profile, hero_adventures, quest_progress)
- Test with curl scripts

**Days 5-7: Reports, Messages, Statistics APIs**
- Build ReportsCtrl (6 endpoints)
- Build MessagesCtrl (8 endpoints)
- Build StatisticsCtrl (6 endpoints)
- Create schema additions (reports_*, messages_*, leaderboard_cache)
- Integration testing

**Deliverable:** 34 Phase 4B endpoints complete (60/118 total)

---

### **Week 2: Testing + Integration Tests + Background Workers**

**Days 1-2: Manual Testing**
- Test all 60 endpoints with curl
- Verify data accuracy with SQL queries
- Performance benchmarking (<200ms target)
- Bug fixes

**Days 3-4: Automated Test Suite**
- Build test script (tests/run-api-tests.sh)
- Create test fixtures
- Set up CI/CD (optional)
- PHPUnit tests (if time)

**Days 5-7: Background Workers**
- Build ResourceProductionWorker
- Build MovementProcessorWorker
- Build BuildingQueueWorker
- Build TrainingQueueWorker
- Set up cron jobs / Supervisor
- Test worker execution

**Deliverable:** Complete test suite + 4 critical workers operational

---

### **Week 3: Caching + Production Deployment**

**Days 1-2: Caching Implementation**
- Set up Redis
- Implement cache layer in controllers
- Cache rankings/statistics
- Cache static game data (APCu)
- Performance testing (measure cache hit rate)

**Days 3-4: Production Optimization**
- PHP opcache configuration
- Database query optimization
- Static asset optimization (Angular build)
- Error logging setup (Papertrail)

**Days 5-7: Deployment**
- Configure Replit deployment
- Test production environment
- Security hardening
- Monitoring setup
- Go live! 🚀

**Deliverable:** Production-ready game server deployed

---

### **Week 4: AI NPC Foundation (Rule-Based)**

**Days 1-2: NPC Architecture**
- Design NPC state structure
- Create NPCPlayer class
- Implement 5 behavior archetypes
- Database tables for NPC state

**Days 3-4: Rule-Based Modules**
- Economy Module (resource balancing, building)
- Military Module (training, attacking)
- Expansion Module (settlers, villages)

**Days 5-7: NPC Tick Processing**
- Build NPCTickWorker
- Implement decision-making logic
- Test with 10-50 NPCs
- Performance optimization

**Deliverable:** 50+ AI NPCs playing the game autonomously (rule-based)

---

### **Week 5: LLM Integration + WebSocket (Optional)**

**Days 1-3: Local LLM Setup**
- Install llama.cpp on RTX hardware
- Download Llama 3.1 8B model
- Build LLM API server
- Create PHP LLM client
- Integrate 5% LLM decision-making

**Days 4-5: WebSocket (Optional)**
- Set up Node.js WebSocket server
- Redis pub/sub integration
- Event publishing from workers
- Frontend WebSocket client
- Real-time notifications

**Days 6-7: Polish & Testing**
- Load testing (500 NPCs)
- LLM response time optimization
- Bug fixes
- Documentation
- Celebration! 🎉

**Deliverable:** Complete AI-driven game with 50-500 NPCs + real-time updates

---

## Success Metrics

### Phase 4B (Week 1)
- ✅ 34 new API endpoints functional
- ✅ All endpoints <200ms response time
- ✅ Database schema extended (9+ new tables)
- ✅ Integration tests passing

### Testing (Week 2)
- ✅ 100% API test coverage
- ✅ Automated test suite running
- ✅ 4 background workers operational
- ✅ Zero critical bugs

### Production (Week 3)
- ✅ Deployed to Replit
- ✅ >80% cache hit rate
- ✅ Monitoring and logging active
- ✅ Public URL accessible

### AI NPCs (Week 4)
- ✅ 50+ NPCs actively playing
- ✅ Rule-based decisions working
- ✅ NPCs building, training, attacking
- ✅ Game feels alive

### Full AI (Week 5)
- ✅ LLM integration functional
- ✅ 500 NPCs sustainable
- ✅ WebSocket real-time updates (optional)
- ✅ Performance <200ms maintained

---

## Next Immediate Steps

1. **Update Task List** - Track Week 1 progress
2. **Build HeroCtrl** - Start Phase 4B implementation
3. **Create Schema Migrations** - Add hero/quest tables
4. **Test APIs** - Verify with curl commands
5. **Document Progress** - Keep replit.md updated

---

**Last Updated:** October 30, 2025  
**Status:** Ready for Week 1 implementation  
**Next Milestone:** Phase 4B complete (34 endpoints)
