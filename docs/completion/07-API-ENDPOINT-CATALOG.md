# API Endpoint Catalog

Complete reference for all API endpoints in the Travian-style game automation system.

## Table of Contents

1. [Overview](#overview)
2. [Authentication & Configuration](#authentication--configuration)
3. [Game Core API](#game-core-api)
4. [Automation & Management API](#automation--management-api)
5. [Common Response Format](#common-response-format)
6. [Rate Limits & Validation](#rate-limits--validation)

---

## Overview

### Base URL
```
https://your-domain.com/sections/api/
```

### Routing Pattern
- **Standard endpoints**: `POST /v1/{section}/{action}`
- **Config endpoints**: `POST /v1/{action}` (section defaults to 'config')

### HTTP Methods
- All endpoints accept: `GET`, `POST`, `OPTIONS`
- Primary method: `POST` with JSON payload

### Request Headers
```
Content-Type: application/json
Access-Control-Allow-Origin: *
```

### Common Request Parameters
```json
{
  "lang": "en-US",              // Required (except config endpoints)
  "playerId": 123,              // Optional - player context
  "player_id": 123,             // Alternative player ID field
  "playerType": "human",        // Optional - "human" or "npc"
  "player_type": "npc"          // Alternative player type field
}
```

---

## Authentication & Configuration

### Config Endpoints

#### `POST /v1/loadConfig`
Load initial application configuration.

**Request:**
```json
{}
```

**Response:**
```json
{
  "defaultLang": "international",
  "globalCssClass": "travian",
  "autoCheckTermsAndConditions": true,
  "registrationRecommendedMinSecondsPast": -3600,
  "showLoginAfterServerFinished": true
}
```

**Authentication**: None  
**Rate Limit**: Unlimited

---

#### `POST /v1/token`
Generate CSRF token for secure requests.

**Request:**
```json
{}
```

**Response:**
```json
{
  "token": "abc123def456..."
}
```

**Authentication**: None  
**Rate Limit**: Unlimited

---

### Authentication Endpoints

#### `POST /v1/auth/login`
Authenticate user and get redirect URL.

**Request:**
```json
{
  "gameWorldId": 1,
  "usernameOrEmail": "player@example.com",
  "password": "SecurePass123",
  "lowResMode": false,
  "captcha": "optional_captcha_response"
}
```

**Response:**
```json
{
  "redirect": "https://game.example.com/login.php?handshake=xyz123"
}
```

**Validation**:
- `gameWorldId`: Must be valid, active game world
- `password`: Minimum 4 characters
- Account must not be inactive (21+ days)

**Error Responses**:
- `userDoesNotExists` - Invalid username/email
- `passwordWrong` - Incorrect password
- `unknownGameWorld` - Invalid game world ID
- `gameWorldNotStartedYet` - Game hasn't started
- `accountIsInactive` - Account inactive for 21+ days

---

#### `POST /v1/auth/forgotPassword`
Request password reset email.

**Request:**
```json
{
  "email": "player@example.com",
  "gameWorldId": 1,
  "captcha": "optional"
}
```

**Response:**
```json
{
  "success": true
}
```

**Error Responses**:
- `emailUnknown` - Email not found
- `unknownGameWorld` - Invalid world ID

---

#### `POST /v1/auth/forgotGameWorld`
Retrieve list of game worlds associated with email.

**Request:**
```json
{
  "email": "player@example.com",
  "captcha": "optional"
}
```

**Response:**
```json
{
  "success": true
}
```

Sends email with game world list.

---

#### `POST /v1/auth/updatePassword`
Update password using recovery code.

**Request:**
```json
{
  "recoveryCode": "abc123",
  "worldId": 1,
  "uid": 456,
  "password": "NewSecurePass123"
}
```

**Response:**
```json
{
  "success": true
}
```

**Validation**:
- `password`: Minimum 4 characters
- `recoveryCode`: Must be valid and not expired

---

### Registration Endpoints

#### `POST /v1/register/register`
Create new player account.

**Request:**
```json
{
  "gameWorldId": 1,
  "username": "PlayerName",
  "email": "player@example.com",
  "password": "SecurePass123",
  "tribe": "romans",
  "acceptTerms": true,
  "captcha": "captcha_response"
}
```

**Response:**
```json
{
  "success": true,
  "activationRequired": true
}
```

**Validation**:
- `username`: Unique, 3-15 characters
- `email`: Valid email format, unique
- `password`: Minimum 4 characters
- `tribe`: One of: romans, gauls, teutons
- `acceptTerms`: Must be true

---

#### `POST /v1/register/activate`
Activate account with activation token.

**Request:**
```json
{
  "token": "activation_token_here"
}
```

**Response:**
```json
{
  "success": true,
  "redirect": "https://game.example.com/"
}
```

---

#### `POST /v1/register/resendActivationMail`
Resend activation email.

**Request:**
```json
{
  "email": "player@example.com",
  "gameWorldId": 1
}
```

**Response:**
```json
{
  "success": true
}
```

---

## Game Core API

### Server Management

#### `POST /v1/servers/loadServers`
Get list of all game servers.

**Request:**
```json
{
  "lang": "en-US"
}
```

**Response:**
```json
{
  "data": {
    "servers": [
      {
        "id": 1,
        "worldId": "s1",
        "gameWorldUrl": "https://s1.example.com/",
        "startTime": 1609459200,
        "speed": 3,
        "finished": false
      }
    ]
  }
}
```

---

#### `POST /v1/servers/loadServerByID`
Get server details by internal ID.

**Request:**
```json
{
  "lang": "en-US",
  "serverId": 1
}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "worldId": "s1",
    "gameWorldUrl": "https://s1.example.com/",
    "startTime": 1609459200
  }
}
```

---

#### `POST /v1/servers/loadServerByWID`
Get server details by world ID.

**Request:**
```json
{
  "lang": "en-US",
  "worldId": "s1"
}
```

---

### Village Management

#### `POST /v1/village/getVillageList`
Get all villages belonging to current player.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "villages": [
      {
        "id": 456,
        "name": "Capital",
        "x": 0,
        "y": 0,
        "population": 523,
        "isCapital": true
      }
    ]
  }
}
```

---

#### `POST /v1/village/getVillageDetails`
Get detailed information about a specific village.

**Request:**
```json
{
  "lang": "en-US",
  "villageId": 456
}
```

**Response:**
```json
{
  "data": {
    "id": 456,
    "name": "Capital",
    "x": 0,
    "y": 0,
    "population": 523,
    "tribe": "romans",
    "buildings": {
      "1": {"type": "woodcutter", "level": 5},
      "2": {"type": "clayPit", "level": 5}
    },
    "resources": {
      "wood": 1500,
      "clay": 1200,
      "iron": 800,
      "crop": 2000
    }
  }
}
```

---

#### `POST /v1/village/getResources`
Get current resource levels for a village.

**Request:**
```json
{
  "lang": "en-US",
  "villageId": 456
}
```

**Response:**
```json
{
  "data": {
    "wood": 1500,
    "clay": 1200,
    "iron": 800,
    "crop": 2000,
    "production": {
      "wood": 150,
      "clay": 120,
      "iron": 80,
      "crop": 50
    },
    "capacity": {
      "warehouse": 8000,
      "granary": 8000
    }
  }
}
```

---

#### `POST /v1/village/getBuildingQueue`
Get building construction queue.

**Request:**
```json
{
  "lang": "en-US",
  "villageId": 456
}
```

**Response:**
```json
{
  "data": {
    "queue": [
      {
        "id": 789,
        "buildingType": "barracks",
        "level": 3,
        "finishTime": "2025-10-31 12:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/village/upgradeBuilding`
Queue a building upgrade.

**Request:**
```json
{
  "lang": "en-US",
  "villageId": 456,
  "buildingId": 1,
  "targetLevel": 6
}
```

**Response:**
```json
{
  "data": {
    "queueId": 789,
    "finishTime": "2025-10-31 12:00:00",
    "cost": {
      "wood": 200,
      "clay": 150,
      "iron": 100,
      "crop": 50
    }
  }
}
```

**Validation**:
- Building must exist in village
- Sufficient resources required
- Building level prerequisites must be met

---

### Troop Management

#### `POST /v1/troop/getTroops`
Get troop counts for a village.

**Request:**
```json
{
  "lang": "en-US",
  "villageId": 456
}
```

**Response:**
```json
{
  "data": {
    "troops": {
      "legionnaire": 50,
      "praetorian": 30,
      "imperian": 20,
      "equites_legati": 10
    }
  }
}
```

---

#### `POST /v1/troop/trainUnits`
Queue troop training.

**Request:**
```json
{
  "lang": "en-US",
  "villageId": 456,
  "unitType": "legionnaire",
  "quantity": 10
}
```

**Response:**
```json
{
  "data": {
    "queueId": 890,
    "unitType": "legionnaire",
    "quantity": 10,
    "finishTime": "2025-10-31 15:00:00",
    "cost": {
      "wood": 950,
      "clay": 750,
      "iron": 400,
      "crop": 400
    }
  }
}
```

---

#### `POST /v1/troop/getTrainingQueue`
Get current training queue.

**Request:**
```json
{
  "lang": "en-US",
  "villageId": 456
}
```

**Response:**
```json
{
  "data": {
    "queue": [
      {
        "id": 890,
        "unitType": "legionnaire",
        "quantity": 10,
        "finishTime": "2025-10-31 15:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/troop/sendAttack`
Send attack to target coordinates.

**Request:**
```json
{
  "lang": "en-US",
  "fromVillageId": 456,
  "targetX": 5,
  "targetY": -3,
  "troops": {
    "legionnaire": 20,
    "imperian": 10
  },
  "attackType": "normal"
}
```

**Response:**
```json
{
  "data": {
    "movementId": 991,
    "arrivalTime": "2025-10-31 18:00:00",
    "distance": 5.8,
    "duration": 10800
  }
}
```

**Validation**:
- `attackType`: One of: normal, raid, spy
- Sufficient troops must be available
- Target must be valid coordinates

---

#### `POST /v1/troop/sendReinforcement`
Send reinforcements to target village.

**Request:**
```json
{
  "lang": "en-US",
  "fromVillageId": 456,
  "targetVillageId": 789,
  "troops": {
    "legionnaire": 30,
    "praetorian": 15
  }
}
```

**Response:**
```json
{
  "data": {
    "movementId": 992,
    "arrivalTime": "2025-10-31 17:00:00"
  }
}
```

---

#### `POST /v1/troop/getMovements`
Get all troop movements (incoming/outgoing).

**Request:**
```json
{
  "lang": "en-US",
  "villageId": 456
}
```

**Response:**
```json
{
  "data": {
    "incoming": [
      {
        "id": 993,
        "type": "attack",
        "from": {"x": 10, "y": -5},
        "arrivalTime": "2025-10-31 20:00:00"
      }
    ],
    "outgoing": [
      {
        "id": 991,
        "type": "attack",
        "target": {"x": 5, "y": -3},
        "arrivalTime": "2025-10-31 18:00:00"
      }
    ]
  }
}
```

---

### Map & Exploration

#### `POST /v1/map/getMapData`
Get map tile data for a region.

**Request:**
```json
{
  "lang": "en-US",
  "centerX": 0,
  "centerY": 0,
  "radius": 10
}
```

**Response:**
```json
{
  "data": {
    "tiles": [
      {
        "x": 0,
        "y": 0,
        "type": "village",
        "owner": "PlayerName",
        "population": 523
      },
      {
        "x": 1,
        "y": 0,
        "type": "oasis",
        "bonus": "wood+25%"
      }
    ]
  }
}
```

---

#### `POST /v1/map/getVillageInfo`
Get information about a village at coordinates.

**Request:**
```json
{
  "lang": "en-US",
  "x": 5,
  "y": -3
}
```

**Response:**
```json
{
  "data": {
    "id": 888,
    "name": "Enemy Village",
    "owner": "EnemyPlayer",
    "tribe": "teutons",
    "population": 412,
    "alliance": "Alliance Name"
  }
}
```

---

#### `POST /v1/map/getTileDetails`
Get detailed tile information.

**Request:**
```json
{
  "lang": "en-US",
  "x": 1,
  "y": 0
}
```

**Response:**
```json
{
  "data": {
    "type": "oasis",
    "x": 1,
    "y": 0,
    "bonus": "wood+25%",
    "troops": null,
    "conquerable": true
  }
}
```

---

#### `POST /v1/map/searchVillages`
Search for villages by name or owner.

**Request:**
```json
{
  "lang": "en-US",
  "query": "Capital",
  "searchType": "village"
}
```

**Response:**
```json
{
  "data": {
    "results": [
      {
        "id": 456,
        "name": "Capital",
        "owner": "PlayerName",
        "x": 0,
        "y": 0,
        "population": 523
      }
    ]
  }
}
```

**Validation**:
- `searchType`: One of: village, player, alliance
- `query`: Minimum 3 characters

---

#### `POST /v1/map/getNearby`
Get nearby villages/oases.

**Request:**
```json
{
  "lang": "en-US",
  "x": 0,
  "y": 0,
  "radius": 5
}
```

---

### Marketplace & Trading

#### `POST /v1/market/getOffers`
Get available trade offers.

**Request:**
```json
{
  "lang": "en-US",
  "resourceType": "wood"
}
```

**Response:**
```json
{
  "data": {
    "offers": [
      {
        "id": 101,
        "sellerId": 789,
        "offering": {"wood": 1000},
        "requesting": {"clay": 800},
        "ratio": 1.25,
        "expiresAt": "2025-11-01 12:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/market/createOffer`
Create a trade offer.

**Request:**
```json
{
  "lang": "en-US",
  "villageId": 456,
  "offering": {"wood": 1000},
  "requesting": {"clay": 800},
  "duration": 86400
}
```

**Response:**
```json
{
  "data": {
    "offerId": 102,
    "expiresAt": "2025-11-01 12:00:00"
  }
}
```

---

#### `POST /v1/market/sendResources`
Send resources to another village.

**Request:**
```json
{
  "lang": "en-US",
  "fromVillageId": 456,
  "toVillageId": 789,
  "resources": {
    "wood": 500,
    "clay": 500,
    "iron": 500,
    "crop": 500
  }
}
```

**Response:**
```json
{
  "data": {
    "transportId": 201,
    "merchantsUsed": 4,
    "arrivalTime": "2025-10-31 14:30:00"
  }
}
```

**Validation**:
- Sufficient resources must be available
- Sufficient merchants must be available
- Target village must exist

---

#### `POST /v1/market/acceptOffer`
Accept a trade offer.

**Request:**
```json
{
  "lang": "en-US",
  "offerId": 101,
  "villageId": 456
}
```

**Response:**
```json
{
  "data": {
    "success": true,
    "transportId": 202,
    "arrivalTime": "2025-10-31 16:00:00"
  }
}
```

---

#### `POST /v1/market/getTradeHistory`
Get trade history for a village.

**Request:**
```json
{
  "lang": "en-US",
  "villageId": 456,
  "limit": 50
}
```

---

### Hero System

#### `POST /v1/hero/getHero`
Get hero information.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "id": 501,
    "name": "Hero Name",
    "level": 15,
    "experience": 8500,
    "health": 100,
    "attributes": {
      "fightingStrength": 80,
      "attackBonus": 50,
      "defenseBonus": 30,
      "regeneration": 20
    },
    "inventory": [
      {
        "id": 601,
        "type": "weapon",
        "name": "Iron Sword",
        "bonus": "+5 attack"
      }
    ]
  }
}
```

---

#### `POST /v1/hero/levelUp`
Level up hero and allocate attribute points.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "attributes": {
    "fightingStrength": 5,
    "attackBonus": 3,
    "defenseBonus": 2
  }
}
```

**Response:**
```json
{
  "data": {
    "newLevel": 16,
    "attributes": {
      "fightingStrength": 85,
      "attackBonus": 53,
      "defenseBonus": 32,
      "regeneration": 20
    }
  }
}
```

**Validation**:
- Hero must have enough experience
- Total attribute points must not exceed available points

---

#### `POST /v1/hero/equipItem`
Equip an item to hero.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "itemId": 601,
  "slot": "weapon"
}
```

**Response:**
```json
{
  "data": {
    "equipped": true,
    "previousItem": null
  }
}
```

---

#### `POST /v1/hero/startAdventure`
Start an adventure.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "adventureId": 701
}
```

**Response:**
```json
{
  "data": {
    "movementId": 801,
    "arrivalTime": "2025-10-31 21:00:00",
    "difficulty": "medium"
  }
}
```

---

#### `POST /v1/hero/getAdventures`
Get available adventures.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "adventures": [
      {
        "id": 701,
        "x": 15,
        "y": -8,
        "difficulty": "medium",
        "distance": 17.0,
        "expiresAt": "2025-11-01 00:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/hero/sellItem`
Sell an item from hero inventory.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "itemId": 602
}
```

**Response:**
```json
{
  "data": {
    "silverGained": 250
  }
}
```

---

#### `POST /v1/hero/auctionItem`
List an item on auction.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "itemId": 603,
  "startingBid": 500,
  "duration": 86400
}
```

**Response:**
```json
{
  "data": {
    "auctionId": 901,
    "endsAt": "2025-11-01 12:00:00"
  }
}
```

---

#### `POST /v1/hero/bidOnAuction`
Place bid on auction.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "auctionId": 901,
  "bidAmount": 600
}
```

**Response:**
```json
{
  "data": {
    "currentBid": 600,
    "isWinning": true
  }
}
```

---

#### `POST /v1/hero/getAuctions`
Get active auctions.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "auctions": [
      {
        "id": 901,
        "itemName": "Steel Helmet",
        "currentBid": 600,
        "endsAt": "2025-11-01 12:00:00"
      }
    ]
  }
}
```

---

### Alliance System

#### `POST /v1/alliance/create`
Create a new alliance.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "allianceName": "Empire",
  "tag": "EMP",
  "description": "A powerful alliance"
}
```

**Response:**
```json
{
  "data": {
    "allianceId": 1001,
    "name": "Empire",
    "tag": "EMP"
  }
}
```

**Validation**:
- `allianceName`: 3-30 characters, unique
- `tag`: 2-6 characters, unique
- Player must not already be in an alliance

---

#### `POST /v1/alliance/invite`
Invite player to alliance.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "targetPlayerId": 456
}
```

**Response:**
```json
{
  "data": {
    "inviteId": 1101,
    "sent": true
  }
}
```

---

#### `POST /v1/alliance/getMembers`
Get alliance member list.

**Request:**
```json
{
  "lang": "en-US",
  "allianceId": 1001
}
```

**Response:**
```json
{
  "data": {
    "members": [
      {
        "playerId": 123,
        "playerName": "Leader",
        "rank": "leader",
        "population": 2500,
        "villages": 3
      },
      {
        "playerId": 456,
        "playerName": "Member",
        "rank": "member",
        "population": 1200,
        "villages": 2
      }
    ]
  }
}
```

---

#### `POST /v1/alliance/setDiplomacy`
Set diplomatic status with another alliance.

**Request:**
```json
{
  "lang": "en-US",
  "allianceId": 1001,
  "targetAllianceId": 1002,
  "status": "nap"
}
```

**Response:**
```json
{
  "data": {
    "status": "nap",
    "established": true
  }
}
```

**Validation**:
- `status`: One of: war, nap, ally, confederation
- Requester must have permission to manage diplomacy

---

#### `POST /v1/alliance/getDiplomacy`
Get diplomatic relations.

**Request:**
```json
{
  "lang": "en-US",
  "allianceId": 1001
}
```

**Response:**
```json
{
  "data": {
    "relations": [
      {
        "allianceId": 1002,
        "allianceName": "Neighbors",
        "status": "nap"
      }
    ]
  }
}
```

---

### Quest System

#### `POST /v1/quest/getActiveQuests`
Get active quests for player.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "quests": [
      {
        "id": 1201,
        "title": "Build Barracks",
        "description": "Construct a barracks in your village",
        "progress": 0,
        "required": 1,
        "reward": {
          "wood": 200,
          "clay": 200,
          "iron": 100,
          "crop": 100
        }
      }
    ]
  }
}
```

---

#### `POST /v1/quest/completeQuest`
Complete a quest and claim rewards.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "questId": 1201
}
```

**Response:**
```json
{
  "data": {
    "completed": true,
    "rewards": {
      "wood": 200,
      "clay": 200,
      "iron": 100,
      "crop": 100
    }
  }
}
```

---

#### `POST /v1/quest/getQuestRewards`
Get available quest rewards.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "questId": 1201
}
```

---

#### `POST /v1/quest/skipQuest`
Skip a quest (if allowed).

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "questId": 1201
}
```

**Response:**
```json
{
  "data": {
    "skipped": true
  }
}
```

---

#### `POST /v1/quest/getQuestProgress`
Get progress on a specific quest.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "questId": 1201
}
```

**Response:**
```json
{
  "data": {
    "questId": 1201,
    "progress": 0,
    "required": 1,
    "percentage": 0
  }
}
```

---

### Messaging System

#### `POST /v1/messages/getInbox`
Get player's inbox messages.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "folder": "inbox",
  "limit": 50,
  "offset": 0
}
```

**Response:**
```json
{
  "data": {
    "messages": [
      {
        "id": 1301,
        "from": "OtherPlayer",
        "subject": "Trade Offer",
        "timestamp": "2025-10-31 10:00:00",
        "isRead": false
      }
    ],
    "pagination": {
      "total": 100,
      "limit": 50,
      "offset": 0
    }
  }
}
```

---

#### `POST /v1/messages/getMessage`
Get message details.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "messageId": 1301
}
```

**Response:**
```json
{
  "data": {
    "id": 1301,
    "from": "OtherPlayer",
    "subject": "Trade Offer",
    "body": "I'd like to trade resources with you...",
    "timestamp": "2025-10-31 10:00:00",
    "isRead": true
  }
}
```

---

#### `POST /v1/messages/sendMessage`
Send a message to another player.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "recipientId": 456,
  "subject": "Hello",
  "body": "Message content here..."
}
```

**Response:**
```json
{
  "data": {
    "messageId": 1302,
    "sent": true
  }
}
```

**Validation**:
- `subject`: Maximum 100 characters
- `body`: Maximum 5000 characters
- Recipient must exist and not be blocked

---

#### `POST /v1/messages/deleteMessage`
Delete a message.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "messageId": 1301
}
```

**Response:**
```json
{
  "data": {
    "deleted": true
  }
}
```

---

#### `POST /v1/messages/archiveMessage`
Archive a message.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "messageId": 1301
}
```

**Response:**
```json
{
  "data": {
    "archived": true
  }
}
```

---

#### `POST /v1/messages/getAllianceMessages`
Get alliance messages.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "limit": 50
}
```

**Response:**
```json
{
  "data": {
    "messages": [
      {
        "id": 1303,
        "from": "AllianceLeader",
        "subject": "Alliance Strategy",
        "timestamp": "2025-10-31 11:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/messages/sendAllianceMessage`
Send message to all alliance members.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "subject": "Important Announcement",
  "body": "Message to all members..."
}
```

**Response:**
```json
{
  "data": {
    "messageId": 1304,
    "recipientCount": 25
  }
}
```

**Validation**:
- Player must be in an alliance
- Player must have permission to send alliance messages

---

#### `POST /v1/messages/getUnreadCount`
Get count of unread messages.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "unreadCount": 5
  }
}
```

---

### Reports System

#### `POST /v1/reports/getReports`
Get battle and event reports.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "reportType": "all",
  "limit": 50,
  "offset": 0
}
```

**Response:**
```json
{
  "data": {
    "reports": [
      {
        "id": 1401,
        "type": "battle",
        "subject": "Attack on Village",
        "timestamp": "2025-10-31 09:00:00",
        "isRead": false
      }
    ],
    "pagination": {
      "total": 75,
      "limit": 50,
      "offset": 0
    }
  }
}
```

**Validation**:
- `reportType`: One of: all, battle, trade, alliance, system

---

#### `POST /v1/reports/getReportDetails`
Get detailed report information.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "reportId": 1401
}
```

**Response:**
```json
{
  "data": {
    "id": 1401,
    "type": "battle",
    "timestamp": "2025-10-31 09:00:00",
    "attacker": {
      "name": "Enemy",
      "troops": {"legionnaire": 50}
    },
    "defender": {
      "name": "You",
      "troops": {"praetorian": 30}
    },
    "result": {
      "winner": "defender",
      "casualties": {
        "attacker": {"legionnaire": 35},
        "defender": {"praetorian": 8}
      },
      "bounty": {
        "wood": 100,
        "clay": 100,
        "iron": 50,
        "crop": 50
      }
    }
  }
}
```

---

#### `POST /v1/reports/markRead`
Mark report as read.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "reportId": 1401
}
```

**Response:**
```json
{
  "data": {
    "marked": true
  }
}
```

---

#### `POST /v1/reports/deleteReport`
Delete a report.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "reportId": 1401
}
```

**Response:**
```json
{
  "data": {
    "deleted": true
  }
}
```

---

#### `POST /v1/reports/archiveReport`
Archive a report.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "reportId": 1401
}
```

**Response:**
```json
{
  "data": {
    "archived": true
  }
}
```

---

#### `POST /v1/reports/getUnreadCount`
Get count of unread reports.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "unreadCount": 3
  }
}
```

---

### Statistics

#### `POST /v1/statistics/getPlayerRankings`
Get player rankings.

**Request:**
```json
{
  "lang": "en-US",
  "rankingType": "population",
  "limit": 100,
  "offset": 0
}
```

**Response:**
```json
{
  "data": {
    "rankings": [
      {
        "rank": 1,
        "playerId": 789,
        "playerName": "TopPlayer",
        "population": 15000,
        "villages": 10
      }
    ],
    "pagination": {
      "total": 1000,
      "limit": 100,
      "offset": 0
    }
  }
}
```

**Validation**:
- `rankingType`: One of: population, attack, defense, offPoints, defPoints

---

#### `POST /v1/statistics/getAllianceRankings`
Get alliance rankings.

**Request:**
```json
{
  "lang": "en-US",
  "rankingType": "population",
  "limit": 100
}
```

**Response:**
```json
{
  "data": {
    "rankings": [
      {
        "rank": 1,
        "allianceId": 1001,
        "allianceName": "Empire",
        "tag": "EMP",
        "totalPopulation": 50000,
        "memberCount": 25
      }
    ]
  }
}
```

---

#### `POST /v1/statistics/getPlayerStats`
Get detailed player statistics.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "playerId": 123,
    "playerName": "PlayerName",
    "population": 2500,
    "villages": 3,
    "attackPoints": 5000,
    "defensePoints": 3000,
    "rank": {
      "population": 50,
      "attack": 75,
      "defense": 120
    }
  }
}
```

---

#### `POST /v1/statistics/getAllianceStats`
Get detailed alliance statistics.

**Request:**
```json
{
  "lang": "en-US",
  "allianceId": 1001
}
```

**Response:**
```json
{
  "data": {
    "allianceId": 1001,
    "name": "Empire",
    "totalPopulation": 50000,
    "memberCount": 25,
    "averagePopulation": 2000,
    "rank": 1
  }
}
```

---

#### `POST /v1/statistics/getTop10`
Get top 10 players/alliances.

**Request:**
```json
{
  "lang": "en-US",
  "category": "attackers"
}
```

**Response:**
```json
{
  "data": {
    "top10": [
      {
        "rank": 1,
        "name": "TopAttacker",
        "value": 50000
      }
    ]
  }
}
```

**Validation**:
- `category`: One of: attackers, defenders, population, alliances

---

#### `POST /v1/statistics/getWorldStats`
Get world-wide statistics.

**Request:**
```json
{
  "lang": "en-US"
}
```

**Response:**
```json
{
  "data": {
    "totalPlayers": 1000,
    "activePlayers": 850,
    "totalAlliances": 50,
    "totalVillages": 3500,
    "totalPopulation": 500000
  }
}
```

---

### News System

#### `POST /v1/news/loadNews`
Get latest news/announcements.

**Request:**
```json
{
  "lang": "en-US"
}
```

**Response:**
```json
{
  "data": {
    "news": [
      {
        "id": 1501,
        "title": "New Server Starting",
        "content": "Server X will start on...",
        "timestamp": "2025-10-30 12:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/news/getNewsById`
Get specific news article.

**Request:**
```json
{
  "lang": "en-US",
  "newsId": 1501
}
```

**Response:**
```json
{
  "data": {
    "id": 1501,
    "title": "New Server Starting",
    "content": "Server X will start on...",
    "timestamp": "2025-10-30 12:00:00"
  }
}
```

---

## Automation & Management API

### Building Automation

#### `POST /v1/building/queueBuildings`
Queue multiple building upgrades at once.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "villageId": 456,
  "buildingPlan": [
    {"type": "woodcutter", "level": 6},
    {"type": "clayPit", "level": 6},
    {"type": "ironMine", "level": 5}
  ]
}
```

**Response:**
```json
{
  "data": {
    "queued_buildings": [
      {"queue_id": 1601, "building_type": "woodcutter", "target_level": 6},
      {"queue_id": 1602, "building_type": "clayPit", "target_level": 6}
    ],
    "skipped_buildings": [],
    "total_queued": 2
  },
  "message": "Buildings queued successfully"
}
```

**Feature Gate**: Requires `building` feature enabled  
**Authentication**: Requires valid `playerId`

---

#### `POST /v1/building/getQueue`
Get building queue for a village.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "villageId": 456
}
```

**Response:**
```json
{
  "data": {
    "queue": [
      {
        "id": 1601,
        "village_id": 456,
        "building_type": "woodcutter",
        "target_level": 6,
        "status": "pending",
        "created_at": "2025-10-31 10:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/building/balanceResources`
Get building recommendations based on strategy.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "strategy": "economic",
  "autoQueue": false
}
```

**Response:**
```json
{
  "data": {
    "strategy": "economic",
    "recommendations": [
      {
        "village_id": 456,
        "building_type": "warehouse",
        "target_level": 10,
        "priority": "high"
      },
      {
        "village_id": 456,
        "building_type": "granary",
        "target_level": 10,
        "priority": "high"
      }
    ],
    "auto_queued": false
  }
}
```

**Validation**:
- `strategy`: One of: economic, military, balanced
- `autoQueue`: Boolean, if true automatically queues recommendations

---

#### `POST /v1/building/cancelQueue`
Cancel a queued building.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "queueId": 1601
}
```

**Response:**
```json
{
  "data": {
    "queue_id": 1601,
    "cancelled": true
  }
}
```

---

### Training Automation

#### `POST /v1/training/executeTraining`
Queue troop training based on plan.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "villageId": 456,
  "troopPlan": [
    {"type": "legionnaire", "quantity": 20},
    {"type": "praetorian", "quantity": 10}
  ]
}
```

**Response:**
```json
{
  "data": {
    "queued_troops": [
      {"queue_id": 1701, "troop_type": "legionnaire", "quantity": 20},
      {"queue_id": 1702, "troop_type": "praetorian", "quantity": 10}
    ],
    "total_cost": {
      "wood": 3800,
      "clay": 3250,
      "iron": 2250,
      "crop": 1400
    },
    "total_queued": 2
  }
}
```

**Feature Gate**: Requires `training` feature enabled

---

#### `POST /v1/training/enableContinuous`
Enable/disable continuous troop training.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "villageId": 456,
  "troopType": "legionnaire",
  "enabled": true
}
```

**Response:**
```json
{
  "data": {
    "village_id": 456,
    "troop_type": "legionnaire",
    "enabled": true
  }
}
```

---

#### `POST /v1/training/getTrainingQueue`
Get training queue for a village.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "villageId": 456
}
```

**Response:**
```json
{
  "data": {
    "queue": [
      {
        "id": 1701,
        "troop_type": "legionnaire",
        "quantity": 20,
        "status": "queued",
        "created_at": "2025-10-31 11:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/training/cancelTraining`
Cancel queued troop training.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "queueId": 1701
}
```

**Response:**
```json
{
  "data": {
    "queue_id": 1701,
    "cancelled": true
  }
}
```

---

### Farming Automation

#### `POST /v1/farming/executeFarmlist`
Execute attacks on farmlist targets.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "farmlistId": 1801
}
```

**Response:**
```json
{
  "data": {
    "attacks_sent": 15,
    "skipped_targets": 3,
    "resources_expected": {
      "wood": 2500,
      "clay": 2200,
      "iron": 1800,
      "crop": 2000
    },
    "farmlists_processed": 1
  }
}
```

**Feature Gate**: Requires `farming` feature enabled  
**Validation**: Targets can only be raided every 5 minutes (300 seconds)

---

#### `POST /v1/farming/createFarmlist`
Create a new farmlist with targets.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "farmlistName": "East Farms",
  "targets": [
    {"x": 10, "y": 5},
    {"x": 12, "y": 3},
    {"x": 8, "y": 7}
  ]
}
```

**Response:**
```json
{
  "data": {
    "farmlist_id": 1802,
    "name": "East Farms",
    "targets_added": 3
  }
}
```

---

#### `POST /v1/farming/listFarmlists`
Get all farmlists for player.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "farmlists": [
      {
        "id": 1801,
        "name": "East Farms",
        "is_active": 1,
        "target_count": 15,
        "created_at": "2025-10-30 10:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/farming/updateTargets`
Update farmlist targets.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "farmlistId": 1801,
  "targets": [
    {"x": 10, "y": 5},
    {"x": 11, "y": 6}
  ]
}
```

**Response:**
```json
{
  "data": {
    "farmlist_id": 1801,
    "targets_updated": 2
  }
}
```

---

#### `POST /v1/farming/deleteFarmlist`
Delete a farmlist.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "farmlistId": 1801
}
```

**Response:**
```json
{
  "data": {
    "farmlist_id": 1801,
    "deleted": true
  }
}
```

---

### Defense Automation

#### `POST /v1/defense/autoEvade`
Automatically evade troops from incoming attacks.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "villageId": 456,
  "evasionRules": {
    "threat_threshold": 1000,
    "target_village": 789
  }
}
```

**Response:**
```json
{
  "data": {
    "evaded": true,
    "troops_sent": 150,
    "target_village": 789,
    "arrival_time": "2025-10-31 12:30:00",
    "threat_level": 2500
  }
}
```

**Feature Gate**: Requires `defense` feature enabled  
**Validation**: Threat level must exceed threshold to trigger evasion

---

#### `POST /v1/defense/autoReinforce`
Send automatic reinforcements.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "targetVillageId": 789,
  "troopAllocation": {
    "legionnaire": 50,
    "praetorian": 30
  }
}
```

**Response:**
```json
{
  "data": {
    "reinforcement_id": 1901,
    "target_village_id": 789,
    "troops_sent": {
      "legionnaire": 50,
      "praetorian": 30
    },
    "arrival_time": "2025-10-31 13:00:00"
  }
}
```

---

#### `POST /v1/defense/autoCounter`
Queue automatic counter-attack.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "attackerId": 999,
  "counterStrategy": "immediate"
}
```

**Response:**
```json
{
  "data": {
    "counter_attack_id": 2001,
    "attacker_id": 999,
    "strategy": "immediate",
    "launch_time": "2025-10-31 12:00:00"
  }
}
```

**Validation**:
- `counterStrategy`: One of: immediate, delayed, none

---

#### `POST /v1/defense/getThreats`
Get threat analysis and incoming attacks.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "incoming_attacks": [
      {
        "id": 2101,
        "target_village_id": 456,
        "estimated_power": 1500,
        "arrival_time": "2025-10-31 15:00:00"
      }
    ],
    "total_threat_level": 1500,
    "attack_count": 1,
    "recommendations": [
      "Monitor incoming attacks closely"
    ]
  }
}
```

---

### Logistics Automation

#### `POST /v1/logistics/transportResources`
Transport resources between villages.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "fromVillageId": 456,
  "toVillageId": 789,
  "resources": {
    "wood": 1000,
    "clay": 1000,
    "iron": 1000,
    "crop": 1000
  }
}
```

**Response:**
```json
{
  "data": {
    "transport_id": 2201,
    "from_village_id": 456,
    "to_village_id": 789,
    "resources": {
      "wood": 1000,
      "clay": 1000,
      "iron": 1000,
      "crop": 1000
    },
    "merchants_used": 8,
    "arrival_time": "2025-10-31 12:45:00"
  }
}
```

**Feature Gate**: Requires `logistics` feature enabled  
**Calculation**: Merchants needed = ceil(total_resources / 500)

---

#### `POST /v1/logistics/balanceResources`
Automatically balance resources across villages.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "balanceStrategy": "equal"
}
```

**Response:**
```json
{
  "data": {
    "strategy": "equal",
    "villages_analyzed": 3,
    "transports_executed": [
      {
        "transport_id": 2202,
        "from": 456,
        "to": 789,
        "resources": {
          "wood": 500,
          "clay": 500,
          "iron": 500,
          "crop": 500
        }
      }
    ],
    "total_transports": 1
  }
}
```

**Validation**:
- `balanceStrategy`: One of: equal, priority_capital, by_need

---

#### `POST /v1/logistics/getLogisticsStatus`
Get logistics status and capacity.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "ongoing_transports": [
      {
        "id": 2201,
        "from_village_id": 456,
        "to_village_id": 789,
        "status": "in_transit",
        "created_at": "2025-10-31 12:00:00"
      }
    ],
    "village_capacities": [
      {
        "id": 456,
        "name": "Capital",
        "wood": 5000,
        "warehouse_capacity": 8000,
        "granary_capacity": 8000
      }
    ],
    "merchants": {
      "available": 10,
      "in_use": 8,
      "total": 18
    }
  }
}
```

---

### Away Mode

#### `POST /v1/awayMode/enableAwayMode`
Enable away mode with automation.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123,
  "duration": 24,
  "intensity": "medium"
}
```

**Response:**
```json
{
  "data": {
    "session_id": 2301,
    "enabled": true,
    "duration_hours": 24,
    "enabled_until": "2025-11-01 12:00:00",
    "intensity": "medium",
    "features_active": ["farming", "building", "training", "defense", "logistics"]
  }
}
```

**Feature Gate**: Requires `away_mode` feature enabled  
**Validation**:
- `duration`: 1-168 hours (1 hour to 1 week)
- `intensity`: One of: low, medium, high
- High/medium intensity enables defense and logistics features

---

#### `POST /v1/awayMode/disableAwayMode`
Disable away mode.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "enabled": false,
    "disabled_at": "2025-10-31 12:00:00"
  }
}
```

---

#### `POST /v1/awayMode/getAwayStatus`
Get away mode status and statistics.

**Request:**
```json
{
  "lang": "en-US",
  "playerId": 123
}
```

**Response:**
```json
{
  "data": {
    "enabled": true,
    "session_id": 2301,
    "intensity": "medium",
    "enabled_since": "2025-10-31 12:00:00",
    "enabled_until": "2025-11-01 12:00:00",
    "time_remaining": "23h 45m",
    "actions_taken": 150,
    "successful_actions": 145,
    "resources_gained": {
      "wood": 3500,
      "clay": 3200,
      "iron": 2800,
      "crop": 4000
    }
  }
}
```

---

### Monitoring & Analytics

#### `POST /v1/monitoring/getSystemMetrics`
Get system-wide metrics.

**Request:**
```json
{
  "lang": "en-US"
}
```

**Response:**
```json
{
  "data": {
    "active_npcs": 250,
    "pending_batches": 5,
    "features_enabled": 12,
    "cache_status": "connected",
    "performance": {
      "avg_decision_time_ms": 45.23,
      "decisions_last_hour": 3500,
      "automation_actions_24h": 75000
    }
  }
}
```

**Authentication**: None (server-level metrics)

---

#### `POST /v1/monitoring/getDecisionLog`
Get automation decision log.

**Request:**
```json
{
  "lang": "en-US",
  "actor_type": "rule_based",
  "feature_category": "farming",
  "outcome": "executed",
  "limit": 1000,
  "offset": 0
}
```

**Response:**
```json
{
  "data": {
    "decisions": [
      {
        "id": 2401,
        "actor_id": 123,
        "actor_type": "rule_based",
        "feature_category": "farming",
        "action": "execute_farmlist",
        "outcome": "executed",
        "execution_time_ms": 52,
        "created_at": "2025-10-31 11:30:00"
      }
    ],
    "pagination": {
      "total": 15000,
      "limit": 1000,
      "offset": 0
    }
  }
}
```

**Validation**:
- `actor_type`: One of: rule_based, llm
- `outcome`: One of: executed, skipped, error

---

#### `POST /v1/monitoring/getAuditLog`
Get system audit log.

**Request:**
```json
{
  "lang": "en-US",
  "limit": 500,
  "offset": 0
}
```

**Response:**
```json
{
  "data": {
    "audit_log": [
      {
        "id": 2501,
        "event_type": "feature_toggle",
        "actor": "admin",
        "payload": {"feature": "farming", "enabled": true},
        "created_at": "2025-10-31 10:00:00"
      }
    ],
    "pagination": {
      "total": 5000,
      "limit": 500,
      "offset": 0
    }
  }
}
```

---

#### `POST /v1/monitoring/getPerformanceMetrics`
Get performance metrics for last 24 hours.

**Request:**
```json
{
  "lang": "en-US"
}
```

**Response:**
```json
{
  "data": {
    "total_decisions_24h": 75000,
    "avg_time_ms": 48.75,
    "min_time_ms": 12.5,
    "max_time_ms": 523.8,
    "p95_time_ms": 125.4,
    "p99_time_ms": 287.6,
    "success_rate": 97.3,
    "successful_decisions": 73000,
    "error_count": 2000
  }
}
```

---

### Feature Management

#### `POST /v1/featureManagement/listFeatures`
Get all server-level feature flags.

**Request:**
```json
{
  "lang": "en-US"
}
```

**Response:**
```json
{
  "data": {
    "features": [
      {
        "id": 1,
        "flag_key": "farming",
        "enabled": true,
        "scope": "server",
        "is_locked": false,
        "payload": {"max_farmlists": 10}
      },
      {
        "id": 2,
        "flag_key": "building",
        "enabled": true,
        "scope": "server",
        "is_locked": false
      }
    ]
  }
}
```

---

#### `POST /v1/featureManagement/getFeature`
Get specific feature flag details.

**Request:**
```json
{
  "lang": "en-US",
  "flagKey": "farming"
}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "flag_key": "farming",
    "enabled": true,
    "scope": "server",
    "is_locked": false,
    "payload": {"max_farmlists": 10},
    "created_at": "2025-10-01 00:00:00",
    "updated_at": "2025-10-30 10:00:00"
  }
}
```

---

#### `POST /v1/featureManagement/toggleFeature`
Toggle feature flag on/off.

**Request:**
```json
{
  "lang": "en-US",
  "flag_key": "farming",
  "enabled": false,
  "adminId": 1
}
```

**Response:**
```json
{
  "data": {
    "flag_key": "farming",
    "enabled": false,
    "scope": "server",
    "updated_at": "2025-10-31 12:00:00"
  }
}
```

**Validation**:
- Feature must not be locked
- Admin permissions may be required

---

#### `POST /v1/featureManagement/updateConfig`
Update feature flag configuration.

**Request:**
```json
{
  "lang": "en-US",
  "flagKey": "farming",
  "payload_json": {
    "max_farmlists": 15,
    "cooldown_seconds": 300
  }
}
```

**Response:**
```json
{
  "data": {
    "flag_key": "farming",
    "payload": {
      "max_farmlists": 15,
      "cooldown_seconds": 300
    },
    "updated_at": "2025-10-31 12:00:00"
  }
}
```

**Validation**:
- Feature must not be locked

---

#### `POST /v1/featureManagement/resetDefaults`
Reset all non-locked features to default state.

**Request:**
```json
{
  "lang": "en-US"
}
```

**Response:**
```json
{
  "data": {
    "reset_count": 10,
    "message": "Reset 10 non-locked feature flags to default state (enabled)"
  }
}
```

---

### NPC Management

#### `POST /v1/npcManagement/listNPCs`
Get list of NPCs with filters.

**Request:**
```json
{
  "lang": "en-US",
  "world_id": 1,
  "tribe": "romans",
  "difficulty": "medium",
  "limit": 100,
  "offset": 0
}
```

**Response:**
```json
{
  "data": {
    "npcs": [
      {
        "id": 2601,
        "username": "NPC_Roman_01",
        "tribe": "romans",
        "world_id": 1,
        "is_active": 1,
        "difficulty": "medium",
        "personality": "balanced",
        "decision_frequency_seconds": 300,
        "llm_ratio": 0.2,
        "created_at": "2025-10-30 10:00:00"
      }
    ],
    "pagination": {
      "total": 250,
      "limit": 100,
      "offset": 0
    }
  }
}
```

**Validation**:
- `tribe`: One of: romans, gauls, teutons
- `difficulty`: One of: easy, medium, hard, expert

---

#### `POST /v1/npcManagement/getNPC`
Get detailed NPC information.

**Request:**
```json
{
  "lang": "en-US",
  "npcId": 2601
}
```

**Response:**
```json
{
  "data": {
    "id": 2601,
    "username": "NPC_Roman_01",
    "tribe": "romans",
    "world_id": 1,
    "is_active": 1,
    "difficulty": "medium",
    "personality": "balanced",
    "llm_ratio": 0.2,
    "villages": [
      {
        "id": 2701,
        "name": "NPC Village",
        "x": 25,
        "y": -15,
        "population": 245
      }
    ],
    "llm_bias": {
      "aggressive": 0.3,
      "defensive": 0.5,
      "economic": 0.2
    }
  }
}
```

---

#### `POST /v1/npcManagement/createNPC`
Create a new NPC.

**Request:**
```json
{
  "lang": "en-US",
  "worldId": 1,
  "tribe": "romans",
  "difficulty": "medium",
  "personality": "balanced",
  "x": 25,
  "y": -15
}
```

**Response:**
```json
{
  "data": {
    "npc_id": 2602,
    "username": "NPC_Roman_02",
    "village_id": 2702,
    "location": {"x": 25, "y": -15}
  }
}
```

**Validation**:
- `tribe`: One of: romans, gauls, teutons
- `difficulty`: One of: easy, medium, hard, expert
- Coordinates must be valid and unoccupied

---

#### `POST /v1/npcManagement/updateConfig`
Update NPC AI configuration.

**Request:**
```json
{
  "lang": "en-US",
  "npcId": 2601,
  "difficulty": "hard",
  "personality": "aggressive",
  "decision_frequency_seconds": 180,
  "llm_ratio": 0.4
}
```

**Response:**
```json
{
  "data": {
    "npc_id": 2601
  },
  "message": "NPC config updated successfully"
}
```

**Validation**:
- `decision_frequency_seconds`: Minimum 60 seconds
- `llm_ratio`: 0.0 to 1.0

---

#### `POST /v1/npcManagement/activate`
Activate an NPC.

**Request:**
```json
{
  "lang": "en-US",
  "npcId": 2601
}
```

**Response:**
```json
{
  "data": {
    "npc_id": 2601
  },
  "message": "NPC activated successfully"
}
```

---

#### `POST /v1/npcManagement/deactivate`
Deactivate an NPC.

**Request:**
```json
{
  "lang": "en-US",
  "npcId": 2601
}
```

**Response:**
```json
{
  "data": {
    "npc_id": 2601
  },
  "message": "NPC deactivated successfully"
}
```

---

#### `POST /v1/npcManagement/deleteNPC`
Permanently delete an NPC and all associated data.

**Request:**
```json
{
  "lang": "en-US",
  "npcId": 2601
}
```

**Response:**
```json
{
  "data": {
    "npc_id": 2601
  },
  "message": "NPC deleted successfully"
}
```

**Warning**: This is a destructive operation. Deletes NPC, villages, AI configs, and spawn records.

---

#### `POST /v1/npcManagement/getNPCStatistics`
Get NPC statistics and breakdown.

**Request:**
```json
{
  "lang": "en-US"
}
```

**Response:**
```json
{
  "data": {
    "total_npcs": 250,
    "active_npcs": 200,
    "inactive_npcs": 50,
    "by_tribe": {
      "romans": 90,
      "gauls": 80,
      "teutons": 80
    },
    "by_difficulty": {
      "easy": 60,
      "medium": 100,
      "hard": 70,
      "expert": 20
    },
    "by_personality": {
      "balanced": 150,
      "aggressive": 50,
      "defensive": 30,
      "economic": 20
    }
  }
}
```

---

### Spawn Management

#### `POST /v1/spawnManagement/getBatches`
Get spawn batches for a world.

**Request:**
```json
{
  "lang": "en-US",
  "worldId": 1
}
```

**Response:**
```json
{
  "data": {
    "batches": [
      {
        "id": 2801,
        "world_id": 1,
        "batch_number": 1,
        "npc_count": 50,
        "status": "pending",
        "scheduled_at": "2025-11-01 00:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/spawnManagement/executeBatch`
Execute a spawn batch immediately.

**Request:**
```json
{
  "lang": "en-US",
  "batchId": 2801
}
```

**Response:**
```json
{
  "data": {
    "batch_id": 2801,
    "npcs_spawned": 50,
    "status": "completed"
  },
  "message": "Batch executed successfully"
}
```

---

#### `POST /v1/spawnManagement/pauseSpawning`
Pause all pending spawns for a world.

**Request:**
```json
{
  "lang": "en-US",
  "worldId": 1
}
```

**Response:**
```json
{
  "data": {
    "world_id": 1,
    "batches_paused": 5
  },
  "message": "Spawning paused for world 1"
}
```

---

#### `POST /v1/spawnManagement/resumeSpawning`
Resume paused spawning.

**Request:**
```json
{
  "lang": "en-US",
  "worldId": 1
}
```

**Response:**
```json
{
  "data": {
    "world_id": 1,
    "batches_resumed": 5
  },
  "message": "Spawning resumed for world 1"
}
```

---

#### `POST /v1/spawnManagement/getPendingBatches`
Get all pending spawn batches across worlds.

**Request:**
```json
{
  "lang": "en-US"
}
```

**Response:**
```json
{
  "data": {
    "pending_batches": [
      {
        "id": 2801,
        "world_id": 1,
        "world_key": "s1",
        "world_name": "Server 1",
        "batch_number": 1,
        "scheduled_at": "2025-11-01 00:00:00"
      }
    ],
    "count": 1
  }
}
```

---

#### `POST /v1/spawnManagement/retryBatch`
Retry a failed batch.

**Request:**
```json
{
  "lang": "en-US",
  "batchId": 2801
}
```

**Response:**
```json
{
  "data": {
    "batch_id": 2801,
    "status": "completed"
  },
  "message": "Batch retry executed successfully"
}
```

---

### Spawn Preset Management

#### `POST /v1/spawnPreset/listPresets`
Get all spawn presets.

**Request:**
```json
{
  "lang": "en-US"
}
```

**Response:**
```json
{
  "data": {
    "presets": [
      {
        "id": 1,
        "preset_key": "small_world",
        "name": "Small World",
        "description": "250 NPCs with balanced distribution",
        "total_npcs": 250,
        "config": {
          "romans": 90,
          "gauls": 80,
          "teutons": 80,
          "difficulty_mix": {
            "easy": 0.25,
            "medium": 0.50,
            "hard": 0.20,
            "expert": 0.05
          }
        }
      }
    ]
  }
}
```

---

#### `POST /v1/spawnPreset/getPreset`
Get specific preset details.

**Request:**
```json
{
  "lang": "en-US",
  "presetId": 1
}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "preset_key": "small_world",
    "name": "Small World",
    "total_npcs": 250,
    "config": {...}
  }
}
```

---

#### `POST /v1/spawnPreset/createPreset`
Create a new spawn preset.

**Request:**
```json
{
  "lang": "en-US",
  "preset_key": "custom_world",
  "preset_name": "Custom World",
  "description": "Custom configuration",
  "total_npcs": 300,
  "config_json": {
    "romans": 100,
    "gauls": 100,
    "teutons": 100
  }
}
```

**Response:**
```json
{
  "data": {
    "preset_id": 2,
    "preset_key": "custom_world"
  },
  "message": "Preset created successfully"
}
```

---

#### `POST /v1/spawnPreset/updatePreset`
Update existing preset.

**Request:**
```json
{
  "lang": "en-US",
  "presetId": 2,
  "preset_name": "Updated Custom World",
  "total_npcs": 350
}
```

**Response:**
```json
{
  "data": {
    "preset_id": 2
  },
  "message": "Preset updated successfully"
}
```

---

#### `POST /v1/spawnPreset/deletePreset`
Delete a spawn preset.

**Request:**
```json
{
  "lang": "en-US",
  "presetId": 2
}
```

**Response:**
```json
{
  "data": {
    "preset_id": 2
  },
  "message": "Preset deleted successfully"
}
```

**Validation**: Preset cannot be deleted if it's in use by any world

---

### Server Generator

#### `POST /v1/serverGenerator/create`
Create a new game world.

**Request:**
```json
{
  "lang": "en-US",
  "world_key": "s2",
  "world_name": "Server 2",
  "database_name": "s1_s2",
  "speed": 3.0,
  "spawn_preset_key": "small_world",
  "placement_algorithm": "quadrant_balanced",
  "max_npcs": 250
}
```

**Response:**
```json
{
  "data": {
    "world_id": 2,
    "world_key": "s2",
    "database_created": true,
    "spawn_batches_scheduled": 5
  },
  "message": "World created successfully"
}
```

---

#### `POST /v1/serverGenerator/preview`
Preview spawn plan without creating world.

**Request:**
```json
{
  "lang": "en-US",
  "spawn_preset_key": "small_world",
  "placement_algorithm": "quadrant_balanced"
}
```

**Response:**
```json
{
  "data": {
    "total_npcs": 250,
    "distribution": {
      "quadrant_1": 63,
      "quadrant_2": 62,
      "quadrant_3": 63,
      "quadrant_4": 62
    },
    "tribe_breakdown": {
      "romans": 90,
      "gauls": 80,
      "teutons": 80
    }
  },
  "message": "Spawn plan preview generated"
}
```

---

#### `POST /v1/serverGenerator/listWorlds`
Get list of game worlds.

**Request:**
```json
{
  "lang": "en-US",
  "status": "active"
}
```

**Response:**
```json
{
  "data": {
    "worlds": [
      {
        "id": 1,
        "world_key": "s1",
        "world_name": "Server 1",
        "status": "active",
        "created_at": "2025-10-01 00:00:00"
      }
    ]
  }
}
```

---

#### `POST /v1/serverGenerator/getStatistics`
Get world statistics.

**Request:**
```json
{
  "lang": "en-US",
  "worldId": 1
}
```

**Response:**
```json
{
  "data": {
    "world_id": 1,
    "total_players": 100,
    "total_npcs": 250,
    "total_villages": 450,
    "total_population": 125000
  },
  "message": "World statistics retrieved successfully"
}
```

---

#### `POST /v1/serverGenerator/deleteWorld`
Delete a game world.

**Request:**
```json
{
  "lang": "en-US",
  "worldId": 2
}
```

**Response:**
```json
{
  "data": {
    "world_id": 2,
    "deleted": true
  },
  "message": "World deleted successfully"
}
```

**Warning**: This is a destructive operation. Deletes all world data including players, NPCs, and villages.

---

## Common Response Format

### Success Response
```json
{
  "success": true,
  "status": "success",
  "message": "Operation completed successfully",
  "data": {
    ...
  }
}
```

### Error Response
```json
{
  "success": false,
  "status": "error",
  "code": 400,
  "message": "Invalid input",
  "error_code": "INVALID_INPUT",
  "error": {
    "errorType": "MissingParameterException",
    "errorMsg": "Missing required parameter: villageId",
    "file": "/path/to/file.php",
    "line": 42
  }
}
```

### HTTP Status Codes
- **200 OK**: Successful request
- **400 Bad Request**: Invalid input or validation error
- **403 Forbidden**: Feature disabled or insufficient permissions
- **404 Not Found**: Resource not found
- **405 Method Not Allowed**: Invalid HTTP method
- **500 Internal Server Error**: Server-side error

### Error Codes
- `INVALID_INPUT`: Invalid or missing parameters
- `FEATURE_DISABLED`: Required feature is not enabled
- `NOT_FOUND`: Requested resource does not exist
- `UNAUTHORIZED`: Authentication required or failed
- `PERMISSION_DENIED`: Insufficient permissions
- `RATE_LIMIT_EXCEEDED`: Too many requests
- `VALIDATION_ERROR`: Data validation failed
- `LOCKED_FLAG`: Feature flag is locked and cannot be modified
- `PRESET_IN_USE`: Spawn preset is in use and cannot be deleted

---

## Rate Limits & Validation

### Rate Limiting
Currently, there are no explicit rate limits enforced at the API level. However, certain actions have natural rate limits:

- **Farming raids**: Minimum 5 minutes (300 seconds) between raids on same target
- **NPC decision frequency**: Configurable per NPC (minimum 60 seconds)
- **Away mode duration**: 1-168 hours (1 hour to 1 week)

### Common Validation Rules

**Player Context**:
- `playerId`/`player_id`/`userId`: Must be valid integer
- `playerType`/`player_type`: One of: human, npc

**Coordinates**:
- `x`, `y`: Valid game world coordinates
- Range typically: -400 to +400

**Tribe Validation**:
- Valid values: romans, gauls, teutons

**Difficulty Levels**:
- Valid values: easy, medium, hard, expert

**Strategy Types**:
- Building: economic, military, balanced
- Logistics: equal, priority_capital, by_need
- Defense: immediate, delayed, none

**Feature Gates**:
All automation endpoints check if the corresponding feature is enabled:
- `building`, `training`, `farming`, `defense`, `logistics`, `away_mode`

**Language Parameter**:
- Required for all non-config endpoints
- Supports: en-US, de-DE, fa-IR, international, en, ir, fa, us
- Invalid codes are automatically mapped to valid locales

---

## Authentication Flow

1. **Initial Config**: `POST /v1/loadConfig` - Get initial settings
2. **CSRF Token**: `POST /v1/token` - Get security token
3. **Login**: `POST /v1/auth/login` - Authenticate and get redirect URL
4. **Game Requests**: Include `lang` and player context in all subsequent requests

---

## Feature Gate System

The API uses a feature gate system to control access to automation features:

**Server-Level Features**:
- Managed via `/v1/featureManagement/*` endpoints
- Can be locked to prevent modification
- Control availability across entire server

**Player-Level Access**:
- Checked via `requireFeature()` method
- Returns 403 Forbidden if feature is disabled
- Error code: `FEATURE_DISABLED`

**Available Features**:
- `building` - Building automation
- `training` - Troop training automation
- `farming` - Farmlist execution
- `defense` - Defensive automation
- `logistics` - Resource transport automation
- `away_mode` - Full automation mode

---

## Best Practices

1. **Always include `lang` parameter** for non-config endpoints
2. **Provide player context** (`playerId`) for game-related operations
3. **Check feature availability** before attempting automation operations
4. **Handle error responses** gracefully with proper error codes
5. **Use pagination** for list endpoints with large datasets
6. **Validate input** before sending requests to avoid validation errors
7. **Monitor rate limits** to avoid overwhelming the system
8. **Use CSRF tokens** for secure state-changing operations

---

## Example cURL Requests

### Login
```bash
curl -X POST https://your-domain.com/sections/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "gameWorldId": 1,
    "usernameOrEmail": "player@example.com",
    "password": "SecurePass123",
    "lowResMode": false
  }'
```

### Get Village List
```bash
curl -X POST https://your-domain.com/sections/api/v1/village/getVillageList \
  -H "Content-Type: application/json" \
  -d '{
    "lang": "en-US",
    "playerId": 123
  }'
```

### Execute Farmlist
```bash
curl -X POST https://your-domain.com/sections/api/v1/farming/executeFarmlist \
  -H "Content-Type: application/json" \
  -d '{
    "lang": "en-US",
    "playerId": 123,
    "farmlistId": 1801
  }'
```

### Enable Away Mode
```bash
curl -X POST https://your-domain.com/sections/api/v1/awayMode/enableAwayMode \
  -H "Content-Type: application/json" \
  -d '{
    "lang": "en-US",
    "playerId": 123,
    "duration": 24,
    "intensity": "medium"
  }'
```

---

## Changelog

### Version 1.0 (October 2025)
- Initial API documentation
- Complete endpoint catalog for core game and automation systems
- Feature gate system implementation
- NPC management endpoints
- World generation and spawn management
- Monitoring and analytics endpoints
