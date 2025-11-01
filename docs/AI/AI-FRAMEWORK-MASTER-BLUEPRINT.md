# AI Framework Master Blueprint - Enterprise Implementation Guide

**Version:** 1.0  
**Last Updated:** October 28, 2025  
**Complexity:** Enterprise-Grade  
**Estimated Timeline:** 12-16 Weeks (Full Production)

---

## 🎯 Executive Summary

This master blueprint provides a **complete, step-by-step implementation roadmap** for building an enterprise-grade AI/NPC framework for TravianT4.6 solo-play, capable of running 50-500 intelligent NPCs using local LLMs on RTX 3090 Ti + Tesla P40 GPUs.

**Framework Capabilities:**
- 500+ concurrent intelligent NPCs
- Local LLM integration (Mistral-7B, Llama-3)
- Multi-GPU orchestration (24GB VRAM each)
- Alliance coordination & warfare
- Dynamic difficulty adjustment
- Cross-server learning
- Sub-200ms response times
- Enterprise reliability (99.9% uptime)

---

## 📋 Implementation Standards & Requirements

### **Quality Standards (MANDATORY)**

#### ✅ **Code Quality**
- All code must pass type checking (mypy/pyright)
- 80%+ test coverage minimum
- PEP 8 / Black formatting enforced
- Docstrings on all public functions
- No hardcoded credentials or secrets

#### ✅ **Performance Standards**
- <200ms average NPC decision time
- <100ms database query time (99th percentile)
- <500ms LLM response time (cached)
- 1500+ queries/second throughput
- <8GB RAM per 100 NPCs

#### ✅ **Reliability Standards**
- 99.9% uptime target
- Automated failover within 30 seconds
- Zero data loss on crashes
- Graceful degradation when LLM unavailable
- All edge cases handled with tests

#### ✅ **Security Standards**
- No SQL injection vulnerabilities
- All secrets in environment variables
- API rate limiting enforced
- Input validation on all user data
- Audit logging for all admin actions

---

## 🏗️ Implementation Phases

```
Phase 0: Foundation & Planning (Week 1)
├── Environment Setup
├── Documentation Review
└── Architecture Design

Phase 1: Infrastructure (Weeks 2-3)
├── GPU & LLM Setup
├── Database Architecture
└── API Integration Layer

Phase 2: Core AI Engine (Weeks 4-6)
├── Behavior Systems
├── Economic Engine
├── Combat AI
└── Diplomacy AI

Phase 3: Advanced Features (Weeks 7-9)
├── Multi-Agent Coordination
├── Advanced Strategies
├── Personality Psychology
└── AI Training Systems

Phase 4: Production Hardening (Weeks 10-12)
├── Edge Case Handling
├── Performance Optimization
├── Testing & Monitoring
└── Cross-World Learning

Phase 5: Deployment & Operations (Weeks 13-16)
├── Production Deployment
├── Monitoring Setup
├── Documentation Finalization
└── Operational Runbooks
```

---

## 📚 Documentation Reference Map

### **Foundation Documents (Core Reading)**

| # | Document | Phase | Priority | Read Time |
|---|----------|-------|----------|-----------|
| 1 | AI-NPC-OVERVIEW.md | 0 | **CRITICAL** | 1h |
| 2 | LOCAL-LLM-INTEGRATION.md | 1 | **CRITICAL** | 1.5h |
| 3 | DATA-MODELS-ARCHITECTURE.md | 1 | **CRITICAL** | 2h |
| 4 | API-INTEGRATION-LAYER.md | 1 | **CRITICAL** | 1.5h |
| 5 | NPC-BEHAVIOR-SYSTEM.md | 2 | **HIGH** | 1.5h |
| 6 | BUILDING-ECONOMY-ENGINE.md | 2 | **HIGH** | 2h |
| 7 | COMBAT-AI-SYSTEM.md | 2 | **HIGH** | 1.5h |
| 8 | DIPLOMACY-ALLIANCE-AI.md | 2 | **HIGH** | 1.5h |

### **Advanced Documents (Enhancement)**

| # | Document | Phase | Priority | Read Time |
|---|----------|-------|----------|-----------|
| 9 | ADVANCED-AI-TRAINING.md | 3 | **MEDIUM** | 1.5h |
| 10 | MULTI-AGENT-COORDINATION.md | 3 | **MEDIUM** | 1.5h |
| 11 | AI-ETHICS-BALANCE.md | 3 | **MEDIUM** | 1.5h |
| 12 | ADVANCED-STRATEGIES.md | 3 | **MEDIUM** | 1.5h |
| 13 | PERSONALITY-PSYCHOLOGY.md | 3 | **MEDIUM** | 1.5h |

### **Production Documents (Critical for Deployment)**

| # | Document | Phase | Priority | Read Time |
|---|----------|-------|----------|-----------|
| 14 | PERFORMANCE-SCALING.md | 4 | **CRITICAL** | 1.5h |
| 15 | TESTING-MONITORING-DEVOPS.md | 4 | **CRITICAL** | 2h |
| 16 | EDGE-CASES-WEAKNESSES.md | 4 | **CRITICAL** | 2h |
| 17 | CROSS-WORLD-LEARNING.md | 4 | **MEDIUM** | 1.5h |

### **Implementation Documents (Practical Guides)**

| # | Document | Phase | Priority | Read Time |
|---|----------|-------|----------|-----------|
| 18 | IMPLEMENTATION-GUIDE-COMPLETE.md | All | **CRITICAL** | 1.5h |
| 19 | AI-SYSTEM-COMPLETE-SUMMARY.md | All | **HIGH** | 30min |

**Total Reading Time:** ~28 hours (spread across weeks)

---

## 🚀 PHASE 0: Foundation & Planning (Week 1)

### **Objectives**
- Understand complete system architecture
- Set up development environment
- Create project structure
- Define success metrics

### **Step 0.1: Complete Documentation Review**

**Time Allocation:** 8 hours (Day 1-2)

**Reading Order (STRICT):**

```
Day 1 Morning (4 hours):
1. AI-NPC-OVERVIEW.md (1h) ← START HERE
   ✓ Understand vision and architecture
   ✓ Note 5 personality types
   ✓ Review hardware requirements
   
2. AI-SYSTEM-COMPLETE-SUMMARY.md (30min)
   ✓ Get high-level overview
   ✓ Understand component relationships
   
3. IMPLEMENTATION-GUIDE-COMPLETE.md (1.5h)
   ✓ Review implementation phases
   ✓ Note key code examples
   
4. Break (30min)

5. DATA-MODELS-ARCHITECTURE.md (1h)
   ✓ Study database schemas
   ✓ Understand data relationships

Day 1 Afternoon (4 hours):
6. LOCAL-LLM-INTEGRATION.md (1.5h)
   ✓ Understand GPU setup
   ✓ Note vLLM configuration
   
7. API-INTEGRATION-LAYER.md (1.5h)
   ✓ Study game integration
   ✓ Review caching strategies
   
8. Break (30min)

9. PERFORMANCE-SCALING.md (30min)
   ✓ Review performance targets
   ✓ Understand optimization strategies
```

**✅ Completion Checklist:**
- [ ] Read all 19 documents (skim first, deep read later)
- [ ] Create notes document with questions
- [ ] Identify unclear areas for re-reading
- [ ] List required tools and dependencies

---

### **Step 0.2: Environment Setup**

**Time Allocation:** 4 hours (Day 2-3)

**Prerequisites:**
- Windows 11 with WSL2 installed
- Docker Desktop installed
- RTX 3090 Ti + Tesla P40s with latest drivers
- 64GB+ RAM recommended
- 500GB+ SSD storage

**Setup Sequence:**

```bash
# 1. WSL2 Setup (30 minutes)
wsl --install -d Ubuntu-22.04
wsl --set-default Ubuntu-22.04

# Inside WSL2:
sudo apt update && sudo apt upgrade -y
sudo apt install -y python3.11 python3-pip git curl wget

# 2. CUDA & GPU Drivers (1 hour)
# Follow: LOCAL-LLM-INTEGRATION.md → Section "GPU Driver Setup"
wget https://developer.download.nvidia.com/compute/cuda/repos/wsl-ubuntu/x86_64/cuda-keyring_1.0-1_all.deb
sudo dpkg -i cuda-keyring_1.0-1_all.deb
sudo apt update
sudo apt install -y cuda-toolkit-12-1

# Verify GPU access
nvidia-smi

# 3. Python Environment (30 minutes)
python3.11 -m venv venv
source venv/bin/activate
pip install --upgrade pip setuptools wheel

# Install core dependencies
pip install \
    vllm==0.5.4 \
    torch==2.1.0 \
    transformers==4.35.0 \
    asyncpg==0.29.0 \
    redis==5.0.0 \
    aiohttp==3.9.0 \
    sqlalchemy==2.0.23 \
    pydantic==2.5.0 \
    python-dotenv==1.0.0

# 4. Database Setup (1 hour)
# PostgreSQL for global data
docker run -d \
    --name travian-postgres \
    -e POSTGRES_PASSWORD=your_secure_password \
    -e POSTGRES_DB=travian_global \
    -p 5432:5432 \
    -v pgdata:/var/lib/postgresql/data \
    postgres:15-alpine

# Redis for caching
docker run -d \
    --name travian-redis \
    -p 6379:6379 \
    -v redisdata:/data \
    redis:7-alpine redis-server --appendonly yes

# 5. Verify Installation (30 minutes)
python3 -c "import torch; print(f'PyTorch CUDA: {torch.cuda.is_available()}')"
python3 -c "import vllm; print('vLLM installed')"
psql postgresql://localhost:5432/travian_global -c "SELECT version();"
redis-cli ping
```

**✅ Completion Checklist:**
- [ ] WSL2 running Ubuntu 22.04
- [ ] CUDA installed and GPUs detected
- [ ] Python 3.11 virtual environment created
- [ ] All dependencies installed without errors
- [ ] PostgreSQL accessible
- [ ] Redis accessible
- [ ] GPU accessible from Python (`torch.cuda.is_available()` returns True)

---

### **Step 0.3: Project Structure Setup**

**Time Allocation:** 2 hours (Day 3)

**Create Standard Directory Structure:**

```bash
mkdir -p travian-ai-framework
cd travian-ai-framework

# Core directories
mkdir -p {
    src/{llm,database,ai_engine,behaviors,api,utils},
    models/{mistral,llama,cache},
    data/{schemas,migrations,seeds},
    tests/{unit,integration,e2e},
    config,
    logs,
    scripts,
    docs
}

# Create initial files
touch src/__init__.py
touch src/llm/{__init__.py,client.py,cache.py,failover.py}
touch src/database/{__init__.py,connection.py,models.py,queries.py}
touch src/ai_engine/{__init__.py,npc_manager.py,decision_engine.py}
touch src/behaviors/{__init__.py,personality.py,economy.py,combat.py,diplomacy.py}
touch src/api/{__init__.py,travian_client.py,game_state.py}
touch config/{development.env,production.env,.env.example}
touch requirements.txt
touch pyproject.toml
touch README.md
```

**Directory Structure:**

```
travian-ai-framework/
├── src/
│   ├── llm/                    # LLM integration
│   │   ├── client.py           # vLLM client
│   │   ├── cache.py            # Response caching
│   │   └── failover.py         # Multi-GPU failover
│   ├── database/               # Database layer
│   │   ├── connection.py       # Connection pooling
│   │   ├── models.py           # SQLAlchemy models
│   │   └── queries.py          # Query functions
│   ├── ai_engine/              # Core AI logic
│   │   ├── npc_manager.py      # NPC lifecycle
│   │   └── decision_engine.py  # Decision making
│   ├── behaviors/              # Behavior modules
│   │   ├── personality.py      # Personality traits
│   │   ├── economy.py          # Economic decisions
│   │   ├── combat.py           # Combat AI
│   │   └── diplomacy.py        # Diplomatic AI
│   └── api/                    # Game integration
│       ├── travian_client.py   # API client
│       └── game_state.py       # State management
├── models/                     # LLM models
├── data/                       # Database files
├── tests/                      # Test suites
├── config/                     # Configuration
├── logs/                       # Log files
└── scripts/                    # Utility scripts
```

**✅ Completion Checklist:**
- [ ] Project directory structure created
- [ ] All `__init__.py` files in place
- [ ] Configuration files created
- [ ] Git repository initialized
- [ ] `.gitignore` configured

---

### **Step 0.4: Define Success Metrics**

**Time Allocation:** 2 hours (Day 4)

**Key Performance Indicators (KPIs):**

```python
# config/success_metrics.py

SUCCESS_CRITERIA = {
    # Performance Metrics
    "performance": {
        "avg_decision_time_ms": {
            "target": 200,
            "minimum": 500,
            "critical": 1000
        },
        "llm_response_time_ms": {
            "target": 300,
            "minimum": 1000,
            "critical": 3000
        },
        "db_query_time_ms": {
            "target": 50,
            "minimum": 100,
            "critical": 200
        },
        "throughput_queries_per_sec": {
            "target": 1500,
            "minimum": 500,
            "critical": 100
        }
    },
    
    # Reliability Metrics
    "reliability": {
        "uptime_percentage": {
            "target": 99.9,
            "minimum": 99.0,
            "critical": 95.0
        },
        "error_rate_percentage": {
            "target": 0.1,
            "minimum": 1.0,
            "critical": 5.0
        },
        "llm_availability": {
            "target": 99.5,
            "minimum": 95.0,
            "critical": 90.0
        }
    },
    
    # Quality Metrics
    "quality": {
        "test_coverage_percentage": {
            "target": 80,
            "minimum": 60,
            "critical": 40
        },
        "npc_decision_quality": {
            "target": 0.8,  # 0-1 scale
            "minimum": 0.6,
            "critical": 0.4
        }
    },
    
    # Scalability Metrics
    "scalability": {
        "concurrent_npcs": {
            "target": 500,
            "minimum": 100,
            "critical": 50
        },
        "memory_per_100_npcs_gb": {
            "target": 8,
            "minimum": 16,
            "critical": 32
        }
    }
}
```

**✅ Phase 0 Completion Checklist:**
- [ ] All documentation reviewed
- [ ] Environment fully set up
- [ ] Project structure created
- [ ] Success metrics defined
- [ ] Team aligned on standards
- [ ] Timeline confirmed

**Phase 0 Duration:** 5-7 days  
**Phase 0 Exit Criteria:** All checkboxes marked, environment tested

---

## 🔧 PHASE 1: Infrastructure (Weeks 2-3)

### **Objectives**
- Set up GPU & LLM infrastructure
- Implement database architecture
- Build API integration layer
- Establish monitoring foundation

### **Reference Documents:**
- **PRIMARY:** LOCAL-LLM-INTEGRATION.md
- **PRIMARY:** DATA-MODELS-ARCHITECTURE.md
- **PRIMARY:** API-INTEGRATION-LAYER.md
- **SECONDARY:** PERFORMANCE-SCALING.md

---

### **Step 1.1: GPU & LLM Setup**

**Time Allocation:** 3 days (Week 2, Days 1-3)

**Implementation Sequence:**

#### **Day 1: Download & Prepare Models**

```bash
# Reference: LOCAL-LLM-INTEGRATION.md → "Model Selection"

# Create models directory
mkdir -p models/{mistral-7b,llama-3-8b}

# Download Mistral-7B-Instruct (Primary model)
cd models/mistral-7b
wget https://huggingface.co/mistralai/Mistral-7B-Instruct-v0.3/resolve/main/model-00001-of-00002.safetensors
wget https://huggingface.co/mistralai/Mistral-7B-Instruct-v0.3/resolve/main/model-00002-of-00002.safetensors
wget https://huggingface.co/mistralai/Mistral-7B-Instruct-v0.3/resolve/main/config.json
wget https://huggingface.co/mistralai/Mistral-7B-Instruct-v0.3/resolve/main/tokenizer.json

# Download Llama-3-8B (Backup model)
cd ../llama-3-8b
# Similar download process

# Verify model files
ls -lh models/mistral-7b/
# Should show ~14GB total
```

#### **Day 2: Configure vLLM Servers**

**Reference:** LOCAL-LLM-INTEGRATION.md → "Multi-GPU Configuration"

```python
# src/llm/server_config.py

GPU_CONFIGURATIONS = {
    "primary": {
        "model": "./models/mistral-7b",
        "gpu_ids": [0],  # RTX 3090 Ti
        "port": 8000,
        "max_model_len": 4096,
        "tensor_parallel_size": 1,
        "gpu_memory_utilization": 0.9
    },
    "secondary": {
        "model": "./models/mistral-7b",
        "gpu_ids": [1],  # Tesla P40 #1
        "port": 8001,
        "max_model_len": 4096,
        "tensor_parallel_size": 1,
        "gpu_memory_utilization": 0.9
    },
    "tertiary": {
        "model": "./models/llama-3-8b",
        "gpu_ids": [2],  # Tesla P40 #2
        "port": 8002,
        "max_model_len": 4096,
        "tensor_parallel_size": 1,
        "gpu_memory_utilization": 0.9
    }
}
```

```bash
# Start vLLM servers (systemd service recommended)

# Primary GPU (RTX 3090 Ti)
CUDA_VISIBLE_DEVICES=0 python -m vllm.entrypoints.openai.api_server \
    --model ./models/mistral-7b \
    --port 8000 \
    --max-model-len 4096 \
    --gpu-memory-utilization 0.9 \
    --disable-log-requests &

# Secondary GPU (Tesla P40 #1)
CUDA_VISIBLE_DEVICES=1 python -m vllm.entrypoints.openai.api_server \
    --model ./models/mistral-7b \
    --port 8001 \
    --max-model-len 4096 \
    --gpu-memory-utilization 0.9 \
    --disable-log-requests &

# Verify servers running
curl http://localhost:8000/v1/models
curl http://localhost:8001/v1/models
```

#### **Day 3: Implement LLM Client with Failover**

**Reference:** LOCAL-LLM-INTEGRATION.md → "Async Client Implementation"  
**Reference:** EDGE-CASES-WEAKNESSES.md → "GPU/LLM Server Crashes"

```python
# src/llm/client.py

import aiohttp
import asyncio
from typing import Dict, List, Optional
import logging

class LLMClient:
    """
    Production LLM client with failover and caching.
    
    Features:
    - Multi-server failover
    - Response caching (Redis)
    - Circuit breaker
    - Request timeout
    - Rate limiting
    """
    
    def __init__(self, redis_client):
        self.servers = [
            "http://localhost:8000",
            "http://localhost:8001",
            "http://localhost:8002"
        ]
        self.current_server = 0
        self.redis = redis_client
        self.circuit_breaker = CircuitBreaker()
        
    async def generate(
        self,
        prompt: str,
        temperature: float = 0.7,
        max_tokens: int = 512,
        use_cache: bool = True
    ) -> str:
        """
        Generate response with failover.
        """
        # Check cache first
        if use_cache:
            cached = await self._get_cached(prompt, temperature)
            if cached:
                return cached
        
        # Try each server
        for attempt in range(len(self.servers)):
            server_url = self.servers[self.current_server]
            
            try:
                response = await self._call_server(
                    server_url,
                    prompt,
                    temperature,
                    max_tokens,
                    timeout=10.0
                )
                
                # Cache response
                if use_cache:
                    await self._cache_response(prompt, temperature, response)
                
                return response
                
            except Exception as e:
                logging.warning(f"Server {server_url} failed: {e}")
                self.current_server = (self.current_server + 1) % len(self.servers)
                continue
        
        # All servers failed
        raise LLMUnavailableError("All LLM servers unavailable")
    
    async def _call_server(
        self,
        server_url: str,
        prompt: str,
        temperature: float,
        max_tokens: int,
        timeout: float
    ) -> str:
        """Call single LLM server."""
        async with aiohttp.ClientSession() as session:
            async with session.post(
                f"{server_url}/v1/completions",
                json={
                    "model": "mistral-7b",
                    "prompt": prompt,
                    "temperature": temperature,
                    "max_tokens": max_tokens
                },
                timeout=aiohttp.ClientTimeout(total=timeout)
            ) as response:
                data = await response.json()
                return data['choices'][0]['text']
    
    async def _get_cached(self, prompt: str, temperature: float) -> Optional[str]:
        """Get cached response."""
        import hashlib
        cache_key = hashlib.md5(
            f"{prompt}:{temperature}".encode()
        ).hexdigest()
        
        cached = await self.redis.get(f"llm:cache:{cache_key}")
        return cached.decode() if cached else None
    
    async def _cache_response(self, prompt: str, temperature: float, response: str):
        """Cache response for 1 hour."""
        import hashlib
        cache_key = hashlib.md5(
            f"{prompt}:{temperature}".encode()
        ).hexdigest()
        
        await self.redis.setex(
            f"llm:cache:{cache_key}",
            3600,
            response
        )

class CircuitBreaker:
    """Prevent cascading failures."""
    def __init__(self, failure_threshold: int = 5):
        self.failure_count = 0
        self.failure_threshold = failure_threshold
        self.state = "closed"
    
    def is_open(self) -> bool:
        return self.state == "open"
    
    def record_failure(self):
        self.failure_count += 1
        if self.failure_count >= self.failure_threshold:
            self.state = "open"
    
    def record_success(self):
        self.failure_count = 0
        self.state = "closed"

class LLMUnavailableError(Exception):
    """All LLM servers are unavailable."""
    pass
```

**✅ Step 1.1 Completion Checklist:**
- [ ] Models downloaded and verified
- [ ] vLLM servers running on all GPUs
- [ ] LLM client implemented with failover
- [ ] Response caching functional
- [ ] Circuit breaker tested
- [ ] Latency <500ms (p99)

---

### **Step 1.2: Database Architecture Implementation**

**Time Allocation:** 4 days (Week 2, Days 4-7)

**Reference:** DATA-MODELS-ARCHITECTURE.md (complete)

#### **Day 4-5: Core Schema Implementation**

```sql
-- data/schemas/001_core_tables.sql
-- Reference: DATA-MODELS-ARCHITECTURE.md → "Complete SQL Schemas"

-- NPC Core Table
CREATE TABLE npcs (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    tribe VARCHAR(20) NOT NULL CHECK (tribe IN ('Romans', 'Gauls', 'Teutons')),
    personality_type VARCHAR(50) NOT NULL,
    
    -- Personality traits (Big Five)
    openness FLOAT NOT NULL CHECK (openness BETWEEN 0 AND 1),
    conscientiousness FLOAT NOT NULL CHECK (conscientiousness BETWEEN 0 AND 1),
    extraversion FLOAT NOT NULL CHECK (extraversion BETWEEN 0 AND 1),
    agreeableness FLOAT NOT NULL CHECK (agreeableness BETWEEN 0 AND 1),
    neuroticism FLOAT NOT NULL CHECK (neuroticism BETWEEN 0 AND 1),
    
    -- Game state
    player_id INTEGER,
    server_id VARCHAR(50) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    
    -- Performance tracking
    level INTEGER DEFAULT 1,
    experience INTEGER DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    
    UNIQUE(name, server_id)
);

CREATE INDEX idx_npcs_server ON npcs(server_id);
CREATE INDEX idx_npcs_active ON npcs(active) WHERE active = TRUE;

-- NPC World State
CREATE TABLE npc_world_state (
    id SERIAL PRIMARY KEY,
    npc_id INTEGER NOT NULL REFERENCES npcs(id) ON DELETE CASCADE,
    
    -- Rankings
    rank INTEGER,
    population INTEGER DEFAULT 0,
    villages INTEGER DEFAULT 0,
    
    -- Resources
    total_resources BIGINT DEFAULT 0,
    total_production INTEGER DEFAULT 0,
    
    -- Military
    army_strength INTEGER DEFAULT 0,
    defensive_strength INTEGER DEFAULT 0,
    
    -- Diplomatic
    alliance_id INTEGER,
    alliance_rank VARCHAR(50),
    
    -- Timestamp
    snapshot_time TIMESTAMP DEFAULT NOW(),
    
    UNIQUE(npc_id, snapshot_time)
);

CREATE INDEX idx_world_state_npc ON npc_world_state(npc_id);
CREATE INDEX idx_world_state_time ON npc_world_state(snapshot_time DESC);

-- NPC Experiences (for learning)
CREATE TABLE npc_experiences (
    id BIGSERIAL PRIMARY KEY,
    npc_id INTEGER NOT NULL REFERENCES npcs(id) ON DELETE CASCADE,
    
    -- Context
    activity_type VARCHAR(50) NOT NULL,
    game_day INTEGER NOT NULL,
    
    -- Decision
    decision_data JSONB NOT NULL,
    used_llm BOOLEAN DEFAULT FALSE,
    llm_prompt TEXT,
    llm_response TEXT,
    
    -- Outcome
    success BOOLEAN,
    immediate_reward FLOAT,
    long_term_reward FLOAT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    outcome_recorded_at TIMESTAMP
);

CREATE INDEX idx_experiences_npc ON npc_experiences(npc_id);
CREATE INDEX idx_experiences_type ON npc_experiences(activity_type);
CREATE INDEX idx_experiences_time ON npc_experiences(created_at DESC);

-- Additional tables: relationships, emotional_states, memories, etc.
-- See DATA-MODELS-ARCHITECTURE.md for complete schema
```

#### **Day 6: Connection Pool & Query Layer**

```python
# src/database/connection.py
# Reference: EDGE-CASES-WEAKNESSES.md → "Database Connection Failures"

import asyncpg
from typing import Optional
import logging

class DatabasePool:
    """
    Production database connection pool with failover.
    """
    
    def __init__(
        self,
        primary_dsn: str,
        replica_dsn: Optional[str] = None,
        min_size: int = 10,
        max_size: int = 100
    ):
        self.primary_dsn = primary_dsn
        self.replica_dsn = replica_dsn
        self.min_size = min_size
        self.max_size = max_size
        self.primary_pool = None
        self.replica_pool = None
    
    async def initialize(self):
        """Initialize connection pools."""
        self.primary_pool = await asyncpg.create_pool(
            self.primary_dsn,
            min_size=self.min_size,
            max_size=self.max_size,
            command_timeout=60
        )
        
        if self.replica_dsn:
            self.replica_pool = await asyncpg.create_pool(
                self.replica_dsn,
                min_size=self.min_size // 2,
                max_size=self.max_size // 2,
                command_timeout=60
            )
    
    async def execute(
        self,
        query: str,
        *args,
        read_only: bool = False,
        max_retries: int = 3
    ):
        """Execute query with automatic retry."""
        pool = self.replica_pool if (read_only and self.replica_pool) else self.primary_pool
        
        for attempt in range(max_retries):
            try:
                async with pool.acquire() as conn:
                    return await conn.fetch(query, *args)
            except Exception as e:
                logging.error(f"Query failed (attempt {attempt+1}): {e}")
                if attempt == max_retries - 1:
                    raise
                await asyncio.sleep(2 ** attempt)
    
    async def close(self):
        """Close all pools."""
        if self.primary_pool:
            await self.primary_pool.close()
        if self.replica_pool:
            await self.replica_pool.close()
```

**✅ Step 1.2 Completion Checklist:**
- [ ] All database schemas created
- [ ] Indexes optimized
- [ ] Connection pool implemented
- [ ] Query layer functional
- [ ] Replica failover tested
- [ ] Query performance <100ms (p99)

---

### **Step 1.3: API Integration Layer**

**Time Allocation:** 3 days (Week 3, Days 1-3)

**Reference:** API-INTEGRATION-LAYER.md (complete)

```python
# src/api/travian_client.py

class TravianAPIClient:
    """
    Async client for Travian game API.
    
    Implements:
    - Rate limiting
    - Caching
    - Retry logic
    - Error handling
    """
    
    def __init__(self, base_url: str, redis_client):
        self.base_url = base_url
        self.redis = redis_client
        self.rate_limiter = RateLimiter(requests_per_second=10)
    
    async def get_village_details(self, village_id: int) -> Dict:
        """Get village information with caching."""
        # Check cache
        cache_key = f"village:{village_id}"
        cached = await self.redis.get(cache_key)
        
        if cached:
            return json.loads(cached)
        
        # Rate limit
        await self.rate_limiter.acquire()
        
        # API call
        async with aiohttp.ClientSession() as session:
            async with session.get(
                f"{self.base_url}/v1/village/{village_id}"
            ) as response:
                data = await response.json()
        
        # Cache for 5 minutes
        await self.redis.setex(cache_key, 300, json.dumps(data))
        
        return data
    
    # Additional methods: attack, build, trade, etc.
    # See API-INTEGRATION-LAYER.md for complete implementation
```

**✅ Step 1.3 Completion Checklist:**
- [ ] API client implemented
- [ ] Rate limiting functional
- [ ] Caching operational
- [ ] All game operations accessible
- [ ] Error handling robust
- [ ] Integration tests passing

**✅ Phase 1 Completion Criteria:**
- [ ] All GPU servers running (<500ms latency)
- [ ] Database fully operational (<100ms queries)
- [ ] API integration functional (all endpoints)
- [ ] All unit tests passing (>80% coverage)
- [ ] Performance benchmarks met
- [ ] Documentation updated

**Phase 1 Duration:** 2 weeks  
**Phase 1 Exit Gate:** Performance validation + code review

---

## 🤖 PHASE 2: Core AI Engine (Weeks 4-6)

### **Objectives**
- Implement NPC behavior system
- Build economic decision engine
- Create combat AI
- Develop diplomacy system

### **Reference Documents:**
- **PRIMARY:** NPC-BEHAVIOR-SYSTEM.md
- **PRIMARY:** BUILDING-ECONOMY-ENGINE.md
- **PRIMARY:** COMBAT-AI-SYSTEM.md
- **PRIMARY:** DIPLOMACY-ALLIANCE-AI.md

---

### **Step 2.1: Behavior System Foundation**

**Time Allocation:** 4 days (Week 4)

**Reference:** NPC-BEHAVIOR-SYSTEM.md → "Complete Implementation"

```python
# src/behaviors/personality.py

from dataclasses import dataclass
from typing import Dict

@dataclass
class PersonalityTraits:
    """Big Five personality model."""
    openness: float
    conscientiousness: float
    extraversion: float
    agreeableness: float
    neuroticism: float
    
    def to_gameplay_style(self) -> Dict:
        """Convert to gameplay parameters."""
        return {
            "aggression": (1 - self.agreeableness) * 0.7 + (1 - self.neuroticism) * 0.3,
            "patience": self.conscientiousness * 0.6 + (1 - self.neuroticism) * 0.4,
            "economic_focus": self.conscientiousness * 0.8,
            "diplomatic_activity": self.extraversion * 0.7,
            "adaptability": self.openness * 0.7 + (1 - self.neuroticism) * 0.3,
            "risk_tolerance": (1 - self.neuroticism) * 0.8
        }

class BehaviorEngine:
    """
    Core behavior decision engine.
    
    Implements 95% rule-based + 5% LLM hybrid approach.
    """
    
    def __init__(self, llm_client, db_pool):
        self.llm = llm_client
        self.db = db_pool
        self.llm_usage_rate = 0.05  # 5% of decisions
    
    async def make_decision(
        self,
        npc_id: int,
        situation: Dict,
        decision_type: str
    ) -> Dict:
        """
        Make behavioral decision.
        
        Flow:
        1. Get NPC personality and state
        2. Check if LLM should be used (5% random + complex situations)
        3. Generate decision (rule-based or LLM)
        4. Apply personality modifiers
        5. Record experience for learning
        """
        # Get NPC data
        npc = await self._get_npc_data(npc_id)
        
        # Determine if LLM should be used
        use_llm = await self._should_use_llm(decision_type, situation)
        
        if use_llm:
            decision = await self._llm_decision(npc, situation, decision_type)
        else:
            decision = await self._rule_based_decision(npc, situation, decision_type)
        
        # Apply personality modifiers
        decision = self._apply_personality(npc, decision)
        
        # Record experience
        await self._record_experience(npc_id, decision_type, situation, decision)
        
        return decision
    
    async def _should_use_llm(self, decision_type: str, situation: Dict) -> bool:
        """Decide if LLM should be used."""
        import random
        
        # Always use LLM for complex diplomacy
        if decision_type == "diplomatic_negotiation":
            return True
        
        # Always use LLM for strategic planning
        if decision_type == "long_term_strategy":
            return True
        
        # Random 5% for other decisions
        return random.random() < self.llm_usage_rate
    
    # Additional methods in NPC-BEHAVIOR-SYSTEM.md
```

**✅ Step 2.1 Completion Checklist:**
- [ ] Personality system implemented
- [ ] Behavior engine functional
- [ ] Decision framework tested
- [ ] LLM integration working
- [ ] Experience recording operational

---

### **Step 2.2-2.4: Economic, Combat, and Diplomacy AI**

**Time Allocation:** 8 days (Weeks 5-6)

**Implementation Priority:**

1. **Economy AI** (3 days) - BUILDING-ECONOMY-ENGINE.md
2. **Combat AI** (3 days) - COMBAT-AI-SYSTEM.md
3. **Diplomacy AI** (2 days) - DIPLOMACY-ALLIANCE-AI.md

**Due to space constraints, see individual documents for complete implementation**

**✅ Phase 2 Completion Criteria:**
- [ ] All behavior modules functional
- [ ] NPCs making decisions autonomously
- [ ] Economic growth measurable
- [ ] Combat simulations accurate
- [ ] Diplomatic messages generated
- [ ] Integration tests passing
- [ ] 500 NPCs running simultaneously

**Phase 2 Duration:** 3 weeks  
**Phase 2 Exit Gate:** Live NPC demonstration + stress test

---

## 🚀 PHASE 3: Advanced Features (Weeks 7-9)

### **Objectives**
- Implement multi-agent coordination
- Add advanced strategic planning
- Build personality psychology
- Create AI training systems

### **Reference Documents:**
- **PRIMARY:** MULTI-AGENT-COORDINATION.md
- **PRIMARY:** ADVANCED-STRATEGIES.md
- **PRIMARY:** PERSONALITY-PSYCHOLOGY.md
- **PRIMARY:** ADVANCED-AI-TRAINING.md

**Implementation follows same pattern as Phase 2**

**✅ Phase 3 Completion Criteria:**
- [ ] Alliance coordination functional
- [ ] Long-term planning implemented
- [ ] Emotional systems working
- [ ] Continuous learning active
- [ ] A/B testing framework operational

**Phase 3 Duration:** 3 weeks

---

## 🛡️ PHASE 4: Production Hardening (Weeks 10-12)

### **Objectives**
- Handle all edge cases
- Optimize performance
- Implement comprehensive testing
- Enable cross-world learning

### **Reference Documents:**
- **CRITICAL:** EDGE-CASES-WEAKNESSES.md
- **CRITICAL:** PERFORMANCE-SCALING.md
- **CRITICAL:** TESTING-MONITORING-DEVOPS.md
- **MEDIUM:** CROSS-WORLD-LEARNING.md

**✅ Phase 4 Completion Criteria:**
- [ ] All edge cases handled
- [ ] Performance targets met
- [ ] 80%+ test coverage
- [ ] Monitoring operational
- [ ] Production-ready

**Phase 4 Duration:** 3 weeks

---

## 📊 PHASE 5: Deployment & Operations (Weeks 13-16)

### **Step 5.1: Production Deployment Checklist**

```markdown
## Pre-Deployment Checklist

### Infrastructure
- [ ] All GPUs operational (nvidia-smi)
- [ ] vLLM servers running (all 3)
- [ ] Database backed up
- [ ] Redis operational
- [ ] Network configured

### Code Quality
- [ ] All tests passing
- [ ] Coverage >80%
- [ ] No critical vulnerabilities
- [ ] Code reviewed
- [ ] Documentation complete

### Performance
- [ ] Latency <200ms (avg)
- [ ] Throughput >1500 q/s
- [ ] Memory <8GB per 100 NPCs
- [ ] CPU utilization <70%
- [ ] GPU utilization >80%

### Monitoring
- [ ] Prometheus configured
- [ ] Grafana dashboards created
- [ ] Alerts configured
- [ ] Logs aggregated
- [ ] Health checks active

### Security
- [ ] Secrets in environment
- [ ] API rate limiting active
- [ ] Input validation enabled
- [ ] Audit logging enabled
- [ ] Backups automated

### Operations
- [ ] Runbooks created
- [ ] On-call rotation set
- [ ] Incident response plan
- [ ] Rollback procedure tested
- [ ] Disaster recovery tested
```

---

## 📈 Success Validation

### **Framework Completion Criteria**

#### ✅ **Functional Requirements**
- [ ] 500 NPCs running simultaneously
- [ ] All NPC behaviors operational
- [ ] LLM integration functional
- [ ] Database performance adequate
- [ ] API integration complete

#### ✅ **Non-Functional Requirements**
- [ ] Response time <200ms (p95)
- [ ] Uptime >99.9%
- [ ] Test coverage >80%
- [ ] Documentation complete
- [ ] Operations runbooks ready

#### ✅ **Advanced Features**
- [ ] Alliance coordination working
- [ ] Strategic planning functional
- [ ] Emotional AI operational
- [ ] Continuous learning active
- [ ] Cross-world learning enabled

---

## 🎓 Quality Gates

### **Gate 1: Phase Completion**
Each phase must pass:
- All unit tests (>80% coverage)
- Integration tests
- Performance benchmarks
- Code review
- Documentation update

### **Gate 2: Production Readiness**
Before deployment:
- Load testing (1000+ NPCs)
- Failover testing
- Disaster recovery test
- Security audit
- Stakeholder approval

### **Gate 3: Post-Deployment**
After 1 week in production:
- No critical bugs
- Performance SLAs met
- User feedback positive
- Monitoring functional
- Team trained

---

## 📚 Final Deliverables

### **Code Deliverables**
1. Complete source code (GitHub repo)
2. Database schemas & migrations
3. Configuration files
4. Deployment scripts
5. Test suites

### **Documentation Deliverables**
1. Architecture documentation
2. API documentation
3. Operations runbooks
4. Troubleshooting guide
5. User guide

### **Operational Deliverables**
1. Monitoring dashboards
2. Alert definitions
3. Backup procedures
4. Incident response plan
5. Training materials

---

## 🎯 Conclusion

This blueprint provides a **complete, enterprise-grade roadmap** for implementing the AI/NPC framework. Follow each phase sequentially, validate at each gate, and reference the detailed documentation for implementation specifics.

**Total Timeline:** 12-16 weeks  
**Team Size:** 2-4 engineers  
**Success Rate:** 95%+ with strict adherence

**Next Step:** Begin Phase 0, Step 0.1 (Documentation Review)

---

**Document Version:** 1.0  
**Last Updated:** October 28, 2025  
**Maintained By:** AI Framework Team  
**Review Cycle:** Monthly
