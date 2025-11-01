# AI Ethics & Game Balance - Fair Gameplay Framework

## 🎯 Overview

**Current Weakness**: No safeguards against AI domination, unfair advantages, or exploits.

**Solution**: Implement comprehensive fairness systems, anti-exploit measures, and difficulty balancing to ensure fun gameplay.

---

## ⚖️ Fairness Principles

### **Core Rules**

1. **NPCs must be challenging, not unbeatable**
2. **Human players should feel accomplishment when winning**
3. **AI advantages must be transparent**
4. **Exploits must be prevented**
5. **Game remains fun for solo players**

---

## 🎮 Dynamic Difficulty Adjustment

### **Adaptive AI Strength**

```python
from dataclasses import dataclass
from typing import Dict, List
import numpy as np

@dataclass
class DifficultySettings:
    """
    Dynamic difficulty parameters.
    """
    npc_aggression_multiplier: float = 1.0  # 0.5-2.0
    npc_economy_multiplier: float = 1.0     # 0.5-2.0
    npc_decision_speed: float = 1.0          # 0.5-2.0 (slower = easier)
    npc_llm_usage_rate: float = 0.05        # % of decisions using LLM
    npc_mistake_probability: float = 0.05    # Chance of suboptimal decision
    
    alliance_coordination: float = 0.7       # How well NPCs coordinate
    scouting_accuracy: float = 0.8           # Intelligence gathering accuracy
    battle_prediction_accuracy: float = 0.9  # How accurate battle simulations

class DifficultyBalancer:
    """
    Automatically adjust AI difficulty based on player performance.
    
    If player is struggling: Make NPCs easier
    If player is dominating: Make NPCs harder
    """
    
    def __init__(self, player_id: int):
        self.player_id = player_id
        self.current_difficulty = DifficultySettings()
        self.performance_history = []
    
    async def adjust_difficulty(self):
        """
        Adjust AI difficulty based on player performance.
        
        Runs daily to keep game balanced.
        """
        # Analyze player performance
        perf = await self._analyze_player_performance()
        
        # Calculate difficulty score (-1.0 to +1.0)
        # Negative = player struggling
        # Positive = player dominating
        difficulty_score = self._calculate_difficulty_score(perf)
        
        # Adjust parameters
        if difficulty_score < -0.5:
            # Player struggling - make easier
            await self._make_easier()
        
        elif difficulty_score > 0.5:
            # Player dominating - make harder
            await self._make_harder()
        
        # Log adjustment
        await self._log_difficulty_change(difficulty_score)
    
    async def _analyze_player_performance(self) -> Dict:
        """
        Analyze how well player is doing.
        """
        async with self.db.acquire() as conn:
            stats = await conn.fetchrow("""
                SELECT 
                    -- Relative rank
                    p.rank,
                    (SELECT COUNT(*) FROM players WHERE active = TRUE) as total_players,
                    
                    -- Growth rate
                    p.population,
                    p.villages,
                    
                    -- Combat success
                    (SELECT COUNT(*) FROM battles 
                     WHERE attacker_id = p.id AND result = 'victory') as battles_won,
                    (SELECT COUNT(*) FROM battles 
                     WHERE defender_id = p.id AND result = 'defeat') as battles_lost,
                    
                    -- Economic health
                    (SELECT SUM(wood + clay + iron + wheat) 
                     FROM village_resources vr
                     JOIN villages v ON vr.village_id = v.id
                     WHERE v.player_id = p.id) as total_resources
                    
                FROM players p
                WHERE p.id = $1
            """, self.player_id)
        
        return dict(stats)
    
    def _calculate_difficulty_score(self, perf: Dict) -> float:
        """
        Calculate how well player is doing compared to NPCs.
        
        Returns:
            -1.0 = Player far behind (struggling)
             0.0 = Player average
            +1.0 = Player far ahead (dominating)
        """
        score = 0.0
        
        # Rank score (most important)
        total_players = perf['total_players']
        rank_percentile = perf['rank'] / total_players
        
        if rank_percentile < 0.2:  # Top 20%
            score += 0.5
        elif rank_percentile > 0.8:  # Bottom 20%
            score -= 0.5
        
        # Battle success rate
        total_battles = perf['battles_won'] + perf['battles_lost']
        if total_battles > 0:
            win_rate = perf['battles_won'] / total_battles
            
            if win_rate > 0.7:  # Winning most battles
                score += 0.3
            elif win_rate < 0.3:  # Losing most battles
                score -= 0.3
        
        # Economic health
        avg_resources_per_village = perf['total_resources'] / max(perf['villages'], 1)
        
        if avg_resources_per_village > 50000:  # Rich
            score += 0.2
        elif avg_resources_per_village < 10000:  # Poor
            score -= 0.2
        
        return np.clip(score, -1.0, 1.0)
    
    async def _make_easier(self):
        """
        Reduce AI difficulty.
        """
        # Reduce NPC aggression
        self.current_difficulty.npc_aggression_multiplier *= 0.9
        
        # Slow down NPC decisions
        self.current_difficulty.npc_decision_speed *= 0.9
        
        # Reduce LLM usage (more predictable)
        self.current_difficulty.npc_llm_usage_rate *= 0.8
        
        # Increase mistake probability
        self.current_difficulty.npc_mistake_probability *= 1.2
        
        # Reduce coordination
        self.current_difficulty.alliance_coordination *= 0.9
        
        # Apply to all nearby NPCs
        await self._apply_difficulty_to_nearby_npcs()
        
        print(f"🎮 Difficulty reduced for player {self.player_id}")
    
    async def _make_harder(self):
        """
        Increase AI difficulty.
        """
        # Increase NPC aggression
        self.current_difficulty.npc_aggression_multiplier = min(
            2.0,
            self.current_difficulty.npc_aggression_multiplier * 1.1
        )
        
        # Speed up NPC decisions
        self.current_difficulty.npc_decision_speed = min(
            2.0,
            self.current_difficulty.npc_decision_speed * 1.1
        )
        
        # Increase LLM usage (smarter decisions)
        self.current_difficulty.npc_llm_usage_rate = min(
            0.15,
            self.current_difficulty.npc_llm_usage_rate * 1.2
        )
        
        # Reduce mistake probability
        self.current_difficulty.npc_mistake_probability = max(
            0.01,
            self.current_difficulty.npc_mistake_probability * 0.8
        )
        
        # Increase coordination
        self.current_difficulty.alliance_coordination = min(
            1.0,
            self.current_difficulty.alliance_coordination * 1.1
        )
        
        await self._apply_difficulty_to_nearby_npcs()
        
        print(f"🎮 Difficulty increased for player {self.player_id}")
```

---

## 🚫 Anti-Exploit Measures

### **Prevent AI Abuse**

```python
class AntiExploitSystem:
    """
    Detect and prevent exploits against AI.
    """
    
    async def detect_farming_exploit(
        self,
        player_id: int
    ) -> bool:
        """
        Detect if player is farming same NPCs repeatedly.
        
        Exploit: Attacking same weak NPC over and over.
        """
        async with self.db.acquire() as conn:
            recent_attacks = await conn.fetch("""
                SELECT 
                    defender_id,
                    COUNT(*) as attack_count,
                    SUM(resources_gained) as total_loot
                FROM battles
                WHERE 
                    attacker_id = $1
                    AND created_at > NOW() - INTERVAL '24 hours'
                    AND result = 'victory'
                GROUP BY defender_id
                HAVING COUNT(*) > 10  -- More than 10 attacks per day
                ORDER BY attack_count DESC
            """, player_id)
        
        if recent_attacks:
            # Player is farming same NPCs
            for victim in recent_attacks:
                if victim['attack_count'] > 15:
                    # Too many attacks - trigger anti-exploit
                    await self._apply_anti_farming_measure(
                        player_id,
                        victim['defender_id']
                    )
                    return True
        
        return False
    
    async def _apply_anti_farming_measure(
        self,
        farmer_id: int,
        victim_npc_id: int
    ):
        """
        Make farmed NPC fight back intelligently.
        """
        # Boost NPC defenses
        await self._boost_npc_defense(victim_npc_id, multiplier=2.0)
        
        # NPC requests alliance help
        await self._request_alliance_protection(victim_npc_id)
        
        # NPC starts counter-raiding
        await self._enable_counter_raids(victim_npc_id, farmer_id)
        
        # Notify player (transparent)
        await self._notify_player(
            farmer_id,
            f"Your repeated attacks have angered {await self._get_npc_name(victim_npc_id)}! "
            f"They have called for alliance help and will fight back."
        )
    
    async def detect_ai_pattern_exploitation(
        self,
        player_id: int
    ) -> bool:
        """
        Detect if player is exploiting predictable AI patterns.
        
        Example: Knowing NPCs always attack at certain times.
        """
        # Check if player times defenses suspiciously well
        async with self.db.acquire() as conn:
            suspicious_defenses = await conn.fetch("""
                SELECT COUNT(*) as perfect_dodges
                FROM battles b
                JOIN (
                    SELECT 
                        defender_id,
                        arrival_time,
                        (SELECT COUNT(*) FROM village_troops 
                         WHERE village_id = target_village_id 
                         AND timestamp < arrival_time) as troops_before_attack
                    FROM incoming_attacks
                ) ia ON b.id = ia.id
                WHERE 
                    b.defender_id = $1
                    AND b.result = 'no_troops'  # Perfect dodge
                    AND ia.troops_before_attack = 0  # No troops before
                    AND b.created_at > NOW() - INTERVAL '7 days'
            """, player_id)
        
        if suspicious_defenses and suspicious_defenses[0]['perfect_dodges'] > 10:
            # Player dodging too perfectly - randomize AI timing
            await self._randomize_npc_timing()
            return True
        
        return False
    
    async def _randomize_npc_timing(self):
        """
        Add randomness to NPC attack timing to prevent exploitation.
        """
        # Update all nearby NPCs to use random timing
        pass
```

---

## 🎲 Intentional Imperfection

### **NPCs Make Realistic Mistakes**

```python
class RealisticImperfection:
    """
    Make NPCs occasionally make mistakes like human players.
    
    Makes game feel more realistic and gives players opportunities.
    """
    
    async def apply_decision_noise(
        self,
        npc_id: int,
        optimal_decision: Dict,
        mistake_probability: float = 0.05
    ) -> Dict:
        """
        Occasionally make NPCs choose suboptimal decisions.
        
        Types of mistakes:
        - Attack when should defend
        - Build wrong building
        - Trade at bad rates
        - Miss obvious opportunities
        """
        if random.random() < mistake_probability:
            # Make a mistake
            mistake_type = random.choice([
                'wrong_priority',
                'bad_timing',
                'poor_target_selection',
                'resource_mismanagement'
            ])
            
            return await self._apply_mistake(
                optimal_decision,
                mistake_type
            )
        
        return optimal_decision
    
    async def _apply_mistake(
        self,
        decision: Dict,
        mistake_type: str
    ) -> Dict:
        """
        Apply a specific type of mistake.
        """
        if mistake_type == 'wrong_priority':
            # Choose 2nd or 3rd best option instead of best
            if 'alternatives' in decision:
                decision['choice'] = random.choice(decision['alternatives'])
        
        elif mistake_type == 'bad_timing':
            # Delay decision by random amount
            if 'timing' in decision:
                delay = random.randint(1, 6)  # 1-6 hours
                decision['timing'] += timedelta(hours=delay)
        
        elif mistake_type == 'poor_target_selection':
            # Attack wrong target
            if decision['type'] == 'attack' and 'alternate_targets' in decision:
                decision['target'] = random.choice(decision['alternate_targets'])
        
        return decision
    
    async def simulate_human_errors(self, npc_id: int):
        """
        Simulate realistic human mistakes:
        - Forgetting to check messages
        - Missing scout reports
        - Not responding to attacks quickly
        - Sending troops to wrong village (typo)
        """
        pass
```

---

## 📊 Fairness Monitoring

### **Detect Unfair Situations**

```python
class FairnessMonitor:
    """
    Monitor game state for unfair situations.
    """
    
    async def check_npc_domination(self) -> Dict:
        """
        Check if NPCs are dominating too much.
        
        Red flags:
        - Top 10 ranks all NPCs
        - Player can't expand (all spots taken by NPCs)
        - Player getting ganged up on
        """
        async with self.db.acquire() as conn:
            # Check top ranks
            top_10 = await conn.fetch("""
                SELECT 
                    p.id,
                    p.is_npc,
                    p.rank,
                    p.population
                FROM players p
                ORDER BY rank ASC
                LIMIT 10
            """)
            
            npc_in_top_10 = sum(1 for p in top_10 if p['is_npc'])
            
            if npc_in_top_10 > 7:
                # Too many NPCs dominating
                return {
                    "fair": False,
                    "issue": "npc_domination",
                    "severity": "high",
                    "recommendation": "Reduce top NPC difficulty or add handicaps"
                }
        
        return {"fair": True}
    
    async def check_player_being_ganged(
        self,
        player_id: int
    ) -> bool:
        """
        Check if player is being unfairly targeted by multiple NPCs.
        """
        async with self.db.acquire() as conn:
            attackers = await conn.fetch("""
                SELECT DISTINCT attacker_id
                FROM battles
                WHERE 
                    defender_id = $1
                    AND created_at > NOW() - INTERVAL '24 hours'
                    AND attacker_is_npc = TRUE
            """, player_id)
        
        if len(attackers) > 5:
            # More than 5 different NPCs attacking in 24 hours
            # Reduce NPC aggression toward this player
            await self._reduce_aggression_toward_player(player_id)
            return True
        
        return False
```

---

## 🎯 Transparent AI Advantages

### **Clear Communication**

```python
class TransparencySystem:
    """
    Make AI advantages transparent to players.
    """
    
    async def show_ai_capabilities(self):
        """
        Show players what AI can and can't do.
        """
        capabilities = {
            "AI Advantages": [
                "⚡ Instant battle calculations (no waiting)",
                "📊 Perfect resource tracking (no mistakes)",
                "⏰ 24/7 activity (doesn't sleep)",
                "🤝 Alliance coordination (synchronized attacks)"
            ],
            
            "AI Limitations": [
                "🎲 Makes occasional mistakes (5% chance)",
                "⏱️ Can't predict future events",
                "🤔 Doesn't know your plans (no cheating)",
                "📉 Difficulty adjusts to YOUR performance",
                "❤️ Has same game rules as you (no resource cheats)"
            ],
            
            "Fairness Guarantees": [
                "✅ NPCs use same game mechanics",
                "✅ No wallhacks or fog of war cheating",
                "✅ Must scout to gather intelligence",
                "✅ Difficulty scales with your skill",
                "✅ Anti-exploit systems protect you"
            ]
        }
        
        return capabilities
    
    async def notify_difficulty_change(
        self,
        player_id: int,
        direction: str,
        reason: str
    ):
        """
        Tell player when difficulty changes.
        """
        if direction == "easier":
            message = f"""
🎮 AI Difficulty Adjusted: EASIER

{reason}

Changes:
- NPCs will be less aggressive
- NPCs will make more mistakes
- Alliance coordination reduced

You've got this! 💪
"""
        else:
            message = f"""
🎮 AI Difficulty Adjusted: HARDER

{reason}

Changes:
- NPCs will be more challenging
- Better strategic decisions
- Improved alliance coordination

Time to step up your game! 🔥
"""
        
        await self._send_ingame_message(player_id, message)
```

---

## 🚀 Next Steps

- **ADVANCED-STRATEGIES.md** - Meta-game tactics
- **PERSONALITY-PSYCHOLOGY.md** - Deep personalities
- **EDGE-CASES-HANDLING.md** - Edge case management

**Your game will be fair, fun, and balanced!** ⚖️
