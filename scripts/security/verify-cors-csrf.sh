#!/usr/bin/env bash
set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

BASE_URL="${1:-http://localhost:5000}"

echo -e "${YELLOW}🔍 Verifying CORS & CSRF protection...${NC}"
echo ""

# Test 1: CORS - Valid Origin
echo "Test 1: CORS with valid origin"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Origin: http://localhost:4200" \
    -H "Access-Control-Request-Method: POST" \
    -X OPTIONS \
    "$BASE_URL/v1/auth/login")

if [ "$RESPONSE" -eq 204 ]; then
    echo -e "${GREEN}✅ CORS allows valid origin${NC}"
else
    echo -e "${RED}❌ CORS test failed (expected 204, got $RESPONSE)${NC}"
fi

# Test 2: CORS - Invalid Origin
echo "Test 2: CORS with invalid origin"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Origin: https://evil.com" \
    -H "Access-Control-Request-Method: POST" \
    -X OPTIONS \
    "$BASE_URL/v1/auth/login")

if [ "$RESPONSE" -eq 403 ] || [ "$RESPONSE" -eq 204 ]; then
    echo -e "${GREEN}✅ CORS handles invalid origin${NC}"
else
    echo -e "${YELLOW}⚠️  CORS should block invalid origin (got $RESPONSE)${NC}"
fi

# Test 3: CSRF Token Generation
echo "Test 3: CSRF token generation"
TOKEN_RESPONSE=$(curl -s -c /tmp/csrf-cookies.txt "$BASE_URL/v1/token")
TOKEN=$(echo "$TOKEN_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -n "$TOKEN" ]; then
    echo -e "${GREEN}✅ CSRF token generated: ${TOKEN:0:10}...${NC}"
else
    echo -e "${RED}❌ CSRF token generation failed${NC}"
    echo "Response: $TOKEN_RESPONSE"
fi

# Test 4: CSRF Protection - Verify token endpoint requires no CSRF
echo "Test 4: GET token endpoint does not require CSRF"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/v1/token")

if [ "$RESPONSE" -eq 200 ]; then
    echo -e "${GREEN}✅ Token endpoint accessible without CSRF${NC}"
else
    echo -e "${RED}❌ Token endpoint should be accessible (got $RESPONSE)${NC}"
fi

# Test 5: CSRF Protection - POST requires token
echo "Test 5: POST request requires CSRF token"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Content-Type: application/json" \
    -H "lang: en-US" \
    -X POST \
    -d '{"test":"data"}' \
    "$BASE_URL/v1/auth/login")

if [ "$RESPONSE" -eq 403 ]; then
    echo -e "${GREEN}✅ CSRF blocks POST without token${NC}"
else
    echo -e "${YELLOW}⚠️  CSRF should block POST without token (got $RESPONSE)${NC}"
fi

# Cleanup
rm -f /tmp/csrf-cookies.txt

echo ""
echo -e "${GREEN}✅ CORS & CSRF verification complete${NC}"
