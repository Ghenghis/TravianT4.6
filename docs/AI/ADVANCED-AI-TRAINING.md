# Advanced AI Training & Improvement - Continuous Learning Framework

## 🎯 Overview

**Current Weakness**: NPCs don't improve over time. They use static LLM prompts without learning from experience.

**Solution**: Implement reinforcement learning, fine-tuning, and continuous improvement systems that make NPCs smarter with every game played.

---

## 🧠 Learning Architecture

```
Learning Pipeline
├── Experience Collection
│   ├── Battle Outcomes
│   ├── Economic Performance
│   ├── Diplomatic Success
│   └── Strategic Wins/Losses
│
├── Reward Calculation
│   ├── Short-term Rewards (resources gained)
│   ├── Long-term Rewards (rank improvement)
│   └── Penalty Signals (losses, defeats)
│
├── Model Improvement
│   ├── Prompt Optimization
│   ├── Few-Shot Learning
│   ├── LoRA Fine-tuning
│   └── Behavior Parameter Tuning
│
└── Deployment
    ├── A/B Testing
    ├── Gradual Rollout
    └── Performance Validation
```

---

## 📊 Experience Collection System

### **Track Everything**

```python
from dataclasses import dataclass
from typing import Dict, List, Optional
from datetime import datetime
import numpy as np

@dataclass
class ExperienceRecord:
    """
    Single experience for learning.
    """
    # Context
    npc_id: int
    timestamp: datetime
    game_day: int
    
    # State before action
    state_before: Dict
    """
    {
        "rank": 42,
        "villages": 3,
        "population": 5000,
        "resources": {...},
        "threats": [...],
        "opportunities": [...]
    }
    """
    
    # Action taken
    action_type: str  # "attack", "build", "trade", "diplomacy"
    action_details: Dict
    decision_confidence: float
    used_llm: bool
    llm_reasoning: Optional[str]
    
    # State after action
    state_after: Dict
    
    # Outcome
    immediate_reward: float  # -1.0 to +1.0
    long_term_reward: Optional[float]  # Updated later
    success: bool
    
    # Metadata
    opponent_id: Optional[int]
    related_experiences: List[int]  # IDs of related experiences


class ExperienceCollector:
    """
    Collect and store experiences for learning.
    """
    
    def __init__(self, db_pool):
        self.db = db_pool
        self.buffer = []
        self.buffer_size = 100
    
    async def record_experience(
        self,
        npc_id: int,
        action_type: str,
        state_before: Dict,
        action_details: Dict,
        decision_data: Dict
    ) -> int:
        """
        Record an experience.
        
        Returns:
            Experience ID for later reward updates
        """
        exp = ExperienceRecord(
            npc_id=npc_id,
            timestamp=datetime.now(),
            game_day=await self._get_game_day(),
            state_before=state_before,
            action_type=action_type,
            action_details=action_details,
            decision_confidence=decision_data.get('confidence', 0.5),
            used_llm=decision_data.get('used_llm', False),
            llm_reasoning=decision_data.get('reasoning'),
            state_after={},  # Will update later
            immediate_reward=0.0,  # Calculate when outcome known
            long_term_reward=None,
            success=False
        )
        
        # Store in database
        exp_id = await self._save_experience(exp)
        
        # Add to buffer for batch processing
        self.buffer.append(exp_id)
        
        if len(self.buffer) >= self.buffer_size:
            await self._process_buffer()
        
        return exp_id
    
    async def update_outcome(
        self,
        exp_id: int,
        state_after: Dict,
        immediate_reward: float,
        success: bool
    ):
        """
        Update experience with outcome.
        """
        async with self.db.acquire() as conn:
            await conn.execute("""
                UPDATE npc_experiences
                SET 
                    state_after = $1,
                    immediate_reward = $2,
                    success = $3,
                    outcome_recorded_at = NOW()
                WHERE id = $4
            """, state_after, immediate_reward, success, exp_id)
    
    async def calculate_long_term_rewards(self):
        """
        Calculate delayed rewards for recent experiences.
        
        Example: An attack that seemed good immediately
        might have led to retaliation later (negative long-term reward)
        """
        async with self.db.acquire() as conn:
            # Get experiences from last 24 hours without long-term rewards
            experiences = await conn.fetch("""
                SELECT 
                    e.id,
                    e.npc_id,
                    e.action_type,
                    e.immediate_reward,
                    e.timestamp,
                    
                    -- Current state of NPC
                    s.rank as current_rank,
                    s.population as current_population,
                    
                    -- State at time of action
                    (e.state_before->>'rank')::int as rank_before
                    
                FROM npc_experiences e
                JOIN npc_world_state s ON e.npc_id = s.npc_id
                WHERE 
                    e.timestamp > NOW() - INTERVAL '24 hours'
                    AND e.long_term_reward IS NULL
                    AND e.outcome_recorded_at IS NOT NULL
            """)
            
            for exp in experiences:
                # Calculate long-term impact
                rank_change = exp['rank_before'] - exp['current_rank']
                
                # Better rank = positive reward
                long_term_reward = rank_change / 100.0  # Normalize
                
                # Update
                await conn.execute("""
                    UPDATE npc_experiences
                    SET long_term_reward = $1
                    WHERE id = $2
                """, long_term_reward, exp['id'])


# Reward calculation functions
class RewardCalculator:
    """
    Calculate rewards for different action types.
    """
    
    @staticmethod
    def calculate_battle_reward(
        battle_result: Dict,
        prediction: Dict
    ) -> float:
        """
        Reward for battle decisions.
        
        High reward if:
        - Won with low casualties
        - Prediction was accurate
        - Gained valuable resources
        """
        reward = 0.0
        
        # Win/loss
        if battle_result['result'] == 'victory':
            reward += 0.5
        elif battle_result['result'] == 'defeat':
            reward -= 0.5
        
        # Casualty efficiency
        if battle_result['my_casualties'] > 0:
            efficiency = (
                battle_result['enemy_casualties'] / 
                battle_result['my_casualties']
            )
            reward += min(efficiency / 10, 0.3)  # Cap at +0.3
        
        # Resource gain
        resources = battle_result.get('resources_gained', 0)
        reward += min(resources / 100000, 0.2)  # Cap at +0.2
        
        # Prediction accuracy bonus
        if prediction and 'predicted_result' in prediction:
            if prediction['predicted_result'] == battle_result['result']:
                reward += 0.1  # Accurate prediction
        
        return np.clip(reward, -1.0, 1.0)
    
    @staticmethod
    def calculate_economic_reward(
        action: Dict,
        state_before: Dict,
        state_after: Dict
    ) -> float:
        """
        Reward for economic decisions.
        
        High reward if:
        - Production increased
        - Resources not wasted
        - Building queue optimal
        """
        reward = 0.0
        
        # Production increase
        prod_before = state_before.get('total_production', 0)
        prod_after = state_after.get('total_production', 0)
        prod_increase = (prod_after - prod_before) / max(prod_before, 1)
        reward += prod_increase * 0.5
        
        # Resource waste penalty
        waste = state_after.get('resources_wasted', 0)
        reward -= waste / 50000  # Penalize waste
        
        # Queue efficiency
        queue_uptime = state_after.get('queue_uptime', 0.5)
        reward += (queue_uptime - 0.8) * 0.3  # Reward high uptime
        
        return np.clip(reward, -1.0, 1.0)
    
    @staticmethod
    def calculate_diplomatic_reward(
        action: Dict,
        outcome: Dict
    ) -> float:
        """
        Reward for diplomatic actions.
        
        High reward if:
        - Alliance formed successfully
        - Treaty beneficial
        - Relationship improved
        """
        reward = 0.0
        
        # Success/failure
        if outcome.get('accepted', False):
            reward += 0.4
        else:
            reward -= 0.2
        
        # Relationship change
        trust_delta = outcome.get('trust_change', 0)
        reward += trust_delta / 100  # Normalize to -1 to +1
        
        # Strategic value
        if outcome.get('strategic_value') == 'high':
            reward += 0.3
        elif outcome.get('strategic_value') == 'low':
            reward -= 0.1
        
        return np.clip(reward, -1.0, 1.0)
```

---

## 🎓 Prompt Optimization

### **Dynamic Prompt Engineering**

```python
class PromptOptimizer:
    """
    Optimize LLM prompts based on success rates.
    """
    
    def __init__(self, llm_client):
        self.llm = llm_client
        self.prompt_templates = {}
        self.performance_history = {}
    
    async def optimize_prompt(
        self,
        prompt_type: str,
        current_template: str
    ) -> str:
        """
        Improve prompt based on historical performance.
        
        Uses:
        1. A/B testing between variations
        2. LLM-generated improvements
        3. Successful examples as few-shot
        """
        # Get performance data
        perf = await self._get_prompt_performance(prompt_type)
        
        if perf['success_rate'] < 0.6:
            # Prompt needs improvement
            
            # Collect successful examples
            successful_examples = await self._get_successful_examples(
                prompt_type,
                limit=5
            )
            
            # Ask LLM to improve prompt
            improvement_prompt = f"""
Analyze this LLM prompt that has only {perf['success_rate']*100:.1f}% success rate:

CURRENT PROMPT:
{current_template}

SUCCESSFUL EXAMPLES:
{self._format_examples(successful_examples)}

FAILED EXAMPLES:
{self._format_examples(await self._get_failed_examples(prompt_type, limit=3))}

Create an improved version of the prompt that:
1. Maintains the same structure
2. Adds clarity where examples failed
3. Includes relevant constraints
4. Uses successful patterns

IMPROVED PROMPT:
"""
            
            improved = await self.llm.generate(
                improvement_prompt,
                temperature=0.3
            )
            
            # Test new prompt (A/B test)
            await self._register_prompt_variant(
                prompt_type,
                improved,
                variant_name="optimized_v1"
            )
            
            return improved
        
        return current_template
    
    async def add_few_shot_examples(
        self,
        prompt_type: str,
        base_prompt: str
    ) -> str:
        """
        Add successful examples to prompt for few-shot learning.
        """
        examples = await self._get_successful_examples(
            prompt_type,
            limit=3
        )
        
        few_shot_section = "\n\nSUCCESSFUL EXAMPLES:\n\n"
        for i, ex in enumerate(examples, 1):
            few_shot_section += f"Example {i}:\n"
            few_shot_section += f"Situation: {ex['context']}\n"
            few_shot_section += f"Decision: {ex['decision']}\n"
            few_shot_section += f"Outcome: {ex['outcome']} (success)\n\n"
        
        # Insert before final instruction
        improved_prompt = base_prompt + few_shot_section + "\nYour turn:\n"
        
        return improved_prompt
```

---

## 🔧 LoRA Fine-tuning (Advanced)

### **Fine-tune LLM for Better NPC Behavior**

```python
from transformers import AutoModelForCausalLM, AutoTokenizer, TrainingArguments
from peft import LoraConfig, get_peft_model, prepare_model_for_kbit_training
from datasets import Dataset
import torch

class NPCModelFineTuner:
    """
    Fine-tune base LLM for better NPC decision-making.
    
    Uses LoRA (Low-Rank Adaptation) for efficient fine-tuning.
    """
    
    def __init__(
        self,
        base_model: str = "mistralai/Mistral-7B-Instruct-v0.3",
        output_dir: str = "./fine-tuned-models"
    ):
        self.base_model = base_model
        self.output_dir = output_dir
    
    async def prepare_training_data(
        self,
        min_reward: float = 0.5
    ) -> Dataset:
        """
        Prepare training dataset from successful experiences.
        
        Only includes experiences with high rewards.
        """
        # Get successful experiences
        async with self.db.acquire() as conn:
            experiences = await conn.fetch("""
                SELECT 
                    llm_prompt,
                    llm_response,
                    immediate_reward,
                    long_term_reward,
                    success
                FROM npc_experiences
                WHERE 
                    used_llm = TRUE
                    AND immediate_reward >= $1
                    AND success = TRUE
                ORDER BY immediate_reward DESC
                LIMIT 1000
            """, min_reward)
        
        # Format for training
        training_data = []
        for exp in experiences:
            training_data.append({
                "text": f"### Instruction:\n{exp['llm_prompt']}\n\n### Response:\n{exp['llm_response']}"
            })
        
        return Dataset.from_list(training_data)
    
    async def fine_tune(
        self,
        training_data: Dataset,
        epochs: int = 3,
        learning_rate: float = 2e-4
    ):
        """
        Fine-tune model using LoRA.
        """
        # Load base model
        model = AutoModelForCausalLM.from_pretrained(
            self.base_model,
            load_in_8bit=True,
            device_map="auto"
        )
        
        tokenizer = AutoTokenizer.from_pretrained(self.base_model)
        
        # Prepare for LoRA
        model = prepare_model_for_kbit_training(model)
        
        # LoRA configuration
        lora_config = LoraConfig(
            r=16,  # Rank
            lora_alpha=32,
            target_modules=["q_proj", "v_proj"],
            lora_dropout=0.05,
            bias="none",
            task_type="CAUSAL_LM"
        )
        
        # Apply LoRA
        model = get_peft_model(model, lora_config)
        
        # Training arguments
        training_args = TrainingArguments(
            output_dir=self.output_dir,
            num_train_epochs=epochs,
            per_device_train_batch_size=4,
            gradient_accumulation_steps=4,
            learning_rate=learning_rate,
            logging_steps=10,
            save_steps=100,
            warmup_steps=50,
        )
        
        # Train
        from transformers import Trainer
        
        trainer = Trainer(
            model=model,
            args=training_args,
            train_dataset=training_data,
        )
        
        trainer.train()
        
        # Save
        model.save_pretrained(f"{self.output_dir}/npc-lora")
        tokenizer.save_pretrained(f"{self.output_dir}/npc-lora")
        
        print(f"✅ Fine-tuned model saved to {self.output_dir}/npc-lora")
```

---

## 📈 Behavior Parameter Evolution

### **Genetic Algorithm for Behavior Optimization**

```python
import random
from typing import List, Dict

class BehaviorEvolution:
    """
    Evolve NPC behavior parameters using genetic algorithms.
    
    Treats behavior parameters (aggression, economy, etc.) as genes
    and evolves them based on performance.
    """
    
    def __init__(self, population_size: int = 50):
        self.population_size = population_size
        self.generation = 0
    
    async def evolve_generation(
        self,
        current_npcs: List[Dict]
    ) -> List[Dict]:
        """
        Create next generation of NPCs with better parameters.
        
        Steps:
        1. Evaluate fitness of current NPCs
        2. Select top performers
        3. Breed new NPCs from top performers
        4. Apply mutations
        5. Return new generation
        """
        # Evaluate fitness
        fitness_scores = []
        for npc in current_npcs:
            fitness = await self._calculate_fitness(npc['id'])
            fitness_scores.append((npc, fitness))
        
        # Sort by fitness
        fitness_scores.sort(key=lambda x: x[1], reverse=True)
        
        # Select top 20% for breeding
        elite_count = int(self.population_size * 0.2)
        elite = [npc for npc, _ in fitness_scores[:elite_count]]
        
        # Create new generation
        new_generation = []
        
        # Keep top 10% unchanged (elitism)
        new_generation.extend(elite[:elite_count // 2])
        
        # Breed remaining
        while len(new_generation) < self.population_size:
            parent1 = random.choice(elite)
            parent2 = random.choice(elite)
            
            child = self._crossover(parent1, parent2)
            child = self._mutate(child)
            
            new_generation.append(child)
        
        self.generation += 1
        return new_generation
    
    async def _calculate_fitness(self, npc_id: int) -> float:
        """
        Calculate fitness score for NPC.
        
        Higher = better performance
        """
        async with self.db.acquire() as conn:
            stats = await conn.fetchrow("""
                SELECT 
                    rank,
                    population,
                    villages,
                    battles_won,
                    battles_lost,
                    avg_reward
                FROM npc_performance_summary
                WHERE npc_id = $1
            """, npc_id)
        
        # Fitness function
        fitness = (
            (1000 - stats['rank']) * 10 +  # Rank (lower is better)
            stats['population'] / 100 +
            stats['villages'] * 500 +
            (stats['battles_won'] - stats['battles_lost']) * 100 +
            stats['avg_reward'] * 1000
        )
        
        return fitness
    
    def _crossover(
        self,
        parent1: Dict,
        parent2: Dict
    ) -> Dict:
        """
        Breed two NPCs to create offspring.
        """
        child_behavior = {}
        
        # Randomly inherit each trait
        for trait in ['aggression', 'economy', 'diplomacy', 'risk_tolerance', 'patience']:
            if random.random() < 0.5:
                child_behavior[trait] = parent1['behavior_template'][trait]
            else:
                child_behavior[trait] = parent2['behavior_template'][trait]
        
        # Average some traits
        child_behavior['adaptability'] = (
            parent1['behavior_template']['adaptability'] +
            parent2['behavior_template']['adaptability']
        ) / 2
        
        return {
            'name': f"Evolved_Gen{self.generation}_{random.randint(1000, 9999)}",
            'tribe': parent1['tribe'],
            'personality': parent1['personality'],
            'behavior_template': child_behavior
        }
    
    def _mutate(self, npc: Dict, mutation_rate: float = 0.1) -> Dict:
        """
        Apply random mutations to NPC.
        """
        for trait in ['aggression', 'economy', 'diplomacy', 'risk_tolerance', 'patience']:
            if random.random() < mutation_rate:
                # Mutate by ±0.1
                current = npc['behavior_template'][trait]
                mutation = random.uniform(-0.1, 0.1)
                npc['behavior_template'][trait] = np.clip(
                    current + mutation,
                    0.0,
                    1.0
                )
        
        return npc
```

---

## 🎯 Continuous Improvement Pipeline

### **Automated Training Workflow**

```python
class ContinuousLearningPipeline:
    """
    Automated pipeline for continuous NPC improvement.
    
    Runs daily to:
    1. Collect experiences
    2. Calculate rewards
    3. Optimize prompts
    4. Update behaviors
    5. Deploy improvements
    """
    
    def __init__(self):
        self.experience_collector = ExperienceCollector()
        self.reward_calculator = RewardCalculator()
        self.prompt_optimizer = PromptOptimizer()
        self.behavior_evolution = BehaviorEvolution()
    
    async def run_daily_improvement(self):
        """
        Daily improvement cycle.
        """
        print("🎓 Starting daily AI improvement cycle...")
        
        # 1. Calculate long-term rewards
        print("📊 Calculating long-term rewards...")
        await self.experience_collector.calculate_long_term_rewards()
        
        # 2. Optimize prompts
        print("✏️ Optimizing LLM prompts...")
        prompt_types = ['strategic', 'tactical', 'diplomatic', 'economic']
        for prompt_type in prompt_types:
            await self.prompt_optimizer.optimize_prompt(prompt_type)
        
        # 3. Evolve behaviors (weekly)
        if datetime.now().weekday() == 0:  # Monday
            print("🧬 Evolving behavior parameters...")
            npcs = await self._get_all_npcs()
            new_generation = await self.behavior_evolution.evolve_generation(npcs)
            await self._deploy_new_generation(new_generation)
        
        # 4. Fine-tune model (monthly)
        if datetime.now().day == 1:  # First of month
            print("🔧 Fine-tuning LLM...")
            tuner = NPCModelFineTuner()
            training_data = await tuner.prepare_training_data(min_reward=0.6)
            await tuner.fine_tune(training_data)
        
        print("✅ Daily improvement cycle complete!")
    
    async def run_ab_test(
        self,
        variant_a: Dict,
        variant_b: Dict,
        test_duration_days: int = 7
    ):
        """
        A/B test two NPC configurations.
        """
        # Deploy 50/50 split
        npcs_a = await self._deploy_variant(variant_a, count=25)
        npcs_b = await self._deploy_variant(variant_b, count=25)
        
        # Wait for test duration
        await asyncio.sleep(test_duration_days * 86400)
        
        # Compare performance
        perf_a = await self._get_variant_performance(npcs_a)
        perf_b = await self._get_variant_performance(npcs_b)
        
        # Determine winner
        if perf_a['avg_fitness'] > perf_b['avg_fitness']:
            print(f"✅ Variant A wins! ({perf_a['avg_fitness']} vs {perf_b['avg_fitness']})")
            await self._deploy_variant(variant_a, count=self.population_size)
        else:
            print(f"✅ Variant B wins! ({perf_b['avg_fitness']} vs {perf_a['avg_fitness']})")
            await self._deploy_variant(variant_b, count=self.population_size)
```

---

## 📊 Performance Tracking

```sql
-- Track learning progress over time
CREATE TABLE ai_training_metrics (
    id SERIAL PRIMARY KEY,
    date DATE NOT NULL,
    
    -- Model performance
    avg_decision_accuracy FLOAT,
    avg_reward FLOAT,
    success_rate FLOAT,
    
    -- Prompt performance
    prompt_optimization_count INTEGER,
    avg_prompt_improvement FLOAT,
    
    -- Behavior evolution
    generation_number INTEGER,
    avg_fitness_score FLOAT,
    best_fitness_score FLOAT,
    
    -- A/B tests
    active_ab_tests INTEGER,
    completed_ab_tests INTEGER,
    
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_training_metrics_date ON ai_training_metrics(date DESC);
```

---

## 🚀 Next Steps

- **MULTI-AGENT-COORDINATION.md** - NPCs working together
- **AI-ETHICS-BALANCE.md** - Keeping game fair
- **ADVANCED-STRATEGIES.md** - Meta-game planning

**Your NPCs will continuously improve and become unbeatable!** 🎓
