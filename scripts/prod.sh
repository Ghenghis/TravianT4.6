#!/usr/bin/env bash
# Start production stack with WAF protection

set -e

echo "Starting production stack..."
echo "All traffic will go through ModSecurity WAF (ports 80/443)"
echo "Nginx port 5000 will NOT be exposed"
echo ""

docker compose -f docker-compose.yml --profile production up -d

echo ""
echo "Production stack started successfully!"
echo "Access: http://your-domain.com (via WAF on ports 80/443)"
echo ""
echo "SECURITY: Nginx is only accessible internally through the WAF"
