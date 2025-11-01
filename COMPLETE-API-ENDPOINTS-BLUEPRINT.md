# Complete API Endpoints Blueprint - Travian T4.6 Game Server
## 100% Game Completion Roadmap

**Date:** October 30, 2025  
**Status:** Phase 4A In Progress (VillageCtrl: 5/5 endpoints ✅)  
**Goal:** Build 100+ API endpoints for full Travian T4.6 functionality

---

## Table of Contents
1. [Phase 4A - Core Gameplay (CRITICAL)](#phase-4a---core-gameplay)
2. [Phase 4B - Essential Features (IMPORTANT)](#phase-4b---essential-features)
3. [Phase 4C - Advanced Features (IMPORTANT)](#phase-4c---advanced-features)
4. [Phase 4D - Support Systems (OPTIONAL)](#phase-4d---support-systems)
5. [Implementation Guidance](#implementation-guidance)
6. [Performance Targets](#performance-targets)

---

# Phase 4A - Core Gameplay (CRITICAL)
**Priority:** CRITICAL - Required for basic playable game  
**Timeline:** Immediate focus  
**Endpoints:** 26 total

## 1. Village API ✅ COMPLETED
**Controller:** `VillageCtrl.php`  
**Status:** ✅ 5/5 endpoints implemented  
**Priority:** CRITICAL  
**Database Tables:** users, vdata, fdata

### Endpoints

#### ✅ getVillageList
- **Description:** List all villages owned by user
- **Complexity:** LOW
- **Request:** `{worldId, uid, lang}`
- **Response:** `{villages: [{id, name, coordinates, population, isCapital}]}`
- **Performance:** <50ms (simple SELECT)

#### ✅ getVillageDetails
- **Description:** Full village data with buildings and owner info
- **Complexity:** MEDIUM
- **Request:** `{worldId, villageId, lang}`
- **Response:** `{village: {id, name, owner, buildings, resources, production}}`
- **Performance:** <100ms (2-3 JOINs)

#### ✅ getResources
- **Description:** Current resources with real-time production calculation
- **Complexity:** MEDIUM
- **Request:** `{worldId, villageId, lang}`
- **Response:** `{wood, clay, iron, crop, storage, production, lastUpdate}`
- **Performance:** <75ms (calculation overhead)

#### ✅ getBuildingQueue
- **Description:** Active building construction queue
- **Complexity:** LOW
- **Request:** `{worldId, villageId, lang}`
- **Response:** `{queue: [{building, level, timeRemaining}]}`
- **Performance:** <50ms
- **Note:** Graceful fallback if buildingQueue table missing

#### ✅ upgradeBuilding
- **Description:** Start building upgrade with validation
- **Complexity:** HIGH
- **Request:** `{worldId, villageId, buildingSlot, lang}`
- **Response:** `{success, cost, duration, queuePosition}`
- **Validation:** Check resources, building requirements, queue limits
- **Performance:** <150ms (complex validation)

---

## 2. Map API ⏳ PENDING
**Controller:** `MapCtrl.php`  
**Status:** ⏳ 0/5 endpoints  
**Priority:** CRITICAL  
**Database Tables:** vdata, odata, users, alliance

### Endpoints

#### getMapData
- **Description:** Tiles in coordinate range with pagination
- **Complexity:** HIGH
- **Request:** `{worldId, x1, y1, x2, y2, limit, offset, lang}`
- **Response:** `{tiles: [{x, y, type, owner, alliance, oasis}], total, hasMore}`
- **Performance:** <200ms (requires pagination, indexed queries)
- **Validation:** Max range 40×40 tiles
- **Caching:** Cache tile data for 60 seconds

#### getVillageInfo
- **Description:** Village info at specific coordinates
- **Complexity:** MEDIUM
- **Request:** `{worldId, x, y, lang}`
- **Response:** `{village: {id, name, owner, tribe, alliance, population}}`
- **Performance:** <75ms
- **Note:** Return null if no village at coordinates

#### getTileDetails
- **Description:** Detailed tile data (oasis, resources, type)
- **Complexity:** MEDIUM
- **Request:** `{worldId, x, y, lang}`
- **Response:** `{tile: {type, oasis, bonuses, animals, owner}}`
- **Performance:** <100ms
- **Tables:** odata for oasis information

#### searchVillages
- **Description:** Find villages by name/player/alliance
- **Complexity:** MEDIUM
- **Request:** `{worldId, query, type: 'village'|'player'|'alliance', limit, lang}`
- **Response:** `{results: [{id, name, owner, coordinates, distance}], total}`
- **Performance:** <150ms
- **Validation:** Minimum 3 characters for search
- **Pagination:** Max 50 results per page

#### getNearby
- **Description:** Villages near coordinates sorted by distance
- **Complexity:** MEDIUM
- **Request:** `{worldId, x, y, radius, lang}`
- **Response:** `{villages: [{id, name, distance, coordinates, owner}]}`
- **Performance:** <125ms
- **Validation:** Max radius 20 tiles

---

## 3. Troop API ⏳ PENDING
**Controller:** `TroopCtrl.php`  
**Status:** ⏳ 0/6 endpoints  
**Priority:** CRITICAL  
**Database Tables:** units, training, movement, vdata

### Endpoints

#### getTroops
- **Description:** Garrison troops for village
- **Complexity:** LOW
- **Request:** `{worldId, villageId, lang}`
- **Response:** `{troops: {u1, u2, ..., u11}, incoming, outgoing}`
- **Performance:** <50ms

#### trainUnits
- **Description:** Queue troop training with cost/time calculation
- **Complexity:** HIGH
- **Request:** `{worldId, villageId, unitType, quantity, lang}`
- **Response:** `{success, cost, duration, queuePosition}`
- **Validation:** 
  - Check resources (wood, clay, iron, crop)
  - Check building requirements (barracks/stable level)
  - Check population capacity
  - Calculate training time based on speed/building level
- **Performance:** <175ms
- **Game Rules:** Training time = baseTime / speed / buildingBonus

#### getTrainingQueue
- **Description:** Active training queues for all buildings
- **Complexity:** MEDIUM
- **Request:** `{worldId, villageId, lang}`
- **Response:** `{queues: [{building, units, timeRemaining, total}]}`
- **Performance:** <75ms

#### sendAttack
- **Description:** Launch attack with troop validation
- **Complexity:** HIGH
- **Request:** `{worldId, fromVillageId, toX, toY, troops: {u1, u2, ...}, attackType, lang}`
- **Response:** `{success, movementId, arrivalTime, distance}`
- **Validation:**
  - Check troop availability
  - Calculate distance and travel time
  - Validate attack type (normal, raid, siege)
  - Check hero presence for certain attack types
- **Performance:** <200ms
- **Game Rules:** Travel time = distance * slowestUnitSpeed / worldSpeed

#### sendReinforcement
- **Description:** Send support troops to ally/own village
- **Complexity:** MEDIUM
- **Request:** `{worldId, fromVillageId, toX, toY, troops, lang}`
- **Response:** `{success, movementId, arrivalTime}`
- **Validation:** Check alliance status, troop availability
- **Performance:** <150ms

#### getMovements
- **Description:** Active troop movements (incoming/outgoing)
- **Complexity:** MEDIUM
- **Request:** `{worldId, villageId, type: 'all'|'incoming'|'outgoing', lang}`
- **Response:** `{movements: [{id, from, to, troops, type, arrivalTime}]}`
- **Performance:** <100ms
- **Real-time:** Calculate remaining time on-the-fly

---

## 4. Alliance API ⏳ PENDING
**Controller:** `AllianceCtrl.php`  
**Status:** ⏳ 0/5 endpoints  
**Priority:** CRITICAL  
**Database Tables:** alliance, users, vdata

### Endpoints

#### create
- **Description:** Create new alliance
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, name, tag, description, lang}`
- **Response:** `{success, allianceId}`
- **Validation:**
  - Check embassy level ≥ 3
  - Unique alliance name/tag
  - User not already in alliance
- **Performance:** <125ms

#### invite
- **Description:** Invite player to alliance
- **Complexity:** LOW
- **Request:** `{worldId, allianceId, targetUid, lang}`
- **Response:** `{success, inviteId}`
- **Validation:** Check inviter has permission, target not in alliance
- **Performance:** <75ms

#### getMembers
- **Description:** List alliance members with stats
- **Complexity:** MEDIUM
- **Request:** `{worldId, allianceId, lang}`
- **Response:** `{members: [{uid, name, rank, villages, population, role}]}`
- **Performance:** <100ms (JOIN users + vdata for stats)

#### setDiplomacy
- **Description:** Set alliance relations (war/peace/NAP/confederation)
- **Complexity:** MEDIUM
- **Request:** `{worldId, allianceId, targetAllianceId, status, lang}`
- **Response:** `{success, diplomacy}`
- **Validation:** Check leader/diplomat permissions
- **Performance:** <100ms
- **Status Types:** war, peace, nap, confederation

#### getDiplomacy
- **Description:** Get diplomatic relations
- **Complexity:** LOW
- **Request:** `{worldId, allianceId, lang}`
- **Response:** `{diplomacy: [{allianceId, name, status, since}]}`
- **Performance:** <75ms

---

## 5. Market API ⏳ PENDING
**Controller:** `MarketCtrl.php`  
**Status:** ⏳ 0/5 endpoints  
**Priority:** CRITICAL  
**Database Tables:** market, vdata

### Endpoints

#### getOffers
- **Description:** List market offers with filters
- **Complexity:** MEDIUM
- **Request:** `{worldId, villageId, filters: {resource, ratio}, limit, offset, lang}`
- **Response:** `{offers: [{id, seller, offering, requesting, ratio, distance}], total}`
- **Performance:** <125ms
- **Pagination:** Required (limit 50 per page)

#### createOffer
- **Description:** Post resource trade offer
- **Complexity:** MEDIUM
- **Request:** `{worldId, villageId, offering: {resource, amount}, requesting: {resource, amount}, lang}`
- **Response:** `{success, offerId, fee}`
- **Validation:**
  - Check marketplace level
  - Check resource availability
  - Calculate marketplace fee (3%)
- **Performance:** <150ms

#### sendResources
- **Description:** Direct resource transfer to ally/own village
- **Complexity:** MEDIUM
- **Request:** `{worldId, fromVillageId, toX, toY, resources: {wood, clay, iron, crop}, lang}`
- **Response:** `{success, merchantsUsed, arrivalTime}`
- **Validation:**
  - Check marketplace level for merchants
  - Check alliance status
  - Calculate travel time
- **Performance:** <150ms
- **Game Rules:** Merchants = ceil(totalResources / 500)

#### acceptOffer
- **Description:** Accept market trade
- **Complexity:** HIGH
- **Request:** `{worldId, villageId, offerId, lang}`
- **Response:** `{success, transactionId, arrivalTime}`
- **Validation:**
  - Check offer still available
  - Check resources for requested amount
  - Check merchant availability
  - Calculate travel time
- **Performance:** <175ms

#### getTradeHistory
- **Description:** Past trades for village
- **Complexity:** LOW
- **Request:** `{worldId, villageId, limit, offset, lang}`
- **Response:** `{trades: [{id, type, partner, resources, timestamp}], total}`
- **Performance:** <100ms

---

# Phase 4B - Essential Features (IMPORTANT)
**Priority:** IMPORTANT - Required for full gameplay  
**Timeline:** After Phase 4A completion  
**Endpoints:** 34 total

## 6. Hero API ⏳ PENDING
**Controller:** `HeroCtrl.php`  
**Status:** ⏳ 0/9 endpoints  
**Priority:** IMPORTANT  
**Database Tables:** hero, auctions, vdata

### Endpoints

#### getHero
- **Description:** Hero stats, equipment, attributes
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, lang}`
- **Response:** `{hero: {health, level, xp, strength, attackBonus, defenseBonus, resources, equipment}}`
- **Performance:** <75ms

#### levelUp
- **Description:** Distribute attribute points on level up
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, attributes: {strength, attackBonus, defenseBonus, resources}, lang}`
- **Response:** `{success, newStats}`
- **Validation:** Check available points, valid distribution
- **Performance:** <100ms

#### equipItem
- **Description:** Equip hero item from inventory
- **Complexity:** LOW
- **Request:** `{worldId, uid, itemId, slot, lang}`
- **Response:** `{success, newStats}`
- **Performance:** <75ms

#### startAdventure
- **Description:** Send hero on adventure
- **Complexity:** HIGH
- **Request:** `{worldId, uid, adventureId, lang}`
- **Response:** `{success, duration, rewards}`
- **Validation:** Check hero not on adventure, health > 0
- **Performance:** <150ms
- **Game Rules:** Adventure difficulty based on distance, level

#### getAdventures
- **Description:** Available adventures on map
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, lang}`
- **Response:** `{adventures: [{id, x, y, difficulty, duration, distance}]}`
- **Performance:** <100ms

#### sellItem
- **Description:** Sell hero item for silver
- **Complexity:** LOW
- **Request:** `{worldId, uid, itemId, lang}`
- **Response:** `{success, silverGained}`
- **Performance:** <75ms

#### auctionItem
- **Description:** Put item up for auction
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, itemId, startingBid, duration, lang}`
- **Response:** `{success, auctionId}`
- **Performance:** <100ms

#### bidOnAuction
- **Description:** Bid on hero item auction
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, auctionId, bidAmount, lang}`
- **Response:** `{success, currentBid}`
- **Validation:** Check silver availability, bid > currentBid
- **Performance:** <125ms

#### getAuctions
- **Description:** Active hero item auctions
- **Complexity:** MEDIUM
- **Request:** `{worldId, filters: {itemType, maxBid}, limit, offset, lang}`
- **Response:** `{auctions: [{id, item, currentBid, timeRemaining, seller}], total}`
- **Performance:** <100ms
- **Pagination:** Required

---

## 7. Quest API ⏳ PENDING
**Controller:** `QuestCtrl.php`  
**Status:** ⏳ 0/5 endpoints  
**Priority:** IMPORTANT  
**Database Tables:** quests, users

### Endpoints

#### getActiveQuests
- **Description:** Active quests for user
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, lang}`
- **Response:** `{quests: [{id, title, description, progress, reward, type}]}`
- **Performance:** <100ms
- **Quest Types:** tutorial, economy, battle, world

#### completeQuest
- **Description:** Mark quest complete and claim reward
- **Complexity:** HIGH
- **Request:** `{worldId, uid, questId, lang}`
- **Response:** `{success, rewards: {resources, gold, xp}}`
- **Validation:** Check quest completion criteria met
- **Performance:** <150ms

#### getQuestRewards
- **Description:** Available quest rewards
- **Complexity:** LOW
- **Request:** `{worldId, uid, questId, lang}`
- **Response:** `{rewards: {gold, resources, troops, items}}`
- **Performance:** <50ms

#### skipQuest
- **Description:** Skip tutorial quest with gold
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, questId, lang}`
- **Response:** `{success, goldCost}`
- **Validation:** Check gold availability
- **Performance:** <100ms

#### getQuestProgress
- **Description:** Detailed quest progress tracking
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, questId, lang}`
- **Response:** `{progress: {current, required, percentage, steps}}`
- **Performance:** <75ms

---

## 8. Reports API ⏳ PENDING
**Controller:** `ReportsCtrl.php`  
**Status:** ⏳ 0/6 endpoints  
**Priority:** IMPORTANT  
**Database Tables:** reports, users, vdata

### Endpoints

#### getReports
- **Description:** List reports with filters
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, type: 'all'|'attack'|'defense'|'trade'|'system', unreadOnly, limit, offset, lang}`
- **Response:** `{reports: [{id, type, title, timestamp, isRead, preview}], total, unreadCount}`
- **Performance:** <125ms
- **Pagination:** Required (limit 50)

#### getReportDetails
- **Description:** Full report with battle details
- **Complexity:** HIGH
- **Request:** `{worldId, uid, reportId, lang}`
- **Response:** `{report: {attacker, defender, troops, casualties, resources, result}}`
- **Performance:** <150ms
- **Auto-mark:** Mark as read on view

#### markRead
- **Description:** Mark reports as read
- **Complexity:** LOW
- **Request:** `{worldId, uid, reportIds: [], lang}`
- **Response:** `{success, markedCount}`
- **Performance:** <75ms

#### deleteReport
- **Description:** Delete reports
- **Complexity:** LOW
- **Request:** `{worldId, uid, reportIds: [], lang}`
- **Response:** `{success, deletedCount}`
- **Performance:** <75ms

#### archiveReport
- **Description:** Archive important reports
- **Complexity:** LOW
- **Request:** `{worldId, uid, reportId, lang}`
- **Response:** `{success}`
- **Performance:** <50ms

#### getUnreadCount
- **Description:** Get unread report count
- **Complexity:** LOW
- **Request:** `{worldId, uid, lang}`
- **Response:** `{unreadCount, byType: {attack, defense, trade, system}}`
- **Performance:** <50ms
- **Caching:** Cache for 30 seconds

---

## 9. Messages API ⏳ PENDING
**Controller:** `MessagesCtrl.php`  
**Status:** ⏳ 0/8 endpoints  
**Priority:** IMPORTANT  
**Database Tables:** messages, users, alliance

### Endpoints

#### getInbox
- **Description:** Inbox messages with pagination
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, folder: 'inbox'|'sent'|'archive', limit, offset, lang}`
- **Response:** `{messages: [{id, from, subject, timestamp, isRead, hasAttachment}], total}`
- **Performance:** <100ms

#### getMessage
- **Description:** Read full message
- **Complexity:** LOW
- **Request:** `{worldId, uid, messageId, lang}`
- **Response:** `{message: {from, to, subject, body, timestamp, attachments}}`
- **Performance:** <75ms
- **Auto-mark:** Mark as read

#### sendMessage
- **Description:** Send message to player
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, toUid, subject, body, lang}`
- **Response:** `{success, messageId}`
- **Validation:** Check recipient exists, not blocked
- **Performance:** <100ms

#### deleteMessage
- **Description:** Delete messages
- **Complexity:** LOW
- **Request:** `{worldId, uid, messageIds: [], lang}`
- **Response:** `{success, deletedCount}`
- **Performance:** <75ms

#### archiveMessage
- **Description:** Archive messages
- **Complexity:** LOW
- **Request:** `{worldId, uid, messageIds: [], lang}`
- **Response:** `{success, archivedCount}`
- **Performance:** <75ms

#### getAllianceMessages
- **Description:** Alliance chat/announcements
- **Complexity:** MEDIUM
- **Request:** `{worldId, allianceId, limit, offset, lang}`
- **Response:** `{messages: [{id, sender, message, timestamp}], total}`
- **Performance:** <125ms

#### sendAllianceMessage
- **Description:** Post alliance message
- **Complexity:** MEDIUM
- **Request:** `{worldId, allianceId, uid, message, isAnnouncement, lang}`
- **Response:** `{success, messageId}`
- **Validation:** Check alliance membership, permissions
- **Performance:** <100ms

#### getUnreadCount
- **Description:** Unread message count
- **Complexity:** LOW
- **Request:** `{worldId, uid, lang}`
- **Response:** `{unreadCount, inboxCount, allianceCount}`
- **Performance:** <50ms

---

## 10. Statistics API ⏳ PENDING
**Controller:** `StatisticsCtrl.php`  
**Status:** ⏳ 0/6 endpoints  
**Priority:** IMPORTANT  
**Database Tables:** users, vdata, alliance

### Endpoints

#### getPlayerRankings
- **Description:** Player rankings by category
- **Complexity:** HIGH
- **Request:** `{worldId, category: 'population'|'attack'|'defense', limit, offset, lang}`
- **Response:** `{rankings: [{rank, uid, name, value, villages, alliance}], total}`
- **Performance:** <200ms (complex aggregation)
- **Caching:** Cache for 5 minutes

#### getAllianceRankings
- **Description:** Alliance rankings
- **Complexity:** HIGH
- **Request:** `{worldId, category: 'population'|'attack'|'defense'|'members', limit, offset, lang}`
- **Response:** `{rankings: [{rank, allianceId, name, value, members}], total}`
- **Performance:** <200ms
- **Caching:** Cache for 5 minutes

#### getPlayerStats
- **Description:** Detailed player statistics
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, lang}`
- **Response:** `{stats: {rank, population, villages, attackPoints, defensePoints, alliance}}`
- **Performance:** <100ms

#### getAllianceStats
- **Description:** Alliance statistics
- **Complexity:** MEDIUM
- **Request:** `{worldId, allianceId, lang}`
- **Response:** `{stats: {rank, population, members, avgPopulation, totalAttack, totalDefense}}`
- **Performance:** <125ms

#### getTop10
- **Description:** Top 10 lists for multiple categories
- **Complexity:** MEDIUM
- **Request:** `{worldId, lang}`
- **Response:** `{top10: {attackers, defenders, richest, alliances}}`
- **Performance:** <150ms
- **Caching:** Cache for 10 minutes

#### getWorldStats
- **Description:** Server-wide statistics
- **Complexity:** MEDIUM
- **Request:** `{worldId, lang}`
- **Response:** `{stats: {totalPlayers, totalVillages, totalAlliances, activePlayers}}`
- **Performance:** <100ms
- **Caching:** Cache for 15 minutes

---

# Phase 4C - Advanced Features (IMPORTANT)
**Priority:** IMPORTANT - Full feature completion  
**Timeline:** After Phase 4B  
**Endpoints:** 24 total

## 11. Auction API (Silver Economy) ⏳ PENDING
**Controller:** `AuctionCtrl.php` (merged with HeroCtrl)  
**Status:** Covered in Hero API  
**Priority:** IMPORTANT

## 12. Artifacts API ⏳ PENDING
**Controller:** `ArtifactsCtrl.php`  
**Status:** ⏳ 0/5 endpoints  
**Priority:** IMPORTANT  
**Database Tables:** artefacts, vdata, users

### Endpoints

#### getArtifacts
- **Description:** List all artifacts with status
- **Complexity:** MEDIUM
- **Request:** `{worldId, lang}`
- **Response:** `{artifacts: [{id, type, size, owner, village, effect, captureProgress}]}`
- **Performance:** <100ms

#### getArtifactDetails
- **Description:** Detailed artifact info
- **Complexity:** MEDIUM
- **Request:** `{worldId, artifactId, lang}`
- **Response:** `{artifact: {type, size, owner, village, effect, history, captureRequirements}}`
- **Performance:** <100ms

#### captureArtifact
- **Description:** Attempt artifact capture
- **Complexity:** HIGH
- **Request:** `{worldId, villageId, artifactId, lang}`
- **Response:** `{success, captureProgress, timeRemaining}`
- **Validation:** Check attack won, treasury built, capture conditions
- **Performance:** <175ms

#### getArtifactHistory
- **Description:** Artifact ownership history
- **Complexity:** LOW
- **Request:** `{worldId, artifactId, lang}`
- **Response:** `{history: [{timestamp, previousOwner, newOwner, captureType}]}`
- **Performance:** <75ms

#### getPlayerArtifacts
- **Description:** Artifacts owned by player
- **Complexity:** LOW
- **Request:** `{worldId, uid, lang}`
- **Response:** `{artifacts: [{id, type, village, effect, since}]}`
- **Performance:** <75ms

---

## 13. Medals API ⏳ PENDING
**Controller:** `MedalsCtrl.php`  
**Status:** ⏳ 0/4 endpoints  
**Priority:** IMPORTANT  
**Database Tables:** medals, users

### Endpoints

#### getMedals
- **Description:** Player medals/achievements
- **Complexity:** MEDIUM
- **Request:** `{worldId, uid, lang}`
- **Response:** `{medals: [{id, category, rank, week, points, special}]}`
- **Performance:** <100ms

#### getMedalCategories
- **Description:** Available medal categories
- **Complexity:** LOW
- **Request:** `{worldId, lang}`
- **Response:** `{categories: [{id, name, description, ranks}]}`
- **Performance:** <50ms
- **Caching:** Static data, cache indefinitely

#### getWeeklyRankings
- **Description:** Weekly top rankings for medals
- **Complexity:** HIGH
- **Request:** `{worldId, week, category, lang}`
- **Response:** `{rankings: [{rank, uid, name, points, medal}]}`
- **Performance:** <200ms

#### getSpecialMedals
- **Description:** Special achievement medals
- **Complexity:** LOW
- **Request:** `{worldId, uid, lang}`
- **Response:** `{specialMedals: [{id, name, rarity, earnedDate}]}`
- **Performance:** <75ms

---

## 14. Oasis API ⏳ PENDING
**Controller:** `OasisCtrl.php`  
**Status:** ⏳ 0/5 endpoints  
**Priority:** IMPORTANT  
**Database Tables:** odata, vdata

### Endpoints

#### getOasisInfo
- **Description:** Oasis details and bonuses
- **Complexity:** MEDIUM
- **Request:** `{worldId, x, y, lang}`
- **Response:** `{oasis: {type, bonuses, animals, owner, captureProgress}}`
- **Performance:** <100ms

#### captureOasis
- **Description:** Capture oasis for village
- **Complexity:** HIGH
- **Request:** `{worldId, villageId, x, y, lang}`
- **Response:** `{success, bonuses, animalsDefeated}`
- **Validation:** Check hero mansion level, attack won, max oases (3)
- **Performance:** <175ms

#### abandonOasis
- **Description:** Release oasis control
- **Complexity:** LOW
- **Request:** `{worldId, villageId, oasisId, lang}`
- **Response:** `{success}`
- **Performance:** <75ms

#### getVillageOases
- **Description:** Oases controlled by village
- **Complexity:** LOW
- **Request:** `{worldId, villageId, lang}`
- **Response:** `{oases: [{id, coordinates, type, bonuses}]}`
- **Performance:** <75ms

#### getOasisAnimals
- **Description:** Animal troops in oasis
- **Complexity:** LOW
- **Request:** `{worldId, x, y, lang}`
- **Response:** `{animals: {rats, spiders, bats, wildBoars, bears, wolves, etc}}`
- **Performance:** <50ms

---

## 15. Wonder of the World API ⏳ PENDING
**Controller:** `WonderCtrl.php`  
**Status:** ⏳ 0/5 endpoints  
**Priority:** IMPORTANT  
**Database Tables:** wdata, vdata, alliance

### Endpoints

#### getWWVillages
- **Description:** All WW villages on server
- **Complexity:** MEDIUM
- **Request:** `{worldId, lang}`
- **Response:** `{wwVillages: [{id, coordinates, owner, alliance, level, population}]}`
- **Performance:** <100ms

#### getWWDetails
- **Description:** Detailed WW info
- **Complexity:** MEDIUM
- **Request:** `{worldId, villageId, lang}`
- **Response:** `{ww: {level, resources, buildPlan, attackers, defenders, constructionProgress}}`
- **Performance:** <125ms

#### getWWRankings
- **Description:** WW level rankings
- **Complexity:** MEDIUM
- **Request:** `{worldId, lang}`
- **Response:** `{rankings: [{rank, village, alliance, level, population}]}`
- **Performance:** <100ms

#### getWWPlans
- **Description:** Construction plans collected
- **Complexity:** LOW
- **Request:** `{worldId, allianceId, lang}`
- **Response:** `{plans: {collected, required, missing}}`
- **Performance:** <75ms

#### getVictoryConditions
- **Description:** Server victory status
- **Complexity:** MEDIUM
- **Request:** `{worldId, lang}`
- **Response:** `{status: {endDate, topWW, victoryCondition, daysRemaining}}`
- **Performance:** <100ms

---

# Phase 4D - Support Systems (OPTIONAL)
**Priority:** OPTIONAL - Enhanced experience  
**Timeline:** After Phase 4C  
**Endpoints:** 20+ total

## 16. Account API ⏳ PENDING
**Controller:** `AccountCtrl.php`  
**Status:** ⏳ 0/8 endpoints  
**Priority:** OPTIONAL  
**Database Tables:** users, sitter permissions

### Endpoints

#### getAccountSettings
- **Description:** User account settings
- **Request:** `{worldId, uid, lang}`
- **Response:** `{settings: {email, language, timezone, notifications}}`

#### updateSettings
- **Description:** Update account settings
- **Request:** `{worldId, uid, settings: {}, lang}`
- **Response:** `{success}`

#### getSitters
- **Description:** Account sitters
- **Request:** `{worldId, uid, lang}`
- **Response:** `{sitters: [{uid, name, permissions, since}]}`

#### addSitter
- **Description:** Add account sitter
- **Request:** `{worldId, uid, sitterUid, permissions, lang}`
- **Response:** `{success}`

#### removeSitter
- **Description:** Remove sitter
- **Request:** `{worldId, uid, sitterUid, lang}`
- **Response:** `{success}`

#### setVacationMode
- **Description:** Enable vacation mode
- **Request:** `{worldId, uid, duration, lang}`
- **Response:** `{success, enabledUntil}`

#### deleteAccount
- **Description:** Delete account (irreversible)
- **Request:** `{worldId, uid, confirmation, lang}`
- **Response:** `{success}`

#### getAccountStatistics
- **Description:** Account-wide stats
- **Request:** `{worldId, uid, lang}`
- **Response:** `{stats: {joinDate, totalVillages, totalPopulation, achievements}}`

---

## 17. Payment API ⏳ PENDING
**Controller:** `PaymentCtrl.php`  
**Status:** ⏳ 0/6 endpoints  
**Priority:** OPTIONAL  
**Database Tables:** paymentConfig, users

### Endpoints

#### getGoldBalance
- **Description:** Current gold balance
- **Request:** `{worldId, uid, lang}`
- **Response:** `{gold: {free, purchased, total}}`

#### getPlusAccountStatus
- **Description:** Plus account info
- **Request:** `{worldId, uid, lang}`
- **Response:** `{plus: {active, expiresAt, features}}`

#### buyGold
- **Description:** Purchase gold (mock/test)
- **Request:** `{worldId, uid, packageId, lang}`
- **Response:** `{success, goldAdded, transactionId}`
- **Note:** Mock implementation for testing

#### activatePlusAccount
- **Description:** Activate plus account
- **Request:** `{worldId, uid, duration, lang}`
- **Response:** `{success, expiresAt, goldCost}`

#### buyBonuses
- **Description:** Purchase in-game bonuses
- **Request:** `{worldId, uid, bonusType, duration, lang}`
- **Response:** `{success, goldCost, expiresAt}`

#### getTransactionHistory
- **Description:** Purchase history
- **Request:** `{worldId, uid, lang}`
- **Response:** `{transactions: [{timestamp, type, amount, goldSpent}]}`

---

## 18. Admin API ⏳ PENDING
**Controller:** `AdminCtrl.php`  
**Status:** ⏳ 0/6 endpoints  
**Priority:** OPTIONAL  
**Database Tables:** users, banIP, various

### Endpoints

#### banPlayer
- **Description:** Ban player account
- **Request:** `{worldId, targetUid, reason, duration, adminUid, lang}`
- **Response:** `{success, banId}`

#### unbanPlayer
- **Description:** Unban player
- **Request:** `{worldId, banId, adminUid, lang}`
- **Response:** `{success}`

#### getBannedPlayers
- **Description:** List banned accounts
- **Request:** `{worldId, lang}`
- **Response:** `{banned: [{uid, name, reason, bannedAt, expiresAt}]}`

#### moderateMessage
- **Description:** Delete/edit inappropriate messages
- **Request:** `{worldId, messageId, action, adminUid, lang}`
- **Response:** `{success}`

#### getServerStatus
- **Description:** Server health metrics
- **Request:** `{worldId, lang}`
- **Response:** `{status: {players, load, uptime, errors}}`

#### broadcastMessage
- **Description:** Send server-wide announcement
- **Request:** `{worldId, message, adminUid, lang}`
- **Response:** `{success, recipientCount}`

---

# Additional Systems Not Yet Categorized

## 19. Farmlist API (Automation) ⏳ FUTURE
**Priority:** OPTIONAL  
**Endpoints:** 5-7 (create, edit, sendAll, getTargets, etc.)

## 20. Tournament API ⏳ FUTURE
**Priority:** OPTIONAL  
**Endpoints:** 4-6 (getTournamentInfo, getSquares, upgradeSquare, etc.)

## 21. Natars/AI API ⏳ FUTURE
**Priority:** FUTURE (AI NPC integration)  
**Endpoints:** 6-8 (getNatarVillages, getNatarAttacks, simulateAI, etc.)

---

# Implementation Guidance

## Shared Utilities (Build First)

Create base utilities in `sections/api/include/Api/Helpers/` or extend `ApiAbstractCtrl`:

### 1. World Resolver
```php
protected function getServerDB($worldId) {
    $server = Server::getServerByWId($worldId);
    if (!$server) {
        throw new NotFoundException("World not found");
    }
    return ServerDB::getInstance($server['configFileLocation']);
}
```

### 2. Village Validator
```php
protected function validateVillageOwnership($serverDB, $villageId, $uid) {
    $stmt = $serverDB->prepare("SELECT owner FROM vdata WHERE kid=:kid");
    $stmt->execute(['kid' => $villageId]);
    $owner = $stmt->fetchColumn();
    if ($owner != $uid) {
        throw new UnauthorizedException("Village not owned by user");
    }
}
```

### 3. Response Formatter
```php
protected function formatCoordinates($kid) {
    $y = floor($kid / 801) - 200;
    $x = ($kid % 801) - 200;
    return ['x' => $x, 'y' => $y];
}
```

### 4. Table Checker
```php
protected function tableExists($serverDB, $tableName) {
    $stmt = $serverDB->prepare(
        "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)"
    );
    $stmt->execute([$tableName]);
    return $stmt->fetchColumn();
}
```

---

## Performance Strategies

### 1. Caching
- **Static data:** Building definitions, tribe data (cache indefinitely)
- **Rankings:** Cache for 5-10 minutes
- **Unread counts:** Cache for 30-60 seconds

### 2. Database Optimization
- **Indexed queries:** Use indexed columns (id, kid, owner, allianceId)
- **Batch operations:** Single JOIN vs multiple queries
- **Pagination:** All list endpoints must paginate (limit 50-100)

### 3. Real-time Calculations
- **Resources:** Calculate on-the-fly based on production rates
- **Movements:** Calculate remaining time vs storing completion time
- **Training:** Real-time queue processing

---

## Testing Strategy

### 1. Unit Tests
Test each endpoint with:
- ✅ Valid request → Success response
- ❌ Missing parameters → Exception
- ❌ Unauthorized access → Exception
- ❌ Invalid data → Validation error

### 2. Integration Tests
Use test accounts (TestPlayer1-3, AdminTest):
```bash
# Village API
curl -X POST http://localhost:5000/v1/village/getVillageList \
  -H "Content-Type: application/json" \
  -d '{"worldId":"testworld","uid":2,"lang":"en-US"}'

# Expected: List of 7 villages for TestPlayer1
```

### 3. Performance Tests
- Measure with `microtime(true)`
- Log slow queries (>100ms)
- Verify <200ms target for all endpoints

### 4. Load Tests
- Use ApacheBench or k6
- Simulate 100 concurrent users
- Monitor response times under load

---

## Build Sequence Summary

### Phase 4A (CRITICAL) - Weeks 1-2
1. ✅ VillageCtrl (5 endpoints) - COMPLETED
2. MapCtrl (5 endpoints)
3. TroopCtrl (6 endpoints)
4. AllianceCtrl (5 endpoints)
5. MarketCtrl (5 endpoints)
**Total:** 26 endpoints

### Phase 4B (IMPORTANT) - Weeks 3-4
6. HeroCtrl (9 endpoints)
7. QuestCtrl (5 endpoints)
8. ReportsCtrl (6 endpoints)
9. MessagesCtrl (8 endpoints)
10. StatisticsCtrl (6 endpoints)
**Total:** 34 endpoints

### Phase 4C (IMPORTANT) - Weeks 5-6
11. ArtifactsCtrl (5 endpoints)
12. MedalsCtrl (4 endpoints)
13. OasisCtrl (5 endpoints)
14. WonderCtrl (5 endpoints)
**Total:** 19 endpoints

### Phase 4D (OPTIONAL) - Week 7+
15. AccountCtrl (8 endpoints)
16. PaymentCtrl (6 endpoints)
17. AdminCtrl (6 endpoints)
**Total:** 20 endpoints

### Future Enhancements
18. FarmlistCtrl (6 endpoints)
19. TournamentCtrl (5 endpoints)
20. NatarsCtrl (8 endpoints) - AI NPC integration
**Total:** 19 endpoints

---

## Grand Total: 118 API Endpoints

**Current Status:** 5/118 (4.2%) ✅  
**Phase 4A Target:** 26/118 (22%)  
**Phase 4B Target:** 60/118 (51%)  
**Phase 4C Target:** 79/118 (67%)  
**Phase 4D Target:** 99/118 (84%)  
**100% Completion:** 118/118 endpoints

---

## Background Workers & Scheduled Tasks

### Required Background Processes
1. **Resource Production Worker** - Update resources every minute
2. **Movement Processor** - Process troop arrivals
3. **Training Queue Processor** - Complete unit training
4. **Building Queue Processor** - Complete construction
5. **Market Merchant Processor** - Handle trade arrivals
6. **Adventure Completion** - Process hero adventures
7. **Auction Expiration** - Close expired auctions
8. **Daily Quest Reset** - Reset daily quests
9. **Weekly Rankings** - Calculate medal rankings
10. **Server Maintenance** - Cleanup old data

### Implementation Options
- **Cron jobs:** Linux cron for scheduled tasks
- **Queue system:** Database-backed job queue
- **WebSockets:** Real-time updates (optional)

---

## Database Migration Strategy

### Current Schema: 26 Tables
Essential tables already available for Phase 4A-4B

### Missing Tables (64 per-world + 20 global)
- **Phase 4A:** Use existing tables with graceful fallbacks
- **Phase 4B:** Add missing tables incrementally (hero adventures, auction bids)
- **Phase 4C:** Add artifact tracking, WW construction tables
- **Phase 4D:** Add payment transactions, admin logs

### Migration Approach
1. Continue with existing 26 tables
2. Add missing tables only when features require them
3. Use graceful degradation for optional features
4. Run migrations per-world schema

---

## API Documentation Template

For each endpoint, document:

```markdown
### Endpoint Name
**URL:** `/v1/controller/action`
**Method:** POST
**Priority:** CRITICAL/IMPORTANT/OPTIONAL
**Complexity:** LOW/MEDIUM/HIGH
**Performance Target:** <XXms

**Request Payload:**
```json
{
  "worldId": "testworld",
  "uid": 2,
  "param": "value",
  "lang": "en-US"
}
```

**Response:**
```json
{
  "success": true,
  "data": {...},
  "error": {
    "errorType": null,
    "errorMsg": null
  }
}
```

**Validation:**
- Check X
- Validate Y
- Calculate Z

**Database Tables:** table1, table2

**Game Rules:**
- Rule 1
- Rule 2

**Error Cases:**
- Missing parameter → MissingParameterException
- Unauthorized → UnauthorizedException
- Not found → NotFoundException
```

---

## Next Steps

1. **Complete Phase 4A** - Finish Map, Troop, Alliance, Market controllers
2. **Create shared utilities** - World resolver, validators, formatters
3. **Integration testing** - Test all endpoints with maxed accounts
4. **Performance verification** - Ensure <200ms target met
5. **Documentation** - Document all endpoints with examples
6. **Phase 4B planning** - Prepare Hero, Quest, Reports, Messages controllers

---

**Last Updated:** October 30, 2025  
**Next Review:** After Phase 4A completion
