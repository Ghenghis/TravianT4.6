# Advanced Strategies - Meta-Game & Long-Term Planning

## 🎯 Overview

**Current Weakness**: NPCs only think short-term (1-3 days). No long-term planning, meta-game strategies, or endgame vision.

**Solution**: Implement strategic planning frameworks, meta-game analysis, and multi-month planning capabilities.

---

## 🎓 Strategic Thinking Layers

```
Strategic Depth
├── Tactical (Hours-Days)
│   ├── Raid timing
│   ├── Building queue
│   └── Resource management
│
├── Operational (Days-Weeks)
│   ├── Village expansion
│   ├── Army building
│   └── Alliance formation
│
├── Strategic (Weeks-Months)
│   ├── Territory control
│   ├── Wonder race
│   └── Server domination
│
└── Meta-Game (Entire Server)
    ├── Win conditions
    ├── Coalition building
    └── Diplomatic chess
```

---

## 🗺️ Territory Control Strategy

### **Map Domination AI**

```python
from typing import Dict, List, Tuple, Set
from dataclasses import dataclass
import numpy as np

@dataclass
class Territory:
    """
    A controlled region of the map.
    """
    center: Tuple[int, int]  # (x, y)
    radius: int
    controller_alliance: int
    villages_count: int
    strategic_value: float
    threats: List[int]  # Enemy alliance IDs
    oases: List[Tuple[int, int]]

class TerritorialStrategy:
    """
    Long-term territorial control and expansion.
    """
    
    async def analyze_map_control(
        self,
        world_size: int = 400
    ) -> Dict:
        """
        Analyze who controls which parts of the map.
        
        Divides map into sectors and determines control.
        """
        sector_size = 50  # 50x50 sectors
        sectors = {}
        
        for x in range(-world_size, world_size, sector_size):
            for y in range(-world_size, world_size, sector_size):
                sector_key = (x, y)
                
                # Count villages per alliance in this sector
                control = await self._analyze_sector_control(
                    x, y,
                    sector_size
                )
                
                sectors[sector_key] = control
        
        # Identify strategic sectors
        strategic_sectors = self._identify_strategic_sectors(sectors)
        
        return {
            "sectors": sectors,
            "strategic_sectors": strategic_sectors,
            "control_summary": self._summarize_control(sectors)
        }
    
    async def plan_territorial_expansion(
        self,
        alliance_id: int,
        current_territory: List[Territory]
    ) -> Dict:
        """
        Plan long-term territorial expansion.
        
        Goals:
        1. Consolidate existing territory
        2. Expand toward strategic locations
        3. Block enemy expansion
        4. Secure Wonder location
        """
        # Use LLM for strategic planning
        map_analysis = await self.analyze_map_control()
        
        expansion_prompt = f"""
You are strategic advisor for Alliance {alliance_id}.

CURRENT TERRITORY:
{format_territory(current_territory)}

MAP CONTROL:
{format_map_control(map_analysis)}

STRATEGIC GOALS:
1. Control 15% of map (currently: {calculate_control_percentage(alliance_id, map_analysis)}%)
2. Secure Wonder location (coordinates TBD)
3. Block enemy Alliance {get_main_enemy(alliance_id)} expansion
4. Connect our territories (fill gaps)

PLAN a 60-day territorial expansion strategy:
- Which sectors to expand into? (priority order)
- Which enemies to displace?
- How to protect expansion villages?
- Timeline for each phase?

Response JSON: {{
    "phases": [
        {{
            "day_start": 1,
            "day_end": 20,
            "target_sectors": [...],
            "priority": "high/medium/low",
            "strategy": "...",
            "required_forces": {{...}}
        }}
    ],
    "overall_strategy": "...",
    "risk_assessment": "...",
    "success_probability": 0.0-1.0
}}
"""
        
        expansion_plan = await self.llm.generate(
            expansion_prompt,
            temperature=0.4  # More strategic, less creative
        )
        
        return json.loads(expansion_plan)
    
    def _identify_strategic_sectors(self, sectors: Dict) -> List:
        """
        Identify strategically valuable sectors.
        
        High value if:
        - Center of map (Wonder location)
        - High oasis density
        - Natural chokepoints
        - Resource-rich areas
        """
        strategic = []
        
        for coords, sector in sectors.items():
            x, y = coords
            
            # Distance from center
            dist_from_center = np.sqrt(x**2 + y**2)
            
            # Strategic value calculation
            value = 0.0
            
            # Center is valuable (Wonder)
            if dist_from_center < 50:
                value += 1.0
            
            # Oases are valuable
            value += sector['oases_count'] * 0.3
            
            # Contested areas (multiple alliances)
            if len(sector['alliances']) > 1:
                value += 0.5  # Chokepoint
            
            if value > 1.0:
                strategic.append({
                    "coords": coords,
                    "value": value,
                    "reason": self._explain_strategic_value(coords, sector, value)
                })
        
        return sorted(strategic, key=lambda x: x['value'], reverse=True)
```

---

## 🏆 Wonder Race Strategy

### **Endgame Planning**

```python
class WonderStrategy:
    """
    Plan and execute Wonder (endgame) strategy.
    
    Wonder of the World is final objective - first to build wins server.
    """
    
    async def plan_wonder_race(
        self,
        alliance_id: int,
        server_end_date: datetime
    ) -> Dict:
        """
        Create comprehensive Wonder strategy.
        
        Phases:
        1. Resource Stockpiling (60 days before end)
        2. Wonder Village Preparation (30 days)
        3. Construction Race (final 14 days)
        4. Defense of Wonder (continuous)
        """
        days_remaining = (server_end_date - datetime.now()).days
        
        if days_remaining > 60:
            # Early game - focus on growth
            return await self._plan_early_game_wonder_prep(alliance_id)
        
        elif days_remaining > 30:
            # Mid game - resource stockpiling
            return await self._plan_resource_stockpiling(alliance_id, days_remaining)
        
        elif days_remaining > 14:
            # Late game - wonder village prep
            return await self._plan_wonder_village_prep(alliance_id)
        
        else:
            # Endgame - construction race
            return await self._plan_construction_race(alliance_id, days_remaining)
    
    async def _plan_resource_stockpiling(
        self,
        alliance_id: int,
        days_remaining: int
    ) -> Dict:
        """
        Plan resource accumulation for Wonder.
        
        Wonder costs: ~100M resources
        Need to stockpile across entire alliance.
        """
        # Calculate required resources
        wonder_cost = {
            "wood": 25_000_000,
            "clay": 25_000_000,
            "iron": 25_000_000,
            "wheat": 25_000_000
        }
        
        # Current alliance resources
        current_resources = await self._get_alliance_total_resources(alliance_id)
        
        # Calculate daily production
        daily_production = await self._get_alliance_daily_production(alliance_id)
        
        # Gap analysis
        resource_gap = {
            res: wonder_cost[res] - current_resources[res]
            for res in wonder_cost
        }
        
        # Can we produce enough?
        days_to_produce = {
            res: gap / daily_production[res]
            for res, gap in resource_gap.items()
            if gap > 0
        }
        
        max_days_needed = max(days_to_produce.values()) if days_to_produce else 0
        
        if max_days_needed > days_remaining:
            # Need to boost production or raid
            return {
                "status": "behind_schedule",
                "deficit": resource_gap,
                "recommendation": "increase_raiding",
                "additional_resources_needed": {
                    res: gap for res, gap in resource_gap.items() if gap > 0
                }
            }
        
        return {
            "status": "on_track",
            "stockpile_plan": self._create_stockpile_schedule(
                resource_gap,
                days_remaining
            )
        }
    
    async def _plan_wonder_village_prep(
        self,
        alliance_id: int
    ) -> Dict:
        """
        Prepare the Wonder village.
        
        Requirements:
        - Max level buildings
        - Massive defensive army
        - Treasury full of artifacts
        - Heroic mansion for hero production
        """
        # Select Wonder village location
        wonder_location = await self._select_wonder_location(alliance_id)
        
        # LLM strategic planning
        prep_prompt = f"""
Plan Wonder village preparation for Alliance {alliance_id}.

WONDER LOCATION: {wonder_location}

REQUIREMENTS:
1. All resource fields level 20
2. Warehouse level 20 (capacity: 80,000)
3. Granary level 20 (capacity: 80,000)
4. Defensive army: 50,000+ troops
5. Wall level 20
6. Hero's mansion for artifacts

CURRENT STATUS:
{await self._get_wonder_village_status(wonder_location)}

THREATS:
{await self._identify_wonder_threats(wonder_location)}

Create 30-day preparation plan:
- Building priority order
- Troop training schedule
- Resource allocation
- Defense coordination

JSON response with detailed timeline.
"""
        
        return await self.llm.generate(prep_prompt)
```

---

## ♟️ Diplomatic Chess

### **Alliance Politics & Coalition Building**

```python
class DiplomaticChess:
    """
    Meta-game alliance politics and manipulation.
    
    Like Game of Thrones but in Travian.
    """
    
    async def analyze_power_balance(self) -> Dict:
        """
        Analyze server-wide power dynamics.
        
        Identifies:
        - Dominant alliances
        - Rising powers
        - Declining powers
        - Potential coalitions
        """
        alliances = await self._get_all_alliances()
        
        power_analysis = []
        for alliance in alliances:
            power = await self._calculate_alliance_power(alliance['id'])
            trend = await self._calculate_power_trend(alliance['id'])
            
            power_analysis.append({
                "alliance_id": alliance['id'],
                "name": alliance['name'],
                "current_power": power,
                "trend": trend,  # growing/stable/declining
                "rank": alliance['rank']
            })
        
        # Sort by power
        power_analysis.sort(key=lambda x: x['current_power'], reverse=True)
        
        # Identify coalitions
        coalitions = await self._identify_coalitions(power_analysis)
        
        return {
            "power_ranking": power_analysis,
            "coalitions": coalitions,
            "recommended_action": await self._recommend_diplomatic_action(
                power_analysis,
                coalitions
            )
        }
    
    async def execute_diplomatic_maneuver(
        self,
        alliance_id: int,
        maneuver_type: str
    ):
        """
        Execute complex diplomatic strategy.
        
        Maneuvers:
        - divide_and_conquer: Turn enemies against each other
        - coalition_building: Unite smaller alliances against strong one
        - backstab: Break alliance at opportune moment
        - appeasement: Give tribute to avoid war
        - proxy_war: Get others to fight your battles
        """
        if maneuver_type == "divide_and_conquer":
            await self._divide_enemies(alliance_id)
        
        elif maneuver_type == "coalition_building":
            await self._build_anti_hegemony_coalition(alliance_id)
        
        elif maneuver_type == "backstab":
            await self._plan_betrayal(alliance_id)
    
    async def _divide_enemies(self, alliance_id: int):
        """
        Turn enemy alliances against each other.
        
        Strategy:
        1. Identify two enemy alliances
        2. Share false intelligence with each about the other
        3. Provoke conflict between them
        4. Profit from their war
        """
        enemies = await self._get_enemy_alliances(alliance_id)
        
        if len(enemies) < 2:
            return  # Need at least 2 enemies
        
        # Select two strongest enemies
        target_a, target_b = enemies[:2]
        
        # LLM generates deception strategy
        deception_prompt = f"""
Create a strategy to turn these two enemy alliances against each other:

Alliance A: {target_a['name']}
- Power: {target_a['power']}
- Territory: {target_a['territory']}
- Current wars: {target_a['wars']}

Alliance B: {target_b['name']}
- Power: {target_b['power']}
- Territory: {target_b['territory']}
- Current wars: {target_b['wars']}

How can we make them fight each other?
Ideas:
- Fake attack from A on B (blame A)
- Spread rumors about A planning to attack B
- Offer false alliance to B if they attack A
- Use alt accounts to instigate

Create detailed deception plan (ethically questionable but valid in-game strategy).
"""
        
        strategy = await self.llm.generate(deception_prompt, temperature=0.9)
        
        # Execute (with ethical limits)
        await self._execute_deception_strategy(strategy)
```

---

## 📊 Meta-Game Pattern Recognition

### **Server-Wide Trend Analysis**

```python
class MetaGameAnalysis:
    """
    Analyze server-wide patterns and meta-game trends.
    """
    
    async def identify_winning_strategy(self) -> str:
        """
        Determine what strategy is winning on this server.
        
        Analyzes top players to find patterns:
        - Are aggressive players winning?
        - Are economic players winning?
        - Is diplomacy key?
        """
        # Get top 20 players
        top_players = await self._get_top_players(limit=20)
        
        # Analyze their strategies
        strategies = []
        for player in top_players:
            strategy = await self._classify_player_strategy(player['id'])
            strategies.append(strategy)
        
        # Count strategy types
        from collections import Counter
        strategy_counts = Counter(strategies)
        
        # Most common winning strategy
        winning_strategy = strategy_counts.most_common(1)[0][0]
        
        return {
            "winning_strategy": winning_strategy,
            "distribution": dict(strategy_counts),
            "recommendation": f"Adapt NPCs to use more {winning_strategy} strategy",
            "evidence": self._explain_why_strategy_wins(
                winning_strategy,
                top_players
            )
        }
    
    async def detect_meta_shifts(self) -> List[Dict]:
        """
        Detect when meta-game is shifting.
        
        Example: Server was aggressive early, now becoming defensive.
        """
        # Compare strategies over time
        current_meta = await self.identify_winning_strategy()
        
        # Get historical data
        past_metas = await self._get_historical_meta(days=30)
        
        shifts = []
        if past_metas and past_metas[-1] != current_meta['winning_strategy']:
            shifts.append({
                "type": "strategy_shift",
                "from": past_metas[-1],
                "to": current_meta['winning_strategy'],
                "timestamp": datetime.now(),
                "recommendation": "Update NPC strategies to match new meta"
            })
        
        return shifts
```

---

## 🎯 Adaptive Long-Term Planning

### **Multi-Month Strategy Evolution**

```python
class LongTermPlanner:
    """
    Plan NPC strategy over entire server lifespan (3-6 months).
    """
    
    async def create_server_lifecycle_strategy(
        self,
        npc_id: int,
        server_start: datetime,
        server_end: datetime
    ) -> Dict:
        """
        Create complete strategy from server start to end.
        
        Phases:
        1. Early game (days 1-30): Growth & expansion
        2. Mid game (days 30-90): Consolidation & alliance
        3. Late game (days 90-150): Preparation for wonder
        4. Endgame (days 150-180): Wonder race
        """
        total_days = (server_end - server_start).days
        current_day = (datetime.now() - server_start).days
        
        # Determine current phase
        if current_day < 30:
            phase = "early_game"
        elif current_day < 90:
            phase = "mid_game"
        elif current_day < 150:
            phase = "late_game"
        else:
            phase = "endgame"
        
        # LLM creates phase-appropriate strategy
        strategy_prompt = f"""
Create {phase} strategy for NPC {npc_id}.

SERVER INFO:
- Total duration: {total_days} days
- Current day: {current_day}
- Days remaining: {total_days - current_day}

NPC STATUS:
{await self._get_npc_status(npc_id)}

CURRENT PHASE: {phase}

Create detailed strategy considering:
- Immediate goals (next 7 days)
- Short-term goals (next 30 days)
- Long-term goals (until server end)
- Contingency plans
- Win conditions

JSON response with milestone timeline.
"""
        
        return await self.llm.generate(strategy_prompt)
```

---

## 🚀 Next Steps

- **PERSONALITY-PSYCHOLOGY.md** - Deep character modeling
- **EDGE-CASES-HANDLING.md** - Handle unusual situations
- **CROSS-WORLD-LEARNING.md** - NPCs learning across servers

**Your NPCs will think like grandmasters!** ♟️
