#!/usr/bin/env bash
set -euo pipefail

# Scan Docker images for vulnerabilities using Trivy

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}🔍 Scanning Docker images for vulnerabilities...${NC}"

# Check if Trivy is installed
if ! command -v trivy &> /dev/null; then
    echo -e "${RED}❌ Trivy not installed${NC}"
    echo "Install with: curl -sfL https://raw.githubusercontent.com/aquasecurity/trivy/main/contrib/install.sh | sh -s -- -b /usr/local/bin"
    exit 1
fi

# Get actual image names from docker compose
echo "📋 Discovering images from docker-compose.yml..."
IMAGES=$(docker compose config 2>/dev/null | grep 'image:' | awk '{print $2}' | sort -u || true)

# Add locally built images
LOCAL_IMAGES=$(docker images --format "{{.Repository}}:{{.Tag}}" 2>/dev/null | grep -E "travian|php-app|nginx" | grep -v "<none>" || true)

# Combine and dedupe
ALL_IMAGES=$(echo -e "$IMAGES\n$LOCAL_IMAGES" | grep -v '^$' | sort -u)

if [ -z "$ALL_IMAGES" ]; then
    echo -e "${RED}❌ No images found to scan${NC}"
    exit 1
fi

echo -e "${GREEN}Found images to scan:${NC}"
echo "$ALL_IMAGES"
echo ""

# Scan each image
FAIL_COUNT=0
for IMAGE in $ALL_IMAGES; do
    echo -e "${YELLOW}Scanning $IMAGE...${NC}"
    
    # Scan for HIGH and CRITICAL vulnerabilities
    if trivy image --severity HIGH,CRITICAL --exit-code 1 "$IMAGE" 2>/dev/null; then
        echo -e "${GREEN}✅ $IMAGE passed security scan${NC}"
    else
        echo -e "${RED}❌ $IMAGE has HIGH or CRITICAL vulnerabilities${NC}"
        ((FAIL_COUNT++))
    fi
    echo ""
done

# Summary
if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}✅ All images passed security scan${NC}"
    exit 0
else
    echo -e "${RED}❌ $FAIL_COUNT images have vulnerabilities${NC}"
    exit 1
fi
