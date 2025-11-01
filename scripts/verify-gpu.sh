#!/bin/bash

# ==========================================
# TRAVIAN T4.6 - GPU VERIFICATION SCRIPT
# ==========================================
# Verifies NVIDIA GPU access inside containers
# Outputs JSON for automation
# Gracefully handles missing nvidia-smi
# ==========================================

set -o pipefail

# ==========================================
# CONFIGURATION
# ==========================================
NVIDIA_SMI_CMD="nvidia-smi"
OUTPUT_FORMAT="${OUTPUT_FORMAT:-json}"  # json or human
EXIT_ON_ERROR="${EXIT_ON_ERROR:-true}"

# ==========================================
# FUNCTIONS
# ==========================================

# Output JSON result
output_json() {
    local status="$1"
    local message="$2"
    local gpu_count="${3:-0}"
    local gpu_details="${4:-[]}"
    
    cat <<EOF
{
  "status": "$status",
  "message": "$message",
  "gpu_count": $gpu_count,
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "gpus": $gpu_details
}
EOF
}

# Output human-readable result
output_human() {
    local status="$1"
    local message="$2"
    local gpu_count="${3:-0}"
    
    echo "========================================"
    echo "GPU Verification Report"
    echo "========================================"
    echo "Status: $status"
    echo "Message: $message"
    echo "GPU Count: $gpu_count"
    echo "Timestamp: $(date)"
    echo "========================================"
}

# Check if nvidia-smi is available
check_nvidia_smi() {
    if command -v $NVIDIA_SMI_CMD &> /dev/null; then
        return 0
    else
        return 1
    fi
}

# Get GPU count
get_gpu_count() {
    local count=$(nvidia-smi --list-gpus 2>/dev/null | wc -l)
    echo "$count"
}

# Get GPU details as JSON
get_gpu_details_json() {
    nvidia-smi --query-gpu=index,name,driver_version,memory.total,memory.used,memory.free,utilization.gpu,utilization.memory,temperature.gpu --format=csv,noheader,nounits 2>/dev/null | awk -F', ' '
    BEGIN { printf "["; first=1 }
    {
        if (!first) printf ",";
        first=0;
        printf "{\"index\":%d,\"name\":\"%s\",\"driver_version\":\"%s\",\"memory_total\":%d,\"memory_used\":%d,\"memory_free\":%d,\"utilization_gpu\":%d,\"utilization_memory\":%d,\"temperature\":%d}",
        $1, $2, $3, $4, $5, $6, $7, $8, $9
    }
    END { printf "]" }
    '
}

# Get GPU details as human-readable
get_gpu_details_human() {
    echo ""
    echo "GPU Details:"
    echo "----------------------------------------"
    nvidia-smi --query-gpu=index,name,driver_version,memory.total,memory.used,memory.free,utilization.gpu,utilization.memory,temperature.gpu --format=table 2>/dev/null || echo "Unable to retrieve GPU details"
    echo "----------------------------------------"
}

# ==========================================
# MAIN EXECUTION
# ==========================================

main() {
    # Check if nvidia-smi is available
    if ! check_nvidia_smi; then
        if [ "$OUTPUT_FORMAT" = "json" ]; then
            output_json "error" "nvidia-smi not found (NVIDIA Container Toolkit not installed or GPU not available)" 0 "[]"
        else
            output_human "error" "nvidia-smi not found (NVIDIA Container Toolkit not installed or GPU not available)" 0
        fi
        
        if [ "$EXIT_ON_ERROR" = "true" ]; then
            exit 1
        else
            exit 0  # Graceful exit for CPU fallback
        fi
    fi
    
    # Get GPU count
    GPU_COUNT=$(get_gpu_count)
    
    if [ "$GPU_COUNT" -eq 0 ]; then
        if [ "$OUTPUT_FORMAT" = "json" ]; then
            output_json "warning" "nvidia-smi found but no GPUs detected (check CUDA_VISIBLE_DEVICES or container GPU passthrough)" 0 "[]"
        else
            output_human "warning" "nvidia-smi found but no GPUs detected (check CUDA_VISIBLE_DEVICES or container GPU passthrough)" 0
        fi
        
        if [ "$EXIT_ON_ERROR" = "true" ]; then
            exit 1
        else
            exit 0  # Graceful exit for CPU fallback
        fi
    fi
    
    # Get GPU details
    if [ "$OUTPUT_FORMAT" = "json" ]; then
        GPU_DETAILS=$(get_gpu_details_json)
        output_json "success" "GPU(s) detected and accessible" "$GPU_COUNT" "$GPU_DETAILS"
    else
        output_human "success" "GPU(s) detected and accessible" "$GPU_COUNT"
        get_gpu_details_human
    fi
    
    exit 0
}

# Run main function
main "$@"
