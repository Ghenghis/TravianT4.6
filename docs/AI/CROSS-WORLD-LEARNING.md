# Cross-World Learning - Multi-Server AI Evolution

## 🎯 Overview

**Current Weakness**: NPCs reset between servers. Each server starts from scratch with no learning transfer.

**Solution**: Implement cross-world learning where NPCs improve across multiple game servers, building a knowledge base of strategies that work.

---

## 🌍 Multi-Server Architecture

```
Cross-World Learning System
├── Central Knowledge Base
│   ├── Strategy Repository
│   ├── Opponent Profiles
│   ├── Meta-Game Analysis
│   └── Best Practices Library
│
├── Per-Server Instances
│   ├── Local NPCs
│   ├── Local Strategies
│   └── Local Adaptations
│
├── Learning Pipeline
│   ├── Experience Upload
│   ├── Pattern Recognition
│   ├── Strategy Synthesis
│   └── Knowledge Distribution
│
└── Global Rankings
    ├── Best Strategies
    ├── Top NPCs
    └── Hall of Fame
```

---

## 📚 Central Knowledge Base

### **Shared Strategy Repository**

```python
from typing import Dict, List, Optional
from dataclasses import dataclass
import hashlib

@dataclass
class StrategyTemplate:
    """
    A proven strategy that worked on another server.
    """
    id: str
    name: str
    description: str
    
    # Where it was successful
    source_server: str
    success_rate: float
    games_played: int
    
    # Strategy details
    tribe: str  # Romans, Gauls, Teutons
    personality_type: str
    build_order: List[Dict]
    military_focus: str
    diplomatic_approach: str
    
    # Performance metrics
    avg_final_rank: float
    avg_population: int
    avg_villages: int
    
    # Conditions where it works
    works_best_when: List[str]
    countered_by: List[str]
    
    # Evolution
    parent_strategy: Optional[str]
    created_at: datetime
    updated_at: datetime

class CentralKnowledgeBase:
    """
    Global repository of strategies across all servers.
    """
    
    def __init__(self, db_connection):
        self.db = db_connection
    
    async def upload_successful_strategy(
        self,
        server_id: str,
        npc_id: int,
        final_stats: Dict
    ):
        """
        Upload successful NPC strategy to global repository.
        
        Called when server ends and NPC performed well.
        """
        # Extract strategy from NPC's behavior
        strategy = await self._extract_strategy(server_id, npc_id)
        
        # Calculate success metrics
        success_metrics = self._calculate_success_metrics(final_stats)
        
        # Check if similar strategy already exists
        existing = await self._find_similar_strategy(strategy)
        
        if existing:
            # Update existing strategy with new data
            await self._merge_strategies(existing, strategy, success_metrics)
        else:
            # Create new strategy template
            template = StrategyTemplate(
                id=self._generate_strategy_id(strategy),
                name=await self._generate_strategy_name(strategy),
                description=await self._describe_strategy(strategy),
                source_server=server_id,
                success_rate=success_metrics['success_rate'],
                games_played=1,
                **strategy,
                avg_final_rank=final_stats['rank'],
                avg_population=final_stats['population'],
                avg_villages=final_stats['villages'],
                created_at=datetime.now(),
                updated_at=datetime.now()
            )
            
            # Save to global database
            await self._save_strategy(template)
    
    async def get_best_strategies(
        self,
        tribe: str,
        server_characteristics: Dict,
        limit: int = 10
    ) -> List[StrategyTemplate]:
        """
        Get best proven strategies for specific conditions.
        
        Args:
            tribe: Romans, Gauls, or Teutons
            server_characteristics: {
                "speed": 100,  # Server speed multiplier
                "player_count": 500,
                "map_size": 400,
                "difficulty": "medium"
            }
        """
        # Query global database
        strategies = await self.db.fetch("""
            SELECT *
            FROM global_strategies
            WHERE 
                tribe = $1
                AND success_rate > 0.6
                AND games_played > 5
            ORDER BY 
                success_rate * LOG(games_played) DESC
            LIMIT $2
        """, tribe, limit)
        
        # Filter by server characteristics
        suitable = []
        for strategy in strategies:
            if self._is_suitable_for_server(strategy, server_characteristics):
                suitable.append(strategy)
        
        return suitable
    
    def _is_suitable_for_server(
        self,
        strategy: Dict,
        server_chars: Dict
    ) -> bool:
        """
        Check if strategy is appropriate for this server.
        
        Some strategies only work on high-speed servers,
        others on low-population servers, etc.
        """
        # Check speed compatibility
        if 'speed_preference' in strategy:
            speed_range = strategy['speed_preference']
            if not (speed_range['min'] <= server_chars['speed'] <= speed_range['max']):
                return False
        
        # Check population compatibility
        if 'population_preference' in strategy:
            pop_range = strategy['population_preference']
            if not (pop_range['min'] <= server_chars['player_count'] <= pop_range['max']):
                return False
        
        return True
    
    async def _generate_strategy_name(self, strategy: Dict) -> str:
        """
        Use LLM to generate memorable name for strategy.
        """
        prompt = f"""
Create a memorable, creative name for this Travian strategy:

Tribe: {strategy['tribe']}
Personality: {strategy['personality_type']}
Military Focus: {strategy['military_focus']}
Economic Focus: {strategy.get('economic_focus', 'balanced')}
Diplomatic Approach: {strategy['diplomatic_approach']}

Early game: {strategy['build_order'][:5]}
Mid game: {strategy.get('mid_game_strategy', 'expansion')}
Late game: {strategy.get('late_game_strategy', 'wonder')}

Examples of good names:
- "The Patient Turtle" (defensive, slow expansion)
- "Blitzkrieg Raiders" (fast aggressive expansion)
- "Economic Empire Builder" (peaceful economic focus)
- "Diplomatic Chess Master" (alliance manipulation)

Generate ONE creative name (2-4 words):
"""
        
        name = await self.llm.generate(prompt, temperature=0.8)
        return name.strip()
```

---

## 🧬 Strategy Evolution Across Servers

### **Genetic Algorithm for Global Improvement**

```python
class GlobalStrategyEvolution:
    """
    Evolve strategies across multiple servers using genetic algorithms.
    
    Best strategies from all servers breed to create better strategies.
    """
    
    async def evolve_global_generation(self):
        """
        Create next generation of strategies from global gene pool.
        
        Runs monthly to synthesize learning from all active servers.
        """
        # Get top strategies from all servers
        top_strategies = await self.kb.get_top_strategies(
            min_success_rate=0.7,
            min_games=10,
            limit=100
        )
        
        # Group by tribe
        by_tribe = {
            "Romans": [],
            "Gauls": [],
            "Teutons": []
        }
        
        for strategy in top_strategies:
            by_tribe[strategy['tribe']].append(strategy)
        
        # Evolve each tribe separately
        next_gen = []
        
        for tribe, strategies in by_tribe.items():
            # Breed best strategies
            offspring = await self._breed_strategies(strategies, count=20)
            next_gen.extend(offspring)
        
        # Test new strategies on sandbox servers
        test_results = await self._test_strategies_in_sandbox(next_gen)
        
        # Keep successful ones
        successful = [
            strategy for strategy, result in zip(next_gen, test_results)
            if result['success_rate'] > 0.6
        ]
        
        # Add to global repository
        for strategy in successful:
            await self.kb.add_evolved_strategy(strategy)
        
        return successful
    
    async def _breed_strategies(
        self,
        parent_strategies: List[Dict],
        count: int
    ) -> List[Dict]:
        """
        Breed new strategies from successful parents.
        """
        offspring = []
        
        for i in range(count):
            # Select two high-performing parents
            parent1 = self._select_parent(parent_strategies)
            parent2 = self._select_parent(parent_strategies)
            
            # Crossover
            child = self._crossover(parent1, parent2)
            
            # Mutate
            child = self._mutate(child)
            
            offspring.append(child)
        
        return offspring
    
    def _crossover(self, parent1: Dict, parent2: Dict) -> Dict:
        """
        Combine traits from two successful strategies.
        """
        child = {}
        
        # Inherit build order from one parent
        child['build_order'] = (
            parent1['build_order'] if random.random() < 0.5 
            else parent2['build_order']
        )
        
        # Blend personality traits
        child['personality'] = {
            trait: (parent1['personality'][trait] + parent2['personality'][trait]) / 2
            for trait in parent1['personality']
        }
        
        # Inherit military focus from stronger parent
        stronger_parent = (
            parent1 if parent1['success_rate'] > parent2['success_rate']
            else parent2
        )
        child['military_focus'] = stronger_parent['military_focus']
        
        # Blend diplomatic approach
        child['diplomatic_approach'] = random.choice([
            parent1['diplomatic_approach'],
            parent2['diplomatic_approach'],
            'hybrid'
        ])
        
        return child
```

---

## 📊 Cross-Server Analytics

### **Meta-Game Pattern Recognition**

```python
class CrossServerAnalytics:
    """
    Analyze patterns across all servers to identify meta trends.
    """
    
    async def analyze_global_meta(self) -> Dict:
        """
        What strategies are winning globally?
        """
        # Get data from all servers
        all_servers = await self.db.fetch("""
            SELECT 
                server_id,
                strategy_type,
                COUNT(*) as usage_count,
                AVG(final_rank) as avg_rank,
                AVG(success_rate) as avg_success
            FROM server_results
            WHERE 
                server_end_date > NOW() - INTERVAL '3 months'
            GROUP BY server_id, strategy_type
        """)
        
        # Aggregate across servers
        from collections import defaultdict
        global_stats = defaultdict(lambda: {
            'total_usage': 0,
            'avg_rank': [],
            'avg_success': []
        })
        
        for row in all_servers:
            strategy = row['strategy_type']
            global_stats[strategy]['total_usage'] += row['usage_count']
            global_stats[strategy]['avg_rank'].append(row['avg_rank'])
            global_stats[strategy]['avg_success'].append(row['avg_success'])
        
        # Calculate global performance
        meta_analysis = {}
        
        for strategy, stats in global_stats.items():
            meta_analysis[strategy] = {
                'usage_count': stats['total_usage'],
                'avg_final_rank': np.mean(stats['avg_rank']),
                'avg_success_rate': np.mean(stats['avg_success']),
                'consistency': 1 - np.std(stats['avg_rank']) / 100,  # Lower std = more consistent
                'meta_tier': self._calculate_tier(stats)
            }
        
        # Rank strategies
        ranked = sorted(
            meta_analysis.items(),
            key=lambda x: x[1]['avg_success_rate'] * x[1]['consistency'],
            reverse=True
        )
        
        return {
            'top_strategies': ranked[:10],
            'meta_trends': self._identify_trends(ranked),
            'emerging_strategies': self._find_emerging(ranked)
        }
    
    def _identify_trends(self, ranked_strategies: List) -> List[str]:
        """
        Identify meta-game trends.
        """
        trends = []
        
        # Check if aggressive strategies dominating
        aggressive_count = sum(
            1 for name, stats in ranked_strategies[:10]
            if 'aggressive' in name.lower() or 'raider' in name.lower()
        )
        
        if aggressive_count > 6:
            trends.append("Aggressive meta: Fast expansion and raiding dominates")
        
        # Check for economic strategies
        economic_count = sum(
            1 for name, stats in ranked_strategies[:10]
            if 'economic' in name.lower() or 'peaceful' in name.lower()
        )
        
        if economic_count > 6:
            trends.append("Economic meta: Building and trading outperforms aggression")
        
        return trends
```

---

## 🔄 Knowledge Transfer Protocol

### **Deploying Global Strategies to New Servers**

```python
class KnowledgeDeployment:
    """
    Deploy proven strategies from global repository to new servers.
    """
    
    async def initialize_new_server(
        self,
        server_id: str,
        server_config: Dict,
        npc_count: int = 500
    ):
        """
        Initialize new server with best global strategies.
        
        Instead of random NPCs, deploy proven winners.
        """
        # Get server characteristics
        server_chars = {
            "speed": server_config['speed'],
            "player_count": server_config['max_players'],
            "map_size": server_config['map_size'],
            "duration_days": server_config['duration']
        }
        
        # Get best strategies for each tribe
        roman_strategies = await self.kb.get_best_strategies(
            "Romans",
            server_chars,
            limit=npc_count // 3
        )
        
        gaul_strategies = await self.kb.get_best_strategies(
            "Gauls",
            server_chars,
            limit=npc_count // 3
        )
        
        teuton_strategies = await self.kb.get_best_strategies(
            "Teutons",
            server_chars,
            limit=npc_count // 3
        )
        
        # Create NPCs using proven strategies
        npcs_created = []
        
        for strategy in roman_strategies + gaul_strategies + teuton_strategies:
            npc = await self._create_npc_from_strategy(
                server_id,
                strategy
            )
            npcs_created.append(npc)
        
        # Add some experimental NPCs (10%)
        experimental_count = int(npc_count * 0.1)
        for i in range(experimental_count):
            experimental_npc = await self._create_experimental_npc(server_id)
            npcs_created.append(experimental_npc)
        
        return npcs_created
    
    async def _create_npc_from_strategy(
        self,
        server_id: str,
        strategy: StrategyTemplate
    ) -> Dict:
        """
        Create NPC instance using proven strategy.
        """
        npc = {
            "server_id": server_id,
            "name": await self._generate_npc_name(strategy),
            "tribe": strategy.tribe,
            
            # Copy strategy parameters
            "personality": strategy.personality_type,
            "behavior_template": {
                "build_order": strategy.build_order,
                "military_focus": strategy.military_focus,
                "diplomatic_approach": strategy.diplomatic_approach,
                **strategy.to_gameplay_style()
            },
            
            # Track lineage
            "strategy_id": strategy.id,
            "strategy_version": strategy.updated_at,
            "parent_strategy": strategy.parent_strategy,
            
            # Performance tracking
            "expected_rank": strategy.avg_final_rank,
            "confidence": strategy.success_rate
        }
        
        return await self.db.create_npc(npc)
```

---

## 🏆 Global Leaderboard

### **Hall of Fame: Best NPCs Across All Servers**

```python
class GlobalLeaderboard:
    """
    Track best-performing NPCs across all servers.
    """
    
    async def add_server_champion(
        self,
        server_id: str,
        npc_id: int,
        final_stats: Dict
    ):
        """
        Add server winner to hall of fame.
        """
        await self.db.execute("""
            INSERT INTO global_hall_of_fame
            (server_id, npc_id, npc_name, final_rank, population, villages, strategy_id)
            VALUES ($1, $2, $3, $4, $5, $6, $7)
        """, 
            server_id,
            npc_id,
            final_stats['name'],
            final_stats['rank'],
            final_stats['population'],
            final_stats['villages'],
            final_stats['strategy_id']
        )
    
    async def get_hall_of_fame(self, limit: int = 100) -> List[Dict]:
        """
        Get all-time best NPCs.
        """
        return await self.db.fetch("""
            SELECT 
                npc_name,
                strategy_id,
                COUNT(*) as championships,
                AVG(final_rank) as avg_rank,
                AVG(population) as avg_population,
                MIN(final_rank) as best_rank
            FROM global_hall_of_fame
            GROUP BY npc_name, strategy_id
            ORDER BY championships DESC, avg_rank ASC
            LIMIT $1
        """, limit)
```

---

## 🌐 Multi-Region Support

```python
class RegionalKnowledgeBases:
    """
    Different regions may have different meta-games.
    
    European servers might play differently than Asian servers.
    """
    
    def __init__(self):
        self.regions = {
            "NA": CentralKnowledgeBase("north_america"),
            "EU": CentralKnowledgeBase("europe"),
            "ASIA": CentralKnowledgeBase("asia"),
            "GLOBAL": CentralKnowledgeBase("global")
        }
    
    async def get_regional_strategies(
        self,
        region: str,
        **kwargs
    ) -> List[StrategyTemplate]:
        """
        Get best strategies for specific region.
        """
        # Get regional strategies
        regional = await self.regions[region].get_best_strategies(**kwargs)
        
        # Supplement with global strategies
        global_strategies = await self.regions["GLOBAL"].get_best_strategies(**kwargs)
        
        # Blend 70% regional, 30% global
        return regional[:7] + global_strategies[:3]
```

---

## 🚀 Benefits of Cross-World Learning

✅ **NPCs get smarter over time** (learn from 100+ servers)
✅ **No wasted learning** (knowledge persists between servers)
✅ **Meta-game adaptation** (strategies evolve with player tactics)
✅ **Faster improvement** (new servers start with proven strategies)
✅ **Global hall of fame** (track best NPCs ever)
✅ **Regional variations** (different playstyles per region)

**Your NPCs will become legendary!** 🏆
