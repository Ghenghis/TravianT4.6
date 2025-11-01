# NPC Behavior System - Complete Implementation Guide

## 🎯 Overview

Design a flexible behavior system where each NPC has a unique personality, play style, and decision-making process combining rule-based scripts (95%) with LLM strategic thinking (5%).

---

## 🤖 NPC Architecture

### **Core Components**

```
NPC
├── Profile (Static)
│   ├── Name, Tribe, Personality
│   └── Behavior Template
├── State (Dynamic)
│   ├── Villages, Resources, Army
│   └── Relationships, Reputation
├── Brain (Decision Making)
│   ├── Rule Engine (95% - Fast)
│   └── LLM Layer (5% - Smart)
└── Memory (Learning)
    ├── Past Battles
    ├── Player Relationships
    └── Strategy Effectiveness
```

---

## 🎭 Behavior Templates

### **1. Aggressive Warmonger**

**Personality Traits:**
```python
{
    "name": "BattleKing47",
    "tribe": "Teutons",
    "personality": {
        "aggression": 0.9,      # Very aggressive
        "economy": 0.3,         # Minimal focus
        "diplomacy": 0.2,       # Poor at alliances
        "risk_tolerance": 0.85, # High risk
        "patience": 0.2         # Acts quickly
    },
    "goals": [
        "Maximize conquest rate",
        "Build largest army",
        "Raid weaker players daily"
    ],
    "building_priority": [
        "barracks",
        "stable",
        "workshop",
        "smithy",
        "rally_point"
    ]
}
```

**Decision Logic:**
```python
class AggressiveWarmonger(NPCBehavior):
    """
    Focuses on military expansion and constant warfare.
    """
    
    async def daily_strategy(self):
        # 1. Build troops (highest priority)
        await self.queue_troop_production()
        
        # 2. Launch raids
        if self.army_size > self.min_raid_force:
            targets = await self.find_raid_targets()
            await self.launch_raids(targets[:5])  # Attack 5 villages
        
        # 3. Expand if army strong enough
        if self.army_size > self.expansion_threshold:
            await self.found_new_village()
        
        # 4. Upgrade military buildings
        await self.upgrade_military_buildings()
    
    async def find_raid_targets(self):
        """Find weak players to raid"""
        nearby = await self.get_nearby_villages(radius=15)
        
        # Filter for weak targets
        targets = [
            v for v in nearby 
            if v.population < self.population * 0.7  # Smaller than me
            and v.last_online > 8  # Inactive
            and not self.is_ally(v.owner)
        ]
        
        # Sort by estimated loot
        targets.sort(key=lambda v: v.estimated_resources, reverse=True)
        return targets
    
    async def respond_to_attack(self, attacker):
        """Always counter-attack"""
        # Build revenge army
        await self.prioritize_troops("offensive")
        
        # Plan counter-attack
        llm_decision = await self.llm.generate(f"""
        I was attacked by {attacker.name}.
        My losses: {self.last_battle_losses}
        Their strength: {attacker.estimated_power}
        
        Plan revenge attack. When and with what force?
        """)
        
        await self.execute_revenge(llm_decision)
```

---

### **2. Economic Powerhouse**

**Personality Traits:**
```python
{
    "name": "Merchant_Queen",
    "tribe": "Gauls",
    "personality": {
        "aggression": 0.1,      # Very peaceful
        "economy": 0.95,        # Max economic focus
        "diplomacy": 0.8,       # Good at alliances
        "risk_tolerance": 0.2,  # Low risk
        "patience": 0.9         # Very patient
    },
    "goals": [
        "Maximize resource production",
        "Build trade empire",
        "Support allies with resources"
    ],
    "building_priority": [
        "resource_fields",
        "marketplace",
        "warehouse",
        "granary",
        "embassy"
    ]
}
```

**Decision Logic:**
```python
class EconomicPowerhouse(NPCBehavior):
    """
    Focuses on resource production and peaceful expansion.
    """
    
    async def daily_strategy(self):
        # 1. Upgrade resource fields
        await self.upgrade_resource_fields()
        
        # 2. Trade excess resources
        await self.execute_trades()
        
        # 3. Support allies
        if self.in_alliance():
            await self.send_tributes_to_allies()
        
        # 4. Build defensive army (minimal)
        if self.army_size < self.min_defense:
            await self.train_defensive_units()
    
    async def respond_to_attack(self, attacker):
        """Seek diplomatic solution or hire protection"""
        
        # Option 1: Negotiate
        if self.can_negotiate(attacker):
            message = await self.llm.generate(f"""
            I am a peaceful economic player.
            {attacker.name} attacked me.
            
            Draft a message offering tribute to stop attacks.
            Offer: 10% of my daily production.
            """)
            await self.send_message(attacker, message)
        
        # Option 2: Buy protection
        else:
            nearby_strong = await self.find_strong_allies()
            for ally in nearby_strong:
                offer = await self.llm.generate(f"""
                Request protection from {ally.name}.
                Offer: Resources in exchange for defense pact.
                """)
                await self.propose_alliance(ally, offer)
    
    async def execute_trades(self):
        """Automated resource trading"""
        surplus = self.calculate_surplus()
        needs = await self.scan_market_prices()
        
        for resource, amount in surplus.items():
            if amount > 10000:
                best_trade = self.find_best_trade(resource, amount, needs)
                await self.create_trade_offer(best_trade)
```

---

### **3. Balanced Strategist**

**Personality Traits:**
```python
{
    "name": "TacticalMind",
    "tribe": "Romans",
    "personality": {
        "aggression": 0.5,      # Moderate
        "economy": 0.6,         # Balanced
        "diplomacy": 0.7,       # Good at alliances
        "risk_tolerance": 0.5,  # Calculated risks
        "patience": 0.7         # Patient but opportunistic
    },
    "goals": [
        "Balanced development",
        "Opportunistic expansion",
        "Strong alliance presence"
    ],
    "building_priority": [
        "mixed_resources",
        "barracks",
        "stable",
        "marketplace",
        "embassy"
    ]
}
```

**Decision Logic:**
```python
class BalancedStrategist(NPCBehavior):
    """
    Adapts strategy based on game state and neighbors.
    """
    
    async def daily_strategy(self):
        # Analyze current situation
        threat_level = await self.assess_threats()
        opportunity_level = await self.assess_opportunities()
        
        if threat_level > 0.7:
            # Defensive mode
            await self.fortify_defenses()
            await self.seek_protective_alliances()
        
        elif opportunity_level > 0.7:
            # Offensive mode
            await self.prepare_expansion()
            await self.launch_strategic_attacks()
        
        else:
            # Development mode
            await self.balanced_development()
    
    async def assess_threats(self) -> float:
        """Calculate threat level from neighbors"""
        threats = await self.get_nearby_threats()
        
        llm_analysis = await self.llm.generate(f"""
        Analyze these potential threats:
        {format_threats(threats)}
        
        Rate overall threat level 0.0-1.0 and suggest response.
        JSON: {{"threat_level": 0.0-1.0, "response": "fortify/negotiate/attack"}}
        """)
        
        return json.loads(llm_analysis)['threat_level']
    
    async def balanced_development(self):
        """Develop economy and military equally"""
        # Alternate priorities
        if self.day_of_week % 2 == 0:
            await self.upgrade_economy()
            await self.train_small_army()
        else:
            await self.upgrade_military()
            await self.expand_production()
```

---

### **4. Diplomat / Alliance Leader**

**Personality Traits:**
```python
{
    "name": "Ambassador_Prime",
    "tribe": "Romans",
    "personality": {
        "aggression": 0.3,      # Low aggression
        "economy": 0.5,         # Moderate
        "diplomacy": 0.95,      # Master diplomat
        "risk_tolerance": 0.4,  # Cautious
        "patience": 0.85        # Very patient
    },
    "goals": [
        "Build largest alliance",
        "Coordinate group strategies",
        "Mediate conflicts",
        "Organize coordinated attacks"
    ],
    "building_priority": [
        "embassy",
        "marketplace",
        "barracks",
        "stable"
    ]
}
```

**Decision Logic:**
```python
class DiplomatLeader(NPCBehavior):
    """
    Focuses on building and managing alliances.
    """
    
    async def daily_strategy(self):
        # 1. Recruit new members
        await self.recruit_alliance_members()
        
        # 2. Organize group activities
        if self.is_alliance_leader():
            await self.plan_coordinated_attack()
            await self.distribute_resources()
        
        # 3. Mediate conflicts
        await self.resolve_internal_disputes()
        
        # 4. Strengthen bonds
        await self.send_diplomatic_messages()
    
    async def recruit_alliance_members(self):
        """Find and invite suitable players"""
        candidates = await self.find_recruitment_targets()
        
        for candidate in candidates:
            # LLM crafts personalized invitation
            invitation = await self.llm.generate(f"""
            You are ambassador of {self.alliance_name}.
            Invite {candidate.name} to join.
            
            Their stats:
            - Population: {candidate.population}
            - Villages: {candidate.villages}
            - Activity: {candidate.activity_level}
            
            Craft persuasive but friendly invitation.
            Highlight benefits:
            - Protection from enemies
            - Resource sharing
            - Coordinated attacks
            - Active community
            
            Keep it casual and welcoming (max 150 words).
            """)
            
            await self.send_alliance_invitation(candidate, invitation)
    
    async def plan_coordinated_attack(self):
        """Organize multi-player attack"""
        # Find target
        target = await self.select_war_target()
        
        # Plan with LLM
        war_plan = await self.llm.generate(f"""
        Plan coordinated attack on {target.name}.
        
        Alliance forces available:
        {self.get_alliance_military_summary()}
        
        Target defenses:
        {target.estimated_defenses}
        
        Create attack plan:
        1. Which members attack which villages?
        2. What timing for waves?
        3. Who sends fakes/real attacks?
        
        JSON format with assignments.
        """)
        
        # Distribute orders
        await self.send_attack_orders(json.loads(war_plan))
```

---

### **5. Solo Assassin**

**Personality Traits:**
```python
{
    "name": "ShadowStrike",
    "tribe": "Gauls",  # Fast units
    "personality": {
        "aggression": 0.7,      # Selective aggression
        "economy": 0.4,         # Moderate
        "diplomacy": 0.1,       # Loner
        "risk_tolerance": 0.8,  # High risk
        "patience": 0.9         # Very patient (waits for perfect moment)
    },
    "goals": [
        "Disrupt enemy plans",
        "Strike at critical moments",
        "Remain unpredictable",
        "Be kingmaker in conflicts"
    ],
    "building_priority": [
        "stable",          # Fast cavalry
        "workshop",        # Catapults for structure destruction
        "smithy",          # Upgraded units
        "tournament_square" # Gauls special
    ]
}
```

**Decision Logic:**
```python
class SoloAssassin(NPCBehavior):
    """
    Lone wolf who strikes strategically to influence game balance.
    """
    
    async def daily_strategy(self):
        # 1. Gather intelligence
        await self.spy_on_major_players()
        
        # 2. Identify critical moments
        war_status = await self.analyze_current_wars()
        
        # 3. Strike to influence outcome
        if war_status:
            await self.kingmaker_strike(war_status)
        
        # 4. Stay hidden
        await self.maintain_low_profile()
    
    async def kingmaker_strike(self, war):
        """Intervene in war to tip balance"""
        
        # LLM decides which side to help
        decision = await self.llm.generate(f"""
        Major war between {war.side_a} and {war.side_b}.
        
        Side A strength: {war.side_a_power}
        Side B strength: {war.side_b_power}
        
        As a solo player, which side should you help?
        Consider:
        - Who is more dangerous if they win?
        - Who would owe you favors?
        - How to maximize your influence?
        
        JSON: {{"support": "A/B/neither", "reason": "...", "strategy": "..."}}
        """)
        
        choice = json.loads(decision)
        
        if choice['support'] != 'neither':
            await self.launch_surprise_attack(
                target=war.get_losing_side(),
                timing="when_critical"
            )
    
    async def maintain_low_profile(self):
        """Avoid being targeted"""
        # Don't join alliances
        # Reject all invitations politely
        # Keep villages spread out
        # Use diverse attack patterns
        pass
```

---

## 🎯 Decision Framework

### **When to Use Rules vs LLM**

```python
class NPCDecisionEngine:
    """
    Hybrid decision-making: Rules for speed, LLM for strategy.
    """
    
    # Rule-based decisions (95% of actions)
    RULE_BASED_ACTIONS = [
        "queue_building",
        "train_troops",
        "collect_resources",
        "send_merchants",
        "upgrade_fields",
        "auto_raid_inactive"
    ]
    
    # LLM-based decisions (5% strategic)
    LLM_DECISIONS = [
        "declare_war",
        "form_alliance",
        "negotiate_peace",
        "choose_expansion_location",
        "respond_to_complex_situation",
        "craft_diplomatic_message"
    ]
    
    async def decide(self, situation: str, context: Dict):
        """Route decision to appropriate engine"""
        
        if situation in self.RULE_BASED_ACTIONS:
            return await self.rule_engine.decide(situation, context)
        
        elif situation in self.LLM_DECISIONS:
            return await self.llm_engine.decide(situation, context)
        
        else:
            # Hybrid: Use rules for speed, LLM for quality check
            rule_decision = await self.rule_engine.decide(situation, context)
            
            if context.get('importance') == 'high':
                llm_validation = await self.llm_engine.validate(rule_decision)
                return llm_validation
            
            return rule_decision
```

---

## 📊 Behavior Metrics

### **Track NPC Performance**

```python
class NPCMetrics:
    """
    Track and optimize NPC behavior effectiveness.
    """
    
    def __init__(self, npc_id: str):
        self.npc_id = npc_id
        self.metrics = {
            "battles_won": 0,
            "battles_lost": 0,
            "villages_founded": 0,
            "villages_conquered": 0,
            "resources_raided": 0,
            "alliances_formed": 0,
            "wars_initiated": 0,
            "avg_rank_change": 0.0
        }
    
    def track_battle(self, result: str, losses: int, gains: int):
        """Record battle outcome"""
        if result == "win":
            self.metrics['battles_won'] += 1
            self.metrics['resources_raided'] += gains
        else:
            self.metrics['battles_lost'] += 1
    
    def adapt_behavior(self):
        """Adjust behavior based on performance"""
        win_rate = self.metrics['battles_won'] / (
            self.metrics['battles_won'] + self.metrics['battles_lost'] + 1
        )
        
        if win_rate < 0.3:
            # Losing too much - become more defensive
            return "increase_defense_focus"
        elif win_rate > 0.8:
            # Dominating - can be more aggressive
            return "increase_aggression"
        else:
            return "maintain_current"
```

---

## 🔄 Dynamic Adaptation

```python
async def adapt_to_player(self, player_id: str):
    """
    Learn and adapt to specific player's tactics.
    """
    player_history = await self.get_interaction_history(player_id)
    
    # LLM analyzes player patterns
    analysis = await self.llm.generate(f"""
    Analyze this player's behavior:
    
    {format_player_history(player_history)}
    
    What are their patterns?
    - Attack timing preferences
    - Typical army composition
    - Response to diplomacy
    - Economic focus vs military
    
    How should I counter their strategy?
    
    JSON: {{"patterns": [...], "counter_strategy": "...", "risk_level": 0.0-1.0}}
    """)
    
    # Update NPC strategy
    counter_plan = json.loads(analysis)
    await self.adjust_behavior(counter_plan)
```

---

## 📈 Next Documents

- `BUILDING-STRATEGIES.md` - Detailed building templates
- `COMBAT-AI.md` - Battle intelligence
- `DIPLOMACY-AI.md` - Alliance & negotiation
- `IMPLEMENTATION-GUIDE.md` - Code implementation

**Your NPCs will play like experienced humans!** 🎮
