# Diplomacy & Alliance AI - Master Negotiator System

## 🎯 Objective

Create AI diplomats that can:
- Form and manage alliances
- Negotiate treaties and deals
- Mediate conflicts
- Coordinate group strategies
- Communicate like experienced human players
- Manipulate alliances for strategic advantage

---

## 🤝 Diplomacy AI Architecture

### **Core Components**

```
Diplomacy AI
├── Relationship Manager
│   ├── Trust Scores (-100 to +100)
│   ├── Alliance Membership
│   └── Diplomatic History
├── Communication Engine
│   ├── LLM Message Generation
│   ├── Negotiation Tactics
│   └── Tone Adaptation
├── Strategic Alliance Planner
│   ├── Power Balance Analysis
│   └── Coalition Building
└── Treaty Manager
    ├── NAP (Non-Aggression Pacts)
    ├── Trade Agreements
    └── Military Alliances
```

---

## 💬 Message Generation (LLM-Powered)

### **Alliance Invitation**

```python
async def craft_alliance_invitation(
    self,
    target_player: Player,
    alliance: Alliance
) -> str:
    """
    LLM generates personalized, persuasive invitation.
    """
    
    # Gather intelligence
    player_profile = await self.analyze_player(target_player)
    
    prompt = f"""
You are {self.npc_name}, ambassador of {alliance.name}.

Write alliance invitation to {target_player.name}.

THEIR PROFILE:
- Rank: #{target_player.rank}
- Villages: {target_player.villages}
- Population: {target_player.population}
- Play style: {player_profile['play_style']}  # aggressive/defensive/economic
- Activity: {player_profile['activity']} logins/day
- Current alliance: {target_player.alliance or 'None'}

YOUR ALLIANCE:
- Name: {alliance.name}
- Rank: #{alliance.rank}
- Members: {alliance.member_count}
- Average member rank: #{alliance.avg_member_rank}
- Territory: {alliance.territory_description}
- Play style: {alliance.culture}  # military/peaceful/balanced

BENEFITS YOU CAN OFFER:
- {alliance.benefits}

TONE: {self._select_tone(player_profile)}  # professional/casual/friendly

Requirements:
1. Personalized - reference their achievements
2. Show value - explain what they gain
3. Casual tone - sound like experienced player
4. Not desperate - we're selective
5. Call to action - invite reply
6. Max 200 words

Generate message:
"""
    
    message = await self.llm.generate(prompt, temperature=0.8)
    return message
```

**Example Generated Message:**
```
Hey {player_name},

Noticed you've been crushing it lately - 6 villages in the northwest is impressive!
We run {alliance_name}, currently #3 alliance with 45 active members.

Why I'm reaching out:
• You're in prime territory we're looking to secure
• Your growth rate suggests you know what you're doing
• We need strong players in that quadrant

What we offer:
• Coordinated defense (we've got your back)
• Resource sharing network (never run out mid-build)
• Target intelligence (we track enemies 24/7)
• Weekly organized ops (optional but fun)

We're selective - not recruiting everyone. But you'd fit our crew.

Interested? Hit me up. If not, no worries - maybe a NAP so we don't step on each other's toes?

Cheers,
{npc_name}
```

---

### **Negotiation System**

```python
class NegotiationAI:
    """
    LLM-powered negotiation that adapts to responses.
    """
    
    async def negotiate_treaty(
        self,
        other_player: Player,
        desired_outcome: str,  # "NAP", "trade_deal", "military_alliance"
        max_rounds: int = 5
    ):
        """
        Multi-round negotiation using LLM.
        """
        
        negotiation_history = []
        current_offer = await self.create_initial_offer(desired_outcome)
        
        for round_num in range(max_rounds):
            # Send offer
            await self.send_message(other_player, current_offer)
            negotiation_history.append({
                "round": round_num,
                "our_offer": current_offer,
                "sender": "us"
            })
            
            # Wait for response
            response = await self.wait_for_response(other_player, timeout=3600)
            
            if not response:
                break  # Timeout
            
            negotiation_history.append({
                "round": round_num,
                "their_response": response,
                "sender": "them"
            })
            
            # Analyze response with LLM
            analysis = await self.llm.generate(f"""
Analyze negotiation response:

NEGOTIATION HISTORY:
{json.dumps(negotiation_history, indent=2)}

THEIR LATEST MESSAGE:
"{response}"

Analyze:
1. Are they interested? (yes/no/maybe)
2. What are their concerns?
3. What do they value most?
4. Should we accept, counter, or walk away?

Suggest next move:
- If accept: Draft acceptance message
- If counter: New offer addressing their concerns
- If walk away: Polite exit

JSON response: {{
    "interest_level": "high/medium/low",
    "concerns": [...],
    "values": [...],
    "recommendation": "accept/counter/walk",
    "next_message": "..."
}}
""")
            
            decision = json.loads(analysis)
            
            if decision['recommendation'] == 'accept':
                await self.accept_deal(other_player, decision['next_message'])
                return "deal_made"
            
            elif decision['recommendation'] == 'walk':
                await self.decline_politely(other_player, decision['next_message'])
                return "no_deal"
            
            else:  # counter
                current_offer = decision['next_message']
        
        return "timeout"
```

---

## 🎭 Personality-Based Communication

### **Tone Adaptation**

```python
COMMUNICATION_TONES = {
    "aggressive": {
        "formality": 0.3,
        "friendliness": 0.2,
        "directness": 0.9,
        "emoji_usage": 0.1,
        "example": "Listen up. We control this quadrant. Join or get crushed."
    },
    
    "diplomatic": {
        "formality": 0.8,
        "friendliness": 0.7,
        "directness": 0.6,
        "emoji_usage": 0.2,
        "example": "We believe a mutually beneficial arrangement could be established."
    },
    
    "casual": {
        "formality": 0.2,
        "friendliness": 0.8,
        "directness": 0.5,
        "emoji_usage": 0.6,
        "example": "Hey! Wanna team up? Could be fun 😄"
    },
    
    "manipulative": {
        "formality": 0.5,
        "friendliness": 0.9,
        "directness": 0.3,
        "emoji_usage": 0.4,
        "example": "I totally understand your concerns... but between you and me..."
    },
    
    "mercenary": {
        "formality": 0.4,
        "friendliness": 0.3,
        "directness": 0.8,
        "emoji_usage": 0.0,
        "example": "I work for resources. 5k/day and I don't attack you."
    }
}

async def adapt_tone_to_player(self, target_player: Player) -> str:
    """
    Select communication tone based on player type.
    """
    player_type = await self.classify_player(target_player)
    
    tone_mapping = {
        "newbie": "friendly_helpful",
        "experienced": "casual",
        "aggressive": "confident_direct",
        "economic": "professional",
        "turtle": "respectful"
    }
    
    return tone_mapping.get(player_type, "casual")
```

---

## 🤖 Alliance Management AI

### **Member Recruitment**

```python
class RecruitmentAI:
    """
    Intelligent alliance recruitment system.
    """
    
    async def find_recruitment_targets(
        self,
        alliance: Alliance,
        target_count: int = 10
    ) -> List[Player]:
        """
        Identify ideal recruitment candidates.
        """
        
        # Get all players in range
        nearby_players = await self.get_players_in_territory(
            alliance.territory,
            radius=50
        )
        
        # Score each candidate
        candidates = []
        for player in nearby_players:
            if player.alliance_id:
                continue  # Already in alliance
            
            score = await self.calculate_recruitment_score(player, alliance)
            candidates.append((player, score))
        
        # Sort by score
        candidates.sort(key=lambda x: x[1], reverse=True)
        
        # Return top candidates
        return [player for player, score in candidates[:target_count]]
    
    async def calculate_recruitment_score(
        self,
        player: Player,
        alliance: Alliance
    ) -> float:
        """
        Score candidate (0.0-1.0).
        """
        score = 0.0
        
        # Activity (30%)
        activity_score = min(player.logins_per_day / 10, 1.0)
        score += activity_score * 0.3
        
        # Strength (25%)
        strength_score = min(player.population / 5000, 1.0)
        score += strength_score * 0.25
        
        # Location (20%)
        location_score = self.calculate_strategic_value(player.location, alliance)
        score += location_score * 0.2
        
        # Growth rate (15%)
        growth_score = min(player.population_growth_rate / 100, 1.0)
        score += growth_score * 0.15
        
        # Play style fit (10%)
        fit_score = self.calculate_culture_fit(player, alliance)
        score += fit_score * 0.1
        
        return score
```

---

### **Internal Politics Management**

```python
class AlliancePoliticsAI:
    """
    Manage internal alliance dynamics.
    """
    
    async def resolve_internal_conflict(
        self,
        member_a: Player,
        member_b: Player,
        conflict_type: str
    ):
        """
        Mediate conflicts between alliance members.
        """
        
        # Gather context
        context = await self.investigate_conflict(member_a, member_b)
        
        # LLM mediation
        mediation_prompt = f"""
You are alliance leader mediating conflict:

CONFLICT: {conflict_type}

MEMBER A ({member_a.name}):
- Rank: #{member_a.rank}
- Contribution: {member_a.alliance_points}
- Complaint: "{context['member_a_complaint']}"
- Evidence: {context['member_a_evidence']}

MEMBER B ({member_b.name}):
- Rank: #{member_b.rank}
- Contribution: {member_b.alliance_points}
- Complaint: "{context['member_b_complaint']}"
- Evidence: {context['member_b_evidence']}

ALLIANCE RULES:
{context['alliance_rules']}

Propose fair resolution:
1. Who is at fault (if anyone)?
2. What punishment/compensation?
3. How to prevent future conflicts?

Draft messages to both members explaining decision.

JSON: {{
    "verdict": "...",
    "resolution": "...",
    "message_to_a": "...",
    "message_to_b": "...",
    "policy_update": "..."
}}
"""
        
        mediation = await self.llm.generate(mediation_prompt)
        decision = json.loads(mediation)
        
        # Communicate decision
        await self.send_message(member_a, decision['message_to_a'])
        await self.send_message(member_b, decision['message_to_b'])
        
        # Update alliance policy if needed
        if decision['policy_update']:
            await self.update_alliance_policy(decision['policy_update'])
```

---

## ⚖️ Strategic Alliance Planning

### **Coalition Building**

```python
async def build_winning_coalition(
    self,
    target_enemy: Alliance,
    our_alliance: Alliance
):
    """
    Form coalition to defeat stronger enemy.
    """
    
    # Analyze power balance
    enemy_power = await self.calculate_alliance_power(target_enemy)
    our_power = await self.calculate_alliance_power(our_alliance)
    
    power_deficit = enemy_power - our_power
    
    if power_deficit > 0:
        # Need allies
        potential_allies = await self.find_potential_allies(
            enemy=target_enemy,
            needed_power=power_deficit
        )
        
        # LLM coalition strategy
        strategy = await self.llm.generate(f"""
We need to defeat {target_enemy.name} (power: {enemy_power}).
Our power: {our_power}
Deficit: {power_deficit}

Potential allies:
{format_potential_allies(potential_allies)}

Design coalition strategy:
1. Who to approach first?
2. What to offer each ally?
3. How to coordinate attack?
4. How to divide conquered territory?

Consider:
- Who has grudge against target?
- Who gains most from their defeat?
- Who can we trust?

JSON: {{
    "recruitment_order": [...],
    "offers": {{ally_id: "offer_details"}},
    "attack_plan": "...",
    "territory_distribution": {{...}}
}}
""")
        
        coalition_plan = json.loads(strategy)
        
        # Execute recruitment
        for ally_id in coalition_plan['recruitment_order']:
            ally = potential_allies[ally_id]
            offer = coalition_plan['offers'][ally_id]
            
            success = await self.recruit_ally(ally, offer)
            
            if success:
                our_power += await self.calculate_alliance_power(ally)
                
                if our_power > enemy_power:
                    break  # Sufficient power
        
        return coalition_plan
```

---

## 📊 Relationship Management

### **Trust System**

```python
class TrustManager:
    """
    Track and manage relationships with all players.
    """
    
    def __init__(self):
        self.trust_scores = {}  # player_id -> score (-100 to +100)
    
    async def update_trust(
        self,
        player_id: str,
        event: str,
        magnitude: float = 1.0
    ):
        """
        Adjust trust based on interactions.
        """
        
        trust_changes = {
            "alliance_formed": +30,
            "treaty_honored": +10,
            "treaty_broken": -50,
            "helped_in_battle": +20,
            "attacked_me": -40,
            "attacked_ally": -30,
            "traded_fairly": +5,
            "shared_intelligence": +15,
            "lied_to_me": -60,
            "backstabbed": -100
        }
        
        change = trust_changes.get(event, 0) * magnitude
        current = self.trust_scores.get(player_id, 0)
        
        # Update with bounds
        self.trust_scores[player_id] = max(-100, min(100, current + change))
        
        # Adapt behavior based on trust
        await self.adjust_behavior_for_player(player_id)
    
    async def adjust_behavior_for_player(self, player_id: str):
        """
        Change how we interact based on trust level.
        """
        trust = self.trust_scores.get(player_id, 0)
        
        if trust > 70:
            # Trusted ally
            self.behaviors[player_id] = {
                "share_intelligence": True,
                "send_reinforcements": True,
                "trade_favorably": True,
                "coordinate_attacks": True
            }
        
        elif trust > 30:
            # Friendly
            self.behaviors[player_id] = {
                "share_intelligence": "limited",
                "send_reinforcements": "if_convenient",
                "trade_favorably": False,
                "coordinate_attacks": False
            }
        
        elif trust > -30:
            # Neutral
            self.behaviors[player_id] = {
                "share_intelligence": False,
                "send_reinforcements": False,
                "trade_favorably": False,
                "coordinate_attacks": False
            }
        
        else:
            # Enemy
            self.behaviors[player_id] = {
                "share_intelligence": "false_info",  # Misinformation
                "attack_when_weak": True,
                "raid_frequently": True,
                "sabotage_plans": True
            }
```

---

## 🎯 Advanced Diplomacy Tactics

### **Divide and Conquer**

```python
async def drive_wedge_in_alliance(
    self,
    target_alliance: Alliance
):
    """
    Create internal conflict in enemy alliance.
    """
    
    # Identify potential fracture points
    members = await self.get_alliance_members(target_alliance)
    
    # Find disgruntled members
    weak_links = []
    for member in members:
        issues = await self.analyze_member_satisfaction(member, target_alliance)
        if issues['dissatisfaction'] > 0.5:
            weak_links.append((member, issues))
    
    # Target most vulnerable
    target_member, issues = weak_links[0]
    
    # LLM manipulation strategy
    strategy = await self.llm.generate(f"""
Target: {target_member.name} in {target_alliance.name}

Their grievances:
{json.dumps(issues['grievances'], indent=2)}

Design manipulation strategy to make them leave alliance:
1. What private message to send?
2. What to offer them?
3. How to frame their alliance as unfair?
4. How to position us as better option?

Be subtle - don't make it obvious manipulation.

JSON: {{
    "approach": "...",
    "initial_message": "...",
    "offers": [...],
    "talking_points": [...]
}}
""")
    
    plan = json.loads(strategy)
    
    # Execute slowly over multiple messages
    await self.execute_recruitment_campaign(target_member, plan)
```

---

## 📈 Metrics & Success Tracking

```python
class DiplomacyMetrics:
    """
    Track diplomatic performance.
    """
    
    metrics = {
        "alliances_formed": 0,
        "alliances_broken": 0,
        "treaties_negotiated": 0,
        "successful_recruitments": 0,
        "failed_recruitments": 0,
        "conflicts_mediated": 0,
        "wars_started": 0,
        "wars_won": 0,
        
        # Advanced
        "average_negotiation_rounds": 0.0,
        "treaty_compliance_rate": 0.0,  # How often partners honor deals
        "manipulation_success_rate": 0.0,
        
        # vs Human baseline
        "human_alliance_stability": 0.70,  # 70% of alliances last >1 month
        "ai_alliance_stability": 0.85,     # Target: 85%
    }
```

---

## 🚀 Next Steps

- **IMPLEMENTATION-GUIDE.md** - Code integration
- **PERFORMANCE-OPTIMIZATION.md** - Scale to 500 NPCs
- **BUILDING-STRATEGIES.md** - Economic foundations

**Your AI will build alliances that rival the best human diplomats!** 🤝
