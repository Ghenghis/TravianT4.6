# AI/NPC Solo-Play Travian - Complete Vision & Architecture

## 🎯 Project Vision

Transform TravianT4.6 into a **fully autonomous solo-play experience** where 50-500 AI-powered NPCs play alongside you as teammates, allies, enemies, or neutral entities - **smarter, faster, and more challenging than human players**.

---

## 🤖 What Makes This Unique

### **AI Players That Are BETTER Than Humans**

**Speed Advantages:**
- React in milliseconds, not minutes
- Process game state updates instantly
- Coordinate attacks with perfect timing
- Manage multiple villages simultaneously without errors

**Intelligence Advantages:**
- Calculate optimal building sequences
- Predict enemy movements based on patterns
- Perfect resource management (never waste production)
- Strategic alliance formation based on power dynamics
- Adapt strategies based on opponent behavior

**Endurance Advantages:**
- 24/7 active gameplay (no sleep needed)
- Consistent performance (no fatigue)
- Perfect attendance (never misses crop production times)
- Simultaneous management of dozens of villages

---

## 🏗️ System Architecture

### **Core Components**

```
┌─────────────────────────────────────────────────┐
│         Local LLM Brain (RTX 3090 Ti)          │
│  Strategic Planning, Decision Making, Diplomacy │
└─────────────────┬───────────────────────────────┘
                  │
    ┌─────────────┴──────────────┐
    │                            │
┌───▼────────┐          ┌────────▼──────┐
│  Rule-Based │          │  LLM-Enhanced │
│   Scripts   │          │  Agents       │
│  (Fast)     │          │  (Smart)      │
└───┬────────┘          └────────┬──────┘
    │                            │
    └─────────────┬──────────────┘
                  │
    ┌─────────────▼──────────────┐
    │    Game Engine Interface    │
    │  (Travian PHP Backend)      │
    └─────────────────────────────┘
```

### **Hybrid AI Approach**

**Layer 1: Rule-Based Scripts (95% of actions)**
- Building construction queues
- Resource collection
- Troop training
- Farm list raiding
- Market trading

**Layer 2: Local LLM (5% strategic decisions)**
- Alliance diplomacy
- War strategy
- Village placement planning
- Adaptive opponent analysis
- Complex negotiations

**Why Hybrid?**
- Rule-based = Fast, efficient, predictable
- LLM = Creative, adaptive, human-like
- Together = Best of both worlds

---

## 💪 Your Hardware Advantage

### **GPU Setup**

**RTX 3090 Ti (24GB)**: Primary LLM inference
- Run 13B-34B parameter models
- Sub-100ms response times
- Handle 10-20 concurrent AI agents

**Tesla P40s (24GB each)**: Parallel processing
- Distribute agent workload
- Run multiple smaller models simultaneously
- Batch processing for large-scale simulations

**Total Capacity**: 50-500 AI agents easily manageable

### **Performance Estimates**

| AI Agents | GPU Used | Response Time | Complexity |
|-----------|----------|---------------|------------|
| 50 agents | RTX 3090 Ti only | <50ms | High complexity decisions |
| 100 agents | 3090 Ti + 1 P40 | <100ms | Medium complexity |
| 200 agents | 3090 Ti + 2 P40s | <150ms | Balanced |
| 500 agents | All GPUs + caching | <200ms | Simple decisions |

---

## 🎮 AI Player Types & Roles

### **1. Aggressive Warmongers** (20% of NPCs)
**Behavior:**
- Constant expansion through conquest
- Build large armies early
- Attack weaker neighbors
- Form temporary war alliances

**Building Focus:**
- Barracks, Stable, Workshop priority
- Minimal economic buildings
- Fast troop production

**Strategy:**
- Strike when enemy armies are away
- Coordinate multi-village attacks
- Resource raids to fund wars

---

### **2. Economic Powerhouses** (30% of NPCs)
**Behavior:**
- Peaceful expansion
- Maximum resource production
- Trade-focused gameplay
- Defensive fortifications only

**Building Focus:**
- All resource fields to max level
- Multiple granaries/warehouses
- Marketplaces for trading
- Minimal military

**Strategy:**
- Feed war alliances with resources
- Become indispensable to allies
- Buy protection with trade

---

### **3. Balanced Players** (30% of NPCs)
**Behavior:**
- Mixed strategy
- Adapt based on neighbors
- Build defense when threatened
- Expand when safe

**Building Focus:**
- Balanced development
- Strong economy + decent army
- Flexible priorities

**Strategy:**
- React to game state
- Opportunistic expansion
- Defensive when needed

---

### **4. Alliance Leaders** (10% of NPCs)
**Behavior:**
- Diplomatic masterminds
- Recruit and organize allies
- Coordinate multi-player operations
- Strategic planning

**Building Focus:**
- Communication tools (embassy)
- Support infrastructure
- Moderate military

**Strategy:**
- Form powerful alliances
- Negotiate treaties
- Organize coordinated attacks
- Manage internal politics

---

### **5. Solo Assassins** (10% of NPCs)
**Behavior:**
- Lone wolf playstyle
- Strategic strikes
- Unpredictable behavior
- No permanent alliances

**Building Focus:**
- High-tier units (catapults, chiefs)
- Stealth and speed
- Special units

**Strategy:**
- Disrupt enemy plans
- Kingmaker role (decide wars)
- Mercenary-style gameplay
- Trade favors for resources

---

## 🧠 LLM Integration Strategy

### **When to Use LLM** (Strategic Layer)

**Diplomacy & Communication:**
```python
# Example: Alliance invitation
llm_prompt = """
You are {npc_name}, a {tribe} player in Travian.
Your power rank: {rank}
Your neighbors: {neighbors}
Your resources: {resources}

Another player {target_name} (rank {target_rank}) is nearby.

Should you:
1. Send alliance invitation
2. Propose NAP (non-aggression pact)
3. Prepare for war
4. Ignore them

Consider: their strength, proximity, current alliances.
Respond with JSON: {"action": "...", "message": "...", "reason": "..."}
"""

decision = local_llm.generate(llm_prompt)
```

**War Planning:**
```python
# Example: Attack decision
llm_prompt = """
You are planning a war.
Your army: {army_composition}
Enemy villages: {enemy_villages}
Enemy online patterns: {activity_data}
Your allies: {ally_list}

Plan a 3-village coordinated attack.
When to strike? Which villages? What units?

Respond with attack plan JSON.
"""
```

**Adaptive Learning:**
```python
# Example: Learn from losses
llm_prompt = """
Your attack on {target} failed.
Your losses: {casualties}
Enemy defense: {enemy_troops}

What went wrong? How to adjust strategy?
Update your opponent profile.
"""
```

---

### **When NOT to Use LLM** (Rule-Based Layer)

**Fast Repetitive Actions:**
- Building queue management (simple priority list)
- Resource collection (automatic)
- Auto-farming inactive players (predefined logic)
- Market trading (price algorithms)
- Troop training schedules (templates)

**Why?**
- LLM is 100-1000x slower than rules
- Wastes GPU on simple decisions
- Not cost-effective for routine tasks

---

## 📊 Technical Stack

### **AI/ML Components**

**Local LLM Options** (in order of recommendation):

1. **Mistral-7B-Instruct** (Primary choice)
   - Size: 7B parameters
   - VRAM: ~8GB
   - Speed: 30-50 tokens/sec on 3090 Ti
   - Perfect for: Quick strategic decisions

2. **Llama-3-8B-Instruct**
   - Size: 8B parameters
   - VRAM: ~10GB
   - Speed: 25-40 tokens/sec
   - Perfect for: Balanced performance

3. **Mixtral-8x7B** (Advanced)
   - Size: 47B parameters (sparse)
   - VRAM: ~24GB
   - Speed: 15-25 tokens/sec
   - Perfect for: Complex diplomacy, long-term planning

4. **Qwen2.5-14B** (High intelligence)
   - Size: 14B parameters
   - VRAM: ~18GB
   - Speed: 20-30 tokens/sec
   - Perfect for: Advanced strategic thinking

**Inference Engines:**
- **vLLM** (Primary) - Best for batch processing multiple agents
- **Ollama** (Backup) - Easy setup, good for development
- **LM Studio** (Development) - GUI for testing prompts

---

### **NPC Behavior Engine**

**Scripting Language:** Python 3.11+

**Core Libraries:**
```bash
pip install asyncio aiohttp sqlalchemy redis
pip install numpy pandas  # Data processing
pip install vllm transformers  # LLM inference
```

**Architecture:**
```
NPCs/
├── behaviors/           # Behavior templates
│   ├── aggressive.py
│   ├── economic.py
│   ├── balanced.py
│   ├── diplomat.py
│   └── assassin.py
├── ai/                  # AI decision layer
│   ├── llm_client.py   # vLLM interface
│   ├── strategies.py   # Strategic prompts
│   └── learning.py     # Opponent modeling
├── scripts/             # Rule-based actions
│   ├── building.py     # Build queue logic
│   ├── farming.py      # Raid scripts
│   ├── trading.py      # Market automation
│   └── training.py     # Troop production
└── engine/              # Game interface
    ├── api_client.py   # Travian API wrapper
    ├── state_manager.py # Game state tracking
    └── scheduler.py    # Action timing
```

---

## 🎯 Key Features

### **1. Building Strategy Templates**

**5 Pre-defined Layouts Per Faction:**

**Romans - Economic Layout:**
```
Fields: 4x Wheat, 4x Clay, 4x Iron, 6x Wood
Buildings: Marketplace(20), Warehouse(20), Granary(20)
```

**Romans - Military Layout:**
```
Fields: 3x each resource, 6x Wheat (cavalry food)
Buildings: Barracks(20), Stable(20), Academy(20)
```

**Gauls - Speed Layout:**
```
Fields: 5x Wheat, 5x Clay, 4x Iron, 4x Wood
Buildings: Stable(20), Smithy(20), Tournament Square(20)
```

**Teutons - Raiding Layout:**
```
Fields: 3x Wheat, 5x Wood, 5x Clay, 5x Iron
Buildings: Barracks(20), Warehouse(20), Rally Point(20)
```

**Each NPC randomly selects + adapts based on game state**

---

### **2. Intelligent Guardrails**

**Prevent NPC Chaos:**
- Max attack frequency per village
- Resource spending limits
- Alliance stability rules
- Power level balancing
- Anti-snowball mechanics

**Balance Mechanisms:**
```python
# Example: Prevent one NPC from dominating
if npc.villages > average_villages * 2:
    npc.expansion_rate *= 0.5  # Slow down
    npc.aggression *= 0.7      # Reduce attacks
```

---

### **3. Email Communication System**

**NPCs Send In-Game Messages:**
- Alliance invitations (LLM-generated)
- War declarations (formatted templates)
- Trade offers (dynamic pricing)
- Diplomacy negotiations (LLM conversations)
- Trash talk (personality-based)

**Example LLM-Generated Message:**
```
From: AI_Warrior_47
To: You
Subject: Alliance Proposal

Greetings neighbor,

I've noticed your villages near my borders. Rather than conflict,
I propose we form a defensive pact. Together we could dominate
the northwest quadrant.

I can offer:
- 10k wheat/hour tribute
- Military support against aggressors
- Shared intelligence on enemies

Interested? Let's discuss terms.

- Warrior47
```

---

## 🚀 Implementation Phases

### **Phase 1: Foundation** (Week 1)
- Set up vLLM on RTX 3090 Ti
- Create basic NPC database schema
- Build game state polling system
- Test LLM response times

### **Phase 2: Rule Engine** (Week 2)
- Implement building queue scripts
- Add farming raid logic
- Create troop training schedules
- Test 10 NPCs simultaneously

### **Phase 3: AI Layer** (Week 3)
- Integrate LLM for diplomacy
- Add strategic decision-making
- Implement opponent modeling
- Test with 50 NPCs

### **Phase 4: Advanced Behaviors** (Week 4)
- Create 5 behavior templates
- Add personality variations
- Implement alliance AI
- Test with 100+ NPCs

### **Phase 5: Optimization** (Week 5)
- Distribute load across P40s
- Add caching and batching
- Optimize prompt engineering
- Scale to 200-500 NPCs

---

## 📈 Performance Targets

| Metric | Target | How to Achieve |
|--------|--------|----------------|
| **NPC Count** | 200-500 | Multi-GPU distribution |
| **Response Time** | <200ms | Cached decisions + batch processing |
| **LLM Quality** | Human-like | Fine-tuned prompts + few-shot examples |
| **Game Balance** | Challenging but fair | Dynamic difficulty scaling |
| **Server Load** | <30% CPU | Async processing, Redis caching |

---

## 🎮 Player Experience

### **As Teammate:**
- Follows your strategic direction
- Sends resources when requested
- Defends your villages
- Coordinates attacks with you

### **As Ally:**
- Honors treaties
- Trades fairly
- Shares intelligence
- Helps in wars

### **As Enemy:**
- Challenging but beatable
- Uses diverse strategies
- Adapts to your tactics
- Creates interesting conflicts

### **As Neutral:**
- Responds to diplomacy
- Can be persuaded
- Trades opportunistically
- Stays out of conflicts

---

## 🔮 Future Enhancements

1. **Voice Commands** - Control NPCs via speech
2. **Personality Customization** - Design custom AI opponents
3. **Tournament Mode** - 100 NPCs battle royale
4. **Co-op Campaigns** - Story-driven missions with AI team
5. **Machine Learning** - NPCs learn from your playstyle

---

## 📚 Next Steps

Read the detailed implementation guides:
- `AI-AGENT-ARCHITECTURE.md` - Technical deep dive
- `NPC-BEHAVIOR-SYSTEM.md` - Behavior templates
- `LOCAL-LLM-INTEGRATION.md` - GPU setup & optimization
- `BUILDING-STRATEGIES.md` - All faction layouts
- `COMBAT-AI.md` - Battle intelligence
- `DIPLOMACY-AI.md` - Alliance & negotiation
- `IMPLEMENTATION-GUIDE.md` - Step-by-step coding
- `PERFORMANCE-OPTIMIZATION.md` - Scaling to 500 NPCs

---

**Ready to build the most advanced AI-driven Travian server ever created?** 🚀
