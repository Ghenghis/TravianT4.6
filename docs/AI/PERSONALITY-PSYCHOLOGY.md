# Personality Psychology - Deep Character Modeling

## 🎯 Overview

**Current Weakness**: NPCs have shallow personalities (just 5 numbers). No emotional depth, no character development, no relationships.

**Solution**: Implement comprehensive psychological models with emotions, memories, relationships, and character growth.

---

## 🧠 Psychological Framework

```
NPC Psychology
├── Core Traits (Big Five)
│   ├── Openness
│   ├── Conscientiousness
│   ├── Extraversion
│   ├── Agreeableness
│   └── Neuroticism
│
├── Emotional State
│   ├── Current Mood
│   ├── Stress Level
│   ├── Confidence
│   └── Emotional History
│
├── Cognitive Style
│   ├── Risk Perception
│   ├── Planning Horizon
│   ├── Learning Rate
│   └── Adaptability
│
├── Social Bonds
│   ├── Friendships
│   ├── Rivalries
│   ├── Trust Networks
│   └── Reputation
│
└── Character Development
    ├── Experience Level
    ├── Skill Progression
    ├── Personality Evolution
    └── Story Arc
```

---

## 🎭 Big Five Personality Model

### **Deep Trait System**

```python
from dataclasses import dataclass
from typing import Dict, List
import numpy as np

@dataclass
class PersonalityTraits:
    """
    Big Five personality model (OCEAN).
    Each trait 0.0-1.0
    """
    # Openness to Experience
    openness: float  # Curious, creative, unconventional vs. practical, traditional
    
    # Conscientiousness
    conscientiousness: float  # Organized, disciplined vs. spontaneous, careless
    
    # Extraversion
    extraversion: float  # Outgoing, sociable vs. reserved, solitary
    
    # Agreeableness
    agreeableness: float  # Cooperative, trusting vs. competitive, suspicious
    
    # Neuroticism (Emotional Stability)
    neuroticism: float  # Anxious, moody vs. calm, stable
    
    def to_gameplay_style(self) -> Dict:
        """
        Convert personality to gameplay tendencies.
        """
        return {
            # High openness = tries new strategies
            "strategy_variety": self.openness,
            
            # High conscientiousness = plans carefully
            "planning_depth": self.conscientiousness,
            
            # High extraversion = seeks alliances, communicates often
            "social_activity": self.extraversion,
            
            # High agreeableness = cooperative, honors treaties
            "cooperation_tendency": self.agreeableness,
            
            # High neuroticism = risk-averse, emotional decisions
            "risk_aversion": self.neuroticism,
            
            # Derived traits
            "aggression": (1 - self.agreeableness) * 0.7 + (1 - self.neuroticism) * 0.3,
            "patience": self.conscientiousness * 0.6 + (1 - self.neuroticism) * 0.4,
            "adaptability": self.openness * 0.7 + (1 - self.neuroticism) * 0.3
        }


class PersonalityGenerator:
    """
    Generate diverse, realistic personalities.
    """
    
    @staticmethod
    def generate_archetype(archetype: str) -> PersonalityTraits:
        """
        Generate personality for specific archetype.
        """
        archetypes = {
            "aggressive_warlord": PersonalityTraits(
                openness=0.4,          # Traditional, proven tactics
                conscientiousness=0.7,  # Disciplined military
                extraversion=0.8,       # Leadership, commands
                agreeableness=0.2,      # Ruthless
                neuroticism=0.3         # Confident in battle
            ),
            
            "peaceful_merchant": PersonalityTraits(
                openness=0.6,           # Open to deals
                conscientiousness=0.8,  # Reliable trader
                extraversion=0.7,       # Social, networking
                agreeableness=0.8,      # Cooperative
                neuroticism=0.4         # Stable
            ),
            
            "paranoid_turtle": PersonalityTraits(
                openness=0.3,           # Cautious, traditional
                conscientiousness=0.9,  # Meticulous defense
                extraversion=0.3,       # Isolated
                agreeableness=0.4,      # Suspicious
                neuroticism=0.8         # Anxious, fearful
            ),
            
            "charismatic_diplomat": PersonalityTraits(
                openness=0.8,           # Creative solutions
                conscientiousness=0.6,  # Somewhat organized
                extraversion=0.9,       # Very social
                agreeableness=0.7,      # Friendly
                neuroticism=0.3         # Calm under pressure
            ),
            
            "calculating_strategist": PersonalityTraits(
                openness=0.7,           # Strategic thinking
                conscientiousness=0.9,  # Methodical
                extraversion=0.5,       # Moderate social
                agreeableness=0.5,      # Pragmatic
                neuroticism=0.2         # Very calm
            )
        }
        
        return archetypes.get(archetype, PersonalityGenerator.generate_random())
    
    @staticmethod
    def generate_random() -> PersonalityTraits:
        """
        Generate random but realistic personality.
        
        Uses normal distribution to avoid extremes.
        """
        return PersonalityTraits(
            openness=np.clip(np.random.normal(0.5, 0.15), 0.0, 1.0),
            conscientiousness=np.clip(np.random.normal(0.5, 0.15), 0.0, 1.0),
            extraversion=np.clip(np.random.normal(0.5, 0.15), 0.0, 1.0),
            agreeableness=np.clip(np.random.normal(0.5, 0.15), 0.0, 1.0),
            neuroticism=np.clip(np.random.normal(0.5, 0.15), 0.0, 1.0)
        )
```

---

## 😊 Emotional System

### **Dynamic Emotional States**

```python
@dataclass
class EmotionalState:
    """
    Current emotional state of NPC.
    Changes based on game events.
    """
    # Core emotions
    happiness: float = 0.5      # 0.0 (miserable) to 1.0 (ecstatic)
    anger: float = 0.0          # 0.0 (calm) to 1.0 (furious)
    fear: float = 0.0           # 0.0 (fearless) to 1.0 (terrified)
    confidence: float = 0.5     # 0.0 (insecure) to 1.0 (overconfident)
    stress: float = 0.0         # 0.0 (relaxed) to 1.0 (overwhelmed)
    
    # Emotional momentum
    emotional_inertia: float = 0.7  # How slowly emotions change
    
    def update(self, event: Dict):
        """
        Update emotional state based on event.
        
        Events affect emotions differently based on personality.
        """
        if event['type'] == 'battle_victory':
            # Victory increases happiness and confidence
            self.happiness = self._adjust(self.happiness, +0.2)
            self.confidence = self._adjust(self.confidence, +0.15)
            self.anger = self._adjust(self.anger, -0.1)
            
        elif event['type'] == 'battle_defeat':
            # Defeat decreases happiness, may increase anger
            self.happiness = self._adjust(self.happiness, -0.3)
            self.confidence = self._adjust(self.confidence, -0.2)
            
            if event.get('unfair', False):
                self.anger = self._adjust(self.anger, +0.4)
            else:
                self.fear = self._adjust(self.fear, +0.2)
        
        elif event['type'] == 'betrayed_by_ally':
            # Betrayal causes strong emotional response
            self.happiness = self._adjust(self.happiness, -0.5)
            self.anger = self._adjust(self.anger, +0.6)
            self.confidence = self._adjust(self.confidence, -0.3)
        
        elif event['type'] == 'successful_alliance':
            self.happiness = self._adjust(self.happiness, +0.3)
            self.confidence = self._adjust(self.confidence, +0.1)
        
        # Stress accumulates from many attacks
        if event['type'] == 'under_attack':
            self.stress = self._adjust(self.stress, +0.1)
        
        # Emotions decay toward baseline over time
        self._decay_emotions()
    
    def _adjust(self, current: float, change: float) -> float:
        """
        Adjust emotion with inertia.
        """
        target = current + change
        # Move partially toward target (emotional inertia)
        new_value = current + (target - current) * (1 - self.emotional_inertia)
        return np.clip(new_value, 0.0, 1.0)
    
    def _decay_emotions(self, decay_rate: float = 0.05):
        """
        Emotions slowly return to baseline.
        """
        # Anger and fear decay faster
        self.anger *= (1 - decay_rate * 2)
        self.fear *= (1 - decay_rate * 2)
        
        # Happiness and confidence decay slower
        self.happiness = self.happiness * (1 - decay_rate) + 0.5 * decay_rate
        self.confidence = self.confidence * (1 - decay_rate) + 0.5 * decay_rate
    
    def affects_decision(self, decision: Dict) -> Dict:
        """
        Modify decision based on emotional state.
        """
        if self.anger > 0.7:
            # Angry NPCs are more aggressive, less cautious
            if decision['type'] == 'attack':
                decision['aggression'] *= 1.5
                decision['risk_acceptance'] *= 1.3
        
        if self.fear > 0.7:
            # Fearful NPCs avoid risks
            if decision['type'] == 'expansion':
                decision['cancel'] = True
                decision['reason'] = "Too risky in current state"
        
        if self.confidence > 0.8:
            # Overconfident NPCs take bigger risks
            decision['risk_acceptance'] *= 1.4
        
        if self.stress > 0.8:
            # Stressed NPCs make worse decisions
            decision['quality_modifier'] *= 0.7
            decision['mistake_probability'] *= 2.0
        
        return decision
```

---

## 💭 Memory & Learning

### **Episodic Memory System**

```python
class EpisodicMemory:
    """
    NPCs remember specific experiences and learn from them.
    
    Like human episodic memory - remembers events with context.
    """
    
    def __init__(self, npc_id: int):
        self.npc_id = npc_id
        self.memories = []
        self.max_memories = 1000
    
    async def record_memory(
        self,
        event_type: str,
        context: Dict,
        emotional_intensity: float,
        outcome: str
    ):
        """
        Record a memory.
        
        Important memories (high emotional intensity) are remembered longer.
        """
        memory = {
            "id": len(self.memories),
            "timestamp": datetime.now(),
            "event_type": event_type,
            "context": context,
            "emotional_intensity": emotional_intensity,
            "outcome": outcome,
            
            # Memory strength (fades over time)
            "strength": 1.0,
            
            # How often this memory is recalled
            "recall_count": 0
        }
        
        self.memories.append(memory)
        
        # Forget old, weak memories
        await self._forget_weak_memories()
    
    async def recall_similar(
        self,
        current_situation: Dict
    ) -> List[Dict]:
        """
        Recall memories similar to current situation.
        
        Used for learning: "What happened last time I was in this situation?"
        """
        similar_memories = []
        
        for memory in self.memories:
            similarity = self._calculate_similarity(
                current_situation,
                memory['context']
            )
            
            if similarity > 0.7:
                # Strengthen memory when recalled
                memory['recall_count'] += 1
                memory['strength'] = min(1.0, memory['strength'] + 0.1)
                
                similar_memories.append({
                    **memory,
                    "similarity": similarity
                })
        
        # Sort by relevance (similarity * strength * emotional intensity)
        similar_memories.sort(
            key=lambda m: m['similarity'] * m['strength'] * m['emotional_intensity'],
            reverse=True
        )
        
        return similar_memories[:5]  # Return top 5
    
    async def learn_from_memories(self) -> Dict:
        """
        Extract patterns from memories.
        
        Examples:
        - "Attacking Player X always leads to heavy losses"
        - "Trading with Alliance Y is always profitable"
        - "Expanding east leads to more conflicts"
        """
        # Group memories by type
        battle_memories = [m for m in self.memories if m['event_type'] == 'battle']
        trade_memories = [m for m in self.memories if m['event_type'] == 'trade']
        
        # Analyze patterns
        patterns = {}
        
        # Battle pattern: Which opponents are dangerous?
        if battle_memories:
            opponent_outcomes = {}
            for mem in battle_memories:
                opponent = mem['context'].get('opponent_id')
                outcome = mem['outcome']
                
                if opponent not in opponent_outcomes:
                    opponent_outcomes[opponent] = {'wins': 0, 'losses': 0}
                
                if outcome == 'victory':
                    opponent_outcomes[opponent]['wins'] += 1
                else:
                    opponent_outcomes[opponent]['losses'] += 1
            
            # Identify dangerous opponents
            dangerous_opponents = [
                opponent_id
                for opponent_id, record in opponent_outcomes.items()
                if record['losses'] > record['wins'] * 2
            ]
            
            patterns['dangerous_opponents'] = dangerous_opponents
        
        return patterns
    
    def _forget_weak_memories(self):
        """
        Forget old, weak, low-importance memories.
        """
        # Sort by retention score
        for memory in self.memories:
            # Memory strength decays over time
            age_days = (datetime.now() - memory['timestamp']).days
            decay_factor = 0.95 ** age_days
            
            memory['strength'] *= decay_factor
            
            # But emotional memories decay slower
            if memory['emotional_intensity'] > 0.7:
                memory['strength'] *= 1.1
        
        # Keep only strong memories
        self.memories = [
            m for m in self.memories
            if m['strength'] > 0.1
        ][-self.max_memories:]  # Keep most recent
```

---

## 🤝 Relationship System

### **Social Network**

```python
@dataclass
class Relationship:
    """
    Relationship between NPC and another player.
    """
    target_id: int
    
    # Relationship dimensions
    trust: float = 0.5          # 0.0 (distrust) to 1.0 (complete trust)
    respect: float = 0.5        # 0.0 (contempt) to 1.0 (admiration)
    affection: float = 0.5      # 0.0 (hatred) to 1.0 (friendship)
    fear: float = 0.0           # 0.0 (not feared) to 1.0 (terrified of)
    
    # Interaction history
    interactions: List[Dict] = None
    first_contact: datetime = None
    last_interaction: datetime = None
    
    def __post_init__(self):
        self.interactions = []
        if not self.first_contact:
            self.first_contact = datetime.now()
    
    def update(self, interaction: Dict):
        """
        Update relationship based on interaction.
        """
        self.last_interaction = datetime.now()
        self.interactions.append(interaction)
        
        if interaction['type'] == 'helped_in_battle':
            self.trust += 0.2
            self.affection += 0.15
            self.respect += 0.1
        
        elif interaction['type'] == 'betrayed':
            self.trust = max(0, self.trust - 0.8)
            self.affection = max(0, self.affection - 0.7)
            self.respect = max(0, self.respect - 0.3)
        
        elif interaction['type'] == 'defeated_in_battle':
            self.fear += 0.2
            self.respect += 0.15
        
        elif interaction['type'] == 'fair_trade':
            self.trust += 0.05
            self.affection += 0.03
        
        # Normalize
        self.trust = np.clip(self.trust, 0.0, 1.0)
        self.respect = np.clip(self.respect, 0.0, 1.0)
        self.affection = np.clip(self.affection, 0.0, 1.0)
        self.fear = np.clip(self.fear, 0.0, 1.0)
    
    def get_relationship_type(self) -> str:
        """
        Classify relationship.
        """
        if self.affection > 0.7 and self.trust > 0.7:
            return "close_friend"
        elif self.affection > 0.5 and self.trust > 0.5:
            return "ally"
        elif self.fear > 0.7:
            return "feared_enemy"
        elif self.affection < 0.3 and self.trust < 0.3:
            return "enemy"
        elif self.respect > 0.7:
            return "respected_rival"
        else:
            return "neutral"
    
    def influences_decision(self, decision: Dict) -> Dict:
        """
        Modify decision based on relationship.
        """
        relationship_type = self.get_relationship_type()
        
        if decision['target_id'] == self.target_id:
            if relationship_type == "close_friend":
                # Won't attack friends
                if decision['type'] == 'attack':
                    decision['cancel'] = True
                    decision['reason'] = "Won't attack a close friend"
            
            elif relationship_type == "feared_enemy":
                # Avoid feared enemies
                if decision['type'] == 'attack':
                    decision['risk_perception'] *= 2.0
        
        return decision
```

---

## 📈 Character Development

### **NPCs Grow Over Time**

```python
class CharacterDevelopment:
    """
    NPCs develop and change over server lifespan.
    
    - Gain experience
    - Develop skills
    - Personality evolves
    - Story arcs
    """
    
    async def update_experience(
        self,
        npc_id: int,
        activity: str,
        success: bool
    ):
        """
        NPCs gain experience from activities.
        """
        xp_gains = {
            "battle": 10 if success else 5,
            "trade": 3,
            "diplomacy": 5,
            "expansion": 15 if success else 5
        }
        
        xp = xp_gains.get(activity, 1)
        
        # Update NPC level
        await self.db.execute("""
            UPDATE npcs
            SET 
                experience = experience + $1,
                level = FLOOR(POWER(experience + $1, 0.5))
            WHERE id = $2
        """, xp, npc_id)
        
        # Check for personality evolution
        await self._check_personality_evolution(npc_id)
    
    async def _check_personality_evolution(self, npc_id: int):
        """
        Personality evolves based on experiences.
        
        Examples:
        - Lots of successful battles → more confident, less neurotic
        - Multiple betrayals → less agreeable, more suspicious
        - Economic success → more conscientious
        """
        # Get experience distribution
        experiences = await self.db.fetch("""
            SELECT 
                activity_type,
                COUNT(*) as count,
                AVG(CASE WHEN success THEN 1 ELSE 0 END) as success_rate
            FROM npc_experiences
            WHERE npc_id = $1
            GROUP BY activity_type
        """, npc_id)
        
        # Calculate personality changes
        personality_changes = {}
        
        for exp in experiences:
            if exp['activity_type'] == 'battle':
                if exp['success_rate'] > 0.7:
                    # Successful warrior → less neurotic, more confident
                    personality_changes['neuroticism'] = -0.05
                    personality_changes['conscientiousness'] = +0.03
            
            elif exp['activity_type'] == 'diplomacy':
                if exp['count'] > 50:
                    # Active diplomat → more extraverted
                    personality_changes['extraversion'] = +0.03
                    personality_changes['agreeableness'] = +0.02
        
        # Apply changes
        if personality_changes:
            await self._apply_personality_changes(npc_id, personality_changes)
```

---

## 🚀 Next Steps

- **EDGE-CASES-HANDLING.md** - Unusual situations
- **CROSS-WORLD-LEARNING.md** - Multi-server AI
- **VOICE-INTERFACE.md** - Voice commands

**Your NPCs will have depth like real humans!** 🧠
