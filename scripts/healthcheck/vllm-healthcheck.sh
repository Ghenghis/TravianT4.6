#!/bin/bash
# vLLM health check - HTTP API probe
# Returns: 0 (healthy), 1 (unhealthy)

VLLM_PORT="${VLLM_PORT:-8000}"
TIMEOUT="${TIMEOUT:-10}"

if curl -sf -m "$TIMEOUT" http://localhost:$VLLM_PORT/health > /dev/null 2>&1; then
    exit 0
elif curl -sf -m "$TIMEOUT" http://localhost:$VLLM_PORT/v1/models > /dev/null 2>&1; then
    exit 0
fi

exit 1
