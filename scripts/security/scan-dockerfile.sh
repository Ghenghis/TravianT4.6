#!/usr/bin/env bash
set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}🔍 Dockerfile Security Scanning${NC}"
echo ""

if ! command -v hadolint &> /dev/null; then
    echo -e "${YELLOW}⚠️  hadolint not installed. Skipping Dockerfile scanning.${NC}"
    echo -e "${YELLOW}To install hadolint: https://github.com/hadolint/hadolint${NC}"
else
    echo "Scanning docker/php-app/Dockerfile..."
    hadolint docker/php-app/Dockerfile || true
    
    echo ""
    echo "Scanning docker/nginx/Dockerfile.prod..."
    hadolint docker/nginx/Dockerfile.prod || true
    
    echo ""
    echo -e "${GREEN}✅ Dockerfile scanning complete${NC}"
fi
