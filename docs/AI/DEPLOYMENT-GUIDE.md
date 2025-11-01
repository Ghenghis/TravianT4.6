# AI-NPC Deployment Guide

Complete deployment guide for TravianT4.6 AI-NPC system on Windows 11/WSL2/Docker with GPU acceleration (RTX 3090 Ti + Tesla P40s).

## Table of Contents

1. [Windows 11/WSL2 Setup](#windows-11wsl2-setup)
2. [GPU Passthrough Configuration](#gpu-passthrough-configuration)
3. [LLM Backend Options](#llm-backend-options)
4. [Docker Compose Configuration](#docker-compose-configuration)
5. [Background Workers Setup](#background-workers-setup)
6. [Performance Tuning](#performance-tuning)
7. [Monitoring & Troubleshooting](#monitoring--troubleshooting)
8. [Environment Variables Reference](#environment-variables-reference)

---

## Windows 11/WSL2 Setup

### Prerequisites

**System Requirements:**
- Windows 11 Pro or Enterprise (required for GPU passthrough)
- CPU: 8+ cores recommended
- RAM: 32GB minimum, 64GB recommended for 500 NPCs
- Storage: 200GB+ SSD
- GPU: NVIDIA RTX 3090 Ti (24GB VRAM) + Tesla P40 (24GB VRAM)

**Software Requirements:**
- Windows 11 build 21H2 or later
- WSL2 with Ubuntu 22.04 LTS
- Docker Desktop 4.12+ with WSL2 backend
- NVIDIA GPU Driver 510.39.01+ (Windows)
- NVIDIA Container Toolkit

### Step 1: Install WSL2

Open PowerShell as Administrator:

```powershell
# Enable WSL and Virtual Machine Platform
dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart

# Restart computer
Restart-Computer
```

After restart:

```powershell
# Set WSL2 as default
wsl --set-default-version 2

# Install Ubuntu 22.04
wsl --install -d Ubuntu-22.04

# Update WSL kernel
wsl --update
```

### Step 2: Install NVIDIA Drivers

1. Download latest NVIDIA Game Ready or Studio Driver from: https://www.nvidia.com/Download/index.aspx
2. Install driver with default settings
3. Verify installation:

```powershell
nvidia-smi
```

Expected output showing both GPUs:
```
+-----------------------------------------------------------------------------+
| NVIDIA-SMI 535.xx       Driver Version: 535.xx       CUDA Version: 12.2     |
|-------------------------------+----------------------+----------------------+
| GPU  Name            TCC/WDDM | Bus-Id        Disp.A | Volatile Uncorr. ECC |
| Fan  Temp  Perf  Pwr:Usage/Cap|         Memory-Usage | GPU-Util  Compute M. |
|===============================+======================+======================|
|   0  NVIDIA GeForce ... WDDM  | 00000000:01:00.0  On |                  N/A |
|  0%   45C    P8    25W / 450W |   1024MiB / 24576MiB |      2%      Default |
+-------------------------------+----------------------+----------------------+
|   1  Tesla P40          WDDM  | 00000000:02:00.0 Off |                    0 |
|  0%   32C    P8    15W / 250W |      0MiB / 24576MiB |      0%      Default |
+-------------------------------+----------------------+----------------------+
```

### Step 3: Install Docker Desktop

1. Download Docker Desktop from: https://www.docker.com/products/docker-desktop/
2. Install with WSL2 backend enabled
3. Configure Docker Desktop:
   - Settings → General → Use WSL 2 based engine ✓
   - Settings → Resources → WSL Integration → Enable Ubuntu-22.04 ✓
   - Settings → Resources → Advanced:
     - CPUs: 8
     - Memory: 24GB
     - Swap: 4GB

### Step 4: Verify WSL GPU Access

Open Ubuntu terminal in WSL2:

```bash
# Check WSL GPU access
nvidia-smi

# Should show both RTX 3090 Ti and Tesla P40
# If not visible, NVIDIA drivers need reinstallation
```

---

## GPU Passthrough Configuration

### NVIDIA Container Toolkit Setup

In Ubuntu WSL2 terminal:

```bash
# Add NVIDIA package repository
distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
curl -s -L https://nvidia.github.io/nvidia-docker/gpgkey | sudo apt-key add -
curl -s -L https://nvidia.github.io/nvidia-docker/$distribution/nvidia-docker.list | \
    sudo tee /etc/apt/sources.list.d/nvidia-docker.list

# Update and install
sudo apt-get update
sudo apt-get install -y nvidia-docker2

# Restart Docker daemon
sudo systemctl restart docker
```

### Test GPU Access in Docker

```bash
# Test CUDA in container
docker run --rm --gpus all nvidia/cuda:12.2.0-base-ubuntu22.04 nvidia-smi

# Should display both GPUs
```

### Configure GPU Device Selection

Create `.env` file for GPU selection:

```bash
# Use RTX 3090 Ti (GPU 0) for primary LLM inference
LLM_CUDA_DEVICE=0

# Or use Tesla P40 (GPU 1)
# LLM_CUDA_DEVICE=1

# Or use both GPUs with vLLM
# LLM_CUDA_DEVICE=0,1
```

---

## LLM Backend Options

### Performance Comparison Table

| Backend | Pros | Cons | Best For | Multi-GPU | Avg Latency |
|---------|------|------|----------|-----------|-------------|
| **Ollama** | Easy setup, good defaults | Single GPU only | Development, <100 NPCs | ❌ | 150-250ms |
| **LM Studio** | GUI, user-friendly | Windows only, single GPU | Testing, <50 NPCs | ❌ | 200-300ms |
| **vLLM** | Best performance, multi-GPU | Complex setup | Production, 200-500 NPCs | ✅ | 80-150ms |
| **Text Gen WebUI** | Feature-rich, flexible | Moderate complexity | 100-200 NPCs | ✅ | 100-200ms |

### Option 1: Ollama (Recommended for Development)

**Pros:** Easiest setup, good performance for single GPU
**Best For:** Development, testing, <100 concurrent NPCs

#### Installation (WSL2)

```bash
# Install Ollama
curl https://ollama.ai/install.sh | sh

# Pull recommended model
ollama pull llama2:7b

# Or use larger model for better quality
ollama pull llama2:13b

# Start Ollama service
ollama serve
```

#### Environment Configuration

```bash
# .env file
LLM_BACKEND=ollama
LLM_ENDPOINT=http://localhost:11434/api/generate
LLM_MODEL=llama2:7b
LLM_MAX_TOKENS=200
LLM_TEMPERATURE=0.7
LLM_TIMEOUT=5
LLM_CUDA_DEVICE=0
LLM_ENABLE_CACHE=true
```

#### Docker Compose Integration

```yaml
services:
  ollama:
    image: ollama/ollama:latest
    container_name: travian_ollama
    ports:
      - "11434:11434"
    volumes:
      - ollama_models:/root/.ollama
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              device_ids: ['0']
              capabilities: [gpu]
    networks:
      - travian_network
    restart: unless-stopped

volumes:
  ollama_models:
```

### Option 2: vLLM (Recommended for Production)

**Pros:** Best performance, multi-GPU support, efficient batching
**Best For:** Production, 200-500 concurrent NPCs

#### Installation (WSL2)

```bash
# Create Python virtual environment
python3 -m venv vllm-env
source vllm-env/bin/activate

# Install vLLM
pip install vllm

# Download model (Hugging Face)
# Requires HF token for gated models
huggingface-cli login

# Start vLLM server with multi-GPU
python -m vllm.entrypoints.openai.api_server \
    --model meta-llama/Llama-2-7b-chat-hf \
    --tensor-parallel-size 2 \
    --gpu-memory-utilization 0.9 \
    --max-num-seqs 128 \
    --port 8000
```

#### Environment Configuration

```bash
# .env file
LLM_BACKEND=vllm
LLM_ENDPOINT=http://localhost:8000/v1/completions
LLM_MODEL=meta-llama/Llama-2-7b-chat-hf
LLM_MAX_TOKENS=200
LLM_TEMPERATURE=0.7
LLM_TIMEOUT=10
LLM_CUDA_DEVICE=0,1
LLM_GPU_MEMORY_LIMIT=20GB
LLM_ENABLE_CACHE=true
```

#### Docker Compose Integration

```yaml
services:
  vllm:
    image: vllm/vllm-openai:latest
    container_name: travian_vllm
    ports:
      - "8000:8000"
    volumes:
      - vllm_models:/root/.cache/huggingface
    environment:
      - HUGGING_FACE_HUB_TOKEN=${HF_TOKEN}
    command: >
      --model meta-llama/Llama-2-7b-chat-hf
      --tensor-parallel-size 2
      --gpu-memory-utilization 0.9
      --max-num-seqs 128
      --port 8000
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              device_ids: ['0', '1']
              capabilities: [gpu]
    networks:
      - travian_network
    restart: unless-stopped

volumes:
  vllm_models:
```

### Option 3: Text Generation WebUI

**Pros:** Feature-rich, good performance, web interface
**Best For:** 100-200 concurrent NPCs, experimentation

#### Installation (WSL2)

```bash
# Clone repository
git clone https://github.com/oobabooga/text-generation-webui
cd text-generation-webui

# Install dependencies
pip install -r requirements.txt

# Download models via web UI or CLI
python download-model.py meta-llama/Llama-2-7b-chat-hf

# Start server
python server.py --api --listen --gpu-memory 0.9 0.9
```

#### Environment Configuration

```bash
# .env file
LLM_BACKEND=text_generation_webui
LLM_ENDPOINT=http://localhost:5000/api/v1/generate
LLM_MODEL=llama-2-7b-chat
LLM_MAX_TOKENS=200
LLM_TEMPERATURE=0.7
LLM_TIMEOUT=8
LLM_CUDA_DEVICE=0,1
LLM_ENABLE_CACHE=true
```

### Option 4: LM Studio (Windows Development Only)

**Pros:** Easy GUI, no coding required
**Best For:** Local Windows testing, <50 NPCs

#### Installation (Windows)

1. Download from: https://lmstudio.ai/
2. Install and launch LM Studio
3. Download Llama 2 7B Chat model via GUI
4. Start local server (Settings → Local Server → Start)

#### Environment Configuration

```bash
# .env file (in WSL2, pointing to Windows LM Studio)
LLM_BACKEND=lm_studio
LLM_ENDPOINT=http://host.docker.internal:1234/v1/completions
LLM_MODEL=llama-2-7b-chat
LLM_MAX_TOKENS=200
LLM_TEMPERATURE=0.7
LLM_TIMEOUT=10
```

### Model Recommendations by NPC Scale

| NPC Count | Recommended Model | VRAM Usage | Latency Target | Backend |
|-----------|-------------------|------------|----------------|---------|
| 1-50 | Llama 2 7B | 8GB | <300ms | Ollama/LM Studio |
| 50-100 | Llama 2 7B | 8GB | <200ms | Ollama/vLLM |
| 100-200 | Llama 2 7B + batching | 12GB | <150ms | vLLM/Text Gen WebUI |
| 200-500 | Llama 2 13B + multi-GPU | 24GB+ | <150ms | vLLM (tensor parallel) |

---

## Docker Compose Configuration

### Complete AI-NPC System Configuration

Create `docker-compose-ai.yml`:

```yaml
version: '3.8'

services:
  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    container_name: travian_nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html:ro
      - nginx_logs:/var/log/nginx
    depends_on:
      - php
    networks:
      - travian_network
    restart: unless-stopped

  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: travian_php
    volumes:
      - ./:/var/www/html
      - php_logs:/var/log/php
    environment:
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - LLM_BACKEND=${LLM_BACKEND}
      - LLM_ENDPOINT=${LLM_ENDPOINT}
      - LLM_MODEL=${LLM_MODEL}
      - LLM_CUDA_DEVICE=${LLM_CUDA_DEVICE}
      - LLM_ENABLE_CACHE=${LLM_ENABLE_CACHE}
    depends_on:
      - postgres
      - redis
      - vllm
    networks:
      - travian_network
    restart: unless-stopped

  postgres:
    image: postgres:15-alpine
    container_name: travian_postgres
    ports:
      - "5432:5432"
    environment:
      - POSTGRES_DB=${DB_DATABASE}
      - POSTGRES_USER=${DB_USERNAME}
      - POSTGRES_PASSWORD=${DB_PASSWORD}
      - POSTGRES_INITDB_ARGS=--encoding=UTF8 --locale=en_US.UTF-8
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./database/schemas:/docker-entrypoint-initdb.d:ro
    command: >
      postgres
      -c max_connections=500
      -c shared_buffers=2GB
      -c effective_cache_size=6GB
      -c maintenance_work_mem=512MB
      -c checkpoint_completion_target=0.9
      -c wal_buffers=16MB
      -c default_statistics_target=100
      -c random_page_cost=1.1
      -c effective_io_concurrency=200
      -c work_mem=10MB
      -c min_wal_size=1GB
      -c max_wal_size=4GB
    networks:
      - travian_network
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME}"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    container_name: travian_redis
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
      - ./docker/redis/redis.conf:/usr/local/etc/redis/redis.conf:ro
    command: redis-server /usr/local/etc/redis/redis.conf
    networks:
      - travian_network
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 5

  vllm:
    image: vllm/vllm-openai:latest
    container_name: travian_vllm
    ports:
      - "8000:8000"
    volumes:
      - vllm_models:/root/.cache/huggingface
    environment:
      - HUGGING_FACE_HUB_TOKEN=${HF_TOKEN}
      - CUDA_VISIBLE_DEVICES=0,1
    command: >
      --model ${LLM_MODEL:-meta-llama/Llama-2-7b-chat-hf}
      --tensor-parallel-size 2
      --gpu-memory-utilization 0.85
      --max-num-seqs 128
      --max-model-len 2048
      --port 8000
      --host 0.0.0.0
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              device_ids: ['0', '1']
              capabilities: [gpu]
    networks:
      - travian_network
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  automation_worker:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: travian_automation_worker
    volumes:
      - ./:/var/www/html
      - worker_logs:/var/log/workers
    environment:
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - WORKER_TYPE=automation
    command: >
      sh -c "while true; do
        php /var/www/html/cli/workers/automation-worker.php >> /var/log/workers/automation.log 2>&1;
        sleep 300;
      done"
    depends_on:
      - postgres
      - redis
    networks:
      - travian_network
    restart: unless-stopped

  ai_decision_worker:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: travian_ai_decision_worker
    volumes:
      - ./:/var/www/html
      - worker_logs:/var/log/workers
    environment:
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - LLM_BACKEND=${LLM_BACKEND}
      - LLM_ENDPOINT=${LLM_ENDPOINT}
      - LLM_MODEL=${LLM_MODEL}
      - WORKER_TYPE=ai_decision
    command: >
      sh -c "while true; do
        php /var/www/html/cli/workers/ai-decision-worker.php >> /var/log/workers/ai-decision.log 2>&1;
        sleep 300;
      done"
    depends_on:
      - postgres
      - redis
      - vllm
    networks:
      - travian_network
    restart: unless-stopped

  spawn_scheduler_worker:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: travian_spawn_scheduler_worker
    volumes:
      - ./:/var/www/html
      - worker_logs:/var/log/workers
    environment:
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - WORKER_TYPE=spawn_scheduler
    command: >
      sh -c "while true; do
        php /var/www/html/cli/workers/spawn-scheduler-worker.php >> /var/log/workers/spawn-scheduler.log 2>&1;
        sleep 900;
      done"
    depends_on:
      - postgres
      - redis
    networks:
      - travian_network
    restart: unless-stopped

networks:
  travian_network:
    driver: bridge

volumes:
  nginx_logs:
  php_logs:
  postgres_data:
  redis_data:
  vllm_models:
  worker_logs:
```

### Redis Configuration

Create `docker/redis/redis.conf`:

```conf
maxmemory 4gb
maxmemory-policy allkeys-lru

save 900 1
save 300 10
save 60 10000

appendonly yes
appendfsync everysec

tcp-keepalive 300
timeout 0

databases 16

loglevel notice
logfile ""

slowlog-log-slower-than 10000
slowlog-max-len 128
```

### Environment Variables File

Create `.env`:

```bash
# Database Configuration
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=travian_t46
DB_USERNAME=travian
DB_PASSWORD=secure_password_here

# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379

# LLM Configuration - vLLM (Production)
LLM_BACKEND=vllm
LLM_ENDPOINT=http://vllm:8000/v1/completions
LLM_MODEL=meta-llama/Llama-2-7b-chat-hf
LLM_MAX_TOKENS=200
LLM_TEMPERATURE=0.7
LLM_TIMEOUT=10
LLM_RETRY_ATTEMPTS=3
LLM_RETRY_DELAY=1000

# GPU Configuration
LLM_CUDA_DEVICE=0,1
LLM_GPU_TYPE=auto
LLM_GPU_MEMORY_LIMIT=20GB
LLM_FALLBACK_CPU=true

# Performance Configuration
LLM_ENABLE_CACHE=true
LLM_ENABLE_METRICS=true
LLM_POOL_SIZE=10

# Hugging Face Token (for model downloads)
HF_TOKEN=your_huggingface_token_here
```

### Start the System

```bash
# Start all services
docker-compose -f docker-compose-ai.yml up -d

# Check service status
docker-compose -f docker-compose-ai.yml ps

# View logs
docker-compose -f docker-compose-ai.yml logs -f vllm
docker-compose -f docker-compose-ai.yml logs -f ai_decision_worker

# Stop all services
docker-compose -f docker-compose-ai.yml down
```

---

## Background Workers Setup

### Overview

Three background workers handle AI-NPC automation:

1. **automation-worker.php** - Executes farming, building, training, defense automation
2. **ai-decision-worker.php** - Makes LLM-enhanced decisions for NPCs
3. **spawn-scheduler-worker.php** - Spawns progressive NPC batches (Day 1, 3, 7)

### Worker Execution Frequencies

| Worker | Frequency | Purpose | Resource Usage |
|--------|-----------|---------|----------------|
| automation-worker | Every 5 minutes | Execute automation actions | Low CPU, Moderate DB |
| ai-decision-worker | Every 5 minutes | LLM decision-making | High GPU/CPU, Moderate DB |
| spawn-scheduler-worker | Every 15 minutes | Check spawn schedules | Low CPU, Low DB |

### Option 1: Docker Containers (Recommended)

Already configured in `docker-compose-ai.yml`. Workers run as separate containers with automatic restart.

**Advantages:**
- Auto-restart on failure
- Isolated logs
- Easy scaling
- Consistent environment

**Monitoring:**

```bash
# Check worker status
docker ps | grep worker

# View automation worker logs
docker logs -f travian_automation_worker --tail 100

# View AI decision worker logs
docker logs -f travian_ai_decision_worker --tail 100

# View spawn scheduler logs
docker logs -f travian_spawn_scheduler_worker --tail 100

# Restart specific worker
docker restart travian_ai_decision_worker
```

### Option 2: WSL2 Cron Jobs

For development or custom setups.

#### Setup Cron

```bash
# Edit crontab
crontab -e

# Add worker jobs
*/5 * * * * /usr/bin/php /path/to/travian/cli/workers/automation-worker.php >> /var/log/travian/automation.log 2>&1
*/5 * * * * /usr/bin/php /path/to/travian/cli/workers/ai-decision-worker.php >> /var/log/travian/ai-decision.log 2>&1
*/15 * * * * /usr/bin/php /path/to/travian/cli/workers/spawn-scheduler-worker.php >> /var/log/travian/spawn-scheduler.log 2>&1

# Verify cron jobs
crontab -l

# Create log directory
sudo mkdir -p /var/log/travian
sudo chown $USER:$USER /var/log/travian
```

#### Manual Execution (Testing)

```bash
# Run automation worker once
php cli/workers/automation-worker.php

# Run with verbose output
php cli/workers/ai-decision-worker.php --verbose

# Run spawn scheduler
php cli/workers/spawn-scheduler-worker.php
```

### Option 3: Windows Task Scheduler

For Windows-native PHP installations.

#### Create Scheduled Tasks

**1. Automation Worker Task**

```powershell
# Open Task Scheduler
$action = New-ScheduledTaskAction -Execute "php.exe" -Argument "C:\Projects\TravianT4.6\cli\workers\automation-worker.php"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 5)
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERNAME" -LogonType ServiceAccount
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask -TaskName "Travian Automation Worker" -Action $action -Trigger $trigger -Principal $principal -Settings $settings
```

**2. AI Decision Worker Task**

```powershell
$action = New-ScheduledTaskAction -Execute "php.exe" -Argument "C:\Projects\TravianT4.6\cli\workers\ai-decision-worker.php"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 5)
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERNAME" -LogonType ServiceAccount
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask -TaskName "Travian AI Decision Worker" -Action $action -Trigger $trigger -Principal $principal -Settings $settings
```

**3. Spawn Scheduler Task**

```powershell
$action = New-ScheduledTaskAction -Execute "php.exe" -Argument "C:\Projects\TravianT4.6\cli\workers\spawn-scheduler-worker.php"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 15)
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERNAME" -LogonType ServiceAccount
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask -TaskName "Travian Spawn Scheduler" -Action $action -Trigger $trigger -Principal $principal -Settings $settings
```

### Option 4: Systemd Services (Linux Production)

For dedicated Linux servers.

#### Create Service Files

**automation-worker.service:**

```ini
[Unit]
Description=Travian Automation Worker
After=network.target postgresql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/travian
ExecStart=/usr/bin/php /var/www/travian/cli/workers/automation-worker.php
Restart=always
RestartSec=300
StandardOutput=append:/var/log/travian/automation-worker.log
StandardError=append:/var/log/travian/automation-worker-error.log

[Install]
WantedBy=multi-user.target
```

**ai-decision-worker.service:**

```ini
[Unit]
Description=Travian AI Decision Worker
After=network.target postgresql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/travian
ExecStart=/usr/bin/php /var/www/travian/cli/workers/ai-decision-worker.php
Restart=always
RestartSec=300
StandardOutput=append:/var/log/travian/ai-decision-worker.log
StandardError=append:/var/log/travian/ai-decision-worker-error.log

[Install]
WantedBy=multi-user.target
```

**spawn-scheduler-worker.service:**

```ini
[Unit]
Description=Travian Spawn Scheduler Worker
After=network.target postgresql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/travian
ExecStart=/usr/bin/php /var/www/travian/cli/workers/spawn-scheduler-worker.php
Restart=always
RestartSec=900
StandardOutput=append:/var/log/travian/spawn-scheduler-worker.log
StandardError=append:/var/log/travian/spawn-scheduler-worker-error.log

[Install]
WantedBy=multi-user.target
```

#### Enable and Start Services

```bash
# Copy service files
sudo cp *.service /etc/systemd/system/

# Reload systemd
sudo systemctl daemon-reload

# Enable services (start on boot)
sudo systemctl enable automation-worker.service
sudo systemctl enable ai-decision-worker.service
sudo systemctl enable spawn-scheduler-worker.service

# Start services
sudo systemctl start automation-worker.service
sudo systemctl start ai-decision-worker.service
sudo systemctl start spawn-scheduler-worker.service

# Check status
sudo systemctl status automation-worker.service
sudo systemctl status ai-decision-worker.service
sudo systemctl status spawn-scheduler-worker.service

# View logs
sudo journalctl -u automation-worker.service -f
sudo journalctl -u ai-decision-worker.service -f
```

---

## Performance Tuning

### Optimal Configuration by Scale

#### 50 NPCs (Small Scale)

**Hardware:**
- Single GPU (RTX 3090 Ti or Tesla P40)
- 16GB RAM
- 4 CPU cores

**LLM Configuration:**

```bash
LLM_BACKEND=ollama
LLM_MODEL=llama2:7b
LLM_MAX_TOKENS=150
LLM_TEMPERATURE=0.7
LLM_TIMEOUT=5
LLM_CUDA_DEVICE=0
LLM_GPU_MEMORY_LIMIT=8GB
LLM_ENABLE_CACHE=true
```

**Expected Performance:**
- Response time: 150-250ms
- GPU utilization: 30-50%
- Memory usage: 8GB VRAM

#### 100 NPCs (Medium Scale)

**Hardware:**
- Single GPU (RTX 3090 Ti recommended)
- 24GB RAM
- 6 CPU cores

**LLM Configuration:**

```bash
LLM_BACKEND=vllm
LLM_MODEL=meta-llama/Llama-2-7b-chat-hf
LLM_MAX_TOKENS=200
LLM_TEMPERATURE=0.7
LLM_TIMEOUT=8
LLM_CUDA_DEVICE=0
LLM_GPU_MEMORY_LIMIT=18GB
LLM_ENABLE_CACHE=true
LLM_POOL_SIZE=10
```

**vLLM Start Command:**

```bash
python -m vllm.entrypoints.openai.api_server \
    --model meta-llama/Llama-2-7b-chat-hf \
    --gpu-memory-utilization 0.85 \
    --max-num-seqs 64 \
    --max-model-len 2048 \
    --port 8000
```

**Expected Performance:**
- Response time: 100-180ms
- GPU utilization: 60-75%
- Memory usage: 15GB VRAM

#### 250 NPCs (Large Scale)

**Hardware:**
- Dual GPU (RTX 3090 Ti + Tesla P40)
- 48GB RAM
- 8 CPU cores

**LLM Configuration:**

```bash
LLM_BACKEND=vllm
LLM_MODEL=meta-llama/Llama-2-7b-chat-hf
LLM_MAX_TOKENS=200
LLM_TEMPERATURE=0.7
LLM_TIMEOUT=10
LLM_CUDA_DEVICE=0,1
LLM_GPU_MEMORY_LIMIT=38GB
LLM_ENABLE_CACHE=true
LLM_POOL_SIZE=20
```

**vLLM Start Command:**

```bash
python -m vllm.entrypoints.openai.api_server \
    --model meta-llama/Llama-2-7b-chat-hf \
    --tensor-parallel-size 2 \
    --gpu-memory-utilization 0.85 \
    --max-num-seqs 128 \
    --max-model-len 2048 \
    --port 8000
```

**Expected Performance:**
- Response time: 80-150ms
- GPU utilization: 70-85% (both GPUs)
- Memory usage: 30GB VRAM total

#### 500 NPCs (Maximum Scale)

**Hardware:**
- Dual GPU (RTX 3090 Ti + Tesla P40)
- 64GB RAM
- 12+ CPU cores

**LLM Configuration:**

```bash
LLM_BACKEND=vllm
LLM_MODEL=meta-llama/Llama-2-13b-chat-hf
LLM_MAX_TOKENS=200
LLM_TEMPERATURE=0.7
LLM_TIMEOUT=12
LLM_CUDA_DEVICE=0,1
LLM_GPU_MEMORY_LIMIT=44GB
LLM_ENABLE_CACHE=true
LLM_POOL_SIZE=30
```

**vLLM Start Command:**

```bash
python -m vllm.entrypoints.openai.api_server \
    --model meta-llama/Llama-2-13b-chat-hf \
    --tensor-parallel-size 2 \
    --gpu-memory-utilization 0.90 \
    --max-num-seqs 256 \
    --max-model-len 2048 \
    --port 8000 \
    --disable-log-requests
```

**Expected Performance:**
- Response time: 100-180ms
- GPU utilization: 85-95% (both GPUs)
- Memory usage: 40GB VRAM total

### Database Tuning for AI-NPCs

PostgreSQL configuration for high NPC load:

```sql
-- postgresql.conf adjustments

-- Connection Management
max_connections = 500

-- Memory Settings
shared_buffers = 4GB
effective_cache_size = 12GB
maintenance_work_mem = 1GB
work_mem = 20MB

-- Checkpoint Settings
checkpoint_completion_target = 0.9
wal_buffers = 32MB
min_wal_size = 2GB
max_wal_size = 8GB

-- Query Planner
random_page_cost = 1.1
effective_io_concurrency = 200
```

### Redis Tuning

```conf
# redis.conf for AI-NPC caching

maxmemory 8gb
maxmemory-policy allkeys-lru

# Persistence (optional - can disable for pure cache)
save 900 1
save 300 10
save 60 10000

appendonly yes
appendfsync everysec

# Performance
tcp-backlog 511
timeout 300
tcp-keepalive 300

# Slow log
slowlog-log-slower-than 10000
slowlog-max-len 128
```

### Response Time Optimization

**Target: <200ms average response time**

1. **Enable Response Caching:**
   - Cache identical prompts for 1 hour
   - Expected cache hit rate: 30-50%
   - Reduces load by 40%

2. **Batch Processing:**
   - Group NPC decisions in batches of 10-20
   - Process batch every 5 minutes
   - Reduces GPU context switches

3. **Rule-Based Fallback:**
   - Use LLM for only 5-20% of decisions
   - Rule-based decisions: <50ms
   - Weighted average: <150ms

4. **Circuit Breaker:**
   - Automatically disable LLM if >5 consecutive failures
   - Fallback to rules for 60 seconds
   - Prevents cascade failures

### Memory Management for 500 NPCs

**Memory Breakdown:**

| Component | Memory Usage | Notes |
|-----------|--------------|-------|
| PHP Workers | 500MB per worker | 3 workers = 1.5GB |
| PostgreSQL | 8GB | Shared buffers + connections |
| Redis | 8GB | Feature flags + session cache |
| vLLM (dual GPU) | 40GB VRAM | Model weights + KV cache |
| System | 8GB | OS + Docker overhead |
| **Total** | **~66GB RAM + 40GB VRAM** | |

**Optimization Tips:**

1. **Reduce LLM Usage:**
   ```bash
   # Use LLM for only expert NPCs (20% of decisions)
   # Medium/Easy NPCs use rules only
   ```

2. **Aggressive Response Caching:**
   ```bash
   LLM_ENABLE_CACHE=true
   # Expected cache hit: 40-60% for 500 NPCs
   ```

3. **Optimize Model Size:**
   ```bash
   # Use quantized models (INT8/INT4)
   # 13B model → 7B model reduces VRAM by 50%
   ```

4. **Database Connection Pooling:**
   ```php
   // Limit connections per worker
   max_connections_per_worker = 10
   ```

---

## Monitoring & Troubleshooting

### Log File Locations

#### Docker Containers

```bash
# Worker logs
docker logs travian_automation_worker
docker logs travian_ai_decision_worker
docker logs travian_spawn_scheduler_worker

# vLLM logs
docker logs travian_vllm

# PHP application logs
docker logs travian_php

# Nginx logs
docker logs travian_nginx
```

#### Persistent Logs (Volumes)

```bash
# Access worker logs on host
docker exec travian_automation_worker cat /var/log/workers/automation.log
docker exec travian_ai_decision_worker cat /var/log/workers/ai-decision.log

# Or mount volumes and check directly
ls -lah /var/lib/docker/volumes/travian_worker_logs/_data/
```

### GPU Utilization Monitoring

#### nvidia-smi Dashboard

```bash
# Watch GPU usage in real-time
watch -n 1 nvidia-smi

# Or continuous log
nvidia-smi dmon -s pucvmet -c 100 > gpu_metrics.log

# Check specific GPU
nvidia-smi -i 0  # RTX 3090 Ti
nvidia-smi -i 1  # Tesla P40
```

#### Expected GPU Utilization

| NPC Count | GPU 0 (RTX 3090 Ti) | GPU 1 (Tesla P40) | Total VRAM |
|-----------|---------------------|-------------------|------------|
| 50 | 40-60% | 0% (unused) | 8-10GB |
| 100 | 60-80% | 0% (unused) | 12-16GB |
| 250 | 75-85% | 75-85% | 25-35GB |
| 500 | 85-95% | 85-95% | 38-45GB |

### Performance Metrics API

#### LLM Service Metrics

```bash
# Get LLM integration metrics
curl http://localhost/v1/admin/llm/metrics | jq

# Example response:
{
  "total_requests": 1450,
  "successful_requests": 1398,
  "failed_requests": 52,
  "cache_hits": 620,
  "success_rate": 96.41,
  "cache_hit_rate": 42.76,
  "avg_response_time": 142.5,
  "circuit_breaker_state": "closed",
  "circuit_breaker_failures": 0
}
```

#### GPU Status API

```bash
# Get GPU status
curl http://localhost/v1/admin/llm/gpu-status | jq

# Example response:
{
  "available": true,
  "cuda_device": "0,1",
  "devices": [
    {
      "index": "0",
      "name": "NVIDIA GeForce RTX 3090 Ti",
      "memory_used_mb": 18432,
      "memory_total_mb": 24576,
      "utilization_percent": 82,
      "temperature_celsius": 68
    },
    {
      "index": "1",
      "name": "Tesla P40",
      "memory_used_mb": 16384,
      "memory_total_mb": 24576,
      "utilization_percent": 78,
      "temperature_celsius": 62
    }
  ]
}
```

### Common Issues and Solutions

#### Issue 1: LLM Service Not Available

**Symptoms:**
- All NPCs using fallback decisions
- Circuit breaker OPEN
- Error: "Connection refused"

**Solutions:**

```bash
# Check if vLLM is running
docker ps | grep vllm

# Check vLLM logs
docker logs travian_vllm --tail 100

# Restart vLLM service
docker restart travian_vllm

# Verify endpoint
curl http://localhost:8000/health

# Check firewall/network
docker network inspect travian_network
```

#### Issue 2: High GPU Memory Usage

**Symptoms:**
- CUDA out of memory errors
- vLLM crashes
- Slow response times

**Solutions:**

```bash
# Check GPU memory
nvidia-smi

# Reduce GPU memory utilization
# Edit docker-compose-ai.yml:
# --gpu-memory-utilization 0.75  (from 0.90)

# Or reduce batch size
# --max-num-seqs 64  (from 128)

# Restart vLLM
docker restart travian_vllm

# Use smaller model
# LLM_MODEL=meta-llama/Llama-2-7b-chat-hf  (instead of 13b)
```

#### Issue 3: Slow Response Times (>300ms)

**Symptoms:**
- avg_response_time > 300ms
- NPCs taking too long to decide
- Workers timing out

**Diagnosis:**

```bash
# Check LLM metrics
curl http://localhost/v1/admin/llm/metrics

# Check GPU utilization
nvidia-smi

# Check vLLM queue depth
docker logs travian_vllm | grep "queue"
```

**Solutions:**

1. **Increase cache hit rate:**
   ```bash
   LLM_ENABLE_CACHE=true
   # Target: 40-60% cache hit rate
   ```

2. **Reduce LLM usage percentage:**
   ```php
   // In AIDecisionEngine, reduce LLM probability
   // Expert: 20% → 10%
   // Hard: 10% → 5%
   // Medium/Easy: rules only
   ```

3. **Optimize batch processing:**
   ```bash
   # Increase batch size in vLLM
   --max-num-seqs 256  (from 128)
   ```

4. **Use faster model:**
   ```bash
   # Switch to 7B model instead of 13B
   LLM_MODEL=meta-llama/Llama-2-7b-chat-hf
   ```

#### Issue 4: Workers Not Running

**Symptoms:**
- NPCs not performing actions
- No automation logs
- Decisions not being made

**Solutions:**

```bash
# Check worker containers
docker ps | grep worker

# Check worker logs
docker logs travian_automation_worker --tail 50
docker logs travian_ai_decision_worker --tail 50

# Restart workers
docker restart travian_automation_worker
docker restart travian_ai_decision_worker
docker restart travian_spawn_scheduler_worker

# Check database connectivity
docker exec travian_php php -r "new PDO('pgsql:host=postgres;dbname=travian_t46', 'travian', 'password');"
```

#### Issue 5: Database Connection Pool Exhausted

**Symptoms:**
- Error: "FATAL: remaining connection slots are reserved"
- Workers hanging
- High latency

**Solutions:**

```bash
# Check active connections
docker exec travian_postgres psql -U travian -c "SELECT count(*) FROM pg_stat_activity;"

# Increase max_connections in PostgreSQL
# Edit docker-compose-ai.yml:
# -c max_connections=1000  (from 500)

# Restart PostgreSQL
docker restart travian_postgres

# Optimize connection pooling in PHP workers
# Reduce connections per worker
```

### Monitoring Dashboard (Optional)

For advanced monitoring, consider integrating:

**Grafana + Prometheus:**

```yaml
# Add to docker-compose-ai.yml
  prometheus:
    image: prom/prometheus:latest
    ports:
      - "9090:9090"
    volumes:
      - ./monitoring/prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus

  grafana:
    image: grafana/grafana:latest
    ports:
      - "3000:3000"
    volumes:
      - grafana_data:/var/lib/grafana
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
```

**Metrics to Track:**
- LLM response time (P50, P95, P99)
- GPU utilization per device
- Cache hit rate
- Circuit breaker state
- Worker execution time
- Database query performance
- NPC decision distribution

---

## Environment Variables Reference

### Complete Environment Variables List

```bash
# ====================================
# Database Configuration
# ====================================
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=travian_t46
DB_USERNAME=travian
DB_PASSWORD=secure_password_here

# ====================================
# Redis Configuration
# ====================================
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=  # Optional

# ====================================
# LLM Backend Selection
# ====================================
# Options: ollama, lm_studio, vllm, text_generation_webui, openai
LLM_BACKEND=vllm

# ====================================
# LLM Endpoint Configuration
# ====================================
# Ollama
# LLM_ENDPOINT=http://localhost:11434/api/generate

# LM Studio
# LLM_ENDPOINT=http://localhost:1234/v1/completions

# vLLM (recommended)
LLM_ENDPOINT=http://vllm:8000/v1/completions

# Text Generation WebUI
# LLM_ENDPOINT=http://localhost:5000/api/v1/generate

# OpenAI
# LLM_ENDPOINT=https://api.openai.com/v1/completions

# ====================================
# LLM Model Configuration
# ====================================
# Model name (varies by backend)
LLM_MODEL=meta-llama/Llama-2-7b-chat-hf

# For Ollama:
# LLM_MODEL=llama2:7b

# For LM Studio:
# LLM_MODEL=llama-2-7b-chat

# ====================================
# LLM API Authentication
# ====================================
LLM_API_KEY=  # Required for OpenAI, optional for others

# ====================================
# LLM Generation Parameters
# ====================================
LLM_MAX_TOKENS=200
LLM_TEMPERATURE=0.7
LLM_TIMEOUT=10  # seconds

# ====================================
# LLM Retry Configuration
# ====================================
LLM_RETRY_ATTEMPTS=3
LLM_RETRY_DELAY=1000  # milliseconds

# ====================================
# GPU Configuration
# ====================================
# CUDA device selection
# Single GPU: 0 or 1
# Multi-GPU: 0,1
LLM_CUDA_DEVICE=0,1

# GPU type (auto-detected if not specified)
# Options: auto, rtx_3090_ti, tesla_p40
LLM_GPU_TYPE=auto

# Maximum GPU memory to use
LLM_GPU_MEMORY_LIMIT=40GB

# Fallback to CPU if GPU unavailable
LLM_FALLBACK_CPU=true

# ====================================
# Performance Configuration
# ====================================
# Enable response caching
LLM_ENABLE_CACHE=true

# Enable performance metrics
LLM_ENABLE_METRICS=true

# Connection pool size
LLM_POOL_SIZE=20

# ====================================
# Hugging Face Configuration
# ====================================
# Required for downloading gated models
HF_TOKEN=your_huggingface_token_here

# ====================================
# Worker Configuration
# ====================================
WORKER_AUTOMATION_INTERVAL=300  # seconds
WORKER_AI_DECISION_INTERVAL=300  # seconds
WORKER_SPAWN_SCHEDULER_INTERVAL=900  # seconds

# ====================================
# Feature Flags (Optional)
# ====================================
FEATURE_AI_NPCS_ENABLED=true
FEATURE_LLM_DECISIONS_ENABLED=true
FEATURE_AUTO_FARMING_ENABLED=true
FEATURE_AUTO_BUILDING_ENABLED=true
FEATURE_AUTO_TRAINING_ENABLED=true
```

### Quick Configuration Presets

#### Development (Single GPU, 50 NPCs)

```bash
LLM_BACKEND=ollama
LLM_ENDPOINT=http://localhost:11434/api/generate
LLM_MODEL=llama2:7b
LLM_CUDA_DEVICE=0
LLM_GPU_MEMORY_LIMIT=10GB
LLM_TIMEOUT=5
LLM_ENABLE_CACHE=true
LLM_POOL_SIZE=5
```

#### Production Small (Single GPU, 100 NPCs)

```bash
LLM_BACKEND=vllm
LLM_ENDPOINT=http://vllm:8000/v1/completions
LLM_MODEL=meta-llama/Llama-2-7b-chat-hf
LLM_CUDA_DEVICE=0
LLM_GPU_MEMORY_LIMIT=20GB
LLM_TIMEOUT=8
LLM_ENABLE_CACHE=true
LLM_POOL_SIZE=10
```

#### Production Large (Dual GPU, 500 NPCs)

```bash
LLM_BACKEND=vllm
LLM_ENDPOINT=http://vllm:8000/v1/completions
LLM_MODEL=meta-llama/Llama-2-13b-chat-hf
LLM_CUDA_DEVICE=0,1
LLM_GPU_MEMORY_LIMIT=44GB
LLM_TIMEOUT=12
LLM_RETRY_ATTEMPTS=3
LLM_ENABLE_CACHE=true
LLM_ENABLE_METRICS=true
LLM_POOL_SIZE=30
```

---

## Appendix

### Useful Commands Reference

```bash
# ====================================
# Docker Commands
# ====================================

# Start AI-NPC system
docker-compose -f docker-compose-ai.yml up -d

# Stop system
docker-compose -f docker-compose-ai.yml down

# View logs (all services)
docker-compose -f docker-compose-ai.yml logs -f

# View specific service logs
docker-compose -f docker-compose-ai.yml logs -f vllm

# Restart specific service
docker-compose -f docker-compose-ai.yml restart ai_decision_worker

# Check service status
docker-compose -f docker-compose-ai.yml ps

# Rebuild services
docker-compose -f docker-compose-ai.yml up -d --build

# Remove all volumes (CAUTION: deletes data)
docker-compose -f docker-compose-ai.yml down -v

# ====================================
# GPU Monitoring
# ====================================

# Real-time GPU monitoring
watch -n 1 nvidia-smi

# Continuous metrics logging
nvidia-smi dmon -s pucvmet -c 1000 > gpu_metrics.log

# Check CUDA availability
nvidia-smi --query-gpu=name,driver_version,cuda_version --format=csv

# ====================================
# Database Commands
# ====================================

# Connect to PostgreSQL
docker exec -it travian_postgres psql -U travian -d travian_t46

# Check active connections
docker exec travian_postgres psql -U travian -c "SELECT count(*) FROM pg_stat_activity;"

# Vacuum database
docker exec travian_postgres psql -U travian -d travian_t46 -c "VACUUM ANALYZE;"

# ====================================
# Redis Commands
# ====================================

# Connect to Redis CLI
docker exec -it travian_redis redis-cli

# Check memory usage
docker exec travian_redis redis-cli INFO memory

# Clear all caches
docker exec travian_redis redis-cli FLUSHALL

# ====================================
# Worker Management
# ====================================

# Restart all workers
docker restart travian_automation_worker travian_ai_decision_worker travian_spawn_scheduler_worker

# Check worker logs
docker logs travian_automation_worker --tail 100 -f

# Execute worker manually (testing)
docker exec travian_php php /var/www/html/cli/workers/automation-worker.php --verbose

# ====================================
# Performance Testing
# ====================================

# Test LLM endpoint
curl -X POST http://localhost:8000/v1/completions \
  -H "Content-Type: application/json" \
  -d '{"model":"meta-llama/Llama-2-7b-chat-hf","prompt":"Hello","max_tokens":50}'

# Load test (Apache Bench)
ab -n 100 -c 10 http://localhost/v1/npc/decision

# ====================================
# Backup & Restore
# ====================================

# Backup database
docker exec travian_postgres pg_dump -U travian travian_t46 > backup_$(date +%Y%m%d).sql

# Restore database
docker exec -i travian_postgres psql -U travian travian_t46 < backup_20251030.sql

# Backup volumes
docker run --rm -v travian_postgres_data:/data -v $(pwd):/backup alpine tar czf /backup/postgres_backup.tar.gz /data
```

### Performance Benchmarks

Expected performance metrics for reference:

| Configuration | NPCs | LLM Calls/min | Avg Response | GPU Util | VRAM Usage |
|---------------|------|---------------|--------------|----------|------------|
| Dev (Ollama, 7B, 1 GPU) | 50 | 10 | 180ms | 45% | 8GB |
| Small (vLLM, 7B, 1 GPU) | 100 | 20 | 120ms | 70% | 14GB |
| Medium (vLLM, 7B, 2 GPU) | 250 | 50 | 100ms | 80% | 28GB |
| Large (vLLM, 13B, 2 GPU) | 500 | 100 | 140ms | 90% | 42GB |

### Troubleshooting Checklist

Before reporting issues, verify:

- [ ] NVIDIA drivers installed (nvidia-smi works)
- [ ] Docker Desktop running with WSL2 backend
- [ ] GPU passthrough enabled in WSL2
- [ ] vLLM/Ollama service running
- [ ] Database accessible (PostgreSQL)
- [ ] Redis accessible
- [ ] Environment variables correctly set
- [ ] Workers running (docker ps)
- [ ] Sufficient GPU memory available
- [ ] Network connectivity between containers
- [ ] Log files checked for errors

### Support Resources

- **TravianT4.6 Documentation:** `/docs/SYSTEM_DOCUMENTATION.md`
- **Ollama Documentation:** https://ollama.ai/docs
- **vLLM Documentation:** https://docs.vllm.ai/
- **NVIDIA Container Toolkit:** https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/
- **Docker Compose Reference:** https://docs.docker.com/compose/

---

**Last Updated:** October 30, 2025  
**Version:** 1.0.0  
**Author:** TravianT4.6 Development Team
