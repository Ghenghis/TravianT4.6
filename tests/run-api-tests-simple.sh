#!/bin/bash
# ============================================================================
# Travian T4.6 API Integration Test Suite (Simplified)
# ============================================================================

set -e

API_BASE="http://localhost:5000"
PASSED=0
FAILED=0
TOTAL=0
SKIPPED=0

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Test endpoint
test_endpoint() {
    local name="$1"
    local endpoint="$2"
    local payload="$3"
    local expected_key="$4"
    
    ((TOTAL++))
    echo -n "  [$TOTAL]: $name... "
    
    response=$(timeout 5 curl -s -X POST "$API_BASE$endpoint" \
        -H "Content-Type: application/json" \
        -d "$payload" 2>&1 || echo '{"error": "timeout"}')
    
    if echo "$response" | jq -e ".data.$expected_key" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ PASS${NC}"
        ((PASSED++))
    else
        echo -e "${RED}❌ FAIL${NC}"
        ((FAILED++))
    fi
}

# Skip endpoint
skip_endpoint() {
    local name="$1"
    ((TOTAL++))
    ((SKIPPED++))
    echo -e "  [$TOTAL]: $name... ${BLUE}⊘ SKIPPED${NC}"
}

echo "============================================================="
echo "      TravianT4.6 API Integration Test Suite"
echo "============================================================="
echo ""

# Check server
echo -n "Checking API server... "
if curl -s -o /dev/null -X POST "$API_BASE/v1/servers/loadServers" \
    -H "Content-Type: application/json" \
    -d '{"lang":"en-US"}'; then
    echo -e "${GREEN}✅ Running${NC}"
else
    echo -e "${RED}❌ Not responding${NC}"
    exit 1
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "PHASE 4A: CORE GAMEPLAY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "Village API (5 endpoints):"
test_endpoint "getVillageList" "/v1/village/getVillageList" \
    '{"worldId":"testworld","uid":2,"lang":"en-US"}' "villages"
test_endpoint "getVillageDetails" "/v1/village/getVillageDetails" \
    '{"worldId":"testworld","villageId":160400,"uid":2,"lang":"en-US"}' "village"
test_endpoint "getResources" "/v1/village/getResources" \
    '{"worldId":"testworld","villageId":160400,"lang":"en-US"}' "resources"
test_endpoint "getBuildingQueue" "/v1/village/getBuildingQueue" \
    '{"worldId":"testworld","villageId":160400,"lang":"en-US"}' "queue"
skip_endpoint "upgradeBuilding"

echo ""
echo "Map API (5 endpoints):"
skip_endpoint "getMapData"
skip_endpoint "getVillageInfo"
skip_endpoint "getTileDetails"
skip_endpoint "searchVillages"
skip_endpoint "getNearby"

echo ""
echo "Troop API (6 endpoints):"
skip_endpoint "getTroops"
skip_endpoint "trainUnits"
skip_endpoint "sendAttack"
skip_endpoint "sendReinforcement"
skip_endpoint "getTrainingQueue"
skip_endpoint "getMovements"

echo ""
echo "Alliance API (5 endpoints):"
skip_endpoint "create"
skip_endpoint "invite"
skip_endpoint "getMembers"
skip_endpoint "setDiplomacy"
skip_endpoint "getDiplomacy"

echo ""
echo "Market API (5 endpoints):"
skip_endpoint "getOffers"
skip_endpoint "createOffer"
skip_endpoint "sendResources"
skip_endpoint "acceptOffer"
skip_endpoint "getTradeHistory"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "PHASE 4B: ESSENTIAL FEATURES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "Hero API (9 endpoints):"
skip_endpoint "getHero"
skip_endpoint "levelUp"
skip_endpoint "equipItem"
skip_endpoint "startAdventure"
skip_endpoint "getAdventures"
skip_endpoint "sellItem"
skip_endpoint "auctionItem"
skip_endpoint "bidOnAuction"
skip_endpoint "getAuctions"

echo ""
echo "Quest API (5 endpoints):"
skip_endpoint "getActiveQuests"
skip_endpoint "completeQuest"
skip_endpoint "getQuestRewards"
skip_endpoint "skipQuest"
skip_endpoint "getQuestProgress"

echo ""
echo "Reports API (6 endpoints):"
skip_endpoint "getReports"
skip_endpoint "getReportDetails"
skip_endpoint "getUnreadCount"
skip_endpoint "markAsRead"
skip_endpoint "archive"
skip_endpoint "deleteReport"

echo ""
echo "Messages API (8 endpoints):"
skip_endpoint "getInbox"
skip_endpoint "sendMessage"
skip_endpoint "deleteMessage"
skip_endpoint "markAsRead"
skip_endpoint "getAllianceMessages"
skip_endpoint "sendAllianceMessage"
skip_endpoint "getMessageDetails"
skip_endpoint "getUnreadCount"

echo ""
echo "Statistics API (6 endpoints):"
skip_endpoint "getPlayerRankings"
skip_endpoint "getAllianceRankings"
skip_endpoint "getPlayerStats"
skip_endpoint "getAllianceStats"
skip_endpoint "getTop10"
skip_endpoint "getWorldStats"

echo ""
echo "============================================================="
echo "                    TEST SUMMARY"
echo "============================================================="
echo "Total Tests:   $TOTAL"
echo -e "${GREEN}Passed:        $PASSED${NC}"
echo -e "${RED}Failed:        $FAILED${NC}"
echo -e "${BLUE}Skipped:       $SKIPPED${NC} (not yet implemented)"
echo "============================================================="

if [ $FAILED -eq 0 ]; then
    success_rate=$(( PASSED * 100 / TOTAL ))
    echo -e "\n${GREEN}✅ ALL IMPLEMENTED TESTS PASSED!${NC}"
    echo "Implementation: ${success_rate}% complete (${PASSED}/${TOTAL} endpoints)"
    exit 0
else
    echo -e "\n${RED}❌ SOME TESTS FAILED${NC}"
    exit 1
fi
