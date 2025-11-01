# LLM Operations Guide - TravianT4.6

## Overview

This guide provides operational procedures for managing the dual-LLM architecture in TravianT4.6. The system uses two LLM inference engines (Ollama and vLLM) running on separate GPUs to provide AI-powered decision-making for NPCs.

**Dual-LLM Architecture:**

| LLM | GPU | Model | Use Case | Target Latency |
|-----|-----|-------|----------|----------------|
| **Ollama** | RTX 3090 Ti (device 0) | Gemma 2B | Simple decisions, fast inference | <200ms |
| **vLLM** | Tesla P40 (device 1) | LLaMA 7B | Complex decisions, deep reasoning | <500ms |

**Decision Strategy:**
- **95% Rule-Based:** Fast deterministic decisions using predefined rules (<50ms)
- **5% LLM:** Complex scenarios requiring reasoning and context understanding

**Target Performance:**
- Overall average decision time: <200ms for 500 NPCs
- Rule-based decisions: <50ms average
- LLM decisions (Ollama): <200ms average
- LLM decisions (vLLM): <500ms average

**Target Audience:** Operations team, DevOps engineers, AI/ML engineers

---

## Prerequisites

Before managing LLM operations, ensure:

### Hardware Requirements

- **GPU 0:** NVIDIA RTX 3090 Ti (24GB VRAM)
- **GPU 1:** NVIDIA Tesla P40 (24GB VRAM)
- **Host RAM:** 64GB+ recommended
- **Storage:** 100GB+ for models

### Software Requirements

- **NVIDIA Drivers:** 535.xx or later
- **CUDA:** 12.0+
- **Docker:** 24.0+ with GPU runtime support
- **NVIDIA Container Toolkit:** Latest

### Verification

```bash
# Verify GPUs visible on host (WSL2)
nvidia-smi

# Expected output:
# GPU 0: NVIDIA GeForce RTX 3090 Ti (24GB)
# GPU 1: Tesla P40 (24GB)

# Verify GPU access from Docker
docker run --rm --gpus all nvidia/cuda:12.0.0-base-ubuntu22.04 nvidia-smi

# Run GPU verification script
./scripts/verify-gpu.sh

# Expected output:
# ✅ GPU 0 (RTX 3090 Ti): Detected
# ✅ GPU 1 (Tesla P40): Detected
# ✅ NVIDIA drivers: 535.xx
# ✅ CUDA version: 12.0
# ✅ Docker GPU runtime: Configured
```

**See Also:** [GPU-SETUP.md](GPU-SETUP.md) for initial GPU configuration

---

## Ollama Operations

### Container Information

**Container Name:** `travian_ollama`

**Configuration:**
- **GPU:** RTX 3090 Ti (device 0)
- **Port:** 11434 (internal)
- **API Base URL:** `http://ollama:11434`
- **Model Storage:** `/root/.ollama/models`
- **Network:** `llm_gpu`

### Supported Models

Ollama supports various model families optimized for different use cases:

| Model | Size | Use Case | VRAM Required |
|-------|------|----------|---------------|
| **Gemma 2B** | 2B params | Fast NPC decisions (current) | ~4GB |
| Gemma 7B | 7B params | Advanced decisions | ~14GB |
| LLaMA 2 7B | 7B params | General reasoning | ~14GB |
| Mistral 7B | 7B params | High-quality reasoning | ~14GB |

**Current Production Model:** `gemma:2b`

### Model Installation

```bash
# List available models
docker exec travian_ollama ollama list

# Pull new model
docker exec travian_ollama ollama pull gemma:2b

# Pull specific version
docker exec travian_ollama ollama pull gemma:2b-v1.2

# Verify model downloaded
docker exec travian_ollama ollama list
```

**Example Output:**
```
NAME          ID              SIZE    MODIFIED
gemma:2b      abc123def456    1.4GB   2 hours ago
```

### Model Management

```bash
# Show model info
docker exec travian_ollama ollama show gemma:2b

# Test model inference
docker exec travian_ollama ollama run gemma:2b "Explain NPC strategy in 20 words"

# Remove model
docker exec travian_ollama ollama rm gemma:7b

# Check model storage usage
docker exec travian_ollama du -sh /root/.ollama/models
```

### API Endpoints

**Health Check:**
```bash
curl http://localhost:11434/api/health
# Expected: {"status":"ok"}
```

**Generate Text:**
```bash
curl http://localhost:11434/api/generate -d '{
  "model": "gemma:2b",
  "prompt": "NPC has 5000 wood, 4000 clay, 3000 iron. Should build barracks or upgrade resources?",
  "stream": false
}'
```

**List Models:**
```bash
curl http://localhost:11434/api/tags
```

**Model Info:**
```bash
curl http://localhost:11434/api/show -d '{
  "name": "gemma:2b"
}'
```

### Health Checks

```bash
# Check container status
docker ps | grep ollama

# Check container health
docker inspect travian_ollama | jq '.[0].State.Health'

# Check GPU usage
nvidia-smi

# View logs
docker compose logs ollama --tail=50

# Test API responsiveness
time curl -s http://localhost:11434/api/health
# Should respond in <100ms
```

---

## vLLM Operations

### Container Information

**Container Name:** `travian_vllm`

**Configuration:**
- **GPU:** Tesla P40 (device 1)
- **Port:** 8000 (internal)
- **API Base URL:** `http://vllm:8000`
- **Model Storage:** `/root/.cache/huggingface`
- **Network:** `llm_gpu`

### Supported Models

vLLM is optimized for high-throughput inference:

| Model | Size | Use Case | VRAM Required |
|-------|------|----------|---------------|
| **LLaMA 2 7B** | 7B params | Complex NPC reasoning (current) | ~14GB |
| LLaMA 2 13B | 13B params | Very complex scenarios | ~26GB (exceeds P40) |
| Mistral 7B | 7B params | High-quality reasoning | ~14GB |
| Vicuna 7B | 7B params | Conversational AI | ~14GB |

**Current Production Model:** `meta-llama/Llama-2-7b-hf`

### Model Installation

```bash
# Pull model from Hugging Face
docker exec travian_vllm python3 -c "
from huggingface_hub import snapshot_download
snapshot_download('meta-llama/Llama-2-7b-hf', 
                  cache_dir='/root/.cache/huggingface')
"

# List downloaded models
docker exec travian_vllm ls -lh /root/.cache/huggingface/hub/

# Check model size
docker exec travian_vllm du -sh /root/.cache/huggingface/hub/models--meta-llama--Llama-2-7b-hf
```

**Note:** Hugging Face models require authentication token for gated models like LLaMA 2.

### Model Management

```bash
# Show loaded model info
curl http://localhost:8000/v1/models

# Restart vLLM to load different model
# Edit docker-compose.yml to change MODEL_NAME
nano docker-compose.yml
# vllm:
#   environment:
#     - MODEL_NAME=meta-llama/Llama-2-7b-hf  # Change here

docker compose restart vllm

# Check vLLM startup logs
docker compose logs vllm --tail=100
# Look for "model loaded successfully"

# Remove old models
docker exec travian_vllm rm -rf /root/.cache/huggingface/hub/models--<old-model>
```

### API Endpoints

vLLM provides OpenAI-compatible API:

**Health Check:**
```bash
curl http://localhost:8000/health
# Expected: {"status":"ok"}
```

**Generate Text (OpenAI-compatible):**
```bash
curl http://localhost:8000/v1/completions -H "Content-Type: application/json" -d '{
  "model": "meta-llama/Llama-2-7b-hf",
  "prompt": "NPC faces 3 incoming attacks from different directions. Strategic response:",
  "max_tokens": 150,
  "temperature": 0.7
}'
```

**Chat Completion (OpenAI-compatible):**
```bash
curl http://localhost:8000/v1/chat/completions -H "Content-Type: application/json" -d '{
  "model": "meta-llama/Llama-2-7b-hf",
  "messages": [
    {"role": "system", "content": "You are an NPC strategy advisor."},
    {"role": "user", "content": "Should I attack now or wait for reinforcements?"}
  ],
  "max_tokens": 100,
  "temperature": 0.8
}'
```

**List Models:**
```bash
curl http://localhost:8000/v1/models
```

### Health Checks

```bash
# Check container status
docker ps | grep vllm

# Check GPU usage (should show Tesla P40)
nvidia-smi -i 1

# View logs
docker compose logs vllm --tail=50

# Test API responsiveness
time curl -s http://localhost:8000/health
# Should respond in <100ms

# Test inference latency
time curl -s http://localhost:8000/v1/completions \
  -H "Content-Type: application/json" \
  -d '{"model":"meta-llama/Llama-2-7b-hf","prompt":"Hello","max_tokens":10}'
# Should respond in <500ms
```

---

## Model Lifecycle

### Installing New Models

#### Ollama Model Installation

```bash
# Search for available models
# Visit: https://ollama.ai/library

# Install new model
docker exec travian_ollama ollama pull mistral:7b

# Verify installation
docker exec travian_ollama ollama list

# Test new model
docker exec travian_ollama ollama run mistral:7b "Test prompt"

# Update application to use new model
# Edit sections/api/include/Services/LLMIntegrationService.php
# Change model name in API calls
```

#### vLLM Model Installation

```bash
# Set Hugging Face token (if needed)
docker exec travian_vllm bash -c "export HUGGING_FACE_HUB_TOKEN=<your_token>"

# Download new model
docker exec travian_vllm python3 -c "
from huggingface_hub import snapshot_download
snapshot_download('mistralai/Mistral-7B-v0.1', 
                  cache_dir='/root/.cache/huggingface',
                  token='<your_token>')
"

# Update docker-compose.yml to use new model
nano docker-compose.yml
# Change MODEL_NAME environment variable

# Restart vLLM
docker compose restart vllm

# Wait for model to load (check logs)
docker compose logs vllm -f
```

### Updating Models

```bash
# Ollama: Pull latest version
docker exec travian_ollama ollama pull gemma:2b

# This automatically updates to latest version
# Old version is replaced

# vLLM: Download new version
docker exec travian_vllm python3 -c "
from huggingface_hub import snapshot_download
snapshot_download('meta-llama/Llama-2-7b-hf',
                  cache_dir='/root/.cache/huggingface',
                  revision='main',  # or specific version
                  force_download=True)
"

# Restart to load updated model
docker compose restart vllm
```

### Removing Models

```bash
# Ollama: Remove specific model
docker exec travian_ollama ollama rm gemma:7b

# Verify removed
docker exec travian_ollama ollama list

# vLLM: Delete model directory
docker exec travian_vllm rm -rf /root/.cache/huggingface/hub/models--mistralai--Mistral-7B-v0.1

# Check disk space reclaimed
docker exec travian_vllm df -h /root/.cache/huggingface
```

### Model Versioning

**Ollama:**
```bash
# Ollama tags models by version automatically
# List all versions
docker exec travian_ollama ollama list

# Use specific version
docker exec travian_ollama ollama run gemma:2b-v1.2
```

**vLLM:**
```bash
# Download specific commit/version
docker exec travian_vllm python3 -c "
from huggingface_hub import snapshot_download
snapshot_download('meta-llama/Llama-2-7b-hf',
                  cache_dir='/root/.cache/huggingface',
                  revision='abc123def456')  # Git commit hash
"
```

### Model Rollback

If new model performs poorly:

```bash
# Ollama: Revert to previous version
docker exec travian_ollama ollama pull gemma:2b-v1.1
# Update app config to use v1.1

# vLLM: Change docker-compose.yml to previous model
nano docker-compose.yml
# Revert MODEL_NAME to previous value

docker compose restart vllm

# Verify rollback successful
./scripts/test-llm-performance.sh
```

---

## GPU Scheduling

### Device Allocation

**Current Allocation:**
- **Ollama → GPU 0** (RTX 3090 Ti)
- **vLLM → GPU 1** (Tesla P40)

**docker-compose.yml Configuration:**

```yaml
ollama:
  deploy:
    resources:
      reservations:
        devices:
          - driver: nvidia
            device_ids: ['0']  # RTX 3090 Ti
            capabilities: [gpu]

vllm:
  deploy:
    resources:
      reservations:
        devices:
          - driver: nvidia
            device_ids: ['1']  # Tesla P40
            capabilities: [gpu]
```

### GPU Memory Management

```bash
# Monitor GPU memory usage
nvidia-smi -l 1  # Update every second

# Check memory per GPU
nvidia-smi -i 0  # RTX 3090 Ti (Ollama)
nvidia-smi -i 1  # Tesla P40 (vLLM)

# View process-level GPU usage
nvidia-smi pmon -c 1

# If GPU memory full, restart services
docker compose restart ollama
docker compose restart vllm
```

**GPU Memory Limits:**
- RTX 3090 Ti: 24GB total, ~20GB usable
- Tesla P40: 24GB total, ~20GB usable

**Recommendations:**
- Ollama (Gemma 2B): Uses ~4GB, 16GB free for other processes
- vLLM (LLaMA 7B): Uses ~14GB, 6GB free for cache

### Multi-GPU Orchestration

**Routing Logic in AIDecisionEngine:**

```php
// sections/api/include/Services/AIDecisionEngine.php

public function makeDecision(NPC $npc): array
{
    // 95% rule-based
    if (rand(1, 100) <= 95) {
        return $this->ruleBasedEngine->decide($npc);
    }
    
    // 5% LLM-based
    $complexity = $this->assessComplexity($npc);
    
    if ($complexity === 'low') {
        // Use Ollama (fast, simple)
        return $this->llmService->decideWithOllama($npc);
    } else {
        // Use vLLM (slow, complex)
        return $this->llmService->decideWithVLLM($npc);
    }
}
```

**Complexity Assessment:**
- **Low:** Single-village NPCs, routine decisions
- **High:** Multi-village NPCs, strategic warfare, diplomatic scenarios

### Fallback to CPU (Development)

For development without GPU:

```bash
# Edit docker-compose.yml
nano docker-compose.yml

# Comment out GPU device allocation
# ollama:
#   deploy:
#     resources:
#       reservations:
#         # devices:
#         #   - driver: nvidia
#         #     device_ids: ['0']

# Set environment variable
echo "USE_GPU=false" >> .env

# Restart services
docker compose restart ollama vllm
```

**Performance Impact:**
- GPU: 200ms average
- CPU: 2000-5000ms average (10-25x slower)

---

## Performance Tuning

### KV Cache Configuration

KV (Key-Value) cache stores attention states for faster subsequent tokens.

**Ollama KV Cache:**
```bash
# Default: 2048 tokens
# Increase for longer contexts (uses more VRAM)

docker exec travian_ollama ollama run gemma:2b --ctx-size 4096
```

**vLLM KV Cache:**

Edit `docker-compose.yml`:

```yaml
vllm:
  command: >
    vllm serve meta-llama/Llama-2-7b-hf
    --gpu-memory-utilization 0.9
    --max-model-len 4096        # Context window
    --kv-cache-dtype auto       # Auto optimize
```

Restart vLLM:
```bash
docker compose restart vllm
```

### Batch Size Tuning

**vLLM Batch Size:**

```yaml
# docker-compose.yml
vllm:
  command: >
    vllm serve meta-llama/Llama-2-7b-hf
    --max-num-seqs 16           # Process 16 requests in parallel
    --max-num-batched-tokens 4096
```

**Trade-offs:**
- Higher batch size: Better throughput, higher latency per request
- Lower batch size: Lower latency, lower throughput

**Recommendations:**
- Development: `--max-num-seqs 4`
- Production: `--max-num-seqs 16`

### Context Window Optimization

**Ollama:**
```bash
# Set context window size
docker exec travian_ollama ollama run gemma:2b --ctx-size 8192
```

**vLLM:**
```yaml
vllm:
  command: >
    vllm serve meta-llama/Llama-2-7b-hf
    --max-model-len 4096        # 4K tokens (default)
    # or
    --max-model-len 8192        # 8K tokens (uses more VRAM)
```

**Context Window Recommendations:**
- NPC decisions: 2048-4096 tokens sufficient
- Complex scenarios: 4096-8192 tokens

### Temperature and Top-p Settings

**Temperature:** Controls randomness (0.0 = deterministic, 1.0 = random)

**Top-p:** Nucleus sampling (0.9 = consider top 90% probable tokens)

**Ollama:**
```bash
curl http://localhost:11434/api/generate -d '{
  "model": "gemma:2b",
  "prompt": "NPC strategy:",
  "options": {
    "temperature": 0.7,
    "top_p": 0.9
  }
}'
```

**vLLM:**
```bash
curl http://localhost:8000/v1/completions -d '{
  "model": "meta-llama/Llama-2-7b-hf",
  "prompt": "NPC strategy:",
  "temperature": 0.8,
  "top_p": 0.95
}'
```

**Recommendations:**
- Strategic decisions: `temperature: 0.7-0.8` (balanced)
- Tactical decisions: `temperature: 0.5-0.6` (more deterministic)
- Creative scenarios: `temperature: 0.9-1.0` (more random)

### Performance Baselines

| Metric | Target | Critical Threshold |
|--------|--------|-------------------|
| Rule-based decision | <50ms | >100ms |
| Ollama inference | <200ms | >500ms |
| vLLM inference | <500ms | >1000ms |
| Overall avg (500 NPCs) | <200ms | >400ms |

**Validation:**
```bash
# Run performance test
./scripts/test-llm-performance.sh

# Expected output:
# ✅ Rule-based: 42ms average
# ✅ Ollama: 187ms average
# ✅ vLLM: 456ms average
# ✅ Overall: 152ms average
```

---

## Monitoring LLM Health

### Grafana "LLM Latency" Dashboard

**Dashboard URL:** `http://localhost:3000/d/llm-latency`

**Key Panels:**
1. **LLM Response Time (95th percentile)**
   - Ollama: <200ms
   - vLLM: <500ms

2. **LLM Request Rate**
   - Requests per second to each LLM

3. **GPU Utilization**
   - GPU 0 (Ollama): 40-60% normal
   - GPU 1 (vLLM): 50-70% normal

4. **LLM Error Rate**
   - Target: <0.1%
   - Alert: >1%

5. **Decision Strategy Distribution**
   - Rule-based: ~95%
   - LLM (Ollama): ~4%
   - LLM (vLLM): ~1%

### Prometheus Metrics

**Exposed Metrics:**

```
# LLM request duration histogram
llm_request_duration_seconds{llm="ollama"}
llm_request_duration_seconds{llm="vllm"}

# LLM request total counter
llm_requests_total{llm="ollama",status="success"}
llm_requests_total{llm="vllm",status="error"}

# GPU utilization gauge
gpu_utilization_percent{device="0"}  # Ollama
gpu_utilization_percent{device="1"}  # vLLM

# GPU memory usage gauge
gpu_memory_used_bytes{device="0"}
gpu_memory_used_bytes{device="1"}
```

**Query Examples:**

```promql
# Average LLM latency (last 5 minutes)
avg(rate(llm_request_duration_seconds_sum[5m]) / rate(llm_request_duration_seconds_count[5m]))

# LLM error rate
sum(rate(llm_requests_total{status="error"}[5m])) / sum(rate(llm_requests_total[5m]))

# GPU memory utilization
(gpu_memory_used_bytes / gpu_memory_total_bytes) * 100
```

### Log Analysis

```bash
# View Ollama logs
docker compose logs ollama --tail=100

# View vLLM logs
docker compose logs vllm --tail=100

# Search for errors
docker compose logs ollama | grep -i error
docker compose logs vllm | grep -i error

# Check inference latency in logs
docker compose logs ollama | grep "inference_time"

# Analyze slow requests (>1s)
docker compose logs vllm | grep "inference_time" | awk '$NF > 1000'
```

### Performance Indicators

**Healthy System:**
- ✅ GPU utilization: 40-80%
- ✅ GPU temperature: <85°C
- ✅ LLM response time: <500ms (95th percentile)
- ✅ Error rate: <0.1%
- ✅ Request rate: Consistent with NPC count

**Warning Signs:**
- ⚠️ GPU utilization: >90% sustained
- ⚠️ GPU temperature: >85°C
- ⚠️ LLM response time: >500ms
- ⚠️ Error rate: >0.5%

**Critical Issues:**
- 🚨 GPU utilization: 100% sustained
- 🚨 GPU temperature: >90°C
- 🚨 LLM response time: >1000ms
- 🚨 Error rate: >2%
- 🚨 GPU out of memory errors

---

## Troubleshooting

### Model Not Loading

**Symptoms:**
- vLLM container exits immediately
- Error: "model not found"
- Error: "cannot load model"

**Solutions:**

```bash
# Check model exists
docker exec travian_vllm ls -lh /root/.cache/huggingface/hub/

# Re-download model
docker exec travian_vllm python3 -c "
from huggingface_hub import snapshot_download
snapshot_download('meta-llama/Llama-2-7b-hf',
                  cache_dir='/root/.cache/huggingface',
                  force_download=True)
"

# Check vLLM logs for specific error
docker compose logs vllm --tail=200

# Restart vLLM
docker compose restart vllm
```

### GPU Out of Memory

**Symptoms:**
- "CUDA out of memory" errors
- Container crashes
- Inference fails intermittently

**Solutions:**

```bash
# Check GPU memory
nvidia-smi

# Restart containers to clear memory
docker compose restart ollama vllm

# Reduce batch size (vLLM)
# Edit docker-compose.yml
# --max-num-seqs 8  # Reduce from 16

# Reduce context window
# --max-model-len 2048  # Reduce from 4096

# Switch to smaller model
docker exec travian_ollama ollama pull gemma:2b  # Instead of 7b
```

### Slow Inference

**Symptoms:**
- LLM response time >1000ms
- Grafana showing high latency
- NPCs making slow decisions

**Solutions:**

```bash
# Check GPU utilization
nvidia-smi

# If GPU underutilized, increase batch size
# Edit docker-compose.yml (vLLM)
# --max-num-seqs 32  # Increase from 16

# If GPU maxed out, reduce concurrent requests
# Scale down AI workers
docker compose -f docker-compose.workers.yml up -d --scale ai-decision-worker=2

# Enable KV cache optimization
# vLLM already optimized by default

# Check for CPU throttling
docker stats travian_ollama travian_vllm
```

### Connection Errors

**Symptoms:**
- "Connection refused" to Ollama/vLLM
- API timeouts
- Workers failing to get LLM responses

**Solutions:**

```bash
# Check containers running
docker ps | grep -E "ollama|vllm"

# Restart containers
docker compose restart ollama vllm

# Check health endpoints
curl http://localhost:11434/api/health  # Ollama
curl http://localhost:8000/health        # vLLM

# Check logs for startup errors
docker compose logs ollama --tail=50
docker compose logs vllm --tail=50

# Verify network connectivity
docker exec travian_app ping -c 3 ollama
docker exec travian_app ping -c 3 vllm
```

**See Also:** [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for comprehensive troubleshooting guide

---

## Prompt Engineering

### Prompt Templates

**NPC Decision Prompt Template:**

```
Context:
- NPC Name: {npc_name}
- Tribe: {tribe}
- Villages: {village_count}
- Resources: Wood {wood}, Clay {clay}, Iron {iron}, Crop {crop}
- Army: {army_composition}
- Threats: {incoming_attacks}
- Nearby Players: {nearby_players}

Current Situation:
{situation_description}

Task: Decide the best action for this NPC. Choose from:
- BUILD: Construct or upgrade buildings
- TRAIN: Train troops
- ATTACK: Launch attack
- DEFEND: Prepare defense
- TRADE: Market actions
- EXPAND: Found new village

Respond with JSON:
{
  "action": "<action_type>",
  "target": "<specific_target>",
  "priority": "<high|medium|low>",
  "reasoning": "<brief_explanation>"
}
```

**Strategic Planning Prompt Template:**

```
NPC Profile:
- Difficulty: {difficulty}
- Personality: {personality}
- Long-term Goal: {goal}

Current State:
{state_summary}

Recent Events:
{recent_events}

Strategic Question:
{strategic_question}

Provide strategic recommendation considering NPC personality and difficulty level.
```

### Best Practices for NPC Decisions

1. **Keep prompts concise:** <2048 tokens for fast inference
2. **Structured output:** Request JSON for easy parsing
3. **Clear constraints:** Specify valid actions explicitly
4. **Context prioritization:** Most important info first
5. **Few-shot examples:** Include 1-2 examples in prompt (optional)

**Example with few-shot:**

```
Example 1:
Resources: Wood 1000, Clay 800, Iron 500, Crop 2000
Decision: {"action":"BUILD","target":"barracks","priority":"high"}

Example 2:
Resources: Wood 5000, Clay 4000, Iron 3000, Crop 3500
Army: 50 legionnaires, 20 praetorians
Threat: Incoming attack in 2 hours
Decision: {"action":"DEFEND","target":"reinforce_gates","priority":"high"}

Your turn:
Resources: Wood {wood}, Clay {clay}, Iron {iron}, Crop {crop}
Army: {army}
Decide:
```

### Context Window Management

**Token Estimation:**
- ~4 characters = 1 token
- Typical NPC context: 500-800 tokens
- Leave room for response: 200-500 tokens
- Total context window: 2048-4096 tokens

**Optimization Techniques:**

1. **Summarize repetitive data:**
```
# Instead of:
Village 1: Wood 1000, Clay 800, Iron 500, Crop 1200
Village 2: Wood 900, Clay 750, Iron 480, Crop 1100
Village 3: Wood 1100, Clay 850, Iron 520, Crop 1250

# Use:
Total Resources (3 villages): Wood 3000, Clay 2400, Iron 1500, Crop 3550
```

2. **Prioritize recent events:**
```
# Only include last 5 events
Recent Events:
- [2 hours ago] Completed barracks construction
- [4 hours ago] Sent reinforcements to village 2
- [6 hours ago] ...
```

3. **Compress numerical data:**
```
# Use ranges and summaries
Army Strength: Medium (500 troops total)
Resource Status: Sufficient for immediate needs
Threat Level: Low (no active attacks)
```

---

## Integration with AI System

### AIDecisionEngine

**Location:** `sections/api/include/Services/AIDecisionEngine.php`

**Responsibilities:**
- Orchestrate decision-making process
- Route to rule-based or LLM engine
- Log decisions and metrics
- Handle fallbacks

**Decision Flow:**

```php
public function makeDecision(NPC $npc): array
{
    $startTime = microtime(true);
    
    try {
        // Check if LLM decision needed (5% probability)
        if ($this->shouldUseLLM($npc)) {
            $decision = $this->llmService->makeDecision($npc);
            $decisionType = 'llm';
        } else {
            $decision = $this->ruleBasedEngine->makeDecision($npc);
            $decisionType = 'rule_based';
        }
        
        // Log decision
        $latency = (microtime(true) - $startTime) * 1000;
        $this->logDecision($npc, $decision, $decisionType, $latency);
        
        return $decision;
        
    } catch (Exception $e) {
        // Fallback to rule-based if LLM fails
        Log::error("LLM decision failed, falling back to rule-based", [
            'npc_id' => $npc->id,
            'error' => $e->getMessage()
        ]);
        
        return $this->ruleBasedEngine->makeDecision($npc);
    }
}
```

### LLMIntegrationService

**Location:** `sections/api/include/Services/LLMIntegrationService.php`

**Responsibilities:**
- Interface with Ollama and vLLM APIs
- Construct prompts from NPC context
- Parse LLM responses
- Handle timeouts and errors

**Example Integration:**

```php
class LLMIntegrationService
{
    private $ollamaBaseUrl = 'http://ollama:11434';
    private $vllmBaseUrl = 'http://vllm:8000';
    
    public function decideWithOllama(NPC $npc): array
    {
        $prompt = $this->buildPrompt($npc);
        
        $response = $this->httpClient->post($this->ollamaBaseUrl . '/api/generate', [
            'model' => 'gemma:2b',
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => 0.7,
                'top_p' => 0.9
            ]
        ]);
        
        return $this->parseResponse($response);
    }
    
    public function decideWithVLLM(NPC $npc): array
    {
        $prompt = $this->buildPrompt($npc);
        
        $response = $this->httpClient->post($this->vllmBaseUrl . '/v1/completions', [
            'model' => 'meta-llama/Llama-2-7b-hf',
            'prompt' => $prompt,
            'max_tokens' => 200,
            'temperature' => 0.8
        ]);
        
        return $this->parseResponse($response);
    }
}
```

### 95% Rule-Based + 5% LLM Strategy

**Implementation:**

```php
private function shouldUseLLM(NPC $npc): bool
{
    // Base probability: 5%
    $baseProbability = 0.05;
    
    // Increase probability for:
    // - Expert difficulty NPCs (+5%)
    // - Complex scenarios (+10%)
    // - Strategic decisions (+5%)
    
    $probability = $baseProbability;
    
    if ($npc->difficulty === 'expert') {
        $probability += 0.05;
    }
    
    if ($this->isComplexScenario($npc)) {
        $probability += 0.10;
    }
    
    if ($this->isStrategicDecision($npc)) {
        $probability += 0.05;
    }
    
    // Cap at 25% max
    $probability = min($probability, 0.25);
    
    return (mt_rand() / mt_getrandmax()) < $probability;
}
```

### When LLM is Invoked

**Scenarios triggering LLM:**

1. **Complex Multi-Village Management** (10% chance)
   - NPC has 3+ villages
   - Resource balancing needed
   - Coordinated attacks

2. **Strategic Warfare** (15% chance)
   - Multiple simultaneous attacks
   - Alliance coordination
   - Long-term campaign planning

3. **Diplomatic Scenarios** (20% chance)
   - NAP negotiations
   - Alliance decisions
   - Trade agreements

4. **Expert Difficulty NPCs** (10% base, +5% per scenario)
   - More realistic human-like behavior
   - Adaptive strategies

5. **Fallback for Edge Cases** (100% if rule-based fails)
   - Unknown scenarios
   - Rule engine unable to decide

---

## See Also

- [GPU-SETUP.md](GPU-SETUP.md) - Initial GPU configuration and drivers
- [MONITORING.md](MONITORING.md) - Monitoring stack setup and dashboards
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Comprehensive troubleshooting guide
- [SYSTEM_DOCUMENTATION.md](SYSTEM_DOCUMENTATION.md) - AI-NPC system architecture
- [OPERATIONS-RUNBOOK.md](OPERATIONS-RUNBOOK.md) - Day-to-day operations
- [ARCHITECTURE.md](ARCHITECTURE.md) - Overall system architecture

---

## Additional Resources

- **Ollama Documentation:** https://ollama.ai/docs
- **vLLM Documentation:** https://docs.vllm.ai/
- **Hugging Face Model Hub:** https://huggingface.co/models
- **NVIDIA GPU Monitoring:** https://developer.nvidia.com/nvidia-system-management-interface
- **Prometheus Query Examples:** https://prometheus.io/docs/prometheus/latest/querying/examples/
