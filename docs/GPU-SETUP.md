# GPU Setup for TravianT4.6 AI-NPC System

## Overview
The AI-NPC system uses local LLMs for decision-making, leveraging NVIDIA GPUs for acceleration.

## Hardware Requirements
- **Primary GPU:** NVIDIA RTX 3090 Ti (24GB VRAM) - Ollama
- **Secondary GPU:** NVIDIA Tesla P40 (24GB VRAM) - vLLM
- NVIDIA Driver 525.60.13 or newer
- CUDA 12.0 or newer

## Host Setup

### 1. Install NVIDIA Drivers
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y nvidia-driver-535

# Verify installation
nvidia-smi
```

### 2. Install NVIDIA Container Toolkit
```bash
# Add repository
distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
curl -s -L https://nvidia.github.io/libnvidia-container/gpgkey | sudo apt-key add -
curl -s -L https://nvidia.github.io/libnvidia-container/$distribution/libnvidia-container.list | sudo tee /etc/apt/sources.list.d/nvidia-container-toolkit.list

# Install toolkit
sudo apt-get update
sudo apt-get install -y nvidia-container-toolkit

# Configure Docker
sudo nvidia-ctk runtime configure --runtime=docker
sudo systemctl restart docker

# Verify installation
docker run --rm --gpus all nvidia/cuda:12.0-base nvidia-smi
```

### 3. Verify GPU Access
```bash
# Check both GPUs are visible
nvidia-smi --list-gpus

# Expected output:
# GPU 0: NVIDIA GeForce RTX 3090 Ti (UUID: GPU-...)
# GPU 1: Tesla P40 (UUID: GPU-...)
```

## Docker Compose GPU Configuration

### Production (GPU Required)
```bash
# Enable GPU for both LLM services
docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile llm-ollama --profile llm-vllm up -d

# Verify GPU allocation
docker exec travian_ollama nvidia-smi
docker exec travian_vllm nvidia-smi
```

### Development (GPU Optional)

#### CPU Mode (Default)
```bash
# Run without GPU (automatic CPU fallback)
docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile llm-ollama up -d

# Ollama will automatically use CPU when GPU is not configured
# Performance: ~10-20x slower than GPU, suitable for testing
```

#### GPU Mode (Opt-in)
```bash
# 1. Edit docker-compose.dev.yml and uncomment GPU sections for ollama/vllm
# 2. Uncomment environment variables (CUDA_VISIBLE_DEVICES, OLLAMA_NUM_GPU)
# 3. Uncomment deploy.resources.reservations.devices section
# 4. Start services with GPU
docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile llm-ollama --profile llm-vllm up -d
```

## GPU Allocation Strategy

| Service | GPU | Device ID | VRAM | Purpose |
|---------|-----|-----------|------|---------|
| Ollama | RTX 3090 Ti | 0 | 24GB | General LLM inference (phi-2, llama-2-7b) |
| vLLM | Tesla P40 | 1 | 24GB | Batched inference (llama-2-7b-chat-hf) |

## CPU Fallback

### Ollama (Graceful Fallback)
- Automatically falls back to CPU if GPU unavailable
- Performance: ~10-20x slower than GPU
- Use for development/testing only

### vLLM (Limited Fallback)
- Requires GPU for most models
- CPU mode not recommended for production
- Use Ollama for CPU-only environments

## Important Environment Variables

### OLLAMA_NUM_GPU
- **Value 1 or higher:** Use GPU acceleration (requires GPU configured in deploy section)
- **Value 0 or omitted:** Use CPU mode (automatic fallback, no GPU required)
- **Default in dev:** Omitted (CPU mode)
- **Default in prod:** 1 (GPU mode)

### CUDA_VISIBLE_DEVICES
- Specifies which GPU(s) to use
- **Value 0:** First GPU (RTX 3090 Ti)
- **Value 1:** Second GPU (Tesla P40)
- **Value -1 or omitted:** CPU mode (no GPU)
- **Default in dev:** Omitted (CPU mode)
- **Default in prod:** 0 for Ollama, 1 for vLLM

## Behavior Summary

| Environment | OLLAMA_NUM_GPU | CUDA_VISIBLE_DEVICES | GPU Deploy | Behavior |
|-------------|----------------|----------------------|------------|----------|
| Base (docker-compose.yml) | omitted | omitted | commented | CPU mode |
| Development (dev.yml) | omitted | omitted | commented | CPU mode (opt-in GPU) |
| Production (prod.yml) | 1 | 0 or 1 | enabled | GPU mode (required) |

## GPU Verification

### Inside Containers
```bash
# Verify GPU access in Ollama container
docker exec travian_ollama /var/www/html/scripts/verify-gpu.sh

# Expected output (JSON):
{
  "status": "success",
  "message": "GPU(s) detected and accessible",
  "gpu_count": 1,
  "timestamp": "2025-10-30T12:00:00Z",
  "gpus": [
    {
      "index": 0,
      "name": "NVIDIA GeForce RTX 3090 Ti",
      "driver_version": "535.129.03",
      "memory_total": 24564,
      "memory_used": 1024,
      "memory_free": 23540,
      "utilization_gpu": 15,
      "utilization_memory": 4,
      "temperature": 45
    }
  ]
}
```

### Human-Readable Output
```bash
docker exec travian_ollama bash -c "OUTPUT_FORMAT=human /var/www/html/scripts/verify-gpu.sh"

# Expected output:
========================================
GPU Verification Report
========================================
Status: success
Message: GPU(s) detected and accessible
GPU Count: 1
Timestamp: Wed Oct 30 12:00:00 UTC 2025
========================================

GPU Details:
----------------------------------------
index, name, driver_version, memory.total [MiB], memory.used [MiB], ...
0, NVIDIA GeForce RTX 3090 Ti, 535.129.03, 24564, 1024, ...
----------------------------------------
```

### Automated Monitoring
```bash
# Create monitoring script
cat > scripts/monitor-gpu.sh << 'EOF'
#!/bin/bash
while true; do
    docker exec travian_ollama /var/www/html/scripts/verify-gpu.sh | jq -r '.gpus[0] | "GPU: \(.name) | Temp: \(.temperature)°C | GPU: \(.utilization_gpu)% | Memory: \(.utilization_memory)%"'
    sleep 5
done
EOF

chmod +x scripts/monitor-gpu.sh
./scripts/monitor-gpu.sh
```

## Troubleshooting

### GPU Not Detected
```bash
# Check NVIDIA driver
nvidia-smi

# Check Docker GPU support
docker run --rm --gpus all nvidia/cuda:12.0-base nvidia-smi

# Check container GPU access
docker exec -it travian_ollama nvidia-smi
```

### Out of Memory Errors
```bash
# Reduce GPU memory utilization
# Edit .env.production:
VLLM_GPU_MEMORY_UTILIZATION=0.7  # Default: 0.9

# Or use smaller models
OLLAMA_MODEL=phi-2  # Instead of llama-2-7b
VLLM_MODEL=meta-llama/Llama-2-7b-chat-hf  # Instead of 13b
```

### Permission Denied
```bash
# Add user to docker group
sudo usermod -aG docker $USER
newgrp docker

# Verify docker can access GPU
docker run --rm --gpus all nvidia/cuda:12.0-base nvidia-smi
```

## Performance Optimization

### CUDA Memory Management
```bash
# Set CUDA environment variables in .env
CUDA_LAUNCH_BLOCKING=0
CUDA_CACHE_DISABLE=0
PYTORCH_CUDA_ALLOC_CONF=max_split_size_mb:512
```

### GPU Monitoring
```bash
# Real-time GPU monitoring
watch -n 1 nvidia-smi

# Log GPU usage
nvidia-smi --query-gpu=timestamp,name,utilization.gpu,utilization.memory,memory.used,memory.free --format=csv -l 1 > gpu_usage.log
```

## References
- [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/install-guide.html)
- [Docker GPU Support](https://docs.docker.com/config/containers/resource_constraints/#gpu)
- [Ollama GPU Acceleration](https://github.com/ollama/ollama/blob/main/docs/gpu.md)
- [vLLM GPU Configuration](https://docs.vllm.ai/en/latest/getting_started/installation.html)
