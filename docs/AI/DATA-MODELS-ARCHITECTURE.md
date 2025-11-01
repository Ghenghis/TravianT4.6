# Data Models & Database Architecture - Complete Schema Framework

## 🎯 Overview

Enterprise-grade database architecture for NPC AI system supporting:
- 500+ concurrent NPCs
- Real-time decision making
- Historical analytics
- Relationship graphs
- Performance optimization

---

## 📊 Database Design Philosophy

### **Multi-Database Strategy**

```
Database Architecture
├── Global Database (MySQL/PostgreSQL)
│   ├── NPC Profiles & Configuration
│   ├── Player Relationships
│   ├── Alliance Data
│   └── Cross-World Analytics
│
├── World Databases (Per Game Server)
│   ├── Villages & Buildings
│   ├── Troops & Battles
│   ├── Resources & Production
│   └── Game State
│
├── Redis Cache
│   ├── Hot Data (LLM responses)
│   ├── Session State
│   ├── Real-time Metrics
│   └── Queue Management
│
└── Time-Series DB (InfluxDB/TimescaleDB)
    ├── Performance Metrics
    ├── Resource History
    ├── Battle Analytics
    └── Economic Trends
```

---

## 🗄️ Complete Schema Definitions

### **Global Database: NPC Management**

```sql
-- ============================================
-- NPC CORE TABLES
-- ============================================

CREATE TABLE npcs (
    id SERIAL PRIMARY KEY,
    uuid UUID DEFAULT gen_random_uuid() UNIQUE NOT NULL,
    name VARCHAR(100) UNIQUE NOT NULL,
    tribe VARCHAR(50) NOT NULL CHECK (tribe IN ('Romans', 'Gauls', 'Teutons')),
    personality VARCHAR(50) NOT NULL CHECK (
        personality IN ('aggressive', 'economic', 'balanced', 'diplomat', 'assassin')
    ),
    
    -- Behavior configuration (JSONB for flexibility)
    behavior_template JSONB NOT NULL,
    /*
    Example structure:
    {
        "aggression": 0.9,
        "economy": 0.3,
        "diplomacy": 0.2,
        "risk_tolerance": 0.85,
        "patience": 0.2,
        "building_priority": ["barracks", "stable", "workshop"],
        "goals": ["maximize_conquest", "build_army"],
        "adaptability": 0.7
    }
    */
    
    -- AI Configuration
    llm_temperature FLOAT DEFAULT 0.7 CHECK (llm_temperature BETWEEN 0.0 AND 2.0),
    decision_cache_ttl INTEGER DEFAULT 3600, -- seconds
    strategic_update_frequency INTEGER DEFAULT 86400, -- daily
    
    -- Status
    active BOOLEAN DEFAULT TRUE,
    paused BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    last_active TIMESTAMP DEFAULT NOW(),
    last_decision TIMESTAMP,
    
    -- Performance tracking
    total_decisions_made INTEGER DEFAULT 0,
    llm_calls_made INTEGER DEFAULT 0,
    battles_fought INTEGER DEFAULT 0,
    villages_founded INTEGER DEFAULT 0,
    
    -- Metadata
    version INTEGER DEFAULT 1, -- For behavior template versioning
    notes TEXT, -- Debug/admin notes
    
    CONSTRAINT valid_personality CHECK (
        (behavior_template->>'aggression')::float BETWEEN 0.0 AND 1.0
    )
);

CREATE INDEX idx_npcs_active ON npcs(active) WHERE active = TRUE;
CREATE INDEX idx_npcs_personality ON npcs(personality);
CREATE INDEX idx_npcs_last_active ON npcs(last_active DESC);

-- ============================================
-- NPC STATE PER WORLD
-- ============================================

CREATE TABLE npc_world_state (
    id SERIAL PRIMARY KEY,
    npc_id INTEGER REFERENCES npcs(id) ON DELETE CASCADE,
    world_id VARCHAR(50) NOT NULL, -- e.g., 'testworld', 'demo'
    
    -- Game state
    player_id INTEGER NOT NULL, -- ID in world database
    rank INTEGER,
    total_population INTEGER DEFAULT 0,
    village_count INTEGER DEFAULT 0,
    
    -- Resources (aggregated across all villages)
    total_wood INTEGER DEFAULT 0,
    total_clay INTEGER DEFAULT 0,
    total_iron INTEGER DEFAULT 0,
    total_wheat INTEGER DEFAULT 0,
    
    -- Military power
    offensive_power INTEGER DEFAULT 0,
    defensive_power INTEGER DEFAULT 0,
    total_troops INTEGER DEFAULT 0,
    
    -- Economic metrics
    production_rate_wood INTEGER DEFAULT 0, -- per hour
    production_rate_clay INTEGER DEFAULT 0,
    production_rate_iron INTEGER DEFAULT 0,
    production_rate_wheat INTEGER DEFAULT 0,
    
    -- Strategic state
    current_strategy VARCHAR(50), -- 'expansion', 'military', 'economic', 'diplomatic'
    strategic_goal TEXT,
    threat_level FLOAT DEFAULT 0.0 CHECK (threat_level BETWEEN 0.0 AND 1.0),
    
    -- Alliance
    alliance_id INTEGER,
    alliance_role VARCHAR(50), -- 'member', 'leader', 'co-leader', null
    
    -- Timestamps
    state_updated_at TIMESTAMP DEFAULT NOW(),
    last_login TIMESTAMP DEFAULT NOW(),
    
    PRIMARY KEY (npc_id, world_id),
    UNIQUE(world_id, player_id)
);

CREATE INDEX idx_npc_world_state_world ON npc_world_state(world_id);
CREATE INDEX idx_npc_world_state_rank ON npc_world_state(world_id, rank);
CREATE INDEX idx_npc_world_state_updated ON npc_world_state(state_updated_at DESC);

-- ============================================
-- RELATIONSHIP MANAGEMENT
-- ============================================

CREATE TABLE npc_relationships (
    id SERIAL PRIMARY KEY,
    npc_id INTEGER REFERENCES npcs(id) ON DELETE CASCADE,
    target_player_id INTEGER NOT NULL,
    world_id VARCHAR(50) NOT NULL,
    
    -- Relationship metrics
    trust_score INTEGER DEFAULT 0 CHECK (trust_score BETWEEN -100 AND 100),
    relationship_type VARCHAR(50) CHECK (
        relationship_type IN ('ally', 'enemy', 'neutral', 'trading_partner', 'vassal', 'overlord')
    ),
    
    -- Interaction history
    total_interactions INTEGER DEFAULT 0,
    last_interaction_type VARCHAR(100),
    last_interaction_at TIMESTAMP,
    
    -- Battle history
    battles_won_against INTEGER DEFAULT 0,
    battles_lost_against INTEGER DEFAULT 0,
    resources_raided_from INTEGER DEFAULT 0,
    resources_lost_to INTEGER DEFAULT 0,
    
    -- Diplomatic history
    treaties_active JSONB DEFAULT '[]'::jsonb,
    /*
    [
        {"type": "NAP", "start": "2025-01-01", "end": "2025-12-31"},
        {"type": "trade_agreement", "start": "2025-01-01", "terms": {...}}
    ]
    */
    
    messages_sent INTEGER DEFAULT 0,
    messages_received INTEGER DEFAULT 0,
    
    -- Intelligence gathered
    known_villages JSONB DEFAULT '[]'::jsonb,
    known_army_composition JSONB,
    activity_pattern JSONB,
    /*
    {
        "typical_online_hours": [0, 1, 8, 9, 18, 19, 20, 21],
        "timezone_estimate": "UTC-5",
        "activity_level": "high",
        "logins_per_day": 15
    }
    */
    
    -- Prediction model
    predicted_next_action VARCHAR(100),
    prediction_confidence FLOAT,
    predicted_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    
    UNIQUE(npc_id, target_player_id, world_id)
);

CREATE INDEX idx_relationships_npc ON npc_relationships(npc_id);
CREATE INDEX idx_relationships_target ON npc_relationships(world_id, target_player_id);
CREATE INDEX idx_relationships_type ON npc_relationships(relationship_type);
CREATE INDEX idx_relationships_trust ON npc_relationships(trust_score DESC);

-- ============================================
-- NPC MEMORY & LEARNING
-- ============================================

CREATE TABLE npc_memory (
    id BIGSERIAL PRIMARY KEY,
    npc_id INTEGER REFERENCES npcs(id) ON DELETE CASCADE,
    world_id VARCHAR(50) NOT NULL,
    
    -- Memory classification
    memory_type VARCHAR(100) NOT NULL CHECK (
        memory_type IN (
            'battle', 'trade', 'message', 'alliance_event',
            'strategic_decision', 'building_completed', 'village_founded',
            'treaty_signed', 'treaty_broken', 'player_interaction'
        )
    ),
    
    -- Memory content
    event_data JSONB NOT NULL,
    /*
    Battle example:
    {
        "battle_id": 12345,
        "opponent": "PlayerXYZ",
        "result": "victory",
        "my_losses": {"legionnaire": 50},
        "enemy_losses": {"praetorian": 80},
        "resources_gained": 15000,
        "prediction_accuracy": 0.92
    }
    */
    
    -- Importance scoring
    importance FLOAT DEFAULT 0.5 CHECK (importance BETWEEN 0.0 AND 1.0),
    emotional_weight FLOAT DEFAULT 0.0, -- How much this affects future decisions
    
    -- Temporal context
    occurred_at TIMESTAMP NOT NULL,
    game_day INTEGER, -- In-game day for easier querying
    
    -- Associated entities
    related_player_ids INTEGER[],
    related_village_ids INTEGER[],
    
    -- Learning outcomes
    lesson_learned TEXT,
    behavior_adjustment JSONB,
    /*
    {
        "aggression": +0.05,  // Increase aggression by 5%
        "reason": "Won battle with 90% of force remaining, can be more aggressive"
    }
    */
    
    -- Memory management
    accessed_count INTEGER DEFAULT 0,
    last_accessed TIMESTAMP,
    expires_at TIMESTAMP, -- For cleanup of old memories
    
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_memory_npc ON npc_memory(npc_id);
CREATE INDEX idx_memory_type ON npc_memory(memory_type);
CREATE INDEX idx_memory_importance ON npc_memory(importance DESC);
CREATE INDEX idx_memory_occurred ON npc_memory(occurred_at DESC);
CREATE INDEX idx_memory_expires ON npc_memory(expires_at) WHERE expires_at IS NOT NULL;

-- Partition by month for performance
CREATE TABLE npc_memory_2025_01 PARTITION OF npc_memory
    FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');

-- ============================================
-- DECISION LOG (For Analysis & Debugging)
-- ============================================

CREATE TABLE npc_decisions (
    id BIGSERIAL PRIMARY KEY,
    npc_id INTEGER REFERENCES npcs(id) ON DELETE CASCADE,
    world_id VARCHAR(50) NOT NULL,
    
    -- Decision context
    decision_type VARCHAR(100) NOT NULL CHECK (
        decision_type IN (
            'strategic', 'tactical', 'economic', 'diplomatic',
            'building', 'military', 'expansion'
        )
    ),
    
    situation_summary TEXT NOT NULL,
    
    -- Decision making
    used_llm BOOLEAN DEFAULT FALSE,
    llm_prompt TEXT,
    llm_response TEXT,
    llm_latency_ms INTEGER,
    
    rule_based_recommendation JSONB,
    final_decision JSONB NOT NULL,
    
    -- Execution
    executed BOOLEAN DEFAULT FALSE,
    execution_started_at TIMESTAMP,
    execution_completed_at TIMESTAMP,
    execution_result JSONB,
    
    -- Outcome tracking
    outcome_measured BOOLEAN DEFAULT FALSE,
    outcome_success BOOLEAN,
    outcome_data JSONB,
    
    -- Performance
    decision_latency_ms INTEGER,
    decision_confidence FLOAT,
    
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_decisions_npc ON npc_decisions(npc_id);
CREATE INDEX idx_decisions_type ON npc_decisions(decision_type);
CREATE INDEX idx_decisions_llm ON npc_decisions(used_llm) WHERE used_llm = TRUE;
CREATE INDEX idx_decisions_created ON npc_decisions(created_at DESC);

-- ============================================
-- ALLIANCE MANAGEMENT
-- ============================================

CREATE TABLE npc_alliances (
    id SERIAL PRIMARY KEY,
    world_id VARCHAR(50) NOT NULL,
    alliance_id INTEGER NOT NULL, -- ID in world database
    
    -- Alliance metadata
    name VARCHAR(100) NOT NULL,
    tag VARCHAR(10) NOT NULL,
    
    -- Composition
    total_members INTEGER DEFAULT 0,
    npc_members INTEGER DEFAULT 0, -- How many are NPCs
    npc_leader_id INTEGER REFERENCES npcs(id), -- If NPC is leader
    
    -- Strategy
    alliance_strategy VARCHAR(50), -- 'military', 'peaceful', 'trading', 'expansionist'
    diplomatic_stance JSONB,
    /*
    {
        "allied_alliances": [123, 456],
        "enemy_alliances": [789],
        "nap_alliances": [111, 222]
    }
    */
    
    -- Performance
    total_rank INTEGER,
    total_population INTEGER,
    total_villages INTEGER,
    
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    
    UNIQUE(world_id, alliance_id)
);

CREATE INDEX idx_alliances_world ON npc_alliances(world_id);
CREATE INDEX idx_alliances_leader ON npc_alliances(npc_leader_id) WHERE npc_leader_id IS NOT NULL;

-- ============================================
-- COMMUNICATION LOG
-- ============================================

CREATE TABLE npc_messages (
    id BIGSERIAL PRIMARY KEY,
    world_id VARCHAR(50) NOT NULL,
    
    -- Message metadata
    from_npc_id INTEGER REFERENCES npcs(id),
    to_player_id INTEGER NOT NULL,
    to_is_npc BOOLEAN DEFAULT FALSE,
    
    -- Message content
    subject VARCHAR(200),
    message_body TEXT NOT NULL,
    message_type VARCHAR(50) CHECK (
        message_type IN (
            'alliance_invitation', 'treaty_proposal', 'war_declaration',
            'trade_offer', 'diplomatic_message', 'threat', 'friendly_chat'
        )
    ),
    
    -- Generation metadata
    generated_by_llm BOOLEAN DEFAULT TRUE,
    llm_prompt TEXT,
    generation_time_ms INTEGER,
    
    -- Tracking
    sent_at TIMESTAMP DEFAULT NOW(),
    read_at TIMESTAMP,
    replied_at TIMESTAMP,
    reply_message_id BIGINT REFERENCES npc_messages(id),
    
    -- Outcome
    response_received BOOLEAN DEFAULT FALSE,
    response_positive BOOLEAN,
    objective_achieved BOOLEAN,
    
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_messages_from ON npc_messages(from_npc_id);
CREATE INDEX idx_messages_to ON npc_messages(world_id, to_player_id);
CREATE INDEX idx_messages_sent ON npc_messages(sent_at DESC);
CREATE INDEX idx_messages_unread ON npc_messages(read_at) WHERE read_at IS NULL;

-- ============================================
-- BATTLE ANALYTICS
-- ============================================

CREATE TABLE npc_battle_history (
    id BIGSERIAL PRIMARY KEY,
    world_id VARCHAR(50) NOT NULL,
    npc_id INTEGER REFERENCES npcs(id) ON DELETE CASCADE,
    
    -- Battle identification
    battle_id INTEGER, -- ID from world database
    battle_type VARCHAR(50) CHECK (
        battle_type IN ('attack', 'defense', 'reinforcement', 'raid')
    ),
    
    -- Participants
    attacker_id INTEGER NOT NULL,
    defender_id INTEGER NOT NULL,
    npc_role VARCHAR(50), -- 'attacker', 'defender', 'reinforcement'
    
    -- Battle details
    from_village_id INTEGER,
    to_village_id INTEGER,
    
    -- Forces
    my_troops_sent JSONB,
    my_troops_survived JSONB,
    enemy_troops_estimated JSONB,
    enemy_troops_killed JSONB,
    
    -- Outcome
    result VARCHAR(50), -- 'victory', 'defeat', 'draw'
    resources_gained INTEGER DEFAULT 0,
    resources_lost INTEGER DEFAULT 0,
    
    -- AI Performance
    prediction_made JSONB,
    /*
    {
        "predicted_result": "victory",
        "predicted_losses": {"legionnaire": 30},
        "confidence": 0.85
    }
    */
    prediction_accuracy FLOAT,
    
    -- Learning
    tactical_lesson TEXT,
    strategy_adjustment JSONB,
    
    occurred_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_battle_history_npc ON npc_battle_history(npc_id);
CREATE INDEX idx_battle_history_result ON npc_battle_history(result);
CREATE INDEX idx_battle_history_occurred ON npc_battle_history(occurred_at DESC);

-- ============================================
-- TRADING HISTORY
-- ============================================

CREATE TABLE npc_trade_history (
    id BIGSERIAL PRIMARY KEY,
    world_id VARCHAR(50) NOT NULL,
    npc_id INTEGER REFERENCES npcs(id) ON DELETE CASCADE,
    
    -- Trade details
    trade_id INTEGER, -- From world database
    trade_type VARCHAR(50), -- 'offer_created', 'offer_accepted', 'merchant_sent'
    
    -- Trade terms
    offered_resource VARCHAR(50),
    offered_amount INTEGER,
    requested_resource VARCHAR(50),
    requested_amount INTEGER,
    exchange_rate FLOAT,
    
    -- Market analysis
    market_rate_at_time FLOAT,
    profit_margin FLOAT, -- How good was the deal
    
    -- Trading partner
    partner_id INTEGER,
    partner_trustworthiness FLOAT,
    
    -- Execution
    trade_status VARCHAR(50), -- 'pending', 'completed', 'cancelled', 'expired'
    completed_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_trade_history_npc ON npc_trade_history(npc_id);
CREATE INDEX idx_trade_history_status ON npc_trade_history(trade_status);
CREATE INDEX idx_trade_history_created ON npc_trade_history(created_at DESC);
```

---

## 🏛️ World Database Extensions

### **Village-Level NPC Data**

```sql
-- ============================================
-- EXTEND WORLD DATABASE FOR NPC VILLAGES
-- ============================================

CREATE TABLE npc_village_config (
    village_id INTEGER PRIMARY KEY REFERENCES villages(id) ON DELETE CASCADE,
    npc_id INTEGER NOT NULL, -- References global npcs(id)
    
    -- Village specialization
    village_type VARCHAR(50) CHECK (
        village_type IN ('capital', 'military', 'economic', 'farming', 'defensive')
    ),
    
    build_template_name VARCHAR(100),
    current_build_phase VARCHAR(50), -- 'early', 'mid', 'late', 'endgame'
    
    -- Build queue management
    auto_build_enabled BOOLEAN DEFAULT TRUE,
    next_scheduled_build TIMESTAMP,
    build_queue_strategy JSONB,
    
    -- Resource management
    resource_priority JSONB, -- {"wood": 1, "clay": 2, "iron": 3, "wheat": 4}
    warehouse_reserve INTEGER DEFAULT 5000, -- Keep this much in reserve
    
    -- Military management
    troop_training_enabled BOOLEAN DEFAULT TRUE,
    training_queue_strategy JSONB,
    auto_raid_enabled BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_npc_village_npc ON npc_village_config(npc_id);
CREATE INDEX idx_npc_village_type ON npc_village_config(village_type);

-- ============================================
-- NPC BUILD QUEUE TRACKING
-- ============================================

CREATE TABLE npc_build_queue_history (
    id BIGSERIAL PRIMARY KEY,
    village_id INTEGER REFERENCES villages(id) ON DELETE CASCADE,
    npc_id INTEGER NOT NULL,
    
    -- Building details
    building_name VARCHAR(100) NOT NULL,
    from_level INTEGER NOT NULL,
    to_level INTEGER NOT NULL,
    
    -- Timing
    queued_at TIMESTAMP NOT NULL,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    
    -- Resources
    cost_wood INTEGER,
    cost_clay INTEGER,
    cost_iron INTEGER,
    cost_wheat INTEGER,
    
    -- Performance
    wait_time_seconds INTEGER, -- Time from queue to start
    build_time_seconds INTEGER,
    
    -- Decision making
    priority_score FLOAT,
    decision_reason TEXT,
    
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_build_queue_village ON npc_build_queue_history(village_id);
CREATE INDEX idx_build_queue_npc ON npc_build_queue_history(npc_id);
CREATE INDEX idx_build_queue_completed ON npc_build_queue_history(completed_at DESC);
```

---

## 🚀 Redis Data Structures

### **Hot Data Cache**

```python
"""
Redis schema for real-time NPC data.
"""

# Cache structures (key patterns)
REDIS_KEYS = {
    # LLM Response cache
    "llm:response:{hash}": {
        "type": "string",
        "ttl": 3600,  # 1 hour
        "value": "JSON string of LLM response"
    },
    
    # NPC state cache (hot data)
    "npc:state:{npc_id}": {
        "type": "hash",
        "ttl": 300,  # 5 minutes
        "fields": {
            "villages": "count",
            "population": "total",
            "rank": "current rank",
            "offensive_power": "military strength",
            "last_decision": "timestamp",
            "current_strategy": "expansion|military|economic"
        }
    },
    
    # Decision queue
    "npc:decisions:{priority}": {
        "type": "sorted_set",
        "ttl": None,  # Persistent
        "score": "timestamp",
        "members": "JSON decision objects"
    },
    
    # Active sessions
    "npc:active:{npc_id}": {
        "type": "string",
        "ttl": 600,  # 10 minutes
        "value": "timestamp of last activity"
    },
    
    # Rate limiting
    "ratelimit:llm:{npc_id}": {
        "type": "string",
        "ttl": 60,  # 1 minute window
        "value": "count of LLM calls"
    },
    
    # Real-time metrics
    "metrics:decisions:{minute}": {
        "type": "hash",
        "ttl": 3600,
        "fields": {
            "total": "count",
            "llm_used": "count",
            "rule_used": "count",
            "avg_latency": "milliseconds"
        }
    }
}
```

### **Redis Usage Examples**

```python
import redis.asyncio as redis
import json
from datetime import datetime

class NPCCacheManager:
    """
    Manage NPC data in Redis for high performance.
    """
    
    def __init__(self, redis_url: str = "redis://localhost:6379"):
        self.redis = redis.from_url(redis_url, decode_responses=True)
    
    async def cache_npc_state(self, npc_id: int, state: dict):
        """Cache NPC state for quick access"""
        key = f"npc:state:{npc_id}"
        
        # Store as hash for efficient field access
        await self.redis.hset(key, mapping={
            "villages": state['villages'],
            "population": state['population'],
            "rank": state['rank'],
            "offensive_power": state['offensive_power'],
            "last_decision": state['last_decision'],
            "current_strategy": state['current_strategy']
        })
        
        # Set TTL
        await self.redis.expire(key, 300)  # 5 minutes
    
    async def get_npc_state(self, npc_id: int) -> dict:
        """Retrieve cached NPC state"""
        key = f"npc:state:{npc_id}"
        return await self.redis.hgetall(key)
    
    async def queue_decision(
        self,
        npc_id: int,
        decision: dict,
        priority: int = 5
    ):
        """
        Add decision to priority queue.
        Lower priority = processed sooner.
        """
        queue_key = f"npc:decisions:{priority}"
        
        # Score = current timestamp for FIFO within priority
        score = datetime.now().timestamp()
        
        # Member = JSON decision
        decision['npc_id'] = npc_id
        member = json.dumps(decision)
        
        await self.redis.zadd(queue_key, {member: score})
    
    async def pop_next_decision(self, priority: int = 5) -> dict:
        """Get next decision from queue"""
        queue_key = f"npc:decisions:{priority}"
        
        # Get lowest score (oldest)
        results = await self.redis.zpopmin(queue_key, count=1)
        
        if results:
            member, score = results[0]
            return json.loads(member)
        
        return None
    
    async def check_llm_rate_limit(
        self,
        npc_id: int,
        max_per_minute: int = 10
    ) -> bool:
        """
        Check if NPC can make LLM call.
        Returns True if allowed.
        """
        key = f"ratelimit:llm:{npc_id}"
        
        # Increment counter
        current = await self.redis.incr(key)
        
        # Set TTL on first increment
        if current == 1:
            await self.redis.expire(key, 60)
        
        return current <= max_per_minute
```

---

## 📊 Time-Series Database (Optional - Advanced Analytics)

### **TimescaleDB Schema**

```sql
-- ============================================
-- PERFORMANCE METRICS (TimescaleDB Hypertable)
-- ============================================

CREATE TABLE npc_metrics (
    time TIMESTAMPTZ NOT NULL,
    npc_id INTEGER NOT NULL,
    world_id VARCHAR(50),
    
    -- Resource metrics
    wood INTEGER,
    clay INTEGER,
    iron INTEGER,
    wheat INTEGER,
    
    -- Production rates (per hour)
    wood_production INTEGER,
    clay_production INTEGER,
    iron_production INTEGER,
    wheat_production INTEGER,
    
    -- Military metrics
    offensive_power INTEGER,
    defensive_power INTEGER,
    total_troops INTEGER,
    
    -- Performance metrics
    rank INTEGER,
    population INTEGER,
    villages INTEGER,
    
    -- Efficiency scores
    economic_efficiency FLOAT,
    military_efficiency FLOAT,
    diplomatic_score FLOAT,
    
    PRIMARY KEY (time, npc_id)
);

-- Convert to hypertable (TimescaleDB)
SELECT create_hypertable('npc_metrics', 'time');

-- Continuous aggregates for dashboards
CREATE MATERIALIZED VIEW npc_metrics_hourly
WITH (timescaledb.continuous) AS
SELECT
    time_bucket('1 hour', time) AS hour,
    npc_id,
    AVG(population) as avg_population,
    AVG(rank) as avg_rank,
    SUM(wood_production) as total_wood_produced
FROM npc_metrics
GROUP BY hour, npc_id;
```

---

## 🔄 Data Migration & Versioning

### **Schema Version Management**

```sql
CREATE TABLE schema_versions (
    id SERIAL PRIMARY KEY,
    version VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    applied_at TIMESTAMP DEFAULT NOW(),
    rollback_script TEXT
);

-- Current version
INSERT INTO schema_versions (version, description)
VALUES ('1.0.0', 'Initial NPC framework schema');
```

---

## 🎯 Next Steps

- **API-INTEGRATION-LAYER.md** - How to use these schemas
- **PERFORMANCE-SCALING.md** - Optimize queries
- **TESTING-MONITORING.md** - Validate data integrity

**This schema supports 500+ NPCs with enterprise-grade performance!** 🗄️
