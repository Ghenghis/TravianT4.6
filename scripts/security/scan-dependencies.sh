#!/usr/bin/env bash
set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}🔍 Dependency Vulnerability Scanning${NC}"
echo ""

echo "Scanning PHP dependencies (composer audit)..."
if [ -f "sections/api/composer.json" ]; then
    cd sections/api
    composer audit --format=json > /tmp/composer-audit.json 2>&1 || true
    cd ../..
    
    if grep -q '"advisories"' /tmp/composer-audit.json 2>/dev/null; then
        echo -e "${RED}⚠️  Vulnerabilities found in PHP dependencies${NC}"
        cat /tmp/composer-audit.json
    else
        echo -e "${GREEN}✅ No vulnerabilities in PHP dependencies${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  sections/api/composer.json not found${NC}"
fi

echo ""
echo "Scanning Docker images with Trivy..."

if ! command -v trivy &> /dev/null; then
    echo -e "${YELLOW}⚠️  Trivy not installed. Skipping Docker image scanning.${NC}"
    echo -e "${YELLOW}To install Trivy: https://aquasecurity.github.io/trivy/latest/getting-started/installation/${NC}"
else
    echo "Scanning PHP-FPM image..."
    trivy image --severity HIGH,CRITICAL \
        --format json \
        --output /tmp/trivy-php.json \
        travian_php-fpm 2>/dev/null || echo -e "${YELLOW}⚠️  PHP-FPM image not found${NC}"
    
    if [ -f /tmp/trivy-php.json ] && grep -q '"Vulnerabilities"' /tmp/trivy-php.json 2>/dev/null; then
        VULN_COUNT=$(jq '[.Results[].Vulnerabilities // []] | add | length' /tmp/trivy-php.json 2>/dev/null || echo "0")
        if [ "$VULN_COUNT" -gt 0 ]; then
            echo -e "${RED}⚠️  Found $VULN_COUNT HIGH/CRITICAL vulnerabilities in PHP image${NC}"
        else
            echo -e "${GREEN}✅ No HIGH/CRITICAL vulnerabilities in PHP image${NC}"
        fi
    fi
    
    echo "Scanning Nginx image..."
    trivy image --severity HIGH,CRITICAL \
        --format json \
        --output /tmp/trivy-nginx.json \
        travian_nginx 2>/dev/null || echo -e "${YELLOW}⚠️  Nginx image not found${NC}"
    
    if [ -f /tmp/trivy-nginx.json ] && grep -q '"Vulnerabilities"' /tmp/trivy-nginx.json 2>/dev/null; then
        VULN_COUNT=$(jq '[.Results[].Vulnerabilities // []] | add | length' /tmp/trivy-nginx.json 2>/dev/null || echo "0")
        if [ "$VULN_COUNT" -gt 0 ]; then
            echo -e "${RED}⚠️  Found $VULN_COUNT HIGH/CRITICAL vulnerabilities in Nginx image${NC}"
        else
            echo -e "${GREEN}✅ No HIGH/CRITICAL vulnerabilities in Nginx image${NC}"
        fi
    fi
fi

echo ""
echo -e "${GREEN}✅ Dependency scanning complete${NC}"
echo ""
echo "Reports generated:"
echo "  - /tmp/composer-audit.json"
echo "  - /tmp/trivy-php.json (if Trivy installed)"
echo "  - /tmp/trivy-nginx.json (if Trivy installed)"
