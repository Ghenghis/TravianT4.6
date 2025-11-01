#!/bin/bash
# ============================================================================
# API Performance Benchmarking Script
# ============================================================================
# Created: October 30, 2025
# Purpose: Benchmark API performance with ApacheBench
# Target: <200ms average response time per request
# ============================================================================

API_BASE="http://localhost:5000"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "============================================================="
echo "         API Performance Benchmarking"
echo "============================================================="
echo "Target: <200ms average response time"
echo "Concurrency: 10 parallel requests"
echo "Total requests per endpoint: 100"
echo ""

# Check if server is running
echo -n "Checking if API server is running... "
if curl -s -o /dev/null -w "%{http_code}" "$API_BASE/v1/servers/getServerList" | grep -q "200"; then
    echo -e "${GREEN}✅ Server is running${NC}"
else
    echo -e "${RED}❌ Server not responding${NC}"
    echo "Please start the server first"
    exit 1
fi

echo ""

# Test Village API - getVillageList
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1. Testing Village - getVillageList"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo '{"worldId":"testworld","uid":2,"lang":"en-US"}' > /tmp/payload.json
ab -n 100 -c 10 -p /tmp/payload.json -T application/json \
   -q "$API_BASE/v1/village/getVillageList" 2>&1 | \
   grep -E "(Requests per second|Time per request|Failed requests)"

echo ""

# Test Village API - getVillageDetails
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2. Testing Village - getVillageDetails"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo '{"worldId":"testworld","villageId":160400,"uid":2,"lang":"en-US"}' > /tmp/payload2.json
ab -n 100 -c 10 -p /tmp/payload2.json -T application/json \
   -q "$API_BASE/v1/village/getVillageDetails" 2>&1 | \
   grep -E "(Requests per second|Time per request|Failed requests)"

echo ""

# Test Village API - getResources
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3. Testing Village - getResources"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo '{"worldId":"testworld","villageId":160400,"lang":"en-US"}' > /tmp/payload3.json
ab -n 100 -c 10 -p /tmp/payload3.json -T application/json \
   -q "$API_BASE/v1/village/getResources" 2>&1 | \
   grep -E "(Requests per second|Time per request|Failed requests)"

echo ""

# Test Village API - getBuildingQueue
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4. Testing Village - getBuildingQueue"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo '{"worldId":"testworld","villageId":160400,"lang":"en-US"}' > /tmp/payload4.json
ab -n 100 -c 10 -p /tmp/payload4.json -T application/json \
   -q "$API_BASE/v1/village/getBuildingQueue" 2>&1 | \
   grep -E "(Requests per second|Time per request|Failed requests)"

echo ""

# Cleanup
rm -f /tmp/payload*.json

echo "============================================================="
echo "                 Benchmark Complete!"
echo "============================================================="
echo ""
echo "Performance Analysis:"
echo "  - Good:     <100ms per request"
echo "  - Acceptable: 100-200ms per request"
echo "  - Needs optimization: >200ms per request"
echo ""
echo "Note: 'Time per request' shows average time across"
echo "      all concurrent requests"
echo "============================================================="
