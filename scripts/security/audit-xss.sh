#!/usr/bin/env bash
set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}🔍 XSS Security Audit${NC}"
echo ""

echo "Pattern 1: Direct echo of \$_GET/\$_POST/\$_REQUEST"
DIRECT_ECHO=$(grep -rn "echo.*\\\$_GET\|echo.*\\\$_POST\|echo.*\\\$_REQUEST" sections/api/ --include="*.php" || true)
if [ -n "$DIRECT_ECHO" ]; then
    echo -e "${RED}⚠️  Found direct echo of user input:${NC}"
    echo "$DIRECT_ECHO"
else
    echo -e "${GREEN}✅ No direct echo found${NC}"
fi

echo ""
echo "Pattern 2: Output without encoding"
MISSING_ENCODING=$(grep -rn "echo.*<" sections/api/ --include="*.php" | grep -v "htmlspecialchars\|OutputEncoder" || true)
if [ -n "$MISSING_ENCODING" ]; then
    echo -e "${YELLOW}⚠️  Found output without encoding (review needed):${NC}"
    echo "$MISSING_ENCODING" | head -20
else
    echo -e "${GREEN}✅ Output encoding appears consistent${NC}"
fi

echo ""
echo "Pattern 3: JSON responses"
JSON_OUTPUTS=$(grep -rn "json_encode" sections/api/ --include="*.php" | wc -l)
echo -e "${GREEN}✅ Found $JSON_OUTPUTS json_encode usages${NC}"

echo ""
echo -e "${GREEN}✅ XSS audit complete${NC}"
