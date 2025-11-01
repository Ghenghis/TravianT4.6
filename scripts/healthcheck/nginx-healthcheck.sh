#!/bin/bash
# Nginx health check - HTTP probe
# Returns: 0 (healthy), 1 (unhealthy)

NGINX_PORT="${NGINX_PORT:-80}"
TIMEOUT="${TIMEOUT:-5}"

if curl -sf -m "$TIMEOUT" http://localhost:$NGINX_PORT/ > /dev/null 2>&1; then
    exit 0
fi
exit 1
