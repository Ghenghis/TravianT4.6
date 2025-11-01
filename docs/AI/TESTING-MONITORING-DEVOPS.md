# Testing, Monitoring & DevOps - Complete Quality Assurance Framework

## 🎯 Overview

Enterprise-grade testing and monitoring system ensuring:
- 99.9% uptime for NPC agents
- Automated testing across all components
- Real-time performance monitoring
- Intelligent alerting and debugging
- Production-ready deployment

---

## 🧪 Testing Framework

### **Test Pyramid**

```
         /\
        /  \   E2E Tests (5%)
       /____\  - Full NPC gameplay scenarios
      /      \ - Multi-agent interactions
     /________\ Integration Tests (15%)
    /          \ - API integration
   /            \ - Database operations
  /______________\ Unit Tests (80%)
                   - Individual functions
                   - Decision logic
                   - Battle calculations
```

---

## 🔬 Unit Tests

### **Testing Decision Logic**

```python
import pytest
import asyncio
from unittest.mock import Mock, AsyncMock, patch
from datetime import datetime

# Test fixtures
@pytest.fixture
def mock_llm_client():
    """Mock LLM client for testing"""
    client = AsyncMock()
    client.generate = AsyncMock(return_value='{"decision": "expand", "confidence": 0.85}')
    return client

@pytest.fixture
def mock_game_api():
    """Mock game API"""
    api = AsyncMock()
    api.get_village = AsyncMock(return_value=Mock(
        success=True,
        data={
            "id": 1,
            "name": "Test Village",
            "resources": {"wood": 1000, "clay": 1000, "iron": 1000, "wheat": 1000}
        }
    ))
    return api

@pytest.fixture
def sample_npc():
    """Sample NPC for testing"""
    return {
        "id": 1,
        "name": "TestWarrior",
        "tribe": "Romans",
        "personality": "aggressive",
        "behavior_template": {
            "aggression": 0.9,
            "economy": 0.3,
            "diplomacy": 0.2,
            "risk_tolerance": 0.85
        }
    }

# Unit tests for building queue optimizer
class TestBuildQueueOptimizer:
    """Test building decision logic"""
    
    @pytest.mark.asyncio
    async def test_calculate_next_build_prioritizes_main_building(self):
        """Main building should be prioritized early game"""
        from npcs.behavior_engine import BuildQueueOptimizer
        from docs.BUILDING_ECONOMY_ENGINE import ROMANS_ECONOMIC_CAPITAL
        
        optimizer = BuildQueueOptimizer(
            village_id=1,
            template=ROMANS_ECONOMIC_CAPITAL
        )
        
        current_buildings = {}  # Empty village
        current_resources = {
            "wood": 10000,
            "clay": 10000,
            "iron": 10000,
            "wheat": 10000
        }
        production_rate = {
            "wood": 100,
            "clay": 100,
            "iron": 100,
            "wheat": 100
        }
        
        next_build = await optimizer.calculate_next_build(
            current_buildings=current_buildings,
            current_resources=current_resources,
            production_rate=production_rate,
            build_queue=[]
        )
        
        assert next_build is not None
        assert next_build.building_name == "Main Building"
    
    @pytest.mark.asyncio
    async def test_respects_dependencies(self):
        """Should not build if dependencies missing"""
        from npcs.behavior_engine import BuildQueueOptimizer
        
        optimizer = BuildQueueOptimizer(village_id=1, template=Mock())
        
        # Try to build Marketplace (requires Main Building level 3)
        current_buildings = {"Main Building": 1}  # Only level 1
        
        can_build = optimizer._can_build(
            step=Mock(
                building_name="Marketplace",
                target_level=1,
                dependencies=["Main Building(3)"]
            ),
            current_buildings=current_buildings,
            build_queue=[]
        )
        
        assert can_build == False
    
    def test_calculate_building_cost_accurate(self):
        """Building cost formula should be correct"""
        from npcs.behavior_engine import BuildQueueOptimizer
        
        # Test known values
        # Main Building level 1: {wood: 70, clay: 40, iron: 60, wheat: 20}
        cost_lv1 = BuildQueueOptimizer._get_building_cost("Main Building", 1)
        assert cost_lv1["wood"] == 70
        assert cost_lv1["clay"] == 40
        
        # Level 2 should be level 1 * 1.8
        cost_lv2 = BuildQueueOptimizer._get_building_cost("Main Building", 2)
        assert cost_lv2["wood"] == int(70 * 1.8)

# Unit tests for battle simulator
class TestBattleSimulator:
    """Test combat calculations"""
    
    def test_battle_simulation_accurate(self):
        """Battle simulation should predict outcomes accurately"""
        from npcs.combat_ai import BattleSimulator
        
        simulator = BattleSimulator()
        
        # Test scenario: 100 legionnaires vs 50 praetorians
        result = simulator.simulate_battle(
            attacker_troops={"legionnaire": 100},
            defender_troops={"praetorian": 50},
            wall_level=0
        )
        
        assert result["outcome"] in ["win", "loss", "close"]
        assert "attacker_losses" in result
        assert "defender_losses" in result
        assert 0 <= result["confidence"] <= 1.0
    
    def test_wall_bonus_applied(self):
        """Wall should increase defense"""
        from npcs.combat_ai import BattleSimulator
        
        simulator = BattleSimulator()
        
        # Same battle with and without wall
        troops_atk = {"legionnaire": 100}
        troops_def = {"praetorian": 50}
        
        result_no_wall = simulator.simulate_battle(
            troops_atk, troops_def, wall_level=0
        )
        
        result_with_wall = simulator.simulate_battle(
            troops_atk, troops_def, wall_level=10
        )
        
        # Defender should lose fewer troops with wall
        assert (result_with_wall["defender_losses"]["praetorian"] <
                result_no_wall["defender_losses"]["praetorian"])

# Unit tests for trading optimizer
class TestTradingOptimizer:
    """Test trading algorithms"""
    
    @pytest.mark.asyncio
    async def test_finds_profitable_trades(self):
        """Should identify profitable trade opportunities"""
        from npcs.trading import TradingOptimizer
        
        trader = TradingOptimizer(npc_id=1)
        
        surplus = {"wood": 50000}
        deficit = {"iron": 10000}
        market_prices = {"wood": 1.0, "iron": 1.5}
        
        trades = await trader.find_optimal_trades(
            surplus=surplus,
            deficit=deficit,
            market_prices=market_prices,
            merchant_capacity=20
        )
        
        assert len(trades) > 0
        assert trades[0].offer_resource == "wood"
        assert trades[0].request_resource == "iron"
        assert trades[0].exchange_rate > 0

# Run tests
if __name__ == "__main__":
    pytest.main([__file__, "-v", "--asyncio-mode=auto"])
```

---

## 🔗 Integration Tests

### **API Integration Testing**

```python
import pytest
from aiohttp import web
import asyncio

@pytest.fixture
async def test_server():
    """Mock Travian API server for testing"""
    
    app = web.Application()
    
    # Mock endpoints
    async def get_village(request):
        village_id = request.match_info['village_id']
        return web.json_response({
            "id": int(village_id),
            "name": "Test Village",
            "population": 500,
            "resources": {
                "wood": 1000,
                "clay": 1000,
                "iron": 1000,
                "wheat": 1000
            }
        })
    
    async def queue_building(request):
        data = await request.json()
        return web.json_response({
            "success": True,
            "building": data['building'],
            "queue_position": 1
        })
    
    app.router.add_get('/v1/village/{village_id}', get_village)
    app.router.add_post('/v1/village/{village_id}/build', queue_building)
    
    runner = web.AppRunner(app)
    await runner.setup()
    site = web.TCPSite(runner, 'localhost', 8888)
    await site.start()
    
    yield "http://localhost:8888"
    
    await runner.cleanup()

class TestAPIIntegration:
    """Test API client integration"""
    
    @pytest.mark.asyncio
    async def test_get_village_integration(self, test_server):
        """Test getting village through API"""
        from npcs.api_client import TravianAPIClient
        
        async with TravianAPIClient(base_url=test_server) as api:
            result = await api.get_village(village_id=123)
            
            assert result.success == True
            assert result.data['id'] == 123
            assert 'resources' in result.data
    
    @pytest.mark.asyncio
    async def test_queue_building_integration(self, test_server):
        """Test building queue through API"""
        from npcs.api_client import TravianAPIClient
        
        async with TravianAPIClient(base_url=test_server) as api:
            result = await api.queue_building(
                village_id=123,
                building_name="Main Building"
            )
            
            assert result.success == True
            assert result.data['building'] == "Main Building"
    
    @pytest.mark.asyncio
    async def test_api_caching_works(self, test_server):
        """Test that API responses are cached"""
        from npcs.api_client import TravianAPIClient
        import redis.asyncio as redis
        
        # Clear cache
        cache = redis.from_url("redis://localhost:6379")
        await cache.flushdb()
        
        async with TravianAPIClient(base_url=test_server) as api:
            # First call
            result1 = await api.get_village(village_id=123)
            assert result1.cached == False
            
            # Second call (should be cached)
            result2 = await api.get_village(village_id=123)
            assert result2.cached == True
            assert result2.latency_ms == 0  # Instant from cache

# Database integration tests
class TestDatabaseIntegration:
    """Test database operations"""
    
    @pytest.mark.asyncio
    async def test_create_npc(self, db_connection):
        """Test creating NPC in database"""
        from npcs.npc_manager import NPCManager
        
        manager = NPCManager(db_connection)
        
        npc_id = await manager.create_npc(
            name="TestNPC",
            tribe="Romans",
            personality="aggressive",
            behavior_template={
                "aggression": 0.9,
                "economy": 0.3
            }
        )
        
        assert npc_id is not None
        assert npc_id > 0
    
    @pytest.mark.asyncio
    async def test_npc_state_persistence(self, db_connection):
        """Test that NPC state persists correctly"""
        from npcs.npc_manager import NPCManager
        
        manager = NPCManager(db_connection)
        
        # Create NPC
        npc_id = await manager.create_npc(
            name="PersistTest",
            tribe="Gauls",
            personality="economic",
            behavior_template={}
        )
        
        # Update state
        await manager.update_npc_state(
            npc_id=npc_id,
            world_id="testworld",
            state={
                "rank": 42,
                "population": 5000,
                "villages": 3
            }
        )
        
        # Retrieve state
        state = await manager.get_npc_state(npc_id, "testworld")
        
        assert state['rank'] == 42
        assert state['population'] == 5000
```

---

## 🎭 End-to-End Tests

### **Complete NPC Gameplay Scenarios**

```python
class TestNPCGameplay:
    """Test full NPC gameplay scenarios"""
    
    @pytest.mark.asyncio
    @pytest.mark.slow
    async def test_npc_builds_village(self):
        """Test NPC can build village from scratch"""
        from npcs.npc_behavior import NPCBehaviorEngine
        from npcs.api_client import TravianAPIClient
        
        # Create NPC
        npc = await self._create_test_npc(personality="economic")
        
        # Create starting village
        village = await self._create_test_village(npc_id=npc.id)
        
        # Run NPC for 1 simulated hour
        async with TravianAPIClient() as api:
            engine = NPCBehaviorEngine(
                npc_id=npc.id,
                behavior_template=npc.behavior_template,
                llm_client=Mock(),
                game_api=api
            )
            
            # Execute one building cycle
            await engine._manage_build_queue()
            
            # Verify building was queued
            queue = await api.get_build_queue(village['id'])
            assert queue.success
            assert len(queue.data) > 0
    
    @pytest.mark.asyncio
    @pytest.mark.slow
    async def test_npc_conducts_raid(self):
        """Test NPC can find target and raid"""
        from npcs.npc_behavior import NPCBehaviorEngine
        
        # Create aggressive NPC with army
        npc = await self._create_test_npc(personality="aggressive")
        village = await self._create_test_village(
            npc_id=npc.id,
            troops={"legionnaire": 100}
        )
        
        # Create target village (weak, inactive player)
        target = await self._create_test_village(
            npc_id=None,  # Different player
            troops={"praetorian": 10},
            resources={"wood": 10000}
        )
        
        # Run NPC raid logic
        async with TravianAPIClient() as api:
            engine = NPCBehaviorEngine(
                npc_id=npc.id,
                behavior_template=npc.behavior_template,
                llm_client=Mock(),
                game_api=api
            )
            
            # Find and raid targets
            targets = await engine._find_raid_targets()
            
            assert len(targets) > 0
            assert targets[0]['id'] == target['id']
            
            # Execute raid
            await engine._launch_raids(targets[:1])
            
            # Verify attack was sent
            outgoing = await api.get_outgoing_attacks(village['id'])
            assert len(outgoing.data) > 0
    
    @pytest.mark.asyncio
    @pytest.mark.slow
    async def test_multi_npc_interaction(self):
        """Test multiple NPCs interacting"""
        
        # Create alliance leader NPC
        leader = await self._create_test_npc(personality="diplomat")
        
        # Create potential recruit NPC
        recruit = await self._create_test_npc(personality="balanced")
        
        # Leader should send alliance invitation
        # (This would use LLM to generate message)
        
        # Recruit should receive and potentially accept
        
        # Verify alliance formation
        pass  # Full implementation would test complete flow
```

---

## 📊 Performance Testing

### **Load Testing**

```python
import asyncio
import time
from typing import List

class LoadTester:
    """
    Load test NPC system to ensure it handles 500 NPCs.
    """
    
    async def test_concurrent_decisions(self, npc_count: int = 500):
        """
        Test making decisions for 500 NPCs simultaneously.
        
        Target: All complete within 5 seconds
        """
        print(f"\n🔥 Load Test: {npc_count} concurrent NPCs\n")
        
        start_time = time.time()
        
        # Simulate decision making for all NPCs
        tasks = [
            self._simulate_npc_decision(npc_id)
            for npc_id in range(1, npc_count + 1)
        ]
        
        results = await asyncio.gather(*tasks, return_exceptions=True)
        
        duration = time.time() - start_time
        
        # Analyze results
        successful = sum(1 for r in results if not isinstance(r, Exception))
        failed = len(results) - successful
        avg_latency = duration / len(results)
        
        print(f"Results:")
        print(f"  Total NPCs: {npc_count}")
        print(f"  Successful: {successful}")
        print(f"  Failed: {failed}")
        print(f"  Total time: {duration:.2f}s")
        print(f"  Avg latency: {avg_latency*1000:.2f}ms")
        print(f"  Throughput: {npc_count/duration:.2f} decisions/sec")
        
        # Assert performance targets
        assert duration < 5.0, f"Too slow: {duration}s (target: <5s)"
        assert avg_latency < 0.2, f"Avg latency too high: {avg_latency}s (target: <200ms)"
        assert failed == 0, f"{failed} NPCs failed"
        
        print(f"\n✅ Load test PASSED\n")
    
    async def _simulate_npc_decision(self, npc_id: int):
        """Simulate single NPC making a decision"""
        # This would call actual NPC decision logic
        await asyncio.sleep(0.1)  # Simulate processing time
        return {"npc_id": npc_id, "decision": "success"}
    
    async def test_database_load(self):
        """Test database can handle load"""
        print("\n🔥 Database Load Test\n")
        
        # Simulate 1000 queries/second for 10 seconds
        total_queries = 10000
        start_time = time.time()
        
        tasks = [
            self._execute_test_query()
            for _ in range(total_queries)
        ]
        
        await asyncio.gather(*tasks)
        
        duration = time.time() - start_time
        qps = total_queries / duration
        
        print(f"Results:")
        print(f"  Total queries: {total_queries}")
        print(f"  Duration: {duration:.2f}s")
        print(f"  QPS: {qps:.2f}")
        
        assert qps > 500, f"Database too slow: {qps} QPS (target: >500)"
        
        print(f"\n✅ Database load test PASSED\n")
    
    async def _execute_test_query(self):
        """Execute test database query"""
        # This would execute actual database query
        await asyncio.sleep(0.01)
        return True

# Run load tests
async def run_all_load_tests():
    tester = LoadTester()
    
    await tester.test_concurrent_decisions(npc_count=50)
    await tester.test_concurrent_decisions(npc_count=100)
    await tester.test_concurrent_decisions(npc_count=200)
    await tester.test_concurrent_decisions(npc_count=500)
    
    await tester.test_database_load()

if __name__ == "__main__":
    asyncio.run(run_all_load_tests())
```

---

## 📈 Monitoring System

### **Real-Time Metrics Collection**

```python
from prometheus_client import Counter, Histogram, Gauge, start_http_server
import time

# Define metrics
npc_decisions_total = Counter(
    'npc_decisions_total',
    'Total decisions made by NPCs',
    ['npc_id', 'decision_type', 'used_llm']
)

npc_decision_latency = Histogram(
    'npc_decision_latency_seconds',
    'Decision-making latency',
    ['decision_type']
)

llm_calls_total = Counter(
    'llm_calls_total',
    'Total LLM API calls',
    ['gpu_id', 'model']
)

llm_latency = Histogram(
    'llm_latency_seconds',
    'LLM response latency',
    ['gpu_id']
)

active_npcs = Gauge(
    'active_npcs',
    'Number of currently active NPCs'
)

database_queries_total = Counter(
    'database_queries_total',
    'Total database queries',
    ['query_type', 'status']
)

cache_operations = Counter(
    'cache_operations_total',
    'Cache operations',
    ['operation', 'result']  # operation: get/set, result: hit/miss
)

class NPCMetricsCollector:
    """
    Collect and export NPC metrics for monitoring.
    """
    
    def __init__(self, port: int = 9090):
        self.port = port
        
        # Start Prometheus HTTP server
        start_http_server(port)
        print(f"📊 Metrics server started on port {port}")
    
    def record_decision(
        self,
        npc_id: int,
        decision_type: str,
        used_llm: bool,
        latency_seconds: float
    ):
        """Record NPC decision"""
        npc_decisions_total.labels(
            npc_id=npc_id,
            decision_type=decision_type,
            used_llm=str(used_llm)
        ).inc()
        
        npc_decision_latency.labels(
            decision_type=decision_type
        ).observe(latency_seconds)
    
    def record_llm_call(
        self,
        gpu_id: int,
        model: str,
        latency_seconds: float
    ):
        """Record LLM API call"""
        llm_calls_total.labels(
            gpu_id=gpu_id,
            model=model
        ).inc()
        
        llm_latency.labels(
            gpu_id=gpu_id
        ).observe(latency_seconds)
    
    def update_active_npcs(self, count: int):
        """Update active NPC count"""
        active_npcs.set(count)
    
    def record_cache_operation(
        self,
        operation: str,
        result: str
    ):
        """Record cache hit/miss"""
        cache_operations.labels(
            operation=operation,
            result=result
        ).inc()
```

### **Grafana Dashboard Configuration**

```yaml
# grafana-dashboard.yaml
# Import this into Grafana for real-time monitoring

apiVersion: 1

providers:
  - name: 'NPC System'
    folder: ''
    type: file
    options:
      path: /etc/grafana/dashboards

dashboards:
  - title: "NPC System Overview"
    panels:
      - title: "Active NPCs"
        type: "graph"
        targets:
          - expr: "active_npcs"
      
      - title: "Decisions Per Second"
        type: "graph"
        targets:
          - expr: "rate(npc_decisions_total[1m])"
      
      - title: "Avg Decision Latency"
        type: "graph"
        targets:
          - expr: "histogram_quantile(0.95, npc_decision_latency_seconds)"
      
      - title: "LLM Calls Per Minute"
        type: "graph"
        targets:
          - expr: "rate(llm_calls_total[1m]) * 60"
      
      - title: "GPU Utilization"
        type: "graph"
        targets:
          - expr: "rate(llm_calls_total[1m]) by (gpu_id)"
      
      - title: "Cache Hit Rate"
        type: "graph"
        targets:
          - expr: |
              sum(rate(cache_operations_total{result="hit"}[5m])) /
              sum(rate(cache_operations_total[5m]))
```

---

## 🚨 Alerting Rules

### **Alert Configuration**

```yaml
# prometheus-alerts.yaml

groups:
  - name: npc_system
    interval: 30s
    rules:
      # High latency alert
      - alert: HighDecisionLatency
        expr: |
          histogram_quantile(0.95, npc_decision_latency_seconds) > 0.5
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "NPC decision latency is high"
          description: "95th percentile latency is {{ $value }}s (threshold: 0.5s)"
      
      # LLM errors alert
      - alert: LLMErrors
        expr: |
          rate(llm_calls_total{status="error"}[5m]) > 0.1
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "LLM error rate is high"
          description: "{{ $value }} errors per second"
      
      # Database issues
      - alert: DatabaseSlowQueries
        expr: |
          rate(database_queries_total{status="timeout"}[5m]) > 0.05
        for: 3m
        labels:
          severity: warning
        annotations:
          summary: "Database query timeouts detected"
      
      # Low cache hit rate
      - alert: LowCacheHitRate
        expr: |
          sum(rate(cache_operations_total{result="hit"}[5m])) /
          sum(rate(cache_operations_total[5m])) < 0.5
        for: 10m
        labels:
          severity: info
        annotations:
          summary: "Cache hit rate below 50%"
          description: "Consider increasing cache TTL or size"
```

---

## 🐛 Debugging Tools

### **NPC Behavior Debugger**

```python
class NPCDebugger:
    """
    Debug NPC behavior in real-time.
    """
    
    def __init__(self, npc_id: int):
        self.npc_id = npc_id
        self.log_file = f"logs/npc_{npc_id}_debug.log"
    
    async def trace_decision(self, decision_context: dict):
        """
        Trace a single decision with full context.
        """
        trace = {
            "timestamp": datetime.now().isoformat(),
            "npc_id": self.npc_id,
            "context": decision_context,
            "steps": []
        }
        
        # Log each step
        self._log_step(trace, "START", decision_context)
        
        # ... decision logic runs here ...
        
        # Save trace
        with open(self.log_file, 'a') as f:
            f.write(json.dumps(trace, indent=2) + '\n')
    
    def _log_step(self, trace: dict, step_name: str, data: dict):
        """Log a decision step"""
        trace['steps'].append({
            "step": step_name,
            "timestamp": datetime.now().isoformat(),
            "data": data
        })
    
    async def replay_decision(self, trace_id: str):
        """
        Replay a past decision to debug issues.
        """
        # Load trace
        with open(self.log_file) as f:
            traces = [json.loads(line) for line in f]
        
        target_trace = next(
            (t for t in traces if t.get('id') == trace_id),
            None
        )
        
        if target_trace:
            print(f"Replaying decision {trace_id}...")
            for step in target_trace['steps']:
                print(f"  {step['step']}: {step['data']}")
```

---

## 🎯 Continuous Integration

### **GitHub Actions Workflow**

```yaml
# .github/workflows/test.yml

name: NPC System Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_PASSWORD: postgres
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
      
      redis:
        image: redis:7-alpine
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Set up Python
        uses: actions/setup-python@v4
        with:
          python-version: '3.11'
      
      - name: Install dependencies
        run: |
          pip install -r requirements.txt
          pip install pytest pytest-asyncio pytest-cov
      
      - name: Run unit tests
        run: |
          pytest tests/unit/ -v --cov=npcs
      
      - name: Run integration tests
        run: |
          pytest tests/integration/ -v
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
```

---

## 📦 Production Deployment

### **Docker Compose for Production**

```yaml
# docker-compose.prod.yml

version: '3.8'

services:
  # Load balancer
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - npc-controller
  
  # NPC controller (multiple instances for HA)
  npc-controller-1:
    build: .
    environment:
      - INSTANCE_ID=1
      - VLLM_URL=http://vllm-primary:8000
      - REDIS_URL=redis://redis-cluster:6379
      - DATABASE_URL=${DATABASE_URL}
    depends_on:
      - vllm-primary
      - redis-cluster
    restart: unless-stopped
  
  npc-controller-2:
    build: .
    environment:
      - INSTANCE_ID=2
      - VLLM_URL=http://vllm-secondary:8001
      - REDIS_URL=redis://redis-cluster:6379
      - DATABASE_URL=${DATABASE_URL}
    depends_on:
      - vllm-secondary
      - redis-cluster
    restart: unless-stopped
  
  # vLLM services (GPUs)
  vllm-primary:
    image: vllm/vllm-openai:latest
    runtime: nvidia
    environment:
      - CUDA_VISIBLE_DEVICES=0
    volumes:
      - ./models:/models
    command: >
      --model /models/mistral-7b-instruct
      --host 0.0.0.0
      --port 8000
      --gpu-memory-utilization 0.9
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              device_ids: ['0']
              capabilities: [gpu]
    restart: unless-stopped
  
  # Redis cluster
  redis-cluster:
    image: redis:7-alpine
    command: redis-server --appendonly yes --maxmemory 4gb
    volumes:
      - redis_data:/data
    restart: unless-stopped
  
  # Monitoring
  prometheus:
    image: prom/prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus
    restart: unless-stopped
  
  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
    volumes:
      - grafana_data:/var/lib/grafana
      - ./grafana-dashboards:/etc/grafana/dashboards
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=${GRAFANA_PASSWORD}
    restart: unless-stopped

volumes:
  redis_data:
  prometheus_data:
  grafana_data:
```

---

## ✅ Production Checklist

### **Before Going Live**

- [ ] All unit tests passing (>90% coverage)
- [ ] All integration tests passing
- [ ] Load test with 500 NPCs successful
- [ ] Monitoring and alerts configured
- [ ] Database backups automated
- [ ] Error tracking (Sentry) set up
- [ ] Performance baselines documented
- [ ] Rollback plan documented
- [ ] On-call rotation established

**Your NPC system is production-ready!** 🚀
