# API Integration Layer - Complete Game Engine Interface

## 🎯 Overview

Enterprise-grade abstraction layer connecting NPCs to Travian game engine with:
- Async operations for maximum performance
- Connection pooling and retry logic
- Rate limiting and quota management
- Error handling and recovery
- Request caching and optimization

---

## 🏗️ Architecture Overview

```
NPC Behavior Engine
        │
        ▼
  API Integration Layer (This Document)
        │
        ├──► HTTP Client (API Endpoints)
        │
        ├──► Database Direct (World DB)
        │
        ├──► WebSocket (Real-time Events)
        │
        └──► Cache Layer (Redis)
        
        ▼
  Travian Game Engine
```

---

## 📡 API Client Architecture

### **Base API Client**

```python
import asyncio
import aiohttp
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta
import logging
from dataclasses import dataclass
import hashlib

logger = logging.getLogger(__name__)

@dataclass
class APIResponse:
    """Standardized API response"""
    success: bool
    data: Any
    error: Optional[str] = None
    status_code: int = 200
    latency_ms: int = 0
    cached: bool = False

class TravianAPIClient:
    """
    Unified interface to Travian game engine.
    
    Supports multiple backends:
    - HTTP API (sections/api/)
    - Direct database access (faster)
    - WebSocket (real-time updates)
    """
    
    def __init__(
        self,
        base_url: str = "http://localhost:5000",
        world_id: str = "testworld",
        db_connection_string: str = None,
        redis_url: str = "redis://localhost:6379",
        timeout: int = 30,
        max_retries: int = 3
    ):
        self.base_url = base_url
        self.world_id = world_id
        self.timeout = aiohttp.ClientTimeout(total=timeout)
        self.max_retries = max_retries
        
        # Session management
        self._session: Optional[aiohttp.ClientSession] = None
        self._db_pool = None
        
        # Database connection
        if db_connection_string:
            self._init_db_pool(db_connection_string)
        
        # Cache
        import redis.asyncio as redis
        self.cache = redis.from_url(redis_url, decode_responses=True)
        self.cache_ttl = 60  # Default 1 minute
        
        # Rate limiting
        self.rate_limiter = RateLimiter(
            max_requests_per_second=100,
            max_requests_per_minute=5000
        )
        
        # Metrics
        self.metrics = {
            "total_requests": 0,
            "cache_hits": 0,
            "cache_misses": 0,
            "errors": 0,
            "avg_latency_ms": 0
        }
    
    async def __aenter__(self):
        """Async context manager entry"""
        self._session = aiohttp.ClientSession(timeout=self.timeout)
        return self
    
    async def __aexit__(self, exc_type, exc_val, exc_tb):
        """Async context manager exit"""
        if self._session:
            await self._session.close()
        if self._db_pool:
            await self._db_pool.close()
        await self.cache.close()
    
    def _init_db_pool(self, connection_string: str):
        """Initialize database connection pool"""
        import asyncpg
        self._db_pool = asyncpg.create_pool(
            connection_string,
            min_size=5,
            max_size=20,
            command_timeout=10
        )
    
    async def _request(
        self,
        method: str,
        endpoint: str,
        data: Dict = None,
        use_cache: bool = True,
        cache_ttl: int = None
    ) -> APIResponse:
        """
        Make HTTP request with retry logic and caching.
        """
        start_time = datetime.now()
        cache_key = None
        
        # Check cache for GET requests
        if method == "GET" and use_cache:
            cache_key = self._make_cache_key(endpoint, data)
            cached_data = await self.cache.get(cache_key)
            
            if cached_data:
                self.metrics['cache_hits'] += 1
                import json
                return APIResponse(
                    success=True,
                    data=json.loads(cached_data),
                    cached=True,
                    latency_ms=0
                )
            
            self.metrics['cache_misses'] += 1
        
        # Rate limiting
        await self.rate_limiter.acquire()
        
        # Make request with retries
        last_error = None
        for attempt in range(self.max_retries):
            try:
                url = f"{self.base_url}/{endpoint}"
                
                async with self._session.request(
                    method,
                    url,
                    json=data,
                    headers={"Content-Type": "application/json"}
                ) as response:
                    
                    latency = (datetime.now() - start_time).total_seconds() * 1000
                    self.metrics['total_requests'] += 1
                    
                    if response.status == 200:
                        result_data = await response.json()
                        
                        # Cache successful GET responses
                        if method == "GET" and use_cache and cache_key:
                            import json
                            ttl = cache_ttl or self.cache_ttl
                            await self.cache.setex(
                                cache_key,
                                ttl,
                                json.dumps(result_data)
                            )
                        
                        return APIResponse(
                            success=True,
                            data=result_data,
                            status_code=response.status,
                            latency_ms=int(latency)
                        )
                    
                    else:
                        error_text = await response.text()
                        last_error = f"HTTP {response.status}: {error_text}"
                        
                        # Don't retry client errors
                        if response.status < 500:
                            break
            
            except asyncio.TimeoutError:
                last_error = "Request timeout"
                logger.warning(f"Timeout on attempt {attempt + 1}/{self.max_retries}")
            
            except Exception as e:
                last_error = str(e)
                logger.error(f"Request error: {e}")
            
            # Exponential backoff
            if attempt < self.max_retries - 1:
                await asyncio.sleep(2 ** attempt)
        
        # All retries failed
        self.metrics['errors'] += 1
        return APIResponse(
            success=False,
            data=None,
            error=last_error,
            latency_ms=int((datetime.now() - start_time).total_seconds() * 1000)
        )
    
    def _make_cache_key(self, endpoint: str, data: Dict = None) -> str:
        """Create unique cache key"""
        key_string = f"{endpoint}|{data or {}}"
        return f"api:{hashlib.md5(key_string.encode()).hexdigest()}"
    
    # ============================================
    # VILLAGE OPERATIONS
    # ============================================
    
    async def get_village(self, village_id: int) -> APIResponse:
        """Get complete village data"""
        # Try database first (faster)
        if self._db_pool:
            return await self._get_village_db(village_id)
        
        # Fallback to API
        return await self._request(
            "GET",
            f"v1/village/{village_id}",
            use_cache=True,
            cache_ttl=30
        )
    
    async def _get_village_db(self, village_id: int) -> APIResponse:
        """Get village directly from database (fastest)"""
        start_time = datetime.now()
        
        try:
            async with self._db_pool.acquire() as conn:
                village = await conn.fetchrow("""
                    SELECT 
                        v.id,
                        v.name,
                        v.player_id,
                        v.x,
                        v.y,
                        v.population,
                        
                        -- Resources
                        r.wood,
                        r.clay,
                        r.iron,
                        r.wheat,
                        
                        -- Production
                        r.wood_production,
                        r.clay_production,
                        r.iron_production,
                        r.wheat_production,
                        
                        -- Storage
                        r.warehouse_capacity,
                        r.granary_capacity
                        
                    FROM villages v
                    LEFT JOIN village_resources r ON v.id = r.village_id
                    WHERE v.id = $1
                """, village_id)
                
                if village:
                    # Get buildings
                    buildings = await conn.fetch("""
                        SELECT building_name, level
                        FROM village_buildings
                        WHERE village_id = $1
                    """, village_id)
                    
                    # Get build queue
                    queue = await conn.fetch("""
                        SELECT 
                            building_name,
                            target_level,
                            started_at,
                            completes_at
                        FROM build_queue
                        WHERE village_id = $1
                        ORDER BY queue_position
                    """, village_id)
                    
                    latency = (datetime.now() - start_time).total_seconds() * 1000
                    
                    return APIResponse(
                        success=True,
                        data={
                            **dict(village),
                            "buildings": {b['building_name']: b['level'] for b in buildings},
                            "build_queue": [dict(q) for q in queue]
                        },
                        latency_ms=int(latency)
                    )
            
            return APIResponse(success=False, error="Village not found")
        
        except Exception as e:
            logger.error(f"Database error getting village: {e}")
            return APIResponse(success=False, error=str(e))
    
    async def get_villages(self, player_id: int) -> APIResponse:
        """Get all villages for a player"""
        if self._db_pool:
            return await self._get_villages_db(player_id)
        
        return await self._request(
            "GET",
            f"v1/player/{player_id}/villages",
            use_cache=True,
            cache_ttl=60
        )
    
    async def _get_villages_db(self, player_id: int) -> APIResponse:
        """Get all villages from database"""
        start_time = datetime.now()
        
        try:
            async with self._db_pool.acquire() as conn:
                villages = await conn.fetch("""
                    SELECT 
                        v.id,
                        v.name,
                        v.x,
                        v.y,
                        v.population,
                        v.is_capital,
                        r.wood,
                        r.clay,
                        r.iron,
                        r.wheat
                    FROM villages v
                    LEFT JOIN village_resources r ON v.id = r.village_id
                    WHERE v.player_id = $1
                    ORDER BY v.is_capital DESC, v.population DESC
                """, player_id)
                
                latency = (datetime.now() - start_time).total_seconds() * 1000
                
                return APIResponse(
                    success=True,
                    data=[dict(v) for v in villages],
                    latency_ms=int(latency)
                )
        
        except Exception as e:
            return APIResponse(success=False, error=str(e))
    
    # ============================================
    # BUILDING OPERATIONS
    # ============================================
    
    async def queue_building(
        self,
        village_id: int,
        building_name: str,
        use_instant: bool = False
    ) -> APIResponse:
        """
        Add building to queue.
        
        Args:
            village_id: Target village
            building_name: Building to upgrade
            use_instant: Use gold for instant completion
        """
        return await self._request(
            "POST",
            f"v1/village/{village_id}/build",
            data={
                "building": building_name,
                "instant": use_instant
            },
            use_cache=False
        )
    
    async def get_build_queue(self, village_id: int) -> APIResponse:
        """Get current build queue"""
        return await self._request(
            "GET",
            f"v1/village/{village_id}/build_queue",
            use_cache=True,
            cache_ttl=30
        )
    
    async def cancel_building(
        self,
        village_id: int,
        queue_position: int
    ) -> APIResponse:
        """Cancel building in queue"""
        return await self._request(
            "DELETE",
            f"v1/village/{village_id}/build_queue/{queue_position}",
            use_cache=False
        )
    
    # ============================================
    # MILITARY OPERATIONS
    # ============================================
    
    async def train_troops(
        self,
        village_id: int,
        troops: Dict[str, int]
    ) -> APIResponse:
        """
        Train troops.
        
        Args:
            village_id: Village to train in
            troops: {unit_type: count}
        """
        return await self._request(
            "POST",
            f"v1/village/{village_id}/train",
            data={"troops": troops},
            use_cache=False
        )
    
    async def send_attack(
        self,
        from_village_id: int,
        to_village_id: int,
        troops: Dict[str, int],
        attack_type: str = "normal"  # normal, raid, siege
    ) -> APIResponse:
        """
        Send attack.
        
        Args:
            from_village_id: Source village
            to_village_id: Target village
            troops: {unit_type: count}
            attack_type: Type of attack
        """
        return await self._request(
            "POST",
            f"v1/village/{from_village_id}/attack",
            data={
                "target_village_id": to_village_id,
                "troops": troops,
                "attack_type": attack_type
            },
            use_cache=False
        )
    
    async def send_reinforcement(
        self,
        from_village_id: int,
        to_village_id: int,
        troops: Dict[str, int]
    ) -> APIResponse:
        """Send defensive reinforcements"""
        return await self._request(
            "POST",
            f"v1/village/{from_village_id}/reinforce",
            data={
                "target_village_id": to_village_id,
                "troops": troops
            },
            use_cache=False
        )
    
    async def get_incoming_attacks(self, village_id: int) -> APIResponse:
        """Get incoming attacks"""
        return await self._request(
            "GET",
            f"v1/village/{village_id}/incoming",
            use_cache=True,
            cache_ttl=10  # Short TTL for real-time data
        )
    
    async def get_outgoing_attacks(self, village_id: int) -> APIResponse:
        """Get outgoing attacks"""
        return await self._request(
            "GET",
            f"v1/village/{village_id}/outgoing",
            use_cache=True,
            cache_ttl=10
        )
    
    # ============================================
    # TRADING OPERATIONS
    # ============================================
    
    async def create_trade_offer(
        self,
        village_id: int,
        offer_resource: str,
        offer_amount: int,
        request_resource: str,
        request_amount: int
    ) -> APIResponse:
        """Create marketplace trade offer"""
        return await self._request(
            "POST",
            f"v1/village/{village_id}/trade/offer",
            data={
                "offer": {
                    "resource": offer_resource,
                    "amount": offer_amount
                },
                "request": {
                    "resource": request_resource,
                    "amount": request_amount
                }
            },
            use_cache=False
        )
    
    async def send_resources(
        self,
        from_village_id: int,
        to_village_id: int,
        resources: Dict[str, int]
    ) -> APIResponse:
        """Send resources via merchants"""
        return await self._request(
            "POST",
            f"v1/village/{from_village_id}/send_resources",
            data={
                "target_village_id": to_village_id,
                "resources": resources
            },
            use_cache=False
        )
    
    async def get_market_offers(self, village_id: int) -> APIResponse:
        """Get available marketplace offers"""
        return await self._request(
            "GET",
            f"v1/village/{village_id}/market",
            use_cache=True,
            cache_ttl=60
        )
    
    # ============================================
    # INTELLIGENCE & SCOUTING
    # ============================================
    
    async def send_scout(
        self,
        from_village_id: int,
        to_village_id: int,
        scout_count: int = 1
    ) -> APIResponse:
        """Send scouts to gather intelligence"""
        return await self._request(
            "POST",
            f"v1/village/{from_village_id}/scout",
            data={
                "target_village_id": to_village_id,
                "scouts": scout_count
            },
            use_cache=False
        )
    
    async def get_scout_reports(
        self,
        player_id: int,
        limit: int = 20
    ) -> APIResponse:
        """Get recent scout reports"""
        return await self._request(
            "GET",
            f"v1/player/{player_id}/reports/scout",
            data={"limit": limit},
            use_cache=True,
            cache_ttl=120
        )
    
    # ============================================
    # ALLIANCE & DIPLOMACY
    # ============================================
    
    async def send_message(
        self,
        from_player_id: int,
        to_player_id: int,
        subject: str,
        body: str
    ) -> APIResponse:
        """Send in-game message"""
        return await self._request(
            "POST",
            f"v1/player/{from_player_id}/messages/send",
            data={
                "to": to_player_id,
                "subject": subject,
                "body": body
            },
            use_cache=False
        )
    
    async def get_messages(
        self,
        player_id: int,
        unread_only: bool = False
    ) -> APIResponse:
        """Get player messages"""
        return await self._request(
            "GET",
            f"v1/player/{player_id}/messages",
            data={"unread_only": unread_only},
            use_cache=True,
            cache_ttl=30
        )
    
    async def create_alliance(
        self,
        player_id: int,
        alliance_name: str,
        tag: str
    ) -> APIResponse:
        """Create new alliance"""
        return await self._request(
            "POST",
            f"v1/player/{player_id}/alliance/create",
            data={
                "name": alliance_name,
                "tag": tag
            },
            use_cache=False
        )
    
    async def invite_to_alliance(
        self,
        alliance_id: int,
        player_id: int
    ) -> APIResponse:
        """Invite player to alliance"""
        return await self._request(
            "POST",
            f"v1/alliance/{alliance_id}/invite",
            data={"player_id": player_id},
            use_cache=False
        )
    
    # ============================================
    # PLAYER INFORMATION
    # ============================================
    
    async def get_player_info(self, player_id: int) -> APIResponse:
        """Get player information"""
        if self._db_pool:
            return await self._get_player_db(player_id)
        
        return await self._request(
            "GET",
            f"v1/player/{player_id}",
            use_cache=True,
            cache_ttl=120
        )
    
    async def _get_player_db(self, player_id: int) -> APIResponse:
        """Get player from database"""
        try:
            async with self._db_pool.acquire() as conn:
                player = await conn.fetchrow("""
                    SELECT 
                        p.id,
                        p.username,
                        p.tribe,
                        p.rank,
                        p.population,
                        p.alliance_id,
                        a.name as alliance_name,
                        a.tag as alliance_tag
                    FROM players p
                    LEFT JOIN alliances a ON p.alliance_id = a.id
                    WHERE p.id = $1
                """, player_id)
                
                if player:
                    return APIResponse(success=True, data=dict(player))
                
                return APIResponse(success=False, error="Player not found")
        
        except Exception as e:
            return APIResponse(success=False, error=str(e))
    
    async def get_player_ranking(
        self,
        metric: str = "population",
        limit: int = 100
    ) -> APIResponse:
        """Get player rankings"""
        return await self._request(
            "GET",
            f"v1/rankings/{metric}",
            data={"limit": limit},
            use_cache=True,
            cache_ttl=300  # 5 minutes
        )


# ============================================
# RATE LIMITER
# ============================================

class RateLimiter:
    """
    Token bucket rate limiter.
    """
    
    def __init__(
        self,
        max_requests_per_second: int = 100,
        max_requests_per_minute: int = 5000
    ):
        self.tokens_per_second = max_requests_per_second
        self.tokens_per_minute = max_requests_per_minute
        
        self.tokens_second = max_requests_per_second
        self.tokens_minute = max_requests_per_minute
        
        self.last_update = datetime.now()
    
    async def acquire(self):
        """Wait until request can be made"""
        while True:
            await self._refill()
            
            if self.tokens_second > 0 and self.tokens_minute > 0:
                self.tokens_second -= 1
                self.tokens_minute -= 1
                return
            
            # Wait a bit
            await asyncio.sleep(0.01)
    
    async def _refill(self):
        """Refill token buckets"""
        now = datetime.now()
        elapsed = (now - self.last_update).total_seconds()
        
        # Refill per-second bucket
        self.tokens_second = min(
            self.tokens_per_second,
            self.tokens_second + (elapsed * self.tokens_per_second)
        )
        
        # Refill per-minute bucket
        self.tokens_minute = min(
            self.tokens_per_minute,
            self.tokens_minute + (elapsed * (self.tokens_per_minute / 60))
        )
        
        self.last_update = now
```

---

## 🔌 High-Level NPC Interface

### **Simplified NPC API Wrapper**

```python
class NPCGameInterface:
    """
    High-level interface for NPCs.
    Abstracts away API complexity.
    """
    
    def __init__(self, npc_id: int, api_client: TravianAPIClient):
        self.npc_id = npc_id
        self.api = api_client
        self._player_id = None
        self._villages = []
    
    async def initialize(self):
        """Load NPC game state"""
        # Get NPC's player ID
        state = await self._get_npc_state()
        self._player_id = state['player_id']
        
        # Load villages
        result = await self.api.get_villages(self._player_id)
        if result.success:
            self._villages = result.data
    
    async def build_in_village(
        self,
        village_id: int,
        building: str
    ) -> bool:
        """
        Build or upgrade building.
        Returns True if successful.
        """
        result = await self.api.queue_building(village_id, building)
        return result.success
    
    async def train_army(
        self,
        village_id: int,
        unit_type: str,
        count: int
    ) -> bool:
        """Train troops"""
        result = await self.api.train_troops(
            village_id,
            {unit_type: count}
        )
        return result.success
    
    async def launch_raid(
        self,
        from_village_id: int,
        target_x: int,
        target_y: int,
        troops: Dict[str, int]
    ) -> bool:
        """Launch raid on coordinates"""
        # Find target village
        target = await self._find_village_at(target_x, target_y)
        
        if target:
            result = await self.api.send_attack(
                from_village_id,
                target['id'],
                troops,
                attack_type="raid"
            )
            return result.success
        
        return False
    
    async def send_diplomatic_message(
        self,
        to_player_id: int,
        subject: str,
        message: str
    ) -> bool:
        """Send message to another player"""
        result = await self.api.send_message(
            self._player_id,
            to_player_id,
            subject,
            message
        )
        return result.success
```

---

## 🚀 Next Steps

- **PERFORMANCE-SCALING.md** - Optimize API calls
- **TESTING-MONITORING.md** - Test and monitor API

**NPCs can now interact with the game at superhuman speed!** 🚀
