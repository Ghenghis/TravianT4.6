# Multi-Agent Coordination - Alliance Warfare & Teamwork

## 🎯 Overview

**Current Weakness**: NPCs act independently without true coordination. Alliance attacks aren't synchronized, no shared strategies.

**Solution**: Implement swarm intelligence, coordinated strategies, and collective decision-making for alliance warfare.

---

## 🤝 Coordination Architecture

```
Alliance Coordination System
├── Shared Intelligence Network
│   ├── Scout Data Sharing
│   ├── Enemy Movement Tracking
│   ├── Resource Pool Management
│   └── Threat Assessment
│
├── Coordinated Actions
│   ├── Synchronized Attacks
│   ├── Defensive Positioning
│   ├── Resource Distribution
│   └── Territory Control
│
├── Strategic Planning
│   ├── War Councils (LLM-powered)
│   ├── Target Selection
│   ├── Force Allocation
│   └── Victory Conditions
│
└── Communication Protocol
    ├── Real-time Messaging
    ├── Command & Control
    ├── Emergency Response
    └── Success/Failure Feedback
```

---

## 🧠 Swarm Intelligence

### **Collective Decision Making**

```python
from typing import List, Dict, Set
from dataclasses import dataclass
import asyncio
import numpy as np

@dataclass
class CoordinationProposal:
    """
    A proposed coordinated action from one alliance member.
    """
    proposer_npc_id: int
    action_type: str  # "mass_attack", "defense", "expansion"
    target: Dict  # Target details
    participants_needed: int
    proposed_timing: datetime
    resources_required: Dict[str, int]
    expected_outcome: str
    confidence: float
    votes: Dict[int, str] = None  # {npc_id: "approve/reject/abstain"}
    
    def __post_init__(self):
        self.votes = {} if self.votes is None else self.votes


class AllianceCoordinator:
    """
    Coordinate actions across multiple NPCs in an alliance.
    
    Uses consensus-based decision making where NPCs vote on proposals.
    """
    
    def __init__(self, alliance_id: int, llm_client):
        self.alliance_id = alliance_id
        self.llm = llm_client
        self.active_proposals = []
        self.shared_intelligence = {}
        self.member_capabilities = {}
    
    async def propose_coordinated_attack(
        self,
        proposer_npc: int,
        target_player_id: int,
        target_villages: List[int]
    ) -> CoordinationProposal:
        """
        Propose a coordinated attack to alliance.
        
        Other NPCs will vote on whether to participate.
        """
        # Analyze target
        target_analysis = await self._analyze_target(
            target_player_id,
            target_villages
        )
        
        # Calculate required force
        required_force = await self._calculate_required_force(
            target_analysis
        )
        
        # Create proposal
        proposal = CoordinationProposal(
            proposer_npc_id=proposer_npc,
            action_type="mass_attack",
            target={
                "player_id": target_player_id,
                "villages": target_villages,
                "estimated_defense": target_analysis['defense'],
                "strategic_value": target_analysis['value']
            },
            participants_needed=required_force['participants'],
            proposed_timing=self._calculate_optimal_timing(target_analysis),
            resources_required=required_force['resources'],
            expected_outcome=target_analysis['expected_outcome'],
            confidence=target_analysis['confidence']
        )
        
        # Broadcast to alliance
        await self._broadcast_proposal(proposal)
        
        # Wait for votes (24 hours)
        await self._collect_votes(proposal, timeout_hours=24)
        
        # Execute if approved
        if self._is_approved(proposal):
            await self._execute_coordinated_attack(proposal)
        
        return proposal
    
    async def _broadcast_proposal(self, proposal: CoordinationProposal):
        """
        Send proposal to all alliance members for voting.
        """
        members = await self._get_alliance_members()
        
        for member in members:
            if member['npc_id'] == proposal.proposer_npc_id:
                continue  # Skip proposer
            
            # LLM-powered voting decision
            vote_prompt = f"""
You are {member['name']}, a {member['personality']} player in alliance.

PROPOSAL from {await self._get_npc_name(proposal.proposer_npc_id)}:
Action: Coordinated attack on {proposal.target['player_id']}
Target villages: {len(proposal.target['villages'])}
Estimated defense: {proposal.target['estimated_defense']}
Participants needed: {proposal.participants_needed}
Your contribution: {proposal.resources_required}
Attack timing: {proposal.proposed_timing}

Expected outcome: {proposal.expected_outcome}
Confidence: {proposal.confidence*100:.0f}%

Your current status:
- Villages: {member['villages']}
- Army strength: {member['army_strength']}
- Resources: {member['resources']}
- Recent battles: {member['recent_battles']}

Should you vote to participate?
Consider:
- Is this strategically valuable?
- Can you afford the contribution?
- Is timing good for you?
- Do you trust the proposer's analysis?

Response JSON: {{"vote": "approve/reject/abstain", "reason": "...", "conditions": "..."}}
"""
            
            vote_response = await self.llm.generate(vote_prompt, temperature=0.7)
            vote_data = json.loads(vote_response)
            
            # Record vote
            proposal.votes[member['npc_id']] = vote_data['vote']
            
            # Store reasoning for learning
            await self._record_vote_reasoning(
                member['npc_id'],
                proposal,
                vote_data
            )
    
    def _is_approved(self, proposal: CoordinationProposal) -> bool:
        """
        Check if proposal is approved.
        
        Requires:
        - Majority approval
        - Minimum quorum
        - Enough participants
        """
        if not proposal.votes:
            return False
        
        total_votes = len(proposal.votes)
        approve_votes = sum(1 for v in proposal.votes.values() if v == "approve")
        
        # Quorum: >50% must vote
        quorum_met = total_votes >= len(self.member_capabilities) * 0.5
        
        # Majority: >60% approval
        approval_rate = approve_votes / total_votes if total_votes > 0 else 0
        majority_approved = approval_rate > 0.6
        
        # Enough participants
        enough_participants = approve_votes >= proposal.participants_needed
        
        return quorum_met and majority_approved and enough_participants
    
    async def _execute_coordinated_attack(self, proposal: CoordinationProposal):
        """
        Execute approved coordinated attack.
        
        Synchronizes attacks to arrive simultaneously.
        """
        participants = [
            npc_id for npc_id, vote in proposal.votes.items()
            if vote == "approve"
        ]
        
        # Assign targets to participants
        assignments = await self._assign_attack_targets(
            participants,
            proposal.target['villages']
        )
        
        # Calculate synchronized arrival time
        arrival_time = proposal.proposed_timing
        
        # Send attack orders
        for npc_id, assignment in assignments.items():
            # Calculate launch time for this NPC
            distance = await self._calculate_distance(
                npc_id,
                assignment['target_village']
            )
            
            troop_speed = await self._get_troop_speed(
                assignment['troops']
            )
            
            travel_time = distance / troop_speed
            launch_time = arrival_time - timedelta(hours=travel_time)
            
            # Schedule attack
            await self._schedule_attack(
                npc_id=npc_id,
                target_village=assignment['target_village'],
                troops=assignment['troops'],
                launch_at=launch_time,
                arrival_at=arrival_time,
                coordination_id=proposal.id
            )
        
        # Monitor execution
        await self._monitor_coordinated_attack(proposal)
    
    async def _monitor_coordinated_attack(self, proposal: CoordinationProposal):
        """
        Monitor coordinated attack and provide real-time updates.
        """
        while True:
            # Check status
            status = await self._get_attack_status(proposal)
            
            # All attacks landed?
            if status['all_arrived']:
                # Analyze results
                results = await self._analyze_attack_results(proposal)
                
                # Share results with all participants
                await self._broadcast_results(proposal, results)
                
                # Learn from outcome
                await self._record_coordination_outcome(proposal, results)
                
                break
            
            # Check for emergencies
            if status['reinforcements_detected']:
                # Emergency: unexpected reinforcements!
                await self._handle_emergency(proposal, status)
            
            await asyncio.sleep(60)  # Check every minute


# Shared Intelligence Network
class SharedIntelligence:
    """
    Alliance-wide intelligence sharing.
    
    All scout reports, battle results, and observations
    are shared across the alliance.
    """
    
    def __init__(self, alliance_id: int, redis_client):
        self.alliance_id = alliance_id
        self.redis = redis_client
    
    async def share_scout_report(
        self,
        npc_id: int,
        target_village_id: int,
        intel: Dict
    ):
        """
        Share scout intelligence with alliance.
        """
        key = f"alliance:{self.alliance_id}:intel:village:{target_village_id}"
        
        intel_data = {
            "source_npc": npc_id,
            "timestamp": datetime.now().isoformat(),
            "troops": intel['troops'],
            "resources": intel['resources'],
            "buildings": intel.get('buildings'),
            "confidence": intel.get('confidence', 0.8)
        }
        
        # Store in Redis with 7-day TTL
        await self.redis.setex(
            key,
            86400 * 7,
            json.dumps(intel_data)
        )
        
        # Notify alliance members
        await self._notify_new_intel(target_village_id, intel_data)
    
    async def get_alliance_intel(
        self,
        target_village_id: int
    ) -> Optional[Dict]:
        """
        Retrieve shared intelligence about a target.
        """
        key = f"alliance:{self.alliance_id}:intel:village:{target_village_id}"
        data = await self.redis.get(key)
        
        if data:
            return json.loads(data)
        
        return None
    
    async def track_enemy_movements(
        self,
        enemy_player_id: int
    ) -> Dict:
        """
        Aggregate all alliance intelligence on an enemy.
        """
        # Get all villages of enemy
        villages = await self._get_player_villages(enemy_player_id)
        
        # Collect intel on each
        intel_summary = {
            "total_defense": 0,
            "total_resources": 0,
            "village_intel": [],
            "activity_pattern": {},
            "threat_level": 0.0
        }
        
        for village in villages:
            intel = await self.get_alliance_intel(village['id'])
            
            if intel:
                intel_summary['village_intel'].append({
                    "village_id": village['id'],
                    "coords": (village['x'], village['y']),
                    **intel
                })
                
                # Aggregate
                if 'troops' in intel:
                    intel_summary['total_defense'] += self._calculate_defense_power(intel['troops'])
                
                if 'resources' in intel:
                    intel_summary['total_resources'] += sum(intel['resources'].values())
        
        # Calculate threat level
        intel_summary['threat_level'] = self._calculate_threat_level(intel_summary)
        
        return intel_summary
```

---

## ⚔️ Coordinated Attack Patterns

### **Multi-Wave Attacks**

```python
class CoordinatedAttackPatterns:
    """
    Advanced coordinated attack strategies.
    """
    
    async def execute_hammer_and_anvil(
        self,
        alliance_coordinator: AllianceCoordinator,
        target_village: int,
        hammer_npcs: List[int],  # Offensive force
        anvil_npcs: List[int]    # Blocking force
    ):
        """
        Hammer and Anvil tactic:
        1. Anvil NPCs send slow troops to arrive first
        2. Hammer NPCs send fast troops to arrive shortly after
        3. Enemy reinforcements blocked by Anvil
        4. Hammer destroys enemy
        """
        target_timing = datetime.now() + timedelta(hours=12)
        
        # Anvil arrives first (blocks reinforcements)
        anvil_arrival = target_timing - timedelta(minutes=5)
        
        for npc_id in anvil_npcs:
            await alliance_coordinator._schedule_attack(
                npc_id=npc_id,
                target_village=target_village,
                troops={"swordsman": 500},  # Defensive units
                arrival_at=anvil_arrival,
                attack_type="block_reinforcements"
            )
        
        # Hammer arrives second (main attack)
        hammer_arrival = target_timing
        
        for npc_id in hammer_npcs:
            await alliance_coordinator._schedule_attack(
                npc_id=npc_id,
                target_village=target_village,
                troops={"legionnaire": 1000, "praetorian": 500},
                arrival_at=hammer_arrival,
                attack_type="main_assault"
            )
    
    async def execute_fake_and_real(
        self,
        alliance_coordinator: AllianceCoordinator,
        target_villages: List[int],
        decoy_npcs: List[int],
        real_attackers: List[int]
    ):
        """
        Fake and Real tactic:
        1. Send many small fake attacks on multiple villages
        2. Enemy spreads defense thin
        3. Real attack hits weakest point
        """
        # Send fakes to all targets
        for village in target_villages:
            for npc_id in decoy_npcs:
                await alliance_coordinator._schedule_attack(
                    npc_id=npc_id,
                    target_village=village,
                    troops={"pathfinder": 1},  # Tiny fake
                    arrival_at=datetime.now() + timedelta(hours=8),
                    attack_type="fake"
                )
        
        # Wait a bit, then analyze which village has weakest defense
        await asyncio.sleep(3600)  # 1 hour
        
        intel = await alliance_coordinator.shared_intelligence.get_weakest_village(target_villages)
        
        # Real attack on weakest village
        for npc_id in real_attackers:
            await alliance_coordinator._schedule_attack(
                npc_id=npc_id,
                target_village=intel['village_id'],
                troops={"legionnaire": 2000},
                arrival_at=datetime.now() + timedelta(hours=7),
                attack_type="real"
            )
```

---

## 🛡️ Coordinated Defense

```python
class CoordinatedDefense:
    """
    Alliance-wide defensive coordination.
    """
    
    async def emergency_defense(
        self,
        alliance_id: int,
        attacked_village: int,
        incoming_attacks: List[Dict]
    ):
        """
        Coordinate emergency defensive response.
        
        All nearby alliance members send reinforcements.
        """
        # Analyze threat
        total_threat = sum(a['estimated_force'] for a in incoming_attacks)
        
        # Call for help
        nearby_allies = await self._find_nearby_allies(
            attacked_village,
            max_distance=30
        )
        
        # Calculate needed reinforcements
        current_defense = await self._get_village_defense(attacked_village)
        deficit = max(0, total_threat * 1.2 - current_defense)
        
        # Distribute defense request
        for ally in nearby_allies:
            # Can this ally help?
            can_contribute = await self._calculate_contribution(
                ally,
                attacked_village,
                deficit
            )
            
            if can_contribute['troops']:
                # Send reinforcements
                await self._send_reinforcements(
                    from_npc=ally['npc_id'],
                    to_village=attacked_village,
                    troops=can_contribute['troops'],
                    arrival_before=min(a['arrival_time'] for a in incoming_attacks)
                )
```

---

## 🎯 Resource Pooling

```python
class AllianceResourcePool:
    """
    Shared resource management for alliance.
    
    Members contribute to pool for:
    - War effort
    - New member support
    - Wonder building
    """
    
    async def contribute_to_pool(
        self,
        npc_id: int,
        resources: Dict[str, int]
    ):
        """NPC contributes resources to alliance pool"""
        pass
    
    async def request_from_pool(
        self,
        npc_id: int,
        resources: Dict[str, int],
        reason: str
    ) -> bool:
        """
        Request resources from pool.
        
        Approved if:
        - Good standing in alliance
        - Valid reason
        - Pool has resources
        """
        pass
```

---

## 🚀 Next Steps

- **AI-ETHICS-BALANCE.md** - Fair gameplay
- **ADVANCED-STRATEGIES.md** - Meta-game tactics
- **PERSONALITY-PSYCHOLOGY.md** - Deep NPC personalities

**Your NPCs will coordinate like pro teams!** 🤝
