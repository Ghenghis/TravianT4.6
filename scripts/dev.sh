#!/usr/bin/env bash
# Start development stack with nginx exposed on port 5000

set -e

echo "Starting development stack..."
echo "Nginx will be exposed on port 5000 (bypasses WAF)"
echo ""

docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

echo ""
echo "Development stack started successfully!"
echo "Access: http://localhost:5000"
