# Performance & Scaling - Enterprise-Grade Optimization

## 🎯 Overview

Scale NPC system from 50 to 500+ agents with:
- Multi-GPU load balancing
- Intelligent caching strategies
- Database query optimization
- Async parallel processing
- Resource management

---

## 📊 Performance Targets

### **System Requirements**

| NPCs | GPUs Used | Avg Response | DB Queries/sec | Memory Usage |
|------|-----------|--------------|----------------|--------------|
| 50 | RTX 3090 Ti | <50ms | 100-200 | 8GB |
| 100 | 3090 Ti + P40 #1 | <100ms | 200-400 | 16GB |
| 200 | All 3 GPUs | <150ms | 400-800 | 24GB |
| 500 | All 3 GPUs + caching | <200ms | 800-1500 | 32GB |

---

## 🚀 Multi-GPU Orchestration

### **GPU Load Balancer**

```python
import asyncio
from typing import List, Dict
from dataclasses import dataclass
from datetime import datetime
import statistics

@dataclass
class GPUWorker:
    """Single GPU worker configuration"""
    gpu_id: int
    endpoint: str  # vLLM endpoint URL
    max_concurrent: int  # Max concurrent requests
    current_load: int = 0
    total_requests: int = 0
    avg_latency_ms: float = 0.0
    error_count: int = 0

class MultiGPUOrchestrator:
    """
    Distribute LLM requests across multiple GPUs intelligently.
    """
    
    def __init__(self, workers: List[GPUWorker]):
        self.workers = workers
        self.total_workers = len(workers)
        
        # Performance tracking
        self.latency_history = {worker.gpu_id: [] for worker in workers}
        self.request_queue = asyncio.PriorityQueue()
        
        # Start background workers
        self.running = False
    
    async def start(self):
        """Start orchestrator"""
        self.running = True
        
        # Start worker processors
        tasks = [
            self._process_worker_queue(worker)
            for worker in self.workers
        ]
        
        await asyncio.gather(*tasks)
    
    async def generate(
        self,
        prompt: str,
        priority: int = 5,
        **kwargs
    ) -> str:
        """
        Generate LLM response with load balancing.
        
        Args:
            prompt: LLM prompt
            priority: 0 (highest) to 10 (lowest)
            **kwargs: Additional generation parameters
        """
        # Find best worker
        worker = await self._select_worker(priority)
        
        # Make request
        start_time = datetime.now()
        
        try:
            import aiohttp
            async with aiohttp.ClientSession() as session:
                async with session.post(
                    f"{worker.endpoint}/v1/completions",
                    json={
                        "prompt": prompt,
                        **kwargs
                    }
                ) as response:
                    
                    if response.status == 200:
                        result = await response.json()
                        text = result['choices'][0]['text']
                        
                        # Track performance
                        latency = (datetime.now() - start_time).total_seconds() * 1000
                        await self._update_worker_stats(worker, latency, success=True)
                        
                        return text
                    
                    else:
                        await self._update_worker_stats(worker, 0, success=False)
                        raise Exception(f"GPU {worker.gpu_id} error: {response.status}")
        
        except Exception as e:
            await self._update_worker_stats(worker, 0, success=False)
            raise
    
    async def _select_worker(self, priority: int) -> GPUWorker:
        """
        Select best GPU for request.
        
        Strategy:
        - High priority (0-2): Use fastest GPU (3090 Ti)
        - Medium priority (3-7): Load balance
        - Low priority (8-10): Use least loaded GPU
        """
        if priority <= 2:
            # High priority - use fastest GPU
            return min(
                self.workers,
                key=lambda w: w.avg_latency_ms
            )
        
        elif priority >= 8:
            # Low priority - use least loaded
            return min(
                self.workers,
                key=lambda w: w.current_load / w.max_concurrent
            )
        
        else:
            # Medium priority - smart load balancing
            return await self._smart_load_balance()
    
    async def _smart_load_balance(self) -> GPUWorker:
        """
        Intelligent load balancing considering:
        - Current load
        - Average latency
        - Error rate
        - GPU capabilities
        """
        scores = []
        
        for worker in self.workers:
            # Calculate score (lower = better)
            load_score = worker.current_load / worker.max_concurrent
            latency_score = worker.avg_latency_ms / 100  # Normalize
            error_score = worker.error_count / max(worker.total_requests, 1)
            
            # Weighted score
            total_score = (
                load_score * 0.5 +      # 50% weight on current load
                latency_score * 0.3 +   # 30% weight on latency
                error_score * 0.2       # 20% weight on errors
            )
            
            scores.append((worker, total_score))
        
        # Return worker with lowest score
        return min(scores, key=lambda x: x[1])[0]
    
    async def _update_worker_stats(
        self,
        worker: GPUWorker,
        latency_ms: float,
        success: bool
    ):
        """Update worker performance metrics"""
        worker.total_requests += 1
        
        if success:
            # Update average latency (rolling average)
            if worker.avg_latency_ms == 0:
                worker.avg_latency_ms = latency_ms
            else:
                # Exponential moving average
                alpha = 0.2
                worker.avg_latency_ms = (
                    alpha * latency_ms +
                    (1 - alpha) * worker.avg_latency_ms
                )
            
            # Track history
            self.latency_history[worker.gpu_id].append(latency_ms)
            
            # Keep only last 100 samples
            if len(self.latency_history[worker.gpu_id]) > 100:
                self.latency_history[worker.gpu_id].pop(0)
        
        else:
            worker.error_count += 1
    
    async def get_stats(self) -> Dict:
        """Get orchestrator statistics"""
        return {
            "total_workers": self.total_workers,
            "workers": [
                {
                    "gpu_id": w.gpu_id,
                    "current_load": w.current_load,
                    "max_concurrent": w.max_concurrent,
                    "utilization": w.current_load / w.max_concurrent,
                    "total_requests": w.total_requests,
                    "avg_latency_ms": w.avg_latency_ms,
                    "error_rate": w.error_count / max(w.total_requests, 1)
                }
                for w in self.workers
            ]
        }


# Usage example
orchestrator = MultiGPUOrchestrator([
    GPUWorker(
        gpu_id=0,
        endpoint="http://localhost:8000",  # RTX 3090 Ti
        max_concurrent=50
    ),
    GPUWorker(
        gpu_id=1,
        endpoint="http://localhost:8001",  # Tesla P40 #1
        max_concurrent=40
    ),
    GPUWorker(
        gpu_id=2,
        endpoint="http://localhost:8002",  # Tesla P40 #2
        max_concurrent=40
    )
])

# High priority request (war decision)
response = await orchestrator.generate(
    "Should I declare war on...",
    priority=1  # Use fastest GPU
)

# Low priority request (routine building)
response = await orchestrator.generate(
    "What should I build next...",
    priority=9  # Use least loaded GPU
)
```

---

## 💾 Caching Strategy

### **Multi-Layer Cache**

```python
from typing import Optional, Any
import hashlib
import pickle
from datetime import timedelta

class MultiLayerCache:
    """
    3-tier caching system:
    1. In-memory (instant, limited size)
    2. Redis (fast, shared across processes)
    3. Database (persistent, slower)
    """
    
    def __init__(
        self,
        redis_client,
        db_pool,
        memory_size_mb: int = 100
    ):
        self.redis = redis_client
        self.db = db_pool
        
        # L1: In-memory cache
        self.memory_cache = {}
        self.memory_size_limit = memory_size_mb * 1024 * 1024  # bytes
        self.memory_size_current = 0
        
        # Cache statistics
        self.stats = {
            "l1_hits": 0,  # Memory
            "l2_hits": 0,  # Redis
            "l3_hits": 0,  # Database
            "misses": 0
        }
    
    async def get(self, key: str) -> Optional[Any]:
        """Get from cache (check all layers)"""
        
        # L1: Memory cache
        if key in self.memory_cache:
            self.stats['l1_hits'] += 1
            return self.memory_cache[key]['value']
        
        # L2: Redis cache
        redis_value = await self.redis.get(f"cache:{key}")
        if redis_value:
            self.stats['l2_hits'] += 1
            value = pickle.loads(redis_value.encode('latin1'))
            
            # Promote to L1
            await self._set_memory(key, value)
            
            return value
        
        # L3: Database cache
        async with self.db.acquire() as conn:
            row = await conn.fetchrow("""
                SELECT value, expires_at
                FROM cache_store
                WHERE key = $1
                AND (expires_at IS NULL OR expires_at > NOW())
            """, key)
            
            if row:
                self.stats['l3_hits'] += 1
                value = pickle.loads(row['value'])
                
                # Promote to L2 and L1
                await self._set_redis(key, value, ttl=3600)
                await self._set_memory(key, value)
                
                return value
        
        self.stats['misses'] += 1
        return None
    
    async def set(
        self,
        key: str,
        value: Any,
        ttl: int = 3600,
        persist: bool = False
    ):
        """Set in cache (all layers)"""
        
        # L1: Memory
        await self._set_memory(key, value)
        
        # L2: Redis
        await self._set_redis(key, value, ttl)
        
        # L3: Database (if persist=True)
        if persist:
            await self._set_database(key, value, ttl)
    
    async def _set_memory(self, key: str, value: Any):
        """Set in memory cache with size limit"""
        value_size = len(pickle.dumps(value))
        
        # Check if we need to evict
        while (self.memory_size_current + value_size > self.memory_size_limit
               and self.memory_cache):
            # Evict least recently used
            oldest_key = min(
                self.memory_cache.keys(),
                key=lambda k: self.memory_cache[k]['accessed']
            )
            evicted = self.memory_cache.pop(oldest_key)
            self.memory_size_current -= evicted['size']
        
        # Store
        self.memory_cache[key] = {
            'value': value,
            'size': value_size,
            'accessed': datetime.now()
        }
        self.memory_size_current += value_size
    
    async def _set_redis(self, key: str, value: Any, ttl: int):
        """Set in Redis"""
        serialized = pickle.dumps(value).decode('latin1')
        await self.redis.setex(f"cache:{key}", ttl, serialized)
    
    async def _set_database(self, key: str, value: Any, ttl: int):
        """Set in database"""
        expires_at = datetime.now() + timedelta(seconds=ttl)
        serialized = pickle.dumps(value)
        
        async with self.db.acquire() as conn:
            await conn.execute("""
                INSERT INTO cache_store (key, value, expires_at)
                VALUES ($1, $2, $3)
                ON CONFLICT (key)
                DO UPDATE SET value = $2, expires_at = $3
            """, key, serialized, expires_at)
```

---

## 🗄️ Database Optimization

### **Query Optimization**

```sql
-- ============================================
-- OPTIMIZED INDEXES FOR NPC QUERIES
-- ============================================

-- Hot queries for NPCs

-- 1. Get NPC villages (VERY frequent)
CREATE INDEX CONCURRENTLY idx_villages_player_active 
ON villages(player_id, active) 
WHERE active = TRUE;

-- 2. Get nearby villages (for target finding)
CREATE INDEX CONCURRENTLY idx_villages_coordinates 
ON villages(x, y) 
WHERE active = TRUE;

-- Consider using PostGIS for spatial queries:
-- CREATE INDEX idx_villages_location ON villages 
-- USING GIST (ST_MakePoint(x, y));

-- 3. Get NPC state quickly
CREATE INDEX CONCURRENTLY idx_npc_world_state_lookup 
ON npc_world_state(world_id, npc_id);

-- 4. Find relationships efficiently
CREATE INDEX CONCURRENTLY idx_relationships_composite 
ON npc_relationships(npc_id, world_id, relationship_type);

-- 5. Recent memory queries
CREATE INDEX CONCURRENTLY idx_memory_recent 
ON npc_memory(npc_id, occurred_at DESC) 
WHERE importance > 0.5;

-- 6. Battle history lookups
CREATE INDEX CONCURRENTLY idx_battle_recent 
ON npc_battle_history(npc_id, occurred_at DESC);

-- ============================================
-- MATERIALIZED VIEWS FOR AGGREGATES
-- ============================================

-- Aggregate village resources per NPC (refresh every 5 minutes)
CREATE MATERIALIZED VIEW npc_resource_summary AS
SELECT 
    nv.npc_id,
    v.player_id,
    COUNT(v.id) as village_count,
    SUM(vr.wood) as total_wood,
    SUM(vr.clay) as total_clay,
    SUM(vr.iron) as total_iron,
    SUM(vr.wheat) as total_wheat,
    SUM(vr.wood_production) as wood_per_hour,
    SUM(vr.clay_production) as clay_per_hour,
    SUM(vr.iron_production) as iron_per_hour,
    SUM(vr.wheat_production) as wheat_per_hour,
    SUM(v.population) as total_population
FROM npc_village_config nv
JOIN villages v ON nv.village_id = v.id
JOIN village_resources vr ON v.id = vr.village_id
GROUP BY nv.npc_id, v.player_id;

CREATE UNIQUE INDEX ON npc_resource_summary(npc_id);

-- Refresh every 5 minutes via cron or pg_cron
-- SELECT cron.schedule('refresh-npc-resources', '*/5 * * * *',
--   'REFRESH MATERIALIZED VIEW CONCURRENTLY npc_resource_summary');

-- ============================================
-- PARTITIONING FOR LARGE TABLES
-- ============================================

-- Partition npc_memory by month (for 500 NPCs generating millions of records)
CREATE TABLE npc_memory_2025_02 PARTITION OF npc_memory
    FOR VALUES FROM ('2025-02-01') TO ('2025-03-01');

CREATE TABLE npc_memory_2025_03 PARTITION OF npc_memory
    FOR VALUES FROM ('2025-03-01') TO ('2025-04-01');

-- Auto-create new partitions via trigger or pg_partman extension
```

---

## ⚡ Batch Processing

### **Batch Decision Engine**

```python
class BatchDecisionProcessor:
    """
    Process multiple NPC decisions in parallel batches.
    Dramatically faster than sequential processing.
    """
    
    def __init__(
        self,
        orchestrator: MultiGPUOrchestrator,
        batch_size: int = 20
    ):
        self.orchestrator = orchestrator
        self.batch_size = batch_size
    
    async def process_npc_batch(
        self,
        npcs: List[int],
        decision_type: str
    ) -> Dict[int, Any]:
        """
        Process decisions for batch of NPCs in parallel.
        
        Args:
            npcs: List of NPC IDs
            decision_type: Type of decision to make
        
        Returns:
            {npc_id: decision_result}
        """
        results = {}
        
        # Split into batches
        for i in range(0, len(npcs), self.batch_size):
            batch = npcs[i:i + self.batch_size]
            
            # Create prompts for entire batch
            prompts = await asyncio.gather(*[
                self._create_prompt(npc_id, decision_type)
                for npc_id in batch
            ])
            
            # Process batch in parallel
            responses = await asyncio.gather(*[
                self.orchestrator.generate(prompt, priority=5)
                for prompt in prompts
            ])
            
            # Map responses back to NPCs
            for npc_id, response in zip(batch, responses):
                results[npc_id] = self._parse_response(response)
        
        return results
    
    async def _create_prompt(self, npc_id: int, decision_type: str) -> str:
        """Create LLM prompt for NPC decision"""
        # Get NPC state
        state = await self._get_npc_state(npc_id)
        
        if decision_type == "strategic":
            return f"""
NPC {npc_id} strategic decision:
Villages: {state['villages']}
Rank: {state['rank']}
What should be the focus today? (expand/military/economy/diplomacy)
"""
        
        # More decision types...
```

---

## 📊 Monitoring & Metrics

### **Real-Time Performance Dashboard**

```python
class PerformanceMonitor:
    """
    Real-time performance tracking.
    """
    
    def __init__(self):
        self.metrics = {
            "npcs_active": 0,
            "decisions_per_second": 0.0,
            "avg_decision_latency_ms": 0.0,
            "llm_calls_per_minute": 0,
            "db_queries_per_second": 0.0,
            "cache_hit_rate": 0.0,
            "memory_usage_mb": 0,
            "gpu_utilization": {}
        }
        
        # Time-series data
        self.history = []
        self.history_max_size = 1000
    
    async def collect_metrics(self):
        """Collect current metrics"""
        import psutil
        
        self.metrics.update({
            "npcs_active": await self._count_active_npcs(),
            "memory_usage_mb": psutil.Process().memory_info().rss / 1024 / 1024,
            "timestamp": datetime.now()
        })
        
        # Add to history
        self.history.append(dict(self.metrics))
        if len(self.history) > self.history_max_size:
            self.history.pop(0)
    
    def get_dashboard_data(self) -> Dict:
        """Get data for dashboard display"""
        return {
            "current": self.metrics,
            "last_hour": self.history[-60:],  # Last 60 samples
            "summary": {
                "avg_latency": statistics.mean(
                    m['avg_decision_latency_ms']
                    for m in self.history
                    if 'avg_decision_latency_ms' in m
                ),
                "peak_npcs": max(
                    m['npcs_active']
                    for m in self.history
                )
            }
        }
```

---

## 🚀 Scaling Recommendations

### **50 NPCs** (Week 1)
- Single RTX 3090 Ti
- PostgreSQL with basic indexes
- Redis for LLM caching
- Target: <50ms avg latency

### **100 NPCs** (Week 2)
- Add Tesla P40 #1
- Enable connection pooling
- Implement materialized views
- Target: <100ms avg latency

### **200 NPCs** (Week 3)
- Add Tesla P40 #2
- Enable query batching
- Partition large tables
- Target: <150ms avg latency

### **500 NPCs** (Week 4+)
- All 3 GPUs + orchestration
- Multi-layer caching
- Database read replicas
- Target: <200ms avg latency

**Your system can scale to 500+ intelligent NPCs!** 🚀
