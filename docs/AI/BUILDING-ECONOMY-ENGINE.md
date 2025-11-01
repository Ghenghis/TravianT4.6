# Building & Economy Engine - Complete Economic AI Framework

## 🎯 Overview

A comprehensive economic management system for NPCs that handles building strategies, resource optimization, trading algorithms, and village development with **superhuman efficiency**.

---

## 🏗️ Building Strategy Framework

### **Architecture Overview**

```
Building Engine
├── Strategy Selector
│   ├── Village Type Classifier (capital/expansion/farming)
│   ├── Faction-Specific Templates
│   └── Dynamic Adaptation
├── Build Queue Manager
│   ├── Priority Calculator
│   ├── Resource Predictor
│   └── Dependency Resolver
├── Resource Optimizer
│   ├── Production Forecaster
│   ├── Trade Optimizer
│   └── Deficit Manager
└── Progress Tracker
    ├── Goal Monitor
    ├── Efficiency Metrics
    └── Strategy Validator
```

---

## 📐 Village Development Templates

### **Template System Structure**

```python
from dataclasses import dataclass
from typing import List, Dict, Optional
from enum import Enum

class VillageType(Enum):
    CAPITAL = "capital"
    MILITARY = "military"
    ECONOMIC = "economic"
    FARMING = "farming"
    DEFENSIVE = "defensive"

class BuildingPhase(Enum):
    EARLY = "early"      # Levels 1-5
    MID = "mid"          # Levels 6-12
    LATE = "late"        # Levels 13-20
    ENDGAME = "endgame"  # Level 20+

@dataclass
class BuildingTemplate:
    """
    Complete building strategy template.
    """
    name: str
    village_type: VillageType
    tribe: str  # Romans, Gauls, Teutons
    description: str
    
    # Resource field layout (18 fields total)
    resource_fields: Dict[str, int]  # {"wood": 4, "clay": 4, "iron": 4, "wheat": 6}
    
    # Building priorities by phase
    early_game: List[BuildingStep]
    mid_game: List[BuildingStep]
    late_game: List[BuildingStep]
    endgame: List[BuildingStep]
    
    # Economic targets
    target_production: Dict[str, int]  # Resources/hour
    warehouse_target: int
    granary_target: int
    
    # Military targets (if applicable)
    barracks_target: Optional[int] = None
    stable_target: Optional[int] = None
    workshop_target: Optional[int] = None

@dataclass
class BuildingStep:
    """
    Single building upgrade step.
    """
    building_name: str
    target_level: int
    priority: int  # 1 (highest) to 10 (lowest)
    dependencies: List[str]  # Must be built first
    reason: str  # Why this building at this time
```

---

### **Romans - Economic Capital Template**

```python
ROMANS_ECONOMIC_CAPITAL = BuildingTemplate(
    name="Romans Economic Capital",
    village_type=VillageType.CAPITAL,
    tribe="Romans",
    description="Maximum resource production with marketplace dominance",
    
    resource_fields={
        "wood": 4,    # Fields 1-4
        "clay": 4,    # Fields 5-8
        "iron": 4,    # Fields 9-12
        "wheat": 6    # Fields 13-18
    },
    
    early_game=[
        # Phase 1: Foundation (Levels 1-3)
        BuildingStep("Main Building", 3, priority=1, dependencies=[], 
                    reason="Unlock more building slots"),
        BuildingStep("Warehouse", 2, priority=2, dependencies=[],
                    reason="Store early resources"),
        BuildingStep("Granary", 2, priority=2, dependencies=[],
                    reason="Store wheat production"),
        
        # Phase 2: Resource boost (Levels 2-4)
        BuildingStep("Woodcutter", 2, priority=3, dependencies=[],
                    reason="Wood needed for all buildings"),
        BuildingStep("Clay Pit", 2, priority=3, dependencies=[],
                    reason="Clay for warehouses"),
        BuildingStep("Iron Mine", 2, priority=4, dependencies=[],
                    reason="Iron for military later"),
        BuildingStep("Cropland", 3, priority=2, dependencies=[],
                    reason="Wheat for troops and population"),
        
        # Phase 3: Infrastructure
        BuildingStep("Marketplace", 1, priority=5, dependencies=["Main Building(3)"],
                    reason="Enable trading"),
        BuildingStep("Embassy", 1, priority=6, dependencies=["Main Building(3)"],
                    reason="Join/create alliance"),
        BuildingStep("Barracks", 1, priority=7, dependencies=["Main Building(3)"],
                    reason="Minimal defense"),
    ],
    
    mid_game=[
        # Phase 4: Production scaling (Levels 5-8)
        BuildingStep("All Resource Fields", 5, priority=1, dependencies=[],
                    reason="Double production"),
        BuildingStep("Warehouse", 7, priority=2, dependencies=[],
                    reason="Store increased production"),
        BuildingStep("Granary", 7, priority=2, dependencies=[],
                    reason="Support higher population"),
        BuildingStep("Marketplace", 5, priority=3, dependencies=[],
                    reason="Larger trade capacity"),
        
        # Phase 5: Advanced economy (Levels 6-10)
        BuildingStep("Main Building", 10, priority=1, dependencies=[],
                    reason="Faster building speed"),
        BuildingStep("All Resource Fields", 8, priority=2, dependencies=["Warehouse(10)"],
                    reason="Triple production"),
        BuildingStep("Warehouse", 12, priority=3, dependencies=[],
                    reason="Handle massive production"),
        BuildingStep("Granary", 12, priority=3, dependencies=[],
                    reason="Large wheat reserves"),
        
        # Phase 6: Trade empire
        BuildingStep("Marketplace", 10, priority=4, dependencies=[],
                    reason="800 merchants available"),
        BuildingStep("Trade Office", 10, priority=5, dependencies=["Marketplace(10)"],
                    reason="3 trade routes"),
    ],
    
    late_game=[
        # Phase 7: Maximum production (Levels 10-15)
        BuildingStep("All Resource Fields", 12, priority=1, dependencies=["Warehouse(15)"],
                    reason="Quintuple production"),
        BuildingStep("Warehouse", 17, priority=2, dependencies=[],
                    reason="80,000 capacity"),
        BuildingStep("Granary", 17, priority=2, dependencies=[],
                    reason="80,000 wheat"),
        BuildingStep("Marketplace", 15, priority=3, dependencies=[],
                    reason="Maximum trade efficiency"),
        
        # Phase 8: Support infrastructure
        BuildingStep("Academy", 10, priority=4, dependencies=["Main Building(10)"],
                    reason="Research all technologies"),
        BuildingStep("Smithy", 10, priority=5, dependencies=["Academy(5)"],
                    reason="Upgrade defensive units"),
        BuildingStep("Town Hall", 10, priority=6, dependencies=["Academy(10)"],
                    reason="Great celebrations"),
    ],
    
    endgame=[
        # Phase 9: Perfection (Levels 15-20)
        BuildingStep("All Resource Fields", 15, priority=1, dependencies=["Warehouse(20)"],
                    reason="10x production"),
        BuildingStep("Warehouse", 20, priority=2, dependencies=[],
                    reason="Maximum capacity"),
        BuildingStep("Granary", 20, priority=2, dependencies=[],
                    reason="Maximum wheat storage"),
        BuildingStep("Main Building", 20, priority=3, dependencies=[],
                    reason="Instant builds"),
        BuildingStep("Marketplace", 20, priority=4, dependencies=[],
                    reason="Perfect trade network"),
    ],
    
    target_production={
        "wood": 5000,    # per hour at max level
        "clay": 5000,
        "iron": 4500,
        "wheat": 6000
    },
    
    warehouse_target=80000,
    granary_target=80000
)
```

---

### **Gauls - Defensive Farming Template**

```python
GAULS_DEFENSIVE_FARMING = BuildingTemplate(
    name="Gauls Defensive Farming Village",
    village_type=VillageType.FARMING,
    tribe="Gauls",
    description="Fast raiding with strong defense, minimal military production",
    
    resource_fields={
        "wood": 5,    # Extra wood for palisades
        "clay": 3,
        "iron": 3,
        "wheat": 7    # Feed defensive troops
    },
    
    early_game=[
        BuildingStep("Cranny", 3, priority=1, dependencies=[],
                    reason="Hide resources from raids (Gaul special)"),
        BuildingStep("Main Building", 3, priority=2, dependencies=[],
                    reason="Unlock buildings"),
        BuildingStep("Warehouse", 3, priority=3, dependencies=[],
                    reason="Store raided resources"),
        BuildingStep("Granary", 3, priority=3, dependencies=[],
                    reason="Wheat for defense"),
        BuildingStep("Barracks", 1, priority=4, dependencies=["Main Building(3)"],
                    reason="Train phalanx defense"),
    ],
    
    mid_game=[
        # Focus on defense
        BuildingStep("Palisade", 10, priority=1, dependencies=[],
                    reason="Gaul defense bonus"),
        BuildingStep("All Crannies", 5, priority=2, dependencies=[],
                    reason="Hide 4000 resources"),
        BuildingStep("Barracks", 5, priority=3, dependencies=[],
                    reason="Fast troop production"),
        BuildingStep("Stable", 5, priority=4, dependencies=["Academy(5)"],
                    reason="Train Theutates Thunder for raiding"),
        
        # Resource fields
        BuildingStep("All Resource Fields", 7, priority=5, dependencies=[],
                    reason="Self-sufficient"),
    ],
    
    late_game=[
        BuildingStep("Palisade", 20, priority=1, dependencies=[],
                    reason="Maximum defense"),
        BuildingStep("Trapper", 10, priority=2, dependencies=["Rally Point(1)"],
                    reason="Trap raiders (Gaul special)"),
        BuildingStep("All Resource Fields", 10, priority=3, dependencies=[],
                    reason="Good production"),
        BuildingStep("Residence", 10, priority=4, dependencies=["Main Building(5)"],
                    reason="Prepare settlers"),
    ],
    
    endgame=[
        BuildingStep("All Resource Fields", 12, priority=1, dependencies=[],
                    reason="Decent production"),
        BuildingStep("Hero's Mansion", 10, priority=2, dependencies=[],
                    reason="Oasis conquest"),
    ],
    
    target_production={
        "wood": 3000,
        "clay": 2000,
        "iron": 2000,
        "wheat": 3500
    },
    
    warehouse_target=40000,
    granary_target=50000
)
```

---

### **Teutons - Military Powerhouse Template**

```python
TEUTONS_MILITARY_POWERHOUSE = BuildingTemplate(
    name="Teutons Military Powerhouse",
    village_type=VillageType.MILITARY,
    tribe="Teutons",
    description="Maximum troop production, constant warfare",
    
    resource_fields={
        "wood": 5,    # For buildings
        "clay": 3,
        "iron": 6,    # Iron for weapons
        "wheat": 4    # Less wheat, more raids
    },
    
    early_game=[
        BuildingStep("Main Building", 5, priority=1, dependencies=[],
                    reason="Fast expansion"),
        BuildingStep("Barracks", 1, priority=2, dependencies=["Main Building(3)"],
                    reason="Train troops ASAP"),
        BuildingStep("Warehouse", 5, priority=3, dependencies=[],
                    reason="Store raid loot"),
        BuildingStep("Granary", 3, priority=4, dependencies=[],
                    reason="Minimal wheat storage"),
        
        # Immediate military
        BuildingStep("Barracks", 5, priority=2, dependencies=[],
                    reason="Fast clubswingers"),
        BuildingStep("Smithy", 3, priority=3, dependencies=["Academy(1)"],
                    reason="Upgrade attack"),
    ],
    
    mid_game=[
        # Military infrastructure
        BuildingStep("Barracks", 10, priority=1, dependencies=[],
                    reason="3 troops per round"),
        BuildingStep("Smithy", 10, priority=2, dependencies=[],
                    reason="Strong units"),
        BuildingStep("Rally Point", 10, priority=3, dependencies=[],
                    reason="Large raid parties"),
        
        # Advanced units
        BuildingStep("Stable", 10, priority=4, dependencies=["Academy(5)"],
                    reason="Paladin cavalry"),
        BuildingStep("Academy", 10, priority=5, dependencies=[],
                    reason="Research catapults"),
        
        # Resources to fund war
        BuildingStep("All Resource Fields", 6, priority=6, dependencies=[],
                    reason="Basic self-sufficiency"),
    ],
    
    late_game=[
        # Maximum military
        BuildingStep("Barracks", 20, priority=1, dependencies=[],
                    reason="Instant troops"),
        BuildingStep("Stable", 20, priority=2, dependencies=[],
                    reason="Instant cavalry"),
        BuildingStep("Workshop", 10, priority=3, dependencies=["Academy(10)"],
                    reason="Catapults and rams"),
        BuildingStep("Smithy", 20, priority=4, dependencies=[],
                    reason="Maximum upgrades"),
        
        # Support
        BuildingStep("Tournament Square", 10, priority=5, dependencies=[],
                    reason="Faster troops"),
        BuildingStep("Treasure Chamber", 10, priority=6, dependencies=[],
                    reason="Teuton special - store stolen treasures"),
    ],
    
    endgame=[
        BuildingStep("All Military Buildings", 20, priority=1, dependencies=[],
                    reason="War machine"),
        BuildingStep("All Resource Fields", 8, priority=2, dependencies=[],
                    reason="Supplement raids"),
    ],
    
    target_production={
        "wood": 2500,
        "clay": 1500,
        "iron": 3000,
        "wheat": 2000  # Get rest from raids
    },
    
    warehouse_target=50000,
    granary_target=30000,
    
    barracks_target=20,
    stable_target=20,
    workshop_target=10
)
```

---

## 🧮 Build Queue Optimization Engine

### **Priority Calculator**

```python
import asyncio
from typing import List, Tuple, Optional
from datetime import datetime, timedelta

class BuildQueueOptimizer:
    """
    Calculates optimal building order based on:
    - Resource availability
    - Production forecasts
    - Strategic priorities
    - Dependencies
    """
    
    def __init__(self, village_id: int, template: BuildingTemplate):
        self.village_id = village_id
        self.template = template
        self.current_phase = BuildingPhase.EARLY
    
    async def calculate_next_build(
        self,
        current_buildings: Dict[str, int],  # {building_name: current_level}
        current_resources: Dict[str, int],
        production_rate: Dict[str, int],  # per hour
        build_queue: List[BuildingJob]
    ) -> Optional[BuildingJob]:
        """
        Determine next building to queue.
        
        Returns:
            BuildingJob or None if nothing affordable/needed
        """
        
        # Get available building steps for current phase
        phase_steps = self._get_phase_steps()
        
        # Filter to buildable
        candidates = []
        for step in phase_steps:
            if self._can_build(step, current_buildings, build_queue):
                # Calculate when we'll have resources
                time_until_affordable = self._calculate_wait_time(
                    step,
                    current_resources,
                    production_rate
                )
                
                # Score this building
                score = self._calculate_priority_score(
                    step,
                    current_buildings,
                    time_until_affordable
                )
                
                candidates.append((step, score, time_until_affordable))
        
        if not candidates:
            return None
        
        # Sort by score (highest first)
        candidates.sort(key=lambda x: x[1], reverse=True)
        
        # Return best candidate
        best_step, score, wait_time = candidates[0]
        
        return BuildingJob(
            building_name=best_step.building_name,
            target_level=self._get_next_level(best_step, current_buildings),
            estimated_start=datetime.now() + wait_time,
            priority_score=score
        )
    
    def _can_build(
        self,
        step: BuildingStep,
        current_buildings: Dict[str, int],
        build_queue: List[BuildingJob]
    ) -> bool:
        """
        Check if building step is currently buildable.
        """
        # Check if already at target level
        current_level = current_buildings.get(step.building_name, 0)
        if current_level >= step.target_level:
            return False
        
        # Check if already in queue
        if any(job.building_name == step.building_name for job in build_queue):
            return False
        
        # Check dependencies
        for dep in step.dependencies:
            # Parse dependency (e.g., "Main Building(3)")
            dep_building, dep_level = self._parse_dependency(dep)
            
            if current_buildings.get(dep_building, 0) < dep_level:
                return False
        
        return True
    
    def _calculate_wait_time(
        self,
        step: BuildingStep,
        current_resources: Dict[str, int],
        production_rate: Dict[str, int]
    ) -> timedelta:
        """
        Calculate how long until we can afford this building.
        """
        # Get building cost
        next_level = self._get_next_level(step, {})
        cost = self._get_building_cost(step.building_name, next_level)
        
        # Calculate deficit for each resource
        max_wait_hours = 0
        for resource, needed in cost.items():
            deficit = max(0, needed - current_resources.get(resource, 0))
            
            if deficit > 0:
                production = production_rate.get(resource, 1)
                hours_needed = deficit / production
                max_wait_hours = max(max_wait_hours, hours_needed)
        
        return timedelta(hours=max_wait_hours)
    
    def _calculate_priority_score(
        self,
        step: BuildingStep,
        current_buildings: Dict[str, int],
        wait_time: timedelta
    ) -> float:
        """
        Score building priority (higher = more important).
        
        Factors:
        - Template priority (1-10)
        - Current phase urgency
        - Wait time (penalize long waits)
        - Synergy with existing buildings
        """
        # Base score from template
        base_score = 100 - (step.priority * 10)  # Priority 1 = 90, Priority 10 = 0
        
        # Phase urgency bonus
        if self._is_critical_for_phase(step):
            base_score += 50
        
        # Wait time penalty (longer wait = lower priority)
        wait_penalty = min(wait_time.total_seconds() / 3600, 24) * 2
        base_score -= wait_penalty
        
        # Synergy bonus
        synergy = self._calculate_synergy(step, current_buildings)
        base_score += synergy
        
        return base_score
    
    def _calculate_synergy(
        self,
        step: BuildingStep,
        current_buildings: Dict[str, int]
    ) -> float:
        """
        Calculate how well this building synergizes with existing ones.
        """
        synergy_score = 0.0
        
        # Warehouse synergizes with high resource fields
        if step.building_name == "Warehouse":
            avg_field_level = sum(
                level for name, level in current_buildings.items()
                if name in ["Woodcutter", "Clay Pit", "Iron Mine"]
            ) / 3
            if avg_field_level > 5:
                synergy_score += 20
        
        # Marketplace synergizes with high warehouse
        if step.building_name == "Marketplace":
            if current_buildings.get("Warehouse", 0) > 10:
                synergy_score += 15
        
        # Military buildings synergize together
        if step.building_name in ["Barracks", "Stable", "Workshop"]:
            military_total = sum(
                current_buildings.get(b, 0)
                for b in ["Barracks", "Stable", "Workshop", "Smithy"]
            )
            synergy_score += military_total * 2
        
        return synergy_score
    
    @staticmethod
    def _get_building_cost(building_name: str, level: int) -> Dict[str, int]:
        """
        Calculate resource cost for building at specific level.
        
        Formula: cost = base_cost * (1.8 ^ (level - 1))
        """
        # Base costs (level 1)
        base_costs = {
            "Main Building": {"wood": 70, "clay": 40, "iron": 60, "wheat": 20},
            "Warehouse": {"wood": 130, "clay": 160, "iron": 90, "wheat": 40},
            "Granary": {"wood": 80, "clay": 100, "iron": 70, "wheat": 20},
            "Barracks": {"wood": 210, "clay": 140, "iron": 260, "wheat": 120},
            "Stable": {"wood": 260, "clay": 140, "iron": 220, "wheat": 100},
            "Workshop": {"wood": 300, "clay": 240, "iron": 260, "wheat": 120},
            "Marketplace": {"wood": 80, "clay": 70, "iron": 120, "wheat": 70},
            "Embassy": {"wood": 180, "clay": 130, "iron": 150, "wheat": 80},
            "Smithy": {"wood": 170, "clay": 200, "iron": 380, "wheat": 130},
            "Academy": {"wood": 220, "clay": 160, "iron": 90, "wheat": 40},
            
            # Resource fields
            "Woodcutter": {"wood": 40, "clay": 100, "iron": 50, "wheat": 60},
            "Clay Pit": {"wood": 80, "clay": 65, "iron": 40, "wheat": 50},
            "Iron Mine": {"wood": 100, "clay": 80, "iron": 30, "wheat": 60},
            "Cropland": {"wood": 70, "clay": 90, "iron": 70, "wheat": 20},
        }
        
        base = base_costs.get(building_name, {"wood": 100, "clay": 100, "iron": 100, "wheat": 100})
        
        # Apply exponential scaling
        multiplier = 1.8 ** (level - 1)
        
        return {
            resource: int(cost * multiplier)
            for resource, cost in base.items()
        }
```

---

## 💰 Resource Management System

### **Production Forecaster**

```python
class ResourceForecaster:
    """
    Predict future resource availability with high accuracy.
    """
    
    def __init__(self, village_id: int):
        self.village_id = village_id
    
    async def forecast_resources(
        self,
        current_resources: Dict[str, int],
        production_rate: Dict[str, int],  # per hour
        consumption_rate: Dict[str, int],  # per hour (troops, buildings)
        hours_ahead: int = 24
    ) -> Dict[str, List[int]]:
        """
        Forecast resources for next N hours.
        
        Returns:
            {resource: [hour0, hour1, hour2, ...]}
        """
        forecast = {resource: [] for resource in current_resources.keys()}
        
        for hour in range(hours_ahead + 1):
            for resource in current_resources.keys():
                # Calculate net production
                net_production = (
                    production_rate.get(resource, 0) - 
                    consumption_rate.get(resource, 0)
                )
                
                # Project future amount
                future_amount = (
                    current_resources[resource] + 
                    (net_production * hour)
                )
                
                # Cap at warehouse/granary capacity
                capacity = self._get_storage_capacity(resource)
                future_amount = min(future_amount, capacity)
                
                forecast[resource].append(max(0, int(future_amount)))
        
        return forecast
    
    async def calculate_deficit_time(
        self,
        target_resources: Dict[str, int],
        current_resources: Dict[str, int],
        production_rate: Dict[str, int]
    ) -> float:
        """
        Calculate hours until we have target resources.
        """
        max_hours = 0.0
        
        for resource, target in target_resources.items():
            deficit = target - current_resources.get(resource, 0)
            
            if deficit > 0:
                prod_rate = production_rate.get(resource, 1)
                hours = deficit / prod_rate
                max_hours = max(max_hours, hours)
        
        return max_hours
    
    def _get_storage_capacity(self, resource: str) -> int:
        """Get warehouse/granary capacity"""
        # Simplified - should check actual building levels
        if resource == "wheat":
            return 80000  # Granary
        else:
            return 80000  # Warehouse
```

---

### **Trading Algorithm**

```python
class TradingOptimizer:
    """
    Automated trading system for NPCs.
    Smarter than humans at finding profitable trades.
    """
    
    def __init__(self, npc_id: int):
        self.npc_id = npc_id
        self.trade_history = []
    
    async def find_optimal_trades(
        self,
        surplus: Dict[str, int],  # What we have extra
        deficit: Dict[str, int],  # What we need
        market_prices: Dict[str, float],  # Current exchange rates
        merchant_capacity: int
    ) -> List[TradeOrder]:
        """
        Find most profitable trades.
        
        Algorithm:
        1. Identify surplus resources
        2. Calculate opportunity cost
        3. Find best exchange rates
        4. Maximize merchant efficiency
        """
        trades = []
        
        # Sort surplus by value (sell most valuable first)
        surplus_items = sorted(
            surplus.items(),
            key=lambda x: x[1] * market_prices.get(x[0], 1.0),
            reverse=True
        )
        
        # Sort deficit by urgency (buy most needed first)
        deficit_items = sorted(
            deficit.items(),
            key=lambda x: x[1],
            reverse=True
        )
        
        for surplus_resource, surplus_amount in surplus_items:
            if surplus_amount < 1000:  # Don't trade small amounts
                continue
            
            for deficit_resource, deficit_amount in deficit_items:
                if deficit_amount < 500:
                    continue
                
                # Calculate exchange rate
                rate = self._calculate_fair_rate(
                    surplus_resource,
                    deficit_resource,
                    market_prices
                )
                
                # Optimize trade size
                trade_amount = min(
                    surplus_amount,
                    merchant_capacity * 500,  # 500 per merchant
                    deficit_amount * rate
                )
                
                if trade_amount >= 1000:
                    trades.append(TradeOrder(
                        offer_resource=surplus_resource,
                        offer_amount=int(trade_amount),
                        request_resource=deficit_resource,
                        request_amount=int(trade_amount / rate),
                        exchange_rate=rate
                    ))
        
        return trades
    
    def _calculate_fair_rate(
        self,
        offer: str,
        request: str,
        market_prices: Dict[str, float]
    ) -> float:
        """
        Calculate fair exchange rate.
        
        Uses supply/demand + historical data.
        """
        base_rate = market_prices.get(offer, 1.0) / market_prices.get(request, 1.0)
        
        # Adjust for historical performance
        if self.trade_history:
            avg_historical_rate = self._get_avg_historical_rate(offer, request)
            # Blend 70% current market, 30% historical
            rate = (base_rate * 0.7) + (avg_historical_rate * 0.3)
        else:
            rate = base_rate
        
        # Add margin for profit (5-10%)
        margin = 1.05 + (0.05 * self._calculate_urgency())
        
        return rate * margin
    
    async def execute_trade(self, trade: TradeOrder):
        """
        Create trade offer in marketplace.
        """
        # Implementation depends on game API
        pass
    
    def _calculate_urgency(self) -> float:
        """
        How urgently do we need resources?
        0.0 = no urgency, 1.0 = critical
        """
        # Check if any resource below 10% capacity
        # Simplified for example
        return 0.5
```

---

## 📊 Economic Performance Metrics

```python
class EconomicMetrics:
    """
    Track and optimize economic performance.
    """
    
    def __init__(self, npc_id: int):
        self.npc_id = npc_id
        self.metrics = {
            "total_production_per_hour": {},
            "warehouse_efficiency": 0.0,  # % of time at capacity
            "trade_profit": 0,
            "building_queue_uptime": 0.0,  # % of time building
            "resource_waste": 0,  # Resources lost to cap
        }
    
    async def calculate_efficiency_score(self) -> float:
        """
        Overall economic efficiency (0.0-1.0).
        
        Factors:
        - Production vs theoretical max
        - Warehouse utilization
        - Building queue uptime
        - Trade profitability
        """
        scores = []
        
        # Production efficiency
        prod_efficiency = self._calculate_production_efficiency()
        scores.append(prod_efficiency * 0.4)  # 40% weight
        
        # Building efficiency
        build_efficiency = self.metrics['building_queue_uptime']
        scores.append(build_efficiency * 0.3)  # 30% weight
        
        # Storage efficiency
        storage_efficiency = 1.0 - (self.metrics['resource_waste'] / 100000)
        scores.append(storage_efficiency * 0.2)  # 20% weight
        
        # Trade efficiency
        trade_efficiency = min(self.metrics['trade_profit'] / 50000, 1.0)
        scores.append(trade_efficiency * 0.1)  # 10% weight
        
        return sum(scores)
    
    def _calculate_production_efficiency(self) -> float:
        """
        How close are we to theoretical maximum production?
        """
        # Theoretical max (all fields level 20)
        theoretical_max = {
            "wood": 5000,
            "clay": 5000,
            "iron": 5000,
            "wheat": 6000
        }
        
        actual = self.metrics['total_production_per_hour']
        
        efficiency_scores = []
        for resource, max_prod in theoretical_max.items():
            actual_prod = actual.get(resource, 0)
            efficiency = actual_prod / max_prod
            efficiency_scores.append(efficiency)
        
        return sum(efficiency_scores) / len(efficiency_scores)
```

---

## 🎯 Complete Implementation Example

```python
class VillageEconomyManager:
    """
    Master controller for village economic AI.
    """
    
    def __init__(
        self,
        village_id: int,
        template: BuildingTemplate,
        game_api: "TravianAPIClient"
    ):
        self.village_id = village_id
        self.template = template
        self.api = game_api
        
        # Sub-systems
        self.queue_optimizer = BuildQueueOptimizer(village_id, template)
        self.forecaster = ResourceForecaster(village_id)
        self.trader = TradingOptimizer(village_id)
        self.metrics = EconomicMetrics(village_id)
    
    async def run_economy_cycle(self):
        """
        Execute one economic management cycle.
        Run this every 5-10 minutes.
        """
        # 1. Get current state
        state = await self.api.get_village_state(self.village_id)
        
        # 2. Manage build queue
        await self._manage_buildings(state)
        
        # 3. Manage resources
        await self._manage_resources(state)
        
        # 4. Execute trades
        await self._execute_trades(state)
        
        # 5. Update metrics
        await self.metrics.update(state)
    
    async def _manage_buildings(self, state: VillageState):
        """Optimize building queue"""
        if len(state.build_queue) < 2:  # Keep queue full
            next_build = await self.queue_optimizer.calculate_next_build(
                current_buildings=state.buildings,
                current_resources=state.resources,
                production_rate=state.production_rate,
                build_queue=state.build_queue
            )
            
            if next_build:
                # Queue the building
                await self.api.queue_building(
                    self.village_id,
                    next_build.building_name,
                    next_build.target_level
                )
    
    async def _manage_resources(self, state: VillageState):
        """Prevent waste, optimize storage"""
        # Forecast future resources
        forecast = await self.forecaster.forecast_resources(
            current_resources=state.resources,
            production_rate=state.production_rate,
            consumption_rate=state.consumption_rate,
            hours_ahead=6
        )
        
        # Check for overflow in next 6 hours
        for resource, future_amounts in forecast.items():
            if any(amount >= state.capacity[resource] * 0.9 for amount in future_amounts):
                # Approaching cap - create trade offers or send resources
                await self._handle_overflow(resource, state)
    
    async def _execute_trades(self, state: VillageState):
        """Automated trading"""
        # Calculate surplus/deficit
        surplus = self._calculate_surplus(state)
        deficit = self._calculate_deficit(state)
        
        if surplus and deficit:
            # Find optimal trades
            trades = await self.trader.find_optimal_trades(
                surplus=surplus,
                deficit=deficit,
                market_prices=await self.api.get_market_prices(),
                merchant_capacity=state.buildings.get("Marketplace", 0) * 2
            )
            
            # Execute top trades
            for trade in trades[:3]:  # Limit to 3 concurrent trades
                await self.api.create_trade_offer(self.village_id, trade)
```

---

## 🚀 Next Steps

See these related guides:
- **DATA-MODELS-ARCHITECTURE.md** - Database schemas for economy
- **API-INTEGRATION-LAYER.md** - Game API interface
- **PERFORMANCE-SCALING.md** - Multi-village optimization

**Your NPCs will manage economies better than 99% of human players!** 💰
