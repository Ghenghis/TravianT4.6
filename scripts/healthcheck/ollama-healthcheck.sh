#!/bin/bash
# Ollama health check - HTTP API probe
# Returns: 0 (healthy), 1 (unhealthy)

OLLAMA_PORT="${OLLAMA_PORT:-11434}"
TIMEOUT="${TIMEOUT:-10}"

if curl -sf -m "$TIMEOUT" http://localhost:$OLLAMA_PORT/api/tags > /dev/null 2>&1; then
    exit 0
fi

exit 1
