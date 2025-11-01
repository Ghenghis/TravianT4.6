#!/bin/bash
# ============================================================================
# Travian T4.6 API Integration Test Suite
# ============================================================================
# Created: October 30, 2025
# Purpose: Test all API endpoints with maxed test accounts
# 
# Test Accounts:
#   - TestPlayer1 (uid=2, worldId=testworld): Villages 160400, 148375, etc.
#   - TestPlayer2 (uid=3, worldId=demo): Villages 164435, 152430, etc.
#   - TestPlayer3 (uid=4, worldId=speed500k): Villages 170850, 154820, etc.
#   - AdminTest (uid=5, worldId=testworld): Villages 168460, 138030, etc.
# ============================================================================

set -e

API_BASE="http://localhost:5000"
PASSED=0
FAILED=0
TOTAL=0
SKIPPED=0

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test helper function - tests for object response
test_endpoint() {
    local name="$1"
    local endpoint="$2"
    local payload="$3"
    local expected_key="$4"
    
    ((TOTAL++))
    echo -n "Testing [$TOTAL]: $name... "
    
    # Make API call and measure time
    start_time=$(date +%s%N)
    response=$(curl -s -X POST "$API_BASE$endpoint" \
        -H "Content-Type: application/json" \
        -d "$payload" 2>&1)
    end_time=$(date +%s%N)
    duration=$(( (end_time - start_time) / 1000000 ))
    
    # Check if response contains expected key
    if echo "$response" | jq -e ".$expected_key" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ PASS${NC} (${duration}ms)"
        ((PASSED++))
        
        # Warn if slow
        if [ $duration -gt 200 ]; then
            echo -e "  ${YELLOW}⚠ WARNING: Slow response (${duration}ms > 200ms target)${NC}"
        fi
    else
        echo -e "${RED}❌ FAIL${NC}"
        echo "  Response: $response" | head -c 200
        ((FAILED++))
    fi
}

# Test helper for list endpoints (check array)
test_list_endpoint() {
    local name="$1"
    local endpoint="$2"
    local payload="$3"
    local expected_key="$4"
    
    ((TOTAL++))
    echo -n "Testing [$TOTAL]: $name... "
    
    start_time=$(date +%s%N)
    response=$(curl -s -X POST "$API_BASE$endpoint" \
        -H "Content-Type: application/json" \
        -d "$payload" 2>&1)
    end_time=$(date +%s%N)
    duration=$(( (end_time - start_time) / 1000000 ))
    
    # Check if response contains array
    if echo "$response" | jq -e ".$expected_key | type == \"array\"" > /dev/null 2>&1; then
        count=$(echo "$response" | jq ".$expected_key | length")
        echo -e "${GREEN}✅ PASS${NC} ($count items, ${duration}ms)"
        ((PASSED++))
    else
        echo -e "${RED}❌ FAIL${NC}"
        echo "  Response: $response" | head -c 200
        ((FAILED++))
    fi
}

# Skip endpoint (not yet implemented)
skip_endpoint() {
    local name="$1"
    ((TOTAL++))
    ((SKIPPED++))
    echo -e "  [$TOTAL]: $name... ${BLUE}⊘ SKIPPED${NC} (not implemented)"
}

echo "============================================================="
echo "      TravianT4.6 API Integration Test Suite"
echo "============================================================="
echo "API Base: $API_BASE"
echo "Testing endpoints with maxed test accounts"
echo ""

# Check if server is running
echo -n "Checking if API server is running... "
response=$(curl -s -X POST "$API_BASE/v1/servers/loadServers" \
    -H "Content-Type: application/json" \
    -d '{"lang":"en-US"}' 2>&1)
if echo "$response" | jq -e '.success' > /dev/null 2>&1 || echo "$response" | jq -e '.error' > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Server is running${NC}"
else
    echo -e "${RED}❌ Server not responding${NC}"
    echo "Response: $response"
    echo "Please start the server with: php -S 0.0.0.0:5000 router.php"
    exit 1
fi

echo ""

###########################################
# PHASE 4A - CORE GAMEPLAY
###########################################

echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  PHASE 4A: CORE GAMEPLAY (26 endpoints)${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# --- Village API (5 endpoints) ---
echo "┌─────────────────────────────────────────────┐"
echo "│  Village API (5 endpoints)                  │"
echo "└─────────────────────────────────────────────┘"

test_list_endpoint \
    "Village - getVillageList (TestPlayer1)" \
    "/v1/village/getVillageList" \
    '{"worldId":"testworld","uid":2,"lang":"en-US"}' \
    "villages"

test_endpoint \
    "Village - getVillageDetails (Village 160400)" \
    "/v1/village/getVillageDetails" \
    '{"worldId":"testworld","villageId":160400,"uid":2,"lang":"en-US"}' \
    "village"

test_endpoint \
    "Village - getResources (Village 160400)" \
    "/v1/village/getResources" \
    '{"worldId":"testworld","villageId":160400,"lang":"en-US"}' \
    "resources"

test_list_endpoint \
    "Village - getBuildingQueue (Village 160400)" \
    "/v1/village/getBuildingQueue" \
    '{"worldId":"testworld","villageId":160400,"lang":"en-US"}' \
    "queue"

skip_endpoint "Village - upgradeBuilding"

echo ""

# --- Map API (5 endpoints) ---
echo "┌─────────────────────────────────────────────┐"
echo "│  Map API (5 endpoints)                      │"
echo "└─────────────────────────────────────────────┘"

skip_endpoint "Map - getMapData"
skip_endpoint "Map - getVillageInfo"
skip_endpoint "Map - getTileDetails"
skip_endpoint "Map - searchVillages"
skip_endpoint "Map - getNearby"

echo ""

# --- Troop API (6 endpoints) ---
echo "┌─────────────────────────────────────────────┐"
echo "│  Troop API (6 endpoints)                    │"
echo "└─────────────────────────────────────────────┘"

skip_endpoint "Troop - getTroops"
skip_endpoint "Troop - trainUnits"
skip_endpoint "Troop - sendAttack"
skip_endpoint "Troop - sendReinforcement"
skip_endpoint "Troop - getTrainingQueue"
skip_endpoint "Troop - getMovements"

echo ""

# --- Alliance API (5 endpoints) ---
echo "┌─────────────────────────────────────────────┐"
echo "│  Alliance API (5 endpoints)                 │"
echo "└─────────────────────────────────────────────┘"

skip_endpoint "Alliance - create"
skip_endpoint "Alliance - invite"
skip_endpoint "Alliance - getMembers"
skip_endpoint "Alliance - setDiplomacy"
skip_endpoint "Alliance - getDiplomacy"

echo ""

# --- Market API (5 endpoints) ---
echo "┌─────────────────────────────────────────────┐"
echo "│  Market API (5 endpoints)                   │"
echo "└─────────────────────────────────────────────┘"

skip_endpoint "Market - getOffers"
skip_endpoint "Market - createOffer"
skip_endpoint "Market - sendResources"
skip_endpoint "Market - acceptOffer"
skip_endpoint "Market - getTradeHistory"

echo ""

###########################################
# PHASE 4B - ESSENTIAL FEATURES
###########################################

echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  PHASE 4B: ESSENTIAL FEATURES (34 endpoints)${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# --- Hero API (9 endpoints) ---
echo "┌─────────────────────────────────────────────┐"
echo "│  Hero API (9 endpoints)                     │"
echo "└─────────────────────────────────────────────┘"

skip_endpoint "Hero - getHero"
skip_endpoint "Hero - levelUp"
skip_endpoint "Hero - equipItem"
skip_endpoint "Hero - startAdventure"
skip_endpoint "Hero - getAdventures"
skip_endpoint "Hero - sellItem"
skip_endpoint "Hero - auctionItem"
skip_endpoint "Hero - bidOnAuction"
skip_endpoint "Hero - getAuctions"

echo ""

# --- Quest API (5 endpoints) ---
echo "┌─────────────────────────────────────────────┐"
echo "│  Quest API (5 endpoints)                    │"
echo "└─────────────────────────────────────────────┘"

skip_endpoint "Quest - getActiveQuests"
skip_endpoint "Quest - completeQuest"
skip_endpoint "Quest - getQuestRewards"
skip_endpoint "Quest - skipQuest"
skip_endpoint "Quest - getQuestProgress"

echo ""

# --- Reports API (6 endpoints) ---
echo "┌─────────────────────────────────────────────┐"
echo "│  Reports API (6 endpoints)                  │"
echo "└─────────────────────────────────────────────┘"

test_list_endpoint \
    "Reports - getReports (page 1)" \
    "/v1/reports/getReports" \
    '{"worldId":"testworld","uid":2,"type":"all","limit":10,"offset":0,"lang":"en-US"}' \
    "reports"

test_list_endpoint \
    "Reports - getReports (page 2)" \
    "/v1/reports/getReports" \
    '{"worldId":"testworld","uid":2,"type":"all","limit":10,"offset":10,"lang":"en-US"}' \
    "reports"

skip_endpoint "Reports - getReportDetails"

test_endpoint \
    "Reports - getUnreadCount" \
    "/v1/reports/getUnreadCount" \
    '{"worldId":"testworld","uid":2,"lang":"en-US"}' \
    "unreadCount"

skip_endpoint "Reports - markAsRead"
skip_endpoint "Reports - archive"

echo ""

# --- Messages API (8 endpoints) ---
echo "┌─────────────────────────────────────────────┐"
echo "│  Messages API (8 endpoints)                 │"
echo "└─────────────────────────────────────────────┘"

test_list_endpoint \
    "Messages - getInbox" \
    "/v1/messages/getInbox" \
    '{"worldId":"testworld","uid":2,"folder":"inbox","limit":50,"offset":0,"lang":"en-US"}' \
    "messages"

skip_endpoint "Messages - sendMessage"
skip_endpoint "Messages - deleteMessage"
skip_endpoint "Messages - markAsRead"
skip_endpoint "Messages - getAllianceMessages"
skip_endpoint "Messages - sendAllianceMessage"
skip_endpoint "Messages - getMessageDetails"

test_endpoint \
    "Messages - getUnreadCount" \
    "/v1/messages/getUnreadCount" \
    '{"worldId":"testworld","uid":2,"lang":"en-US"}' \
    "unreadCount"

echo ""

# --- Statistics API (6 endpoints) ---
echo "┌─────────────────────────────────────────────┐"
echo "│  Statistics API (6 endpoints)               │"
echo "└─────────────────────────────────────────────┘"

test_list_endpoint \
    "Statistics - getPlayerRankings" \
    "/v1/statistics/getPlayerRankings" \
    '{"worldId":"testworld","category":"population","limit":50,"offset":0,"lang":"en-US"}' \
    "rankings"

test_list_endpoint \
    "Statistics - getAllianceRankings" \
    "/v1/statistics/getAllianceRankings" \
    '{"worldId":"testworld","limit":50,"offset":0,"lang":"en-US"}' \
    "rankings"

test_endpoint \
    "Statistics - getPlayerStats" \
    "/v1/statistics/getPlayerStats" \
    '{"worldId":"testworld","uid":2,"lang":"en-US"}' \
    "stats"

skip_endpoint "Statistics - getAllianceStats"

test_endpoint \
    "Statistics - getTop10" \
    "/v1/statistics/getTop10" \
    '{"worldId":"testworld","lang":"en-US"}' \
    "top10"

test_endpoint \
    "Statistics - getWorldStats" \
    "/v1/statistics/getWorldStats" \
    '{"worldId":"testworld","lang":"en-US"}' \
    "stats"

echo ""

###########################################
# TEST SUMMARY
###########################################

echo ""
echo "============================================================="
echo "                    TEST SUMMARY"
echo "============================================================="
echo -e "Total Tests:   ${TOTAL}"
echo -e "${GREEN}Passed:        ${PASSED}${NC}"
echo -e "${RED}Failed:        ${FAILED}${NC}"
echo -e "${BLUE}Skipped:       ${SKIPPED}${NC} (not yet implemented)"
echo "============================================================="

if [ $FAILED -eq 0 ]; then
    success_rate=$(( PASSED * 100 / (PASSED + SKIPPED) ))
    echo -e "\n${GREEN}✅ ALL IMPLEMENTED TESTS PASSED!${NC}"
    echo -e "Implementation progress: ${success_rate}% (${PASSED}/${TOTAL} endpoints)"
    exit 0
else
    echo -e "\n${RED}❌ SOME TESTS FAILED${NC}"
    echo "Please check the failed endpoints above"
    exit 1
fi
