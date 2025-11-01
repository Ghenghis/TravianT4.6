# API Reference - TravianT4.6

## Overview

The TravianT4.6 API provides RESTful endpoints for managing game servers, NPC spawning, AI automation, and system monitoring. All endpoints return JSON responses and support CSRF protection.

**Base URL:**
```
Production: https://yourdomain.com/v1
Development: http://localhost/v1
```

**API Version:** v1

**Content Type:** `application/json`

**Authentication:** Session-based with CSRF token protection

---

## Authentication

### CSRF Token

All POST/PUT/DELETE requests require a valid CSRF token.

**Endpoint:** `GET /v1/token`

**Description:** Retrieve CSRF token for subsequent requests

**Response:**
```json
{
  "success": true,
  "data": {
    "csrf_token": "a1b2c3d4e5f6..."
  },
  "message": "CSRF token generated"
}
```

**Usage Example:**
```bash
# Get CSRF token
TOKEN=$(curl -s http://localhost/v1/token | jq -r '.data.csrf_token')

# Use token in POST request
curl -X POST http://localhost/v1/server/generate \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{"world_key":"testworld",...}'
```

### Session Management

Sessions are managed via HTTP cookies. Login endpoints set session cookies automatically.

---

## Rate Limiting

The API implements three-tier rate limiting:

| Tier | Limit | Scope | Example Endpoints |
|------|-------|-------|-------------------|
| **Global** | 100 requests/second | All endpoints | All |
| **API** | 50 requests/second | Data endpoints | /v1/server/*, /v1/spawn/* |
| **Auth** | 5 requests/10 minutes | Authentication | /v1/auth/login |

**Rate Limit Headers:**
```
X-RateLimit-Limit: 50
X-RateLimit-Remaining: 49
X-RateLimit-Reset: 1698765432
```

**Rate Limit Exceeded Response:**
```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded. Try again in 60 seconds."
  }
}
```

---

## Response Format

### Success Response

```json
{
  "success": true,
  "data": {
    // Response data
  },
  "message": "Operation successful"
}
```

### Error Response

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {
      // Additional error context (optional)
    }
  }
}
```

---

## API Controllers

### 1. ServerGeneratorCtrl

**Purpose:** Manage game world creation, deletion, and statistics.

**Base Path:** `/v1/server`

#### Endpoints

##### POST /v1/server/generate

Create a new game world with specified configuration.

**Request Body:**
```json
{
  "world_key": "testworld",
  "world_name": "Test World",
  "database_name": "s1_testworld",
  "speed": 100.0,
  "spawn_preset_key": "npc_army_500",
  "placement_algorithm": "quadrant_balanced",
  "max_npcs": 500
}
```

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `world_key` | string | Yes | Unique identifier for world |
| `world_name` | string | Yes | Display name for world |
| `database_name` | string | No | MySQL database name (default: s1_{world_key}) |
| `speed` | float | No | Game speed multiplier (default: 1.0) |
| `spawn_preset_key` | string | Yes | Preset key from spawn_presets table |
| `placement_algorithm` | string | No | Placement algorithm (default: quadrant_balanced) |
| `max_npcs` | integer | No | Maximum NPCs to spawn (default: 250) |

**Response:**
```json
{
  "success": true,
  "data": {
    "world_id": 42,
    "world_key": "testworld",
    "database_name": "s1_testworld",
    "status": "creating",
    "batches_created": 10,
    "total_npcs_planned": 500
  },
  "message": "World created successfully"
}
```

**Curl Example:**
```bash
curl -X POST http://localhost/v1/server/generate \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{
    "world_key": "testworld",
    "world_name": "Test World",
    "speed": 100.0,
    "spawn_preset_key": "npc_army_500",
    "max_npcs": 500
  }'
```

**Rate Limit:** API tier (50/s)

---

##### GET /v1/server/worlds

List all game worlds with optional filtering.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by status: `active`, `creating`, `archived` |

**Response:**
```json
{
  "success": true,
  "data": {
    "worlds": [
      {
        "id": 1,
        "world_key": "testworld",
        "world_name": "Test World",
        "database_name": "s1_testworld",
        "speed": 100.0,
        "status": "active",
        "npc_count": 500,
        "created_at": "2025-10-30T12:00:00Z"
      }
    ]
  },
  "message": "Worlds retrieved successfully"
}
```

**Curl Example:**
```bash
curl http://localhost/v1/server/worlds?status=active
```

**Rate Limit:** API tier (50/s)

---

##### GET /v1/server/statistics

Get detailed statistics for a specific world.

**Request Body:**
```json
{
  "worldId": 42
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "world_id": 42,
    "total_npcs": 500,
    "active_npcs": 487,
    "total_villages": 1245,
    "npc_villages": 500,
    "player_villages": 745,
    "avg_npc_resources": {
      "wood": 45000,
      "clay": 42000,
      "iron": 38000,
      "crop": 35000
    },
    "performance_metrics": {
      "avg_decision_time_ms": 145,
      "llm_decisions_today": 234,
      "rule_based_decisions_today": 4532
    }
  },
  "message": "World statistics retrieved successfully"
}
```

**Curl Example:**
```bash
curl -X POST http://localhost/v1/server/statistics \
  -H "Content-Type: application/json" \
  -d '{"worldId": 42}'
```

**Rate Limit:** API tier (50/s)

---

##### DELETE /v1/server/delete

Delete a game world and its database.

**Request Body:**
```json
{
  "worldId": 42,
  "confirm": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "world_id": 42,
    "deleted_at": "2025-10-30T15:30:00Z"
  },
  "message": "World deleted successfully"
}
```

**Curl Example:**
```bash
curl -X DELETE http://localhost/v1/server/delete \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{"worldId": 42, "confirm": true}'
```

**Rate Limit:** API tier (50/s)

---

**Additional Endpoints:**
- `POST /v1/server/preview` - Preview spawn plan before creation
- `POST /v1/server/archive` - Archive a world
- `POST /v1/server/restore` - Restore archived world
- `GET /v1/server/config` - Get world configuration
- `PUT /v1/server/config` - Update world configuration

*(See source code for detailed parameter specifications)*

---

### 2. SpawnManagementCtrl

**Purpose:** Manage NPC spawning batches and schedules.

**Base Path:** `/v1/spawn`

#### Endpoints

##### GET /v1/spawn/batches

Get all spawn batches for a world.

**Request Body:**
```json
{
  "worldId": 42
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "batches": [
      {
        "id": 1,
        "world_id": 42,
        "batch_number": 1,
        "npc_count": 50,
        "status": "completed",
        "scheduled_at": "2025-10-30T12:00:00Z",
        "executed_at": "2025-10-30T12:05:32Z"
      },
      {
        "id": 2,
        "batch_number": 2,
        "npc_count": 50,
        "status": "pending",
        "scheduled_at": "2025-10-30T12:30:00Z"
      }
    ]
  },
  "message": "Spawn batches retrieved successfully"
}
```

**Curl Example:**
```bash
curl -X GET http://localhost/v1/spawn/batches \
  -H "Content-Type: application/json" \
  -d '{"worldId": 42}'
```

**Rate Limit:** API tier (50/s)

---

##### POST /v1/spawn/execute

Execute a specific spawn batch manually.

**Request Body:**
```json
{
  "batchId": 123
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "batch_id": 123,
    "npcs_spawned": 50,
    "execution_time_ms": 2345,
    "status": "completed"
  },
  "message": "Batch executed successfully"
}
```

**Curl Example:**
```bash
curl -X POST http://localhost/v1/spawn/execute \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{"batchId": 123}'
```

**Rate Limit:** API tier (50/s)

---

##### POST /v1/spawn/pause

Pause all pending spawn batches for a world.

**Request Body:**
```json
{
  "worldId": 42
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "world_id": 42,
    "batches_paused": 8
  },
  "message": "Spawning paused for world 42"
}
```

**Curl Example:**
```bash
curl -X POST http://localhost/v1/spawn/pause \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{"worldId": 42}'
```

**Rate Limit:** API tier (50/s)

---

**Additional Endpoints:**
- `POST /v1/spawn/resume` - Resume paused spawning
- `POST /v1/spawn/plan` - Generate spawn plan
- `GET /v1/spawn/schedule` - Get spawn schedule

*(See source code for detailed parameter specifications)*

---

### 3. SpawnPresetCtrl

**Purpose:** Manage spawn presets for NPC configurations.

**Base Path:** `/v1/spawn/presets`

#### Endpoints

##### GET /v1/spawn/presets

List all available spawn presets.

**Response:**
```json
{
  "success": true,
  "data": {
    "presets": [
      {
        "preset_key": "npc_army_500",
        "name": "NPC Army - 500 NPCs",
        "description": "Balanced army distribution",
        "npc_count": 500,
        "tribe_distribution": {
          "romans": 200,
          "gauls": 150,
          "teutons": 150
        },
        "difficulty_distribution": {
          "easy": 200,
          "medium": 200,
          "hard": 75,
          "expert": 25
        }
      }
    ]
  },
  "message": "Presets retrieved successfully"
}
```

**Curl Example:**
```bash
curl http://localhost/v1/spawn/presets
```

**Rate Limit:** API tier (50/s)

---

##### POST /v1/spawn/presets

Create a new spawn preset.

**Request Body:**
```json
{
  "preset_key": "custom_preset",
  "name": "Custom Preset",
  "description": "Custom NPC configuration",
  "npc_count": 300,
  "tribe_distribution": {
    "romans": 100,
    "gauls": 100,
    "teutons": 100
  },
  "difficulty_distribution": {
    "easy": 150,
    "medium": 100,
    "hard": 40,
    "expert": 10
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "preset_id": 5,
    "preset_key": "custom_preset",
    "created_at": "2025-10-30T14:30:00Z"
  },
  "message": "Preset created successfully"
}
```

**Curl Example:**
```bash
curl -X POST http://localhost/v1/spawn/presets \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{...}'
```

**Rate Limit:** API tier (50/s)

---

**Additional Endpoints:**
- `GET /v1/spawn/presets/{key}` - Get specific preset
- `PUT /v1/spawn/presets/{key}` - Update preset
- `DELETE /v1/spawn/presets/{key}` - Delete preset

*(See source code for detailed parameter specifications)*

---

### 4. NPCManagementCtrl

**Purpose:** Manage individual NPCs and their configurations.

**Base Path:** `/v1/npc`

#### Endpoints

##### GET /v1/npc/list

List NPCs with filtering and pagination.

**Request Body:**
```json
{
  "world_id": 42,
  "tribe": "romans",
  "difficulty": "medium",
  "limit": 100,
  "offset": 0
}
```

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `world_id` | integer | No | Filter by world ID |
| `tribe` | string | No | Filter by tribe: romans, gauls, teutons |
| `difficulty` | string | No | Filter by difficulty: easy, medium, hard, expert |
| `limit` | integer | No | Results per page (default: 100) |
| `offset` | integer | No | Pagination offset (default: 0) |

**Response:**
```json
{
  "success": true,
  "data": {
    "npcs": [
      {
        "id": 1001,
        "username": "NPC_Roman_001",
        "tribe": "romans",
        "world_id": 42,
        "is_active": true,
        "difficulty": "medium",
        "personality": "aggressive",
        "decision_frequency_seconds": 180,
        "llm_ratio": 0.05,
        "created_at": "2025-10-30T10:00:00Z"
      }
    ],
    "pagination": {
      "total": 500,
      "limit": 100,
      "offset": 0
    }
  },
  "message": "NPCs retrieved successfully"
}
```

**Curl Example:**
```bash
curl -X GET http://localhost/v1/npc/list \
  -H "Content-Type: application/json" \
  -d '{"world_id": 42, "tribe": "romans", "limit": 50}'
```

**Rate Limit:** API tier (50/s)

---

##### GET /v1/npc/get

Get detailed information about a specific NPC.

**Request Body:**
```json
{
  "npcId": 1001
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1001,
    "username": "NPC_Roman_001",
    "tribe": "romans",
    "world_id": 42,
    "is_active": true,
    "difficulty": "medium",
    "personality": "aggressive",
    "decision_frequency_seconds": 180,
    "llm_ratio": 0.05,
    "llm_bias": {
      "offensive": 0.7,
      "defensive": 0.3
    },
    "villages": [
      {
        "id": 501,
        "name": "Village 001",
        "x": 10,
        "y": -5,
        "population": 450,
        "resources": {
          "wood": 5000,
          "clay": 4800,
          "iron": 4200,
          "crop": 3500
        }
      }
    ],
    "statistics": {
      "total_decisions": 2345,
      "llm_decisions": 117,
      "rule_based_decisions": 2228,
      "avg_decision_time_ms": 152,
      "last_decision_at": "2025-10-30T14:25:12Z"
    }
  },
  "message": "NPC retrieved successfully"
}
```

**Curl Example:**
```bash
curl -X GET http://localhost/v1/npc/get \
  -H "Content-Type: application/json" \
  -d '{"npcId": 1001}'
```

**Rate Limit:** API tier (50/s)

---

##### PUT /v1/npc/update

Update NPC configuration.

**Request Body:**
```json
{
  "npcId": 1001,
  "difficulty": "hard",
  "decision_frequency_seconds": 120,
  "llm_ratio": 0.10,
  "llm_bias": {
    "offensive": 0.8,
    "defensive": 0.2
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "npc_id": 1001,
    "updated_fields": ["difficulty", "decision_frequency_seconds", "llm_ratio", "llm_bias"],
    "updated_at": "2025-10-30T15:00:00Z"
  },
  "message": "NPC updated successfully"
}
```

**Curl Example:**
```bash
curl -X PUT http://localhost/v1/npc/update \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{...}'
```

**Rate Limit:** API tier (50/s)

---

**Additional Endpoints:**
- `POST /v1/npc/create` - Create NPC manually
- `DELETE /v1/npc/delete` - Delete NPC
- `POST /v1/npc/activate` - Activate NPC
- `POST /v1/npc/deactivate` - Deactivate NPC

*(See source code for detailed parameter specifications)*

---

### 5. FeatureManagementCtrl

**Purpose:** Manage feature flags for gradual rollout and A/B testing.

**Base Path:** `/v1/features`

#### Endpoints

##### GET /v1/features

List all feature flags.

**Response:**
```json
{
  "success": true,
  "data": {
    "features": [
      {
        "key": "llm_decision_engine",
        "name": "LLM Decision Engine",
        "enabled": true,
        "rollout_percentage": 100,
        "description": "Enable LLM-based decision making"
      },
      {
        "key": "advanced_combat",
        "name": "Advanced Combat System",
        "enabled": false,
        "rollout_percentage": 0,
        "description": "New combat calculation system"
      }
    ]
  },
  "message": "Features retrieved successfully"
}
```

**Curl Example:**
```bash
curl http://localhost/v1/features
```

**Rate Limit:** API tier (50/s)

---

**Additional Endpoints:**
- `PUT /v1/features/{key}` - Update feature flag
- `POST /v1/features/{key}/enable` - Enable feature
- `POST /v1/features/{key}/disable` - Disable feature

*(See source code for detailed parameter specifications)*

---

### 6. MonitoringCtrl

**Purpose:** Internal monitoring endpoints for health checks and metrics.

**Base Path:** `/v1/monitoring`

#### Endpoints

##### GET /v1/monitoring/health

System health check endpoint.

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "timestamp": "2025-10-30T15:45:00Z",
    "services": {
      "database": "healthy",
      "redis": "healthy",
      "workers": "healthy",
      "ollama": "healthy",
      "vllm": "healthy"
    },
    "uptime_seconds": 86400
  },
  "message": "System is healthy"
}
```

**Curl Example:**
```bash
curl http://localhost/v1/monitoring/health
```

**Rate Limit:** Global tier (100/s)

---

**Additional Endpoints:**
- `GET /v1/monitoring/metrics` - Get system metrics
- `GET /v1/monitoring/workers` - Get worker status

*(See source code for detailed parameter specifications)*

---

### 7. FarmingCtrl

**Purpose:** Manage NPC farming automation.

**Base Path:** `/v1/farming`

**Endpoints:**
- `GET /v1/farming/list` - List farming configurations
- `POST /v1/farming/create` - Create farming list
- `PUT /v1/farming/update` - Update farming list
- `DELETE /v1/farming/delete` - Delete farming list

*(Full endpoint documentation available in source code)*

---

### 8. BuildingCtrl

**Purpose:** Manage NPC building automation.

**Base Path:** `/v1/building`

**Endpoints:**
- `GET /v1/building/queue` - Get building queue
- `POST /v1/building/add` - Add to building queue
- `DELETE /v1/building/cancel` - Cancel building
- `GET /v1/building/templates` - Get building templates

*(Full endpoint documentation available in source code)*

---

### 9. TrainingCtrl

**Purpose:** Manage NPC troop training automation.

**Base Path:** `/v1/training`

**Endpoints:**
- `GET /v1/training/queue` - Get training queue
- `POST /v1/training/add` - Add to training queue
- `DELETE /v1/training/cancel` - Cancel training

*(Full endpoint documentation available in source code)*

---

### 10. DefenseCtrl

**Purpose:** Manage NPC defensive strategies.

**Base Path:** `/v1/defense`

**Endpoints:**
- `GET /v1/defense/strategies` - List defensive strategies
- `POST /v1/defense/set` - Set defensive strategy
- `GET /v1/defense/status` - Get current defensive status
- `POST /v1/defense/emergency` - Trigger emergency defense mode

*(Full endpoint documentation available in source code)*

---

### 11. LogisticsCtrl

**Purpose:** Manage NPC resource logistics and trade.

**Base Path:** `/v1/logistics`

**Endpoints:**
- `GET /v1/logistics/routes` - Get resource routes
- `POST /v1/logistics/transfer` - Create resource transfer
- `GET /v1/logistics/market` - Get market prices
- `POST /v1/logistics/trade` - Execute trade

*(Full endpoint documentation available in source code)*

---

### 12. AwayModeCtrl

**Purpose:** Manage NPC behavior during player absence.

**Base Path:** `/v1/away`

**Endpoints:**
- `POST /v1/away/enable` - Enable away mode
- `POST /v1/away/disable` - Disable away mode
- `GET /v1/away/status` - Get away mode status
- `PUT /v1/away/config` - Configure away behavior

*(Full endpoint documentation available in source code)*

---

## Error Codes

### HTTP Status Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request successful |
| 400 | Bad Request | Invalid request parameters |
| 401 | Unauthorized | Authentication required |
| 403 | Forbidden | Insufficient permissions or CSRF validation failed |
| 404 | Not Found | Resource not found |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error occurred |
| 503 | Service Unavailable | Service temporarily unavailable |

### Custom Error Codes

| Code | Description | HTTP Status |
|------|-------------|-------------|
| `INVALID_INPUT` | Invalid request parameters | 400 |
| `MISSING_PARAMETER` | Required parameter missing | 400 |
| `CSRF_VALIDATION_FAILED` | CSRF token invalid or missing | 403 |
| `RATE_LIMIT_EXCEEDED` | Rate limit exceeded | 429 |
| `NOT_FOUND` | Resource not found | 404 |
| `WORLD_CREATION_FAILED` | World creation error | 500 |
| `DATABASE_ERROR` | Database operation failed | 500 |
| `LLM_CONNECTION_ERROR` | Cannot connect to LLM service | 503 |
| `WORKER_UNAVAILABLE` | Background worker not available | 503 |

---

## Rate Limiting Details

### Global Rate Limit

**Limit:** 100 requests per second per IP

**Scope:** All API endpoints

**Headers:**
```
X-RateLimit-Global-Limit: 100
X-RateLimit-Global-Remaining: 95
X-RateLimit-Global-Reset: 1698765432
```

### API Rate Limit

**Limit:** 50 requests per second per IP

**Scope:** Data and management endpoints

**Applies to:**
- `/v1/server/*`
- `/v1/spawn/*`
- `/v1/npc/*`
- `/v1/features/*`

**Headers:**
```
X-RateLimit-API-Limit: 50
X-RateLimit-API-Remaining: 45
X-RateLimit-API-Reset: 1698765432
```

### Authentication Rate Limit

**Limit:** 5 requests per 10 minutes per IP

**Scope:** Authentication endpoints

**Applies to:**
- `/v1/auth/login`
- `/v1/auth/register`
- `/v1/auth/reset-password`

**Headers:**
```
X-RateLimit-Auth-Limit: 5
X-RateLimit-Auth-Remaining: 4
X-RateLimit-Auth-Reset: 1698765432
```

**Rate Limit Bypass:**

For internal services and monitoring, include header:
```
X-Internal-Service: true
```

*(Requires valid internal service token)*

---

## Pagination

Endpoints that return lists support pagination via `limit` and `offset` parameters.

**Request:**
```json
{
  "limit": 50,
  "offset": 100
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [...],
    "pagination": {
      "total": 500,
      "limit": 50,
      "offset": 100,
      "has_more": true
    }
  }
}
```

**Best Practices:**
- Default `limit`: 100
- Maximum `limit`: 1000
- Use `offset` for pagination
- Check `has_more` flag for additional pages

---

## Webhooks (Future)

Webhook support for event notifications is planned for v2.

**Planned Events:**
- `world.created`
- `world.deleted`
- `npc.spawned`
- `batch.completed`
- `system.alert`

---

## See Also

- [DEPLOYMENT-GUIDE.md](DEPLOYMENT-GUIDE.md) - Deployment instructions
- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture
- [DEVELOPER-ONBOARDING.md](DEVELOPER-ONBOARDING.md) - Developer guide
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - API troubleshooting
- [RATE-LIMITING.md](RATE-LIMITING.md) - Rate limiting details
- [SECURITY-HARDENING.md](SECURITY-HARDENING.md) - API security best practices
