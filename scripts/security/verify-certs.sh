#!/usr/bin/env bash
set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}🔍 Verifying TLS/SSL certificates...${NC}"

if [ -f "./certs/dev/server.crt" ]; then
    echo "Checking development certificate..."
    openssl x509 -in ./certs/dev/server.crt -noout -text | grep -E "Subject:|Issuer:|Not After"
    echo -e "${GREEN}✅ Development certificate found${NC}"
else
    echo -e "${RED}❌ Development certificate not found${NC}"
    echo "Run: ./scripts/security/generate-dev-certs.sh"
fi

if docker compose exec -T certbot test -d "/etc/letsencrypt/live/${DOMAIN:-travian.example.com}" 2>/dev/null; then
    echo "Checking production certificate..."
    docker compose exec -T certbot certbot certificates
    echo -e "${GREEN}✅ Production certificate found${NC}"
else
    echo -e "${YELLOW}⚠️  Production certificate not found (expected in dev)${NC}"
fi

echo -e "${GREEN}✅ Certificate verification complete${NC}"
