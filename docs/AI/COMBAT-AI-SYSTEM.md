# Combat AI System - Advanced Battle Intelligence

## 🎯 Goal

Create AI combatants that are **smarter and faster than human players** at:
- Battle simulation & prediction
- Optimal troop composition
- Perfect timing of attacks
- Coordinated multi-wave assaults
- Defensive positioning

---

## ⚔️ Combat AI Layers

### **Layer 1: Battle Simulator** (Rule-Based)

```python
class BattleSimulator:
    """
    Instant battle outcome calculation (milliseconds).
    100% accurate based on game formulas.
    """
    
    def simulate_battle(
        self,
        attacker_troops: Dict[str, int],
        defender_troops: Dict[str, int],
        wall_level: int = 0,
        hero_bonus: Dict = None
    ) -> Dict:
        """
        Simulate battle outcome before sending troops.
        
        Returns:
            {
                "outcome": "win/loss/close",
                "attacker_losses": {...},
                "defender_losses": {...},
                "casualties_ratio": 0.0-1.0,
                "confidence": 0.0-1.0
            }
        """
        # Get unit stats
        atk_units = self._get_unit_stats(attacker_troops, "offensive")
        def_units = self._get_unit_stats(defender_troops, "defensive")
        
        # Apply modifiers
        wall_bonus = 1 + (wall_level * 0.02)  # +2% defense per level
        def_units['power'] *= wall_bonus
        
        # Calculate battle power
        atk_power = sum(
            count * stats['attack'] 
            for unit, count in attacker_troops.items()
            for stats in [atk_units[unit]]
        )
        
        def_power = sum(
            count * stats['defense'] 
            for unit, count in defender_troops.items()
            for stats in [def_units[unit]]
        )
        
        # Determine outcome
        power_ratio = atk_power / (def_power + 1)
        
        if power_ratio > 1.5:
            outcome = "decisive_win"
            atk_losses_pct = 0.10  # Lose 10% of troops
            def_losses_pct = 1.00  # Total destruction
        elif power_ratio > 1.1:
            outcome = "win"
            atk_losses_pct = 0.30
            def_losses_pct = 0.90
        elif power_ratio > 0.9:
            outcome = "close"
            atk_losses_pct = 0.50
            def_losses_pct = 0.50
        else:
            outcome = "loss"
            atk_losses_pct = 0.80
            def_losses_pct = 0.20
        
        return {
            "outcome": outcome,
            "attacker_losses": self._calculate_losses(attacker_troops, atk_losses_pct),
            "defender_losses": self._calculate_losses(defender_troops, def_losses_pct),
            "power_ratio": power_ratio,
            "confidence": min(abs(power_ratio - 1.0), 0.99)
        }
```

---

### **Layer 2: Strategic Attack Planning** (LLM-Enhanced)

```python
class StrategicAttackPlanner:
    """
    Uses LLM to plan complex multi-village attacks.
    """
    
    async def plan_attack(
        self,
        target_village: Village,
        available_forces: Dict[str, List[Troop]],
        intelligence: Dict
    ) -> AttackPlan:
        """
        Create sophisticated attack plan using LLM.
        """
        
        # Gather intelligence
        target_analysis = await self._analyze_target(target_village, intelligence)
        
        # LLM strategic planning
        plan_prompt = f"""
You are a master Travian tactician planning an attack.

TARGET ANALYSIS:
- Village: {target_village.name}
- Owner: {target_village.owner} (Rank #{target_village.owner_rank})
- Population: {target_village.population}
- Estimated Defense: {target_analysis['est_defense']}
- Wall Level: {target_analysis['wall_level']}
- Treasury: {target_analysis['has_treasury']}
- Recent Activity: Last online {target_analysis['last_online']} hours ago

YOUR FORCES:
{self._format_available_forces(available_forces)}

INTELLIGENCE:
- Player typically online: {intelligence['active_hours']}
- Recent battles: {intelligence['recent_battles']}
- Alliance: {intelligence['alliance'] or 'None'}
- Likely response: {intelligence['response_pattern']}

MISSION OBJECTIVE: {self._determine_objective(target_analysis)}

Plan the attack considering:
1. Send waves to minimize losses
2. Time arrival during offline hours
3. Account for potential reinforcements
4. Maximize resource capture
5. Consider counter-attack risk

Respond with JSON:
{{
  "attack_waves": [
    {{
      "wave_number": 1,
      "units": {{"legionnaire": 100, ...}},
      "launch_time": "YYYY-MM-DD HH:MM",
      "purpose": "scout/fake/real/cleanup",
      "from_village": "village_id"
    }}
  ],
  "expected_outcome": "total_victory/victory/risky/avoid",
  "resource_gain_estimate": 50000,
  "risk_assessment": {{"level": "low/medium/high", "reasons": [...]}}
  "contingency_plans": [...]
}}
"""
        
        llm_response = await self.llm.generate(plan_prompt, max_tokens=1000)
        attack_plan = json.loads(llm_response)
        
        # Validate plan with battle simulator
        validated_plan = await self._validate_with_simulator(attack_plan, target_analysis)
        
        return validated_plan
```

---

## 🎯 Attack Strategies

### **1. Perfect Scout Timing**

```python
class ScoutingAI:
    """
    AI never wastes scouts - always knows when to spy.
    """
    
    async def should_scout(self, target: Village) -> bool:
        """
        Determine if scouting is worth it.
        """
        # Check cache first (recent intelligence)
        cached_intel = await self.intelligence_cache.get(target.id)
        
        if cached_intel and cached_intel['age'] < 6:  # Less than 6 hours old
            return False  # Use cached data
        
        # Calculate scout ROI
        scout_cost = 200  # Cost of scouts
        potential_raid_value = target.estimated_resources
        
        # Scout if potential value > 10x cost
        return potential_raid_value > (scout_cost * 10)
    
    async def analyze_scout_report(self, report: ScoutReport):
        """
        Extract maximum intelligence from scout report.
        """
        intelligence = {
            "troops": report.troops,
            "resources": report.resources,
            "wall_level": report.wall_level,
            "timestamp": datetime.now(),
            
            # Derived intelligence
            "defensive_strength": self._calculate_defense_power(report.troops),
            "raid_worthiness": self._calculate_raid_score(report.resources),
            "optimal_attack_force": self._calculate_needed_force(report.troops),
            "estimated_player_type": self._infer_player_type(report)
        }
        
        # Store in intelligence database
        await self.intelligence_cache.set(report.village_id, intelligence)
        
        return intelligence
    
    def _infer_player_type(self, report: ScoutReport) -> str:
        """
        Determine player strategy from their troops/resources.
        """
        if report.troops['total'] < 100 and report.resources['total'] > 100000:
            return "farmer"  # High resources, low defense
        elif report.troops['cavalry'] > report.troops['infantry'] * 2:
            return "raider"  # Offense focused
        elif report.troops['defensive'] > report.troops['offensive'] * 2:
            return "turtle"  # Defense focused
        else:
            return "balanced"
```

---

### **2. Wave Attack Coordination**

```python
class WaveAttackCoordinator:
    """
    Coordinate multi-wave attacks with perfect timing.
    Humans can't match this precision.
    """
    
    async def execute_wave_attack(
        self,
        target: Village,
        total_force: Dict[str, int],
        wave_count: int = 3
    ):
        """
        Split attack into multiple waves for maximum efficiency.
        
        Wave 1: Scout/fake (small, fast units)
        Wave 2: Main force (arrives 1 second after wave 1)
        Wave 3: Cleanup (slow, heavy units)
        """
        
        # Calculate travel time
        distance = self.calculate_distance(self.village, target)
        travel_time = self.calculate_travel_time(distance, unit_speed="cavalry")
        
        # Design wave composition
        waves = [
            {
                "type": "fake",
                "units": {"pathfinder": 1},  # Single scout to trigger defenses
                "arrival_offset": 0  # Arrives first
            },
            {
                "type": "main",
                "units": {
                    "theutates_thunder": 200,  # Fast Gaul cavalry
                    "haeduan": 150
                },
                "arrival_offset": 1  # Arrives 1 second after fake
            },
            {
                "type": "cleanup",
                "units": {
                    "trebuchet": 20,  # Destroy buildings
                    "swordsman": 100  # Finish remaining defense
                },
                "arrival_offset": 300  # Arrives 5 minutes later
            }
        ]
        
        # Calculate precise launch times
        target_arrival = await self.calculate_optimal_arrival(target)
        
        for wave in waves:
            wave_travel_time = self.calculate_travel_time(
                distance,
                self._get_slowest_unit_speed(wave['units'])
            )
            
            launch_time = target_arrival - wave_travel_time - wave['arrival_offset']
            
            # Schedule attack with millisecond precision
            await self.schedule_attack(
                target=target,
                units=wave['units'],
                launch_at=launch_time,
                wave_type=wave['type']
            )
    
    async def calculate_optimal_arrival(self, target: Village) -> datetime:
        """
        Calculate best time for attack to arrive.
        """
        # Analyze player activity patterns
        activity = await self.get_activity_pattern(target.owner_id)
        
        # Find longest offline period
        offline_window = activity['offline_windows']  # [(start, end), ...]
        longest_window = max(offline_window, key=lambda x: x[1] - x[0])
        
        # Arrive in middle of offline window
        optimal_time = longest_window[0] + (longest_window[1] - longest_window[0]) / 2
        
        return optimal_time
```

---

### **3. Defensive Positioning**

```python
class DefensiveAI:
    """
    AI calculates perfect defense distribution.
    """
    
    async def distribute_defense(
        self,
        my_villages: List[Village],
        threat_level: Dict[str, float]  # village_id -> threat (0.0-1.0)
    ):
        """
        Distribute defensive troops optimally across villages.
        """
        total_defense = sum(v.defensive_troops for v in my_villages)
        
        # Calculate optimal distribution
        for village in my_villages:
            threat = threat_level.get(village.id, 0.5)
            
            # High threat = more defense
            optimal_defense = int(total_defense * (threat / sum(threat_level.values())))
            current_defense = village.defensive_troops
            
            if current_defense < optimal_defense:
                # Send reinforcements
                deficit = optimal_defense - current_defense
                await self.send_reinforcements(village, deficit)
            
            elif current_defense > optimal_defense * 1.5:
                # Redistribute excess
                excess = current_defense - optimal_defense
                weakest = min(my_villages, key=lambda v: v.defensive_troops)
                await self.transfer_troops(village, weakest, excess)
    
    async def predict_attack(self, incoming_attack: IncomingAttack):
        """
        Predict attack type and respond perfectly.
        """
        # Analyze attacker's patterns
        attacker_history = await self.get_attack_history(incoming_attack.attacker_id)
        
        # LLM prediction
        prediction = await self.llm.generate(f"""
        Incoming attack analysis:
        
        Attacker: {incoming_attack.attacker_name}
        Arrival: {incoming_attack.arrival_time}
        Distance: {incoming_attack.distance}
        
        Historical patterns:
        {format_attack_history(attacker_history)}
        
        Predict:
        1. Is this real or fake?
        2. Estimated force size (small/medium/large)?
        3. Objective (raid/conquer/destroy)?
        
        Should I:
        A) Dodge (send resources away)
        B) Defend (keep troops)
        C) Counter-attack (send offense while he's away)
        
        JSON response.
        """)
        
        action = json.loads(prediction)
        
        if action['decision'] == 'dodge':
            await self.evacuate_resources(incoming_attack.target_village)
        elif action['decision'] == 'defend':
            await self.call_reinforcements(incoming_attack.target_village)
        else:  # counter-attack
            await self.launch_counter_strike(incoming_attack.attacker_village)
```

---

## 🎲 Adaptive Combat Learning

### **Learn from Battle Results**

```python
class CombatLearningSystem:
    """
    AI improves with every battle fought.
    """
    
    async def learn_from_battle(
        self,
        battle: BattleReport,
        my_prediction: Dict,
        actual_outcome: Dict
    ):
        """
        Update combat model based on actual results.
        """
        # Calculate prediction error
        error = abs(my_prediction['attacker_losses'] - actual_outcome['attacker_losses'])
        
        if error > 0.2:  # More than 20% wrong
            # Update opponent model
            await self.update_opponent_profile(
                opponent_id=battle.opponent_id,
                corrections={
                    "actual_defense": actual_outcome['defender_troops'],
                    "wall_bonus": actual_outcome['wall_bonus'],
                    "hero_bonus": actual_outcome['hero_bonus']
                }
            )
            
            # LLM analysis
            analysis = await self.llm.generate(f"""
            Battle prediction error analysis:
            
            My prediction: {my_prediction}
            Actual outcome: {actual_outcome}
            Error margin: {error * 100}%
            
            What factors did I misjudge?
            How should I adjust my predictions?
            
            JSON: {{"overlooked_factors": [...], "adjustment_strategy": "..."}}
            """)
            
            # Apply learning
            await self.apply_learning(json.loads(analysis))
```

---

## 🏆 Advanced Tactics

### **1. Fake Attack Psychology**

```python
async def send_fake_attacks(self, target: Village, real_attack_time: datetime):
    """
    Send multiple fake attacks to confuse defender.
    AI can manage 10+ simultaneous fakes perfectly.
    """
    fake_count = random.randint(5, 10)
    
    for i in range(fake_count):
        # Randomize fake timing (±30 minutes from real)
        fake_time = real_attack_time + timedelta(minutes=random.randint(-30, 30))
        
        # Send minimal units (1 unit)
        await self.send_attack(
            target=target,
            units={"pathfinder": 1},
            arrival_time=fake_time
        )
    
    # Real attack hidden among fakes
    await self.send_attack(
        target=target,
        units=self.main_force,
        arrival_time=real_attack_time
    )
```

### **2. Resource Denial**

```python
async def resource_denial_strategy(self, enemy_village: Village):
    """
    Raid enemy so frequently they can't build anything.
    AI can manage 50+ raids per day per village.
    """
    while True:
        # Wait for resources to accumulate
        await asyncio.sleep(self.calculate_production_time(enemy_village, 10000))
        
        # Instant raid when resources reach threshold
        if await self.scout_resources(enemy_village) > 10000:
            await self.launch_raid(enemy_village)
        
        # Human can't match this frequency or timing precision
```

### **3. Alliance War Coordination**

```python
async def coordinate_alliance_war(
    self,
    target_alliance: Alliance,
    our_alliance: Alliance
):
    """
    Perfect coordination of 50+ players simultaneously.
    """
    # Assign targets to alliance members
    enemy_villages = await self.get_alliance_villages(target_alliance)
    our_members = await self.get_alliance_members(our_alliance)
    
    # LLM strategic distribution
    war_plan = await self.llm.generate(f"""
    Plan alliance war:
    
    Our forces: {len(our_members)} members
    Enemy forces: {len(enemy_villages)} villages
    
    Distribute targets considering:
    - Member strength vs target strength
    - Geographic proximity
    - Balanced workload
    - Simultaneous arrival times
    
    JSON: {{"assignments": [{{"member": "...", "targets": [...], "timing": "..."}}]}}
    """)
    
    # Execute with perfect timing
    plan = json.loads(war_plan)
    await self.distribute_attack_orders(plan)
```

---

## 📊 Combat Performance Metrics

```python
class CombatMetrics:
    """
    Track AI combat performance.
    Goal: Better than human players.
    """
    
    metrics = {
        "battles_fought": 0,
        "win_rate": 0.0,
        "avg_casualties_ratio": 0.0,  # Lower = better
        "resources_raided": 0,
        "prediction_accuracy": 0.0,  # How often battle simulator was right
        "optimal_timing_rate": 0.0,  # How often attacked during offline hours
        
        # vs Human baseline
        "human_avg_win_rate": 0.60,
        "ai_win_rate": 0.85,  # Target: 25% better
        
        "human_avg_losses": 0.40,
        "ai_avg_losses": 0.20,  # Target: 50% lower
    }
```

---

## 🚀 Next Steps

- **DIPLOMACY-AI.md** - Alliance warfare coordination
- **IMPLEMENTATION-GUIDE.md** - Code integration
- **PERFORMANCE-OPTIMIZATION.md** - Scale to 500 NPCs

**Your AI will dominate the battlefield with precision humans can't match!** ⚔️
